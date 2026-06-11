<?php

function rotc_table_columns(PDO $pdo, string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[$row['Field']] = true;
        }
        $cache[$table] = $columns;
        return $columns;
    } catch (Throwable $e) {
        $cache[$table] = [];
        return [];
    }
}

function rotc_has_column(PDO $pdo, string $table, string $column): bool
{
    $columns = rotc_table_columns($pdo, $table);
    return isset($columns[$column]);
}

function rotc_select_column(PDO $pdo, string $table, string $alias, string $column, ?string $as = null): string
{
    $as = $as ?: $column;
    if (!rotc_has_column($pdo, $table, $column)) {
        return "NULL AS `{$as}`";
    }

    return "{$alias}.`{$column}` AS `{$as}`";
}

function rotc_post_value(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

function rotc_preferred_value(array $row, array $keys, string $fallback = ''): string
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }

    return $fallback;
}

function rotc_display_name(array $row): string
{
    $profileFull = rotc_preferred_value($row, ['profile_full_name']);
    if ($profileFull !== '') {
        return $profileFull;
    }

    $parts = array_filter([
        rotc_preferred_value($row, ['profile_first_name', 'user_first_name']),
        rotc_preferred_value($row, ['profile_middle_name']),
        rotc_preferred_value($row, ['profile_last_name', 'user_last_name']),
    ], fn($part) => $part !== '');

    if (!empty($parts)) {
        return implode(' ', $parts);
    }

    return rotc_preferred_value($row, ['user_full_name', 'username'], 'Unknown User');
}

function rotc_admin_user_select(PDO $pdo): string
{
    $userColumns = [
        'id', 'username', 'email', 'role', 'created_at', 'updated_at', 'status',
        'approval_status', 'is_active', 'full_name', 'first_name', 'last_name',
        'student_id', 'course', 'year_level', 'contact_number', 'last_login',
        'paper_form_submitted', 'paper_form_submitted_date', 'paper_form_notes'
    ];

    $profileColumns = [
        'id', 'user_id', 'student_number', 'student_id', 'full_name', 'email',
        'first_name', 'middle_name', 'last_name', 'gender', 'address',
        'contact_number', 'contact', 'phone', 'course', 'section', 'year_level',
        'platoon', 'status', 'facebook_profile', 'birthdate', 'date_of_birth',
        'birth_date', 'place_of_birth', 'birth_place', 'province_city', 'barangay',
        'city', 'region', 'civil_status', 'religion', 'blood_type', 'height',
        'weight', 'medical_conditions', 'emergency_contact',
        'emergency_contact_name', 'emergency_contact_number', 'emergency_phone',
        'guardian_name', 'guardian_contact', 'guardian_relationship',
        'guardian_address', 'beneficiary_name', 'beneficiary_relationship',
        'beneficiary_address', 'father_name', 'father_occupation',
        'mother_name', 'mother_occupation', 'academic_year', 'semester',
        'photo_path', 'signature_path', 'qr_code_path', 'created_at', 'updated_at'
    ];

    $selects = [];
    foreach ($userColumns as $column) {
        $selects[] = rotc_select_column($pdo, 'users', 'u', $column, $column);
    }
    foreach ($profileColumns as $column) {
        $selects[] = rotc_select_column($pdo, 'cadet_profiles', 'cp', $column, 'profile_' . $column);
    }

    return implode(",\n        ", $selects);
}

function rotc_fetch_admin_users(PDO $pdo): array
{
    $select = rotc_admin_user_select($pdo);
    $stmt = $pdo->query("SELECT
        {$select}
        FROM users u
        LEFT JOIN cadet_profiles cp ON cp.user_id = u.id
        ORDER BY u.id ASC");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function rotc_fetch_admin_user(PDO $pdo, int $userId): ?array
{
    $select = rotc_admin_user_select($pdo);
    $stmt = $pdo->prepare("SELECT
        {$select}
        FROM users u
        LEFT JOIN cadet_profiles cp ON cp.user_id = u.id
        WHERE u.id = ?
        LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function rotc_update_columns(PDO $pdo, string $table, array $values, string $whereSql, array $whereParams): void
{
    $sets = [];
    $params = [];

    foreach ($values as $column => $value) {
        if (!rotc_has_column($pdo, $table, $column)) {
            continue;
        }
        $sets[] = "`{$column}` = ?";
        $params[] = $value;
    }

    if (empty($sets)) {
        return;
    }

    if (rotc_has_column($pdo, $table, 'updated_at') && !isset($values['updated_at'])) {
        $sets[] = "`updated_at` = NOW()";
    }

    $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE {$whereSql}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, $whereParams));
}

function rotc_upsert_cadet_profile(PDO $pdo, int $userId, array $values): void
{
    $profileColumns = rotc_table_columns($pdo, 'cadet_profiles');
    if (empty($profileColumns) || !isset($profileColumns['user_id'])) {
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM cadet_profiles WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $profileId = $stmt->fetchColumn();

    if ($profileId) {
        rotc_update_columns($pdo, 'cadet_profiles', $values, 'user_id = ?', [$userId]);
        return;
    }

    $columns = ['user_id'];
    $params = [$userId];
    $placeholders = ['?'];

    foreach ($values as $column => $value) {
        if (!isset($profileColumns[$column]) || $column === 'id' || $column === 'user_id') {
            continue;
        }
        if ($value === '') {
            continue;
        }
        $columns[] = $column;
        $params[] = $value;
        $placeholders[] = '?';
    }

    if (isset($profileColumns['created_at'])) {
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
    }
    if (isset($profileColumns['updated_at'])) {
        $columns[] = 'updated_at';
        $placeholders[] = 'NOW()';
    }

    $columnSql = '`' . implode('`, `', $columns) . '`';
    $placeholderSql = implode(', ', $placeholders);
    $stmt = $pdo->prepare("INSERT INTO cadet_profiles ({$columnSql}) VALUES ({$placeholderSql})");
    $stmt->execute($params);
}

function rotc_user_search_blob(array $row): string
{
    $keys = [
        'id', 'username', 'email', 'role', 'status', 'approval_status',
        'user_full_name', 'user_first_name', 'user_last_name', 'student_id',
        'course', 'year_level', 'contact_number', 'profile_student_number',
        'profile_student_id', 'profile_full_name', 'profile_email',
        'profile_first_name', 'profile_middle_name', 'profile_last_name',
        'profile_contact_number', 'profile_contact', 'profile_phone',
        'profile_course', 'profile_section', 'profile_year_level',
        'profile_platoon', 'profile_address', 'profile_facebook_profile',
        'profile_guardian_name', 'profile_guardian_contact',
        'profile_beneficiary_name', 'profile_emergency_contact_number'
    ];

    return strtolower(implode(' ', array_map(fn($key) => (string)($row[$key] ?? ''), $keys)));
}

