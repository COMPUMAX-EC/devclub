import os
from fastapi import FastAPI
from starlette.middleware.cors import CORSMiddleware

from .routers import templates

def create_app() -> FastAPI:
    app = FastAPI(title="Yastubo Templates Service")

    app.add_middleware(
        CORSMiddleware,
        allow_origins=["*"],
        allow_credentials=True,
        allow_methods=["*"],
        allow_headers=["*"],
    )

    app.include_router(templates.router, prefix="/templates", tags=["templates"])

    return app

app = create_app()
