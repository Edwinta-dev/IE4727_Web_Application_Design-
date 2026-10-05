<?php

declare(strict_types=1);

/** The clinic's existing services, shared by registration and directory lists. */
function canonical_specialties(): array
{
    return ['Dental', 'Dermatology', 'General Practice', 'Paediatrics', 'Physiotherapy'];
}

/** Legacy aliases are readable without changing stored profiles. */
function canonical_specialty(?string $value): ?string
{
    $value = trim((string) $value);
    if (strcasecmp($value, 'Dentistry') === 0) {
        return 'Dental';
    }
    foreach (canonical_specialties() as $specialty) {
        if (strcasecmp($value, $specialty) === 0) {
            return $specialty;
        }
    }
    return null;
}

function doctor_display_name(string $name): string
{
    $name = trim($name);
    // Preserve intentional mixed casing (for example McDonald).
    return $name === strtolower($name) ? ucwords($name, " \t\r\n-'") : $name;
}

function doctor_display_profile(array $doctor): array
{
    $doctor['FullName'] = doctor_display_name((string) $doctor['FullName']);
    $doctor['Specialty'] = canonical_specialty($doctor['Specialty'] ?? null) ?? $doctor['Specialty'];
    return $doctor;
}
