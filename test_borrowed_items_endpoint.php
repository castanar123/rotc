<?php
// Test the borrowed_items.php endpoint directly with the same parameters as frontend

$url = "http://localhost/generate%20qr/rotc-qr-inventory/api/borrowed_items.php?action=get_borrowed";

echo "=== TESTING BORROWED ITEMS ENDPOINT ===\n";
echo "URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response Length: " . strlen($response) . "\n";
echo "Response (first 500 chars):\n";
echo substr($response, 0, 500) . "\n\n";

// Test JSON parsing
echo "=== JSON PARSE TEST ===\n";
$decoded = json_decode($response, true);
if ($decoded === null) {
    echo "JSON Parse Error: " . json_last_error_msg() . "\n";
    echo "Raw response:\n";
    echo $response . "\n";
} else {
    echo "JSON Parse Success\n";
    echo "Decoded: " . print_r($decoded, true) . "\n";
}
?>
