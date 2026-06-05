<?php
require_once 'includes/db.php';

echo "=== COMPREHENSIVE PHP FILES SQL AUDIT ===\n";
echo "Checking all PHP files for SQL queries and database compatibility...\n\n";

// Get all PHP files recursively
function getAllPhpFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

$phpFiles = getAllPhpFiles('.');
echo "Found " . count($phpFiles) . " PHP files to audit\n\n";

$issues = [];
$totalQueries = 0;
$problematicFiles = [];

// Common problematic patterns to check
$patterns = [
    'cadet_profile\s+(?!s)' => 'Using singular table name "cadet_profile" instead of "cadet_profiles"',
    'cp\.birthdate' => 'Using "cp.birthdate" instead of "cp.birth_date"',
    'cp\.father(?!_name)' => 'Using "cp.father" instead of "cp.father_name"',
    'cp\.mother(?!_name)' => 'Using "cp.mother" instead of "cp.mother_name"',
    'cp\.guardian(?!_name)' => 'Using "cp.guardian" instead of "cp.guardian_name"',
    'profile_photo' => 'Using "profile_photo" instead of "photo_path"',
    'student_number' => 'Using "student_number" instead of "student_id"',
    'full_name' => 'Using "full_name" (check if should be first_name + last_name)',
];

foreach ($phpFiles as $file) {
    $relativePath = str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $file);
    $content = file_get_contents($file);
    
    // Skip if file is too small or doesn't contain SQL
    if (strlen($content) < 50 || !preg_match('/SELECT|INSERT|UPDATE|DELETE|FROM|JOIN/i', $content)) {
        continue;
    }
    
    echo "Checking: $relativePath\n";
    $fileIssues = [];
    
    // Count SQL queries
    $queryCount = preg_match_all('/(SELECT|INSERT|UPDATE|DELETE)\s+/i', $content, $matches);
    $totalQueries += $queryCount;
    
    if ($queryCount > 0) {
        echo "  Found $queryCount SQL queries\n";
        
        // Check for problematic patterns
        foreach ($patterns as $pattern => $description) {
            if (preg_match('/' . $pattern . '/i', $content, $matches)) {
                $fileIssues[] = $description;
                echo "  ❌ $description\n";
            }
        }
        
        // Check for specific table references
        if (preg_match_all('/FROM\s+(\w+)/i', $content, $tableMatches)) {
            $tables = array_unique($tableMatches[1]);
            foreach ($tables as $table) {
                if (strtolower($table) === 'cadet_profile') {
                    $fileIssues[] = "References non-existent table 'cadet_profile'";
                    echo "  ❌ References non-existent table 'cadet_profile'\n";
                }
            }
        }
        
        // Check for JOIN statements
        if (preg_match_all('/JOIN\s+(\w+)/i', $content, $joinMatches)) {
            $joinTables = array_unique($joinMatches[1]);
            foreach ($joinTables as $table) {
                if (strtolower($table) === 'cadet_profile') {
                    $fileIssues[] = "JOINs with non-existent table 'cadet_profile'";
                    echo "  ❌ JOINs with non-existent table 'cadet_profile'\n";
                }
            }
        }
        
        // Check for prepared statements vs direct queries
        $directQueries = preg_match_all('/\$pdo->query\s*\(/i', $content);
        $preparedQueries = preg_match_all('/\$pdo->prepare\s*\(/i', $content);
        
        if ($directQueries > $preparedQueries) {
            $fileIssues[] = "More direct queries ($directQueries) than prepared statements ($preparedQueries) - potential security risk";
            echo "  ⚠️  More direct queries ($directQueries) than prepared statements ($preparedQueries)\n";
        }
        
        if (empty($fileIssues)) {
            echo "  ✅ No issues found\n";
        } else {
            $problematicFiles[$relativePath] = $fileIssues;
        }
    }
    
    echo "\n";
}

echo "\n=== AUDIT SUMMARY ===\n";
echo "Total PHP files checked: " . count($phpFiles) . "\n";
echo "Total SQL queries found: $totalQueries\n";
echo "Files with issues: " . count($problematicFiles) . "\n\n";

if (!empty($problematicFiles)) {
    echo "=== FILES REQUIRING ATTENTION ===\n";
    foreach ($problematicFiles as $file => $issues) {
        echo "\n📁 $file:\n";
        foreach ($issues as $issue) {
            echo "  • $issue\n";
        }
    }
    
    echo "\n=== RECOMMENDED ACTIONS ===\n";
    echo "1. Fix table name references from 'cadet_profile' to 'cadet_profiles'\n";
    echo "2. Update column references to match actual database schema\n";
    echo "3. Consider using prepared statements for better security\n";
    echo "4. Test all affected functionality after fixes\n";
} else {
    echo "🎉 All PHP files passed the audit! No database compatibility issues found.\n";
}

echo "\n=== TESTING CRITICAL QUERIES ===\n";

// Test some critical queries to ensure they work
$criticalTests = [
    'Users table' => 'SELECT COUNT(*) as count FROM users',
    'Cadet profiles table' => 'SELECT COUNT(*) as count FROM cadet_profiles',
    'Missing ID requests table' => 'SELECT COUNT(*) as count FROM missing_id_requests',
    'User-Cadet relationship' => 'SELECT COUNT(*) as count FROM users u JOIN cadet_profiles cp ON u.id = cp.user_id',
    'Document generation columns' => 'SELECT cp.birth_date, cp.father_name, cp.mother_name, cp.guardian_name FROM cadet_profiles cp LIMIT 1',
    'Missing ID with facebook' => 'SELECT cp.facebook_profile FROM cadet_profiles cp WHERE cp.facebook_profile IS NOT NULL LIMIT 1'
];

foreach ($criticalTests as $testName => $query) {
    try {
        $stmt = $pdo->query($query);
        $result = $stmt->fetch();
        echo "✅ $testName: PASSED\n";
    } catch (Exception $e) {
        echo "❌ $testName: FAILED - " . $e->getMessage() . "\n";
    }
}

echo "\n=== AUDIT COMPLETE ===\n";
?>