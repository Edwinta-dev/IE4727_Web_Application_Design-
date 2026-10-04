<?php

declare(strict_types=1);

$base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'clinic-base';
$violations = [];

/** @return list<string> */
function audit_php_files(string $directory): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);

    return $files;
}

function audit_relative(string $base, string $path): string
{
    return str_replace('\\', '/', substr($path, strlen($base) + 1));
}

foreach (['patient', 'doctor', 'admin'] as $roleDirectory) {
    foreach (audit_php_files($base . DIRECTORY_SEPARATOR . $roleDirectory) as $path) {
        $source = file_get_contents($path);
        if ($source === false) {
            throw new RuntimeException('Could not read ' . $path);
        }

        $tokens = token_get_all($source);
        $guardPosition = null;
        if (preg_match('/\brequire_(?:login|doctor|admin)\s*\(/', $source, $guardMatch, PREG_OFFSET_CAPTURE) === 1) {
            $guardPosition = $guardMatch[0][1];
        }
        $outputPosition = null;
        $position = 0;
        foreach ($tokens as $token) {
            $text = is_array($token) ? $token[1] : $token;
            if ($outputPosition === null && is_array($token)
                && in_array($token[0], [T_ECHO, T_INLINE_HTML, T_OPEN_TAG_WITH_ECHO], true)) {
                $outputPosition = $position;
            }
            $position += strlen($text);
        }

        $relative = audit_relative($base, $path);
        if ($guardPosition === null) {
            $violations[] = $relative . ': missing role guard';
        } elseif ($outputPosition !== null && $guardPosition > $outputPosition) {
            $violations[] = $relative . ': guard runs after output';
        }
    }
}

foreach (audit_php_files($base) as $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $path);
    }

    if (preg_match_all(
        '~<form\b[^>]*\bmethod\s*=\s*(["\\\'])post\1[^>]*>(.*?)</form\s*>~is',
        $source,
        $forms,
        PREG_SET_ORDER
    ) !== false) {
        foreach ($forms as $form) {
            if (stripos($form[2], 'csrf_field(') === false) {
                $violations[] = audit_relative($base, $path) . ': POST form is missing csrf_field()';
            }
        }
    }
}

foreach (audit_php_files($base . DIRECTORY_SEPARATOR . 'actions') as $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException('Could not read ' . $path);
    }
    $csrfPosition = strpos($source, 'csrf_check(');
    if ($csrfPosition === false) {
        $violations[] = audit_relative($base, $path) . ': missing csrf_check()';
        continue;
    }

    if (preg_match(
        '/\b(?:attempt_login|book_appointment|cancel_appointment|reschedule_appointment|set_appointment_status|create_(?:doctor|patient)_account|user_or_email_taken)\s*\(/',
        substr($source, 0, $csrfPosition)
    ) === 1) {
        $violations[] = audit_relative($base, $path) . ': model call precedes csrf_check()';
    }
}

$appointmentAction = file_get_contents($base . DIRECTORY_SEPARATOR . 'actions' . DIRECTORY_SEPARATOR . 'appointment.php');
$doctorHome = file_get_contents($base . DIRECTORY_SEPARATOR . 'doctor' . DIRECTORY_SEPARATOR . 'home.php');
$doctorVisit = file_get_contents($base . DIRECTORY_SEPARATOR . 'doctor' . DIRECTORY_SEPARATOR . 'visit.php');
$appointmentsModel = file_get_contents($base . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'appointments.php');
if ($appointmentAction === false || $doctorHome === false || $doctorVisit === false || $appointmentsModel === false) {
    throw new RuntimeException('Could not read tenant-isolation sources');
}

if (strpos($appointmentAction, '(is_patient() && (int) $appointment[\'PatientID\'] !== (int) $user[\'id\'])') === false) {
    $violations[] = 'appointment action does not enforce patient ownership';
}
if (strpos($appointmentAction, '(is_doctor() && (int) $appointment[\'DoctorID\'] !== (int) $user[\'id\'])') === false) {
    $violations[] = 'appointment action does not enforce doctor ownership';
}
if (strpos($doctorHome, 'appointments_for_doctor_day($doctorId, $date)') === false) {
    $violations[] = 'doctor day board is not scoped to the authenticated doctor';
}
if (strpos($doctorVisit, '(int) $appointment[\'DoctorID\'] !== $doctorId') === false) {
    $violations[] = 'doctor visit does not enforce appointment ownership';
}
if (strpos($doctorVisit, 'patient_history((int) $appointment[\'PatientID\'], $doctorId, $appointmentId)') === false
    || strpos($appointmentsModel, "a.`DoctorID` = :doctor_id") === false) {
    $violations[] = 'doctor visit history is not scoped to the authenticated doctor';
}

if ($violations !== []) {
    throw new RuntimeException("guard/CSRF audit failed:\n- " . implode("\n- ", $violations));
}

echo "PASS: guard and CSRF audit\n";
