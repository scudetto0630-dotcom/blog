#!/usr/bin/env python3
"""Summarize textlint findings without changing or suppressing its exit status."""
import argparse
import collections
import json
from pathlib import Path


def render(rows):
    if not isinstance(rows, list):
        raise ValueError('Expected the textlint JSON array; no successful review can be inferred.')
    count = collections.Counter()
    total = 0
    lines = ['## Japanese proofreading review', '',
             'This report does not change the textlint result. Findings require editorial review; they are not a website availability failure.', '']
    for row in rows:
        if not isinstance(row, dict) or not isinstance(row.get('messages'), list):
            raise ValueError('Malformed textlint output.')
        for message in row['messages']:
            count[message.get('ruleId', 'unknown')] += 1
            total += 1
    lines += [f'Files checked: {len(rows)}. Findings: {total}.', '', '| Rule | Findings |', '|---|---:|']
    for rule, number in count.most_common():
        lines.append(f'| {rule.replace(chr(124), chr(92) + chr(124))} | {number} |')
    lines += ['', 'See the japanese-proofreading artifact for each file, line and message.',
              'Conversational wording and product names need contextual review. No rule is disabled by this summary.']
    return '\n'.join(lines) + '\n'


if __name__ == '__main__':
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('input', type=Path)
    p.add_argument('--output', type=Path, required=True)
    args = p.parse_args()
    args.output.write_text(render(json.loads(args.input.read_text())))
