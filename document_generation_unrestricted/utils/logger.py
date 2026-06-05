import logging
import sys
from datetime import datetime
from pathlib import Path
from typing import Optional
from loguru import logger as loguru_logger
import json
import os

from config.settings import settings

class SecurityLogger:
    """
    Specialized logger for security events
    """
    
    def __init__(self):
        self.logger = logging.getLogger("security")
        self.logger.setLevel(logging.INFO)
        
        # Create security log file handler
        security_log_path = os.path.join(settings.LOG_DIR, "security.log")
        handler = logging.FileHandler(security_log_path)
        handler.setLevel(logging.INFO)
        
        # Create formatter
        formatter = logging.Formatter(
            '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
        )
        handler.setFormatter(formatter)
        
        # Add handler to logger
        if not self.logger.handlers:
            self.logger.addHandler(handler)
    
    def log_authentication_attempt(self, username: str, success: bool, ip_address: str):
        """
        Log authentication attempts
        """
        event = {
            "event_type": "authentication_attempt",
            "username": username,
            "success": success,
            "ip_address": ip_address,
            "timestamp": datetime.utcnow().isoformat()
        }
        
        if success:
            self.logger.info(f"Successful login: {json.dumps(event)}")
        else:
            self.logger.warning(f"Failed login attempt: {json.dumps(event)}")
    
    def log_document_access(self, user: str, document_id: str, action: str, ip_address: str):
        """
        Log document access events
        """
        event = {
            "event_type": "document_access",
            "user": user,
            "document_id": document_id,
            "action": action,
            "ip_address": ip_address,
            "timestamp": datetime.utcnow().isoformat()
        }
        
        self.logger.info(f"Document access: {json.dumps(event)}")
    
    def log_security_violation(self, violation_type: str, details: dict, ip_address: str):
        """
        Log security violations
        """
        event = {
            "event_type": "security_violation",
            "violation_type": violation_type,
            "details": details,
            "ip_address": ip_address,
            "timestamp": datetime.utcnow().isoformat()
        }
        
        self.logger.error(f"Security violation: {json.dumps(event)}")
    
    def log_data_export(self, user: str, data_type: str, record_count: int, ip_address: str):
        """
        Log data export events
        """
        event = {
            "event_type": "data_export",
            "user": user,
            "data_type": data_type,
            "record_count": record_count,
            "ip_address": ip_address,
            "timestamp": datetime.utcnow().isoformat()
        }
        
        self.logger.info(f"Data export: {json.dumps(event)}")

class AuditLogger:
    """
    Audit logger for compliance and tracking
    """
    
    def __init__(self):
        self.logger = logging.getLogger("audit")
        self.logger.setLevel(logging.INFO)
        
        # Create audit log file handler
        audit_log_path = os.path.join(settings.LOG_DIR, "audit.log")
        handler = logging.FileHandler(audit_log_path)
        handler.setLevel(logging.INFO)
        
        # Create formatter
        formatter = logging.Formatter(
            '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
        )
        handler.setFormatter(formatter)
        
        # Add handler to logger
        if not self.logger.handlers:
            self.logger.addHandler(handler)
    
    def log_user_action(self, user: str, action: str, resource: str, details: dict = None):
        """
        Log user actions for audit trail
        """
        event = {
            "event_type": "user_action",
            "user": user,
            "action": action,
            "resource": resource,
            "details": details or {},
            "timestamp": datetime.utcnow().isoformat()
        }
        
        self.logger.info(f"User action: {json.dumps(event)}")
    
    def log_system_change(self, change_type: str, details: dict, user: str = None):
        """
        Log system configuration changes
        """
        event = {
            "event_type": "system_change",
            "change_type": change_type,
            "details": details,
            "user": user,
            "timestamp": datetime.utcnow().isoformat()
        }
        
        self.logger.info(f"System change: {json.dumps(event)}")
    
    def log_data_modification(self, user: str, table: str, record_id: str, action: str, changes: dict = None):
        """
        Log data modifications
        """
        event = {
            "event_type": "data_modification",
            "user": user,
            "table": table,
            "record_id": record_id,
            "action": action,
            "changes": changes or {},
            "timestamp": datetime.utcnow().isoformat()
        }
        
        self.logger.info(f"Data modification: {json.dumps(event)}")

