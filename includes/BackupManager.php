<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/SecurityLogger.php';

class BackupManager {
    private $pdo;
    private $logger;
    private $backupDir;
    private $encryptionKey;
    
    // --- Schema helpers ---
    private function tableExists($table) {
        try {
            $this->pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (Throwable $e) {
            try {
                $stmt = $this->pdo->query('SELECT DATABASE()');
                $db = $stmt ? $stmt->fetchColumn() : null;
                if (!$db) return false;
                $q = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
                $q->execute([$db, $table]);
                return (int)$q->fetchColumn() > 0;
            } catch (Throwable $e2) {
                return false;
            }
        }
    }
    
    /**
     * Perform automated hourly backup (keeps .sql by default)
     */
    public function performHourlyBackup($encrypt = false) {
        try {
            $backupId = $this->createBackupJob('automated', 'hourly');
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "rotc_hourly_backup_{$timestamp}.sql";
            $filepath = $this->backupDir . '/' . $filename;
            
            $this->createDatabaseDump($filepath);
            $finalFile = $encrypt ? $this->encryptBackupFile($filepath) : $filepath;
            $checksum = hash_file('sha256', $finalFile);
            $fileSize = filesize($finalFile);
            $this->updateBackupJob($backupId, 'completed', $finalFile, $fileSize, $checksum);
            
            $this->logger->logSecurityEvent(
                null,
                'backup_completed',
                'Automated hourly backup completed successfully',
                ['backup_id' => $backupId, 'file_size' => $fileSize]
            );
            return $backupId;
        } catch (Exception $e) {
            if (isset($backupId)) {
                $this->updateBackupJob($backupId, 'failed', null, 0, null, $e->getMessage());
            }
            $this->logger->logSecurityEvent(
                null,
                'backup_failed',
                'Automated hourly backup failed: ' . $e->getMessage(),
                ['error' => $e->getMessage()],
                'high'
            );
            throw $e;
        }
    }

    /**
     * Roll up today's hourly backups before a cutoff time: keep newest N and mark them as daily, delete the rest.
     * Returns an array with counts: ['kept' => x, 'deleted' => y]
     */
    public function rollupHourlyBackups($keepCount = 2, $cutoff = '22:00:00') {
        if (!$this->tableExists('backup_jobs')) return ['kept' => 0, 'deleted' => 0];
        $today = date('Y-m-d');
        $start = $today . ' 00:00:00';
        $cutoffDt = $today . ' ' . $cutoff;
        $bjIdCol = $this->firstExistingColumn('backup_jobs', ['backup_id','id']);
        $createdAtCol = $this->firstExistingColumn('backup_jobs', ['created_at','timestamp','created_on']);
        $descCol = $this->tableHasColumn('backup_jobs','description') ? 'description' : null;
        $fileTable = $this->tableExists('backup_files');
        $fileNameCol = $fileTable ? $this->firstExistingColumn('backup_files',['file_name','filename','name']) : null;
        $filePathCol = $fileTable ? $this->firstExistingColumn('backup_files',['file_path','path']) : null;
        $bfLinkCol = $fileTable ? $this->firstExistingColumn('backup_files',['backup_id','job_id']) : null;

        // Build selection query for today's hourly backups before cutoff
        $where = [];
        $params = [];
        $join = '';
        if ($descCol) {
            $where[] = "bj.`$descCol` = ?"; $params[] = 'hourly';
        } elseif ($fileTable && $fileNameCol) {
            $join = " LEFT JOIN `backup_files` bf ON bj.`$bjIdCol` = bf.`$bfLinkCol`";
            $where[] = "bf.`$fileNameCol` LIKE ?"; $params[] = 'rotc_hourly_backup_' . $today . '%';
        } else {
            // Without a descriptor, conservatively do nothing
            return ['kept' => 0, 'deleted' => 0];
        }
        if ($createdAtCol) {
            $where[] = "bj.`$createdAtCol` >= ?"; $params[] = $start;
            $where[] = "bj.`$createdAtCol` < ?"; $params[] = $cutoffDt;
            $order = "bj.`$createdAtCol` DESC";
        } else {
            // Fall back to id ordering
            $order = "bj.`$bjIdCol` DESC";
        }
        $selects = ["bj.`$bjIdCol` AS id"]; if ($createdAtCol) $selects[] = "bj.`$createdAtCol` AS created_at";
        if ($fileTable) {
            $selects[] = $filePathCol ? "bf.`$filePathCol` AS file_path" : "NULL AS file_path";
            $selects[] = $fileNameCol ? "bf.`$fileNameCol` AS file_name" : "NULL AS file_name";
        } else {
            $selects[] = "NULL AS file_path"; $selects[] = "NULL AS file_name";
        }
        $sql = "SELECT " . implode(', ', $selects) . " FROM `backup_jobs` bj$join WHERE " . implode(' AND ', $where) . " ORDER BY $order";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) return ['kept' => 0, 'deleted' => 0];

        $kept = 0; $deleted = 0;
        $toKeep = array_slice($rows, 0, max(0,(int)$keepCount));
        $toDelete = array_slice($rows, max(0,(int)$keepCount));

        // Mark kept as daily when description column exists
        if ($descCol && !empty($toKeep)) {
            $ids = array_map(fn($r)=>$r['id'], $toKeep);
            $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));
            $sqlUp = "UPDATE `backup_jobs` SET `$descCol` = 'daily' WHERE `$bjIdCol` IN ($inPlaceholders)";
            $stmtUp = $this->pdo->prepare($sqlUp);
            $stmtUp->execute($ids);
            $kept = count($ids);
        } else {
            $kept = count($toKeep);
        }

        // Delete older hourly backups: files and DB rows
        foreach ($toDelete as $r) {
            $path = $r['file_path'];
            if ((!$path || !file_exists($path)) && !empty($r['file_name'])) {
                $path = $this->backupDir . DIRECTORY_SEPARATOR . $r['file_name'];
            }
            if ($path && file_exists($path)) {
                @unlink($path);
            }
            // Delete DB rows
            if ($fileTable) {
                $sqlDelF = "DELETE FROM `backup_files` WHERE `$bfLinkCol` = ?";
                $stmtF = $this->pdo->prepare($sqlDelF);
                $stmtF->execute([$r['id']]);
            }
            $sqlDelJ = "DELETE FROM `backup_jobs` WHERE `$bjIdCol` = ?";
            $stmtJ = $this->pdo->prepare($sqlDelJ);
            $stmtJ->execute([$r['id']]);
            $deleted++;
        }
        return ['kept' => $kept, 'deleted' => $deleted];
    }

    /**
     * Prune all hourly backups for a specific day (default: today), deleting files and DB rows.
     * This is intended to run shortly after 22:00 once daily backups are taken at 20:30 and 22:00.
     */
    public function pruneHourlyBackups($day = null) {
        if (!$this->tableExists('backup_jobs')) return ['deleted' => 0];
        $day = $day ?: date('Y-m-d');
        $start = $day . ' 00:00:00';
        $end = $day . ' 23:59:59';
        $deleted = 0;
        $bjIdCol = $this->firstExistingColumn('backup_jobs', ['backup_id','id']);
        $createdAtCol = $this->firstExistingColumn('backup_jobs', ['created_at','timestamp','created_on']);
        $descCol = $this->tableHasColumn('backup_jobs','description') ? 'description' : null;

        $fileTable = $this->tableExists('backup_files');
        $fileNameCol = $fileTable ? $this->firstExistingColumn('backup_files',['file_name','filename','name']) : null;
        $filePathCol = $fileTable ? $this->firstExistingColumn('backup_files',['file_path','path']) : null;
        $bfLinkCol = $fileTable ? $this->firstExistingColumn('backup_files',['backup_id','job_id']) : null;

        $selects = ["bj.`$bjIdCol` AS id"]; if ($createdAtCol) $selects[] = "bj.`$createdAtCol` AS created_at";
        if ($fileTable) {
            $selects[] = $filePathCol ? "bf.`$filePathCol` AS file_path" : "NULL AS file_path";
            $selects[] = $fileNameCol ? "bf.`$fileNameCol` AS file_name" : "NULL AS file_name";
        } else {
            $selects[] = "NULL AS file_path"; $selects[] = "NULL AS file_name";
        }
        $join = $fileTable ? " LEFT JOIN `backup_files` bf ON bj.`$bjIdCol` = bf.`$bfLinkCol`" : '';
        $where = []; $params = [];
        if ($descCol) { $where[] = "bj.`$descCol` = ?"; $params[] = 'hourly'; }
        elseif ($fileTable && $fileNameCol) { $where[] = "bf.`$fileNameCol` LIKE ?"; $params[] = 'rotc_hourly_backup_' . $day . '%'; }
        else { return ['deleted' => 0]; }
        if ($createdAtCol) { $where[] = "bj.`$createdAtCol` BETWEEN ? AND ?"; $params[] = $start; $params[] = $end; }
        $sql = "SELECT " . implode(', ', $selects) . " FROM `backup_jobs` bj$join WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) return ['deleted' => 0];

        foreach ($rows as $r) {
            $path = $r['file_path'];
            if ((!$path || !file_exists($path)) && !empty($r['file_name'])) {
                $path = $this->backupDir . DIRECTORY_SEPARATOR . $r['file_name'];
            }
            if ($path && file_exists($path)) { @unlink($path); }
            if ($fileTable) { $delF = $this->pdo->prepare("DELETE FROM `backup_files` WHERE `$bfLinkCol` = ?"); $delF->execute([$r['id']]); }
            $delJ = $this->pdo->prepare("DELETE FROM `backup_jobs` WHERE `$bjIdCol` = ?"); $delJ->execute([$r['id']]);
            $deleted++;
        }
        return ['deleted' => $deleted];
    }

    private function tableHasColumn($table, $column) {
        try {
            $this->pdo->query("SELECT `{$column}` FROM `{$table}` LIMIT 0");
            return true;
        } catch (Throwable $e) {
            try {
                $stmt = $this->pdo->query('SELECT DATABASE()');
                $db = $stmt ? $stmt->fetchColumn() : null;
                if (!$db) return false;
                $q = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
                $q->execute([$db, $table, $column]);
                return (int)$q->fetchColumn() > 0;
            } catch (Throwable $e2) {
                return false;
            }
        }
    }

    private function firstExistingColumn($table, array $candidates) {
        foreach ($candidates as $c) {
            if ($this->tableHasColumn($table, $c)) return $c;
        }
        return null;
    }

    // Compute next numeric ID for a primary key column even if the column is CHAR/VARCHAR.
    // Falls back to a large random integer if the table is empty or query fails.
    private function nextNumericId($table, $pkCol) {
        try {
            $sql = "SELECT COALESCE(MAX(CAST(`$pkCol` AS UNSIGNED)), 0) FROM `$table`";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $max = (int)$stmt->fetchColumn();
            $next = $max + 1;
            if ($next <= 0) { $next = random_int(100000, 2000000000); }
            return $next;
        } catch (Throwable $e) {
            return random_int(100000, 2000000000);
        }
    }
    
    private function isAutoIncrement($table, $column) {
        try {
            $stmt = $this->pdo->query('SELECT DATABASE()');
            $db = $stmt ? $stmt->fetchColumn() : null;
            if (!$db) return false;
            $q = $this->pdo->prepare('SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $q->execute([$db, $table, $column]);
            $extra = $q->fetchColumn();
            return $extra && stripos($extra, 'auto_increment') !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function getColumnMeta($table, $column) {
        try {
            $stmt = $this->pdo->query('SELECT DATABASE()');
            $db = $stmt ? $stmt->fetchColumn() : null;
            if (!$db) return null;
            $q = $this->pdo->prepare('SELECT DATA_TYPE, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $q->execute([$db, $table, $column]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function parseEnumValues($columnType) {
        // COLUMN_TYPE like: enum('manual','automated')
        $values = [];
        if (stripos($columnType, 'enum(') === 0) {
            $inside = substr($columnType, 5, -1);
            $parts = preg_split("/','/", trim($inside, "'"));
            foreach ($parts as $p) { $values[] = $p; }
        }
        return $values;
    }

    private function coerceBackupTypeToColumn($type, $meta) {
        $t = strtolower((string)$type);
        if (!$meta) return $t;
        $dataType = strtolower((string)($meta['DATA_TYPE'] ?? ''));
        $colType = strtolower((string)($meta['COLUMN_TYPE'] ?? ''));
        $maxLen = (int)($meta['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
        // Prefer canonical tokens
        $canonical = ($t === 'manual' || $t === 'automated') ? $t : ($t ? $t : 'manual');

        // Enum: choose matching token if allowed, otherwise first allowed
        if (strpos($colType, 'enum(') === 0) {
            $allowed = $this->parseEnumValues($meta['COLUMN_TYPE']);
            if (in_array($canonical, $allowed, true)) return $canonical;
            if (in_array('manual', $allowed, true)) return 'manual';
            if (in_array('automated', $allowed, true)) return 'automated';
            return $allowed[0] ?? $canonical;
        }

        // Character/text types: ensure within length; abbreviate if needed
        if (in_array($dataType, ['char','varchar','text','tinytext','mediumtext','longtext'], true)) {
            $val = $canonical;
            if ($maxLen > 0 && strlen($val) > $maxLen) {
                // Use abbreviations first
                $abbr = ($canonical === 'manual') ? 'M' : (($canonical === 'automated') ? 'A' : substr($val, 0, 1));
                if ($maxLen >= strlen($abbr)) return $abbr;
                return substr($val, 0, $maxLen);
            }
            return $val;
        }

        // Numeric types: map to small integers
        if (in_array($dataType, ['tinyint','smallint','mediumint','int','integer','bigint','decimal','numeric'], true)) {
            // Manual = 1, Automated = 2
            return ($canonical === 'automated') ? 2 : 1;
        }

        // Default: return as-is
        return $canonical;
    }

    private function coerceStatusToColumn($status, $meta) {
        $s = strtolower((string)$status);
        if (!$meta) return $s;
        $dataType = strtolower((string)($meta['DATA_TYPE'] ?? ''));
        $colType = strtolower((string)($meta['COLUMN_TYPE'] ?? ''));
        $maxLen = (int)($meta['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
        // Canonical choices: running, completed, failed
        $canonical = in_array($s, ['running','completed','failed'], true) ? $s : ($s ?: 'running');
        // Enum handling: map to allowed synonyms
        if (strpos($colType, 'enum(') === 0) {
            $allowed = $this->parseEnumValues($meta['COLUMN_TYPE']);
            // Synonym maps
            $syn = [
                'running'   => ['running','in_progress','processing','pending','queued','started'],
                'completed' => ['completed','success','successful','done','finished','ok'],
                'failed'    => ['failed','error','failure','failed_with_error','fail']
            ];
            // Try canonical then synonyms
            $try = array_merge([$canonical], $syn[$canonical]);
            foreach ($try as $opt) {
                if (in_array($opt, $allowed, true)) return $opt;
            }
            // As last resort, pick first allowed
            return $allowed[0] ?? $canonical;
        }
        // Character types
        if (in_array($dataType, ['char','varchar','text','tinytext','mediumtext','longtext'], true)) {
            $val = $canonical;
            if ($maxLen > 0 && strlen($val) > $maxLen) {
                // Abbreviate
                $abbr = $canonical === 'running' ? 'R' : ($canonical === 'completed' ? 'C' : 'F');
                if ($maxLen >= strlen($abbr)) return $abbr;
                return substr($val, 0, $maxLen);
            }
            return $val;
        }
        // Numeric types: running=2, completed=1, failed=0
        if (in_array($dataType, ['tinyint','smallint','mediumint','int','integer','bigint','decimal','numeric'], true)) {
            return $canonical === 'running' ? 2 : ($canonical === 'completed' ? 1 : 0);
        }
        return $canonical;
    }
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->logger = new SecurityLogger();
        $this->backupDir = dirname(__DIR__) . '/backups';
        $this->encryptionKey = $this->getEncryptionKey();
        
        // Create backup directory if it doesn't exist
        if (!file_exists($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Perform automated daily backup
     */
    public function performDailyBackup($encrypt = false) {
        try {
            $backupId = $this->createBackupJob('automated', 'daily');
            
            // Generate backup filename with timestamp
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "rotc_backup_{$timestamp}.sql";
            $filepath = $this->backupDir . '/' . $filename;
            
            // Create database dump
            $this->createDatabaseDump($filepath);
            // Optionally encrypt (default false => keep plain .sql)
            $finalFile = $encrypt ? $this->encryptBackupFile($filepath) : $filepath;
            
            // Calculate checksum
            $checksum = hash_file('sha256', $finalFile);
            $fileSize = filesize($finalFile);
            
            // Update backup job status
            $this->updateBackupJob($backupId, 'completed', $finalFile, $fileSize, $checksum);
            
            // Log successful backup
            $this->logger->logSecurityEvent(
                null,
                'backup_completed',
                'Automated daily backup completed successfully',
                ['backup_id' => $backupId, 'file_size' => $fileSize]
            );
            
            // Clean up old backups (keep last 30 days)
            $this->cleanupOldBackups();
            
            return $backupId;
            
        } catch (Exception $e) {
            $this->logger->logSecurityEvent(
                null,
                'backup_failed',
                'Automated backup failed: ' . $e->getMessage(),
                ['error' => $e->getMessage()],
                'high'
            );
            
            if (isset($backupId)) {
                $this->updateBackupJob($backupId, 'failed', null, 0, null, $e->getMessage());
            }
            
            throw $e;
        }
    }
    
    /**
     * Create manual backup
     */
    public function createManualBackup($userId, $description = '', $encrypt = false) {
        try {
            $backupId = $this->createBackupJob('manual', $description, $userId);
            
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "rotc_manual_backup_{$timestamp}.sql";
            $filepath = $this->backupDir . '/' . $filename;
            
            $this->createDatabaseDump($filepath);
            $finalFile = $encrypt ? $this->encryptBackupFile($filepath) : $filepath;
            
            $checksum = hash_file('sha256', $finalFile);
            $fileSize = filesize($finalFile);
            
            $this->updateBackupJob($backupId, 'completed', $finalFile, $fileSize, $checksum);
            
            $this->logger->logSecurityEvent(
                $userId,
                'manual_backup_created',
                'Manual backup created by user',
                ['backup_id' => $backupId, 'description' => $description, 'encrypted' => $encrypt]
            );
            
            return $backupId;
            
        } catch (Exception $e) {
            $this->logger->logSecurityEvent(
                $userId,
                'backup_failed',
                'Manual backup failed: ' . $e->getMessage(),
                ['error' => $e->getMessage()],
                'high'
            );
            
            if (isset($backupId)) {
                $this->updateBackupJob($backupId, 'failed', null, 0, null, $e->getMessage());
            }
            
            throw $e;
        }
    }
    
    /**
     * Create database dump using mysqldump
     */
    private function createDatabaseDump($filepath) {
        $hostValue = DB_SERVER;
        $username = DB_USERNAME;
        $password = DB_PASSWORD;
        $database = DB_NAME;
        // Split host:port if present
        $host = $hostValue; $port = null;
        if (strpos($hostValue, ':') !== false) {
            [$hOnly, $pPart] = explode(':', $hostValue, 2);
            if ($hOnly !== '') { $host = $hOnly; }
            if ($pPart !== '') { $port = $pPart; }
        }
        
        // Determine mysqldump path (XAMPP default or PATH fallback)
        $dumpExe = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        if (!file_exists($dumpExe)) {
            $dumpExe = 'mysqldump';
        }
        
        // Create a temporary defaults file to avoid password quoting issues
        $defaultsContent = "[client]\n";
        $defaultsContent .= "user={$username}\n";
        if ($password !== '') { $defaultsContent .= "password={$password}\n"; }
        $defaultsContent .= "host=" . ($host ?: 'localhost') . "\n";
        if ($port) { $defaultsContent .= "port={$port}\n"; }
        $defaultsContent .= "default-character-set=utf8mb4\n";
        $defaultsFile = $this->backupDir . DIRECTORY_SEPARATOR . ('dump_' . uniqid() . '.cnf');
        file_put_contents($defaultsFile, $defaultsContent);

        // Build command with explicit options and result-file to avoid shell redirection
        $cmd = [];
        $cmd[] = escapeshellarg($dumpExe);
        // Per MySQL docs, defaults-extra-file should be the first option
        $cmd[] = '--defaults-extra-file=' . escapeshellarg($defaultsFile);
        $cmd[] = '--single-transaction';
        $cmd[] = '--quick';
        $cmd[] = '--routines';
        $cmd[] = '--events';
        $cmd[] = '--default-character-set=utf8mb4';
        $cmd[] = '--result-file=' . escapeshellarg($filepath);
        $cmd[] = escapeshellarg($database);
        $command = implode(' ', $cmd) . ' 2>&1';
        
        $output = [];
        exec($command, $output, $returnCode);
        if ($returnCode !== 0) {
            // Retry with 127.0.0.1 if host was localhost in defaults file
            if (strtolower($host) === 'localhost') {
                // Overwrite defaults file host and retry
                $defaultsContent = preg_replace('/^host=.*$/m', 'host=127.0.0.1', $defaultsContent);
                file_put_contents($defaultsFile, $defaultsContent);
                $output = [];
                exec($command, $output, $returnCode);
            }
        }
        if ($returnCode !== 0) {
            // Analyze stderr and attempt privilege/compatibility fallbacks
            $stderr = implode("\n", $output);
            $attemptedFallback = false;
            // Toggle password if access denied
            if (preg_match('/Access denied/i', $stderr)) {
                // Remove password line and retry (XAMPP often uses empty root password)
                $defaultsContentNoPass = preg_replace('/^password=.*$/mi', '', $defaultsContent);
                if ($defaultsContentNoPass !== $defaultsContent) {
                    file_put_contents($defaultsFile, $defaultsContentNoPass);
                    $output = [];
                    exec($command, $output, $returnCode);
                    $attemptedFallback = true;
                    if ($returnCode === 0) { $stderr = ''; }
                }
            }
            if ($returnCode !== 0) {
            // Remove routines/events if privileges are insufficient
            if (preg_match('/(Access denied|denied).*proc|PROCESS privilege|routine|event/i', $stderr)) {
                $cmdNoRE = array_values(array_filter($cmd, function($p){ return $p !== '--routines' && $p !== '--events'; }));
                $commandNoRE = implode(' ', $cmdNoRE) . ' 2>&1';
                $output = [];
                exec($commandNoRE, $output, $returnCode);
                $attemptedFallback = true;
                if ($returnCode === 0) { $command = $commandNoRE; }
            }
            // Add --no-tablespaces for INFORMATION_SCHEMA INNODB_TABLESPACES issues (older MariaDB)
            if ($returnCode !== 0 && preg_match('/INNODB.*TABLESPACES|INFORMATION_SCHEMA\.INNODB_TABLESPACES/i', $stderr)) {
                $cmdNoTS = $cmd;
                $cmdNoTS[] = '--no-tablespaces';
                $commandNoTS = implode(' ', $cmdNoTS) . ' 2>&1';
                $output = [];
                exec($commandNoTS, $output, $returnCode);
                $attemptedFallback = true;
                if ($returnCode === 0) { $command = $commandNoTS; }
            }
            }
            if ($returnCode !== 0) {
                // Sanitize password in command for error
                $safeCmd = preg_replace('/--password=\S+/', '--password=***', $command);
                $err = !empty($output) ? ("\n" . implode("\n", $output)) : '';
                // Cleanup defaults file before throwing
                @unlink($defaultsFile);
                throw new Exception('Database dump failed with return code: ' . $returnCode . "\nCommand: " . $safeCmd . ($attemptedFallback ? "\n(Fallbacks attempted)" : '') . $err);
            }
        }
        if (!file_exists($filepath) || filesize($filepath) === 0) {
            throw new Exception('Database dump file was not created or is empty');
        }
        // Cleanup defaults file
        @unlink($defaultsFile);
    }
    
    /**
     * Encrypt backup file using AES-256-CBC
     */
    private function encryptBackupFile($filepath) {
        $data = file_get_contents($filepath);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        
        $encryptedFile = $filepath . '.enc';
        file_put_contents($encryptedFile, base64_encode($iv . $encrypted));
        
        // Remove unencrypted file
        unlink($filepath);
        
        return $encryptedFile;
    }
    
    /**
     * Decrypt backup file
     */
    public function decryptBackupFile($encryptedFilepath, $outputPath) {
        $encryptedData = base64_decode(file_get_contents($encryptedFilepath));
        $iv = substr($encryptedData, 0, 16);
        $encrypted = substr($encryptedData, 16);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        
        if ($decrypted === false) {
            throw new Exception('Failed to decrypt backup file');
        }
        
        file_put_contents($outputPath, $decrypted);
        return $outputPath;
    }
    
    /**
     * Get or generate encryption key
     */
    private function getEncryptionKey() {
        // Prefer DB storage if available
        if ($this->tableExists('security_settings')) {
            try {
                $stmt = $this->pdo->prepare("SELECT setting_value FROM security_settings WHERE setting_key = 'backup_encryption_key'");
                $stmt->execute();
                $result = $stmt->fetch();
                if ($result && isset($result['setting_value'])) {
                    return base64_decode($result['setting_value']);
                }
                // Generate new encryption key, store to DB
                $key = random_bytes(32);
                $encodedKey = base64_encode($key);
                $cols = ['setting_key','setting_value'];
                $vals = ['backup_encryption_key', $encodedKey];
                if ($this->tableHasColumn('security_settings','description')) {
                    $cols[] = 'description';
                    $vals[] = 'AES-256 encryption key for backup files';
                }
                $ph = implode(', ', array_fill(0, count($cols), '?'));
                $colList = implode(', ', array_map(fn($c)=>"`$c`", $cols));
                $stmt = $this->pdo->prepare("INSERT INTO security_settings ($colList) VALUES ($ph)");
                $stmt->execute($vals);
                return $key;
            } catch (Throwable $e) {
                // fall through to file storage
            }
        }
        // File-based fallback
        $keyFile = $this->backupDir . '/encryption.key';
        if (file_exists($keyFile)) {
            $data = @file_get_contents($keyFile);
            if ($data !== false) {
                $bin = base64_decode($data, true);
                if ($bin !== false && strlen($bin) === 32) return $bin;
            }
        }
        $key = random_bytes(32);
        @file_put_contents($keyFile, base64_encode($key));
        return $key;
    }
    
    /**
     * Create backup job record
     */
    private function createBackupJob($type, $description, $userId = null) {
        if (!$this->tableExists('backup_jobs')) {
            // If logging table is missing, proceed without DB record
            return null;
        }
        $cols = [];
        $vals = [];
        // Ensure primary key presence if required (non-AI schemas)
        $pkCol = $this->firstExistingColumn('backup_jobs', ['backup_id','id']);
        $generatedId = null;
        if ($pkCol && !$this->isAutoIncrement('backup_jobs', $pkCol)) {
            // Generate next id based on current max to satisfy NOT NULL without default
            $generatedId = $this->nextNumericId('backup_jobs', $pkCol);
            $cols[] = $pkCol; $vals[] = $generatedId;
        }
        // backup type column (with synonyms) with coercion to schema
        $btCol = $this->firstExistingColumn('backup_jobs', ['backup_type','type','job_type']);
        if ($btCol) {
            $meta = $this->getColumnMeta('backup_jobs', $btCol);
            $btVal = $this->coerceBackupTypeToColumn($type, $meta);
            $cols[] = $btCol; $vals[] = $btVal;
        }
        // status column (coerced)
        $statusCol = null;
        if ($this->tableHasColumn('backup_jobs','status')) { 
            $statusCol = 'status';
            $meta = $this->getColumnMeta('backup_jobs', 'status');
            $cols[] = 'status'; 
            $vals[] = $this->coerceStatusToColumn('running', $meta);
        }
        // description
        if ($this->tableHasColumn('backup_jobs','description')) { $cols[] = 'description'; $vals[] = $description; }
        // created_by: only include when we have a value or when NOT NULL without default (choose safe fallback)
        if ($this->tableHasColumn('backup_jobs','created_by')) {
            if ($userId !== null) {
                $cols[] = 'created_by';
                $vals[] = (int)$userId;
            } else {
                $metaCB = $this->getColumnMeta('backup_jobs','created_by');
                $isNullable = strtoupper((string)($metaCB['IS_NULLABLE'] ?? '')) === 'YES';
                $hasDefault = array_key_exists('COLUMN_DEFAULT', (array)$metaCB) && $metaCB['COLUMN_DEFAULT'] !== null;
                if (!$isNullable && !$hasDefault) {
                    // Find a safe fallback user id
                    $fallback = null;
                    try {
                        if ($this->tableExists('users')) {
                            // Prefer admin if roles exist
                            try {
                                if ($this->tableHasColumn('users','role')) {
                                    $stmt = $this->pdo->query("SELECT id FROM users WHERE role IN ('admin','administrator') ORDER BY id ASC LIMIT 1");
                                    $fallback = $stmt ? $stmt->fetchColumn() : null;
                                }
                            } catch (Throwable $e) { /* ignore */ }
                            if ($fallback === null) {
                                $stmt = $this->pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1');
                                $fallback = $stmt ? $stmt->fetchColumn() : null;
                            }
                        }
                    } catch (Throwable $e) { /* ignore */ }
                    if ($fallback === null) { $fallback = 1; }
                    $cols[] = 'created_by';
                    $vals[] = (int)$fallback;
                }
                // else: nullable or has default -> omit column to let DB handle
            }
        }
        // backup_config
        if ($this->tableHasColumn('backup_jobs','backup_config')) {
            $cols[] = 'backup_config';
            $vals[] = json_encode(['encryption' => true, 'compression' => false, 'tables' => ['all']]);
        }
        // If nothing but backup_type, still insert
        $ph = implode(', ', array_fill(0, count($cols), '?'));
        $colList = implode(', ', array_map(fn($c)=>"`$c`", $cols));
        $sql = "INSERT INTO `backup_jobs` ($colList) VALUES ($ph)";
        $stmt = $this->pdo->prepare($sql);
        // Track pk index for duplicates handling
        $pkIndex = null;
        if ($pkCol) { for ($i=0; $i<count($cols); $i++) { if ($cols[$i] === $pkCol) { $pkIndex = $i; break; } } }
        try {
            $stmt->execute($vals);
        } catch (PDOException $e) {
            // If backup_type caused truncation, retry with alternative encodings
            $errMsg = $e->getMessage();
            $btIndex = null;
            for ($i = 0; $i < count($cols); $i++) { if ($cols[$i] === $btCol) { $btIndex = $i; break; } }
            if ($btIndex !== null && (stripos($errMsg, "Data truncated for column") !== false) && stripos($errMsg, $btCol) !== false) {
                $current = strtolower((string)$vals[$btIndex]);
                $candidates = [];
                // If we had enum meta, try first allowed
                $meta = $this->getColumnMeta('backup_jobs', $btCol);
                if ($meta && strpos(strtolower((string)$meta['COLUMN_TYPE']), 'enum(') === 0) {
                    $allowed = $this->parseEnumValues($meta['COLUMN_TYPE']);
                    foreach ($allowed as $a) { $candidates[] = $a; }
                }
                // Add abbreviations and numeric mapping as generic fallbacks
                if ($current === 'automated' || $current === '2') { $candidates[] = 'A'; $candidates[] = 2; }
                if ($current === 'manual' || $current === '1') { $candidates[] = 'M'; $candidates[] = 1; }
                // Always try canonical tokens too (maybe case-sensitive enum)
                $candidates[] = 'manual';
                $candidates[] = 'automated';
                // Deduplicate while preserving order
                $seen = [];
                $candidates = array_values(array_filter($candidates, function($v) use (&$seen) { $k = json_encode($v); if (isset($seen[$k])) return false; $seen[$k] = true; return true; }));
                foreach ($candidates as $cand) {
                    try {
                        $vals[$btIndex] = $cand;
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->execute($vals);
                        // success
                        if ($generatedId !== null) return $generatedId;
                        return $this->pdo->lastInsertId();
                    } catch (PDOException $e2) {
                        // continue trying
                    }
                }
            }
            // If duplicate primary key occurred, recompute next id numerically and retry a few times
            if ((strpos($errMsg, 'Duplicate entry') !== false || (int)$e->getCode() === 23000) && $pkIndex !== null) {
                for ($retry = 0; $retry < 3; $retry++) {
                    $vals[$pkIndex] = $this->nextNumericId('backup_jobs', $pkCol);
                    try {
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->execute($vals);
                        if ($generatedId !== null) return $vals[$pkIndex];
                        return $this->pdo->lastInsertId();
                    } catch (PDOException $eDup) {
                        if (strpos($eDup->getMessage(), 'Duplicate entry') === false && (int)$eDup->getCode() !== 23000) {
                            throw $eDup;
                        }
                        // else continue retrying
                    }
                }
            }
            // If status caused truncation, retry with alternatives
            $stIndex = null;
            if ($statusCol) {
                for ($i = 0; $i < count($cols); $i++) { if ($cols[$i] === $statusCol) { $stIndex = $i; break; } }
            }
            if ($stIndex !== null && (stripos($errMsg, "Data truncated for column") !== false) && stripos($errMsg, $statusCol) !== false) {
                $current = strtolower((string)$vals[$stIndex]);
                $candidates = [];
                $metaS = $this->getColumnMeta('backup_jobs', $statusCol);
                if ($metaS && strpos(strtolower((string)$metaS['COLUMN_TYPE']), 'enum(') === 0) {
                    $allowed = $this->parseEnumValues($metaS['COLUMN_TYPE']);
                    foreach ($allowed as $a) { $candidates[] = $a; }
                }
                // Abbreviations and numeric mapping
                if (!in_array('R', $candidates, true)) $candidates[] = 'R';
                if (!in_array('C', $candidates, true)) $candidates[] = 'C';
                if (!in_array('F', $candidates, true)) $candidates[] = 'F';
                $candidates[] = 2; $candidates[] = 1; $candidates[] = 0;
                // Canonical tokens for case-sensitive enums
                $candidates[] = 'running'; $candidates[] = 'completed'; $candidates[] = 'failed';
                // Deduplicate
                $seen = [];
                $candidates = array_values(array_filter($candidates, function($v) use (&$seen) { $k = json_encode($v); if (isset($seen[$k])) return false; $seen[$k] = true; return true; }));
                foreach ($candidates as $cand) {
                    try {
                        $vals[$stIndex] = $cand;
                        $stmt = $this->pdo->prepare($sql);
                        $stmt->execute($vals);
                        if ($generatedId !== null) return $generatedId;
                        return $this->pdo->lastInsertId();
                    } catch (PDOException $e3) {
                        // continue trying
                    }
                }
            }
            throw $e; // rethrow if we couldn't resolve
        }
        if ($generatedId !== null) {
            return $generatedId;
        }
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Update backup job status
     */
    private function updateBackupJob($backupId, $status, $filepath = null, $fileSize = 0, $checksum = null, $errorMessage = null) {
        if ($backupId && $this->tableExists('backup_jobs')) {
            $sets = [];
            $vals = [];
            if ($this->tableHasColumn('backup_jobs','status')) { 
                $meta = $this->getColumnMeta('backup_jobs', 'status');
                $sets[] = "`status` = ?"; 
                $vals[] = $this->coerceStatusToColumn($status, $meta); 
            }
            if ($this->tableHasColumn('backup_jobs','error_message')) { $sets[] = "`error_message` = ?"; $vals[] = $errorMessage; }
            if ($this->tableHasColumn('backup_jobs','completed_at')) { $sets[] = "`completed_at` = NOW()"; }
            if (!empty($sets)) {
                $idCol = $this->firstExistingColumn('backup_jobs', ['backup_id','id']);
                $sql = "UPDATE `backup_jobs` SET " . implode(', ', $sets) . " WHERE `$idCol` = ?";
                $vals[] = $backupId;
                $stmt = $this->pdo->prepare($sql);
                try {
                    $stmt->execute($vals);
                } catch (PDOException $e) {
                    $errMsg = $e->getMessage();
                    // Find status parameter index if present
                    $stIndex = null;
                    for ($i = 0; $i < count($sets); $i++) {
                        if (stripos($sets[$i], '`status` = ?') !== false) { $stIndex = $i; break; }
                    }
                    if ($stIndex !== null && (stripos($errMsg, 'Data truncated for column') !== false) && stripos($errMsg, 'status') !== false) {
                        $metaS = $this->getColumnMeta('backup_jobs', 'status');
                        $candidates = [];
                        if ($metaS && strpos(strtolower((string)$metaS['COLUMN_TYPE']), 'enum(') === 0) {
                            $allowed = $this->parseEnumValues($metaS['COLUMN_TYPE']);
                            foreach ($allowed as $a) { $candidates[] = $a; }
                        }
                        // Abbreviations and numeric mapping
                        $candidates[] = 'R'; $candidates[] = 'C'; $candidates[] = 'F';
                        $candidates[] = 2; $candidates[] = 1; $candidates[] = 0;
                        // Canonical tokens
                        $candidates[] = 'running'; $candidates[] = 'completed'; $candidates[] = 'failed';
                        // Deduplicate
                        $seen = [];
                        $candidates = array_values(array_filter($candidates, function($v) use (&$seen) { $k = json_encode($v); if (isset($seen[$k])) return false; $seen[$k] = true; return true; }));
                        foreach ($candidates as $cand) {
                            try {
                                $vals[$stIndex] = $cand;
                                $stmt = $this->pdo->prepare($sql);
                                $stmt->execute($vals);
                                // success
                                $errMsg = null;
                                break;
                            } catch (PDOException $e2) {
                                $errMsg = $e2->getMessage();
                                continue;
                            }
                        }
                        if ($errMsg) { throw new PDOException($errMsg); }
                    } else {
                        throw $e;
                    }
                }
            }
        }
        
        if ($filepath && $status === 'completed' && $this->tableExists('backup_files')) {
            $filename = basename($filepath);
            $idLinkCol = $this->firstExistingColumn('backup_files', ['backup_id','job_id']);
            $cols = [$idLinkCol ?: 'backup_id'];
            $vals = [$backupId];
            if ($this->tableHasColumn('backup_files','file_path')) { $cols[] = 'file_path'; $vals[] = $filepath; }
            elseif ($this->tableHasColumn('backup_files','path')) { $cols[] = 'path'; $vals[] = $filepath; }
            if ($this->tableHasColumn('backup_files','file_name')) { $cols[] = 'file_name'; $vals[] = $filename; }
            if ($this->tableHasColumn('backup_files','file_size')) { $cols[] = 'file_size'; $vals[] = $fileSize; }
            elseif ($this->tableHasColumn('backup_files','size')) { $cols[] = 'size'; $vals[] = $fileSize; }
            if ($this->tableHasColumn('backup_files','checksum')) { $cols[] = 'checksum'; $vals[] = $checksum; }
            elseif ($this->tableHasColumn('backup_files','hash')) { $cols[] = 'hash'; $vals[] = $checksum; }
            $ph = implode(', ', array_fill(0, count($cols), '?'));
            $colList = implode(', ', array_map(fn($c)=>"`$c`", $cols));
            $sql = "INSERT INTO `backup_files` ($colList) VALUES ($ph)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($vals);
        }
    }
    
    /**
     * Clean up old backup files (keep last 30 days)
     */
    private function cleanupOldBackups() {
        // Only attempt cleanup if tables exist
        if (!$this->tableExists('backup_jobs')) return;
        $createdAtCol = $this->firstExistingColumn('backup_jobs', ['created_at','timestamp','created_on']);
        if (!$createdAtCol) return; // Can't safely clean without date column
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        // Get old backup files
        if ($this->tableExists('backup_files')) {
            $filePathCol = $this->firstExistingColumn('backup_files', ['file_path','path']);
            if ($filePathCol) {
                $bjIdCol = $this->firstExistingColumn('backup_jobs', ['backup_id','id']);
                $bfLinkCol = $this->firstExistingColumn('backup_files', ['backup_id','job_id']);
                $sql = "
                    SELECT bf.`$filePathCol` 
                    FROM `backup_files` bf
                    JOIN `backup_jobs` bj ON bf.`$bfLinkCol` = bj.`$bjIdCol`
                    WHERE bj.`$createdAtCol` < ?
                ";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$cutoffDate]);
                $oldFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($oldFiles as $filepath) {
                    if ($filepath && file_exists($filepath)) {
                        @unlink($filepath);
                    }
                }
            }
        }
        
        // Delete database records
        $sqlDel = "DELETE FROM `backup_jobs` WHERE `$createdAtCol` < ?";
        $stmt = $this->pdo->prepare($sqlDel);
        $stmt->execute([$cutoffDate]);
    }
    
    /**
     * Get backup history
     */
    public function getBackupHistory($limit = 50) {
        if (!$this->tableExists('backup_jobs')) return [];
        // MySQL/MariaDB with native prepares don't accept bound params for LIMIT.
        $limit = (int)$limit; if ($limit <= 0) $limit = 50; if ($limit > 500) $limit = 500;
        $createdAtCol = $this->firstExistingColumn('backup_jobs', ['created_at','timestamp','created_on','backup_id']);
        $bjIdCol = $this->firstExistingColumn('backup_jobs', ['backup_id','id']);
        $orderBy = $createdAtCol ? "bj.`$createdAtCol` DESC" : "bj.`$bjIdCol` DESC";
        $selects = ["bj.*"];
        $join = '';
        // Optional join to backup_files
        if ($this->tableExists('backup_files')) {
            $fileNameCol = $this->firstExistingColumn('backup_files',['file_name','filename','name']);
            $fileSizeCol = $this->firstExistingColumn('backup_files',['file_size','size']);
            $checksumCol = $this->firstExistingColumn('backup_files',['checksum','hash']);
            $filePathCol = $this->firstExistingColumn('backup_files',['file_path','path']);
            $selects[] = $fileNameCol ? "bf.`$fileNameCol` AS file_name" : "NULL AS file_name";
            $selects[] = $fileSizeCol ? "bf.`$fileSizeCol` AS file_size" : "NULL AS file_size";
            $selects[] = $checksumCol ? "bf.`$checksumCol` AS checksum" : "NULL AS checksum";
            $selects[] = $filePathCol ? "bf.`$filePathCol` AS file_path" : "NULL AS file_path";
            $bfLinkCol = $this->firstExistingColumn('backup_files',['backup_id','job_id']);
            $join .= " LEFT JOIN `backup_files` bf ON bj.`$bjIdCol` = bf.`$bfLinkCol`";
        } else {
            $selects[] = "NULL AS file_name";
            $selects[] = "NULL AS file_size";
            $selects[] = "NULL AS checksum";
            $selects[] = "NULL AS file_path";
        }
        // Optional join to users
        if ($this->tableHasColumn('backup_jobs','created_by') && $this->tableExists('users') && $this->tableHasColumn('users','id')) {
            $selects[] = "u.username AS created_by_username";
            $join .= " LEFT JOIN `users` u ON bj.created_by = u.id";
        } else {
            $selects[] = "NULL AS created_by_username";
        }
        $sql = "SELECT " . implode(', ', $selects) . " FROM `backup_jobs` bj$join ORDER BY $orderBy LIMIT $limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Schedule daily backup (to be called by cron or task scheduler)
     */
    public static function scheduleDailyBackup() {
        $backup = new self();
        return $backup->performDailyBackup();
    }
}
?>