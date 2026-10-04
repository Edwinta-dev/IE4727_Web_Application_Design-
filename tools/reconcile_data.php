<?php

declare(strict_types=1);

// CLI only. JSON files are private tooling artifacts, never application transport.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$action = $argv[1] ?? '';
$options = [];
foreach (array_slice($argv, 2) as $argument) {
    if (!preg_match('/^--(database|manifest|targets|token|backup)=(.+)$/D', $argument, $match) || isset($options[$match[1]])) {
        fwrite(STDERR, "Invalid or duplicate option.\n"); exit(2);
    }
    $options[$match[1]] = $match[2];
}
$database = $options['database'] ?? '';
if (!in_array($action, ['inventory', 'preview', 'apply', 'restore'], true)
    || !in_array($database, ['ie4727db_test', 'ie4727db'], true)
    || (in_array($action, ['apply', 'restore'], true) && $database !== 'ie4727db_test')
    || (getenv('CLINIC_DB_NAME') && getenv('CLINIC_DB_NAME') !== $database)) {
    fwrite(STDERR, "Refusing before connection. Usage: reconcile_data.php inventory|preview|apply|restore --database=ie4727db_test [--manifest=private.json --targets=doctor:ID,patient:ID --token=preview-token --backup=UIPROBLEMS/private/backup.json]. Live supports read-only inventory/preview only.\n"); exit(2);
}
putenv('CLINIC_DB_NAME=' . $database);
$connection = null;
try {
    require_once dirname(__DIR__) . '/clinic-base/models/data_reconciliation.php';
    if (DB_NAME !== $database) throw new RuntimeException('Config mismatch.');
    $read = static function (string $path): array {
        $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new RuntimeException('Expected JSON object.');
        return $value;
    };
    $connection = db();
    reconciliation_begin($database, in_array($action, ['inventory', 'preview'], true));
    $rows = reconciliation_snapshot(in_array($action, ['apply', 'restore'], true));
    $manifest = isset($options['manifest']) ? $read($options['manifest']) : [];
    if ($action === 'inventory') $result = reconciliation_inventory($rows, $manifest);
    elseif ($action === 'restore') {
        $backup = $read($options['backup'] ?? '');
        if (($backup['database'] ?? '') !== 'ie4727db_test' || !isset($backup['rows'], $backup['token'])
            || ($options['token'] ?? '') !== $backup['token']) throw new RuntimeException('Backup confirmation mismatch.');
        $hashes = [];
        foreach ($backup['rows'] as $ref => $row) {
            if (isset($rows[$ref]) || !preg_match('/^(doctor|patient|slots|appointment|notifications):[1-9][0-9]*$/D', $ref, $m)
                || (string) ($row[RECONCILE_KEYS[$m[1]]] ?? '') !== explode(':', $ref)[1]) throw new RuntimeException('Restore ID conflict.');
            $hashes[$ref] = reconciliation_fingerprint($row);
        }
        ksort($hashes);
        if (hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR)) !== $backup['token']) throw new RuntimeException('Backup integrity mismatch.');
        reconciliation_restore($backup['rows']);
        $result = ['restored' => count($backup['rows'])];
    } else {
        $targets = explode(',', $options['targets'] ?? '');
        $result = reconciliation_plan($rows, $manifest, $targets);
        if ($action === 'apply') {
            if (!$result['allowed'] || ($options['token'] ?? '') !== $result['token']) throw new RuntimeException('Ambiguous or changed plan.');
            $path = $options['backup'] ?? '';
            $privateRoot = realpath(dirname(__DIR__) . '/UIPROBLEMS');
            $parent = realpath(dirname($path));
            if (!$privateRoot || !$parent || !str_starts_with(strtolower($parent . DIRECTORY_SEPARATOR), strtolower($privateRoot . DIRECTORY_SEPARATOR))) throw new RuntimeException('Backup must be inside existing gitignored UIPROBLEMS directory.');
            $saved = array_intersect_key($rows, array_fill_keys($result['references'], true));
            $file = fopen($path, 'x'); // Never overwrite a previous backup.
            if (!$file) throw new RuntimeException('Backup unavailable.');
            try {
                $json = json_encode(['database' => $database, 'token' => $result['token'], 'rows' => $saved], JSON_THROW_ON_ERROR);
                if (fwrite($file, $json) !== strlen($json) || !fflush($file)) throw new RuntimeException('Incomplete backup.');
            } finally { fclose($file); }
            reconciliation_delete($result);
        }
    }
    db()->commit();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    if ($connection instanceof PDO && $connection->inTransaction()) $connection->rollBack();
    // Raw exception messages could contain personal values or local credentials.
    fwrite(STDERR, "Reconciliation refused or failed; check target, provenance, dependencies, token, backup and local configuration. No transaction was committed.\n");
    exit(1);
}
