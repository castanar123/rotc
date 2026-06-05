# ROTC Document Generation System

A comprehensive Python-based document generation system for ROTC (Reserve Officers' Training Corps) with FastAPI backend, designed to generate AER (Annual Enrollment Report), ASR (Annual Statistical Report), and Cadet List documents with enterprise-grade security features.

## Features

### 🔐 Security Features
- **Multi-layer Authentication**: API keys, JWT tokens, 2FA support
- **Role-based Access Control (RBAC)**: Admin, Officer, and User roles
- **Data Protection**: Encryption at rest and in transit
- **Document Security**: Digital signatures, watermarks, password protection
- **Audit Logging**: Comprehensive security and compliance logging
- **Rate Limiting**: API request throttling
- **Input Validation**: SQL injection and XSS prevention

### 📄 Document Generation
- **AER Reports**: Annual Enrollment Reports with statistical summaries
- **ASR Reports**: Annual Statistical Reports with detailed analytics
- **Cadet Lists**: Formatted cadet rosters with filtering options
- **Template System**: Word document templates with placeholder replacement
- **Batch Processing**: Asynchronous document generation
- **Export Formats**: DOCX with security features

### 🎯 Admin Dashboard Integration
- RESTful API endpoints for admin dashboard integration
- Real-time generation status tracking
- Document download management
- User permission management
- Audit trail visualization

## Project Structure

```
document_generation/
├── api/
│   └── routes/
│       ├── auth_routes.py      # Authentication endpoints
│       └── document_routes.py  # Document generation endpoints
├── config/
│   ├── database.py            # Database configuration
│   └── settings.py            # Application settings
├── models/
│   ├── cadet.py              # Cadet data models
│   └── document.py           # Document generation models
├── services/
│   └── document_generator.py  # Core document generation logic
├── templates/
│   ├── aer/                  # AER report templates
│   ├── asr/                  # ASR report templates
│   └── cadet_list/           # Cadet list templates
├── utils/
│   ├── logger.py             # Logging utilities
│   └── security.py           # Security utilities
├── uploads/                   # File upload directory
├── output/                    # Generated document output
├── logs/                      # Application logs
├── static/                    # Static files
├── backups/                   # Database backups
├── main.py                    # FastAPI application entry point
├── requirements.txt           # Python dependencies
├── .env                       # Environment configuration
└── setup_directories.py       # Setup script
```

## Installation

### Prerequisites
- Python 3.9 or higher
- MySQL/MariaDB database
- Git

### Setup Steps

1. **Clone the repository** (if using version control):
   ```bash
   git clone <repository-url>
   cd document_generation
   ```

2. **Install Python dependencies**:
   ```bash
   pip install -r requirements.txt
   ```

3. **Run setup script**:
   ```bash
   python setup_directories.py
   ```

4. **Configure environment variables**:
   - Edit `.env` file with your database credentials
   - Update security keys and settings
   - Configure CORS origins for your admin dashboard

5. **Database setup**:
   - Create MySQL database: `rotc_db`
   - Update database credentials in `.env`
   - Tables will be created automatically on first run

6. **Add document templates**:
   - Place Word document templates (.docx) in respective template directories
   - Use placeholder syntax: `{{variable_name}}`

## Usage

### Starting the Server

```bash
python main.py
```

The API will be available at:
- **API Base URL**: `http://localhost:8000`
- **API Documentation**: `http://localhost:8000/docs`
- **Health Check**: `http://localhost:8000/health`

### API Endpoints

#### Authentication
- `POST /api/auth/login` - User login
- `POST /api/auth/verify-token` - Token verification
- `POST /api/auth/generate-api-key` - Generate API key (admin only)
- `GET /api/auth/me` - Get current user info

#### Document Generation
- `POST /api/documents/generate` - Start document generation
- `GET /api/documents/status/{job_id}` - Check generation status
- `GET /api/documents/download/{job_id}` - Download generated document
- `GET /api/documents/templates` - Get available templates
- `GET /api/documents/history` - Get generation history
- `GET /api/documents/cadets/preview` - Preview cadet data

### Document Generation Process

1. **Select Template**: Choose from available AER, ASR, or Cadet List templates
2. **Apply Filters**: Filter cadets by semester, year level, course, etc.
3. **Configure Security**: Set password protection, watermarks, digital signatures
4. **Generate**: Submit generation request (returns job ID)
5. **Monitor Progress**: Check status using job ID
6. **Download**: Download completed document

### Template Placeholders

#### Common Placeholders
- `{{report_title}}` - Document title
- `{{generation_date}}` - Generation timestamp
- `{{academic_year}}` - Academic year
- `{{semester}}` - Current semester
- `{{total_cadets}}` - Total cadet count

#### Cadet Data Placeholders
- `{{cadets}}` - Array of cadet objects
- Individual cadet fields: `{{cadet.first_name}}`, `{{cadet.last_name}}`, etc.

#### Statistical Placeholders (ASR)
- `{{statistics.gender_distribution}}` - Gender statistics
- `{{statistics.age_distribution}}` - Age group statistics
- `{{statistics.academic_performance}}` - GPA statistics

## Security Configuration

### Environment Variables

```env
# Security
SECRET_KEY=your-secret-key-here
ENCRYPTION_KEY=your-encryption-key-here
ENABLE_2FA=False
ENABLE_IP_WHITELIST=False

# Database
DATABASE_URL=mysql+pymysql://user:password@localhost:3306/rotc_db

# CORS (for admin dashboard)
ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8000
```

### Security Features

1. **Authentication**: JWT tokens with configurable expiration
2. **Authorization**: Role-based permissions
3. **Data Encryption**: Sensitive data encrypted at rest
4. **Document Security**: Watermarks, passwords, digital signatures
5. **Audit Logging**: All actions logged for compliance
6. **Rate Limiting**: API request throttling
7. **Input Validation**: Prevents injection attacks

## Integration with Admin Dashboard

### Frontend Integration

The system provides RESTful APIs that can be integrated with any frontend framework:

```javascript
// Example: Start document generation
const response = await fetch('/api/documents/generate', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    template_id: 1,
    document_type: 'aer_report',
    filters: {
      academic_year: '2024',
      semester: 'Fall'
    },
    security_options: {
      watermark: true,
      password_protected: false
    }
  })
});

const result = await response.json();
console.log('Job ID:', result.job_id);
```

### Dashboard Features

- **Document Generation Interface**: Form-based document creation
- **Progress Monitoring**: Real-time status updates
- **Download Management**: Secure document downloads
- **Template Management**: Upload and manage templates
- **User Management**: Role and permission management
- **Audit Dashboard**: Security and compliance reporting

## Logging and Monitoring

### Log Files
- `logs/app.log` - Application logs
- `logs/security.log` - Security events
- `logs/audit.log` - Audit trail
- `logs/errors.log` - Error logs

### Monitoring Endpoints
- `/health` - Health check
- `/metrics` - Prometheus metrics (if enabled)

## Compliance

### Data Protection
- **GDPR Compliance**: Data anonymization and retention policies
- **FERPA Compliance**: Educational record protection
- **SOC 2**: Security controls and audit logging
- **ISO 27001**: Information security management

### Audit Requirements
- All document access logged
- User actions tracked
- Data modifications recorded
- Security events monitored

## Development

### Running in Development Mode

```bash
# Enable debug mode
export DEBUG=True

# Start with auto-reload
uvicorn main:app --reload --host 0.0.0.0 --port 8000
```

### Testing

```bash
# Install test dependencies
pip install pytest pytest-asyncio httpx

# Run tests
pytest tests/
```

## Production Deployment

### Environment Setup

1. **Set production environment variables**:
   ```env
   DEBUG=False
   ENVIRONMENT=production
   SECRET_KEY=<strong-secret-key>
   ```

2. **Use production database**:
   ```env
   DATABASE_URL=mysql+pymysql://user:password@prod-db:3306/rotc_db
   ```

3. **Configure HTTPS and security headers**

4. **Set up log rotation and monitoring**

### Docker Deployment (Optional)

```dockerfile
FROM python:3.11-slim

WORKDIR /app
COPY requirements.txt .
RUN pip install -r requirements.txt

COPY . .
EXPOSE 8000

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]
```

## Troubleshooting

### Common Issues

1. **Database Connection Error**:
   - Check database credentials in `.env`
   - Ensure MySQL service is running
   - Verify database exists

2. **Template Not Found**:
   - Check template files in `templates/` directories
   - Verify file permissions
   - Check template database records

3. **Document Generation Fails**:
   - Check logs in `logs/app.log`
   - Verify template placeholders
   - Check cadet data availability

4. **Permission Denied**:
   - Verify user roles and permissions
   - Check JWT token validity
   - Review audit logs

### Support

For technical support:
- Check application logs
- Review API documentation at `/docs`
- Contact system administrator

## License

This project is developed for ROTC educational institutions. Please ensure compliance with your institution's policies and applicable regulations.

## Contributing

1. Follow security best practices
2. Maintain audit logging
3. Update documentation
4. Test thoroughly before deployment
5. Follow code style guidelines

---

**Note**: This system handles sensitive educational and personal data. Ensure proper security measures, regular backups, and compliance with applicable data protection regulations.