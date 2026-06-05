<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db.php';
require_once 'includes/term_enrollment.php';

// Simple document generation without PHPWord for now
// Note: For full Word document processing, PHPWord library should be installed

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$documentType = $input['document_type'] ?? '';
$subDocument = $input['sub_document'] ?? '';
$targetSy = trim($input['target_school_year'] ?? '');
$targetSem = trim($input['target_semester'] ?? '');

try {
    switch ($documentType) {
        case 'aer':
            generateAERDocument($pdo, $subDocument, $targetSy, $targetSem);
            break;
        case 'asr':
            if ($subDocument === 'grade_report') {
                generateASRGradeReportDocument($pdo, $targetSy, $targetSem);
            }
            else {
                generateASRDocument($pdo, $targetSy, $targetSem);
            }
            break;
        case 'attendance_platoon':
            generateAttendancePerPlatoon($pdo, $targetSy, $targetSem);
            break;
        case 'qr_data_export':
            generateQRDataExport($pdo, $targetSy, $targetSem);
            break;
        default:
            throw new Exception('Invalid document type');
    }


}
catch (Exception $e) {
    error_log("Document generation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Document generation failed: ' . $e->getMessage()]);
}

function generateAERDocument($pdo, $subDocument, $targetSy = '', $targetSem = '')
{
    switch ($subDocument) {
        case 'summary':
            generateSummaryDocument($pdo, $targetSy, $targetSem);
            break;
        case 'roster':
            generateRosterDocument($pdo, $targetSy, $targetSem);
            break;
        case 'beneficiaries':
            generateBeneficiariesDocument($pdo, $targetSy, $targetSem);
            break;
        case 'cadet_profile':
            generateCadetProfileDocument($pdo, $targetSy, $targetSem);
            break;
        default:
            throw new Exception('Invalid AER sub-document type');
    }
}

function generateSummaryDocument($pdo, $targetSy = '', $targetSem = '')
{
    if ($targetSy !== '' && $targetSem !== '') {
        $school_year = $targetSy;
        $semester = $targetSem;
    }
    else {
        $term = get_active_term();
        $school_year = $term['school_year'] ?? '';
        $semester = $term['semester'] ?? '';
    }
    // Get enrollment statistics by MS level; include all approved basic cadets regardless of gender
    $sql = "SELECT 
                CASE 
                    WHEN u.year_level = '1st Year' OR u.year_level = 'MS1' OR u.year_level = '1' THEN 'MS-1'
                    WHEN u.year_level = '2nd Year' OR u.year_level = 'MS2' OR u.year_level = '2' THEN 'MS-32'
                    WHEN u.year_level = '3rd Year' OR u.year_level = 'MS3' OR u.year_level = '3' THEN 'MS-42'
                    ELSE 'MS-1' -- Default unknown/empty levels to MS-1 so everyone is counted
                END as ms_level,
                SUM(CASE WHEN LOWER(cp.gender) = 'male' THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN LOWER(cp.gender) = 'female' THEN 1 ELSE 0 END) as female,
                COUNT(*) as total
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            JOIN cadet_enrollments ce ON ce.cadet_profile_id = cp.id
            WHERE u.role IN ('basic-cadet','basic_cadet')
                AND u.status = 'active' 
                AND u.approval_status = 'approved' 
                AND cp.status IN ('Active','active')
                AND ce.school_year = ?
                AND ce.semester = ?
                AND ce.enrollment_status = 'enrolled'
            GROUP BY 
                CASE 
                    WHEN u.year_level = '1st Year' OR u.year_level = 'MS1' OR u.year_level = '1' THEN 'MS-1'
                    WHEN u.year_level = '2nd Year' OR u.year_level = 'MS2' OR u.year_level = '2' THEN 'MS-32'
                    WHEN u.year_level = '3rd Year' OR u.year_level = 'MS3' OR u.year_level = '3' THEN 'MS-42'
                    ELSE 'MS-1'
                END
            ORDER BY ms_level";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$school_year, $semester]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process data for template
    $summaryData = [
        'MS-1' => ['male' => 0, 'female' => 0, 'total' => 0],
        'MS-32' => ['male' => 0, 'female' => 0, 'total' => 0],
        'MS-42' => ['male' => 0, 'female' => 0, 'total' => 0]
    ];

    foreach ($results as $row) {
        $ms = $row['ms_level'];
        if (!isset($summaryData[$ms]))
            continue;
        $summaryData[$ms]['male'] = (int)$row['male'];
        $summaryData[$ms]['female'] = (int)$row['female'];
        $summaryData[$ms]['total'] = (int)$row['total'];
    }

    // Calculate totals
    $totals = [
        'MS-1' => $summaryData['MS-1']['total'],
        'MS-32' => $summaryData['MS-32']['total'],
        'MS-42' => $summaryData['MS-42']['total']
    ];

    $grandTotal = $totals['MS-1'] + $totals['MS-32'] + $totals['MS-42'];
    $totalMale = $summaryData['MS-1']['male'] + $summaryData['MS-32']['male'] + $summaryData['MS-42']['male'];
    $totalFemale = $summaryData['MS-1']['female'] + $summaryData['MS-32']['female'] + $summaryData['MS-42']['female'];

    // Create output directory if it doesn't exist
    if (!file_exists('output')) {
        mkdir('output', 0777, true);
    }

    // Generate CSV content
    $csvContent = "AER SUMMARY REPORT - {$semester} Semester S.Y. {$school_year}\n";
    $csvContent .= "Generated on: " . date('F j, Y') . "\n\n";
    $csvContent .= "MS,ENROLLED CADETS,,TOTAL\n";
    $csvContent .= ",MALE,FEMALE,\n";
    $csvContent .= "MS-1,{$summaryData['MS-1']['male']},{$summaryData['MS-1']['female']},{$totals['MS-1']}\n";
    $csvContent .= "MS-32,{$summaryData['MS-32']['male']},{$summaryData['MS-32']['female']},{$totals['MS-32']}\n";
    $csvContent .= "MS-42,{$summaryData['MS-42']['male']},{$summaryData['MS-42']['female']},{$totals['MS-42']}\n";
    $csvContent .= "TOTAL,{$totalMale},{$totalFemale},{$grandTotal}\n";

    // Save document
    $outputPath = 'output/AER_Summary_' . date('Y-m-d_H-i-s') . '.csv';
    file_put_contents($outputPath, $csvContent);

    echo json_encode([
        'success' => true,
        'message' => 'Summary document generated successfully',
        'file_path' => $outputPath,
        'download_url' => $outputPath
    ]);
}

function generateRosterDocument($pdo, $targetSy = '', $targetSem = '')
{
    if ($targetSy !== '' && $targetSem !== '') {
        $school_year = $targetSy;
        $semester = $targetSem;
    }
    else {
        $term = get_active_term();
        $school_year = $term['school_year'] ?? '';
        $semester = $term['semester'] ?? '';
    }
    // Get cadet roster data for MS-1 basic cadets only
    $sql = "SELECT 
                CASE 
                    WHEN u.year_level = '1st Year' OR u.year_level = 'MS1' OR u.year_level = '1' THEN 'MS-1'
                    WHEN u.year_level = '2nd Year' OR u.year_level = 'MS2' OR u.year_level = '2' THEN 'MS-32'
                    WHEN u.year_level = '3rd Year' OR u.year_level = 'MS3' OR u.year_level = '3' THEN 'MS-42'
                    ELSE 'MS-1'
                END as ms_level,
                cp.last_name,
                cp.first_name,
                cp.middle_name,
                cp.course,
                cp.birthdate,
                cp.contact_number,
                cp.province_city,
                cp.region,
                cp.address,
                cp.gender
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            JOIN cadet_enrollments ce ON ce.cadet_profile_id = cp.id
            WHERE u.role = 'basic-cadet'
                AND u.status = 'active' 
                AND u.approval_status = 'approved' 
                AND cp.status = 'Active'
                AND cp.gender IS NOT NULL
                AND ce.school_year = ?
                AND ce.semester = ?
                AND ce.enrollment_status = 'enrolled'
            ORDER BY 
                CASE 
                    WHEN u.year_level = '1st Year' OR u.year_level = 'MS1' OR u.year_level = '1' THEN 1
                    WHEN u.year_level = '2nd Year' OR u.year_level = 'MS2' OR u.year_level = '2' THEN 2
                    WHEN u.year_level = '3rd Year' OR u.year_level = 'MS3' OR u.year_level = '3' THEN 3
                    ELSE 4
                END, 
                cp.gender, cp.last_name, cp.first_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$school_year, $semester]);
    $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group cadets by MS level and gender
    $groupedCadets = [];
    foreach ($cadets as $cadet) {
        $gender = strtoupper(trim((string)($cadet['gender'] ?? '')));
        if ($gender !== 'MALE' && $gender !== 'FEMALE') {
            $gender = 'FEMALE'; /* default to FEMALE to avoid stray groups */
        }
        $ms = $cadet['ms_level'] ?? 'MS-1';
        if ($ms !== 'MS-1' && $ms !== 'MS-32' && $ms !== 'MS-42') {
            $ms = 'MS-1';
        }
        $key = $ms . '_' . $gender;
        if (!isset($groupedCadets[$key])) {
            $groupedCadets[$key] = [];
        }
        $groupedCadets[$key][] = $cadet;
    }

    // Create output directory if it doesn't exist
    if (!file_exists('output')) {
        mkdir('output', 0777, true);
    }

    // Generate CSV content
    $csvContent = "AER ROSTER OF ENROLLED CADETS - {$semester} Semester S.Y. {$school_year}\n";
    $csvContent .= "Generated on: " . date('F j, Y') . "\n\n";

    // Print in fixed order with continuous numbering per gender across MS levels
    $orderMs = ['MS-1', 'MS-32', 'MS-42'];
    $orderGender = ['MALE', 'FEMALE'];
    $genderCounters = ['MALE' => 0, 'FEMALE' => 0];
    foreach ($orderGender as $gender) {
        foreach ($orderMs as $msLevel) {
            $groupKey = $msLevel . '_' . $gender;
            if (!isset($groupedCadets[$groupKey]) || empty($groupedCadets[$groupKey]))
                continue;
            $csvContent .= "\n{$msLevel} {$gender}\n";
            $csvContent .= "NR,L/NAME,F/NAME,MI,COURSE,DOB,CONTACT NUMBER,ADDRESS\n";
            foreach ($groupedCadets[$groupKey] as $cadet) {
                $genderCounters[$gender]++;
                $rowIndex = $genderCounters[$gender];
                $mi = substr($cadet['middle_name'] ?? '', 0, 1);
                // Handle NULL or empty birthdate values
                $dob = 'N/A';
                if (!empty($cadet['birthdate']) && $cadet['birthdate'] !== null && $cadet['birthdate'] !== '0000-00-00') {
                    $timestamp = strtotime($cadet['birthdate']);
                    if ($timestamp !== false) {
                        $dob = date('d-M-y', $timestamp);
                    }
                }
                // Normalize contact number to local 11-digit mobile starting with 0
                $rawContact = trim((string)($cadet['contact_number'] ?? ''));
                $digits = preg_replace('/\D+/', '', $rawContact);
                // Drop leading country code 63 if present
                if (strpos($digits, '63') === 0) {
                    $digits = substr($digits, 2);
                }
                // Prefer last 10 digits if longer (handles cases like 0917xxxxxxx63, etc.)
                if (strlen($digits) > 11) {
                    $tail10 = substr($digits, -10);
                }
                else {
                    $tail10 = $digits;
                }
                if (preg_match('/^0\d{10}$/', $digits)) {
                    $contact = $digits; // already 0XXXXXXXXXX
                }
                elseif (preg_match('/^9\d{9}$/', $digits)) {
                    $contact = '0' . $digits; // 9XXXXXXXXX -> 09XXXXXXXXX
                }
                elseif (preg_match('/^9\d{9}$/', $tail10)) {
                    $contact = '0' . $tail10; // fallback using last 10 digits
                }
                else {
                    // Fallback: if we can find a 10-digit block starting with 9 anywhere, use it
                    if (preg_match('/9\d{9}/', $digits, $m2)) {
                        $contact = '0' . $m2[0];
                    }
                    else {
                        $contact = $rawContact; // leave as-is
                    }
                }

                // Build address as "City Province" from province_city
                $provinceCityRaw = trim((string)($cadet['province_city'] ?? ''));
                $city = '';
                $prov = '';
                if ($provinceCityRaw !== '') {
                    $parts = preg_split('/\s*,\s*/', $provinceCityRaw);
                    if (count($parts) >= 2) {
                        $first = trim($parts[0]);
                        $second = trim($parts[1]);
                        if (preg_match('/\b(city|municipality)\b/i', $first)) {
                            $city = $first;
                            $prov = $second;
                        }
                        elseif (preg_match('/\b(city|municipality)\b/i', $second)) {
                            $city = $second;
                            $prov = $first;
                        }
                        else {
                            $city = $first;
                            $prov = $second;
                        }
                    }
                    else {
                        $city = $parts[0];
                    }
                }
                if ($city !== '') {
                    $city = preg_replace('/^\s*(City of|Municipality of)\s+/i', '', $city);
                    $city = preg_replace('/\s+City$/i', '', $city);
                    $city = preg_replace('/^City\s+/i', '', $city);
                }
                if ($prov !== '') {
                    $prov = preg_replace('/^\s*(Province of)\s+/i', '', $prov);
                }
                $address = ($city !== '' && $prov !== '') ? trim($city . ' ' . $prov) : (($city !== '') ? $city : (($prov !== '') ? $prov : (trim((string)($cadet['address'] ?? '')) !== '' ? trim((string)$cadet['address']) : 'N/A')));
                $address = preg_replace('/\s+/', ' ', $address);

                // Excel-safe: write as formula to preserve leading zero without showing apostrophe
                // CSV field becomes: ="099..."
                $contactCsv = '="' . $contact . '"';
                $csvContent .= "{$rowIndex},{$cadet['last_name']},{$cadet['first_name']},{$mi},{$cadet['course']},{$dob},{$contactCsv},{$address}\n";
            }
        }
    }

    // Save document
    $outputPath = 'output/AER_Roster_' . date('Y-m-d_H-i-s') . '.csv';
    file_put_contents($outputPath, $csvContent);

    echo json_encode([
        'success' => true,
        'message' => 'Roster document generated successfully',
        'file_path' => $outputPath,
        'download_url' => $outputPath
    ]);
}

