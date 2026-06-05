<?php
// Enhanced Roster Generator with Male/Female Separation
$pdo = new PDO('mysql:host=localhost;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function generateRosterReport($ms_level = "MS-2", $academic_year = "2024-2025", $semester = "2nd") {
    global $pdo;
    
    $html = "<h2>LIST OF STUDENT</h2>";
    $html .= "<p>({$semester} SEM SY: {$academic_year})</p>";
    
    // Generate separate sections for Male and Female
    foreach (['Male', 'Female'] as $gender) {
        $html .= "<h3>{$ms_level} {$gender}</h3>";
        $html .= "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        $html .= "<tr><th>NR</th><th>L/NAME</th><th>F/NAME</th><th>MI</th><th>COURSE</th><th>DOB</th><th>CONTACT NUMBER</th><th>ADDRESS</th></tr>";
        
        $query = "
            SELECT last_name, first_name, middle_initial, course, 
                   DATE_FORMAT(date_of_birth, '%d-%b-%y') as formatted_dob,
                   contact_number, address
            FROM cadets 
            WHERE ms_level = ? AND gender = ? AND academic_year = ? AND semester = ? AND status = 'enrolled'
            ORDER BY last_name, first_name
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$ms_level, $gender, $academic_year, $semester]);
        $students = $stmt->fetchAll();
        
        if (count($students) > 0) {
            $nr = 1;
            foreach ($students as $student) {
                $html .= "<tr>";
                $html .= "<td>{$nr}</td>";
                $html .= "<td>{$student['last_name']}</td>";
                $html .= "<td>{$student['first_name']}</td>";
                $html .= "<td>{$student['middle_initial']}</td>";
                $html .= "<td>{$student['course']}</td>";
                $html .= "<td>{$student['formatted_dob']}</td>";
                $html .= "<td>{$student['contact_number']}</td>";
                $html .= "<td>{$student['address']}</td>";
                $html .= "</tr>";
                $nr++;
            }
        } else {
            $html .= "<tr><td>1</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
        }
        
        $html .= "</table><br><br>";
    }
    
    return $html;
}

// Generate and display report
echo generateRosterReport();
?>