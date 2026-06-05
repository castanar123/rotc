from fastapi import APIRouter, HTTPException, Depends, status, BackgroundTasks, Query
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from fastapi.responses import FileResponse
from sqlalchemy.orm import Session
from pydantic import BaseModel
from typing import Optional, List, Dict, Any
from datetime import datetime
import uuid
import os

from config.database import get_db
from models.cadet import CadetProfile, CadetStatus, YearLevel
from models.document import DocumentGeneration, DocumentTemplate, DocumentType, GenerationStatus
from services.document_generator import DocumentGeneratorService
from utils.security import verify_token
from utils.logger import get_logger

router = APIRouter()
security = HTTPBearer()
logger = get_logger(__name__)

# Pydantic models for request/response
class DocumentGenerationRequest(BaseModel):
    template_id: int
    document_type: DocumentType
    filters: Optional[Dict[str, Any]] = {}
    security_options: Optional[Dict[str, Any]] = {
        "password_protected": False,
        "watermark": True,
        "digital_signature": False
    }

class DocumentGenerationResponse(BaseModel):
    job_id: str
    status: str
    message: str
    estimated_records: int

class DocumentStatusResponse(BaseModel):
    job_id: str
    status: str
    progress_percentage: float
    total_records: int
    processed_records: int
    output_filename: Optional[str]
    download_url: Optional[str]
    error_message: Optional[str]
    processing_time: Optional[int]

class CadetFilterRequest(BaseModel):
    semester: Optional[str] = None
    academic_year: Optional[str] = None
    course: Optional[str] = None
    year_level: Optional[YearLevel] = None
    platoon: Optional[str] = None
    status: Optional[CadetStatus] = CadetStatus.ACTIVE
    limit: Optional[int] = 1000
    offset: Optional[int] = 0

@router.post("/generate", response_model=DocumentGenerationResponse)
async def generate_document(
    request: DocumentGenerationRequest,
    background_tasks: BackgroundTasks,
    credentials: HTTPAuthorizationCredentials = Depends(security),
    db: Session = Depends(get_db)
):
    """
    Start document generation process
    """
    try:
        # Verify user token
        token = credentials.credentials
        user_data = verify_token(token)
        
        if not user_data:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token"
            )
        
        # Check permissions - UNRESTRICTED VERSION: Allow broader access
        # Only require basic document access, not strict generation permissions
        if "document_access" not in user_data.get("permissions", []) and "document_generate" not in user_data.get("permissions", []):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Insufficient permissions to access unrestricted document generation"
            )
        
        # Verify template exists
        template = db.query(DocumentTemplate).filter(
            DocumentTemplate.id == request.template_id,
            DocumentTemplate.is_active == True
        ).first()
        
        if not template:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Template not found or inactive"
            )
        
        # Estimate record count based on filters
        query = db.query(CadetProfile)
        
        # Apply filters
        if request.filters.get("semester"):
            query = query.filter(CadetProfile.semester == request.filters["semester"])
        if request.filters.get("academic_year"):
            query = query.filter(CadetProfile.academic_year == request.filters["academic_year"])
        if request.filters.get("course"):
            query = query.filter(CadetProfile.course == request.filters["course"])
        if request.filters.get("year_level"):
            query = query.filter(CadetProfile.year_level == request.filters["year_level"])
        if request.filters.get("platoon"):
            query = query.filter(CadetProfile.platoon == request.filters["platoon"])
        if request.filters.get("status"):
            query = query.filter(CadetProfile.status == request.filters["status"])
        
        estimated_records = query.count()
        
        if estimated_records == 0:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="No records found matching the specified filters"
            )
        
        # Create generation job
        job_id = str(uuid.uuid4())
        generation_job = DocumentGeneration(
            job_id=job_id,
            template_id=request.template_id,
            document_type=request.document_type,
            status=GenerationStatus.PENDING,
            filters=request.filters,
            total_records=estimated_records,
            is_password_protected=request.security_options.get("password_protected", False),
            has_watermark=request.security_options.get("watermark", True),
            has_digital_signature=request.security_options.get("digital_signature", False),
            requested_by=user_data["username"]
        )
        
        db.add(generation_job)
        db.commit()
        db.refresh(generation_job)
        
        # Start background task for document generation
        background_tasks.add_task(
            DocumentGeneratorService.generate_document_async,
            job_id,
            request.filters,
            request.security_options
        )
        
        logger.info(f"Document generation started: {job_id} by {user_data['username']}")
        
        return DocumentGenerationResponse(
            job_id=job_id,
            status="pending",
            message="Document generation started",
            estimated_records=estimated_records
        )
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Document generation error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Error starting document generation"
        )

@router.get("/status/{job_id}", response_model=DocumentStatusResponse)
async def get_generation_status(
    job_id: str,
    credentials: HTTPAuthorizationCredentials = Depends(security),
    db: Session = Depends(get_db)
):
    """
    Get document generation status
    """
    try:
        # Verify user token
        token = credentials.credentials
        user_data = verify_token(token)
        
        if not user_data:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token"
            )
        
        # Get generation job
        generation_job = db.query(DocumentGeneration).filter(
            DocumentGeneration.job_id == job_id
        ).first()
        
        if not generation_job:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Generation job not found"
            )
        
        # Check if user can access this job
        if (generation_job.requested_by != user_data["username"] and 
            user_data.get("role") != "admin"):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Access denied to this generation job"
            )
        
        download_url = None
        if (generation_job.status == GenerationStatus.COMPLETED and 
            generation_job.output_filename):
            download_url = f"/api/documents/download/{job_id}"
        
        return DocumentStatusResponse(
            job_id=job_id,
            status=generation_job.status.value,
            progress_percentage=generation_job.progress_percentage,
            total_records=generation_job.total_records,
            processed_records=generation_job.processed_records,
            output_filename=generation_job.output_filename,
            download_url=download_url,
            error_message=generation_job.error_message,
            processing_time=generation_job.processing_time
        )
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Status check error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Error retrieving generation status"
        )

