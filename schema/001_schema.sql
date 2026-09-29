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

SET FOREIGN_KEY_CHECKS = 1;
