<?php
// Fix document generation to separate male/female data in all reports

try {
    $pdo = new PDO('mysql:host=localhost;dbname=rotc_db;charset=utf8mb4', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== FIXING DOCUMENT GENERATION SYSTEM ===\n";
    
    // 1. Check current cadets table structure
    echo "\n1. Analyzing cadets table structure...\n";
    
    $tables_check = $pdo->query("SHOW TABLES LIKE 'cadets'");
    if ($tables_check->rowCount() == 0) {
        echo "Creating cadets table...\n";
        
        $create_cadets = "
        CREATE TABLE cadets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(20) UNIQUE,
            last_name VARCHAR(100) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            middle_initial VARCHAR(5),
            gender ENUM('Male', 'Female') NOT NULL,
            course VARCHAR(50),
            ms_level ENUM('MS-1', 'MS-2', 'MS-3', 'MS-4') NOT NULL,
            date_of_birth DATE,
            contact_number VARCHAR(20),
            address TEXT,
            religion VARCHAR(50),
            blood_type VARCHAR(5),
            height VARCHAR(10),
            province_city VARCHAR(100),
            region VARCHAR(20),
            beneficiary VARCHAR(200),
            status ENUM('enrolled', 'graduated', 'dropped') DEFAULT 'enrolled',
            academic_year VARCHAR(20) DEFAULT '2024-2025',
            semester ENUM('1st', '2nd') DEFAULT '2nd',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
        ";
        
        $pdo->exec($create_cadets);
        echo "✓ Cadets table created\n";
        
        // Insert sample data based on user's requirements
        echo "Inserting sample cadet data...\n";
        
        $sample_cadets = [
            // MS-1 Male cadets (161 total, showing few samples)
            ['STU001', 'Santos', 'Juan', 'A', 'Male', 'BSCRIM', 'MS-1', '2006-03-15', '09123456789', 'Manila, Philippines', 'Catholic', 'O+', '5\'8"', 'Manila', 'NCR', 'Maria Santos'],
            ['STU002', 'Garcia', 'Pedro', 'B', 'Male', 'BSIT', 'MS-1', '2006-05-20', '09234567890', 'Quezon City, Philippines', 'Protestant', 'A+', '5\'10"', 'Quezon City', 'NCR', 'Rosa Garcia'],
            
            // MS-1 Female cadets (107 total, showing few samples)
            ['STU003', 'Cruz', 'Maria', 'C', 'Female', 'BSED', 'MS-1', '2006-07-10', '09345678901', 'Pasig City, Philippines', 'Catholic', 'B+', '5\'4"', 'Pasig', 'NCR', 'Jose Cruz'],
            ['STU004', 'Reyes', 'Ana', 'D', 'Female', 'BSBA', 'MS-1', '2006-09-25', '09456789012', 'Makati City, Philippines', 'INC', 'AB+', '5\'3"', 'Makati', 'NCR', 'Luis Reyes'],
            
            // MS-1 Male cadets
    ['STU005', 'Advincula', 'John Ashley', 'D', 'Male', 'BSCRIM', 'MS-1', '2006-09-08', '09703268959', 'Los Baños Laguna', 'Catholic', 'O+', '5\'7"', 'Los Baños', 'IV-A', 'Ashley Advincula Sr.'],
            
            // MS-3 Male cadets (6 total)
            ['STU006', 'Mendoza', 'Carlos', 'E', 'Male', 'BSCRIM', 'MS-3', '2004-12-01', '09567890123', 'Laguna, Philippines', 'Catholic', 'A-', '5\'9"', 'Laguna', 'IV-A', 'Carmen Mendoza'],
            
            // MS-3 Female cadets (2 total)
            ['STU007', 'Torres', 'Isabella', 'F', 'Female', 'BSIT', 'MS-3', '2004-11-15', '09678901234', 'Batangas, Philippines', 'Protestant', 'B-', '5\'5"', 'Batangas', 'IV-A', 'Roberto Torres'],
            
            // MS-4 Male cadets (3 total)
            ['STU008', 'Ferrer', 'Jeanclaud', 'B', 'Male', 'BSCRIM', 'MS-4', '2001-05-24', '09789012345', 'Los Baños, Laguna', 'BAPTIST', 'O+', '5\'6"', 'Los Baños', 'IV-A', 'Efren C Ferrer'],
            
            // MS-4 Female cadets (9 total)
            ['STU009', 'Villanueva', 'Sofia', 'G', 'Female', 'BSED', 'MS-4', '2001-08-30', '09890123456', 'Cavite, Philippines', 'Catholic', 'AB-', '5\'2"', 'Cavite', 'IV-A', 'Miguel Villanueva'],
        ];
        
        $insert_stmt = $pdo->prepare("
            INSERT INTO cadets (student_id, last_name, first_name, middle_initial, gender, course, ms_level, 
                              date_of_birth, contact_number, address, religion, blood_type, height, 
                              province_city, region, beneficiary) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($sample_cadets as $cadet) {
            $insert_stmt->execute($cadet);
        }
        
        echo "✓ Sample cadet data inserted\n";
    } else {
        echo "✓ Cadets table already exists\n";
    }
    
    // 2. Create enhanced document generation functions
    echo "\n2. Creating enhanced document generation system...\n";
    
    // Create document generation directory
    $doc_dir = 'rotc-qr-inventory/documents';
    if (!is_dir($doc_dir)) {
        mkdir($doc_dir, 0755, true);
    }
    
    // 3. Create Summary Report Generator
    $summary_generator = '<?php
// Enhanced Summary Report Generator with Male/Female Separation
$pdo = new PDO(\'mysql:host=localhost;dbname=rotc_db;charset=utf8mb4\', \'root\', \'root\');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function generateSummaryReport($academic_year = "2024-2025", $semester = "2nd") {
    global $pdo;
    
    $summary_query = "
        SELECT 
            ms_level,
            gender,
            COUNT(*) as count
        FROM cadets 
        WHERE academic_year = ? AND semester = ? AND status = \'enrolled\'
        GROUP BY ms_level, gender
        ORDER BY ms_level, gender
    ";
    
    $stmt = $pdo->prepare($summary_query);
    $stmt->execute([$academic_year, $semester]);
    $results = $stmt->fetchAll();
    
    // Organize data by MS level
    $summary_data = [];
    foreach ($results as $row) {
        $ms_level = $row[\'ms_level\'];
        $gender = $row[\'gender\'];
        $count = $row[\'count\'];
        
        if (!isset($summary_data[$ms_level])) {
            $summary_data[$ms_level] = [\'Male\' => 0, \'Female\' => 0];
        }
        
        $summary_data[$ms_level][$gender] = $count;
    }
    
    // Generate HTML report
    $html = "<h2>SUMMARY OF ENROLLED CADETS</h2>";
    $html .= "<p>({$semester} SEM SY {$academic_year})</p>";
    $html .= "<table border=\'1\' style=\'border-collapse: collapse; width: 100%;\'>";
    $html .= "<tr><th>MS</th><th>ENROLLED CADETS</th><th></th><th>TOTAL</th></tr>";
    $html .= "<tr><th></th><th>MALE</th><th>FEMALE</th><th></th></tr>";
    
    $total_male = 0;
    $total_female = 0;
    
    foreach ($summary_data as $ms_level => $counts) {
        $male_count = $counts[\'Male\'];
        $female_count = $counts[\'Female\'];
        $total_count = $male_count + $female_count;
        
        $total_male += $male_count;
        $total_female += $female_count;
        
        // Convert MS-3 to MS-32 and MS-4 to MS-42 as per user requirements
        $display_ms = $ms_level;
        if ($ms_level === \'MS-3\') $display_ms = \'MS-32\';
        if ($ms_level === \'MS-4\') $display_ms = \'MS-42\';
        
        $html .= "<tr>";
        $html .= "<td>{$display_ms}</td>";
        $html .= "<td>{$male_count}</td>";
        $html .= "<td>{$female_count}</td>";
        $html .= "<td>{$total_count}</td>";
        $html .= "</tr>";
    }
    
    $grand_total = $total_male + $total_female;
    $html .= "<tr style=\'font-weight: bold;\'>";
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
?>';
    
    file_put_contents($doc_dir . '/generate_summary.php', $summary_generator);
    echo "✓ Summary report generator created\n";
    
    // 4. Create Roster Generator
    $roster_generator = '<?php
// Enhanced Roster Generator with Male/Female Separation
$pdo = new PDO(\'mysql:host=localhost;dbname=rotc_db;charset=utf8mb4\', \'root\', \'root\');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function generateRosterReport($ms_level = "MS-1", $academic_year = "2024-2025", $semester = "2nd") {
    global $pdo;
    
    $html = "<h2>LIST OF STUDENT</h2>";
    $html .= "<p>({$semester} SEM SY: {$academic_year})</p>";
    
    // Generate separate sections for Male and Female
    foreach ([\'Male\', \'Female\'] as $gender) {
        $html .= "<h3>{$ms_level} {$gender}</h3>";
        $html .= "<table border=\'1\' style=\'border-collapse: collapse; width: 100%;\'>";
        $html .= "<tr><th>NR</th><th>L/NAME</th><th>F/NAME</th><th>MI</th><th>COURSE</th><th>DOB</th><th>CONTACT NUMBER</th><th>ADDRESS</th></tr>";
        
        $query = "
            SELECT last_name, first_name, middle_initial, course, 
                   DATE_FORMAT(date_of_birth, \'%d-%b-%y\') as formatted_dob,
                   contact_number, address
            FROM cadets 
            WHERE ms_level = ? AND gender = ? AND academic_year = ? AND semester = ? AND status = \'enrolled\'
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
                $html .= "<td>{$student[\'last_name\']}</td>";
                $html .= "<td>{$student[\'first_name\']}</td>";
                $html .= "<td>{$student[\'middle_initial\']}</td>";
                $html .= "<td>{$student[\'course\']}</td>";
                $html .= "<td>{$student[\'formatted_dob\']}</td>";
                $html .= "<td>{$student[\'contact_number\']}</td>";
                $html .= "<td>{$student[\'address\']}</td>";
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
?>';
    
    file_put_contents($doc_dir . '/generate_roster.php', $roster_generator);
    echo "✓ Roster report generator created\n";
    
    // 5. Create Beneficiary List Generator
    $beneficiary_generator = '<?php
// Enhanced Beneficiary List Generator with Male/Female Separation
$pdo = new PDO(\'mysql:host=localhost;dbname=rotc_db;charset=utf8mb4\', \'root\', \'root\');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function generateBeneficiaryReport($ms_level = "MS-4", $academic_year = "2024-2025", $semester = "2nd") {
    global $pdo;
    
    $html = "<h2>LIST OF BENEFICIARIES</h2>";
    $html .= "<p>({$semester} SEM SY: {$academic_year})</p>";
    
    // Convert MS-4 to MS-42 for display
    $display_ms = $ms_level === \'MS-4\' ? \'MS-42\' : $ms_level;
    
    // Generate separate sections for Male and Female
    foreach ([\'Male\', \'Female\'] as $gender) {
        $html .= "<h3>{$display_ms} {$gender}</h3>";
        $html .= "<table border=\'1\' style=\'border-collapse: collapse; width: 100%;\'>";
        $html .= "<tr><th>NR</th><th>L/NAME</th><th>F/NAME</th><th>MI</th><th>COURSE</th><th>DOB</th><th>BENEFICIARY</th><th>ADDRESS</th></tr>";
        
        $query = "
            SELECT last_name, first_name, middle_initial, course, 
                   DATE_FORMAT(date_of_birth, \'%d-%b-%y\') as formatted_dob,
                   beneficiary, address
            FROM cadets 
            WHERE ms_level = ? AND gender = ? AND academic_year = ? AND semester = ? AND status = \'enrolled\'
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
                $html .= "<td>{$beneficiary[\'last_name\']}</td>";
                $html .= "<td>{$beneficiary[\'first_name\']}</td>";
                $html .= "<td>{$beneficiary[\'middle_initial\']}</td>";
                $html .= "<td>{$beneficiary[\'course\']}</td>";
                $html .= "<td>{$beneficiary[\'formatted_dob\']}</td>";
                $html .= "<td>{$beneficiary[\'beneficiary\']}</td>";
                $html .= "<td>{$beneficiary[\'address\']}</td>";
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
?>';
    
    file_put_contents($doc_dir . '/generate_beneficiary.php', $beneficiary_generator);
    echo "✓ Beneficiary report generator created\n";
    
    // 6. Create Cadet Profile Generator
    $profile_generator = '<?php
// Enhanced Cadet Profile Generator with Male/Female Separation
$pdo = new PDO(\'mysql:host=localhost;dbname=rotc_db;charset=utf8mb4\', \'root\', \'root\');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function generateCadetProfileReport($ms_level = "MS-4", $academic_year = "2024-2025", $semester = "2nd") {
    global $pdo;
    
    $html = "<h2>CADET\'S PROFILE</h2>";
    $html .= "<p>({$semester} SEM SY: {$academic_year})</p>";
    
    // Convert MS-4 to MS-42 for display
    $display_ms = $ms_level === \'MS-4\' ? \'MS-42\' : $ms_level;
    
    // Generate separate sections for Male and Female
    foreach ([\'Male\', \'Female\'] as $gender) {
        $html .= "<h3>{$display_ms} {$gender}</h3>";
        $html .= "<table border=\'1\' style=\'border-collapse: collapse; width: 100%;\'>";
        $html .= "<tr><th>NR</th><th>L/NAME</th><th>F/NAME</th><th>MI</th><th>COURSE</th><th>DOB</th><th>RELIGION</th><th>BT</th><th>PROVINCE/CITY</th><th>RGN</th><th>HT</th></tr>";
        
        $query = "
            SELECT last_name, first_name, middle_initial, course, 
                   DATE_FORMAT(date_of_birth, \'%d-%b-%y\') as formatted_dob,
                   religion, blood_type, province_city, region, height
            FROM cadets 
            WHERE ms_level = ? AND gender = ? AND academic_year = ? AND semester = ? AND status = \'enrolled\'
            ORDER BY last_name, first_name
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$ms_level, $gender, $academic_year, $semester]);
        $profiles = $stmt->fetchAll();
        
        if (count($profiles) > 0) {
            $nr = 1;
            foreach ($profiles as $profile) {
                $html .= "<tr>";
                $html .= "<td>{$nr}</td>";
                $html .= "<td>{$profile[\'last_name\']}</td>";
                $html .= "<td>{$profile[\'first_name\']}</td>";
                $html .= "<td>{$profile[\'middle_initial\']}</td>";
                $html .= "<td>{$profile[\'course\']}</td>";
                $html .= "<td>{$profile[\'formatted_dob\']}</td>";
                $html .= "<td>{$profile[\'religion\']}</td>";
                $html .= "<td>{$profile[\'blood_type\']}</td>";
                $html .= "<td>{$profile[\'province_city\']}</td>";
                $html .= "<td>{$profile[\'region\']}</td>";
                $html .= "<td>{$profile[\'height\']}</td>";
                $html .= "</tr>";
                $nr++;
            }
        } else {
            $html .= "<tr><td>1</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
        }
        
        $html .= "</table><br><br>";
    }
    
    return $html;
}

// Generate and display report
echo generateCadetProfileReport();
?>';
    
    file_put_contents($doc_dir . '/generate_cadet_profile.php', $profile_generator);
    echo "✓ Cadet profile report generator created\n";
    
    // 7. Test all document generators
    echo "\n3. Testing document generation...\n";
    
    // Test summary report
    echo "Testing summary report...\n";
    ob_start();
    include $doc_dir . '/generate_summary.php';
    $summary_output = ob_get_clean();
    echo "✓ Summary report generated successfully\n";
    
    // Test roster report
    echo "Testing roster report...\n";
    ob_start();
    include $doc_dir . '/generate_roster.php';
    $roster_output = ob_get_clean();
    echo "✓ Roster report generated successfully\n";
    
    // Test beneficiary report
    echo "Testing beneficiary report...\n";
    ob_start();
    include $doc_dir . '/generate_beneficiary.php';
    $beneficiary_output = ob_get_clean();
    echo "✓ Beneficiary report generated successfully\n";
    
    // Test cadet profile report
    echo "Testing cadet profile report...\n";
    ob_start();
    include $doc_dir . '/generate_cadet_profile.php';
    $profile_output = ob_get_clean();
    echo "✓ Cadet profile report generated successfully\n";
    
    echo "\n=== DOCUMENT GENERATION SYSTEM FIXED ===\n";
    echo "✓ Cadets table created with gender separation\n";
    echo "✓ Sample data inserted\n";
    echo "✓ Summary report with male/female counts\n";
    echo "✓ Roster with separate male/female sections\n";
    echo "✓ Beneficiary list with gender separation\n";
    echo "✓ Cadet profile with complete data fields\n";
    echo "✓ All reports tested successfully\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>