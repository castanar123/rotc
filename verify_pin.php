<?php
ob_start();

require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/SecurityLogger.php';
require_once 'includes/term_enrollment.php';

ensure_term_enrollment_schema();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$logger = new SecurityLogger();
$userId = (int)$_SESSION['user_id'];

ensure_user_security_row($userId);

list($locked, $lockedUntil) = is_pin_locked($userId);

$errors = [];
$success = '';

$pinHash = get_user_pin_hash($userId);
$mode = ($pinHash === null || $pinHash === '') ? 'set' : 'verify';

// If session doesn't require PIN but user navigated here, allow verify/set then redirect.
if (!isset($_SESSION['require_pin'])) {
    $_SESSION['require_pin'] = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = preg_replace('/\D+/', '', $_POST['pin'] ?? '');

    if ($locked) {
        $errors[] = 'PIN entry is temporarily locked. Please try again later.';
    } elseif (strlen($pin) < 4 || strlen($pin) > 6) {
        $errors[] = 'PIN must be 4 to 6 digits.';
    } else {
        if (isset($_POST['action']) && $_POST['action'] === 'set_pin') {
            $pinConfirm = preg_replace('/\D+/', '', $_POST['pin_confirm'] ?? '');
            if ($pin !== $pinConfirm) {
                $errors[] = 'PIN confirmation does not match.';
            } else {
                set_user_pin($userId, $pin);
                $_SESSION['pin_verified'] = true;
                $_SESSION['require_pin'] = false;
                $logger->logSecurityEvent($userId, 'PIN_SET', 'User set a new PIN', [], 'medium');
                $success = 'PIN set successfully.';
            }
        } else {
            $hash = get_user_pin_hash($userId);
            if (!$hash) {
                $errors[] = 'No PIN is set for this account yet. Please set a PIN.';
                $mode = 'set';
            } else {
                if (password_verify($pin, $hash)) {
                    reset_pin_attempts($userId);
                    $_SESSION['pin_verified'] = true;
                    $_SESSION['require_pin'] = false;
                    $logger->logSecurityEvent($userId, 'PIN_SUCCESS', 'PIN verified successfully', [], 'low');
                    $success = 'PIN verified.';
                } else {
                    record_failed_pin_attempt($userId);
                    $logger->logSecurityEvent($userId, 'PIN_FAILED', 'Invalid PIN entry', [], 'medium');
                    list($lockedNow, $untilNow) = is_pin_locked($userId);
                    if ($lockedNow) {
                        $logger->logSecurityEvent($userId, 'PIN_LOCKED', 'PIN entry locked due to too many failed attempts', ['locked_until' => $untilNow], 'high');
                        $errors[] = 'Too many failed attempts. PIN is locked for 15 minutes.';
                    } else {
                        $errors[] = 'Invalid PIN.';
                    }
                }
            }
        }
    }

    if ($success !== '') {
        $redirect = $_SESSION['post_pin_redirect'] ?? null;
        if ($redirect) {
            unset($_SESSION['post_pin_redirect']);
            header('Location: ' . $redirect);
            exit;
        }
        redirect_to_dashboard();
        exit;
    }
}

