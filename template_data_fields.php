<?php
// Template Data Fields Guide
// This shows exactly what data goes in each Word document template table

echo "=== ROTC DOCUMENT TEMPLATE DATA FIELDS ===\n\n";

// 1. ROSTER OF ENROLLED CADETS (Most requested by user)
echo "1. ROSTER OF ENROLLED CADETS\n";
echo "Template: B-ROSTER-OF-ENROLLED-CADETS-2ND-SEM-S.Y-24-25-LSPU-LB.docx\n";
echo "Table Structure: One row per cadet\n\n";
echo "COLUMNS FOR THE TABLE:\n";
echo "┌─────┬─────────────┬─────────────┬──────────────┬────────────┬─────────────┬─────────────┬──────────────┐\n";
echo "│ No. │ Last Name   │ First Name  │ Middle Name  │ Year Level │ Course      │ Student ID  │ Contact No.  │\n";
echo "├─────┼─────────────┼─────────────┼──────────────┼────────────┼─────────────┼─────────────┼──────────────┤\n";
echo "│  1  │ DELA CRUZ   │ JUAN        │ SANTOS       │ MS 1       │ BSIT        │ 2024-12345  │ 09123456789  │\n";
echo "│  2  │ GARCIA      │ MARIA       │ LOPEZ        │ MS 1       │ BSCS        │ 2024-12346  │ 09123456790  │\n";
echo "│  3  │ SANTOS      │ PEDRO       │ REYES        │ MS 2       │ BSIT        │ 2023-12347  │ 09123456791  │\n";
echo "│ ... │ ...         │ ...         │ ...          │ ...        │ ...         │ ...         │ ...          │\n";
echo "└─────┴─────────────┴─────────────┴──────────────┴────────────┴─────────────┴─────────────┴──────────────┘\n\n";

echo "DATA SOURCE FOR ROSTER:\n";
echo "- Last Name: cp.last_name\n";
echo "- First Name: cp.first_name\n";
echo "- Middle Name: cp.middle_name\n";
echo "- Year Level: cp.year_level (converted to MS format)\n";
echo "- Course: cp.course\n";
echo "- Student ID: u.student_id\n";
echo "- Contact No.: cp.contact_number\n\n";

// 2. LIST OF BENEFICIARIES
echo "2. LIST OF BENEFICIARIES\n";
echo "Template: C-LIST-OF-BENEFICIARIES-1ST-SEM-LSPULB.docx\n";
echo "Table Structure: One row per beneficiary\n\n";
echo "COLUMNS FOR THE TABLE:\n";
echo "┌─────┬─────────────┬─────────────┬──────────────┬────────────┬─────────────┬─────────────┬──────────────┐\n";
echo "│ No. │ Last Name   │ First Name  │ Middle Name  │ Year Level │ Course      │ Student ID  │ Scholarship  │\n";
echo "├─────┼─────────────┼─────────────┼──────────────┼────────────┼─────────────┼─────────────┼──────────────┤\n";
echo "│  1  │ DELA CRUZ   │ JUAN        │ SANTOS       │ MS 1       │ BSIT        │ 2024-12345  │ ROTC Scholar │\n";
echo "│  2  │ GARCIA      │ MARIA       │ LOPEZ        │ MS 1       │ BSCS        │ 2024-12346  │ ROTC Scholar │\n";
echo "│ ... │ ...         │ ...         │ ...          │ ...        │ ...         │ ...         │ ...          │\n";
echo "└─────┴─────────────┴─────────────┴──────────────┴────────────┴─────────────┴─────────────┴──────────────┘\n\n";

// 3. CADET PROFILES
echo "3. CADET PROFILES\n";
echo "Template: D-CADETS-PROFILE-2ND-SEM-LSPULB.docx\n";
echo "Table Structure: Detailed profile per cadet (multiple sections)\n\n";
echo "PROFILE SECTIONS FOR EACH CADET:\n";
echo "┌─────────────────────────────────────────────────────────────────┐\n";
echo "│ PERSONAL INFORMATION                                            │\n";
echo "├─────────────────────────────────────────────────────────────────┤\n";
echo "│ Name: DELA CRUZ, JUAN SANTOS                                    │\n";
echo "│ Student ID: 2024-12345                                          │\n";
echo "│ Year Level: MS 1                                                │\n";
echo "│ Course: Bachelor of Science in Information Technology           │\n";
echo "│ Gender: Male                                                    │\n";
echo "│ Date of Birth: January 15, 2005                                 │\n";
echo "│ Address: 123 Main St, Los Baños, Laguna                        │\n";
echo "├─────────────────────────────────────────────────────────────────┤\n";
echo "│ CONTACT INFORMATION                                             │\n";
echo "├─────────────────────────────────────────────────────────────────┤\n";
echo "│ Contact Number: 09123456789                                     │\n";
echo "│ Email: juan.delacruz@email.com                                  │\n";
echo "│ Emergency Contact: Maria Dela Cruz (Mother) - 09123456788       │\n";
echo "└─────────────────────────────────────────────────────────────────┘\n\n";

