<?php
// --- FORCE ERROR DISPLAY --- //
// This is the final diagnostic step to reveal all hidden errors.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the Composer autoloader to load the QR code library
require_once __DIR__ . '/vendor/autoload.php';

require_once 'includes/session.php';
require_once 'includes/db.php';

// Define a base path constant for robust file operations
define('ROOT_PATH', dirname(__FILE__));

if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Stop the silent redirect and show a clear error.
    die("<h2>Error: No Cadet ID Provided</h2><p>This page cannot be accessed directly. Please go to the <strong>User Management</strong> page, select a cadet, and click 'Generate ID Card' to view this page correctly.</p>");
}

$profile_id = $_GET['id'];

$sql = "SELECT * FROM cadet_profiles WHERE id = ?";
$stmt = $link->prepare($sql);
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();

if (!$profile) {
    die('Profile not found.');
}

// --- Self-Healing QR Code ---
$qr_code_rel_path = $profile['qr_code_path'] ?? '';
$qr_code_abs_path = ROOT_PATH . '/' . ltrim($qr_code_rel_path, '/');


// Define a placeholder for missing images
$placeholder_img = 'data:image/gif;base64,R0lGODlhAQABAIAAAMLCwgAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==';

// --- Other Image Display Logic ---
$cache_buster = '?t=' . time();

// Photo (not currently used on card, but logic is here)
$photo_path = $profile['photo_path'] ?? '';
$photo_abs_path = ROOT_PATH . '/' . ltrim($photo_path, '/');
$display_photo_src = (!empty($photo_path) && file_exists($photo_abs_path)) ? htmlspecialchars($photo_path) . $cache_buster : $placeholder_img;

// Signature
$signature_path = $profile['signature_path'] ?? '';
$signature_abs_path = ROOT_PATH . '/' . ltrim($signature_path, '/');
$display_signature_src = (!empty($signature_path) && file_exists($signature_abs_path)) ? htmlspecialchars($signature_path) . $cache_buster : $placeholder_img;

$stmt->close();

// --- Process Name and Platoon for Customization ---
$full_name = $profile['full_name'] ?? 'N/A';
$name_parts = explode(' ', $full_name);
$last_name = end($name_parts);
// Basic check for suffixes, can be improved
if (count($name_parts) > 1 && in_array(strtoupper($last_name), ['JR', 'SR', 'I', 'II', 'III', 'IV'])) {
    $last_name = $name_parts[count($name_parts) - 2];
}
$display_name = strtoupper(htmlspecialchars($last_name));

$platoon = $profile['platoon'] ?? 'N/A';
$platoon_color = '#003300'; // Default Green
switch (strtoupper($platoon)) {
    case 'ALPHA':
        $platoon_color = '#B71C1C'; // Dark Red
        break;
    case 'BRAVO':
        $platoon_color = '#1A237E'; // Dark Blue
        break;
    case 'CHARLIE':
        $platoon_color = '#28a745'; // Green
        break;
}

// --- Construct QR Code URL for Local Network Scanning ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

// Use the server's local IP address instead of 'localhost' to make it scannable on the same Wi-Fi network.
// This is a more robust method for local network environments.
$host = gethostbyname(gethostname());

