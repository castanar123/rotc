"""
API endpoint for generating pending approval reports
Tracks cadets who completed online enrollment but haven't submitted paper forms
"""

from fastapi import APIRouter, HTTPException, Query
from typing import Optional, List, Dict, Any
import mysql.connector
from datetime import datetime
import logging
from config.settings import settings

router = APIRouter(prefix="/api/pending-approvals", tags=["pending-approvals"])
logger = logging.getLogger(__name__)

def get_database_connection():
    """Get database connection using settings from config"""
    try:
        connection = mysql.connector.connect(
            host=settings.DB_HOST,
            port=settings.DB_PORT,
            user=settings.DB_USER,
            password=settings.DB_PASSWORD,
            database=settings.DB_NAME,
            charset='utf8mb4',
            collation='utf8mb4_unicode_ci'
        )
        return connection
    except mysql.connector.Error as err:
        print(f"Database connection error: {err}")
        raise HTTPException(status_code=500, detail=f"Database connection failed: {err}")

@router.get("/list")
async def get_pending_approvals(
    format: str = Query("json", description="Output format: json, html"),
    include_paper_form_status: bool = Query(True, description="Include paper form submission status"),
    days_threshold: Optional[int] = Query(None, description="Filter by days since registration"),
    course_filter: Optional[str] = Query(None, description="Filter by course"),
    platoon_filter: Optional[str] = Query(None, description="Filter by platoon")
):
    """
    Get list of cadets with pending approval who need to submit paper forms
    
    This endpoint returns cadets who:
    - Have completed online enrollment (approval_status = 'pending')
    - Have not yet submitted their physical paper form (paper_form_submitted = 0)
    - Need to be tracked for paper form submission
    """
    
    connection = None
    try:
        connection = get_database_connection()
        cursor = connection.cursor(dictionary=True)
        
        # Build the query with filters
        base_query = """
        SELECT 
            u.id,
            u.username,
            u.email,
            u.full_name,
            u.first_name,
            u.last_name,
            u.student_id,
            u.course,
            u.year_level,
            u.contact_number,
            u.approval_status,
            u.created_at as registration_date,
            u.paper_form_submitted,
            u.paper_form_submitted_date,
            u.paper_form_notes,
            cp.platoon,
            cp.section,
            DATEDIFF(NOW(), u.created_at) as days_since_registration,
            CASE 
                WHEN u.paper_form_submitted = 1 THEN 'Submitted'
                WHEN u.paper_form_submitted = 0 THEN 'Pending'
                ELSE 'Unknown'
            END as paper_form_status
        FROM users u
        LEFT JOIN cadet_profiles cp ON u.id = cp.user_id
        WHERE u.approval_status = 'pending' 
        AND u.role IN ('basic_cadet', 'cadet', 'basic-cadet')
        """
        
        conditions = []
        params = []
        
        # Add paper form filter if requested
        if include_paper_form_status:
            conditions.append("AND u.paper_form_submitted = 0")
        
        # Add days threshold filter
        if days_threshold is not None:
            conditions.append("AND DATEDIFF(NOW(), u.created_at) >= %s")
            params.append(days_threshold)
        
        # Add course filter
        if course_filter:
            conditions.append("AND u.course LIKE %s")
            params.append(f"%{course_filter}%")
        
        # Add platoon filter
        if platoon_filter:
            conditions.append("AND cp.platoon = %s")
            params.append(platoon_filter)
        
        # Combine query with conditions
        if conditions:
            query = base_query + " " + " ".join(conditions)
        else:
            query = base_query
        
        query += " ORDER BY u.created_at ASC"
        
        cursor.execute(query, params)
        pending_cadets = cursor.fetchall()
        
        # Get summary statistics
        stats_query = """
        SELECT 
            COUNT(*) as total_pending,
            COUNT(CASE WHEN u.paper_form_submitted = 0 THEN 1 END) as pending_paper_forms,
            COUNT(CASE WHEN u.paper_form_submitted = 1 THEN 1 END) as submitted_paper_forms,
            AVG(DATEDIFF(NOW(), u.created_at)) as avg_days_pending,
            MIN(u.created_at) as earliest_registration,
            MAX(u.created_at) as latest_registration
        FROM users u
        WHERE u.approval_status = 'pending' 
        AND u.role IN ('basic_cadet', 'cadet', 'basic-cadet')
        """
        
        cursor.execute(stats_query)
        stats = cursor.fetchone()
        
        # Format dates for JSON serialization
        for cadet in pending_cadets:
            if cadet['registration_date']:
                cadet['registration_date'] = cadet['registration_date'].isoformat()
            if cadet['paper_form_submitted_date']:
                cadet['paper_form_submitted_date'] = cadet['paper_form_submitted_date'].isoformat()
        
        if stats['earliest_registration']:
            stats['earliest_registration'] = stats['earliest_registration'].isoformat()
        if stats['latest_registration']:
            stats['latest_registration'] = stats['latest_registration'].isoformat()
        
        result = {
            "status": "success",
            "timestamp": datetime.now().isoformat(),
            "summary": {
                "total_pending_approvals": stats['total_pending'],
                "pending_paper_forms": stats['pending_paper_forms'],
                "submitted_paper_forms": stats['submitted_paper_forms'],
                "average_days_pending": round(float(stats['avg_days_pending']) if stats['avg_days_pending'] else 0, 1),
                "earliest_registration": stats['earliest_registration'],
                "latest_registration": stats['latest_registration']
            },
            "filters_applied": {
                "include_paper_form_status": include_paper_form_status,
                "days_threshold": days_threshold,
                "course_filter": course_filter,
                "platoon_filter": platoon_filter
            },
            "cadets": pending_cadets
        }
        
        if format.lower() == "html":
            return generate_html_report(result)
        
        return result
        
    except mysql.connector.Error as e:
        logger.error(f"Database error in get_pending_approvals: {e}")
        raise HTTPException(status_code=500, detail=f"Database error: {str(e)}")
    except Exception as e:
        logger.error(f"Unexpected error in get_pending_approvals: {e}")
        raise HTTPException(status_code=500, detail=f"Internal server error: {str(e)}")
    finally:
        if connection and connection.is_connected():
            connection.close()

