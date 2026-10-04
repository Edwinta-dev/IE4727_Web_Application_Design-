<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/db.php';

const RECONCILE_KEYS = ['doctor' => 'DoctorID', 'patient' => 'PatientID', 'slots' => 'slotID', 'appointment' => 'appointmentID', 'notifications' => 'notificationID'];

function reconciliation_begin(string $database, bool $readOnly): void
{
    if (DB_NAME !== $database) throw new RuntimeException('Config mismatch.');
    if ($readOnly) q('SET TRANSACTION READ ONLY');
    else reconciliation_test_guard();
    db()->beginTransaction();
    if (q_val('SELECT DATABASE()') !== $database) throw new RuntimeException('Connection mismatch.');
}

/** Full rows stay in memory or a private backup, never in the public inventory. */
function reconciliation_snapshot(bool $lock = false): array
{
    $queries = [
        'doctor' => 'SELECT * FROM doctor ORDER BY DoctorID',
        'patient' => 'SELECT * FROM patient ORDER BY PatientID',
        'slots' => 'SELECT * FROM slots ORDER BY slotID',
        'appointment' => 'SELECT * FROM appointment ORDER BY appointmentID',
        'notifications' => 'SELECT * FROM notifications ORDER BY notificationID',
    ];
    $result = [];
    foreach ($queries as $table => $sql) {
        foreach (q_all($sql . ($lock ? ' FOR UPDATE' : '')) as $row) {
            $result[$table . ':' . $row[RECONCILE_KEYS[$table]]] = $row;
        }
    }
    return $result;
}

function reconciliation_fingerprint(array $row): string
{
    ksort($row);
    return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
}

function reconciliation_inventory(array $rows, array $provenance): array
{
    $counts = ['synthetic' => 0, 'demo' => 0, 'manual' => 0, 'ambiguous' => 0];
    $items = [];
    $elapsedPending = 0;
    foreach ($rows as $ref => $row) {
        $hash = reconciliation_fingerprint($row);
        $entry = $provenance[$ref] ?? [];
        $category = 'ambiguous';
        if (($entry['sha256'] ?? '') === $hash && is_string($entry['evidence'] ?? null)
            && trim($entry['evidence']) !== '' && in_array($entry['category'] ?? '', ['synthetic', 'demo', 'manual'], true)) {
            $category = $entry['category'];
        }
        $counts[$category]++;
        if (str_starts_with($ref, 'appointment:') && $row['Status'] === 'Future'
            && strtotime($row['appointmentDateTime']) < time()) $elapsedPending++;
        $links = [];
        foreach (['DoctorID' => 'doctor', 'PatientID' => 'patient', 'slotID' => 'slots', 'appointmentID' => 'appointment'] as $key => $table) {
            if (isset($row[$key]) && !str_starts_with($ref, $table . ':')) $links[] = $table . ':' . $row[$key];
        }
        $items[$ref] = ['category' => $category, 'sha256' => $hash, 'links' => $links];
    }
    return ['counts' => $counts, 'elapsed_future' => $elapsedPending, 'rows' => $items];
}

/** Include every cascading, SET NULL and email-linked dependent; no name matching. */
function reconciliation_plan(array $rows, array $provenance, array $targets): array
{
    if ($targets === [] || count($targets) > 100) throw new RuntimeException('Require 1..100 exact root references.');
    $selected = array_fill_keys($targets, true);
    foreach ($targets as $ref) if (!isset($rows[$ref])) throw new RuntimeException('Missing target.');
    do {
        $before = count($selected);
        $emails = [];
        foreach ($selected as $ref => $_) if (isset($rows[$ref]['Email'])) $emails[] = strtolower($rows[$ref]['Email']);
        foreach ($rows as $ref => $row) {
            foreach (['DoctorID' => 'doctor', 'PatientID' => 'patient', 'slotID' => 'slots', 'appointmentID' => 'appointment'] as $key => $table) {
                if (!str_starts_with($ref, $table . ':') && isset($row[$key]) && isset($selected[$table . ':' . $row[$key]])) $selected[$ref] = true;
            }
            if (str_starts_with($ref, 'notifications:') && (in_array(strtolower($row['sender']), $emails, true) || in_array(strtolower($row['recipient']), $emails, true))) $selected[$ref] = true;
            // A removed appointment's linked slot also needs explicit review; never leave a stale Booked slot.
            if (str_starts_with($ref, 'appointment:') && isset($selected[$ref]) && isset($row['slotID'])) $selected['slots:' . $row['slotID']] = true;
        }
    } while (count($selected) !== $before);
    $inventory = reconciliation_inventory($rows, $provenance);
    $counts = array_fill_keys(array_keys(RECONCILE_KEYS), 0);
    $refs = array_keys($selected);
    sort($refs);
    $hashes = [];
    $ambiguous = [];
    foreach ($refs as $ref) {
        $item = $inventory['rows'][$ref] ?? null;
        if (($item['category'] ?? '') !== 'synthetic') $ambiguous[] = $ref;
        $hashes[$ref] = $item['sha256'] ?? null;
        $counts[explode(':', $ref)[0]]++;
    }
    return ['allowed' => $ambiguous === [], 'review_required' => $ambiguous, 'counts' => $counts,
        'references' => $refs, 'token' => hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR))];
}

