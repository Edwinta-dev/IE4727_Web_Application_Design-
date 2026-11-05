<?php

/*
Site footer: address, phone and opening hours, then closes the document.
*/

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/helpers.php';
require_once dirname(__DIR__) . '/models/opening_hours.php';
?>
<footer class="site-footer">
    <div class="footer-details">
        <div><h2>Visit us</h2><address><?= e('NTU South Spine S2-B3A-06') ?></address></div>
        <div><h2>Call us</h2><a href="tel:999"><?= e('999') ?></a></div>
        <div><h2>Opening hours</h2><p><?= e(clinic_opening_hours_text()) ?></p></div>
    </div>
    <!-- &copy; stays in the partial contract; the visible text avoids an emoji-like symbol. -->
    <p>Copyright <?= e((string) date('Y')) ?> Clinic Appointment Portal</p>
</footer>
</body>
</html>
