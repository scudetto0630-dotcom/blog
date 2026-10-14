"""Local HTTP/browser fixture: replaces external retrieval and AI with mocks only."""
import os
import sys
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]));sys.path.insert(0,str(Path(__file__).resolve().parent))
import server
from app.engine.jobs import ResearchJobs
from test_research_jobs import Client,Judge
factory=(lambda id:Judge()) if '--mock-classifier' in sys.argv else None
if '--mock-classifier' in sys.argv:sys.argv.remove('--mock-classifier')
server.ResearchJobs=lambda path,bundle:ResearchJobs(path,bundle,Client(),factory)
os.environ['PAPERDESK_RESEARCH_ENGINE']='1'
server.main()
