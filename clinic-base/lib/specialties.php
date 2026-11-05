<?php

/*
The five clinic specialties and the legacy alias Dentistry -> Dental,
their care photos and profile copy. Also tidies a doctor's displayed name and specialty without changing stored rows.
*/

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

/**
 * Specialty care photo, or a neutral illustration when no matching photo exists.
 */
function specialty_image(string $specialty): string
{
    $images = [
        'General Practice' => 'General_Practice_in_Action.jpg',
        'Dental' => 'Dentist_in_action.jpg',
        'Paediatrics' => 'Paediatrics_in_Action.jpg',
        'Dermatology' => 'Dermatologist_in_Action.jpg',
        'Physiotherapy' => 'Physiotherapist_in_Action.jpg',
    ];

    return '/assets/img/' . ($images[$specialty] ?? 'specialty-neutral.svg');
}

/** @return array{summary: string, who_should_book: list<string>} */
function specialty_profile(string $specialty): array
{
    $profiles = [
        'General Practice' => [
            'summary' => 'Our general practice team provides first assessments, routine health reviews and ongoing care for common illnesses and long-term conditions. Your doctor can help coordinate referrals when specialist care is needed.',
            'who_should_book' => [
                'Adults seeking a check-up or advice about a new, non-emergency concern.',
                'People managing conditions such as high blood pressure, diabetes or asthma.',
                'Families arranging vaccinations and routine preventive care.',
                'Patients who need follow-up after a recent illness or treatment.',
            ],
        ],
        'Dental' => [
            'summary' => 'Dental appointments at our clinic cover preventive checks, common tooth and gum concerns, restorative treatment and orthodontic assessment. Your dentist will explain findings and options before treatment begins.',
            'who_should_book' => [
                'People due for a routine dental examination or cleaning.',
                'Patients with toothache, sensitivity or bleeding gums.',
                'Adults and young people seeking advice about alignment or bite concerns.',
                'Anyone who wants a review of a filling, crown or other previous dental work.',
            ],
        ],
        'Paediatrics' => [
            'summary' => 'Our paediatric service assesses children from infancy through adolescence, including everyday illness, growth and development, and ongoing childhood conditions. Parents and caregivers are included in each care plan.',
            'who_should_book' => [
                'Parents seeking an assessment for a child who is unwell.',
                'Families arranging growth, development or nutrition reviews.',
                'Children who need follow-up for asthma, allergies or another ongoing condition.',
                'Caregivers with questions about age-appropriate preventive care.',
            ],
        ],
        'Dermatology' => [
            'summary' => 'Our dermatology consultations assess skin, hair and nail concerns, including persistent rashes and changes that need a closer examination. The dermatologist will discuss diagnosis, treatment choices and skin care relevant to your routine.',
            'who_should_book' => [
                'People with recurring eczema, acne or an unexplained rash.',
                'Patients concerned about a changing mole or other skin growth.',
                'Anyone with persistent itching, pigmentation or nail changes.',
                'Patients needing review of a skin condition that has not settled with initial care.',
            ],
        ],
        'Physiotherapy' => [
            'summary' => 'Physiotherapy appointments assess pain, movement and recovery after injury or surgery. Your physiotherapist will agree practical exercises and activity changes with you, then review progress against your everyday goals.',
            'who_should_book' => [
                'People recovering from a sports injury or an operation.',
                'Patients with back, neck or joint pain affecting daily activity.',
                'Anyone working to regain strength, balance or range of movement.',
                'People who want guidance on returning safely to work, exercise or sport.',
            ],
        ],
    ];

    return $profiles[$specialty] ?? [
        'summary' => 'Our clinicians assess your concern, explain suitable care options and arrange follow-up or referral when needed.',
        'who_should_book' => [
            'Patients seeking an assessment related to this specialty.',
            'People who need follow-up for an existing concern.',
            'Anyone unsure which appointment best fits their needs.',
            'Patients seeking advice on next steps after an earlier assessment.',
        ],
    ];
}
