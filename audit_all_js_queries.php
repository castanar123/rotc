<?php

/**
 * Focused JavaScript Audit for Database Queries and AJAX Calls
 * Scans project JS files (excluding vendor/node_modules) for database-related operations
 */

echo "=== JAVASCRIPT DATABASE AUDIT ===\n";
echo "Scanning project JavaScript files for database queries and AJAX calls...\n\n";

$issues = [];
$totalFiles = 0;
$filesWithIssues = 0;

// Function to recursively find JS files (excluding vendor directories)
function findJSFiles($dir) {
    $files = [];
    $excludeDirs = ['vendor', 'node_modules', '.git', 'rotc-inventory/node_modules'];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        $path = $file->getPathname();
        
        // Skip vendor directories
        $skip = false;
        foreach ($excludeDirs as $excludeDir) {
            if (strpos($path, $excludeDir) !== false) {
                $skip = true;
                break;
            }
        }
        
        if (!$skip && $file->isFile() && in_array(strtolower($file->getExtension()), ['js', 'jsx'])) {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

// Function to analyze JavaScript file
function analyzeJSFile($filePath) {
    global $issues, $filesWithIssues;
    
    $content = file_get_contents($filePath);
    $fileIssues = [];
    
    // Check for AJAX calls
    $ajaxPatterns = [
        '/\$\.ajax\s*\(/' => 'jQuery AJAX call',
        '/\$\.post\s*\(/' => 'jQuery POST call',
        '/\$\.get\s*\(/' => 'jQuery GET call',
        '/fetch\s*\(/' => 'Fetch API call',
        '/XMLHttpRequest/' => 'XMLHttpRequest usage',
        '/axios\.[get|post|put|delete]/' => 'Axios HTTP call'
    ];
    
    foreach ($ajaxPatterns as $pattern => $description) {
        if (preg_match($pattern, $content)) {
            $fileIssues[] = "Found $description";
        }
    }
    
    // Check for potential database table references
    $tablePatterns = [
        '/["\']cadet_profile["\']/' => 'Using singular "cadet_profile" (should be "cadet_profiles")',
        '/["\']user["\'][^s]/' => 'Using singular "user" table reference',
        '/["\']missing_id_request["\']/' => 'Using singular "missing_id_request" (should be "missing_id_requests")',
        '/birthdate/' => 'Using "birthdate" (should be "birth_date")',
        '/facebook_profile/' => 'References to facebook_profile column'
    ];
    
    foreach ($tablePatterns as $pattern => $description) {
        if (preg_match($pattern, $content)) {
            $fileIssues[] = $description;
        }
    }
    
    // Check for form data that might not match database
    $formPatterns = [
        '/name=["\']father["\']/' => 'Form field "father" (should be "father_name")',
        '/name=["\']mother["\']/' => 'Form field "mother" (should be "mother_name")',
        '/name=["\']guardian["\']/' => 'Form field "guardian" (should be "guardian_name")',
        '/name=["\']profile_photo["\']/' => 'Form field "profile_photo" (should be "photo_path")',
        '/name=["\']student_number["\']/' => 'Form field "student_number" (should be "student_id")'
    ];
    
    foreach ($formPatterns as $pattern => $description) {
        if (preg_match($pattern, $content)) {
            $fileIssues[] = $description;
        }
    }
    
    // Check for API endpoints
    if (preg_match_all('/["\'][^"\']*\.php["\']/', $content, $matches)) {
        foreach ($matches[0] as $endpoint) {
            $fileIssues[] = "API endpoint: $endpoint";
        }
    }
    
    if (!empty($fileIssues)) {
        $issues[$filePath] = $fileIssues;
        $filesWithIssues++;
    }
    
    return $fileIssues;
}

// Find and analyze all JS files
$jsFiles = findJSFiles('.');
$totalFiles = count($jsFiles);

echo "Found $totalFiles JavaScript files to analyze (excluding vendor directories)...\n\n";

foreach ($jsFiles as $file) {
    echo "Checking: $file\n";
    $fileIssues = analyzeJSFile($file);
    
    if (!empty($fileIssues)) {
        echo "  Issues found:\n";
        foreach ($fileIssues as $issue) {
            echo "  • $issue\n";
        }
    } else {
        echo "  ✅ No issues found\n";
    }
    echo "\n";
}

// Summary
echo "\n=== JAVASCRIPT AUDIT SUMMARY ===\n";
echo "📊 Total files analyzed: $totalFiles\n";
echo "⚠️  Files with issues: $filesWithIssues\n";
echo "✅ Clean files: " . ($totalFiles - $filesWithIssues) . "\n\n";

if (!empty($issues)) {
    echo "=== DETAILED ISSUES BY FILE ===\n";
    foreach ($issues as $file => $fileIssues) {
        echo "📁 $file:\n";
        foreach ($fileIssues as $issue) {
            echo "  • $issue\n";
        }
        echo "\n";
    }
    
    echo "=== RECOMMENDED ACTIONS ===\n";
    echo "1. Update AJAX endpoints to match current database structure\n";
    echo "2. Fix table name references from singular to plural forms\n";
    echo "3. Update column references to match actual database schema\n";
    echo "4. Ensure form field names match database columns\n";
    echo "5. Test all AJAX functionality after fixes\n";
} else {
    echo "🎉 No JavaScript database issues found!\n";
}

echo "\n=== AUDIT COMPLETE ===\n";

?>