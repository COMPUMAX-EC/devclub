from fastapi import APIRouter, Depends, HTTPException, UploadFile, File as UploadFileType, BackgroundTasks
from sqlmodel import Session
from typing import List
from .. import db, crud, schemas, tasks
from ..storage import save_file

router = APIRouter()


@router.on_event("startup")
def on_startup():
    db.init_db()


def get_db():
    yield from db.get_session()


@router.post("/", response_model=schemas.TemplateRead)
def create_template(payload: schemas.TemplateCreate, session: Session = Depends(get_db)):
    tpl = crud.create_template(session, name=payload.name, slug=payload.slug, type=payload.type)
    return tpl


@router.get("/", response_model=List[schemas.TemplateRead])
def list_templates(session: Session = Depends(get_db)):
    return crud.get_templates(session)


@router.post("/{template_id}/versions", response_model=dict)
def create_version(template_id: int, payload: schemas.TemplateVersionCreate, session: Session = Depends(get_db)):
    tpl = crud.get_template(session, template_id)
    if not tpl:
        raise HTTPException(status_code=404, detail="Template not found")
    v = crud.create_version(session, template_id=template_id, name=payload.name, content=payload.content, test_data_json=payload.test_data_json)
    return {"id": v.id, "template_id": v.template_id}


@router.post("/{template_id}/versions/{version_id}/generate", response_model=dict)
def generate_pdf(template_id: int, version_id: int, payload: schemas.GenerateRequest, background_tasks: BackgroundTasks, session: Session = Depends(get_db)):
    v = session.get("TemplateVersion", version_id)
    # simple lookup via SQLModel
    tv = session.get(object, version_id)
    # fallback: fetch from crud
    from ..models import TemplateVersion
    tv = session.get(TemplateVersion, version_id)
    if not tv:
        raise HTTPException(status_code=404, detail="Version not found")

    # schedule celery task
    async_result = tasks.generate_pdf_task.apply_async(args=[tv.content or "", payload.variables or {}])
    return {"task_id": async_result.id}


@router.post("/{template_id}/versions/{version_id}/files")
def upload_file(template_id: int, version_id: int, upload: UploadFile, session: Session = Depends(get_db)):
    content = upload.file.read()
    path = save_file(BinaryIOWrapper(content), upload.filename)
    return {"path": path}


class BinaryIOWrapper:
    def __init__(self, data: bytes):
        self._data = data

    def read(self):
        return self._data
