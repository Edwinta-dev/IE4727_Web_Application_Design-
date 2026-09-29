<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';

function list_notifications(): array
{
    return q_all('SELECT `notificationID`, `sender`, `recipient`, `Subject`, `Body`, `appointmentID`, `deliveryStatus`, `SentAt`
                  FROM `notifications` ORDER BY `notificationID` DESC');
}
