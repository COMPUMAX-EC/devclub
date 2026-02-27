# Templates Service (MODULE_C) — Scaffold

Minimal FastAPI scaffold for Yastubo MODULE_C: templates, versioning, file uploads and PDF generation (Celery).

Quick start (dev):

1. Copy `.env.example` to `.env` and adjust if needed.
2. Run with Docker Compose:

```bash
docker-compose up --build
```

Service available at `http://localhost:8000` and OpenAPI at `/docs`.

Notes:
- This is a minimal scaffold: adapt DB to Postgres and add RBAC, audits and production readiness.
- WeasyPrint may require system packages in Docker image for full PDF support.
