<?php
require_once 'includes/db.php';

echo "Users table structure:\n";
$stmt = $pdo->query('DESCRIBE users');
while($row = $stmt->fetch()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\nSample users data:\n";
$stmt = $pdo->query('SELECT * FROM users LIMIT 3');
while($row = $stmt->fetch()) {
    print_r($row);
    echo "\n";
}

echo "\nJoined officers and users data:\n";
$stmt = $pdo->query('SELECT o.*, u.username, u.email FROM officers o LEFT JOIN users u ON o.user_id = u.id LIMIT 3');
while($row = $stmt->fetch()) {
    echo "Officer ID: " . $row['id'] . ", Username: " . ($row['username'] ?? 'NULL') . ", Rank Position: " . $row['rank_position'] . "\n";
}
?>