-- Issue #5 demo seed. Passwords below are bcrypt outputs for Password123.
USE `ie4727db`;

-- One clinic-local clock for the entire seed, independent of server timezone.
SET time_zone = '+08:00'; -- Asia/Singapore
SET @seed_now = NOW();

SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM `notifications`;
DELETE FROM `appointment`;
DELETE FROM `slots`;
DELETE FROM `patient`;
DELETE FROM `doctor`;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `doctor` (`FullName`,`User`,`HashPass`,`Email`,`Specialty`,`Qualifications`,`Languages`,`WriteUp`,`ImageURL`) VALUES
('Dr. John Smith','drsmith','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','drsmith@clinic.test','General Practice','MBBS (NUS), MRCP (UK)','English, Mandarin','Dr. Smith is a family physician who has cared for adults and children in Singapore for more than fifteen years. He focuses on preventive health, chronic disease management and practical advice that helps patients make confident decisions between visits. He takes time to understand how symptoms affect daily routines and agrees on clear next steps with each patient.\n\nAt a consultation, patients can discuss a new concern, review an ongoing condition or plan routine screening. Dr. Smith also supports families with vaccinations and follow-up after illness, coordinating further assessment when a concern needs specialist input. He aims to make each visit useful by explaining findings in plain language and setting out what to monitor at home. Patients are welcome to bring a current medication list and questions.','assets/img/Dr_John_Smith.jpg'),
('Dr. Priya Nair','drnair','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','drnair@clinic.test','Dental','BDS (Manipal), MDS Orthodontics','English, Tamil, Hindi','Dr. Nair is a restorative dentist and orthodontist who takes time to explain each treatment option. Her calm, preventive approach helps nervous patients feel comfortable, while her experience with families and young adults supports healthy smiles at every stage of life. She discusses the reasons for a recommended procedure and answers questions before care begins.\n\nPatients see Dr. Nair for routine examinations, tooth or gum concerns, restorative work and orthodontic assessment. She encourages regular preventive visits and gives practical guidance on caring for teeth between appointments. When treatment involves more than one step, she explains the sequence and expected follow-up so patients can plan around work, school and family commitments. Patients can bring prior dental records or note current concerns.','assets/img/Dr_Priya_Nair.jpg'),
('Dr. Wei Ming Tan','drtan','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','drtan@clinic.test','Paediatrics','MBBS (NTU), MRCPCH (UK)','English, Mandarin, Hokkien','Dr. Tan is a paediatrician who looks after newborns, school-age children and teenagers. He combines careful assessment with clear guidance for parents, and has a particular interest in childhood asthma, nutrition and supporting healthy development through the changing school years. He welcomes questions from caregivers and explains how a child''s symptoms and history inform the assessment.\n\nFamilies can book for an illness review, a growth or development check, or follow-up for an ongoing condition such as asthma. Dr. Tan discusses practical steps parents can use at home and when to seek further care. He aims to make visits comfortable for children while giving caregivers a clear account of the plan and any signs that should prompt another review. Please bring relevant health records and note any recent changes.','assets/img/Dr_Wei_Ming_Tan.jpg'),
('Dr. Sarah Lim','drlim','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','drlim@clinic.test','Dermatology','MBBS (NUS), MRCP (UK), Dip Dermatology (Singapore)','English, Mandarin, Cantonese','Dr. Lim treats common and complex skin conditions, including eczema, acne, pigmentation and persistent rashes. She considers an accurate diagnosis and a manageable routine equally important, so consultations include practical skin care advice that takes a patient''s daily life into account. She explains what to watch for and how each treatment option is used.\n\nPatients can book when a skin concern keeps returning, has changed or has not settled with initial care. Dr. Lim also reviews hair and nail concerns and helps patients understand when follow-up is useful. During the visit, she asks about previous treatments and their effects, then discusses a plan that can be followed consistently and reviewed if symptoms continue. A list of current medicines and previous treatments can guide the discussion.','assets/img/Dr_Sarah_Lim.jpg'),
('Dr. Arjun Rao','drrao','$2y$10$eDfjtikHZUYsU6TuU0bsOORvSWjgXjXWbhTO9LIFn9Z0B5vvm15Ia','drrao@clinic.test','Physiotherapy','BSc Physiotherapy (SIT), MSc Sports Rehabilitation (Edinburgh)','English, Tamil, Malay','Dr. Rao is a physiotherapist specialising in sports injuries, post-operative recovery and persistent back and neck pain. He builds progressive exercise plans around each patient''s goals, supporting a safe return to work, recreation and everyday movement. Assessment includes how pain affects activity, strength and mobility, so the plan addresses the tasks a patient wants to resume.\n\nPatients can book after an injury or operation, or when discomfort limits movement over time. Dr. Rao reviews progress with patients and adjusts exercises as strength and confidence improve. He provides guidance on pacing activity and practising movements between visits, with the aim of helping each person manage recovery as part of their normal routine. Patients can bring relevant reports and note which activities remain difficult.','assets/img/Dr_Arjun_Rao.jpg');

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
INSERT INTO `slots` (`DoctorID`,`SlotDateTime`,`CreatedAt`,`Status`)
WITH RECURSIVE days AS (
  SELECT DATE(@seed_now) - INTERVAL 30 DAY AS d
  UNION ALL SELECT d + INTERVAL 1 DAY FROM days WHERE d < DATE(@seed_now) + INTERVAL 29 DAY
), times AS (
  SELECT '09:00:00' AS t UNION ALL SELECT '09:30:00' UNION ALL SELECT '10:00:00'
  UNION ALL SELECT '10:30:00' UNION ALL SELECT '11:00:00' UNION ALL SELECT '11:30:00'
  UNION ALL SELECT '12:00:00' UNION ALL SELECT '12:30:00' UNION ALL SELECT '14:00:00'
  UNION ALL SELECT '14:30:00' UNION ALL SELECT '15:00:00' UNION ALL SELECT '15:30:00'
  UNION ALL SELECT '16:00:00' UNION ALL SELECT '16:30:00'
)
SELECT d.DoctorID, TIMESTAMP(days.d, times.t), LEAST(@seed_now, TIMESTAMP(days.d, times.t) - INTERVAL 14 DAY), 'Available'
FROM doctor d CROSS JOIN days CROSS JOIN times
WHERE DAYOFWEEK(days.d) <> 1;

