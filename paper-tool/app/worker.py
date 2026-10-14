import logging
import threading
from .demo import make_result
from .models import STAGES

class DemoWorker:
    """Durable steps for synthetic runs; no research or verification engine is invoked."""
    def __init__(self, store, delay=0.8):
        self.store, self.delay = store, delay
        self.stop_event = threading.Event()
        self.thread = threading.Thread(target=self.run, daemon=True, name='demo-worker')

    def start(self): self.thread.start()
    def stop(self):
        self.stop_event.set()
        self.thread.join(timeout=5)

    def run(self):
        while not self.stop_event.is_set():
            job = None
            try:
                job = self.store.next_job()
                if not job:
                    self.stop_event.wait(0.15)
                    continue
                self.store.update_job(job['id'], 'running', job['completed_steps'])
                for step in range(job['completed_steps'], len(STAGES)):
                    if self.stop_event.wait(self.delay): break
                    result = make_result(job['snapshot']) if step == len(STAGES) - 1 else None
                    self.store.update_job(job['id'], 'completed' if result else 'running', step + 1, result)
            except Exception:
                logging.exception('demo_worker_failure')
                try:
                    if job:
                        self.store.update_job(job['id'], 'failed', job['completed_steps'], error='デモ処理を完了できませんでした。履歴から入力を再利用してください。')
                except Exception: logging.exception('demo_worker_error_persistence_failure')
                self.stop_event.wait(0.5)
