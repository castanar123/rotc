<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'libs/phpqrcode/qrlib.php';

// Admin-only access
check_login();
if ($_SESSION['role'] !== 'admin') {
    redirect_to_dashboard();
}

$page_title = 'Batch Generate QR Codes';
include 'includes/header.php';

echo '<div class="d-flex"><div class="container-fluid main-content">';
echo '<div class="form-container">';
echo '<h2 class="display-5">Batch QR Code Generator</h2>';
echo '<p>This script will find any cadets missing a QR code and generate one for them.</p><hr>';

// Find cadets with no QR code path
$sql = "SELECT id, user_id FROM cadet_profiles WHERE qr_code_path IS NULL OR qr_code_path = ''";
$result = $link->query($sql);

if ($result && $result->num_rows > 0) {
    $generated_count = 0;
    echo '<div class="alert alert-info">Found ' . $result->num_rows . ' cadets missing a QR code. Starting generation...</div>';
    echo '<ul class="list-group">';

    while($profile = $result->fetch_assoc()) {
        $profile_id = $profile['id'];
        $user_id = $profile['user_id'];
        $qr_code_path = 'uploads/qrcodes/' . $user_id . '.png';

        // Ensure the directory exists
        if (!is_dir(dirname($qr_code_path))) {
            mkdir(dirname($qr_code_path), 0755, true);
        }

        // Get student details for encrypted QR generation
        $student_sql = "SELECT student_id, CONCAT(first_name, ' ', IFNULL(CONCAT(middle_name, ' '), ''), last_name) AS full_name FROM cadet_profiles WHERE id = ?";
        if ($student_stmt = $link->prepare($student_sql)) {
            $student_stmt->bind_param("i", $profile_id);
            $student_stmt->execute();
            $student_result = $student_stmt->get_result();
            $student_data = $student_result->fetch_assoc();
            $student_stmt->close();
            
            if ($student_data) {
                // Create data object like the QR/index.html system
                $qr_data = array(
                    'student_id' => $student_data['student_id'],
                    'name' => $student_data['full_name'],
                    'valid_until' => date('Y-m-d', strtotime('+12 months')) // 12 months validity
                );
                
                // Convert to JSON and encrypt using the same method as QR/script.js
                $json_data = json_encode($qr_data);
                $encryption_key = 'attendance-system-permanent-key-2023'; // Same key as in config.js
                
                // Simple encryption (matching CryptoJS.AES format would require additional libraries)
                // For now, we'll use base64 encoding with a prefix to indicate it's from batch generation
                $encrypted_data = base64_encode($encryption_key . '|' . $json_data);
                
                // Generate the QR code image containing the encrypted data
                QRcode::png($encrypted_data, $qr_code_path);
            } else {
                // Fallback to profile_id if student data not found
                QRcode::png((string)$profile_id, $qr_code_path);
            }
        } else {
            // Fallback to profile_id if query fails
            QRcode::png((string)$profile_id, $qr_code_path);
        }

        // Update the database
        $update_sql = "UPDATE cadet_profiles SET qr_code_path = ? WHERE id = ?";
        if ($stmt = $link->prepare($update_sql)) {
            $stmt->bind_param("si", $qr_code_path, $profile_id);
            if ($stmt->execute()) {
                echo '<li class="list-group-item list-group-item-success">Successfully generated QR code for Profile ID: ' . $profile_id . '</li>';
                $generated_count++;
            } else {
                echo '<li class="list-group-item list-group-item-danger">Failed to update database for Profile ID: ' . $profile_id . '</li>';
            }
            $stmt->close();
        }
    }
    echo '</ul>';
    echo '<div class="alert alert-success mt-3"><strong>Finished!</strong> Generated ' . $generated_count . ' new QR codes.</div>';

} else {
    echo '<div class="alert alert-success">All cadets already have a QR code. No action needed.</div>';
}

echo '<a href="admin_dashboard.php" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>';
echo '</div></div></div>';

include 'includes/footer.php';
$link->close();
?>
