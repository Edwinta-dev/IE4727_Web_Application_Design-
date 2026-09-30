<?php

declare(strict_types=1);

$index = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/index.php');
$style = (string) file_get_contents(dirname(__DIR__) . '/clinic-base/assets/style.css');
$audit = (string) file_get_contents(dirname(__DIR__) . '/tools/ui/audit.mjs');
$agents = (string) file_get_contents(dirname(__DIR__) . '/AGENTS.md');

test('home photo banner, compact login, and scoped scrim exception are present', static function () use ($index, $style, $audit, $agents): void {
    assert_true(strpos($index, '<div class="photo-banner">') < strpos($index, '<div class="photo-scrim"'), 'scrim is layered inside the shared photo banner');
    assert_true(strpos($index, '<div class="photo-caption">') < strpos($index, '<div class="hero-subtitle">'), 'headline precedes the subtitle row');
    assert_true(str_contains($style, 'background: linear-gradient(to top, rgba(0, 0, 0, 0.76), transparent)'), 'photo scrim is a neutral dark-to-transparent gradient');
    assert_true(str_contains($style, 'width: min(100%, 24rem)'), 'member login stays content-sized');
    assert_true(substr_count($audit, 'photo-scrim(?:\\.|$)') >= 2, 'slop and palette audits exempt only the photo scrim');
    assert_true(str_contains($audit, '.photo-scrim{height:60px;background:linear-gradient(to top,rgba(0,0,0,.76),transparent)}'), 'good audit fixture contains an approved photo scrim');
    assert_true(str_contains($audit, '.other-gradient{background:linear-gradient(red,blue)}'), 'bad audit fixture retains a disallowed gradient');
    assert_true(str_contains($agents, 'A neutral black-to-transparent `.photo-scrim` over a photo is allowed'), 'design rules document the owner exception');
});
