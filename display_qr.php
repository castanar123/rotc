<?php
require_once 'includes/session.php';

// This page should be secure
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . rotc_relative_url('login.php'));
    exit();
}

$page_title = 'Print Scanner QR Code';
include 'includes/header.php';

$short_url = 'https://tinyurl.com/rotculb';
$qr_code_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=" . urlencode($short_url);
?>

<style>
    .qr-container {
        border: 2px solid #4e73df;
        padding: 20px;
        border-radius: 10px;
        background-color: #f8f9fc;
        max-width: 500px;
        margin: auto;
    }
    .print-button {
        margin-top: 20px;
    }
    @media print {
        body * {
            visibility: hidden;
        }
        #wrapper, #content-wrapper {
            background-color: white !important;
        }
        .printable-area, .printable-area * {
            visibility: visible;
        }
        .printable-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .print-button, .navbar, .sidebar, .footer, .sticky-footer, #logoutModal, .modal-backdrop {
            display: none !important;
        }
    }
</style>

<div class="container-fluid">
    <div class="printable-area">
        <div class="qr-container text-center">
            <h1 class="h3 mb-4 text-gray-800">ROTC Attendance Scanner</h1>
            <p class="lead">Scan this QR code with your phone to open the attendance scanner.</p>
            <img src="<?php echo $qr_code_api_url; ?>" alt="Scanner QR Code" class="img-fluid my-3" style="border: 5px solid #e3e6f0; padding: 5px; border-radius: 5px;">
            <h4 class="mt-3 font-weight-bold"><?php echo $short_url; ?></h4>
            <p class="text-muted">This is a permanent link. You can print this page and post it for easy access.</p>
        </div>
    </div>

    <div class="text-center">
        <button onclick="window.print();" class="btn btn-primary print-button">
            <i class="fas fa-print"></i> Print QR Code
        </button>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
