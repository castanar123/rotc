<?php
// Test Document Generation Functions Directly
require_once 'includes/db.php';

echo "=== DIRECT DOCUMENT GENERATION FUNCTION TEST ===\n\n";

// Copy the document generation functions without authentication
function testGenerateBeneficiariesDocument($pdo) {
    echo "Testing Beneficiaries Document Generation...\n";
    
    // Get beneficiary data
    $sql = "SELECT 
                CASE 
                    WHEN cp.year_level = 1 THEN 'MS-1'
                    WHEN cp.year_level = 2 THEN 'MS-32'
                    WHEN cp.year_level = 3 THEN 'MS-42'
                    ELSE 'Other'
                END as ms_level,
                cp.last_name,
                cp.first_name,
                cp.middle_name,
                cp.course,
                cp.birth_date,
                cp.beneficiary_name,
                cp.beneficiary_address,
                cp.address,
                cp.gender,
                cp.father_name,
                cp.mother_name,
                cp.guardian_name
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE u.status = 'active'
            ORDER BY cp.year_level, cp.gender, cp.last_name, cp.first_name";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "✅ Query executed successfully - Found " . count($cadets) . " cadets\n";
        
        if (count($cadets) > 0) {
            // Create output directory if it doesn't exist
            if (!file_exists('output')) {
                mkdir('output', 0777, true);
            }
            
            // Group cadets by MS level and gender
            $groupedCadets = [];
            foreach ($cadets as $cadet) {
                $key = $cadet['ms_level'] . '_' . strtoupper($cadet['gender']);
                if (!isset($groupedCadets[$key])) {
                    $groupedCadets[$key] = [];
                }
                $groupedCadets[$key][] = $cadet;
            }
            
            // Generate CSV content
            $csvContent = "AER LIST OF BENEFICIARIES - 2nd Semester S.Y. 2024-25\n";
            $csvContent .= "Generated on: " . date('F j, Y') . "\n\n";
            
            foreach ($groupedCadets as $groupKey => $groupCadets) {
                $parts = explode('_', $groupKey);
                $msLevel = $parts[0];
                $gender = $parts[1];
                
                $csvContent .= "\n{$msLevel} {$gender}\n";
                $csvContent .= "NR,L/NAME,F/NAME,MI,COURSE,DOB,BENEFICIARY,RELATIONSHIP,ADDRESS\n";
                
                foreach ($groupCadets as $index => $cadet) {
                    $rowIndex = $index + 1;
                    $mi = substr($cadet['middle_name'] ?? '', 0, 1);
                    
                    // Handle NULL or empty birthdate values
                    $dob = 'N/A';
                    if (!empty($cadet['birth_date']) && $cadet['birth_date'] !== null) {
                        $timestamp = strtotime($cadet['birth_date']);
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
                    } elseif (!empty($cadet['mother_name']) && $cadet['mother_name'] !== 'N/A') {
                        $beneficiary = $cadet['mother_name'];
                        $relationship = 'Mother';
                    } elseif (!empty($cadet['guardian_name']) && $cadet['guardian_name'] !== 'N/A') {
                        $beneficiary = $cadet['guardian_name'];
                        $relationship = 'Guardian';
                    }
                    
                    $beneficiaryAddress = $cadet['beneficiary_address'] ?? $cadet['address'];
                    
                    $csvContent .= "{$rowIndex},{$cadet['last_name']},{$cadet['first_name']},{$mi},{$cadet['course']},{$dob},{$beneficiary},{$relationship},{$beneficiaryAddress}\n";
                }
            }
            
            // Save document
            $outputPath = 'output/TEST_AER_Beneficiaries_' . date('Y-m-d_H-i-s') . '.csv';
            $result = file_put_contents($outputPath, $csvContent);
            
            if ($result !== false) {
                echo "✅ Beneficiaries document generated successfully\n";
                echo "  - File: {$outputPath}\n";
                echo "  - Size: {$result} bytes\n";
            } else {
                echo "❌ Failed to save beneficiaries document\n";
            }
        } else {
            echo "⚠️ No cadets found for beneficiaries document\n";
        }
        
    } catch (PDOException $e) {
        echo "❌ Beneficiaries document generation failed: " . $e->getMessage() . "\n";
    }
}

