# ROTC Cadet Management Portal

This is a web-based portal for managing ROTC cadet information, attendance, grades, and announcements.

## Features

- User authentication with roles (Basic Cadet, Officer, Admin)
- Cadet registration with photo and signature uploads
- QR code generation for cadet IDs
- PDF ID card generation
- Attendance tracking via QR code scanning and manual entry
- Grading system for events and activities
- Announcement board
- Reporting module for attendance and grades

## Setup Instructions

### Prerequisites

- A web server stack like XAMPP (Apache, MySQL, PHP)
- A modern web browser

### Installation

1.  **Clone or download** this project into your web server's root directory (e.g., `htdocs` in XAMPP).
2.  **Database Setup**:
    - Open phpMyAdmin or your preferred MySQL client.
    - Create a new database named `rotc_db`.
    - Import the `db/rotc_db.sql` file into the new database. This will create all the necessary tables.
3.  **Database Connection**:
    - Open `includes/db.php`.
    - Update the `DB_SERVER`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_NAME` constants to match your MySQL setup.
4.  **Third-Party Libraries**:
    - **PHP QR Code**: Download the library from [SourceForge](https://sourceforge.net/projects/phpqrcode/) and place the contents (specifically `qrlib.php`) inside the `libs/phpqrcode/` directory.
    - **FPDF**: Download the library from [fpdf.org](http://www.fpdf.org/en/download.php) and place the contents (specifically `fpdf.php` and the `font` directory) inside the `libs/fpdf/` directory.
5.  **Permissions**:
    - Ensure your web server has write permissions for the `uploads/` directory and its subdirectories (`photos/`, `signatures/`, `qrcodes/`, `ids/`).

### Local Configuration

Database credentials should not be committed to GitHub. Configure them with either:

- `includes/db_config.local.php`, copied from `includes/db_config.local.php.example`
- Environment variables from `.env.example`, such as `ROTC_DB_SERVER`, `ROTC_DB_USER`, `ROTC_DB_PASS`, and `ROTC_DB_NAME`

The local override file is ignored by Git.

### Accessing the Portal

- Open your web browser and navigate to `http://localhost/rotc/` (or wherever you placed the project files).

## Default Roles & Access

- **Admin**: Full access to all modules, including user management (via database) and reporting.
- **Officer Roles (`2cl`, `1cl`, `commandant`)**: Can manage attendance, grades, and post announcements.
- **Basic Cadet**: Can view their own profile, grades, and announcements, and download their ID card.

## Project Structure

- `/` (root): Login, registration, and main entry points.
- `/announcements`: Scripts for creating and viewing announcements.
- `/attendance`: Scripts for QR scanning, manual entry, and viewing attendance logs.
- `/css`: Contains the main stylesheet (`style.css`).
- `/dashboard`: Role-specific landing pages.
- `/db`: Contains the SQL schema file.
- `/grades`: Scripts for managing and viewing cadet grades.
- `/includes`: Core files for database connection and session management.
- `/libs`: Placeholder for third-party libraries (FPDF, PHP QR Code).
- `/profile`: Public cadet profile page.
- `/reports`: Scripts for generating and viewing system reports.
- `/uploads`: Directory for storing user-uploaded files (photos, signatures, etc.).

## Deployment Notes

See `docs/Deployment.md` before pushing to GitHub or connecting the project to Vercel. The current app expects PHP, local filesystem writes, and a local MySQL database, so Vercel needs a special plan rather than a one-click static deployment.