function generateBeneficiariesDocument($pdo, $targetSy = '', $targetSem = '')
{
    if ($targetSy !== '' && $targetSem !== '') {
        $school_year = $targetSy;
        $semester = $targetSem;
    }
    else {
        $term = get_active_term();
        $school_year = $term['school_year'] ?? '';
        $semester = $term['semester'] ?? '';
    }
    // Get beneficiary data for all MS levels
    $sql = "SELECT 
                CASE 
                    WHEN u.year_level = '1st Year' OR u.year_level = 'MS1' OR u.year_level = '1' THEN 'MS-1'
                    WHEN u.year_level = '2nd Year' OR u.year_level = 'MS2' OR u.year_level = '2' THEN 'MS-32'
                    WHEN u.year_level = '3rd Year' OR u.year_level = 'MS3' OR u.year_level = '3' THEN 'MS-42'
                    ELSE 'MS-1'
                END as ms_level,
                cp.last_name,
                cp.first_name,
                cp.middle_name,
                cp.course,
                cp.birthdate,
                cp.beneficiary_name,
                cp.beneficiary_address,
                cp.address,
                cp.gender,
                cp.father_name,
                cp.mother_name,
                cp.guardian_name
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            JOIN cadet_enrollments ce ON ce.cadet_profile_id = cp.id
            WHERE u.role = 'basic-cadet'
                AND u.status = 'active' 
                AND u.approval_status = 'approved' 
                AND cp.status = 'Active'
                AND cp.gender IS NOT NULL
                AND ce.school_year = ?
                AND ce.semester = ?
                AND ce.enrollment_status = 'enrolled'
            ORDER BY 
                CASE 
                    WHEN u.year_level = '1st Year' OR u.year_level = 'MS1' OR u.year_level = '1' THEN 1
                    WHEN u.year_level = '2nd Year' OR u.year_level = 'MS2' OR u.year_level = '2' THEN 2
                    WHEN u.year_level = '3rd Year' OR u.year_level = 'MS3' OR u.year_level = '3' THEN 3
                    ELSE 4
                END, 
                cp.gender, cp.last_name, cp.first_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$school_year, $semester]);
    $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug output
    error_log("DEBUG: Beneficiaries query returned " . count($cadets) . " cadets");

    // Create output directory if it doesn't exist
    if (!file_exists('output')) {
        mkdir('output', 0777, true);
    }

    // Group cadets by MS level and gender
    $groupedCadets = [];
    foreach ($cadets as $cadet) {
        $gender = strtoupper(trim((string)($cadet['gender'] ?? '')));
        if ($gender !== 'MALE' && $gender !== 'FEMALE') {
            $gender = 'FEMALE';
        }
        $ms = $cadet['ms_level'] ?? 'MS-1';
        if ($ms !== 'MS-1' && $ms !== 'MS-32' && $ms !== 'MS-42') {
            $ms = 'MS-1';
        }
        $key = $ms . '_' . $gender;
        if (!isset($groupedCadets[$key])) {
            $groupedCadets[$key] = [];
        }
        $groupedCadets[$key][] = $cadet;
    }

    // Generate CSV content
    $csvContent = "AER LIST OF BENEFICIARIES - {$semester} Semester S.Y. {$school_year}\n";
    $csvContent .= "Generated on: " . date('F j, Y') . "\n\n";

    $orderMs = ['MS-1', 'MS-32', 'MS-42'];
    $orderGender = ['MALE', 'FEMALE'];
    $genderCounters = ['MALE' => 0, 'FEMALE' => 0];
    foreach ($orderGender as $gender) {
        foreach ($orderMs as $msLevel) {
            $groupKey = $msLevel . '_' . $gender;
            if (!isset($groupedCadets[$groupKey]) || empty($groupedCadets[$groupKey]))
                continue;
            $csvContent .= "\n{$msLevel} {$gender}\n";
            $csvContent .= "NR,L/NAME,F/NAME,MI,COURSE,DOB,BENEFICIARY,RELATIONSHIP,ADDRESS\n";
            foreach ($groupedCadets[$groupKey] as $cadet) {
                $genderCounters[$gender]++;
                $rowIndex = $genderCounters[$gender];
                $mi = substr($cadet['middle_name'] ?? '', 0, 1);
                // Handle NULL or empty birthdate values
                $dob = 'N/A';
                if (!empty($cadet['birthdate']) && $cadet['birthdate'] !== null && $cadet['birthdate'] !== '0000-00-00') {
                    $timestamp = strtotime($cadet['birthdate']);
                    if ($timestamp !== false) {
                        $dob = date('d-M-y', $timestamp);
                    }
                }
                // Priority system: Father -> Mother -> Guardian
                $beneficiary = 'N/A';
                $relationship = 'N/A';
                if (!empty($cadet['father_name']) && $cadet['father_name'] !== 'N/A') {
                    $beneficiary = $cadet['father_name'];
                    $relationship = 'Father';
                }
                elseif (!empty($cadet['mother_name']) && $cadet['mother_name'] !== 'N/A') {
                    $beneficiary = $cadet['mother_name'];
                    $relationship = 'Mother';
                }
                elseif (!empty($cadet['guardian_name']) && $cadet['guardian_name'] !== 'N/A') {
                    $beneficiary = $cadet['guardian_name'];
                    $relationship = 'Guardian';
                }
                $beneficiaryAddress = $cadet['beneficiary_address'] ?? $cadet['address'];
                $csvContent .= "{$rowIndex},{$cadet['last_name']},{$cadet['first_name']},{$mi},{$cadet['course']},{$dob},{$beneficiary},{$relationship},{$beneficiaryAddress}\n";
            }
        }
    }

    // Save document
    $outputPath = 'output/AER_Beneficiaries_' . date('Y-m-d_H-i-s') . '.csv';
    file_put_contents($outputPath, $csvContent);

    echo json_encode([
        'success' => true,
        'message' => 'Beneficiaries document generated successfully',
        'file_path' => $outputPath,
        'download_url' => $outputPath
    ]);
}

