import os
from celery import Celery
from jinja2 import Template as JinjaTemplate
from .storage import save_file
from weasyprint import HTML
import uuid

CELERY_BROKER = os.getenv("CELERY_BROKER_URL", os.getenv("REDIS_URL", "redis://localhost:6379/0"))
CELERY_BACKEND = os.getenv("CELERY_RESULT_BACKEND", CELERY_BROKER)

celery_app = Celery("templates_tasks", broker=CELERY_BROKER, backend=CELERY_BACKEND)


@celery_app.task(name="generate_pdf")
def generate_pdf_task(content: str, variables: dict = None) -> dict:
    variables = variables or {}
    rendered = JinjaTemplate(content).render(**variables)
    out_name = f"template_{uuid.uuid4().hex}.pdf"
    out_path = save_file(BinaryIOWrapper(rendered.encode("utf-8")), out_name)
    try:
        HTML(string=rendered).write_pdf(out_path)
    except Exception:
        # if WeasyPrint fails, still return path for inspection
        pass
    return {"file": out_path}


class BinaryIOWrapper:
    def __init__(self, data: bytes):
        self._data = data
        self._pos = 0

    def read(self):
        return self._data