// 4. SUMMARY DOCUMENT
echo "4. SUMMARY DOCUMENT\n";
echo "Template: A-SUMMARY-2ND-SEM-S.Y-24-25-LSPULB.docx\n";
echo "Table Structure: Statistical summary\n\n";
echo "SUMMARY STATISTICS TABLE:\n";
echo "┌─────────────────────────────────┬─────────┐\n";
echo "│ Category                        │ Count   │\n";
echo "├─────────────────────────────────┼─────────┤\n";
echo "│ Total Number of Cadets          │   150   │\n";
echo "│ MS 1-2 (Basic Cadets)          │    80   │\n";
echo "│ MS 3 (2nd Class Cadets)        │    45   │\n";
echo "│ MS 4 (1st Class Cadets)        │    25   │\n";
echo "│ Male Cadets                     │    90   │\n";
echo "│ Female Cadets                   │    60   │\n";
echo "└─────────────────────────────────┴─────────┘\n\n";

echo "=== SPECIFIC ANSWER TO USER'S QUESTION ===\n\n";
echo "For the ROSTER OF BASIC CADETS (MS 1-2), each row contains:\n\n";
echo "1. Serial Number (1, 2, 3, 4, ...)\n";
echo "2. Last Name (from cadet_profiles.last_name)\n";
echo "3. First Name (from cadet_profiles.first_name)\n";
echo "4. Middle Name (from cadet_profiles.middle_name)\n";
echo "5. Year Level (MS 1 or MS 2, from cadet_profiles.year_level)\n";
echo "6. Course (from cadet_profiles.course)\n";
echo "7. Student ID (from users.student_id)\n";
echo "8. Contact Number (from cadet_profiles.contact_number)\n\n";

echo "EXAMPLE ROWS FOR BASIC CADETS:\n";
echo "Row 1: 1 | DELA CRUZ | JUAN | SANTOS | MS 1 | BSIT | 2024-12345 | 09123456789\n";
echo "Row 2: 2 | GARCIA | MARIA | LOPEZ | MS 1 | BSCS | 2024-12346 | 09123456790\n";
echo "Row 3: 3 | SANTOS | PEDRO | REYES | MS 2 | BSIT | 2023-12347 | 09123456791\n";
echo "Row 4: 4 | MARTINEZ | ANA | CRUZ | MS 2 | BSCS | 2023-12348 | 09123456792\n";
echo "... and so on for all basic cadets\n\n";

echo "The 'long list' you mentioned means ALL basic cadets (those with year_level 1 or 2)\n";
echo "will be listed in this format, one per row, sorted by last name then first name.\n\n";

echo "=== DATABASE QUERY FOR BASIC CADETS ===\n";
echo "SELECT \n";
echo "    ROW_NUMBER() OVER (ORDER BY cp.last_name, cp.first_name) as serial_no,\n";
echo "    cp.last_name,\n";
echo "    cp.first_name,\n";
echo "    cp.middle_name,\n";
echo "    CASE \n";
echo "        WHEN cp.year_level = 1 THEN 'MS 1'\n";
echo "        WHEN cp.year_level = 2 THEN 'MS 2'\n";
echo "    END as ms_level,\n";
echo "    cp.course,\n";
echo "    u.student_id,\n";
echo "    cp.contact_number\n";
echo "FROM users u \n";
echo "JOIN cadet_profiles cp ON u.id = cp.user_id \n";
echo "WHERE u.status = 'active' \n";
echo "AND cp.year_level IN (1,2) \n";
echo "AND u.role = 'basic_cadet'\n";
echo "ORDER BY cp.last_name, cp.first_name;\n";

?>