<?php

declare(strict_types=1);

// Remarks is a TEXT column (65,535 bytes). New rows use a versioned record;
// legacy completed rows cannot reliably identify who wrote their plain text.
const VISIT_REMARKS_PREFIX = "\x1Eclinic-remarks:1:";
const VISIT_REMARKS_MAX_BYTES = 65535;

/** @return array{reason:string, doctor_remarks:string, legacy:string|null} */
function decode_visit_remarks(?string $stored, string $status): array
{
    $raw = $stored ?? '';
    if (str_starts_with($raw, VISIT_REMARKS_PREFIX)) {
        $payload = substr($raw, strlen(VISIT_REMARKS_PREFIX));
        $data = json_decode($payload, true);
        if (is_array($data) && array_keys($data) === ['reason', 'doctor_remarks', 'legacy']
            && is_string($data['reason']) && is_string($data['doctor_remarks'])
            && ($data['legacy'] === null || is_string($data['legacy']))
            && json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) === $payload) {
            return $data;
        }
    }

    if ($status === 'Completed') {
        return ['reason' => '', 'doctor_remarks' => '', 'legacy' => $raw === '' ? null : $raw];
    }
    return ['reason' => $raw, 'doctor_remarks' => '', 'legacy' => null];
}

function encode_visit_remarks(string $reason, string $doctorRemarks = '', ?string $legacy = null): string
{
    $json = json_encode(
        ['reason' => $reason, 'doctor_remarks' => $doctorRemarks, 'legacy' => $legacy],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $stored = VISIT_REMARKS_PREFIX . $json;
    if (strlen($stored) > VISIT_REMARKS_MAX_BYTES) {
        throw new InvalidArgumentException('The reason and remarks are too long to save together. Shorten the remarks and try again.');
    }
    return $stored;
}