if ($locked) {
    $mode = 'verify';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIN Verification</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: radial-gradient(circle at top, rgba(0,255,136,0.10), transparent 45%),
                        radial-gradient(circle at bottom, rgba(78,115,223,0.12), transparent 50%),
                        linear-gradient(135deg, #0b1220 0%, #0a0f1a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: #e9f3ff;
        }

        .pin-shell {
            width: 100%;
            max-width: 520px;
            position: relative;
        }

        .pin-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.45);
            overflow: hidden;
            backdrop-filter: blur(16px);
        }

        .pin-header {
            padding: 28px 26px;
            background: linear-gradient(135deg, rgba(0,255,136,0.18), rgba(78,115,223,0.16));
            border-bottom: 1px solid rgba(255,255,255,0.12);
        }

        .pin-title {
            margin: 0;
            font-size: 1.4rem;
            letter-spacing: 0.4px;
        }

        .pin-subtitle {
            margin: 8px 0 0;
            color: rgba(233,243,255,0.75);
            font-size: 0.95rem;
        }

        .pin-body {
            padding: 26px;
        }

        .alert {
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.06);
        }

        .alert-error { border-color: rgba(255,71,87,0.35); background: rgba(255,71,87,0.08); }
        .alert-success { border-color: rgba(0,255,136,0.30); background: rgba(0,255,136,0.08); }

        .pin-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-top: 14px;
        }

        .pin-box {
            height: 58px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.14);
            background: rgba(0,0,0,0.25);
            color: #e9f3ff;
            font-size: 1.4rem;
            text-align: center;
            outline: none;
            transition: transform .15s ease, border-color .15s ease, box-shadow .15s ease;
        }

        .pin-box:focus {
            border-color: rgba(0,255,136,0.65);
            box-shadow: 0 0 0 4px rgba(0,255,136,0.14);
            transform: translateY(-1px);
        }

        .pin-hidden {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
        }

        .pin-actions {
            margin-top: 18px;
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .btn-primary {
            border: 0;
            padding: 12px 18px;
            border-radius: 14px;
            font-weight: 700;
            letter-spacing: 0.2px;
            cursor: pointer;
            background: linear-gradient(135deg, #00ff88 0%, #2d7ff9 100%);
            color: #06101a;
            box-shadow: 0 12px 30px rgba(0,255,136,0.15);
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 16px 40px rgba(0,255,136,0.20); }

        .btn-secondary {
            padding: 12px 16px;
            border-radius: 14px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.14);
            color: rgba(233,243,255,0.9);
            cursor: pointer;
        }

        .hint {
            margin-top: 12px;
            color: rgba(233,243,255,0.65);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .shake {
            animation: shake 0.35s linear;
        }

        @keyframes shake {
            0% { transform: translateX(0); }
            20% { transform: translateX(-6px); }
            40% { transform: translateX(6px); }
            60% { transform: translateX(-5px); }
            80% { transform: translateX(5px); }
            100% { transform: translateX(0); }
        }

        @media (max-width: 520px) {
            .pin-grid { grid-template-columns: repeat(4, 1fr); }
        }
    </style>
</head>
<body>
    <div class="pin-shell">
        <div class="pin-card" id="pinCard">
            <div class="pin-header">
                <h1 class="pin-title"><i class="fas fa-key"></i> <?php echo $mode === 'set' ? 'Set Your Security PIN' : 'Enter Your Security PIN'; ?></h1>
                <p class="pin-subtitle"><?php echo $mode === 'set' ? 'Create a 4–6 digit PIN used as an extra login factor.' : 'This protects your account even if your password is exposed.'; ?></p>
            </div>
            <div class="pin-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <strong><i class="fas fa-triangle-exclamation"></i> Action required</strong>
                        <div style="margin-top:8px;">
                            <?php foreach ($errors as $e): ?>
                                <div><?php echo htmlspecialchars($e); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($locked): ?>
                    <div class="alert alert-error">
                        <strong><i class="fas fa-lock"></i> Locked</strong>
                        <div style="margin-top:8px;">Too many attempts. Try again after: <?php echo htmlspecialchars((string)$lockedUntil); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" id="pinForm" autocomplete="off">
                    <input type="hidden" name="action" value="<?php echo $mode === 'set' ? 'set_pin' : 'verify_pin'; ?>">

                    <input class="pin-hidden" id="pin" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="off">
                    <?php if ($mode === 'set'): ?>
                        <input class="pin-hidden" id="pin_confirm" name="pin_confirm" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="off">
                    <?php endif; ?>

                    <div>
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                            <div style="font-weight:700; color: rgba(233,243,255,0.9);">
                                <?php echo $mode === 'set' ? 'New PIN' : 'PIN'; ?>
                            </div>
                            <div style="font-size:0.9rem; color: rgba(233,243,255,0.65);">
                                4–6 digits
                            </div>
                        </div>

                        <div class="pin-grid" id="pinGrid"></div>

                        <?php if ($mode === 'set'): ?>
                            <div class="hint" style="margin-top:16px;">Confirm PIN</div>
                            <div class="pin-grid" id="pinConfirmGrid"></div>
                        <?php endif; ?>

                        <div class="pin-actions">
                            <button type="submit" class="btn-primary" <?php echo $locked ? 'disabled' : ''; ?> >
                                <i class="fas fa-shield-check"></i>
                                <?php echo $mode === 'set' ? 'Set PIN' : 'Verify PIN'; ?>
                            </button>
                            <a class="btn-secondary" href="logout.php" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                                <i class="fas fa-right-from-bracket"></i>
                                Logout
                            </a>
                        </div>

                        <div class="hint">
                            Tips:
                            <ul style="margin:8px 0 0 18px; color: rgba(233,243,255,0.65);">
                                <li>Do not share your PIN.</li>
                                <li>Avoid using your birthday or student number.</li>
                                <li>Too many wrong attempts will lock PIN entry temporarily.</li>
                            </ul>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const mode = <?php echo json_encode($mode); ?>;
        const hasErrors = <?php echo json_encode(!empty($errors)); ?>;

        function buildGrid(el, hiddenInput) {
            el.innerHTML = '';
            const boxes = [];
            const maxLen = 6;

            for (let i = 0; i < maxLen; i++) {
                const input = document.createElement('input');
                input.type = 'password';
                input.inputMode = 'numeric';
                input.maxLength = 1;
                input.className = 'pin-box';
                input.autocomplete = 'off';
                input.addEventListener('input', (e) => {
                    const v = (e.target.value || '').replace(/\D/g, '');
                    e.target.value = v;
                    syncHidden();
                    if (v && i < maxLen - 1) {
                        boxes[i + 1].focus();
                    }
                });
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && !input.value && i > 0) {
                        boxes[i - 1].focus();
                    }
                    if (e.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
                    if (e.key === 'ArrowRight' && i < maxLen - 1) boxes[i + 1].focus();
                });
                boxes.push(input);
                el.appendChild(input);
            }

            function syncHidden() {
                hiddenInput.value = boxes.map(b => b.value || '').join('');
            }

            // Paste support
            el.addEventListener('paste', (e) => {
                const text = (e.clipboardData || window.clipboardData).getData('text');
                const digits = (text || '').replace(/\D/g, '').slice(0, maxLen);
                for (let i = 0; i < maxLen; i++) {
                    boxes[i].value = digits[i] || '';
                }
                syncHidden();
                const nextIndex = Math.min(digits.length, maxLen - 1);
                boxes[nextIndex].focus();
                e.preventDefault();
            });

            // initial focus
            setTimeout(() => boxes[0].focus(), 150);

            return { boxes, syncHidden };
        }

        const pinHidden = document.getElementById('pin');
        const pinGrid = document.getElementById('pinGrid');
        const pinWidget = buildGrid(pinGrid, pinHidden);

        let pinConfirmWidget = null;
        if (mode === 'set') {
            const confirmHidden = document.getElementById('pin_confirm');
            const confirmGrid = document.getElementById('pinConfirmGrid');
            pinConfirmWidget = buildGrid(confirmGrid, confirmHidden);
        }

        if (hasErrors) {
            document.getElementById('pinCard').classList.add('shake');
            setTimeout(() => document.getElementById('pinCard').classList.remove('shake'), 450);
        }

        // Prevent partial pins by trimming trailing empties (server will validate length)
        document.getElementById('pinForm').addEventListener('submit', () => {
            pinWidget.syncHidden();
            if (pinConfirmWidget) pinConfirmWidget.syncHidden();
        });
    </script>
</body>
</html>
