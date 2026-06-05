<?php
// Generate Basic Cadet List with Student Numbers
require_once 'includes/db.php';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="Basic_Cadet_List_' . date('Y-m-d') . '.csv"');

require_once 'includes/term_enrollment.php';

function generateBasicCadetList($pdo)
{
    // Get term from GET params or active term
    $school_year = $_GET['school_year'] ?? '';
    $semester = $_GET['semester'] ?? '';

    if ($school_year === '' || $semester === '') {
        $term = get_active_term();
        $school_year = $term['school_year'] ?? '';
        $semester = $term['semester'] ?? '';
    }

    // Get all basic cadet data with student numbers, filtered by enrollment
    $sql = "SELECT 
                CASE 
                    WHEN u.year_level = '1st Year' OR u.year_level = 'MS1' OR u.year_level = '1' THEN 'MS-1'
                    WHEN u.year_level = '2nd Year' OR u.year_level = 'MS2' OR u.year_level = '2' THEN 'MS-32'
                    WHEN u.year_level = '3rd Year' OR u.year_level = 'MS3' OR u.year_level = '3' THEN 'MS-42'
                    ELSE 'MS-1'
                END as ms_level,
                cp.student_id,
                cp.student_number,
                cp.last_name,
                cp.first_name,
                cp.middle_name,
                cp.course,
                cp.birthdate,
                cp.contact_number,
                cp.address,
                cp.gender,
                cp.platoon,

                u.email
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            JOIN cadet_enrollments ce ON ce.cadet_profile_id = cp.id
            WHERE u.role = 'basic-cadet'
                AND u.status = 'active' 
                AND u.approval_status = 'approved' 
                AND cp.status = 'Active'
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
    $csvContent = "BASIC CADET LIST WITH STUDENT NUMBERS - {$semester} Semester S.Y. {$school_year}\n";
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
            $csvContent .= "NR,STUDENT_ID,STUDENT_NUMBER,L/NAME,F/NAME,MI,COURSE,PLATOON,CONTACT,EMAIL\n";

            foreach ($groupedCadets[$groupKey] as $cadet) {
                $genderCounters[$gender]++;
                $rowIndex = $genderCounters[$gender];
                $mi = substr($cadet['middle_name'] ?? '', 0, 1);

                // Get student identifiers
                $studentId = $cadet['student_id'] ?? 'N/A';
                $studentNumber = $cadet['student_number'] ?? 'N/A';

                $csvContent .= sprintf(
                    "%d,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                    $rowIndex,
                    $studentId,
                    $studentNumber,
                    $cadet['last_name'] ?? '',
                    $cadet['first_name'] ?? '',
                    $mi,
                    $cadet['course'] ?? '',
                    $cadet['platoon'] ?? '',
                    $cadet['contact_number'] ?? '',
                    $cadet['email'] ?? ''
                );
            }
        }
    }

    // Add summary at the end
    $totalMale = $genderCounters['MALE'];
    $totalFemale = $genderCounters['FEMALE'];
    $totalCadets = $totalMale + $totalFemale;

    $csvContent .= "\n\nSUMMARY\n";
    $csvContent .= "Total Male Cadets: {$totalMale}\n";
    $csvContent .= "Total Female Cadets: {$totalFemale}\n";
    $csvContent .= "Total Basic Cadets: {$totalCadets}\n";

    return $csvContent;
}

try {
    // Generate and output the CSV
    $csvContent = generateBasicCadetList($pdo);
    echo $csvContent;
}
catch (Exception $e) {
    // If there's an error, output it as CSV content
    echo "Error generating Basic Cadet List: " . $e->getMessage();
}
?>