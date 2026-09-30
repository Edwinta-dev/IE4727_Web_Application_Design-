-- Issue #5 demo seed. Passwords below are bcrypt outputs for Password123.
USE `ie4727db`;

SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM `notifications`;
DELETE FROM `appointment`;
DELETE FROM `slots`;
DELETE FROM `patient`;
DELETE FROM `doctor`;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `doctor` (`FullName`,`User`,`HashPass`,`Email`,`Specialty`,`Qualifications`,`Languages`,`WriteUp`,`ImageURL`) VALUES
('Dr. John Smith','drsmith','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','drsmith@clinic.test','General Practice','MBBS (NUS), MRCP (UK)','English, Mandarin','Dr. Smith is a family physician who has cared for adults and children in Singapore for more than fifteen years. He focuses on preventive health, chronic disease management, and practical advice that helps patients make confident decisions between visits.','assets/img/Dr_John_Smith.jpg'),
('Dr. Priya Nair','drnair','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','drnair@clinic.test','Dental','BDS (Manipal), MDS Orthodontics','English, Tamil, Hindi','Dr. Nair is a restorative dentist and orthodontist who takes time to explain each treatment option. Her calm, preventive approach helps nervous patients feel comfortable, while her experience with families and young adults supports healthy smiles at every stage of life.','assets/img/Dr_Priya_Nair.png'),
('Dr. Wei Ming Tan','drtan','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','drtan@clinic.test','Paediatrics','MBBS (NTU), MRCPCH (UK)','English, Mandarin, Hokkien','Dr. Tan is a paediatrician who looks after newborns, school-age children, and teenagers. He combines careful assessment with clear guidance for parents, and has a particular interest in childhood asthma, nutrition, and supporting healthy development through the changing school years.','assets/img/Dr_Wei_Ming_Tan.png'),
('Dr. Sarah Lim','drlim','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','drlim@clinic.test','Dermatology','MBBS (NUS), MRCP (UK), Dip Dermatology (Singapore)','English, Mandarin, Cantonese','Dr. Lim treats common and complex skin conditions, including eczema, acne, pigmentation, and troublesome rashes. She believes that an accurate diagnosis and a manageable routine are equally important, so every consultation includes practical skincare advice tailored to the patient’s daily life.','assets/img/Dr_Sarah_Lim.jpg'),
('Dr. Arjun Rao','drrao','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','drrao@clinic.test','Physiotherapy','BSc Physiotherapy (SIT), MSc Sports Rehabilitation (Edinburgh)','English, Tamil, Malay','Dr. Rao is a physiotherapist specialising in sports injuries, post-operative recovery, and persistent back and neck pain. He builds progressive exercise plans around each patient’s goals, helping people return safely to work, recreation, and everyday movement with greater confidence.','assets/img/Dr_Arjun_Rao.png');

INSERT INTO `patient` (`FullName`,`User`,`HashPass`,`Email`,`Gender`,`Phone`,`Allergies`) VALUES
('Alex Tan','alextan','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','alex.tan@example.com','Male','91112223','["Penicillin"]'),
('Bethany Ong','bethong','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','beth.ong@example.com','Female','92223334','[]'),
('Chandran Kumar','chandrank','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','chandran.k@example.com','Male','93334445','["Aspirin","Shellfish"]'),
('Diana Lee','dianalee','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','diana.lee@example.com','Female','94445556','[]'),
('Elijah Wong','elijahw','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','elijah.w@example.com','Male','95556667','["Latex"]'),
('Farah Ismail','farahi','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','farah.i@example.com','Female','96667778','["Peanuts"]'),
('Gerald Sim','geralds','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','gerald.s@example.com','Male','97778889','[]'),
('Hui Ling Chua','huilingc','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','huiling.c@example.com','Female','98889990','["Pollen","Dust mites"]');

-- Keep a rolling window so the demo never becomes stale. There are 60 days
-- here (including the next 30), with Sundays and the 13:00 lunch slot omitted.
INSERT INTO `slots` (`DoctorID`,`SlotDateTime`,`Status`)
WITH RECURSIVE days AS (
  SELECT CURDATE() - INTERVAL 30 DAY AS d
  UNION ALL SELECT d + INTERVAL 1 DAY FROM days WHERE d < CURDATE() + INTERVAL 29 DAY
), times AS (
  SELECT '09:00:00' AS t UNION ALL SELECT '09:30:00' UNION ALL SELECT '10:00:00'
  UNION ALL SELECT '10:30:00' UNION ALL SELECT '11:00:00' UNION ALL SELECT '11:30:00'
  UNION ALL SELECT '12:00:00' UNION ALL SELECT '12:30:00' UNION ALL SELECT '14:00:00'
  UNION ALL SELECT '14:30:00' UNION ALL SELECT '15:00:00' UNION ALL SELECT '15:30:00'
  UNION ALL SELECT '16:00:00' UNION ALL SELECT '16:30:00'
)
SELECT d.DoctorID, TIMESTAMP(days.d, times.t), 'Available'
FROM doctor d CROSS JOIN days CROSS JOIN times
WHERE DAYOFWEEK(days.d) <> 1;