def generate_html_report(data: Dict[str, Any]) -> str:
    """Generate HTML report for pending approvals"""
    
    html_content = f"""
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pending Registration Approvals Report</title>
        <style>
            body {{
                font-family: Arial, sans-serif;
                margin: 20px;
                background-color: #f5f5f5;
            }}
            .header {{
                background-color: #2c3e50;
                color: white;
                padding: 20px;
                text-align: center;
                margin-bottom: 20px;
            }}
            .summary {{
                background-color: white;
                padding: 20px;
                margin-bottom: 20px;
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }}
            .summary-grid {{
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }}
            .summary-item {{
                text-align: center;
                padding: 15px;
                background-color: #ecf0f1;
                border-radius: 5px;
            }}
            .summary-item h3 {{
                margin: 0;
                color: #2c3e50;
                font-size: 24px;
            }}
            .summary-item p {{
                margin: 5px 0 0 0;
                color: #7f8c8d;
            }}
            table {{
                width: 100%;
                border-collapse: collapse;
                background-color: white;
                margin-top: 20px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }}
            th, td {{
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }}
            th {{
                background-color: #34495e;
                color: white;
                font-weight: bold;
            }}
            tr:hover {{
                background-color: #f5f5f5;
            }}
            .status-pending {{
                color: #e74c3c;
                font-weight: bold;
            }}
            .status-submitted {{
                color: #27ae60;
                font-weight: bold;
            }}
            .filters {{
                background-color: white;
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 5px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }}
            .no-data {{
                text-align: center;
                padding: 40px;
                color: #7f8c8d;
                font-style: italic;
            }}
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Pending Registration Approvals Report</h1>
            <p>Cadets who completed online enrollment but need paper form submission</p>
            <p>Generated on: {data['timestamp']}</p>
        </div>
        
        <div class="summary">
            <h2>Summary Statistics</h2>
            <div class="summary-grid">
                <div class="summary-item">
                    <h3>{data['summary']['total_pending_approvals']}</h3>
                    <p>Total Pending Approvals</p>
                </div>
                <div class="summary-item">
                    <h3>{data['summary']['pending_paper_forms']}</h3>
                    <p>Pending Paper Forms</p>
                </div>
                <div class="summary-item">
                    <h3>{data['summary']['submitted_paper_forms']}</h3>
                    <p>Submitted Paper Forms</p>
                </div>
                <div class="summary-item">
                    <h3>{data['summary']['average_days_pending']}</h3>
                    <p>Average Days Pending</p>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <h3>Applied Filters:</h3>
            <ul>
                <li><strong>Include Paper Form Status:</strong> {data['filters_applied']['include_paper_form_status']}</li>
                <li><strong>Days Threshold:</strong> {data['filters_applied']['days_threshold'] or 'None'}</li>
                <li><strong>Course Filter:</strong> {data['filters_applied']['course_filter'] or 'None'}</li>
                <li><strong>Platoon Filter:</strong> {data['filters_applied']['platoon_filter'] or 'None'}</li>
            </ul>
        </div>
    """
    
    if data['cadets']:
        html_content += """
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Student ID</th>
                    <th>Course</th>
                    <th>Year Level</th>
                    <th>Platoon</th>
                    <th>Contact</th>
                    <th>Registration Date</th>
                    <th>Days Pending</th>
                    <th>Paper Form Status</th>
                </tr>
            </thead>
            <tbody>
        """
        
        for i, cadet in enumerate(data['cadets'], 1):
            full_name = cadet['full_name'] or f"{cadet['first_name']} {cadet['last_name']}"
            status_class = "status-pending" if cadet['paper_form_status'] == 'Pending' else "status-submitted"
            
            html_content += f"""
                <tr>
                    <td>{i}</td>
                    <td>{full_name}</td>
                    <td>{cadet['student_id'] or 'N/A'}</td>
                    <td>{cadet['course'] or 'N/A'}</td>
                    <td>{cadet['year_level'] or 'N/A'}</td>
                    <td>{cadet['platoon'] or 'N/A'}</td>
                    <td>{cadet['contact_number'] or 'N/A'}</td>
                    <td>{cadet['registration_date'][:10] if cadet['registration_date'] else 'N/A'}</td>
                    <td>{cadet['days_since_registration']}</td>
                    <td class="{status_class}">{cadet['paper_form_status']}</td>
                </tr>
            """
        
        html_content += """
            </tbody>
        </table>
        """
    else:
        html_content += """
        <div class="no-data">
            <h3>No pending approvals found</h3>
            <p>All cadets have either been approved or have submitted their paper forms.</p>
        </div>
        """
    
    html_content += """
    </body>
    </html>
    """
    
    return html_content

