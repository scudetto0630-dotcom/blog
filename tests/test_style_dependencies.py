"""Prevent recursive WordPress style resolution across plugin components."""
import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / 'hirogaru-home'

def style_graph():
    graph = {}
    for source in ROOT.glob('*.php'):
        for handle, dependencies in re.findall(
            r"wp_enqueue_style\(\s*'([^']+)'\s*,[^;]*?array\(([^)]*)\)",
            source.read_text(),
        ):
            graph.setdefault(handle, set()).update(re.findall(r"'([^']+)'", dependencies))
    return graph

class StyleDependencyTests(unittest.TestCase):
    def test_public_style_graph_is_acyclic(self):
        graph = style_graph()
        self.assertIn('hsh-palette', graph)
        visiting, visited = set(), set()
        def visit(handle, path):
            self.assertNotIn(handle, visiting, 'Style dependency cycle: ' + ' -> '.join(path + [handle]))
            if handle in visited:
                return
            visiting.add(handle)
            for dependency in graph.get(handle, ()):
                visit(dependency, path + [handle])
            visiting.remove(handle)
            visited.add(handle)
        for handle in graph:
            visit(handle, [])

    def test_final_palette_follows_every_public_component(self):
        graph = style_graph()
        def ancestors(handle):
            result = set()
            pending = list(graph.get(handle, ()))
            while pending:
                current = pending.pop()
                if current in result:
                    continue
                result.add(current)
                pending.extend(graph.get(current, ()))
            return result
        self.assertTrue({'hsh-reader', 'hsh-brushup', 'hsh-studio', 'hsh-quality'} <= ancestors('hsh-palette'))

if __name__ == '__main__':
    unittest.main()