$script_path = dirname($_SERVER['SCRIPT_NAME']);
// Handle cases where the script is in the root directory
$base_path = ($script_path == '/' || $script_path == '\\') ? '' : $script_path;
$profile_url = $protocol . $host . $base_path . '/view_profile.php?id=' . $profile_id;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* --- User Friendly & Mobile Friendly Design --- */
        body {
            background-color: #f0f2f5; /* A softer, more modern background */
        }

        .page-container {
            padding-top: 2rem;
            padding-bottom: 2rem;
        }

        .action-buttons .btn {
            border-radius: 50px; /* Rounded buttons */
            padding: 12px 30px;
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin: 5px;
        }

        .action-buttons .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        /* --- Mobile Responsiveness --- */
        @media (max-width: 480px) {
            .page-container {
                padding: 1rem;
            }

            /* Scale the ID card down to fit the screen */
            .id-card-container {
                transform: scale(0.95);
                transform-origin: top center;
            }

            /* Stack buttons vertically on small screens */
            .action-buttons {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 10px; /* Space between stacked buttons */
            }

            .action-buttons .btn {
                width: 85%;
            }
        }
    </style>
    <title>Cadet ID Card - <?php echo htmlspecialchars($profile['full_name'] ?? 'N/A'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .id-card-container {
            border: 4px solid <?php echo $platoon_color; ?>; /* Thicker, colored outline */
        }
        .id-header {
            color: <?php echo $platoon_color; ?>;
            border-bottom-color: <?php echo $platoon_color; ?>;
        }
        .id-photo { /* This class is used for the QR code image */
            border-color: <?php echo $platoon_color; ?>;
        }
        .name-field {
            font-size: 14pt;
            font-weight: 700;
            line-height: 1.2;
        }
        @media screen {
            body {
                font-family: 'Roboto', sans-serif;
                background-color: #e0e0e0;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px 0;
            }
        }
        .id-card-container {
            width: 3.375in;
            height: 2.125in;
            background: #fff;
            border: 4px solid <?php echo $platoon_color; ?>;
            border-radius: 10px;
            padding: 15px;
            display: grid;
            grid-template-columns: 1.2in 1.8in; /* Adjust columns for stability */
            grid-template-rows: auto 1fr auto;
            gap: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            box-sizing: border-box;
            position: relative; /* Crucial for containing children */
            overflow: hidden;   /* Crucial for containing children */
        }
        .id-header { grid-column: 1 / -1; text-align: center; color: <?php echo $platoon_color; ?>; font-family: 'Orbitron', sans-serif; font-weight: 700; font-size: 10pt; border-bottom: 2px solid <?php echo $platoon_color; ?>; padding-bottom: 5px; letter-spacing: 1px; }
        .id-photo-container { grid-row: 2; grid-column: 1; display: flex; flex-direction: column; align-items: center; justify-content: space-around; }
        .id-photo { width: 1in; height: 1in; border: 2px solid <?php echo $platoon_color; ?>; object-fit: cover; }
        .id-details { grid-row: 2; grid-column: 2; font-size: 8pt; color: #333; padding-left: 5px; }
        .id-details p { margin: 0 0 5px 0; }
        .id-details strong { font-weight: 500; color: #003300; }
                .id-footer {
            grid-column: 1 / -1;
        }
        .id-signature {
            width: 1.5in;
            margin: 0 auto; /* Center the signature block */
            text-align: center;
        }
        .id-signature img {
            height: 25px;
            margin-bottom: 2px;
        }
        .id-signature p { margin: 0; font-size: 6pt; text-align: center; border-top: 1px solid #333; }
        .button-container { margin-top: 20px; display: flex; gap: 10px; }
        .action-button { padding: 10px 20px; font-family: 'Orbitron', sans-serif; background-color: #004d00; color: #fff; border: none; cursor: pointer; font-size: 16px; border-radius: 5px; text-decoration: none; text-align: center; }

        @media print {
            body, html { width: 3.375in; height: 2.125in; margin: 0 !important; padding: 0 !important; background: #fff !important; }
            .button-container, body > *:not(.id-card-container) { display: none !important; }
            .id-card-container { position: absolute; top: 0; left: 0; box-shadow: none !important; margin: 0 !important; }
            @page { size: 3.375in 2.125in; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="id-card-container" id="idCard">
        <div class="id-header">RESERVE OFFICER TRAINING CORPS</div>
        <div class="id-photo-container">
            <div id="qrcode" class="id-photo"></div>
        </div>
        <div class="id-details">
            <p><strong>NAME:</strong><br><span class="name-field"><?php echo $display_name; ?></span></p>
            
            <p><strong>PLATOON:</strong><br><?php echo strtoupper(htmlspecialchars($profile['platoon'] ?? 'N/A')); ?></p>
        </div>
        <div class="id-footer">
            <div class="id-signature">
                <img src="<?php echo $display_signature_src; ?>" alt="Signature">
                <p>CADET SIGNATURE</p>
            </div>
        </div>
    </div>

    <div class="text-center mt-3 action-buttons">
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print ID Card</button>
        <button onclick="downloadPDF()" class="btn btn-success"><i class="fas fa-download"></i> Download as PDF</button>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<!-- Include the JavaScript QR Code Library -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

<!-- Generate the QR Code -->
<script type="text/javascript">
    // The div where the QR code will be rendered
    var qrCodeContainer = document.getElementById('qrcode');

    // The data to encode (the full URL to the cadet's profile)
    var dataToEncode = "<?php echo htmlspecialchars($profile_url, ENT_QUOTES, 'UTF-8'); ?>";

    // Create the QR code
    new QRCode(qrCodeContainer, {
        text: dataToEncode,
        width: 97,
        height: 97,
        correctLevel: QRCode.CorrectLevel.H
    });
</script>

    <script>
        function downloadPDF() {
            const element = document.getElementById('idCard');
            const opt = {
                margin: 0,
                filename: 'cadet-id-card-<?php echo htmlspecialchars(str_replace(' ', '_', $profile['full_name'] ?? 'cadet')); ?>.pdf',
                image: { type: 'jpeg', quality: 1.0 },
                html2canvas: { scale: 4, useCORS: true, logging: false },
                jsPDF: { unit: 'in', format: [3.375, 2.125], orientation: 'landscape' }
            };
            html2pdf().from(element).set(opt).save();
        }
    </script>
</body>
</html>