function generateCadetProfileDocument($pdo, $targetSy = '', $targetSem = '')
{
    if ($targetSy !== '' && $targetSem !== '') {
        $school_year = $targetSy;
        $semester = $targetSem;
    }
    else {
        $term = get_active_term();
        $school_year = $term['school_year'] ?? '';
        $semester = $term['semester'] ?? '';
    }
    // Get cadet profile data for all MS levels
    $sql = "SELECT 
                CASE 
                    WHEN u.year_level = '1st Year' OR u.year_level = 'MS1' OR u.year_level = '1' THEN 'MS-1'
                    WHEN u.year_level = '2nd Year' OR u.year_level = 'MS2' OR u.year_level = '2' THEN 'MS-32'
                    WHEN u.year_level = '3rd Year' OR u.year_level = 'MS3' OR u.year_level = '3' THEN 'MS-42'
                    ELSE 'MS-1'
                END as ms_level,
                cp.last_name,
                cp.first_name,
                cp.middle_name,
                cp.course,
                cp.birthdate,
                cp.religion,
                cp.blood_type,
                cp.province_city,
                cp.region,
                cp.height,
                cp.gender
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            JOIN cadet_enrollments ce ON ce.cadet_profile_id = cp.id
            WHERE u.role = 'basic-cadet'
                AND u.status = 'active' 
                AND u.approval_status = 'approved' 
                AND cp.status = 'Active'
                AND cp.gender IS NOT NULL
                AND ce.school_year = ?
                AND ce.semester = ?
                AND ce.enrollment_status = 'enrolled'
            ORDER BY 
                CASE 
                    WHEN u.year_level = '1st Year' OR u.year_level = 'MS1' OR u.year_level = '1' THEN 1
                    WHEN u.year_level = '2nd Year' OR u.year_level = 'MS2' OR u.year_level = '2' THEN 2
                    WHEN u.year_level = '3rd Year' OR u.year_level = 'MS3' OR u.year_level = '3' THEN 3
                    ELSE 4
                END, 
                cp.gender, cp.last_name, cp.first_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$school_year, $semester]);
    $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug output
    error_log("DEBUG: Cadet Profile query returned " . count($cadets) . " cadets");

    // Create output directory if it doesn't exist
    if (!file_exists('output')) {
        mkdir('output', 0777, true);
    }

    // Group cadets by MS level and gender (sanitized) for continuous numbering
    $groupedCadets = [];
    foreach ($cadets as $cadet) {
        $gender = strtoupper(trim((string)($cadet['gender'] ?? '')));
        if ($gender !== 'MALE' && $gender !== 'FEMALE') {
            $gender = 'FEMALE';
        }
        $ms = $cadet['ms_level'] ?? 'MS-1';
        if ($ms !== 'MS-1' && $ms !== 'MS-32' && $ms !== 'MS-42') {
            $ms = 'MS-1';
        }
        $key = $ms . '_' . $gender;
        if (!isset($groupedCadets[$key])) {
            $groupedCadets[$key] = [];
        }
        $groupedCadets[$key][] = $cadet;
    }

    // Generate CSV content
    $csvContent = "AER CADETS PROFILE - {$semester} Semester S.Y. {$school_year}\n";
    $csvContent .= "Generated on: " . date('F j, Y') . "\n\n";

    $orderMs = ['MS-1', 'MS-32', 'MS-42'];
    $orderGender = ['MALE', 'FEMALE'];
    $genderCounters = ['MALE' => 0, 'FEMALE' => 0];
    foreach ($orderGender as $gender) {
        foreach ($orderMs as $msLevel) {
            $groupKey = $msLevel . '_' . $gender;
            if (!isset($groupedCadets[$groupKey]) || empty($groupedCadets[$groupKey]))
                continue;
            $csvContent .= "\n{$msLevel} {$gender}\n";
            $csvContent .= "NR,L/NAME,F/NAME,MI,COURSE,DOB,RELIGION,BT,CITY PROVINCE,RGN,HT\n";
            foreach ($groupedCadets[$groupKey] as $cadet) {
                $genderCounters[$gender]++;
                $rowIndex = $genderCounters[$gender];
                $mi = substr($cadet['middle_name'] ?? '', 0, 1);
                // Handle NULL or empty birthdate values
                $dob = 'N/A';
                if (!empty($cadet['birthdate']) && $cadet['birthdate'] !== null && $cadet['birthdate'] !== '0000-00-00') {
                    $timestamp = strtotime($cadet['birthdate']);
                    if ($timestamp !== false) {
                        $dob = date('d-M-y', $timestamp);
                    }
                }
                $religion = $cadet['religion'] ?? 'RC';
                $bloodType = $cadet['blood_type'] ?? 'O';
                // Normalize Province/City to 'City Province' and strip 'City' words from city
                $provinceCityRaw = trim((string)($cadet['province_city'] ?? ''));
                $city = '';
                $prov = '';
                if ($provinceCityRaw !== '') {
                    $parts = preg_split('/\s*,\s*/', $provinceCityRaw);
                    if (count($parts) >= 2) {
                        $first = trim($parts[0]);
                        $second = trim($parts[1]);
                        if (preg_match('/\b(city|municipality)\b/i', $first)) {
                            $city = $first;
                            $prov = $second;
                        }
                        elseif (preg_match('/\b(city|municipality)\b/i', $second)) {
                            $city = $second;
                            $prov = $first;
                        }
                        else {
                            // Default to first as city, second as province (matches new registration behavior)
                            $city = $first;
                            $prov = $second;
                        }
                    }
                    else {
                        // Single token: treat as city
                        $city = $parts[0];
                    }
                }
                // Strip common prefixes/suffixes from city
                if ($city !== '') {
                    $city = preg_replace('/^\s*(City of|Municipality of)\s+/i', '', $city);
                    $city = preg_replace('/\s+City$/i', '', $city);
                    $city = preg_replace('/^City\s+/i', '', $city);
                }
                // Clean province (remove 'Province of ' if present)
                if ($prov !== '') {
                    $prov = preg_replace('/^\s*(Province of)\s+/i', '', $prov);
                }
                // Combine as "City Province" with a single space (no comma)
                $province = ($city !== '' && $prov !== '') ? (trim($city . ' ' . $prov)) : (($city !== '') ? $city : (($prov !== '') ? $prov : 'N/A'));
                // Collapse any multiple spaces just in case
                $province = preg_replace('/\s+/', ' ', $province);
                // Normalize region to roman code only
                $regionRaw = strtoupper(trim((string)($cadet['region'] ?? 'IV-A')));
                $region = 'IV-A';
                if (preg_match('/(NCR|CAR|BARMM|IV-B|IV-A|XIII|XII|XI|X|IX|VIII|VII|VI|V|IV|III|II|I)/', $regionRaw, $m)) {
                    $region = $m[1];
                }
                $height = $cadet['height'] ?? "5'5";
                $csvContent .= "{$rowIndex},{$cadet['last_name']},{$cadet['first_name']},{$mi},{$cadet['course']},{$dob},{$religion},{$bloodType},{$province},{$region},{$height}\n";
            }
        }
    }

    // Save document
    $outputPath = 'output/AER_Cadet_Profile_' . date('Y-m-d_H-i-s') . '.csv';
    file_put_contents($outputPath, $csvContent);

    echo json_encode([
        'success' => true,
        'message' => 'Cadet Profile document generated successfully',
        'file_path' => $outputPath,
        'download_url' => $outputPath
    ]);
}

