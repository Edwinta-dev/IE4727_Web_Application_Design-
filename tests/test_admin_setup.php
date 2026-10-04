<?php

declare(strict_types=1);

test('local admin setup hashes credentials and preserves existing configuration', static function (): void {
    $root = dirname(__DIR__);
    $sandbox = $root . '/.ui-sessions-setup-' . bin2hex(random_bytes(5));
    mkdir($sandbox . '/tools', 0700, true);
    mkdir($sandbox . '/clinic-base', 0700);
    copy($root . '/tools/setup_admin.php', $sandbox . '/tools/setup_admin.php');
    $target = $sandbox . '/clinic-base/config.local.php';
    $run = static function (string $password, string $username) use ($sandbox): array {
        $process = proc_open([PHP_BINARY, $sandbox . '/tools/setup_admin.php', $username],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        assert_true(is_resource($process), 'setup process starts');
        fwrite($pipes[0], $password . "\n");
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    };
    try {
        [$status] = $run('short', 'admin');
        assert_true($status !== 0 && !file_exists($target), 'invalid password creates no config');
        [$status] = $run(str_repeat('a', 73), 'admin');
        assert_true($status !== 0 && !file_exists($target), 'bcrypt truncation is rejected');
        $password = 'Local$Password142';
        $username = "local'admin";
        [$status, $out] = $run($password, $username);
        assert_eq($status, 0);
        assert_contains($out, 'OK:');
        $contents = (string) file_get_contents($target);
        assert_true(!str_contains($contents, $password), 'plaintext is never written');
        assert_true(!str_contains($contents, 'DB_NAME'), 'test DB selection remains environment controlled');
        // Load the generated file in a fresh process, without suite admin constants.
        $code = 'require $argv[1]; if (ADMIN_USER !== $argv[2] || !password_verify($argv[3], ADMIN_HASH)) exit(1);';
        $process = proc_open([PHP_BINARY, '-r', $code, $target, $username, $password],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        foreach ($pipes as $pipe) { fclose($pipe); }
        assert_eq(proc_close($process), 0, 'generated PHP safely preserves credentials');
        [$status] = $run('Replacement142', 'replacement');
        assert_true($status !== 0, 'existing config is refused');
        assert_eq(file_get_contents($target), $contents, 'existing file stays byte-for-byte intact');
    } finally {
        if (is_file($target)) { unlink($target); }
        unlink($sandbox . '/tools/setup_admin.php');
        rmdir($sandbox . '/tools');
        rmdir($sandbox . '/clinic-base');
        rmdir($sandbox);
    }
});
