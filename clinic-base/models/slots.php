<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'db.php';

/** @return list<array<string, mixed>> */
function next_available(int $doctorId): array
{
    return q_all(
        'SELECT `slotID`, `DoctorID`, `SlotDateTime`,
                DATE(`SlotDateTime`) AS `SlotDate`, TIME(`SlotDateTime`) AS `SlotTime`
         FROM `slots`
         WHERE `DoctorID` = :doctor_id
           AND `Status` = \'Available\'
           AND `SlotDateTime` >= NOW()
         ORDER BY `SlotDateTime`
         LIMIT 3',
        ['doctor_id' => $doctorId]
    );
}
