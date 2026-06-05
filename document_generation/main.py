from fastapi import FastAPI, HTTPException, Depends, Security
from fastapi.middleware.cors import CORSMiddleware
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from fastapi.responses import FileResponse
import uvicorn
from pathlib import Path
import os
from datetime import datetime

# Import our modules
from api.routes import document_routes, auth_routes
from config.database import engine, Base
from config.settings import settings
from utils.logger import setup_logger
from utils.security import verify_token

# Initialize logger
logger = setup_logger()

# Create FastAPI app
app = FastAPI(
    title="ROTC Document Generation System",
    description="High-performance document generation for ROTC After Enrollment Reports (AER)",
    version="1.0.0",
    docs_url="/docs" if settings.DEBUG else None,
    redoc_url="/redoc" if settings.DEBUG else None
)

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.ALLOWED_ORIGINS,
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE"],
    allow_headers=["*"],
)

# Security
security = HTTPBearer()

# Create database tables
@app.on_event("startup")
async def startup_event():
    """Initialize database and create tables on startup"""
    try:
        Base.metadata.create_all(bind=engine)
        logger.info("Database tables created successfully")
    except Exception as e:
        logger.error(f"Failed to create database tables: {e}")
        raise

# Health check endpoint
@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "timestamp": datetime.utcnow().isoformat(),
        "version": "1.0.0"
    }

# Protected endpoint example
@app.get("/protected")
async def protected_route(
    credentials: HTTPAuthorizationCredentials = Security(security)
):
    """Example protected endpoint"""
    token = credentials.credentials
    user_data = verify_token(token)
    if not user_data:
        raise HTTPException(status_code=401, detail="Invalid token")
    
    return {"message": "Access granted", "user": user_data}

# Include routers
app.include_router(auth_routes.router, prefix="/api/auth", tags=["Authentication"])
app.include_router(document_routes.router, prefix="/api/documents", tags=["Documents"])

# Root endpoint
@app.get("/")
async def root():
    """Root endpoint with API information"""
    return {
        "message": "ROTC Document Generation API",
        "version": "1.0.0",
        "docs": "/docs" if settings.DEBUG else "Documentation disabled in production",
        "health": "/health"
    }

if __name__ == "__main__":
    uvicorn.run(
        "main:app",
        host=settings.HOST,
        port=settings.PORT,
        reload=settings.DEBUG,
        log_level="info"
    )