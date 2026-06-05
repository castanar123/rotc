<?php
/**
 * ROTC ID Card Generator
 * Generates ROTC-styled ID cards with QR codes for borrower identification
 */

require_once 'includes/db.php';
require_once 'libs/phpqrcode/qrlib.php';

/**
 * Generate ROTC-styled ID card with QR code
 * @param string $temp_id The temporary borrower ID
 * @return array Result with success status and file paths
 */
function generateROTCIDCard($temp_id) {
    try {
        // Create directories if they don't exist
        $qr_dir = 'qr_codes/borrower_ids/';
        $card_dir = 'id_cards/borrower_cards/';
        
        if (!file_exists($qr_dir)) {
            mkdir($qr_dir, 0755, true);
        }
        if (!file_exists($card_dir)) {
            mkdir($card_dir, 0755, true);
        }
        
        // Generate QR code first
        $qr_filename = $temp_id . '_qr.png';
        $qr_path = $qr_dir . $qr_filename;
        
        // Generate QR code with high error correction
        QRcode::png($temp_id, $qr_path, QR_ECLEVEL_H, 8, 2);
        
        // Create ID card image
        $card_width = 600;
        $card_height = 380;
        
        // Create front side
        $front_card = imagecreatetruecolor($card_width, $card_height);
        
        // ROTC Colors
        $rotc_green = imagecolorallocate($front_card, 34, 139, 34);
        $rotc_gold = imagecolorallocate($front_card, 255, 215, 0);
        $white = imagecolorallocate($front_card, 255, 255, 255);
        $black = imagecolorallocate($front_card, 0, 0, 0);
        $dark_green = imagecolorallocate($front_card, 0, 100, 0);
        
        // Fill background with gradient effect
        for ($y = 0; $y < $card_height; $y++) {
            $ratio = $y / $card_height;
            $r = (int)(255 * (1 - $ratio) + 34 * $ratio);
            $g = (int)(255 * (1 - $ratio) + 139 * $ratio);
            $b = (int)(255 * (1 - $ratio) + 34 * $ratio);
            $gradient_color = imagecolorallocate($front_card, $r, $g, $b);
            imageline($front_card, 0, $y, $card_width, $y, $gradient_color);
        }
        
        // Add border
        imagerectangle($front_card, 0, 0, $card_width-1, $card_height-1, $dark_green);
        imagerectangle($front_card, 1, 1, $card_width-2, $card_height-2, $rotc_gold);
        imagerectangle($front_card, 2, 2, $card_width-3, $card_height-3, $dark_green);
        
        // Load and add QR code
        if (file_exists($qr_path)) {
            $qr_image = imagecreatefrompng($qr_path);
            $qr_size = 120;
            $qr_x = $card_width - $qr_size - 30;
            $qr_y = 80;
            
            // Create white background for QR code
            imagefilledrectangle($front_card, $qr_x-5, $qr_y-5, $qr_x+$qr_size+5, $qr_y+$qr_size+5, $white);
            imagerectangle($front_card, $qr_x-5, $qr_y-5, $qr_x+$qr_size+5, $qr_y+$qr_size+5, $black);
            
            imagecopyresampled($front_card, $qr_image, $qr_x, $qr_y, 0, 0, $qr_size, $qr_size, imagesx($qr_image), imagesy($qr_image));
            imagedestroy($qr_image);
        }
        
        // Add text content
        $font_path = realpath('fonts/arial.ttf'); // You may need to add this font file
        
        // Header text
        imagestring($front_card, 5, 30, 20, 'LSPU-LB ROTC UNIT', $rotc_gold);
        imagestring($front_card, 4, 30, 45, 'PROPERTY ID CARD', $white);
        
        // Borrower ID label
        imagestring($front_card, 4, 30, 90, 'BORROWER ID:', $white);
        imagestring($front_card, 5, 30, 115, $temp_id, $rotc_gold);
        
        // Instructions
        imagestring($front_card, 3, 30, 160, 'This card serves for:', $white);
        imagestring($front_card, 3, 30, 180, '• Rifle borrowing', $white);
        imagestring($front_card, 3, 30, 200, '• Equipment return', $white);
        imagestring($front_card, 3, 30, 220, '• Identity verification', $white);
        
        imagestring($front_card, 2, 30, 260, 'KEEP THIS ID FOR RETURN', $rotc_gold);
        
        // Footer
        imagestring($front_card, 2, 30, 340, 'Laguna State Polytechnic University', $white);
        imagestring($front_card, 2, 30, 355, 'Los Baños Campus - ROTC Unit', $white);
        
        // Save front card
        $front_filename = $temp_id . '_front.png';
        $front_path = $card_dir . $front_filename;
        imagepng($front_card, $front_path);
        imagedestroy($front_card);
        
        // Create back side
        $back_card = imagecreatetruecolor($card_width, $card_height);
        
        // Same colors for back
        $rotc_green = imagecolorallocate($back_card, 34, 139, 34);
        $rotc_gold = imagecolorallocate($back_card, 255, 215, 0);
        $white = imagecolorallocate($back_card, 255, 255, 255);
        $black = imagecolorallocate($back_card, 0, 0, 0);
        $dark_green = imagecolorallocate($back_card, 0, 100, 0);
        
        // Fill background with gradient effect
        for ($y = 0; $y < $card_height; $y++) {
            $ratio = $y / $card_height;
            $r = (int)(34 * (1 - $ratio) + 255 * $ratio);
            $g = (int)(139 * (1 - $ratio) + 255 * $ratio);
            $b = (int)(34 * (1 - $ratio) + 255 * $ratio);
            $gradient_color = imagecolorallocate($back_card, $r, $g, $b);
            imageline($back_card, 0, $y, $card_width, $y, $gradient_color);
        }
        
        // Add border
        imagerectangle($back_card, 0, 0, $card_width-1, $card_height-1, $dark_green);
        imagerectangle($back_card, 1, 1, $card_width-2, $card_height-2, $rotc_gold);
        imagerectangle($back_card, 2, 2, $card_width-3, $card_height-3, $dark_green);
        
        // Back side content - Borrowing Rules
        imagestring($back_card, 4, 30, 20, 'BORROWING RULES & REGULATIONS', $rotc_gold);
        
        $rules = [
            '1. Present this ID card for all transactions',
            '2. Rifles must be returned in same condition',
            '3. Report any damage immediately',
            '4. Maximum borrowing period: 24 hours',
            '5. Late returns subject to penalties',
            '6. Lost ID cards must be reported immediately',
            '7. ID card is non-transferable',
            '8. Follow all safety protocols',
            '9. Inspection required before return',
            '10. Keep this card safe at all times'
        ];
        
        $y_pos = 60;
        foreach ($rules as $rule) {
            imagestring($back_card, 2, 30, $y_pos, $rule, $white);
            $y_pos += 20;
        }
        
        // Important notice
        imagestring($back_card, 3, 30, 290, 'IMPORTANT NOTICE:', $rotc_gold);
        imagestring($back_card, 2, 30, 310, 'This card will serve as for returning and borrowing', $white);
        imagestring($back_card, 2, 30, 325, 'Keep this ID for the return process', $white);
        
        // Footer
        imagestring($back_card, 2, 30, 355, 'For inquiries: ROTC Unit Office', $white);
        
        // Save back card
        $back_filename = $temp_id . '_back.png';
        $back_path = $card_dir . $back_filename;
        imagepng($back_card, $back_path);
        imagedestroy($back_card);
        
        return [
            'success' => true,
            'qr_path' => $qr_path,
            'front_path' => $front_path,
            'back_path' => $back_path,
            'temp_id' => $temp_id
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Generate combined printable ID card (front and back side by side)
 * @param string $temp_id The temporary borrower ID
 * @return array Result with success status and file path
 */
function generatePrintableIDCard($temp_id) {
    try {
        $card_dir = 'id_cards/borrower_cards/';
        $front_path = $card_dir . $temp_id . '_front.png';
        $back_path = $card_dir . $temp_id . '_back.png';
        
        if (!file_exists($front_path) || !file_exists($back_path)) {
            throw new Exception('ID card images not found');
        }
        
        // Load front and back images
        $front_img = imagecreatefrompng($front_path);
        $back_img = imagecreatefrompng($back_path);
        
        $card_width = imagesx($front_img);
        $card_height = imagesy($front_img);
        
        // Create combined image (side by side with margin)
        $margin = 20;
        $combined_width = ($card_width * 2) + ($margin * 3);
        $combined_height = $card_height + ($margin * 2);
        
        $combined = imagecreatetruecolor($combined_width, $combined_height);
        $white = imagecolorallocate($combined, 255, 255, 255);
        imagefill($combined, 0, 0, $white);
        
        // Add front and back images
        imagecopy($combined, $front_img, $margin, $margin, 0, 0, $card_width, $card_height);
        imagecopy($combined, $back_img, $card_width + ($margin * 2), $margin, 0, 0, $card_width, $card_height);
        
        // Add labels
        $black = imagecolorallocate($combined, 0, 0, 0);
        imagestring($combined, 3, $margin + ($card_width / 2) - 20, 5, 'FRONT', $black);
        imagestring($combined, 3, $card_width + ($margin * 2) + ($card_width / 2) - 15, 5, 'BACK', $black);
        
        // Save combined image
        $combined_filename = $temp_id . '_printable.png';
        $combined_path = $card_dir . $combined_filename;
        imagepng($combined, $combined_path);
        
        // Cleanup
        imagedestroy($front_img);
        imagedestroy($back_img);
        imagedestroy($combined);
        
        return [
            'success' => true,
            'printable_path' => $combined_path,
            'temp_id' => $temp_id
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'generate_id_card':
            $temp_id = $_POST['temp_id'] ?? '';
            
            if (empty($temp_id)) {
                echo json_encode(['success' => false, 'message' => 'Temp ID is required']);
                exit;
            }
            
            // Generate ID card
            $card_result = generateROTCIDCard($temp_id);
            
            if ($card_result['success']) {
                // Generate printable version
                $printable_result = generatePrintableIDCard($temp_id);
                
                if ($printable_result['success']) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'ID card generated successfully',
                        'qr_path' => $card_result['qr_path'],
                        'front_path' => $card_result['front_path'],
                        'back_path' => $card_result['back_path'],
                        'printable_path' => $printable_result['printable_path']
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => 'ID card generated, but printable version failed',
                        'qr_path' => $card_result['qr_path'],
                        'front_path' => $card_result['front_path'],
                        'back_path' => $card_result['back_path'],
                        'error' => $printable_result['error']
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to generate ID card',
                    'error' => $card_result['error']
                ]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    exit;
}
?>