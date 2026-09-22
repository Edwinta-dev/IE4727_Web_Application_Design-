-- ie4727db base schema. This is the cleaned dump adopted by the project.
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET FOREIGN_KEY_CHECKS = 0;
CREATE DATABASE IF NOT EXISTS `ie4727db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `ie4727db`;

DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `appointment`;
DROP TABLE IF EXISTS `slots`;
DROP TABLE IF EXISTS `patient`;
DROP TABLE IF EXISTS `doctor`;

CREATE TABLE `doctor` (
  `DoctorID` INT NOT NULL AUTO_INCREMENT,
  `FullName` VARCHAR(100) NOT NULL,
  `User` VARCHAR(100) NOT NULL,
  `HashPass` VARCHAR(255) NOT NULL,
  `Email` VARCHAR(100) NOT NULL,
  `Specialty` VARCHAR(80) DEFAULT NULL,
  `Qualifications` VARCHAR(255) DEFAULT NULL,
  `Languages` VARCHAR(120) DEFAULT NULL,
  `WriteUp` MEDIUMTEXT DEFAULT NULL,
  `ImageURL` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`DoctorID`),
  UNIQUE KEY `uq_doctor_user` (`User`),
  UNIQUE KEY `uq_doctor_email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `patient` (
  `PatientID` INT NOT NULL AUTO_INCREMENT,
  `FullName` VARCHAR(100) NOT NULL,
  `User` VARCHAR(100) NOT NULL,
  `HashPass` VARCHAR(255) NOT NULL,
  `Email` VARCHAR(100) NOT NULL,
  `Gender` ENUM('Male','Female','Other') DEFAULT NULL,
  `Phone` VARCHAR(20) DEFAULT NULL,
  `Allergies` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`Allergies`)),
  PRIMARY KEY (`PatientID`),
  UNIQUE KEY `uq_patient_user` (`User`),
  UNIQUE KEY `uq_patient_email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `slots` (
  `slotID` INT NOT NULL AUTO_INCREMENT,
  `DoctorID` INT NOT NULL,
  `SlotDateTime` DATETIME NOT NULL,
  `CreatedAt` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  `Status` ENUM('Available','Booked','Blocked') NOT NULL DEFAULT 'Available',
  PRIMARY KEY (`slotID`),
  UNIQUE KEY `uq_slot` (`DoctorID`,`SlotDateTime`),
  KEY `idx_slot_lookup` (`DoctorID`,`SlotDateTime`,`Status`),
  CONSTRAINT `fk_slots_doctor` FOREIGN KEY (`DoctorID`) REFERENCES `doctor` (`DoctorID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `appointment` (
  `appointmentID` INT NOT NULL AUTO_INCREMENT,
  `DoctorID` INT NOT NULL,
  `PatientID` INT NOT NULL,
  `slotID` INT DEFAULT NULL,
  `appointmentDateTime` DATETIME NOT NULL,
  `CreatedAt` DATETIME DEFAULT current_timestamp(),
  `updatedAt` TIMESTAMP NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `Status` ENUM('Future','Cancelled','No show','Completed','Rescheduled') NOT NULL DEFAULT 'Future',
  `Diagnosis` TEXT DEFAULT NULL,
  `Prescription` TEXT DEFAULT NULL,
  `Treatment` TEXT DEFAULT NULL,
  `FollowUp` TINYINT(1) DEFAULT 0,
  `Remarks` TEXT DEFAULT NULL,
  PRIMARY KEY (`appointmentID`),
  KEY `idx_appt_doctor_dt` (`DoctorID`,`appointmentDateTime`),
  KEY `idx_appt_patient_dt` (`PatientID`,`appointmentDateTime`),
  KEY `fk_appt_slot` (`slotID`),
  CONSTRAINT `fk_appt_doctor` FOREIGN KEY (`DoctorID`) REFERENCES `doctor` (`DoctorID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appt_patient` FOREIGN KEY (`PatientID`) REFERENCES `patient` (`PatientID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appt_slot_ref` FOREIGN KEY (`slotID`) REFERENCES `slots` (`slotID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `notifications` (
  `notificationID` INT NOT NULL AUTO_INCREMENT,
  `sender` VARCHAR(100) NOT NULL,
  `recipient` VARCHAR(100) NOT NULL,
  `Subject` VARCHAR(100) NOT NULL,
  `Body` TEXT NOT NULL,
  `appointmentID` INT DEFAULT NULL,
  `deliveryStatus` ENUM('logged','sent','failed') NOT NULL DEFAULT 'logged',
  `SentAt` DATETIME DEFAULT current_timestamp(),
  PRIMARY KEY (`notificationID`),
  KEY `fk_notif_appt` (`appointmentID`),
  CONSTRAINT `fk_notif_appt` FOREIGN KEY (`appointmentID`) REFERENCES `appointment` (`appointmentID`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Demo credentials use Password123. Slots are intentionally materialised relative to today.
INSERT INTO `doctor` (`FullName`,`User`,`HashPass`,`Email`,`Specialty`,`Qualifications`,`Languages`,`WriteUp`,`ImageURL`) VALUES
('Dr. John Smith','drsmith','$2y$12$wX0H8ZbgBwJ5oN5s5YBj2.ugWP3VaiExp/AtezDcaJTkDkXsarfqm','drsmith@clinic.test','General Practice','MBBS (NUS), MRCP (UK)','English, Mandarin','A family physician with over fifteen years in primary care.','assets/img/dr-smith.jpg'),
('Dr. Priya Nair','drnair','$2y$12$8s5qsRSwDIkk1N8.DTq/qeS1V5hKcO2JqruX0XSZkCJMd5g9yCGva','drnair@clinic.test','Dental','BDS (Manipal), MDS Orthodontics','English, Tamil, Hindi','Dr. Nair leads the dental practice.','assets/img/dr-nair.jpg'),
('Dr. Wei Ming Tan','drtan','$2y$12$cLvQciWzmkfuOf7DkBwfKudCu1b1ra3osBypmlsiWuafP55BuIi5a','drtan@clinic.test','Paediatrics','MBBS (NTU), MRCPCH (UK)','English, Mandarin, Hokkien','A paediatrician caring for newborns through adolescents.','assets/img/dr-tan.jpg'),
('Dr. Sarah Lim','drlim','$2y$12$mIHe6ziKW0TDcRCRtaZCcO63jxsI1o34BFmzuHovsSUla7HrW4NRy','drlim@clinic.test','Dermatology','MBBS (NUS), MRCP, Dip Dermatology','English, Mandarin, Cantonese','Dr. Lim treats the full range of skin conditions.','assets/img/dr-lim.jpg'),
('Dr. Arjun Rao','drrao','$2y$12$3KqSIz9LoacwNSy9bx1oouXIm6TdElCvEMH5gHALLozpmyA6g8NU.','drrao@clinic.test','Physiotherapy','BSc Physiotherapy (SIT), MSc Sports Rehab','English, Tamil, Malay','A physiotherapist specialising in rehabilitation.','assets/img/dr-rao.jpg');

INSERT INTO `patient` (`FullName`,`User`,`HashPass`,`Email`,`Gender`,`Phone`,`Allergies`) VALUES
('Alex Tan','alextan','$2y$12$UNgGhEv6MYFq87zG7KhOf.wtmbAhgKB01YXPQyBPQofYJyX/6gtt6','alex.tan@example.com','Male','91112223','["Penicillin"]'),
('Bethany Ong','bethong','$2y$12$89xrxjCzURljrr6HRn9oF.Ak9WENjf28PFGWZseMlBv1.PVRFeqtC','beth.ong@example.com','Female','92223334','[]'),
('Chandran Kumar','chandrank','$2y$12$KuiJgar3wtBPDpMiRjfL6.7VNvm.eiaZysWzjrg43hc5Cc7bm2no2','chandran.k@example.com','Male','93334445','["Aspirin","Shellfish"]'),
('Diana Lee','dianalee','$2y$12$RFgnQR1sgV00Hb2b76OyMu/kc3Rs5vpi3CxhVQ3bV03YL0eEPwd2e','diana.lee@example.com','Female','94445556','[]'),
('Elijah Wong','elijahw','$2y$12$XzEyxElY4IzmVTSIdCV9IO4aRQi0y/L4jRlMzrr1TplF223bqZGhy','elijah.w@example.com','Male','95556667','["Latex"]'),
('Farah Ismail','farahi','$2y$12$N6AalgbN0m3o10C2I7/.IuERUNlWlCwp609KHXpkhXSGM5C3r/.jO','farah.i@example.com','Female','96667778','["Peanuts"]'),
('Gerald Sim','geralds','$2y$12$KzpOpam7duC2nHZLiXULhOeBf.FBWlx1UZqqkEYsaE2gGu7v/nYlq','gerald.s@example.com','Male','97778889','[]'),
('Hui Ling Chua','huilingc','$2y$12$4bDtSGEi7WW5WR1gEjuijuNBqi0EKgCdA8vYKBDtkJnq025XAx0hC','huiling.c@example.com','Female','98889990','["Pollen","Dust mites"]');

INSERT INTO `slots` (`DoctorID`,`SlotDateTime`,`Status`)
WITH RECURSIVE days AS (
  SELECT CURDATE() AS d
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

SET FOREIGN_KEY_CHECKS = 1;
