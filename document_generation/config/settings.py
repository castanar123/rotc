from pydantic_settings import BaseSettings
from typing import List
import os
from pathlib import Path

class Settings(BaseSettings):
    """Application settings with environment variable support"""
    
    # Application settings
    APP_NAME: str = "ROTC Document Generation System"
    VERSION: str = "1.0.0"
    DEBUG: bool = True
    HOST: str = "127.0.0.1"
    PORT: int = 8001
    
    # Database settings
    DB_HOST: str = "localhost"
    DB_PORT: int = 3306  # Match local XAMPP MySQL port
    DB_USER: str = "root"
    DB_PASSWORD: str = ""
    DB_NAME: str = "rotc_db"
    
    @property
    def DATABASE_URL(self) -> str:
        return f"mysql+pymysql://{self.DB_USER}:{self.DB_PASSWORD}@{self.DB_HOST}:{self.DB_PORT}/{self.DB_NAME}"
    
    # Security settings
    SECRET_KEY: str = "your-secret-key-change-in-production"
    ALGORITHM: str = "HS256"
    ACCESS_TOKEN_EXPIRE_MINUTES: int = 30
    API_KEY_EXPIRE_DAYS: int = 30
    
    # CORS settings
    ALLOWED_ORIGINS: List[str] = [
        "http://localhost:8000",
        "http://127.0.0.1:8000",
        "http://localhost:3000",
        "http://127.0.0.1:3000"
    ]
    
    # File settings
    UPLOAD_DIR: str = "uploads"
    TEMPLATE_DIR: str = "templates"
    OUTPUT_DIR: str = "output"
    MAX_FILE_SIZE: int = 10 * 1024 * 1024  # 10MB
    ALLOWED_EXTENSIONS: List[str] = [".docx", ".doc", ".pdf"]
    
    # Document generation settings
    MAX_RECORDS_PER_DOCUMENT: int = 5000
    GENERATION_TIMEOUT: int = 300  # 5 minutes
    CONCURRENT_GENERATIONS: int = 10
    
    # Security settings
    ENABLE_2FA: bool = True
    PASSWORD_MIN_LENGTH: int = 8
    MAX_LOGIN_ATTEMPTS: int = 5
    LOCKOUT_DURATION: int = 900  # 15 minutes
    
    # Logging settings
    LOG_LEVEL: str = "INFO"
    LOG_FILE: str = "logs/app.log"
    LOG_ROTATION: str = "1 day"
    LOG_RETENTION: str = "30 days"
    
    # Rate limiting
    RATE_LIMIT_PER_MINUTE: int = 60
    RATE_LIMIT_PER_HOUR: int = 1000
    
    # Document security
    ENABLE_WATERMARK: bool = True
    ENABLE_DIGITAL_SIGNATURE: bool = True
    DOCUMENT_PASSWORD_LENGTH: int = 12
    
    # Compliance settings
    DATA_RETENTION_DAYS: int = 2555  # 7 years for FERPA compliance
    AUDIT_LOG_RETENTION_DAYS: int = 2555
    ENABLE_GDPR_COMPLIANCE: bool = True
    
    class Config:
        env_file = ".env"
        env_file_encoding = "utf-8"
        case_sensitive = True

# Create settings instance
settings = Settings()

# Ensure directories exist
for directory in [settings.UPLOAD_DIR, settings.TEMPLATE_DIR, settings.OUTPUT_DIR, "logs"]:
    Path(directory).mkdir(parents=True, exist_ok=True)
