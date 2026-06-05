from sqlalchemy import Column, Integer, String, DateTime, Text, Enum, Boolean, JSON, ForeignKey
from sqlalchemy.sql import func
from sqlalchemy.orm import relationship
from config.database import Base
import enum
from datetime import datetime

class DocumentType(enum.Enum):
    """Document type enumeration"""
    ROSTER = "roster"
    SUMMARY = "summary"
    BENEFICIARIES = "beneficiaries"
    PROFILES = "profiles"
    CUSTOM = "custom"

class GenerationStatus(enum.Enum):
    """Document generation status enumeration"""
    PENDING = "pending"
    PROCESSING = "processing"
    COMPLETED = "completed"
    FAILED = "failed"
    CANCELLED = "cancelled"

class DocumentTemplate(Base):
    """Document template model"""
    __tablename__ = "document_templates"
    
    id = Column(Integer, primary_key=True, index=True)
    name = Column(String(100), nullable=False)
    description = Column(Text, nullable=True)
    document_type = Column(Enum(DocumentType), nullable=False)
    file_path = Column(String(255), nullable=False)
    version = Column(String(20), default="1.0")
    is_active = Column(Boolean, default=True)
    field_mappings = Column(JSON, nullable=True)  # Store field mapping configuration
    created_by = Column(String(50), nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    
    # Relationship
    generations = relationship("DocumentGeneration", back_populates="template")
    
    def __repr__(self):
        return f"<DocumentTemplate(id={self.id}, name='{self.name}', type='{self.document_type.value}')>"

class DocumentGeneration(Base):
    """Document generation job model"""
    __tablename__ = "document_generations"
    
    id = Column(Integer, primary_key=True, index=True)
    job_id = Column(String(36), unique=True, index=True, nullable=False)  # UUID
    template_id = Column(Integer, ForeignKey("document_templates.id"), nullable=False)
    document_type = Column(Enum(DocumentType), nullable=False)
    status = Column(Enum(GenerationStatus), default=GenerationStatus.PENDING)
    
    # Generation parameters
    filters = Column(JSON, nullable=True)  # Store filter criteria
    total_records = Column(Integer, default=0)
    processed_records = Column(Integer, default=0)
    
    # File information
    output_filename = Column(String(255), nullable=True)
    output_path = Column(String(500), nullable=True)
    file_size = Column(Integer, nullable=True)  # in bytes
    
    # Security settings
    is_password_protected = Column(Boolean, default=False)
    has_watermark = Column(Boolean, default=False)
    has_digital_signature = Column(Boolean, default=False)
    access_expires_at = Column(DateTime(timezone=True), nullable=True)
    
    # Timing information
    started_at = Column(DateTime(timezone=True), nullable=True)
    completed_at = Column(DateTime(timezone=True), nullable=True)
    processing_time = Column(Integer, nullable=True)  # in seconds
    
    # User and audit information
    requested_by = Column(String(50), nullable=False)
    user_ip = Column(String(45), nullable=True)
    user_agent = Column(String(500), nullable=True)
    
    # Error handling
    error_message = Column(Text, nullable=True)
    retry_count = Column(Integer, default=0)
    
    # Metadata
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    
    # Relationships
    template = relationship("DocumentTemplate", back_populates="generations")
    
    def __repr__(self):
        return f"<DocumentGeneration(id={self.id}, job_id='{self.job_id}', status='{self.status.value}')>"
    
    @property
    def progress_percentage(self):
        """Calculate progress percentage"""
        if self.total_records == 0:
            return 0
        return min(100, (self.processed_records / self.total_records) * 100)
    
    @property
    def is_completed(self):
        """Check if generation is completed"""
        return self.status in [GenerationStatus.COMPLETED, GenerationStatus.FAILED, GenerationStatus.CANCELLED]
    
    def to_dict(self):
        """Convert model to dictionary"""
        return {
            "id": self.id,
            "job_id": self.job_id,
            "template_id": self.template_id,
            "document_type": self.document_type.value,
            "status": self.status.value,
            "filters": self.filters,
            "total_records": self.total_records,
            "processed_records": self.processed_records,
            "progress_percentage": self.progress_percentage,
            "output_filename": self.output_filename,
            "output_path": self.output_path,
            "file_size": self.file_size,
            "is_password_protected": self.is_password_protected,
            "has_watermark": self.has_watermark,
            "has_digital_signature": self.has_digital_signature,
            "access_expires_at": self.access_expires_at.isoformat() if self.access_expires_at else None,
            "started_at": self.started_at.isoformat() if self.started_at else None,
            "completed_at": self.completed_at.isoformat() if self.completed_at else None,
            "processing_time": self.processing_time,
            "requested_by": self.requested_by,
            "error_message": self.error_message,
            "retry_count": self.retry_count,
            "created_at": self.created_at.isoformat() if self.created_at else None,
            "updated_at": self.updated_at.isoformat() if self.updated_at else None
        }