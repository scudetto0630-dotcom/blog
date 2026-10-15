import importlib.util
from pathlib import Path
import unittest
spec=importlib.util.spec_from_file_location('proofread',Path(__file__).resolve().parents[1]/'tools/proofread-site.py');module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
class ExtractionTests(unittest.TestCase):
    def extract(self,html):
        parser=module.Parse();parser.feed(html);return module.walk(parser.root)
    def test_inline_link_preserves_complete_sentence(self):
        rows=self.extract('<p>詳細は、<a href="/profile/">プロフィール</a>をご覧ください。</p>')
        self.assertEqual(rows,['詳細は、プロフィールをご覧ください。'])
    def test_navigation_is_a_label_not_an_incomplete_sentence(self):
        rows=self.extract('<nav><a href="/">トップページ</a></nav><p>本文です。</p>')
        self.assertEqual(rows,['### トップページ','本文です。'])
    def test_scripts_and_hidden_share_buttons_are_excluded(self):
        rows=self.extract('<script>wrong prose</script><div class="sns-share">隠されたボタン</div><p>公開された本文です。</p>')
        self.assertEqual(rows,['公開された本文です。'])
    def test_linked_image_alternative_text_is_retained(self):
        self.assertEqual(self.extract('<a href="/full.webp"><img alt="確認手順のイラスト" src="/small.webp"></a>'),['### 確認手順のイラスト'])
    def test_nested_card_preserves_separate_heading_and_description(self):
        rows=self.extract('<a href="/cat/"><h2>猫との暮らし</h2><p>猫用品の選び方を紹介します。</p></a>')
        self.assertEqual(rows,['## 猫との暮らし','猫用品の選び方を紹介します。'])
if __name__=='__main__':unittest.main()
