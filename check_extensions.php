<?php
echo "PHP Extensions Check:\n";
echo "======================\n\n";

// Check if zip extension is loaded
if (extension_loaded('zip')) {
    echo "✓ ZIP extension is loaded\n";
} else {
    echo "✗ ZIP extension is NOT loaded\n";
    echo "\nTo enable ZIP extension in XAMPP:\n";
    echo "1. Open C:\\xampp\\php\\php.ini\n";
    echo "2. Find the line: ;extension=zip\n";
    echo "3. Remove the semicolon to uncomment it: extension=zip\n";
    echo "4. Restart Apache in XAMPP Control Panel\n\n";
}

// Check other relevant extensions
$extensions = ['xml', 'dom', 'libxml', 'simplexml'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ $ext extension is loaded\n";
    } else {
        echo "✗ $ext extension is NOT loaded\n";
    }
}

echo "\nAll loaded extensions:\n";
print_r(get_loaded_extensions());
?>