<?php

/*
Prints and clears the one-time flash message. Include it inside <main> on pages that expect messages.
*/

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';

flash_render();
