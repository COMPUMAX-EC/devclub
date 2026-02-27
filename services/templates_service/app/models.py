from typing import Optional
from datetime import datetime
from sqlmodel import SQLModel, Field, Relationship


class Template(SQLModel, table=True):
    id: Optional[int] = Field(default=None, primary_key=True)
    name: str
    slug: Optional[str] = None
    type: Optional[str] = None
    test_data_json: Optional[str] = None
    active_template_version_id: Optional[int] = None
    created_at: datetime = Field(default_factory=datetime.utcnow)
    updated_at: datetime = Field(default_factory=datetime.utcnow)


class TemplateVersion(SQLModel, table=True):
    id: Optional[int] = Field(default=None, primary_key=True)
    template_id: int = Field(foreign_key="template.id")
    name: Optional[str] = None
    content: Optional[str] = None
    test_data_json: Optional[str] = None
    created_at: datetime = Field(default_factory=datetime.utcnow)


class File(SQLModel, table=True):
    id: Optional[int] = Field(default=None, primary_key=True)
    uuid: Optional[str] = None
    disk: Optional[str] = None
    path: Optional[str] = None
    original_name: Optional[str] = None
    mime_type: Optional[str] = None
    size: Optional[int] = None
    uploaded_by: Optional[int] = None
    meta: Optional[str] = None
    created_at: datetime = Field(default_factory=datetime.utcnow)
