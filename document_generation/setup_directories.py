#!/usr/bin/env python3
"""
Setup script to create necessary directories and initialize the document generation system
"""

import os
from pathlib import Path

def create_directories():
    """
    Create necessary directories for the document generation system
    """
    base_dir = Path(__file__).parent
    
    directories = [
        "uploads",
        "output",
        "logs",
        "templates/aer",
        "templates/asr", 
        "templates/cadet_list",
        "static",
        "backups"
    ]
    
    for directory in directories:
        dir_path = base_dir / directory
        dir_path.mkdir(parents=True, exist_ok=True)
        print(f"Created directory: {dir_path}")
        
        # Create .gitkeep file to ensure directory is tracked in git
        gitkeep_file = dir_path / ".gitkeep"
        if not gitkeep_file.exists():
            gitkeep_file.touch()
            print(f"Created .gitkeep in: {dir_path}")

def create_sample_templates():
    """
    Create sample template files
    """
    base_dir = Path(__file__).parent
    
    # Sample AER template content
    aer_readme = base_dir / "templates" / "aer" / "README.md"
    aer_readme.write_text("""
# AER (Annual Enrollment Report) Templates

Place your AER Word document templates (.docx files) in this directory.

Template placeholders:
- {{report_title}} - Report title
- {{generation_date}} - Date when report was generated
- {{academic_year}} - Academic year
- {{total_cadets}} - Total number of cadets
- {{cadets}} - List of cadet data
- {{summary}} - Summary statistics

Example filename: AER_Template_2024.docx
""")
    
    # Sample ASR template content
    asr_readme = base_dir / "templates" / "asr" / "README.md"
    asr_readme.write_text("""
# ASR (Annual Statistical Report) Templates

Place your ASR Word document templates (.docx files) in this directory.

Template placeholders:
- {{report_title}} - Report title
- {{generation_date}} - Date when report was generated
- {{academic_year}} - Academic year
- {{statistics}} - Statistical data
- {{total_cadets}} - Total number of cadets

Example filename: ASR_Template_2024.docx
""")
    
    # Sample Cadet List template content
    cadet_list_readme = base_dir / "templates" / "cadet_list" / "README.md"
    cadet_list_readme.write_text("""
# Cadet List Templates

Place your Cadet List Word document templates (.docx files) in this directory.

Template placeholders:
- {{report_title}} - Report title
- {{generation_date}} - Date when report was generated
- {{semester}} - Current semester
- {{academic_year}} - Academic year
- {{total_cadets}} - Total number of cadets
- {{cadets}} - List of cadet data

Example filename: Cadet_List_Template.docx
""")
    
    print("Created template README files")

def main():
    """
    Main setup function
    """
    print("Setting up ROTC Document Generation System...")
    
    try:
        create_directories()
        create_sample_templates()
        
        print("\n✅ Setup completed successfully!")
        print("\nNext steps:")
        print("1. Update the .env file with your database credentials")
        print("2. Place your Word document templates in the templates/ subdirectories")
        print("3. Run 'python main.py' to start the FastAPI server")
        print("4. Access the API documentation at http://localhost:8000/docs")
        
    except Exception as e:
        print(f"❌ Setup failed: {str(e)}")
        return 1
    
    return 0

if __name__ == "__main__":
    exit(main())