function testGenerateCadetProfileDocument($pdo) {
    echo "\nTesting Cadet Profile Document Generation...\n";
    
    // Get cadet profile data
    $sql = "SELECT 
                CASE 
                    WHEN cp.year_level = 1 THEN 'MS-1'
                    WHEN cp.year_level = 2 THEN 'MS-32'
                    WHEN cp.year_level = 3 THEN 'MS-42'
                    ELSE 'Other'
                END as ms_level,
                cp.last_name,
                cp.first_name,
                cp.middle_name,
                cp.course,
                cp.birth_date,
                cp.religion,
                cp.blood_type,
                cp.province_city,
                cp.region,
                cp.height,
                cp.gender
            FROM users u
            LEFT JOIN cadet_profiles cp ON u.id = cp.user_id 
            WHERE u.status = 'active'
            ORDER BY cp.year_level, cp.gender, cp.last_name, cp.first_name";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $cadets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "✅ Query executed successfully - Found " . count($cadets) . " cadets\n";
        
        if (count($cadets) > 0) {
            // Create output directory if it doesn't exist
            if (!file_exists('output')) {
                mkdir('output', 0777, true);
            }
            
            // Group cadets by MS level and gender
            $groupedCadets = [];
            foreach ($cadets as $cadet) {
                $key = $cadet['ms_level'] . '_' . strtoupper($cadet['gender']);
                if (!isset($groupedCadets[$key])) {
                    $groupedCadets[$key] = [];
                }
                $groupedCadets[$key][] = $cadet;
            }
            
            // Generate CSV content
            $csvContent = "AER CADETS PROFILE - 2nd Semester S.Y. 2024-25\n";
            $csvContent .= "Generated on: " . date('F j, Y') . "\n\n";
            
            foreach ($groupedCadets as $groupKey => $groupCadets) {
                $parts = explode('_', $groupKey);
                $msLevel = $parts[0];
                $gender = $parts[1];
                
                $csvContent .= "\n{$msLevel} {$gender}\n";
                $csvContent .= "NR,L/NAME,F/NAME,MI,COURSE,DOB,RELIGION,BT,PROVINCE/CITY,RGN,HT\n";
                
                foreach ($groupCadets as $index => $cadet) {
                    $rowIndex = $index + 1;
                    $mi = substr($cadet['middle_name'] ?? '', 0, 1);
                    
                    // Handle NULL or empty birthdate values
                    $dob = 'N/A';
                    if (!empty($cadet['birth_date']) && $cadet['birth_date'] !== null) {
                        $timestamp = strtotime($cadet['birth_date']);
                        if ($timestamp !== false) {
                            $dob = date('d-M-y', $timestamp);
                        }
                    }
                    
                    $religion = $cadet['religion'] ?? 'RC';
                    $bloodType = $cadet['blood_type'] ?? 'O';
                    $province = $cadet['province_city'] ?? 'N/A';
                    $region = $cadet['region'] ?? 'IV-A';
                    $height = $cadet['height'] ?? "5'5";
                    
                    $csvContent .= "{$rowIndex},{$cadet['last_name']},{$cadet['first_name']},{$mi},{$cadet['course']},{$dob},{$religion},{$bloodType},{$province},{$region},{$height}\n";
                }
            }
            
            // Save document
            $outputPath = 'output/TEST_AER_Cadet_Profile_' . date('Y-m-d_H-i-s') . '.csv';
            $result = file_put_contents($outputPath, $csvContent);
            
            if ($result !== false) {
                echo "✅ Cadet profile document generated successfully\n";
                echo "  - File: {$outputPath}\n";
                echo "  - Size: {$result} bytes\n";
            } else {
                echo "❌ Failed to save cadet profile document\n";
            }
        } else {
            echo "⚠️ No cadets found for cadet profile document\n";
        }
        
    } catch (PDOException $e) {
        echo "❌ Cadet profile document generation failed: " . $e->getMessage() . "\n";
    }
}

try {
    // Test database connection
    echo "1. TESTING DATABASE CONNECTION:\n";
    $test_query = "SELECT COUNT(*) as count FROM cadet_profiles";
    $stmt = $pdo->prepare($test_query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Database connected - Found {$result['count']} cadet profiles\n\n";
    
    // Test document generation functions
    echo "2. TESTING DOCUMENT GENERATION FUNCTIONS:\n";
    testGenerateBeneficiariesDocument($pdo);
    testGenerateCadetProfileDocument($pdo);
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ General error: " . $e->getMessage() . "\n";
}

echo "\n🎯 DIRECT DOCUMENT GENERATION TEST COMPLETED\n";
?>