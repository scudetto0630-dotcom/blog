import io
import json
import sqlite3
import tempfile
import unittest
import zipfile
from pathlib import Path
from app.training.ingest import analyze, records, read_archive

TEXT='2024/06\nhttps://pubmed.ncbi.nlm.nih.gov/12345678/\nhttps://pmc.ncbi.nlm.nih.gov/articles/PMC1/figure/F1/\n日本語の題名\nEnglish title.\n\n'

class TeacherTests(unittest.TestCase):
    def test_record_fields(self):
        paper=records(TEXT)[0]
        self.assertEqual(paper['pmid'],'12345678');self.assertEqual(paper['date'],'2024/06')
        self.assertEqual(paper['title_ja'],'日本語の題名');self.assertEqual(paper['title_en'],'English title.')
        self.assertEqual(len(paper['figure_urls']),1)
    def test_missing_date_is_not_inherited(self):
        second='https://pubmed.ncbi.nlm.nih.gov/87654321/\n日本語\nEnglish two.\n'
        self.assertIsNone(records(TEXT+second)[1]['date'])
    def test_nested_archive_and_path_safety(self):
        with tempfile.TemporaryDirectory() as temp:
            inner=io.BytesIO()
            with zipfile.ZipFile(inner,'w') as z:z.writestr('論文一覧_肌.txt',TEXT)
            p=Path(temp)/'nested.zip'
            with zipfile.ZipFile(p,'w') as z:z.writestr('inner.zip',inner.getvalue())
            self.assertEqual(len(list(read_archive(p))),1)
            with zipfile.ZipFile(p,'w') as z:z.writestr('../bad.txt',TEXT)
            with self.assertRaises(ValueError):list(read_archive(p))
    def test_archive_limits(self):
        with tempfile.TemporaryDirectory() as temp:
            p=Path(temp)/'large.zip'
            with zipfile.ZipFile(p,'w') as z:z.writestr('file.txt',TEXT)
            with self.assertRaises(ValueError):list(read_archive(p,max_bytes=10))
    def test_unknown_not_excluded_and_pmid_split(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);source=root/'source.txt';source.write_text(TEXT)
            second=TEXT.replace('12345678','87654321')
            ai=root/'ai.zip';human=root/'human.zip'
            with zipfile.ZipFile(ai,'w') as z:z.writestr('論文一覧_肌_ヒト試験効果あり_2件.txt',TEXT+second)
            with zipfile.ZipFile(human,'w') as z:
                z.writestr('論文一覧_肌_ヒト試験効果あり_1件.txt',TEXT)
                z.writestr('論文一覧_痛み_ヒト試験効果あり_1件.txt',TEXT)
                z.writestr('使用しない/論文一覧_眼_ヒト試験効果あり_1件.txt',second)
            output=root/'out';r=analyze(source,ai,human,output)
            self.assertFalse(r['model_trained']);self.assertEqual(r['paid_api_calls'],0)
            self.assertIn('痛み',r['unmapped_categories'])
            diff=json.loads((output/'pmid-differences.json').read_text())
            self.assertEqual(next(d for d in diff if d['pmid']=='87654321')['teacher_status'],'folder_semantics_unconfirmed')
            with sqlite3.connect(output/'teacher.sqlite3') as db:self.assertEqual(db.execute('SELECT count(*) FROM splits').fetchone()[0],1)
            analyze(source,ai,human,output)
            with sqlite3.connect(output/'teacher.sqlite3') as db:self.assertEqual(db.execute('SELECT count(*) FROM splits').fetchone()[0],1)
    def test_filename_counts_come_from_content(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);source=root/'source.txt';source.write_text(TEXT)
            ai=root/'ai.zip';human=root/'human.zip'
            with zipfile.ZipFile(ai,'w') as z:z.writestr('論文一覧_肌.txt',TEXT)
            with zipfile.ZipFile(human,'w') as z:z.writestr('論文一覧_眼_ヒト試験効果あり_1件.txt','')
            r=analyze(source,ai,human,root/'out')
            self.assertEqual(r['human_main_pmids'],0);self.assertEqual(r['warnings'][0]['actual'],0)

if __name__=='__main__':unittest.main()
