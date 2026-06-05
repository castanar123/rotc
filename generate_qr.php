<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'libs/phpqrcode/qrlib.php';

// Admin-only access
check_login();
if ($_SESSION['role'] !== 'admin') {
    redirect_to_dashboard();
}

$page_title = 'Generate QR Code';
include 'includes/header.php';

// Get profile ID from URL
$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($profile_id <= 0) {
    echo '<div class="alert alert-danger">Invalid profile ID.</div>';
    echo '<a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>';
    include 'includes/footer.php';
    exit;
}

// Get cadet profile information
$sql = "SELECT cp.*, u.username FROM cadet_profiles cp 
        JOIN users u ON cp.user_id = u.id 
        WHERE cp.id = ?";
$stmt = $link->prepare($sql);
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$stmt->close();

if (!$profile) {
    echo '<div class="alert alert-danger">Profile not found.</div>';
    echo '<a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>';
    include 'includes/footer.php';
    exit;
}

echo '<div class="d-flex"><div class="container-fluid main-content">';
echo '<div class="form-container">';
echo '<h2 class="display-5">Generate QR Code</h2>';
echo '<p>Generate QR code for: <strong>' . htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) . '</strong></p><hr>';

// Generate QR code
$user_id = $profile['user_id'];
$qr_code_path = 'uploads/qrcodes/' . $user_id . '.png';

// Ensure the directory exists
if (!is_dir(dirname($qr_code_path))) {
    mkdir(dirname($qr_code_path), 0755, true);
}

// Create data object for QR generation
$qr_data = array(
    'student_id' => $profile['student_id'],
    'name' => $profile['first_name'] . ' ' . ($profile['middle_name'] ? $profile['middle_name'] . ' ' : '') . $profile['last_name'],
    'valid_until' => date('Y-m-d', strtotime('+12 months')) // 12 months validity
);

// Convert to JSON and encrypt
$json_data = json_encode($qr_data);
$encryption_key = 'attendance-system-permanent-key-2023';
$encrypted_data = base64_encode($encryption_key . '|' . $json_data);

// Generate the QR code image
try {
    QRcode::png($encrypted_data, $qr_code_path);
    
    // Update the database
    $update_sql = "UPDATE cadet_profiles SET qr_code_path = ? WHERE id = ?";
    $update_stmt = $link->prepare($update_sql);
    $update_stmt->bind_param("si", $qr_code_path, $profile_id);
    
    if ($update_stmt->execute()) {
        echo '<div class="alert alert-success">QR code generated successfully!</div>';
        echo '<div class="text-center">';
        echo '<img src="' . $qr_code_path . '" alt="QR Code" class="img-fluid" style="max-width: 300px;">';
        echo '<p class="mt-2">QR Code saved to: ' . $qr_code_path . '</p>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-danger">QR code generated but failed to update database.</div>';
    }
    $update_stmt->close();
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Failed to generate QR code: ' . $e->getMessage() . '</div>';
}

echo '<a href="view_profile.php?id=' . $profile_id . '" class="btn btn-primary mt-3">View Profile</a> ';
echo '<a href="admin_dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>';
echo '</div></div></div>';

include 'includes/footer.php';
$link->close();
?>