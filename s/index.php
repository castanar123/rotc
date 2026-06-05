<?php
// Simple short-link redirector
// Usage: /generate%20qr/s/<code>  or  /generate%20qr/s/?c=<code>

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/SecurityLogger.php';

header('X-ShortLinks: on');

$securityLogger = new SecurityLogger();
$ADMIN_KEY = getenv('SHORTLINK_ADMIN_KEY') ?: 'changeme';

// Ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS short_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(64) NOT NULL UNIQUE,
        target_url TEXT NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        clicks INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Short-links storage error.';
    exit;
}

// Debug/list links (temporary admin helper)
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    try {
        $rows = $pdo->query('SELECT code, target_url, active, clicks, created_at FROM short_links ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $rows = []; }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Short Links Debug</title><style>body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:16px}code{background:#f3f3f3;padding:2px 4px;border-radius:4px}</style></head><body>';
    echo '<h1>Short Links</h1>';
    echo '<p><strong>Warning:</strong> This debug view is for testing only. Protect with a key or remove in production.</p>';
    echo '<table border="1" cellpadding="6" cellspacing="0">';
    echo '<tr><th>code</th><th>target_url</th><th>active</th><th>clicks</th><th>created_at</th></tr>';
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td><code>' . htmlspecialchars($r['code']) . '</code></td>';
        echo '<td>' . htmlspecialchars($r['target_url']) . '</td>';
        echo '<td>' . ((int)$r['active'] === 1 ? '1' : '0') . '</td>';
        echo '<td>' . (int)$r['clicks'] . '</td>';
        echo '<td>' . htmlspecialchars($r['created_at']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '<h2>Quick Update</h2>';
    echo '<form method="post" action="?update=1">'
        . 'Code: <input name="code" required> '
        . 'Target URL: <input name="to" size="60" required placeholder="/generate%20qr/register_help_prototype.php"> '
        . 'Key: <input name="key" type="password" value="changeme"> '
        . '<button type="submit">Update</button>'
        . '</form>';
    echo '<p>Or GET: <code>?update=1&code=reg&to=/generate%20qr/register_help_prototype.php&key=changeme</code></p>';
    echo '</body></html>';
    exit;
}

// Admin update mapping (temporary helper)
if ((isset($_GET['update']) && $_GET['update'] === '1') || (isset($_POST['update']) && $_POST['update'] === '1')) {
    $code = trim((string)($_POST['code'] ?? $_GET['code'] ?? ''));
    $to = trim((string)($_POST['to'] ?? $_GET['to'] ?? ''));
    $key = (string)($_POST['key'] ?? $_GET['key'] ?? '');
    if ($key !== $ADMIN_KEY) { http_response_code(403); echo 'Forbidden (bad key)'; exit; }
    if ($code === '' || $to === '') { http_response_code(400); echo 'Missing code/to'; exit; }
    try {
        $stmt = $pdo->prepare('SELECT id FROM short_links WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $upd = $pdo->prepare('UPDATE short_links SET target_url = ?, active = 1 WHERE id = ?');
            $upd->execute([$to, (int)$row['id']]);
        } else {
            $ins = $pdo->prepare('INSERT INTO short_links (code, target_url, active) VALUES (?, ?, 1)');
            $ins->execute([$code, $to]);
        }
        echo 'Updated. <a href="?debug=1">Back</a>';
    } catch (Throwable $e) {
        http_response_code(500); echo 'Update failed.';
    }
    exit;
}

// Seed common codes if missing
function ensure_code($pdo, $code, $url) {
    $chk = $pdo->prepare('SELECT id FROM short_links WHERE code = ? LIMIT 1');
    $chk->execute([$code]);
    if (!$chk->fetch()) {
        $ins = $pdo->prepare('INSERT INTO short_links (code, target_url) VALUES (?, ?)');
        $ins->execute([$code, $url]);
    }
}
ensure_code($pdo, 'reg', '/generate%20qr/register.php');
ensure_code($pdo, 'regp', '/generate%20qr/register_help_prototype.php');

// Resolve code
$code = '';
if (isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] !== '') {
    $code = trim($_SERVER['PATH_INFO'], '/');
} elseif (isset($_GET['c'])) {
    $code = trim((string)$_GET['c']);
}

if ($code === '') {
    http_response_code(400);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Short Link</title></head><body>';
    echo '<p>Usage: <code>/generate%20qr/s/&lt;code&gt;</code> or <code>/generate%20qr/s/?c=&lt;code&gt;</code></p>';
    echo '<p>Examples: <a href="./reg">/s/reg</a>, <a href="./regp">/s/regp</a></p>';
    echo '</body></html>';
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, target_url, active FROM short_links WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['active'] !== 1) {
        http_response_code(404);
        echo 'Short link not found.';
        exit;
    }

    $target = (string)$row['target_url'];
    // Build absolute URL if relative
    if (strpos($target, 'http://') !== 0 && strpos($target, 'https://') !== 0) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if ($target !== '' && $target[0] === '/') {
            $target = $scheme . $host . $target;
        } else {
            // relative to /generate%20qr/
            $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
            $target = $scheme . $host . $basePath . ltrim($target, '/');
        }
    }

    // Count click (best-effort)
    try {
        $upd = $pdo->prepare('UPDATE short_links SET clicks = clicks + 1 WHERE id = ?');
        $upd->execute([(int)$row['id']]);
    } catch (Throwable $e) { /* ignore */ }

    // Log
    try {
        $securityLogger->logSecurityEvent(null, 'SHORTLINK_REDIRECT', 'Short link used', [ 'code'=>$code, 'target'=>$target ], 'low');
    } catch (Throwable $e) { /* ignore */ }

    header('Location: ' . $target, true, 302);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Server error.';
    exit;
}
