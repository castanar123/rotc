from sqlalchemy import Column, Integer, String, DateTime, Text, Enum, Boolean
from sqlalchemy.sql import func
from config.database import Base
import enum

class CadetStatus(enum.Enum):
    """Cadet status enumeration"""
    ACTIVE = "active"
    INACTIVE = "inactive"
    GRADUATED = "graduated"
    DROPPED = "dropped"
    SUSPENDED = "suspended"

class YearLevel(enum.Enum):
    """Year level enumeration"""
    FIRST = "1st Year"
    SECOND = "2nd Year"
    THIRD = "3rd Year"
    FOURTH = "4th Year"

class CadetProfile(Base):
    """Cadet profile model matching the existing database schema"""
    __tablename__ = "cadet_profiles"
    
    id = Column(Integer, primary_key=True, index=True)
    student_id = Column(String(20), unique=True, index=True, nullable=False)
    first_name = Column(String(50), nullable=False)
    last_name = Column(String(50), nullable=False)
    middle_name = Column(String(50), nullable=True)
    contact_number = Column(String(15), nullable=True)
    email = Column(String(100), nullable=True)
    course = Column(String(100), nullable=True)
    year_level = Column(Enum(YearLevel), nullable=True)
    platoon = Column(String(50), nullable=True)
    status = Column(Enum(CadetStatus), default=CadetStatus.ACTIVE)
    semester = Column(String(20), nullable=True)
    academic_year = Column(String(20), nullable=True)
    address = Column(Text, nullable=True)
    emergency_contact = Column(String(100), nullable=True)
    emergency_phone = Column(String(15), nullable=True)
    created_at = Column(DateTime(timezone=True), server_default=func.now())
    updated_at = Column(DateTime(timezone=True), onupdate=func.now())
    
    def __repr__(self):
        return f"<CadetProfile(id={self.id}, student_id='{self.student_id}', name='{self.first_name} {self.last_name}')>"
    
    @property
    def full_name(self):
        """Get full name of cadet"""
        if self.middle_name:
            return f"{self.first_name} {self.middle_name} {self.last_name}"
        return f"{self.first_name} {self.last_name}"
    
    def to_dict(self):
        """Convert model to dictionary"""
        return {
            "id": self.id,
            "student_id": self.student_id,
            "first_name": self.first_name,
            "last_name": self.last_name,
            "middle_name": self.middle_name,
            "full_name": self.full_name,
            "contact_number": self.contact_number,
            "email": self.email,
            "course": self.course,
            "year_level": self.year_level.value if self.year_level else None,
            "platoon": self.platoon,
            "status": self.status.value if self.status else None,
            "semester": self.semester,
            "academic_year": self.academic_year,
            "address": self.address,
            "emergency_contact": self.emergency_contact,
            "emergency_phone": self.emergency_phone,
            "created_at": self.created_at.isoformat() if self.created_at else None,
            "updated_at": self.updated_at.isoformat() if self.updated_at else None
        }