-- Doctor 2's most recent completed clinic day is fully booked.
SET @full_day = (SELECT MAX(DATE(SlotDateTime)) FROM slots WHERE DoctorID = 2 AND SlotDateTime < CURDATE());
CREATE TEMPORARY TABLE demo_full_day AS
SELECT slotID, DoctorID, SlotDateTime FROM slots WHERE DoctorID = 2 AND DATE(SlotDateTime) = @full_day;

INSERT INTO `appointment` (`DoctorID`,`PatientID`,`slotID`,`appointmentDateTime`,`Status`,`Diagnosis`,`Prescription`,`Treatment`,`FollowUp`,`Remarks`)
SELECT DoctorID, MOD(slotID - 1, 8) + 1, slotID, SlotDateTime, 'Completed',
       'Routine review and health screening completed.', 'Continue current medicines as directed.',
       'Lifestyle counselling and follow-up measurements recorded.', 1, 'Patient attended the scheduled review.'
FROM demo_full_day;

-- Four additional completed visits, two no-shows, one cancellation, and four
-- future bookings provide the remaining realistic appointment states.
CREATE TEMPORARY TABLE demo_past AS
SELECT s.slotID, s.DoctorID, s.SlotDateTime
FROM slots s
WHERE s.SlotDateTime < CURDATE() AND DATE(s.SlotDateTime) <> @full_day
ORDER BY s.SlotDateTime DESC, s.DoctorID
LIMIT 7;

INSERT INTO `appointment` (`DoctorID`,`PatientID`,`slotID`,`appointmentDateTime`,`Status`,`Diagnosis`,`Prescription`,`Treatment`,`FollowUp`,`Remarks`)
SELECT DoctorID, MOD(slotID - 1, 8) + 1, slotID, SlotDateTime,
       CASE WHEN ROW_NUMBER() OVER (ORDER BY SlotDateTime DESC, slotID) <= 4 THEN 'Completed'
            WHEN ROW_NUMBER() OVER (ORDER BY SlotDateTime DESC, slotID) <= 6 THEN 'No show'
            ELSE 'Cancelled' END,
       CASE WHEN ROW_NUMBER() OVER (ORDER BY SlotDateTime DESC, slotID) <= 4 THEN 'Symptoms reviewed and diagnosis recorded.' ELSE NULL END,
       CASE WHEN ROW_NUMBER() OVER (ORDER BY SlotDateTime DESC, slotID) <= 4 THEN 'Use medication as prescribed.' ELSE NULL END,
       CASE WHEN ROW_NUMBER() OVER (ORDER BY SlotDateTime DESC, slotID) <= 4 THEN 'Treatment plan discussed with the patient.' ELSE NULL END,
       CASE WHEN ROW_NUMBER() OVER (ORDER BY SlotDateTime DESC, slotID) <= 4 THEN 1 ELSE 0 END,
       CASE WHEN ROW_NUMBER() OVER (ORDER BY SlotDateTime DESC, slotID) = 7 THEN 'Cancelled by patient.' ELSE NULL END
FROM demo_past;

SET @blocked_day = (SELECT MIN(DATE(SlotDateTime)) FROM slots WHERE DoctorID = 3 AND SlotDateTime >= CURDATE() + INTERVAL 2 DAY);
UPDATE slots SET Status = 'Blocked' WHERE DoctorID = 3 AND DATE(SlotDateTime) = @blocked_day AND TIME(SlotDateTime) >= '14:00:00';

CREATE TEMPORARY TABLE demo_future AS
SELECT s.slotID, s.DoctorID, s.SlotDateTime
FROM slots s
WHERE s.SlotDateTime >= CURDATE()
  AND s.Status = 'Available'
  AND NOT (s.DoctorID = 1 AND DATE(s.SlotDateTime) = CURDATE() + INTERVAL 1 DAY AND TIME(s.SlotDateTime) = '10:30:00')
  AND NOT (s.DoctorID = 3 AND DATE(s.SlotDateTime) = @blocked_day AND TIME(s.SlotDateTime) >= '14:00:00')
ORDER BY s.SlotDateTime, s.DoctorID
LIMIT 4;

INSERT INTO `appointment` (`DoctorID`,`PatientID`,`slotID`,`appointmentDateTime`,`Status`,`FollowUp`,`Remarks`)
SELECT DoctorID, MOD(slotID - 1, 8) + 1, slotID, SlotDateTime, 'Future', 0, 'Booked online for an upcoming consultation.'
FROM demo_future;

UPDATE slots s INNER JOIN appointment a ON a.slotID = s.slotID SET s.Status = 'Booked';
DROP TEMPORARY TABLE demo_full_day;
DROP TEMPORARY TABLE demo_past;
DROP TEMPORARY TABLE demo_future;

SET FOREIGN_KEY_CHECKS = 1;
