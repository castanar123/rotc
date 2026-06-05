<?php
// Enhanced Beneficiary List Generator with Male/Female Separation
$pdo = new PDO('mysql:host=localhost;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function generateBeneficiaryReport($ms_level = "MS-4", $academic_year = "2024-2025", $semester = "2nd") {
    global $pdo;
    
    $html = "<h2>LIST OF BENEFICIARIES</h2>";
    $html .= "<p>({$semester} SEM SY: {$academic_year})</p>";
    
    // Convert MS-4 to MS-42 for display
    $display_ms = $ms_level === 'MS-4' ? 'MS-42' : $ms_level;
    
    // Generate separate sections for Male and Female
    foreach (['Male', 'Female'] as $gender) {
        $html .= "<h3>{$display_ms} {$gender}</h3>";
        $html .= "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        $html .= "<tr><th>NR</th><th>L/NAME</th><th>F/NAME</th><th>MI</th><th>COURSE</th><th>DOB</th><th>BENEFICIARY</th><th>ADDRESS</th></tr>";
        
        $query = "
            SELECT last_name, first_name, middle_initial, course, 
                   DATE_FORMAT(date_of_birth, '%d-%b-%y') as formatted_dob,
                   beneficiary, address
            FROM cadets 
            WHERE ms_level = ? AND gender = ? AND academic_year = ? AND semester = ? AND status = 'enrolled'
            ORDER BY last_name, first_name
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$ms_level, $gender, $academic_year, $semester]);
        $beneficiaries = $stmt->fetchAll();
        
        if (count($beneficiaries) > 0) {
            $nr = 1;
            foreach ($beneficiaries as $beneficiary) {
                $html .= "<tr>";
                $html .= "<td>{$nr}</td>";
                $html .= "<td>{$beneficiary['last_name']}</td>";
                $html .= "<td>{$beneficiary['first_name']}</td>";
                $html .= "<td>{$beneficiary['middle_initial']}</td>";
                $html .= "<td>{$beneficiary['course']}</td>";
                $html .= "<td>{$beneficiary['formatted_dob']}</td>";
                $html .= "<td>{$beneficiary['beneficiary']}</td>";
                $html .= "<td>{$beneficiary['address']}</td>";
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
echo generateBeneficiaryReport();
?>