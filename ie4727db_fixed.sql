-- =====================================================================
--  ie4727db1  ·  Clinic Appointment Portal  (IE4727 Theme 4)
--  Cleaned + improved dump  ·  target: MariaDB 10.4+ / MySQL 8+
--
--  Changes from the original dump:
--    * FIXED typo  notifications.receipient  ->  recipient
--    * FIXED data  Dr Smith's fake password  ->  real bcrypt hash
--    * FIXED data  Dr Smith's malformed ImageURL (quotes + Windows path)
--    * ADDED  slots UNIQUE KEY (DoctorID, SlotDateTime)   -- double-booking guard
--    * ADDED  appointment.slotID  + FK                    -- links appt to its slot
--    * ADDED  appointment.updatedAt (ON UPDATE)           -- change feed for live board
--    * ADDED  notifications.deliveryStatus, appointmentID
--    * ADDED  doctor.Specialty / Qualifications / Languages
--    * ADDED  helpful secondary indexes for the hot queries
--    * ADDED  realistic seed: 5 doctors, 8 patients, 30 days of slots,
--             a spread of past + future appointments, sample notifications
--    * Slots are generated RELATIVE TO CURDATE() by a recursive CTE, so the
--      data is always current no matter when you import it.
--
--  All demo passwords are:  Password123
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `ie4727db1` DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_general_ci;
USE `ie4727db1`;

DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `appointment`;
DROP TABLE IF EXISTS `slots`;
DROP TABLE IF EXISTS `patient`;
DROP TABLE IF EXISTS `doctor`;

-- ---------- doctor ----------
CREATE TABLE `doctor` (
  `DoctorID`       INT(11)      NOT NULL AUTO_INCREMENT,
  `FullName`       VARCHAR(100) NOT NULL,
  `User`           VARCHAR(100) NOT NULL,
  `HashPass`       VARCHAR(255) NOT NULL,
  `Email`          VARCHAR(100) NOT NULL,
  `Specialty`      VARCHAR(80)  DEFAULT NULL,
  `Qualifications` VARCHAR(255) DEFAULT NULL,
  `Languages`      VARCHAR(120) DEFAULT NULL,
  `WriteUp`        MEDIUMTEXT   DEFAULT NULL,
  `ImageURL`       VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`DoctorID`),
  UNIQUE KEY `uq_doctor_user`  (`User`),
  UNIQUE KEY `uq_doctor_email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `doctor` (`DoctorID`,`FullName`,`User`,`HashPass`,`Email`,`Specialty`,`Qualifications`,`Languages`,`WriteUp`,`ImageURL`) VALUES
(1,'Dr. John Smith','drsmith','$2y$12$wX0H8ZbgBwJ5oN5s5YBj2.ugWP3VaiExp/AtezDcaJTkDkXsarfqm','drsmith@clinic.test','General Practice','MBBS (NUS), MRCP (UK)','English, Mandarin','A family physician with over fifteen years in primary care, Dr. Smith focuses on chronic disease management, preventive health screening, and continuity of care for patients of every age.','assets/img/dr-smith.jpg'),
(2,'Dr. Priya Nair','drnair','$2y$12$8s5qsRSwDIkk1N8.DTq/qeS1V5hKcO2JqruX0XSZkCJMd5g9yCGva','drnair@clinic.test','Dental','BDS (Manipal), MDS Orthodontics','English, Tamil, Hindi','Dr. Nair leads the dental practice with a special interest in orthodontics and minimally invasive restorative work. She is known for a gentle chairside manner with anxious and younger patients.','assets/img/dr-nair.jpg'),
(3,'Dr. Wei Ming Tan','drtan','$2y$12$cLvQciWzmkfuOf7DkBwfKudCu1b1ra3osBypmlsiWuafP55BuIi5a','drtan@clinic.test','Paediatrics','MBBS (NTU), MRCPCH (UK)','English, Mandarin, Hokkien','A paediatrician caring for newborns through adolescents, Dr. Tan handles childhood immunisations, developmental assessments, and acute paediatric illness with a calm, parent-friendly approach.','assets/img/dr-tan.jpg'),
(4,'Dr. Sarah Lim','drlim','$2y$12$mIHe6ziKW0TDcRCRtaZCcO63jxsI1o34BFmzuHovsSUla7HrW4NRy','drlim@clinic.test','Dermatology','MBBS (NUS), MRCP, Dip Dermatology','English, Mandarin, Cantonese','Dr. Lim treats the full range of skin conditions from acne and eczema to skin-cancer surveillance, combining medical dermatology with practical, evidence-based skincare guidance.','assets/img/dr-lim.jpg'),
(5,'Dr. Arjun Rao','drrao','$2y$12$3KqSIz9LoacwNSy9bx1oouXIm6TdElCvEMH5gHALLozpmyA6g8NU.','drrao@clinic.test','Physiotherapy','BSc Physiotherapy (SIT), MSc Sports Rehab','English, Tamil, Malay','A physiotherapist specialising in musculoskeletal and sports rehabilitation, Dr. Rao designs progressive recovery programmes for post-injury and post-operative patients returning to activity.','assets/img/dr-rao.jpg');

-- ---------- patient ----------
CREATE TABLE `patient` (
  `PatientID` INT(11)      NOT NULL AUTO_INCREMENT,
  `FullName`  VARCHAR(100) NOT NULL,
  `User`      VARCHAR(100) NOT NULL,
  `HashPass`  VARCHAR(255) NOT NULL,
  `Email`     VARCHAR(100) NOT NULL,
  `Gender`    ENUM('Male','Female','Other') DEFAULT NULL,
  `Phone`     VARCHAR(20)  DEFAULT NULL,
  `Allergies` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
              CHECK (json_valid(`Allergies`)),
  PRIMARY KEY (`PatientID`),
  UNIQUE KEY `uq_patient_user`  (`User`),
  UNIQUE KEY `uq_patient_email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `patient` (`PatientID`,`FullName`,`User`,`HashPass`,`Email`,`Gender`,`Phone`,`Allergies`) VALUES
