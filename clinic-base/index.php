<?php

/*
Site entry point. The real home page is screens/index.php; this stub keeps clinic-base/ opening
at index.php and the login redirects (/index.php) working. Everything else lives in screens/.
*/

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'screens' . DIRECTORY_SEPARATOR . 'index.php';
