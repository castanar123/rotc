<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
require_once 'includes/SecurityLogger.php';
require_once 'includes/term_enrollment.php';

// Admin-only access
check_login();
if ($_SESSION['role'] !== 'admin') {
    SecurityLogger::logSecurityEvent('UNAUTHORIZED_ACCESS', 'Non-admin user attempted to access enrollment management', $_SESSION['user_id'] ?? null, 'HIGH');
    redirect_to_dashboard();
}

// Log successful admin access to enrollment management
SecurityLogger::logSecurityEvent('ADMIN_ACCESS', 'Admin accessed enrollment management page', $_SESSION['user_id'], 'LOW');

ensure_term_enrollment_schema();
$__term = get_active_term();
$__school_year = $__term['school_year'] ?? '';
$__semester = $__term['semester'] ?? '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_platoon':
            $user_id = $_POST['user_id'];
            $platoon = $_POST['platoon'];
            
            try {
                $stmt = $pdo->prepare("UPDATE cadet_profiles SET platoon = ? WHERE user_id = ?");
                $stmt->execute([$platoon, $user_id]);
                SecurityLogger::logSecurityEvent('DATA_MODIFICATION', "Admin updated cadet platoon assignment: User ID $user_id to $platoon", $_SESSION['user_id'], 'MEDIUM');
                echo json_encode(['success' => true, 'message' => 'Platoon updated successfully']);
            } catch (Exception $e) {
                SecurityLogger::logSecurityEvent('OPERATION_FAILED', "Failed to update cadet platoon: " . $e->getMessage(), $_SESSION['user_id'], 'HIGH');
                echo json_encode(['success' => false, 'message' => 'Error updating platoon: ' . $e->getMessage()]);
            }
            exit;
            
        case 'bulk_assign':
            $user_ids_raw = $_POST['user_ids'] ?? '[]';
            $platoon = $_POST['platoon'] ?? '';

            $user_ids = [];
            if (is_string($user_ids_raw)) {
                $decoded = json_decode($user_ids_raw, true);
                if (is_array($decoded)) {
                    $user_ids = $decoded;
                }
            } elseif (is_array($user_ids_raw)) {
                $user_ids = $user_ids_raw;
            }
            $user_ids = array_values(array_filter(array_map('intval', $user_ids), function ($v) { return $v > 0; }));

            if (empty($user_ids) || $platoon === '') {
                echo json_encode(['success' => false, 'message' => 'Missing platoon or cadets to assign']);
                exit;
            }

            try {
                $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
                $params = array_merge([$platoon], $user_ids);
                $stmt = $pdo->prepare("UPDATE cadet_profiles SET platoon = ? WHERE user_id IN ($placeholders)");
                $stmt->execute($params);
                SecurityLogger::logSecurityEvent('DATA_MODIFICATION', "Admin performed bulk platoon assignment: " . count($user_ids) . " cadets to $platoon", $_SESSION['user_id'], 'MEDIUM');
                echo json_encode(['success' => true, 'message' => 'Bulk assignment completed']);
            } catch (Exception $e) {
                SecurityLogger::logSecurityEvent('OPERATION_FAILED', "Failed bulk platoon assignment: " . $e->getMessage(), $_SESSION['user_id'], 'HIGH');
                echo json_encode(['success' => false, 'message' => 'Error in bulk assignment: ' . $e->getMessage()]);
            }
            exit;
            
        case 'auto_assign':
            try {
                // Get unassigned cadets grouped by course and gender
                $stmt = $pdo->query("
                    SELECT cp.user_id, cp.course, cp.gender, 
                           CONCAT(cp.first_name, ' ', IFNULL(cp.middle_name, ''), ' ', cp.last_name) as full_name
                    FROM cadet_profiles cp
                    JOIN users u ON cp.user_id = u.id
                    WHERE (cp.platoon IS NULL OR cp.platoon = '' OR cp.platoon = 'Temporary')
                    AND u.role = 'basic_cadet'
                    ORDER BY cp.course, cp.gender, cp.first_name, cp.last_name
                ");
                $unassigned = $stmt->fetchAll();
                
                // Get current platoon counts
                $platoon_counts = [];
                $platoons = ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo'];
                foreach ($platoons as $platoon) {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as count
                        FROM cadet_profiles cp
                        JOIN users u ON cp.user_id = u.id
                        WHERE cp.platoon = ?
                    ");
                    $stmt->execute([$platoon]);
                    $platoon_counts[$platoon] = $stmt->fetch()['count'];
                }
                
                // Auto-assign logic
                $assignments = [];
                $course_groups = [];
                
                // Group by course and gender
                foreach ($unassigned as $cadet) {
                    $key = $cadet['course'] . '_' . $cadet['gender'];
                    if (!isset($course_groups[$key])) {
                        $course_groups[$key] = [];
                    }
                    $course_groups[$key][] = $cadet;
                }
                
                // Assign each group to platoons
                foreach ($course_groups as $group) {
                    $current_platoon = null;
                    $current_count = 0;
                    
                    foreach ($group as $cadet) {
                        // Find platoon with lowest count that can accommodate
                        if ($current_platoon === null || $current_count >= 28) {
                            $current_platoon = array_keys($platoon_counts, min($platoon_counts))[0];
                            $current_count = $platoon_counts[$current_platoon];
                        }
                        
                        if ($current_count < 28) {
                            $assignments[] = [
                                'user_id' => $cadet['user_id'],
                                'platoon' => $current_platoon
                            ];
                            $platoon_counts[$current_platoon]++;
                            $current_count++;
                        }
                    }
                }
                
                // Execute assignments
                foreach ($assignments as $assignment) {
                    $stmt = $pdo->prepare("UPDATE cadet_profiles SET platoon = ? WHERE user_id = ?");
                    $stmt->execute([$assignment['platoon'], $assignment['user_id']]);
                }
                
                SecurityLogger::logSecurityEvent('DATA_MODIFICATION', "Admin performed auto-assignment: " . count($assignments) . " cadets assigned to platoons", $_SESSION['user_id'], 'MEDIUM');
                echo json_encode([
                    'success' => true, 
                    'message' => 'Auto-assignment completed. ' . count($assignments) . ' cadets assigned.',
                    'assignments' => count($assignments)
                ]);
            } catch (Exception $e) {
                SecurityLogger::logSecurityEvent('OPERATION_FAILED', "Failed auto-assignment operation: " . $e->getMessage(), $_SESSION['user_id'], 'HIGH');
                echo json_encode(['success' => false, 'message' => 'Error in auto-assignment: ' . $e->getMessage()]);
            }
            exit;

        case 'drop_selected':
            $user_ids_raw = $_POST['user_ids'] ?? '[]';

            $user_ids = [];
            if (is_string($user_ids_raw)) {
                $decoded = json_decode($user_ids_raw, true);
                if (is_array($decoded)) {
                    $user_ids = $decoded;
                }
            } elseif (is_array($user_ids_raw)) {
                $user_ids = $user_ids_raw;
            }
            $user_ids = array_values(array_filter(array_map('intval', $user_ids), function ($v) { return $v > 0; }));

            if (empty($__school_year) || empty($__semester)) {
                echo json_encode(['success' => false, 'message' => 'Active academic term is not set. Please select a term first.']);
                exit;
            }

            if (empty($user_ids)) {
                echo json_encode(['success' => false, 'message' => 'No cadets selected to drop.']);
                exit;
            }

            try {
                $dropped = 0;
                $stmtCp = $pdo->prepare("SELECT id FROM cadet_profiles WHERE user_id = ? LIMIT 1");
                foreach ($user_ids as $uid) {
                    $stmtCp->execute([(int)$uid]);
                    $cpid = (int)($stmtCp->fetchColumn() ?: 0);
                    if ($cpid > 0) {
                        set_cadet_enrollment_status($cpid, $__school_year, $__semester, 'dropped', 'admin_manual_drop', $_SESSION['user_id'] ?? null);
                        $dropped++;
                    }
                }
                SecurityLogger::logSecurityEvent('DATA_MODIFICATION', "Admin dropped selected cadets from term {$__school_year} {$__semester}: $dropped affected", $_SESSION['user_id'], 'HIGH');
                echo json_encode(['success' => true, 'message' => "Dropped $dropped cadets from the current term."]);
            } catch (Exception $e) {
                SecurityLogger::logSecurityEvent('OPERATION_FAILED', "Failed to drop selected cadets: " . $e->getMessage(), $_SESSION['user_id'], 'HIGH');
                echo json_encode(['success' => false, 'message' => 'Error dropping cadets: ' . $e->getMessage()]);
            }
            exit;

        case 'drop_all':
            if (empty($__school_year) || empty($__semester)) {
                echo json_encode(['success' => false, 'message' => 'Active academic term is not set. Please select a term first.']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("SELECT ce.cadet_profile_id
                    FROM cadet_enrollments ce
                    JOIN cadet_profiles cp ON ce.cadet_profile_id = cp.id
                    JOIN users u ON cp.user_id = u.id
                    WHERE ce.school_year = ?
                      AND ce.semester = ?
                      AND ce.enrollment_status = 'enrolled'
                      AND u.role IN ('basic-cadet','basic_cadet','cadet')");
                $stmt->execute([$__school_year, $__semester]);
                $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

                $dropped = 0;
                foreach ($rows as $cpid) {
                    $cpid = (int)$cpid;
                    if ($cpid > 0) {
                        set_cadet_enrollment_status($cpid, $__school_year, $__semester, 'dropped', 'admin_bulk_drop', $_SESSION['user_id'] ?? null);
                        $dropped++;
                    }
                }

                SecurityLogger::logSecurityEvent('DATA_MODIFICATION', "Admin dropped ALL enrolled cadets for term {$__school_year} {$__semester}: $dropped affected", $_SESSION['user_id'], 'CRITICAL');
                echo json_encode(['success' => true, 'message' => "Dropped $dropped cadets from the current term."]);
            } catch (Exception $e) {
                SecurityLogger::logSecurityEvent('OPERATION_FAILED', "Failed to drop all cadets: " . $e->getMessage(), $_SESSION['user_id'], 'HIGH');
                echo json_encode(['success' => false, 'message' => 'Error dropping all cadets: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Get statistics scoped to active academic term when available
$stats = [];

$term = $__term;
$emSy = $term['school_year'] ?? '';
$emSem = $term['semester'] ?? '';

// Total cadets (enrolled in active term when term set; otherwise global)
if ($emSy !== '' && $emSem !== '') {
    $stmt = $pdo->prepare("\n        SELECT COUNT(DISTINCT ce.cadet_profile_id) as total\n        FROM cadet_enrollments ce\n        JOIN cadet_profiles cp ON ce.cadet_profile_id = cp.id\n        JOIN users u ON cp.user_id = u.id\n        WHERE ce.school_year = ?\n          AND ce.semester = ?\n          AND ce.enrollment_status = 'enrolled'\n          AND u.role = 'basic-cadet'\n          AND u.approval_status = 'approved'\n          AND u.status = 'active'\n    ");
    $stmt->execute([$emSy, $emSem]);
    $stats['total_cadets'] = (int)($stmt->fetch()['total'] ?? 0);
} else {
    $stmt = $pdo->query("\n        SELECT COUNT(*) as total\n        FROM cadet_profiles cp\n        JOIN users u ON cp.user_id = u.id\n        WHERE u.role = 'basic-cadet' AND u.approval_status = 'approved' AND u.status = 'active'\n    ");
    $stats['total_cadets'] = (int)($stmt->fetch()['total'] ?? 0);
}

// Unassigned cadets (within term when set)
if ($emSy !== '' && $emSem !== '') {
    $stmt = $pdo->prepare("\n        SELECT COUNT(DISTINCT ce.cadet_profile_id) as total\n        FROM cadet_enrollments ce\n        JOIN cadet_profiles cp ON ce.cadet_profile_id = cp.id\n        JOIN users u ON cp.user_id = u.id\n        WHERE ce.school_year = ?\n          AND ce.semester = ?\n          AND ce.enrollment_status = 'enrolled'\n          AND (cp.platoon IS NULL OR cp.platoon = '' OR cp.platoon = 'Temporary')\n          AND u.role = 'basic-cadet'\n          AND u.approval_status = 'approved'\n          AND u.status = 'active'\n    ");
    $stmt->execute([$emSy, $emSem]);
    $stats['unassigned'] = (int)($stmt->fetch()['total'] ?? 0);
} else {
    $stmt = $pdo->query("\n        SELECT COUNT(*) as total\n        FROM cadet_profiles cp\n        JOIN users u ON cp.user_id = u.id\n        WHERE (cp.platoon IS NULL OR cp.platoon = '' OR cp.platoon = 'Temporary')\n        AND u.role = 'basic-cadet' AND u.approval_status = 'approved' AND u.status = 'active'\n    ");
    $stats['unassigned'] = (int)($stmt->fetch()['total'] ?? 0);
}

// Platoon counts (within term when set)
$platoon_stats = [];
$platoons = ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo'];
foreach ($platoons as $platoon) {
    if ($emSy !== '' && $emSem !== '') {
        $stmt = $pdo->prepare("\n            SELECT COUNT(DISTINCT ce.cadet_profile_id) as count\n            FROM cadet_enrollments ce\n            JOIN cadet_profiles cp ON ce.cadet_profile_id = cp.id\n            JOIN users u ON cp.user_id = u.id\n            WHERE ce.school_year = ?\n              AND ce.semester = ?\n              AND ce.enrollment_status = 'enrolled'\n              AND cp.platoon = ?\n              AND u.role = 'basic-cadet'\n              AND u.approval_status = 'approved'\n              AND u.status = 'active'\n        ");
        $stmt->execute([$emSy, $emSem, $platoon]);
        $platoon_stats[$platoon] = (int)($stmt->fetch()['count'] ?? 0);
    } else {
        $stmt = $pdo->prepare("\n            SELECT COUNT(*) as count\n            FROM cadet_profiles cp\n            JOIN users u ON cp.user_id = u.id\n            WHERE cp.platoon = ?\n        ");
        $stmt->execute([$platoon]);
        $platoon_stats[$platoon] = (int)($stmt->fetch()['count'] ?? 0);
    }
}

// Get all cadets with detailed info, filtered to active term enrollments when set
if ($emSy !== '' && $emSem !== '') {
    $stmt = $pdo->prepare("\n        SELECT \n            cp.user_id,\n            CONCAT(cp.first_name, ' ', IFNULL(cp.middle_name, ''), ' ', cp.last_name) as full_name,\n            cp.course,\n            cp.gender,\n            cp.platoon,\n            cp.section,\n            u.email,\n            u.created_at\n        FROM cadet_enrollments ce\n        JOIN cadet_profiles cp ON ce.cadet_profile_id = cp.id\n        JOIN users u ON cp.user_id = u.id\n        WHERE ce.school_year = ?\n          AND ce.semester = ?\n          AND ce.enrollment_status = 'enrolled'\n          AND u.role = 'basic-cadet'\n          AND u.approval_status = 'approved'\n          AND u.status = 'active'\n        ORDER BY cp.course, cp.gender, cp.first_name, cp.last_name\n    ");
    $stmt->execute([$emSy, $emSem]);
    $cadets = $stmt->fetchAll();
} else {
    $stmt = $pdo->query("\n        SELECT \n            cp.user_id,\n            CONCAT(cp.first_name, ' ', IFNULL(cp.middle_name, ''), ' ', cp.last_name) as full_name,\n            cp.course,\n            cp.gender,\n            cp.platoon,\n            cp.section,\n            u.email,\n            u.created_at\n        FROM cadet_profiles cp\n        JOIN users u ON cp.user_id = u.id\n        WHERE u.role = 'basic-cadet' AND u.approval_status = 'approved' AND u.status = 'active'\n        ORDER BY cp.course, cp.gender, cp.first_name, cp.last_name\n    ");
    $cadets = $stmt->fetchAll();
}

// Group cadets by course for analysis
$course_analysis = [];
foreach ($cadets as $cadet) {
    $course = $cadet['course'];
    if (!isset($course_analysis[$course])) {
        $course_analysis[$course] = ['male' => 0, 'female' => 0, 'total' => 0];
    }
    $course_analysis[$course][strtolower($cadet['gender'])]++;
    $course_analysis[$course]['total']++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment & Platoon Management - ROTC System</title>
    <link rel="stylesheet" href="css/tactical-theme.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
    <style>
        .enrollment-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            opacity: 0.9;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .platoon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .platoon-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .platoon-card.full {
            border-color: #dc3545;
            background: #fff5f5;
        }
        
        .platoon-card.near-full {
            border-color: #ffc107;
            background: #fffbf0;
        }
        
        .control-panel {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .cadets-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        
        .data-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .platoon-select {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }
        
        .gender-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .gender-male {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .gender-female {
            background: #fce4ec;
            color: #c2185b;
        }
        
        .course-analysis {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .analysis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .course-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .checkbox-column {
            width: 40px;
        }
        
        .bulk-actions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .selected-count {
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="enrollment-container">
        <header style="margin-bottom: 30px;">
            <h1><i class="fas fa-users-cog"></i> Enrollment & Platoon Management</h1>
            <p>Manage cadet enrollment and optimize platoon assignments based on course and gender distribution</p>
        </header>
        
        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Cadets</h3>
                <div class="value"><?php echo $stats['total_cadets']; ?></div>
                <small>Active basic cadets</small>
            </div>
            <div class="stat-card">
                <h3>Unassigned</h3>
                <div class="value"><?php echo $stats['unassigned']; ?></div>
                <small>Awaiting platoon assignment</small>
            </div>
            <div class="stat-card">
                <h3>Assignment Rate</h3>
                <div class="value"><?php echo $stats['total_cadets'] > 0 ? round((($stats['total_cadets'] - $stats['unassigned']) / $stats['total_cadets']) * 100, 1) : 0; ?>%</div>
                <small>Cadets with platoons</small>
            </div>
        </div>
        
        <!-- Platoon Status Overview -->
        <div class="platoon-grid">
            <?php foreach ($platoon_stats as $platoon => $count): ?>
            <div class="platoon-card <?php echo $count >= 28 ? 'full' : ($count >= 25 ? 'near-full' : ''); ?>">
                <h4><?php echo $platoon; ?> Platoon</h4>
                <div style="font-size: 24px; font-weight: bold; margin: 10px 0;">
                    <?php echo $count; ?>/28
                </div>
                <div style="background: #e0e0e0; height: 8px; border-radius: 4px; overflow: hidden;">
                    <div style="background: <?php echo $count >= 28 ? '#dc3545' : ($count >= 25 ? '#ffc107' : '#28a745'); ?>; height: 100%; width: <?php echo min(($count / 28) * 100, 100); ?>%;"></div>
                </div>
                <small><?php echo 28 - $count; ?> slots available</small>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Course Analysis -->
        <div class="course-analysis">
            <h3><i class="fas fa-chart-bar"></i> Course Distribution Analysis</h3>
            <div class="analysis-grid">
                <?php foreach ($course_analysis as $course => $data): ?>
                <div class="course-item">
                    <h4><?php echo htmlspecialchars($course); ?></h4>
                    <div style="margin: 10px 0;">
                        <div>Total: <strong><?php echo $data['total']; ?></strong></div>
                        <div>Male: <span class="gender-badge gender-male"><?php echo $data['male']; ?></span></div>
                        <div>Female: <span class="gender-badge gender-female"><?php echo $data['female']; ?></span></div>
                    </div>
                    <small>Recommended: Keep same course/gender together</small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Control Panel -->
        <div class="control-panel">
            <h3><i class="fas fa-cogs"></i> Assignment Controls</h3>
            
            <div class="filters">
                <div>
                    <label>Filter by Course:</label>
                    <select id="courseFilter" class="form-control">
                        <option value="">All Courses</option>
                        <?php 
                        $courses = array_keys($course_analysis);
                        foreach ($courses as $course): 
                        ?>
                        <option value="<?php echo htmlspecialchars($course); ?>"><?php echo htmlspecialchars($course); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label>Filter by Gender:</label>
                    <select id="genderFilter" class="form-control">
                        <option value="">All Genders</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                
                <div>
                    <label>Filter by Platoon:</label>
                    <select id="platoonFilter" class="form-control">
                        <option value="">All Platoons</option>
                        <option value="unassigned">Unassigned</option>
                        <?php foreach ($platoons as $platoon): ?>
                        <option value="<?php echo $platoon; ?>"><?php echo $platoon; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="action-buttons">
                <button class="btn btn-success" onclick="autoAssign()">
                    <i class="fas fa-magic"></i> Auto-Assign by Course & Gender
                </button>
                <button class="btn btn-primary" onclick="selectUnassigned()">
                    <i class="fas fa-users"></i> Select All Unassigned
                </button>
                <button class="btn btn-warning" onclick="exportData()">
                    <i class="fas fa-download"></i> Export Data
                </button>
                <button class="btn btn-primary" onclick="window.location.href='register.php'">
                    <i class="fas fa-user-plus"></i> Add New Cadet
                </button>
                <button class="btn btn-danger" onclick="dropAllFromTerm()">
                    <i class="fas fa-user-slash"></i> Drop ALL Cadets This Term
                </button>
            </div>
        </div>
        
        <!-- Bulk Actions Panel -->
        <div class="bulk-actions" id="bulkActions">
            <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                <span>Selected: <span class="selected-count" id="selectedCount">0</span> cadets</span>
                <select id="bulkPlatoon" class="form-control" style="width: 200px;">
                    <option value="">Select Platoon</option>
                    <?php foreach ($platoons as $platoon): ?>
                    <option value="<?php echo $platoon; ?>"><?php echo $platoon; ?></option>
                    <?php endforeach; ?>
                    <option value="Temporary">Temporary</option>
                </select>
                <button class="btn btn-primary" onclick="bulkAssign()">
                    <i class="fas fa-users-cog"></i> Assign Selected
                </button>
                <button class="btn btn-danger" onclick="dropSelectedFromTerm()">
                    <i class="fas fa-user-slash"></i> Drop Selected From Term
                </button>
                <button class="btn btn-secondary" onclick="clearSelection()">
                    <i class="fas fa-times"></i> Clear Selection
                </button>
            </div>
        </div>
        
        <!-- Cadets Table -->
        <div class="cadets-table">
            <div class="table-header">
                <h3><i class="fas fa-table"></i> Cadet Roster</h3>
                <p>Click on cadets to select them for bulk operations. Use filters to narrow down the list.</p>
            </div>
            
            <div class="loading" id="loading">
                <i class="fas fa-spinner fa-spin"></i> Processing...
            </div>
            
            <table class="data-table" id="cadetsTable">
                <thead>
                    <tr>
                        <th class="checkbox-column">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        </th>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Gender</th>
                        <th>Current Platoon</th>
                        <th>Assign Platoon</th>
                        <th>Registered</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cadets as $cadet): ?>
                    <tr data-course="<?php echo htmlspecialchars($cadet['course']); ?>" 
                        data-gender="<?php echo htmlspecialchars($cadet['gender']); ?>" 
                        data-platoon="<?php echo htmlspecialchars($cadet['platoon'] ?: 'unassigned'); ?>">
                        <td>
                            <input type="checkbox" class="cadet-checkbox" value="<?php echo $cadet['user_id']; ?>" onchange="updateSelection()">
                        </td>
                        <td><?php echo htmlspecialchars($cadet['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($cadet['course']); ?></td>
                        <td>
                            <span class="gender-badge gender-<?php echo strtolower($cadet['gender']); ?>">
                                <?php echo htmlspecialchars($cadet['gender']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($cadet['platoon']): ?>
                                <strong><?php echo htmlspecialchars($cadet['platoon']); ?></strong>
                            <?php else: ?>
                                <span style="color: #dc3545;">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <select class="platoon-select" onchange="updatePlatoon(<?php echo $cadet['user_id']; ?>, this.value)">
                                <option value="">Select...</option>
                                <?php foreach ($platoons as $platoon): ?>
                                <option value="<?php echo $platoon; ?>" <?php echo $cadet['platoon'] === $platoon ? 'selected' : ''; ?>>
                                    <?php echo $platoon; ?> (<?php echo $platoon_stats[$platoon]; ?>/28)
                                </option>
                                <?php endforeach; ?>
                                <option value="Temporary" <?php echo $cadet['platoon'] === 'Temporary' ? 'selected' : ''; ?>>Temporary</option>
                            </select>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($cadet['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        let selectedCadets = new Set();
        
        function updatePlatoon(userId, platoon) {
            if (!platoon) return;
            
            showLoading(true);
            
            fetch('enrollment_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_platoon&user_id=${userId}&platoon=${encodeURIComponent(platoon)}`
            })
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                if (data.success) {
                    showMessage(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                showLoading(false);
                showMessage('Network error occurred', 'error');
            });
        }

        function dropSelectedFromTerm() {
            if (selectedCadets.size === 0) {
                showMessage('Please select cadets to drop from the term', 'error');
                return;
            }

            if (!confirm('This will mark the selected cadets as DROPPED for the current academic term. Attendance and documents will no longer count them for this term. Continue?')) {
                return;
            }

            showLoading(true);

            const userIds = Array.from(selectedCadets);
            const formData = new FormData();
            formData.append('action', 'drop_selected');
            formData.append('user_ids', JSON.stringify(userIds));

            fetch('enrollment_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                if (data.success) {
                    showMessage(data.message, 'success');
                    clearSelection();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                showLoading(false);
                showMessage('Network error occurred', 'error');
            });
        }
        
        function autoAssign() {
            if (!confirm('This will automatically assign unassigned cadets to platoons based on course and gender. Continue?')) {
                return;
            }
            
            showLoading(true);
            
            fetch('enrollment_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=auto_assign'
            })
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                if (data.success) {
                    showMessage(data.message, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                showLoading(false);
                showMessage('Network error occurred', 'error');
            });
        }
        
        function bulkAssign() {
            const platoon = document.getElementById('bulkPlatoon').value;
            if (!platoon) {
                showMessage('Please select a platoon', 'error');
                return;
            }
            
            if (selectedCadets.size === 0) {
                showMessage('Please select cadets to assign', 'error');
                return;
            }
            
            showLoading(true);
            
            const userIds = Array.from(selectedCadets);
            const formData = new FormData();
            formData.append('action', 'bulk_assign');
            formData.append('platoon', platoon);
            formData.append('user_ids', JSON.stringify(userIds));
            
            fetch('enrollment_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                if (data.success) {
                    showMessage(data.message, 'success');
                    clearSelection();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                showLoading(false);
                showMessage('Network error occurred', 'error');
            });
        }
        
        function updateSelection() {
            selectedCadets.clear();
            document.querySelectorAll('.cadet-checkbox:checked').forEach(cb => {
                selectedCadets.add(parseInt(cb.value));
            });
            
            document.getElementById('selectedCount').textContent = selectedCadets.size;
            document.getElementById('bulkActions').style.display = selectedCadets.size > 0 ? 'block' : 'none';
        }
        
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.cadet-checkbox');
            
            checkboxes.forEach(cb => {
                if (cb.closest('tr').style.display !== 'none') {
                    cb.checked = selectAll.checked;
                }
            });
            
            updateSelection();
        }
        
        function selectUnassigned() {
            document.querySelectorAll('.cadet-checkbox').forEach(cb => {
                const row = cb.closest('tr');
                const platoon = row.dataset.platoon;
                cb.checked = platoon === 'unassigned' || platoon === '';
            });
            updateSelection();
        }
        
        function clearSelection() {
            document.querySelectorAll('.cadet-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            updateSelection();
        }
        
        function applyFilters() {
            const courseFilter = document.getElementById('courseFilter').value.toLowerCase();
            const genderFilter = document.getElementById('genderFilter').value.toLowerCase();
            const platoonFilter = document.getElementById('platoonFilter').value.toLowerCase();
            
            document.querySelectorAll('#cadetsTable tbody tr').forEach(row => {
                const course = row.dataset.course.toLowerCase();
                const gender = row.dataset.gender.toLowerCase();
                const platoon = row.dataset.platoon.toLowerCase();
                
                const courseMatch = !courseFilter || course.includes(courseFilter);
                const genderMatch = !genderFilter || gender === genderFilter;
                const platoonMatch = !platoonFilter || platoon === platoonFilter;
                
                row.style.display = courseMatch && genderMatch && platoonMatch ? '' : 'none';
            });
        }
        
        function exportData() {
            const data = [];
            document.querySelectorAll('#cadetsTable tbody tr').forEach(row => {
                if (row.style.display !== 'none') {
                    const cells = row.querySelectorAll('td');
                    data.push({
                        student_id: cells[1].textContent,
                        name: cells[2].textContent,
                        course: cells[3].textContent,
                        gender: cells[4].textContent,
                        platoon: cells[5].textContent,
                        registered: cells[7].textContent
                    });
                }
            });
            
            const csv = [['Student ID', 'Name', 'Course', 'Gender', 'Platoon', 'Registered']];
            data.forEach(row => {
                csv.push([row.student_id, row.name, row.course, row.gender, row.platoon, row.registered]);
            });
            
            const csvContent = csv.map(row => row.join(',')).join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'cadet_enrollment_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function dropAllFromTerm() {
            if (!confirm('This will mark ALL currently enrolled cadets for the active academic term as DROPPED. This is irreversible for this term. Continue?')) {
                return;
            }

            showLoading(true);

            fetch('enrollment_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=drop_all'
            })
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                if (data.success) {
                    showMessage(data.message, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                showLoading(false);
                showMessage('Network error occurred', 'error');
            });
        }
        
        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
        }
        
        function showMessage(message, type) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert ${alertClass}`;
            alertDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                padding: 15px;
                border-radius: 5px;
                background: ${type === 'success' ? '#d4edda' : '#f8d7da'};
                color: ${type === 'success' ? '#155724' : '#721c24'};
                border: 1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'};
                max-width: 400px;
            `;
            alertDiv.textContent = message;
            
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
        
        // Event listeners
        document.getElementById('courseFilter').addEventListener('change', applyFilters);
        document.getElementById('genderFilter').addEventListener('change', applyFilters);
        document.getElementById('platoonFilter').addEventListener('change', applyFilters);
        
        // Initialize
        updateSelection();
    </script>
</body>
</html>