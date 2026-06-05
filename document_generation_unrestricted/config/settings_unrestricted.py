"""
Settings for Unrestricted Document Generation System
Separate configuration to avoid conflicts with main system
"""
import os
from pathlib import Path

# Base directory
BASE_DIR = Path(__file__).parent.parent

# Database settings (use same database but different table prefixes if needed)
DATABASE_URL = os.getenv("DATABASE_URL", "mysql+pymysql://root:@localhost/rotc_db")

# Document generation settings
TEMPLATE_DIR = BASE_DIR / "templates"
OUTPUT_DIR = BASE_DIR / "output"
UPLOAD_DIR = BASE_DIR / "uploads"
LOG_DIR = BASE_DIR / "logs"

# Security settings - More permissive for unrestricted access
DOCUMENT_ACCESS_EXPIRY_DAYS = 30  # Longer access period
MAX_DOCUMENT_SIZE_MB = 100  # Larger file size limit
ALLOW_UNRESTRICTED_ACCESS = True  # Flag to identify unrestricted system

# API settings
API_PREFIX = "/api/unrestricted"
DEBUG = True
CORS_ORIGINS = ["*"]  # More permissive CORS for admin access

# Logging
LOG_LEVEL = "INFO"
LOG_FORMAT = "%(asctime)s - %(name)s - %(levelname)s - %(message)s"

# File paths
STATIC_DIR = BASE_DIR / "static"
BACKUP_DIR = BASE_DIR / "backups"

# Create directories if they don't exist
for directory in [OUTPUT_DIR, UPLOAD_DIR, LOG_DIR, STATIC_DIR, BACKUP_DIR]:
    directory.mkdir(parents=True, exist_ok=True)

# System identification
SYSTEM_NAME = "ROTC Unrestricted Document Generation"
SYSTEM_VERSION = "1.0.0"
SYSTEM_DESCRIPTION = "Comprehensive document generation without status restrictions"