from sqlmodel import Session, select
from . import models


def create_template(session: Session, name: str, slug: str = None, type: str = None):
    tpl = models.Template(name=name, slug=slug, type=type)
    session.add(tpl)
    session.commit()
    session.refresh(tpl)
    return tpl


def get_templates(session: Session):
    return session.exec(select(models.Template)).all()


def get_template(session: Session, template_id: int):
    return session.get(models.Template, template_id)


def create_version(session: Session, template_id: int, name: str = None, content: str = None, test_data_json: str = None):
    v = models.TemplateVersion(template_id=template_id, name=name, content=content, test_data_json=test_data_json)
    session.add(v)
    session.commit()
    session.refresh(v)
    return v
