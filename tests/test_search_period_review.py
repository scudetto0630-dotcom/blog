import importlib.util,unittest,tempfile,zipfile,datetime
from pathlib import Path
p=Path(__file__).resolve().parents[1]/'tools/search-period-review.py';s=importlib.util.spec_from_file_location('search_period_review',p);m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
class PeriodReview(unittest.TestCase):
 def sample(self,count):
  f=tempfile.NamedTemporaryFile(suffix='.zip',delete=False);f.close();self.addCleanup(lambda:Path(f.name).unlink());date=datetime.date(2026,1,1)
  with zipfile.ZipFile(f.name,'w') as z:z.writestr('daily.csv','Date,Clicks,Impressions\n'+''.join(f'{date+datetime.timedelta(days=i)},0,1\n' for i in range(count)))
  return f.name
 def test_missing_is_not_zero(self):
  d=m.analyze(self.sample(22));self.assertFalse(d['comparable']);self.assertIsNone(d['current']['impressions']);self.assertIsNone(d['previous']['clicks'])
 def test_equal_nonoverlap(self):
  d=m.analyze(self.sample(56));self.assertTrue(d['comparable']);self.assertEqual(d['current']['impressions'],28);self.assertLess(d['previous']['end'],d['current']['start']);self.assertFalse(d['ctr_test_ready'])
if __name__=='__main__':unittest.main()
