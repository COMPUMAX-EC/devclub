import os
from pathlib import Path
from typing import BinaryIO

STORAGE_PATH = os.getenv("STORAGE_PATH", "/data/storage")
Path(STORAGE_PATH).mkdir(parents=True, exist_ok=True)


def save_file(fileobj: BinaryIO, filename: str) -> str:
    dest = Path(STORAGE_PATH) / filename
    with open(dest, "wb") as f:
        f.write(fileobj.read())
    return str(dest)


def get_file_path(path: str) -> str:
    return str(Path(path))
