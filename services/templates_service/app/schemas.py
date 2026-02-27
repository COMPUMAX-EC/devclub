from typing import Optional, Dict
from pydantic import BaseModel


class TemplateCreate(BaseModel):
    name: str
    slug: Optional[str]
    type: Optional[str]


class TemplateRead(BaseModel):
    id: int
    name: str
    slug: Optional[str]
    type: Optional[str]


class TemplateVersionCreate(BaseModel):
    name: Optional[str]
    content: Optional[str]
    test_data_json: Optional[str]


class GenerateRequest(BaseModel):
    lang: str = "es"
    variables: Optional[Dict[str, str]] = {}
