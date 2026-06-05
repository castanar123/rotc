<?php
// Debug script to test cadet profile generation directly

// Start session and simulate admin login
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

require_once 'includes/db.php';

// Capture any errors or warnings
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to capture everything
ob_start();

// Set JSON header
header('Content-Type: application/json');

try {
    // Call the cadet profile generation function directly
    generateCadetProfileDocument($pdo);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Document generation failed: ' . $e->getMessage()]);
}

// Get the captured output
$output = ob_get_contents();
ob_end_clean();

// Display the raw output with debugging info
echo "=== RAW OUTPUT START ===\n";
echo $output;
echo "\n=== RAW OUTPUT END ===\n";

// Show character-by-character analysis of first 100 characters
echo "\n=== CHARACTER ANALYSIS ===\n";
for ($i = 0; $i < min(100, strlen($output)); $i++) {
    $char = $output[$i];
    $ascii = ord($char);
    if ($char === "\n") {
        echo "[$i] \\n (ASCII: $ascii)\n";
    } elseif ($char === "\r") {
        echo "[$i] \\r (ASCII: $ascii)\n";
    } elseif ($char === "\t") {
        echo "[$i] \\t (ASCII: $ascii)\n";
    } elseif ($ascii < 32 || $ascii > 126) {
        echo "[$i] [CTRL] (ASCII: $ascii)\n";
    } else {
        echo "[$i] '$char' (ASCII: $ascii)\n";
    }
}

// Try to decode as JSON
echo "\n=== JSON DECODE TEST ===\n";
$json_data = json_decode($output, true);
if ($json_data === null) {
    echo "JSON decode failed. Error: " . json_last_error_msg() . "\n";
    echo "JSON error code: " . json_last_error() . "\n";
} else {
    echo "JSON decode successful:\n";
    print_r($json_data);
}

// Copy the generateCadetProfileDocument function from generate_document.php
function generateCadetProfileDocument($pdo) {
    // Get all active cadets with their profile information
    $sql = "SELECT 
                u.id,
                cp.last_name,
                cp.first_name,
                cp.middle_name,
                cp.course,
                cp.birthdate,
                cp.contact_number,
                cp.address,
                cp.gender,
                cp.year_level,
                u.role
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE ((cp.year_level IN (1, 2) AND u.role = 'basic_cadet') OR 
                    ((cp.year_level = 31 OR cp.year_level = 32) AND u.role = '2cl') OR 
                    ((cp.year_level = 41 OR cp.year_level = 42) AND u.role = '1cl')) 
                    AND u.status = 'active'
            ORDER BY cp.last_name, cp.first_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create output directory if it doesn't exist
    if (!file_exists('output')) {
        mkdir('output', 0777, true);
    }
    
    // Generate CSV content
    $csvContent = "CADET PROFILES - 2nd Semester S.Y. 2024-25\n";
    $csvContent .= "Generated on: " . date('F j, Y') . "\n\n";
    $csvContent .= "ID,LAST NAME,FIRST NAME,MIDDLE NAME,COURSE,DATE OF BIRTH,CONTACT,ADDRESS,GENDER,YEAR LEVEL,ROLE\n";
    
    foreach ($cadets as $cadet) {
        // Handle NULL or empty birthdate values
        $dob = 'N/A';
        if (!empty($cadet['birthdate'])) {
            $date = DateTime::createFromFormat('Y-m-d', $cadet['birthdate']);
            if ($date !== false) {
                $dob = $date->format('m/d/Y');
            }
        }
        
        $csvContent .= sprintf(
            "%s,%s,%s,%s,%s,%s,%s,\"%s\",%s,%s,%s\n",
            $cadet['id'],
            $cadet['last_name'] ?? '',
            $cadet['first_name'] ?? '',
            $cadet['middle_name'] ?? '',
            $cadet['course'] ?? '',
            $dob,
            $cadet['contact_number'] ?? '',
            str_replace('"', '""', $cadet['address'] ?? ''), // Escape quotes in address
            $cadet['gender'] ?? '',
            $cadet['year_level'] ?? '',
            $cadet['role'] ?? ''
        );
    }
    
    // Save document
    $outputPath = 'output/Cadet_Profiles_' . date('Y-m-d_H-i-s') . '.csv';
    file_put_contents($outputPath, $csvContent);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Cadet profiles document generated successfully',
        'file_path' => $outputPath,
        'download_url' => $outputPath
    ]);
}
?>