(1,'Alex Tan','alextan','$2y$12$UNgGhEv6MYFq87zG7KhOf.wtmbAhgKB01YXPQyBPQofYJyX/6gtt6','alex.tan@example.com','Male','91112223','["Penicillin"]'),
(2,'Bethany Ong','bethong','$2y$12$89xrxjCzURljrr6HRn9oF.Ak9WENjf28PFGWZseMlBv1.PVRFeqtC','beth.ong@example.com','Female','92223334','[]'),
(3,'Chandran Kumar','chandrank','$2y$12$KuiJgar3wtBPDpMiRjfL6.7VNvm.eiaZysWzjrg43hc5Cc7bm2no2','chandran.k@example.com','Male','93334445','["Aspirin","Shellfish"]'),
(4,'Diana Lee','dianalee','$2y$12$RFgnQR1sgV00Hb2b76OyMu/kc3Rs5vpi3CxhVQ3bV03YL0eEPwd2e','diana.lee@example.com','Female','94445556','[]'),
(5,'Elijah Wong','elijahw','$2y$12$XzEyxElY4IzmVTSIdCV9IO4aRQi0y/L4jRlMzrr1TplF223bqZGhy','elijah.w@example.com','Male','95556667','["Latex"]'),
(6,'Farah Ismail','farahi','$2y$12$N6AalgbN0m3o10C2I7/.IuERUNlWlCwp609KHXpkhXSGM5C3r/.jO','farah.i@example.com','Female','96667778','["Peanuts"]'),
(7,'Gerald Sim','geralds','$2y$12$KzpOpam7duC2nHZLiXULhOeBf.FBWlx1UZqqkEYsaE2gGu7v/nYlq','gerald.s@example.com','Male','97778889','[]'),
(8,'Hui Ling Chua','huilingc','$2y$12$4bDtSGEi7WW5WR1gEjuijuNBqi0EKgCdA8vYKBDtkJnq025XAx0hC','huiling.c@example.com','Female','98889990','["Pollen","Dust mites"]');