-- Doctor 2's most recent completed clinic day is fully booked.
SET @full_day = (SELECT MAX(DATE(SlotDateTime)) FROM slots WHERE DoctorID = 2 AND SlotDateTime < DATE(@seed_now));
CREATE TEMPORARY TABLE demo_full_day AS
SELECT slotID, DoctorID, SlotDateTime FROM slots WHERE DoctorID = 2 AND DATE(SlotDateTime) = @full_day;

INSERT INTO `appointment` (`DoctorID`,`PatientID`,`slotID`,`appointmentDateTime`,`CreatedAt`,`Status`,`Diagnosis`,`Prescription`,`Treatment`,`FollowUp`,`Remarks`)
SELECT DoctorID, MOD(slotID - 1, 8) + 1, slotID, SlotDateTime, SlotDateTime - INTERVAL 7 DAY, 'Completed',
       'Routine review and health screening completed.', 'Continue current medicines as directed.',
       'Lifestyle counselling and follow-up measurements recorded.', 1, 'Patient attended the scheduled review.'
FROM demo_full_day;

-- Four additional completed visits, two no-shows, one cancellation, and four
-- future bookings provide the remaining realistic appointment states.
-- Historical/pending visits have exactly seven days' lead; upcoming visits
-- have exactly eight. All 27 bookings are valid: (23 * 7 + 4 * 8) / 27 days.
CREATE TEMPORARY TABLE demo_past AS
SELECT s.slotID, s.DoctorID, s.SlotDateTime
FROM slots s
WHERE s.SlotDateTime < DATE(@seed_now) AND DATE(s.SlotDateTime) <> @full_day
ORDER BY s.SlotDateTime DESC, s.DoctorID
LIMIT 7;

INSERT INTO `appointment` (`DoctorID`,`PatientID`,`slotID`,`appointmentDateTime`,`CreatedAt`,`Status`,`Diagnosis`,`Prescription`,`Treatment`,`FollowUp`,`Remarks`)
SELECT DoctorID, MOD(slotID - 1, 8) + 1, slotID, SlotDateTime, SlotDateTime - INTERVAL 7 DAY,
       CASE WHEN ROW_NUMBER() OVER (ORDER BY SlotDateTime DESC, slotID) <= 4 THEN 'Completed'
            WHEN ROW_NUMBER() OVER (ORDER BY SlotDateTime DESC, slotID) <= 6 THEN 'No show'
            ELSE 'Cancelled' END,
       CASE WHEN ROW_NUMBER() OVER (ORDER BY SlotDateTime DESC, slotID) <= 4 THEN 'Symptoms reviewed and diagnosis recorded.' ELSE NULL END,
       CASE WHEN ROW_NUMBER() OVER (ORDER BY SlotDateTime DESC, slotID) <= 4 THEN 'Use medication as prescribed.' ELSE NULL END,
       CASE WHEN ROW_NUMBER() OVER (ORDER BY SlotDateTime DESC, slotID) <= 4 THEN 'Treatment plan discussed with the patient.' ELSE NULL END,
       CASE WHEN ROW_NUMBER() OVER (ORDER BY SlotDateTime DESC, slotID) <= 4 THEN 1 ELSE 0 END,
       CASE WHEN ROW_NUMBER() OVER (ORDER BY SlotDateTime DESC, slotID) = 7 THEN 'Cancelled by patient.' ELSE NULL END