function generateASRDocument($pdo, $targetSy = '', $targetSem = '')
{
    if ($targetSy !== '' && $targetSem !== '') {
        $school_year = $targetSy;
        $semester = $targetSem;
    }
    else {
        $term = get_active_term();
        $school_year = $term['school_year'] ?? '';
        $semester = $term['semester'] ?? '';
    }

    // Select enrolled basic cadets for the active term
    $sql = "SELECT 
                cp.last_name,
                cp.first_name,
                cp.middle_name,
                cp.gender
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            JOIN cadet_enrollments ce ON ce.cadet_profile_id = cp.id
            WHERE u.role IN ('basic-cadet','basic_cadet')
                AND u.status = 'active' 
                AND u.approval_status = 'approved' 
                AND cp.status IN ('Active','active')
                AND ce.school_year = ?
                AND ce.semester = ?
                AND ce.enrollment_status = 'enrolled'
            ORDER BY cp.last_name, cp.first_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$school_year, $semester]);
    $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create output directory if it doesn't exist
    if (!file_exists('output')) {
        mkdir('output', 0777, true);
    }

    // Generate CSV content for ASR with FULL NAME grouped by gender
    $csvContent = "ASR COMPLETION LIST - {$semester} Semester S.Y. {$school_year}\n";
    $csvContent .= "Generated on: " . date('F j, Y') . "\n\n";

    // Group cadets by gender
    $groups = [
        'MALE' => [],
        'FEMALE' => [],
    ];

    foreach ($cadets as $cadet) {
        $gender = strtoupper(trim((string)($cadet['gender'] ?? '')));
        if ($gender !== 'MALE' && $gender !== 'FEMALE') {
            $gender = 'FEMALE'; // default bucket for unknown
        }
        $groups[$gender][] = $cadet;
    }

    // Write sections: MALE then FEMALE
    foreach (['MALE', 'FEMALE'] as $genderLabel) {
        if (empty($groups[$genderLabel])) {
            continue;
        }

        $csvContent .= $genderLabel . "\n";
        $csvContent .= "FULL NAME\n";

        foreach ($groups[$genderLabel] as $cadet) {
            $lastRaw = trim((string)($cadet['last_name'] ?? ''));
            $last = $lastRaw !== '' ? ucwords(strtolower($lastRaw)) : '';
            $firstRaw = trim((string)($cadet['first_name'] ?? ''));
            $first = $firstRaw !== '' ? ucwords(strtolower($firstRaw)) : '';
            $middleRaw = trim((string)($cadet['middle_name'] ?? ''));
            $mi = $middleRaw !== '' ? strtoupper(substr($middleRaw, 0, 1)) . '.' : '';

            $fullName = $mi !== ''
                ? "{$last}, {$first} {$mi}"
                : "{$last}, {$first}";

            // CSV-escape so the comma stays inside one column
            $escapedName = '"' . str_replace('"', '""', $fullName) . '"';
            $csvContent .= $escapedName . "\n";
        }

        $csvContent .= "\n"; // blank line between gender sections
    }

    // Save document
    $outputPath = 'output/ASR_Document_' . date('Y-m-d_H-i-s') . '.csv';
    file_put_contents($outputPath, $csvContent);

    echo json_encode([
        'success' => true,
        'message' => 'ASR document generated successfully',
        'file_path' => $outputPath,
        'download_url' => $outputPath
    ]);
}

