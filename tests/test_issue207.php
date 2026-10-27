<?php

declare(strict_types=1);

$page = file_get_contents(dirname(__DIR__) . '/clinic-base/register.php');
$action = file_get_contents(dirname(__DIR__) . '/clinic-base/actions/register.php');
$script = file_get_contents(dirname(__DIR__) . '/clinic-base/assets/app.js');
if ($page === false || $action === false || $script === false) {
    throw new RuntimeException('Registration files could not be read');
}

foreach (['name="NoKnownAllergies" type="checkbox" value="1"', 'No Known Allergies',
    "old('NoKnownAllergies') === '1'", "\$noKnownAllergies ? ' readonly' : ''"] as $needle) {
    assert_contains($page, $needle, 'registration checkbox and restored state');
}
foreach (["\$data['NoKnownAllergies']", "\$data['Allergies'] = 'No Known Allergies'", "create_patient_account(\$data)"] as $needle) {
    assert_contains($action, $needle, 'server-side canonical allergies');
}
foreach (['noKnownAllergies.addEventListener', 'allergies.readOnly = noKnownAllergies.checked'] as $needle) {
    assert_contains($script, $needle, 'registration checkbox interaction');
}