FROM demo_past;

SET @blocked_day = (SELECT MIN(DATE(SlotDateTime)) FROM slots WHERE DoctorID = 3 AND SlotDateTime >= DATE(@seed_now) + INTERVAL 2 DAY);
UPDATE slots SET Status = 'Blocked' WHERE DoctorID = 3 AND DATE(SlotDateTime) = @blocked_day AND TIME(SlotDateTime) >= '14:00:00';

CREATE TEMPORARY TABLE demo_future AS
SELECT s.slotID, s.DoctorID, s.SlotDateTime
FROM slots s
WHERE s.SlotDateTime >= DATE(@seed_now) + INTERVAL 1 DAY
  AND s.Status = 'Available'
  AND NOT (s.DoctorID = 1 AND DATE(s.SlotDateTime) = DATE(@seed_now) + INTERVAL 1 DAY AND TIME(s.SlotDateTime) = '10:30:00')
  AND NOT (s.DoctorID = 3 AND DATE(s.SlotDateTime) = @blocked_day AND TIME(s.SlotDateTime) >= '14:00:00')
ORDER BY s.SlotDateTime, s.DoctorID
LIMIT 4;

INSERT INTO `appointment` (`DoctorID`,`PatientID`,`slotID`,`appointmentDateTime`,`CreatedAt`,`Status`,`FollowUp`,`Remarks`)
SELECT DoctorID, MOD(slotID - 1, 8) + 1, slotID, SlotDateTime, SlotDateTime - INTERVAL 8 DAY, 'Future', 0, 'Booked online for an upcoming consultation.'
FROM demo_future;

-- Two pending attendance examples owned by drsmith on the last clinic day.
-- Reuse real slots and patients; Sundays and midnight resets remain safe.
INSERT INTO `appointment` (`DoctorID`,`PatientID`,`slotID`,`appointmentDateTime`,`CreatedAt`,`Status`,`FollowUp`,`Remarks`)
SELECT s.DoctorID, CASE TIME(s.SlotDateTime) WHEN '09:00:00' THEN 1 ELSE 2 END,
       s.slotID, s.SlotDateTime, s.SlotDateTime - INTERVAL 7 DAY, 'Future', 0,
       CONCAT(CHAR(30), 'clinic-remarks:1:',
         CASE TIME(s.SlotDateTime)
           WHEN '09:00:00' THEN '{"reason":"Review persistent cough.","doctor_remarks":"","legacy":null}'
           ELSE '{"reason":"Review recurring headaches.","doctor_remarks":"","legacy":null}' END)
FROM slots s JOIN doctor d ON d.DoctorID = s.DoctorID
WHERE d.User = 'drsmith' AND DATE(s.SlotDateTime) = @full_day
  AND TIME(s.SlotDateTime) IN ('09:00:00', '09:30:00');

UPDATE slots s INNER JOIN appointment a ON a.slotID = s.slotID SET s.Status = 'Booked';

-- Two synthetic outbox examples for the first future appointment. They were
-- logged as fixtures only; no delivery was attempted during seeding.
INSERT INTO `notifications` (`sender`,`recipient`,`Subject`,`Body`,`appointmentID`,`deliveryStatus`,`SentAt`)
SELECT 'clinic@example.local', p.Email, 'Demo appointment notice',
       CONCAT('Sample log entry for ', p.FullName, '''s appointment with ', d.FullName,
              ' on ', DATE(a.appointmentDateTime), ' at ', TIME(a.appointmentDateTime),
              '. This fixture was not delivered.'),
       a.appointmentID, 'logged', @seed_now - INTERVAL 1 SECOND
FROM `appointment` a
JOIN `patient` p ON p.PatientID = a.PatientID
JOIN `doctor` d ON d.DoctorID = a.DoctorID
WHERE a.Status = 'Future'
ORDER BY a.appointmentID
LIMIT 1;

INSERT INTO `notifications` (`sender`,`recipient`,`Subject`,`Body`,`appointmentID`,`deliveryStatus`,`SentAt`)
SELECT 'clinic@example.local', d.Email, 'Demo appointment notice',
       CONCAT('Sample log entry for ', p.FullName, '''s appointment with ', d.FullName,
              ' on ', DATE(a.appointmentDateTime), ' at ', TIME(a.appointmentDateTime),
              '. This fixture was not delivered.'),
       a.appointmentID, 'logged', @seed_now
FROM `appointment` a
JOIN `patient` p ON p.PatientID = a.PatientID
JOIN `doctor` d ON d.DoctorID = a.DoctorID
WHERE a.Status = 'Future'
ORDER BY a.appointmentID
LIMIT 1;

DROP TEMPORARY TABLE demo_full_day;
DROP TEMPORARY TABLE demo_past;
DROP TEMPORARY TABLE demo_future;

SET FOREIGN_KEY_CHECKS = 1;