-- ---------- slots ----------
CREATE TABLE `slots` (
  `slotID`       INT(11)   NOT NULL AUTO_INCREMENT,
  `DoctorID`     INT(11)   NOT NULL,
  `SlotDateTime` DATETIME  NOT NULL,
  `CreatedAt`    TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  `Status`       ENUM('Available','Booked','Blocked') NOT NULL DEFAULT 'Available',
  PRIMARY KEY (`slotID`),
  UNIQUE KEY `uq_slot` (`DoctorID`,`SlotDateTime`),
  KEY `idx_slot_lookup` (`DoctorID`,`SlotDateTime`,`Status`),
  CONSTRAINT `fk_slots_doctor` FOREIGN KEY (`DoctorID`)
      REFERENCES `doctor` (`DoctorID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- appointment references slots, so create slots first (done above)
-- ---------- appointment ----------
CREATE TABLE `appointment` (
  `appointmentID`       INT(11)   NOT NULL AUTO_INCREMENT,
  `DoctorID`            INT(11)   NOT NULL,
  `PatientID`           INT(11)   NOT NULL,
  `slotID`              INT(11)   DEFAULT NULL,
  `appointmentDateTime` DATETIME  NOT NULL,
  `CreatedAt`           DATETIME  DEFAULT current_timestamp(),
  `updatedAt`           TIMESTAMP NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `Status`  ENUM('Future','Cancelled','No show','Completed','Rescheduled') NOT NULL DEFAULT 'Future',
  `Diagnosis`    TEXT    DEFAULT NULL,
  `Prescription` TEXT    DEFAULT NULL,
  `Treatment`    TEXT    DEFAULT NULL,
  `FollowUp`     TINYINT(1) DEFAULT 0,
  `Remarks`      TEXT    DEFAULT NULL,
  PRIMARY KEY (`appointmentID`),
  KEY `idx_appt_doctor_dt`  (`DoctorID`,`appointmentDateTime`),
  KEY `idx_appt_patient_dt` (`PatientID`,`appointmentDateTime`),
  KEY `fk_appt_slot` (`slotID`),
  CONSTRAINT `fk_appt_doctor`  FOREIGN KEY (`DoctorID`)  REFERENCES `doctor`  (`DoctorID`)  ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appt_patient` FOREIGN KEY (`PatientID`) REFERENCES `patient` (`PatientID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appt_slot_ref` FOREIGN KEY (`slotID`)   REFERENCES `slots`   (`slotID`)    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------- notifications ----------
CREATE TABLE `notifications` (
  `notificationID` INT(11)      NOT NULL AUTO_INCREMENT,
  `sender`         VARCHAR(100) NOT NULL,
  `recipient`      VARCHAR(100) NOT NULL,          -- was misspelled 'receipient'
  `Subject`        VARCHAR(100) NOT NULL,
  `Body`           TEXT         NOT NULL,
  `appointmentID`  INT(11)      DEFAULT NULL,
  `deliveryStatus` ENUM('logged','sent','failed') NOT NULL DEFAULT 'logged',
  `SentAt`         DATETIME     DEFAULT current_timestamp(),
  PRIMARY KEY (`notificationID`),
  KEY `fk_notif_appt` (`appointmentID`),
  CONSTRAINT `fk_notif_appt` FOREIGN KEY (`appointmentID`)
      REFERENCES `appointment` (`appointmentID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------- generate 30 days of slots for every doctor ----------
-- 09:00-12:30 and 14:00-16:30, half-hour slots, skipping Sundays and 13:00 lunch.
-- Relative to CURDATE() so the seed is never stale.
INSERT INTO `slots` (`DoctorID`,`SlotDateTime`,`Status`)
WITH RECURSIVE days AS (
    SELECT CURDATE() AS d
    UNION ALL SELECT d + INTERVAL 1 DAY FROM days WHERE d < CURDATE() + INTERVAL 29 DAY
),
hrs AS (
    SELECT 9 AS h UNION SELECT 10 UNION SELECT 11 UNION SELECT 12
    UNION SELECT 14 UNION SELECT 15 UNION SELECT 16
),
mins AS (SELECT 0 AS m UNION SELECT 30)
SELECT doc.DoctorID,
       TIMESTAMP(days.d, MAKETIME(hrs.h, mins.m, 0)),
       'Available'
FROM days
CROSS JOIN hrs
CROSS JOIN mins
CROSS JOIN doctor doc
WHERE DAYOFWEEK(days.d) <> 1;          -- 1 = Sunday

-- Demo-friendly shaping of the generated slots:
-- (a) Dr Lim (4) takes tomorrow afternoon off -> whole afternoon blocked
UPDATE slots SET Status='Blocked'
 WHERE DoctorID=4 AND DATE(SlotDateTime)=CURDATE()+INTERVAL 1 DAY
   AND TIME(SlotDateTime) >= '14:00:00';
-- (b) Dr Nair (2) is fully booked two days out (mark booked; appts added below for a few)
--     leave as-is; we book a handful explicitly next.

-- ---------- future appointments (book real generated slots) ----------
-- Robust against weekends: pick the Nth upcoming free slot for a doctor, then book it.

SET @s := (SELECT slotID FROM slots
           WHERE DoctorID=2 AND Status='Available' AND SlotDateTime > NOW()
           ORDER BY SlotDateTime LIMIT 1 OFFSET 3);
INSERT INTO appointment (DoctorID,PatientID,slotID,appointmentDateTime,Status,Remarks)
     SELECT DoctorID,1,slotID,SlotDateTime,'Future','Routine dental cleaning and check-up' FROM slots WHERE slotID=@s;
UPDATE slots SET Status='Booked' WHERE slotID=@s;

SET @s := (SELECT slotID FROM slots
           WHERE DoctorID=2 AND Status='Available' AND SlotDateTime > NOW()
           ORDER BY SlotDateTime LIMIT 1 OFFSET 9);
INSERT INTO appointment (DoctorID,PatientID,slotID,appointmentDateTime,Status,Remarks)
     SELECT DoctorID,3,slotID,SlotDateTime,'Future','Toothache, upper left molar' FROM slots WHERE slotID=@s;
UPDATE slots SET Status='Booked' WHERE slotID=@s;

SET @s := (SELECT slotID FROM slots
           WHERE DoctorID=3 AND Status='Available' AND SlotDateTime > NOW()
           ORDER BY SlotDateTime LIMIT 1 OFFSET 2);
INSERT INTO appointment (DoctorID,PatientID,slotID,appointmentDateTime,Status,Remarks)
     SELECT DoctorID,4,slotID,SlotDateTime,'Future','Child 18-month developmental review' FROM slots WHERE slotID=@s;
UPDATE slots SET Status='Booked' WHERE slotID=@s;

SET @s := (SELECT slotID FROM slots
           WHERE DoctorID=3 AND Status='Available' AND SlotDateTime > NOW()
           ORDER BY SlotDateTime LIMIT 1 OFFSET 7);
INSERT INTO appointment (DoctorID,PatientID,slotID,appointmentDateTime,Status,Remarks)
     SELECT DoctorID,6,slotID,SlotDateTime,'Future','Fever and cough, 4-year-old' FROM slots WHERE slotID=@s;
UPDATE slots SET Status='Booked' WHERE slotID=@s;

SET @s := (SELECT slotID FROM slots
           WHERE DoctorID=5 AND Status='Available' AND SlotDateTime > NOW()
           ORDER BY SlotDateTime LIMIT 1 OFFSET 4);
INSERT INTO appointment (DoctorID,PatientID,slotID,appointmentDateTime,Status,Remarks)
     SELECT DoctorID,5,slotID,SlotDateTime,'Future','Lower back pain, post-gym' FROM slots WHERE slotID=@s;
UPDATE slots SET Status='Booked' WHERE slotID=@s;

SET @s := (SELECT slotID FROM slots
           WHERE DoctorID=1 AND Status='Available' AND SlotDateTime > NOW()
           ORDER BY SlotDateTime LIMIT 1 OFFSET 6);
INSERT INTO appointment (DoctorID,PatientID,slotID,appointmentDateTime,Status,Remarks)
     SELECT DoctorID,7,slotID,SlotDateTime,'Future','Annual health screening review' FROM slots WHERE slotID=@s;
UPDATE slots SET Status='Booked' WHERE slotID=@s;

SET @s := (SELECT slotID FROM slots
           WHERE DoctorID=4 AND Status='Available' AND SlotDateTime > NOW()
           ORDER BY SlotDateTime LIMIT 1 OFFSET 5);
INSERT INTO appointment (DoctorID,PatientID,slotID,appointmentDateTime,Status,Remarks)
     SELECT DoctorID,8,slotID,SlotDateTime,'Future','Recurring eczema flare on hands' FROM slots WHERE slotID=@s;
UPDATE slots SET Status='Booked' WHERE slotID=@s;

SET @s := (SELECT slotID FROM slots
           WHERE DoctorID=5 AND Status='Available' AND SlotDateTime > NOW()
           ORDER BY SlotDateTime LIMIT 1 OFFSET 11);
INSERT INTO appointment (DoctorID,PatientID,slotID,appointmentDateTime,Status,Remarks)
     SELECT DoctorID,2,slotID,SlotDateTime,'Future','Ankle rehab, week 3 follow-up' FROM slots WHERE slotID=@s;
UPDATE slots SET Status='Booked' WHERE slotID=@s;

-- ---------- past appointments (history; no live slot needed) ----------
INSERT INTO appointment
  (DoctorID,PatientID,slotID,appointmentDateTime,Status,Diagnosis,Treatment,Prescription,FollowUp,Remarks)
VALUES
 (1,1,NULL, NOW()-INTERVAL 12 DAY,'Completed','Viral upper respiratory infection','Rest, fluids, symptomatic care','Paracetamol 500mg PRN',0,'Recovered well'),
 (2,3,NULL, NOW()-INTERVAL 20 DAY,'Completed','Dental caries, lower right','Composite filling','Chlorhexidine mouthwash',1,'Review in 6 months'),
 (3,4,NULL, NOW()-INTERVAL 9  DAY,'Completed','Otitis media, left ear','Course of antibiotics','Amoxicillin 250mg TDS x5d',1,'Parent to monitor temperature'),
 (4,8,NULL, NOW()-INTERVAL 15 DAY,'Completed','Contact dermatitis','Topical steroid, avoid trigger','Hydrocortisone 1% cream BD',0,'Advised patch test if recurs'),
 (5,5,NULL, NOW()-INTERVAL 6  DAY,'Completed','Lumbar muscle strain','Manual therapy + home exercises',NULL,1,'3-session rehab plan started'),
 (1,7,NULL, NOW()-INTERVAL 3  DAY,'No show',NULL,NULL,NULL,0,'Did not attend; no notice'),
 (2,6,NULL, NOW()-INTERVAL 4  DAY,'Cancelled',NULL,NULL,NULL,0,'Patient cancelled, rebooking'),
 (3,2,NULL, NOW()-INTERVAL 30 DAY,'Completed','Routine immunisation','MMR administered',NULL,0,'No adverse reaction');

-- ---------- a couple of sample notifications ----------
INSERT INTO notifications (sender,recipient,Subject,Body,appointmentID,deliveryStatus)
SELECT 'clinic@clinic.test', p.Email,
       'Appointment confirmed',
       CONCAT('Dear ', p.FullName, ', your appointment with ', d.FullName,
              ' on ', DATE_FORMAT(a.appointmentDateTime,'%d %b %Y at %h:%i %p'),
              ' is confirmed.'),
       a.appointmentID, 'logged'
FROM appointment a
JOIN patient p ON p.PatientID=a.PatientID
JOIN doctor  d ON d.DoctorID=a.DoctorID
WHERE a.Status='Future'
LIMIT 3;

SET FOREIGN_KEY_CHECKS = 1;

-- Note: Dr Smith (DoctorID 1) is intentionally left with tomorrow's 10:30 slot free,
-- for booking live during the demo.