@router.get("/download/{job_id}")
async def download_document(
    job_id: str,
    credentials: HTTPAuthorizationCredentials = Depends(security),
    db: Session = Depends(get_db)
):
    """
    Download generated document
    """
    try:
        # Verify user token
        token = credentials.credentials
        user_data = verify_token(token)
        
        if not user_data:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token"
            )
        
        # Get generation job
        generation_job = db.query(DocumentGeneration).filter(
            DocumentGeneration.job_id == job_id
        ).first()
        
        if not generation_job:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Generation job not found"
            )
        
        # Check if user can access this job
        if (generation_job.requested_by != user_data["username"] and 
            user_data.get("role") != "admin"):
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Access denied to this document"
            )
        
        # Check if document is ready
        if generation_job.status != GenerationStatus.COMPLETED:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Document is not ready for download"
            )
        
        # Check if file exists
        if not generation_job.output_path or not os.path.exists(generation_job.output_path):
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Document file not found"
            )
        
        # Check access expiration
        if (generation_job.access_expires_at and 
            generation_job.access_expires_at < datetime.utcnow()):
            raise HTTPException(
                status_code=status.HTTP_410_GONE,
                detail="Document access has expired"
            )
        
        logger.info(f"Document downloaded: {job_id} by {user_data['username']}")
        
        return FileResponse(
            path=generation_job.output_path,
            filename=generation_job.output_filename,
            media_type="application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        )
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Download error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Error downloading document"
        )

@router.get("/cadets/preview")
async def preview_cadets(
    filters: CadetFilterRequest = Depends(),
    credentials: HTTPAuthorizationCredentials = Depends(security),
    db: Session = Depends(get_db)
):
    """
    Preview cadets that will be included in document generation
    """
    try:
        # Verify user token
        token = credentials.credentials
        user_data = verify_token(token)
        
        if not user_data:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token"
            )
        
        # Build query
        query = db.query(CadetProfile)
        
        # Apply filters
        if filters.semester:
            query = query.filter(CadetProfile.semester == filters.semester)
        if filters.academic_year:
            query = query.filter(CadetProfile.academic_year == filters.academic_year)
        if filters.course:
            query = query.filter(CadetProfile.course == filters.course)
        if filters.year_level:
            query = query.filter(CadetProfile.year_level == filters.year_level)
        if filters.platoon:
            query = query.filter(CadetProfile.platoon == filters.platoon)
        if filters.status:
            query = query.filter(CadetProfile.status == filters.status)
        
        # Get total count
        total_count = query.count()
        
        # Get paginated results
        cadets = query.offset(filters.offset).limit(filters.limit).all()
        
        return {
            "total_count": total_count,
            "returned_count": len(cadets),
            "offset": filters.offset,
            "limit": filters.limit,
            "cadets": [cadet.to_dict() for cadet in cadets]
        }
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Cadet preview error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Error retrieving cadet preview"
        )

@router.get("/templates")
async def get_templates(
    credentials: HTTPAuthorizationCredentials = Depends(security),
    db: Session = Depends(get_db)
):
    """
    Get available document templates
    """
    try:
        # Verify user token
        token = credentials.credentials
        user_data = verify_token(token)
        
        if not user_data:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token"
            )
        
        # Get active templates
        templates = db.query(DocumentTemplate).filter(
            DocumentTemplate.is_active == True
        ).all()
        
        return {
            "templates": [
                {
                    "id": template.id,
                    "name": template.name,
                    "description": template.description,
                    "document_type": template.document_type.value,
                    "version": template.version,
                    "created_at": template.created_at.isoformat() if template.created_at else None
                }
                for template in templates
            ]
        }
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Templates retrieval error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Error retrieving templates"
        )

@router.get("/history")
async def get_generation_history(
    limit: int = Query(50, le=100),
    offset: int = Query(0, ge=0),
    credentials: HTTPAuthorizationCredentials = Depends(security),
    db: Session = Depends(get_db)
):
    """
    Get document generation history
    """
    try:
        # Verify user token
        token = credentials.credentials
        user_data = verify_token(token)
        
        if not user_data:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token"
            )
        
        # Build query based on user role
        query = db.query(DocumentGeneration)
        
        if user_data.get("role") != "admin":
            # Non-admin users can only see their own generations
            query = query.filter(DocumentGeneration.requested_by == user_data["username"])
        
        # Get total count
        total_count = query.count()
        
        # Get paginated results
        generations = query.order_by(
            DocumentGeneration.created_at.desc()
        ).offset(offset).limit(limit).all()
        
        return {
            "total_count": total_count,
            "returned_count": len(generations),
            "offset": offset,
            "limit": limit,
            "generations": [generation.to_dict() for generation in generations]
        }
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Generation history error: {str(e)}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Error retrieving generation history"
        )