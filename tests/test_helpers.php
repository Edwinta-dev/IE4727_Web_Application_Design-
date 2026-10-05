<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/clinic-base/lib/helpers.php';

foreach (['Unknown specialty', '', 'Specialty care 7'] as $specialty) {
    assert_eq(specialty_image($specialty), '/assets/img/specialty-neutral.svg', 'unknown specialty uses a neutral image');
}
assert_eq(specialty_image('Dental'), '/assets/img/Dentist_in_action.jpg', 'known specialty retains its care photo');
if (!is_file(dirname(__DIR__) . '/clinic-base' . specialty_image('Unknown specialty'))) {
    throw new RuntimeException('neutral specialty image must ship with the base app');
}

if (e('<b>&amp;</b>') !== '&lt;b&gt;&amp;amp;&lt;/b&gt;') {
    throw new RuntimeException('e() did not escape HTML');
}

session_save_path(sys_get_temp_dir());
session_id('helper-test-' . bin2hex(random_bytes(4)));
start_session_once();
$_SESSION['old'] = ['name' => '&lt;Jane&gt;'];
$_SESSION['errors'] = ['name' => 'Name is required'];

if (old('name') !== '&lt;Jane&gt;' || old('missing', 'fallback') !== 'fallback') {
    throw new RuntimeException('old() did not return session values');
}

if (errors_for('name') !== 'Name is required' || errors_for('missing') !== '') {
    throw new RuntimeException('errors_for() returned an unexpected value');
}

flash('Saved', 'success');
ob_start();
flash_render();
$rendered = ob_get_clean();

if (strpos($rendered, 'flash-success') === false || strpos($rendered, 'Saved') === false) {
    throw new RuntimeException('flash_render() did not render the message');
}

ob_start();
flash_render();
$cleared = ob_get_clean();

if ($cleared !== '') {
    throw new RuntimeException('flash_render() did not clear the message');
}

if (fmt_date('2026-09-22') !== '22 Sep 2026' || fmt_time('2026-09-22 14:30:00') !== '2:30 PM') {
    throw new RuntimeException('date/time formatting is inconsistent');
}

echo "PASS: helper checks\n";