@router.post("/update-paper-form-status")
async def update_paper_form_status(
    user_id: int,
    submitted: bool,
    notes: Optional[str] = None
):
    """
    Update paper form submission status for a cadet
    """
    
    connection = None
    try:
        connection = get_database_connection()
        cursor = connection.cursor()
        
        # Update paper form status
        update_query = """
        UPDATE users 
        SET paper_form_submitted = %s,
            paper_form_submitted_date = %s,
            paper_form_notes = %s
        WHERE id = %s
        """
        
        submitted_date = datetime.now() if submitted else None
        cursor.execute(update_query, (submitted, submitted_date, notes, user_id))
        connection.commit()
        
        if cursor.rowcount == 0:
            raise HTTPException(status_code=404, detail="User not found")
        
        return {
            "status": "success",
            "message": f"Paper form status updated for user ID {user_id}",
            "updated": {
                "user_id": user_id,
                "paper_form_submitted": submitted,
                "paper_form_submitted_date": submitted_date.isoformat() if submitted_date else None,
                "paper_form_notes": notes
            }
        }
        
    except mysql.connector.Error as e:
        logger.error(f"Database error in update_paper_form_status: {e}")
        raise HTTPException(status_code=500, detail=f"Database error: {str(e)}")
    except Exception as e:
        logger.error(f"Unexpected error in update_paper_form_status: {e}")
        raise HTTPException(status_code=500, detail=f"Internal server error: {str(e)}")
    finally:
        if connection and connection.is_connected():
            connection.close()