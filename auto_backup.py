#!/usr/bin/env python3
"""
ROTC QR Inventory System - Automated Backup Script
Creates ZIP backups of the entire system with comprehensive logging
"""

import os
import sys
import zipfile
import shutil
import datetime
import logging
from pathlib import Path

# Configuration
BACKUP_BASE_DIR = Path("backups")
LOG_DIR = Path("folderlog")
SOURCE_DIR = Path(".")
EXCLUDE_PATTERNS = [
    "__pycache__",
    ".git",
    "node_modules",
    "*.log",
    "backups",
    "folderlog",
    "*.tmp",
    "*.cache"
]

def setup_logging():
    """Setup comprehensive logging system"""
    # Create log directory if it doesn't exist
    LOG_DIR.mkdir(exist_ok=True)
    
    # Create timestamp for log file
    timestamp = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
    log_file = LOG_DIR / f"backup_log_{timestamp}.txt"
    
    # Configure logging
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(levelname)s - %(message)s',
        handlers=[
            logging.FileHandler(log_file, encoding='utf-8'),
            logging.StreamHandler(sys.stdout)
        ]
    )
    
    return logging.getLogger(__name__)

def should_exclude(file_path, exclude_patterns):
    """Check if file should be excluded from backup"""
    file_str = str(file_path).lower()
    
    for pattern in exclude_patterns:
        if pattern.startswith("*."):
            # Handle file extension patterns
            ext = pattern[1:]
            if file_str.endswith(ext):
                return True
        else:
            # Handle directory/file name patterns
            if pattern in file_str:
                return True
    
    return False

def create_backup_zip(source_dir, backup_file, logger):
    """Create ZIP backup of the source directory"""
    logger.info(f"Starting backup creation: {backup_file}")
    
    files_added = 0
    files_skipped = 0
    total_size = 0
    
    try:
        with zipfile.ZipFile(backup_file, 'w', zipfile.ZIP_DEFLATED) as zipf:
            for root, dirs, files in os.walk(source_dir):
                # Filter out excluded directories
                dirs[:] = [d for d in dirs if not should_exclude(Path(root) / d, EXCLUDE_PATTERNS)]
                
                for file in files:
                    file_path = Path(root) / file
                    
                    # Skip excluded files
                    if should_exclude(file_path, EXCLUDE_PATTERNS):
                        files_skipped += 1
                        logger.debug(f"Skipped: {file_path}")
                        continue
                    
                    try:
                        # Calculate relative path for ZIP
                        relative_path = file_path.relative_to(source_dir)
                        
                        # Add file to ZIP
                        zipf.write(file_path, relative_path)
                        
                        # Update statistics
                        files_added += 1
                        total_size += file_path.stat().st_size
                        
                        logger.debug(f"Added: {relative_path}")
                        
                    except Exception as e:
                        logger.warning(f"Failed to add {file_path}: {e}")
                        files_skipped += 1
        
        # Log backup statistics
        backup_size = backup_file.stat().st_size
        compression_ratio = (1 - backup_size / total_size) * 100 if total_size > 0 else 0
        
        logger.info(f"Backup completed successfully!")
        logger.info(f"Files added: {files_added}")
        logger.info(f"Files skipped: {files_skipped}")
        logger.info(f"Original size: {total_size / (1024*1024):.2f} MB")
        logger.info(f"Backup size: {backup_size / (1024*1024):.2f} MB")
        logger.info(f"Compression ratio: {compression_ratio:.1f}%")
        
        return True
        
    except Exception as e:
        logger.error(f"Backup creation failed: {e}")
        return False

def cleanup_old_backups(backup_dir, keep_count=10, logger=None):
    """Remove old backup files, keeping only the most recent ones"""
    if not backup_dir.exists():
        return
    
    # Get all backup files sorted by modification time (newest first)
    backup_files = sorted(
        [f for f in backup_dir.glob("*.zip") if f.is_file()],
        key=lambda x: x.stat().st_mtime,
        reverse=True
    )
    
    # Remove old backups
    if len(backup_files) > keep_count:
        for old_backup in backup_files[keep_count:]:
            try:
                old_backup.unlink()
                if logger:
                    logger.info(f"Removed old backup: {old_backup.name}")
            except Exception as e:
                if logger:
                    logger.warning(f"Failed to remove old backup {old_backup}: {e}")

def main():
    """Main backup function"""
    # Setup logging
    logger = setup_logging()
    
    logger.info("=" * 60)
    logger.info("ROTC QR Inventory System - Automated Backup")
    logger.info("=" * 60)
    
    # Create backup directory
    BACKUP_BASE_DIR.mkdir(exist_ok=True)
    
    # Generate backup filename with timestamp
    timestamp = datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
    backup_filename = f"rotc_inventory_backup_{timestamp}.zip"
    backup_file = BACKUP_BASE_DIR / backup_filename
    
    logger.info(f"Source directory: {SOURCE_DIR.absolute()}")
    logger.info(f"Backup file: {backup_file.absolute()}")
    logger.info(f"Log directory: {LOG_DIR.absolute()}")
    
    # Create backup
    success = create_backup_zip(SOURCE_DIR, backup_file, logger)
    
    if success:
        logger.info(f"Backup saved to: {backup_file}")
        
        # Cleanup old backups
        cleanup_old_backups(BACKUP_BASE_DIR, keep_count=10, logger=logger)
        
        logger.info("Backup process completed successfully!")
        return 0
    else:
        logger.error("Backup process failed!")
        return 1

if __name__ == "__main__":
    exit_code = main()
    sys.exit(exit_code)