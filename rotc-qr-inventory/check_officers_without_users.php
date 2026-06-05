<?php
require_once 'includes/db.php';

echo "Officers without corresponding users:\n";
$stmt = $pdo->query('SELECT o.*, u.username FROM officers o LEFT JOIN users u ON o.user_id = u.id WHERE u.username IS NULL');
$officers_without_users = $stmt->fetchAll();

if (empty($officers_without_users)) {
    echo "All officers have corresponding users.\n";
} else {
    foreach ($officers_without_users as $officer) {
        echo "Officer ID: " . $officer['id'] . ", Rank Position: " . $officer['rank_position'] . ", User ID: " . ($officer['user_id'] ?? 'NULL') . "\n";
    }
}

echo "\nAll officers with their usernames:\n";
$stmt = $pdo->query('SELECT o.id, o.rank_position, u.username FROM officers o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.id');
while($row = $stmt->fetch()) {
    echo "Officer ID: " . $row['id'] . ", Username: " . ($row['username'] ?? 'NULL') . ", Rank Position: " . $row['rank_position'] . "\n";
}
?>