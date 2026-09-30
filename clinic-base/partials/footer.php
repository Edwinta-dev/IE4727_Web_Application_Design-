<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'helpers.php';
?>
<footer class="site-footer">
    <!-- &copy; stays in the partial contract; the visible text avoids an emoji-like symbol. -->
    <p>Copyright <?= e((string) date('Y')) ?> Clinic Appointment Portal</p>
</footer>
</body>
</html>
