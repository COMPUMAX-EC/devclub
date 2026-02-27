from fastapi import Depends, Header, HTTPException
from typing import Optional, Dict


def get_current_user(authorization: Optional[str] = Header(None)) -> Dict:
    """
    Simple auth stub for demo purposes.
    - If header `Authorization: Bearer admin-token` => admin with all permissions
    - If `Bearer user-token` => limited permissions
    In real system integrate with MODULE_A (OAuth/JWT/DB)
    """
    if not authorization:
        raise HTTPException(status_code=401, detail="Missing Authorization header")

    parts = authorization.split()
    if len(parts) != 2 or parts[0].lower() != "bearer":
        raise HTTPException(status_code=401, detail="Invalid Authorization header")

    token = parts[1]
    if token == "admin-token":
        return {"id": 1, "name": "admin", "permissions": ["templates.view", "templates.edit"]}
    if token == "user-token":
        return {"id": 2, "name": "user", "permissions": ["templates.view"]}

    raise HTTPException(status_code=403, detail="Invalid token")


def require_permission(permission: str):
    def _checker(user: Dict = Depends(get_current_user)):
        if permission not in user.get("permissions", []):
            raise HTTPException(status_code=403, detail="Insufficient permissions")
        return user

    return _checker
