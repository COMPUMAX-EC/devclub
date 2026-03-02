FROM python:3.11-slim

WORKDIR /app

# System deps for some Python packages
RUN apt-get update && apt-get install -y build-essential gcc git && rm -rf /var/lib/apt/lists/*

# Copy requirements
COPY requirements.txt /app/requirements.txt

# Try to install 'uv' (Astral) tool if available via pip, then install dependencies with uv if present,
# otherwise fallback to pip install.
RUN pip install --no-cache-dir uv || true \
    && if command -v uv >/dev/null 2>&1; then \
        uv install -r /app/requirements.txt || pip install --no-cache-dir -r /app/requirements.txt; \
    else \
        pip install --no-cache-dir -r /app/requirements.txt; \
    fi

# Copy application code
COPY . /app

EXPOSE 8000

CMD ["sh", "-c", "if command -v uv >/dev/null 2>&1; then uv run app.main:app --host 0.0.0.0 --port 8000; else uvicorn app.main:app --host 0.0.0.0 --port 8000; fi"]
FROM python:3.11-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . /app

EXPOSE 8000

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