function reconciliation_test_guard(): void
{
    if (DB_NAME !== 'ie4727db_test' || getenv('CLINIC_DB_NAME') !== 'ie4727db_test') throw new RuntimeException('Only explicit ie4727db_test supports apply/restore.');
    if (q_val('SELECT DATABASE()') !== 'ie4727db_test') throw new RuntimeException('Connection mismatch.');
}

/** Caller owns transaction and must save a private backup before calling. */
function reconciliation_delete(array $plan): void
{
    reconciliation_test_guard();
    if (!$plan['allowed']) throw new RuntimeException('Ambiguous dependencies; refusing cleanup.');
    $sql = ['notifications' => 'DELETE FROM notifications WHERE notificationID = :id',
        'appointment' => 'DELETE FROM appointment WHERE appointmentID = :id',
        'slots' => 'DELETE FROM slots WHERE slotID = :id',
        'patient' => 'DELETE FROM patient WHERE PatientID = :id', 'doctor' => 'DELETE FROM doctor WHERE DoctorID = :id'];
    foreach ($sql as $table => $statement) foreach ($plan['references'] as $ref) {
        if (str_starts_with($ref, $table . ':')) q($statement, ['id' => (int) explode(':', $ref)[1]]);
    }
}

/** Restore exact deleted rows, with original IDs. Existing IDs fail, never overwrite. */
function reconciliation_restore(array $backup): void
{
    reconciliation_test_guard();
    $statements = [
        'doctor' => 'INSERT INTO doctor (DoctorID,FullName,User,HashPass,Email,Specialty,Qualifications,Languages,WriteUp,ImageURL) VALUES (:DoctorID,:FullName,:User,:HashPass,:Email,:Specialty,:Qualifications,:Languages,:WriteUp,:ImageURL)',
        'patient' => 'INSERT INTO patient (PatientID,FullName,User,HashPass,Email,Gender,Phone,Allergies) VALUES (:PatientID,:FullName,:User,:HashPass,:Email,:Gender,:Phone,:Allergies)',
        'slots' => 'INSERT INTO slots (slotID,DoctorID,SlotDateTime,CreatedAt,Status) VALUES (:slotID,:DoctorID,:SlotDateTime,:CreatedAt,:Status)',
        'appointment' => 'INSERT INTO appointment (appointmentID,DoctorID,PatientID,slotID,appointmentDateTime,CreatedAt,updatedAt,Status,Diagnosis,Prescription,Treatment,FollowUp,Remarks) VALUES (:appointmentID,:DoctorID,:PatientID,:slotID,:appointmentDateTime,:CreatedAt,:updatedAt,:Status,:Diagnosis,:Prescription,:Treatment,:FollowUp,:Remarks)',
        'notifications' => 'INSERT INTO notifications (notificationID,sender,recipient,Subject,Body,appointmentID,deliveryStatus,SentAt) VALUES (:notificationID,:sender,:recipient,:Subject,:Body,:appointmentID,:deliveryStatus,:SentAt)',
    ];
    foreach ($statements as $table => $sql) foreach ($backup as $ref => $row) {
        if (str_starts_with($ref, $table . ':')) q($sql, $row);
    }
}