function generateASRGradeReportDocument($pdo, $targetSy = '', $targetSem = '')
{
    if ($targetSy !== '' && $targetSem !== '') {
        $school_year = $targetSy;
        $semester = $targetSem;
    }
    else {
        $term = get_active_term();
        $school_year = $term['school_year'] ?? '';
        $semester = $term['semester'] ?? '';
    }

    // Select enrolled basic cadets for the active term with gender
    $sql = "SELECT 
                cp.last_name,
                cp.first_name,
                cp.middle_name,
                cp.gender
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            JOIN cadet_enrollments ce ON ce.cadet_profile_id = cp.id
            WHERE u.role IN ('basic-cadet','basic_cadet')
                AND u.status = 'active' 
                AND u.approval_status = 'approved' 
                AND cp.status IN ('Active','active')
                AND ce.school_year = ?
                AND ce.semester = ?
                AND ce.enrollment_status = 'enrolled'
            ORDER BY cp.last_name, cp.first_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$school_year, $semester]);
    $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create output directory if it doesn't exist
    if (!file_exists('output')) {
        mkdir('output', 0777, true);
    }

    // Generate CSV content for ASR Grade Report with FULL NAME grouped by gender
    $csvContent = "ASR GRADE REPORT - {$semester} Semester S.Y. {$school_year}\n";
    $csvContent .= "Generated on: " . date('F j, Y') . "\n\n";

    // Group cadets by gender
    $groups = [
        'MALE' => [],
        'FEMALE' => [],
    ];

    foreach ($cadets as $cadet) {
        $gender = strtoupper(trim((string)($cadet['gender'] ?? '')));
        if ($gender !== 'MALE' && $gender !== 'FEMALE') {
            $gender = 'FEMALE';
        }
        $groups[$gender][] = $cadet;
    }

    foreach (['MALE', 'FEMALE'] as $genderLabel) {
        if (empty($groups[$genderLabel])) {
            continue;
        }

        $csvContent .= $genderLabel . "\n";
        $csvContent .= "FULL NAME\n";

        foreach ($groups[$genderLabel] as $cadet) {
            $firstRaw = trim((string)($cadet['first_name'] ?? ''));
            $first = $firstRaw !== '' ? ucwords(strtolower($firstRaw)) : '';
            $middleRaw = trim((string)($cadet['middle_name'] ?? ''));
            $middle = $middleRaw !== '' ? ucwords(strtolower($middleRaw)) : '';
            $lastRaw = trim((string)($cadet['last_name'] ?? ''));
            $last = $lastRaw !== '' ? ucwords(strtolower($lastRaw)) : '';

            // Build "First Middle Last" (middle omitted if empty)
            if ($middle !== '') {
                $fullName = "{$first} {$middle} {$last}";
            }
            else {
                $fullName = "{$first} {$last}";
            }

            $escapedName = '"' . str_replace('"', '""', $fullName) . '"';
            $csvContent .= $escapedName . "\n";
        }

        $csvContent .= "\n";
    }

    $outputPath = 'output/ASR_Grade_Report_' . date('Y-m-d_H-i-s') . '.csv';
    file_put_contents($outputPath, $csvContent);

    echo json_encode([
        'success' => true,
        'message' => 'ASR grade report generated successfully',
        'file_path' => $outputPath,
        'download_url' => $outputPath
    ]);
}