def setup_logging():
    """
    Setup application logging configuration
    """
    # Ensure log directory exists
    os.makedirs(settings.LOG_DIR, exist_ok=True)
    
    # Configure loguru for application logging
    loguru_logger.remove()  # Remove default handler
    
    # Add file handler
    loguru_logger.add(
        os.path.join(settings.LOG_DIR, "app.log"),
        rotation="10 MB",
        retention="30 days",
        level=settings.LOG_LEVEL,
        format="{time:YYYY-MM-DD HH:mm:ss} | {level} | {name}:{function}:{line} | {message}",
        backtrace=True,
        diagnose=True
    )
    
    # Add console handler for development
    if settings.DEBUG:
        loguru_logger.add(
            sys.stdout,
            level="DEBUG",
            format="<green>{time:HH:mm:ss}</green> | <level>{level}</level> | <cyan>{name}</cyan>:<cyan>{function}</cyan>:<cyan>{line}</cyan> | {message}"
        )
    
    # Add error file handler
    loguru_logger.add(
        os.path.join(settings.LOG_DIR, "errors.log"),
        rotation="10 MB",
        retention="90 days",
        level="ERROR",
        format="{time:YYYY-MM-DD HH:mm:ss} | {level} | {name}:{function}:{line} | {message}",
        backtrace=True,
        diagnose=True
    )
    
    # Return the configured logger
    return loguru_logger

def get_logger(name: str):
    """
    Get a logger instance with the specified name
    """
    return loguru_logger.bind(name=name)

def log_performance(func_name: str, execution_time: float, details: dict = None):
    """
    Log performance metrics
    """
    performance_logger = get_logger("performance")
    
    event = {
        "function": func_name,
        "execution_time": execution_time,
        "details": details or {},
        "timestamp": datetime.utcnow().isoformat()
    }
    
    if execution_time > 5.0:  # Log slow operations
        performance_logger.warning(f"Slow operation: {json.dumps(event)}")
    else:
        performance_logger.info(f"Performance: {json.dumps(event)}")

def log_api_request(method: str, endpoint: str, user: str, ip_address: str, 
                   status_code: int, response_time: float):
    """
    Log API requests
    """
    api_logger = get_logger("api")
    
    event = {
        "method": method,
        "endpoint": endpoint,
        "user": user,
        "ip_address": ip_address,
        "status_code": status_code,
        "response_time": response_time,
        "timestamp": datetime.utcnow().isoformat()
    }
    
    if status_code >= 400:
        api_logger.warning(f"API error: {json.dumps(event)}")
    else:
        api_logger.info(f"API request: {json.dumps(event)}")

class StructuredLogger:
    """
    Structured logger for consistent log formatting
    """
    
    def __init__(self, name: str):
        self.logger = get_logger(name)
        self.name = name
    
    def info(self, message: str, **kwargs):
        """
        Log info message with structured data
        """
        self._log("INFO", message, **kwargs)
    
    def warning(self, message: str, **kwargs):
        """
        Log warning message with structured data
        """
        self._log("WARNING", message, **kwargs)
    
    def error(self, message: str, **kwargs):
        """
        Log error message with structured data
        """
        self._log("ERROR", message, **kwargs)
    
    def debug(self, message: str, **kwargs):
        """
        Log debug message with structured data
        """
        self._log("DEBUG", message, **kwargs)
    
    def _log(self, level: str, message: str, **kwargs):
        """
        Internal method to log with structured data
        """
        log_data = {
            "message": message,
            "timestamp": datetime.utcnow().isoformat(),
            "logger": self.name,
            **kwargs
        }
        
        log_message = f"{message} | {json.dumps(kwargs) if kwargs else ''}"
        
        if level == "INFO":
            self.logger.info(log_message)
        elif level == "WARNING":
            self.logger.warning(log_message)
        elif level == "ERROR":
            self.logger.error(log_message)
        elif level == "DEBUG":
            self.logger.debug(log_message)

# Global logger instances
security_logger = SecurityLogger()
audit_logger = AuditLogger()

# Alias for compatibility
setup_logger = setup_logging

# Initialize logging on import
setup_logging()