<?php
// Enhanced Summary Report Generator with Male/Female Separation
$pdo = new PDO('mysql:host=localhost;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function generateSummaryReport($academic_year = "2024-2025", $semester = "2nd") {
    global $pdo;
    
    $summary_query = "
        SELECT 
            ms_level,
            gender,
            COUNT(*) as count
        FROM cadets 
        WHERE academic_year = ? AND semester = ? AND status = 'enrolled'
        GROUP BY ms_level, gender
        ORDER BY ms_level, gender
    ";
    
    $stmt = $pdo->prepare($summary_query);
    $stmt->execute([$academic_year, $semester]);
    $results = $stmt->fetchAll();
    
    // Organize data by MS level
    $summary_data = [];
    foreach ($results as $row) {
        $ms_level = $row['ms_level'];
        $gender = $row['gender'];
        $count = $row['count'];
        
        if (!isset($summary_data[$ms_level])) {
            $summary_data[$ms_level] = ['Male' => 0, 'Female' => 0];
        }
        
        $summary_data[$ms_level][$gender] = $count;
    }
    
    // Generate HTML report
    $html = "<h2>SUMMARY OF ENROLLED CADETS</h2>";
    $html .= "<p>({$semester} SEM SY {$academic_year})</p>";
    $html .= "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    $html .= "<tr><th>MS</th><th>ENROLLED CADETS</th><th></th><th>TOTAL</th></tr>";
    $html .= "<tr><th></th><th>MALE</th><th>FEMALE</th><th></th></tr>";
    
    $total_male = 0;
    $total_female = 0;
    
    foreach ($summary_data as $ms_level => $counts) {
        $male_count = $counts['Male'];
        $female_count = $counts['Female'];
        $total_count = $male_count + $female_count;
        
        $total_male += $male_count;
        $total_female += $female_count;
        
        // Convert MS-3 to MS-32 and MS-4 to MS-42 as per user requirements
        $display_ms = $ms_level;
        if ($ms_level === 'MS-3') $display_ms = 'MS-32';
        if ($ms_level === 'MS-4') $display_ms = 'MS-42';
        
        $html .= "<tr>";
        $html .= "<td>{$display_ms}</td>";
        $html .= "<td>{$male_count}</td>";
        $html .= "<td>{$female_count}</td>";
        $html .= "<td>{$total_count}</td>";
        $html .= "</tr>";
    }
    
    $grand_total = $total_male + $total_female;
    $html .= "<tr style='font-weight: bold;'>";
    $html .= "<td>TOTAL</td>";
    $html .= "<td>{$total_male}</td>";
    $html .= "<td>{$total_female}</td>";
    $html .= "<td>{$grand_total}</td>";
    $html .= "</tr>";
    $html .= "</table>";
    
    return $html;
}

// Generate and display report
echo generateSummaryReport();
?>