function generateAttendancePerPlatoon($pdo, $targetSy = '', $targetSem = '')
{
    if ($targetSy !== '' && $targetSem !== '') {
        $school_year = $targetSy;
        $semester = $targetSem;
    }
    else {
        $term = get_active_term();
        $school_year = $term['school_year'] ?? '';
        $semester = $term['semester'] ?? '';
    }

    // Select enrolled cadets grouped by platoon
    $sql = "SELECT 
                cp.platoon,
                cp.gender,
                cp.last_name,
                cp.first_name,
                cp.middle_name
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            JOIN cadet_enrollments ce ON ce.cadet_profile_id = cp.id
            WHERE u.role IN ('basic-cadet','basic_cadet')
                AND u.status = 'active' 
                AND u.approval_status = 'approved' 
                AND cp.status IN ('Active','active')
                AND ce.school_year = ?
                AND ce.semester = ?
                AND ce.enrollment_status = 'enrolled'
            ORDER BY cp.platoon, cp.gender, cp.last_name, cp.first_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$school_year, $semester]);
    $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!file_exists('output')) {
        mkdir('output', 0777, true);
    }

    $csvContent = "\ufeff"; // BOM for Excel
    $csvContent .= "ATTENDANCE PER PLATOON - {$semester} Semester S.Y. {$school_year}\n";
    $csvContent .= "Generated on: " . date('F j, Y') . "\n\n";

    // Group cadets by platoon
    $platoons = [];
    foreach ($cadets as $cadet) {
        $p = !empty($cadet['platoon']) ? $cadet['platoon'] : 'NO PLATOON';
        if (!isset($platoons[$p])) {
            $platoons[$p] = [];
        }
        $platoons[$p][] = $cadet;
    }

    foreach ($platoons as $platoonName => $members) {
        $csvContent .= "PLATOON: {$platoonName}\n";
        $csvContent .= "NR,FULL NAME,GENDER,DATE: ________,DATE: ________,DATE: ________,DATE: ________,DATE: ________\n";

        $count = 1;
        foreach ($members as $cadet) {
            $last = ucwords(strtolower(trim($cadet['last_name'] ?? '')));
            $first = ucwords(strtolower(trim($cadet['first_name'] ?? '')));
            $middleRaw = trim($cadet['middle_name'] ?? '');
            $mi = $middleRaw !== '' ? strtoupper(substr($middleRaw, 0, 1)) . '.' : '';

            $fullName = $mi !== '' ? "{$last}, {$first} {$mi}" : "{$last}, {$first}";
            $gender = $cadet['gender'] ?? 'N/A';

            $csvContent .= "{$count},\"{$fullName}\",{$gender},,,,, \n";
            $count++;
        }
        $csvContent .= "\n";
    }

    $outputPath = 'output/Attendance_Platoon_' . date('Y-m-d_H-i-s') . '.csv';
    file_put_contents($outputPath, $csvContent);

    echo json_encode([
        'success' => true,
        'message' => 'Attendance per platoon document generated successfully',
        'file_path' => $outputPath,
        'download_url' => $outputPath
    ]);
}

