from fastapi import APIRouter, HTTPException, Depends, status
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from sqlalchemy.orm import Session
from pydantic import BaseModel
from typing import Optional
from datetime import datetime, timedelta

from config.database import get_db
from utils.security import create_access_token, verify_password, get_password_hash, verify_token
from utils.logger import get_logger

router = APIRouter()
security = HTTPBearer()
logger = get_logger(__name__)

# Pydantic models for request/response
class LoginRequest(BaseModel):
    username: str
    password: str
    remember_me: Optional[bool] = False

class TokenResponse(BaseModel):
    access_token: str
    token_type: str
    expires_in: int
    user_info: dict

class APIKeyRequest(BaseModel):
    name: str
    expires_days: Optional[int] = 30

class APIKeyResponse(BaseModel):
    api_key: str
    name: str
    expires_at: datetime
    created_at: datetime

@router.post("/login", response_model=TokenResponse)
async def login(
    login_data: LoginRequest,
    db: Session = Depends(get_db)
):
    """
    Authenticate user and return access token
    """
    try:
        # Note: This is a simplified example. In production, you would:
        # 1. Query the users table from the existing ROTC database
        # 2. Verify the password against the stored hash
        # 3. Check user permissions and roles
        
        # For now, we'll use a basic authentication check
        # This should be replaced with actual database user verification
        
        # Example user verification (replace with actual database query)
        if login_data.username == "admin" and login_data.password == "admin123":
            user_data = {
                "user_id": 1,
                "username": "admin",
                "role": "admin",
                "permissions": ["document_generate", "template_manage", "user_manage"]
            }
        elif login_data.username == "officer" and login_data.password == "officer123":
            user_data = {
                "user_id": 2,
                "username": "officer",
                "role": "officer",
                "permissions": ["document_generate", "document_view"]
            }
        else:
            logger.warning(f"Failed login attempt for username: {login_data.username}")
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid username or password"
            )
        
        # Create access token
        expires_delta = timedelta(hours=24) if login_data.remember_me else timedelta(minutes=30)
        access_token = create_access_token(
            data={"sub": user_data["username"], "user_data": user_data},
            expires_delta=expires_delta
        )
        
        logger.info(f"Successful login for user: {login_data.username}")
        
        return TokenResponse(
            access_token=access_token,
            token_type="bearer",
            expires_in=int(expires_delta.total_seconds()),
            user_info=user_data
        )
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Login error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Internal server error during authentication"
        )

@router.post("/verify-token")
async def verify_user_token(
    credentials: HTTPAuthorizationCredentials = Depends(security)
):
    """
    Verify the provided token and return user information
    """
    try:
        token = credentials.credentials
        user_data = verify_token(token)
        
        if not user_data:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid or expired token"
            )
        
        return {
            "valid": True,
            "user_data": user_data,
            "message": "Token is valid"
        }
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Token verification error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Error verifying token"
        )

@router.post("/generate-api-key", response_model=APIKeyResponse)
async def generate_api_key(
    api_key_data: APIKeyRequest,
    credentials: HTTPAuthorizationCredentials = Depends(security),
    db: Session = Depends(get_db)
):
    """
    Generate a new API key for programmatic access
    """
    try:
        # Verify user token
        token = credentials.credentials
        user_data = verify_token(token)
        
        if not user_data or user_data.get("role") != "admin":
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Only administrators can generate API keys"
            )
        
        # Generate API key (simplified - in production, store in database)
        api_key = create_access_token(
            data={
                "sub": f"api_key_{api_key_data.name}",
                "type": "api_key",
                "created_by": user_data["username"]
            },
            expires_delta=timedelta(days=api_key_data.expires_days)
        )
        
        expires_at = datetime.utcnow() + timedelta(days=api_key_data.expires_days)
        
        logger.info(f"API key generated: {api_key_data.name} by {user_data['username']}")
        
        return APIKeyResponse(
            api_key=api_key,
            name=api_key_data.name,
            expires_at=expires_at,
            created_at=datetime.utcnow()
        )
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"API key generation error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Error generating API key"
        )

@router.post("/logout")
async def logout(
    credentials: HTTPAuthorizationCredentials = Depends(security)
):
    """
    Logout user (in a stateless JWT system, this is mainly for logging)
    """
    try:
        token = credentials.credentials
        user_data = verify_token(token)
        
        if user_data:
            logger.info(f"User logged out: {user_data.get('username', 'unknown')}")
        
        return {"message": "Successfully logged out"}
        
    except Exception as e:
        logger.error(f"Logout error: {str(e)}")
        return {"message": "Logged out"}

@router.get("/me")
async def get_current_user(
    credentials: HTTPAuthorizationCredentials = Depends(security)
):
    """
    Get current user information
    """
    try:
        token = credentials.credentials
        user_data = verify_token(token)
        
        if not user_data:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token"
            )
        
        return {
            "user_data": user_data,
            "authenticated": True
        }
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Get current user error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Error retrieving user information"
        )