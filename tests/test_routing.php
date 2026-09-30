<?php

declare(strict_types=1);

// Every URL the apps emit must work from a subfolder of a stock XAMPP htdocs
// (e.g. /IE4727_Web_Application_Design-/clinic-base/) as well as from a web root.

$root = dirname(__DIR__);

/**
 * Run url()/app_request_uri() in a fresh PHP process, since base_path() caches per request.
 */
$probe = static function (string $app, string $scriptName, string $scriptFile, string $requestUri) use ($root): string {
    $code = '$_SERVER["SCRIPT_NAME"] = ' . var_export($scriptName, true) . ';'
        . '$_SERVER["SCRIPT_FILENAME"] = ' . var_export($scriptFile, true) . ';'
        . '$_SERVER["REQUEST_URI"] = ' . var_export($requestUri, true) . ';'
        . 'require ' . var_export($root . '/' . $app . '/lib/helpers.php', true) . ';'
        . 'echo url("/assets/img/clinic-logo.svg"), "|", url("/book.php?doctor=1"), "|", url("https://example.test/"), "|", app_request_uri();';
    $probeFile = tempnam(sys_get_temp_dir(), 'route');
    file_put_contents($probeFile, '<?php ' . $code);
    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($probeFile));
    unlink($probeFile);

    return trim((string) $output);
};

foreach (['clinic-base', 'clinic-plus'] as $app) {
    $routingCases = [
        'XAMPP subfolder' => [
            '/IE4727_Web_Application_Design-/' . $app . '/doctor/home.php',
            '/IE4727_Web_Application_Design-/' . $app . '/doctor/home.php?date=2026-10-01',
            '/IE4727_Web_Application_Design-/' . $app . '/assets/img/clinic-logo.svg|/IE4727_Web_Application_Design-/' . $app . '/book.php?doctor=1|https://example.test/|/doctor/home.php?date=2026-10-01',
        ],
        'lower-case URL' => [
            '/ie4727_web_application_design-/' . $app . '/index.php',
            '/ie4727_web_application_design-/' . $app . '/index.php',
            '/ie4727_web_application_design-/' . $app . '/assets/img/clinic-logo.svg|/ie4727_web_application_design-/' . $app . '/book.php?doctor=1|https://example.test/|/index.php',
        ],
        'web root' => [
            '/index.php',
            '/index.php?next=%2Fbook.php',
            '/assets/img/clinic-logo.svg|/book.php?doctor=1|https://example.test/|/index.php?next=%2Fbook.php',
        ],
    ];

    foreach ($routingCases as $label => [$scriptName, $requestUri, $expected]) {
        $inApp = str_contains($scriptName, '/doctor/') ? '/doctor/home.php' : '/index.php';
        $actual = $probe($app, $scriptName, $root . '/' . $app . $inApp, $requestUri);
        if ($actual !== $expected) {
            throw new RuntimeException("{$app} {$label}: expected {$expected}, got {$actual}");
        }
    }

    // No template may hard-code a root-absolute URL; it must go through url().
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $app, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (!in_array($file->getExtension(), ['php', 'css', 'js'], true)) {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        if (preg_match('~(?:href|src|action|hx-get)="/|@import url\("/|EventSource\(\'/~', $source, $match) === 1) {
            throw new RuntimeException('Root-absolute URL in ' . $file->getPathname() . ': ' . $match[0]);
        }
    }
}

// Every image a page or the seed references must exist in both apps.
$seed = (string) file_get_contents($root . '/schema/003_seed.sql');
$helpers = (string) file_get_contents($root . '/clinic-base/lib/helpers.php');
$index = (string) file_get_contents($root . '/clinic-base/index.php');
preg_match_all('~assets/img/([A-Za-z0-9_.-]+\.(?:jpg|png|svg))~', $seed . $index, $paths);
preg_match_all('~\'([A-Za-z0-9_]+\.jpg)\'~', $helpers, $specialty);
foreach (array_unique([...$paths[1], ...$specialty[1]]) as $image) {
    foreach (['clinic-base', 'clinic-plus'] as $app) {
        if (!is_file($root . '/' . $app . '/assets/img/' . $image)) {
            throw new RuntimeException("{$app} is missing referenced image {$image}");
        }
    }
}

echo "PASS: base-path aware routing and image references\n";