function generateQRDataExport($pdo, $targetSy = '', $targetSem = '')
{
    if ($targetSy !== '' && $targetSem !== '') {
        $school_year = $targetSy;
        $semester = $targetSem;
    }
    else {
        $term = get_active_term();
        $school_year = $term['school_year'] ?? '';
        $semester = $term['semester'] ?? '';
    }

    $sql = "SELECT cp.id, cp.student_id, cp.last_name, cp.first_name, cp.middle_name, cp.platoon, cp.gender 
            FROM cadet_profiles cp
            JOIN cadet_enrollments ce ON ce.cadet_profile_id = cp.id
            WHERE ce.school_year = ?
              AND ce.semester = ?
              AND ce.enrollment_status = 'enrolled'
            ORDER BY cp.last_name, cp.first_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$school_year, $semester]);
    $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!file_exists('output')) {
        mkdir('output', 0777, true);
    }

    $csv = '\ufeff'; // BOM for Excel UTF-8
    $csv .= "Name,Student ID,Gender,Platoon,Status,Profile ID\n";

    foreach ($cadets as $cadet) {
        $fullName = trim(($cadet['last_name'] ?? '') . ', ' . ($cadet['first_name'] ?? '') . ' ' . ($cadet['middle_name'] ?? ''));
        $studentId = $cadet['student_id'] ?? '';
        $gender = $cadet['gender'] ?? '';
        $platoon = !empty($cadet['platoon']) ? $cadet['platoon'] : 'DELTA SECOND';
        $profileId = 'CDT-' . $cadet['id'];
        $status = 'Enrolled';

        $csv .= "\"{$fullName}\",{$studentId},{$gender},\"{$platoon}\",{$status},{$profileId}\n";
    }

    $outputPath = 'output/Cadet_QR_Export_' . date('Y-m-d_H-i-s') . '.csv';
    file_put_contents($outputPath, $csv);

    echo json_encode([
        'success' => true,
        'message' => 'Cadet QR data export generated successfully',
        'file_path' => $outputPath,
        'download_url' => $outputPath
    ]);
}
?>