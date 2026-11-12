"""Keep intentional retirements distinct from HTTP/network failures."""
import importlib.util
from pathlib import Path
import unittest
from unittest.mock import patch
from urllib.error import HTTPError
from io import BytesIO

spec=importlib.util.spec_from_file_location('public_audit',Path(__file__).resolve().parents[1]/'tools/blog-audit.py')
mod=importlib.util.module_from_spec(spec);spec.loader.exec_module(mod)

class StatusTests(unittest.TestCase):
    def test_http_error_keeps_real_status(self):
        error=HTTPError('https://hirogarushumi.com/gone/',404,'Not Found',{},BytesIO(b'gone'))
        with patch.object(mod.urllib.request,'urlopen',side_effect=error):
            self.assertEqual(mod.fetch(error.url),(404,'gone'))

    def test_retired_page_is_expected_404(self):
        with patch.object(mod,'fetch',side_effect=lambda u:(404,'') if u.endswith('/hsh-editorial/') else (200,'')):
            self.assertEqual(mod.audit('https://hirogarushumi.com')['failures'],0)

    def test_retired_page_reappearing_is_reported(self):
        with patch.object(mod,'fetch',return_value=(200,'')):
            rows=mod.audit('https://hirogarushumi.com')['checks']
        retired=next(r for r in rows if r['url'].endswith('/hsh-editorial/'))
        self.assertTrue(retired['errors'])

if __name__=='__main__':unittest.main()
