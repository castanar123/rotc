<?php
require_once 'vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextRun;

// Function to examine a Word document template
function examineTemplate($filePath, $templateName) {
    echo "\n=== Examining $templateName ===\n";
    
    try {
        // Load the document
        $phpWord = IOFactory::load($filePath);
        
        // Get all sections
        $sections = $phpWord->getSections();
        
        foreach ($sections as $sectionIndex => $section) {
            echo "\nSection $sectionIndex:\n";
            
            // Get all elements in the section
            $elements = $section->getElements();
            
            foreach ($elements as $elementIndex => $element) {
                $elementType = get_class($element);
                echo "  Element $elementIndex: $elementType\n";
                
                // If it's a table, examine its structure
                if ($element instanceof Table) {
                    examineTable($element, $elementIndex);
                }
                
                // If it's a text run, examine its content
                if ($element instanceof TextRun) {
                    examineTextRun($element, $elementIndex);
                }
            }
        }
        
    } catch (Exception $e) {
        echo "Error examining $templateName: " . $e->getMessage() . "\n";
    }
}

// Function to examine table structure
function examineTable($table, $tableIndex) {
    echo "    Table $tableIndex structure:\n";
    
    $rows = $table->getRows();
    echo "      Number of rows: " . count($rows) . "\n";
    
    foreach ($rows as $rowIndex => $row) {
        $cells = $row->getCells();
        echo "      Row $rowIndex: " . count($cells) . " cells\n";
        
        foreach ($cells as $cellIndex => $cell) {
            $cellElements = $cell->getElements();
            echo "        Cell $cellIndex: ";
            
            foreach ($cellElements as $cellElement) {
                if (method_exists($cellElement, 'getText')) {
                    echo $cellElement->getText() . " ";
                } elseif (method_exists($cellElement, 'getElements')) {
                    // Handle nested elements
                    $nestedElements = $cellElement->getElements();
                    foreach ($nestedElements as $nested) {
                        if (method_exists($nested, 'getText')) {
                            echo $nested->getText() . " ";
                        }
                    }
                }
            }
            echo "\n";
        }
    }
}

// Function to examine text run content
function examineTextRun($textRun, $textIndex) {
    echo "    TextRun $textIndex: ";
    
    $elements = $textRun->getElements();
    foreach ($elements as $element) {
        if (method_exists($element, 'getText')) {
            echo $element->getText() . " ";
        }
    }
    echo "\n";
}

// Examine all templates
$templates = [
    'Templates/A-SUMMARY-2ND-SEM-S.Y-24-25-LSPULB.docx' => 'Summary Template',
    'Templates/B-ROSTER-OF-ENROLLED-CADETS-2ND-SEM-S.Y-24-25-LSPU-LB.docx' => 'Roster Template',
    'Templates/C-LIST-OF-BENEFICIARIES-1ST-SEM-LSPULB.docx' => 'Beneficiaries Template',
    'Templates/D-CADETS-PROFILE-2ND-SEM-LSPULB.docx' => 'Cadet Profile Template'
];

foreach ($templates as $filePath => $templateName) {
    if (file_exists($filePath)) {
        examineTemplate($filePath, $templateName);
    } else {
        echo "\nTemplate not found: $filePath\n";
    }
}

echo "\n=== Template Examination Complete ===\n";
?>