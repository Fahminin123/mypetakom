-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 12, 2025 at 05:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mypetakom`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `AdminID` varchar(10) NOT NULL,
  `AdminName` varchar(50) NOT NULL,
  `AdminEmail` varchar(50) NOT NULL,
  `AdminPassword` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `admin`:
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `AttendanceID` varchar(10) NOT NULL,
  `StudentID` varchar(10) NOT NULL,
  `SlotID` varchar(10) NOT NULL,
  `CheckInTime` time NOT NULL,
  `ActualGeolocation` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `attendance`:
--   `StudentID`
--       `student` -> `StudentID`
--   `SlotID`
--       `attendanceslot` -> `SlotID`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendanceslot`
--

CREATE TABLE `attendanceslot` (
  `SlotID` varchar(10) NOT NULL,
  `EventID` varchar(10) NOT NULL,
  `QRCodeID` varchar(10) NOT NULL,
  `Geolocation` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `attendanceslot`:
--   `EventID`
--       `event` -> `EventID`
--   `QRCodeID`
--       `qrcode` -> `QRCodeID`
--

-- --------------------------------------------------------

--
-- Table structure for table `committeerole`
--

CREATE TABLE `committeerole` (
  `CR_ID` varchar(10) NOT NULL,
  `CR_Desc` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `committeerole`:
--

-- --------------------------------------------------------

--
-- Table structure for table `event`
--

CREATE TABLE `event` (
  `EventID` varchar(10) NOT NULL,
  `StaffID` varchar(10) NOT NULL,
  `QRCodeID` varchar(10) NOT NULL,
  `EventTitle` varchar(100) NOT NULL,
  `EventDateandTime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `EventVenue` varchar(100) NOT NULL,
  `EventStatus` varchar(20) NOT NULL,
  `ApprovalLetter` blob NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `event`:
--   `StaffID`
--       `staff` -> `StaffID`
--   `QRCodeID`
--       `qrcode` -> `QRCodeID`
--

-- --------------------------------------------------------

--
-- Table structure for table `eventcommittee`
--

CREATE TABLE `eventcommittee` (
  `CommitteeID` varchar(10) NOT NULL,
  `EventID` varchar(10) NOT NULL,
  `CR_ID` varchar(10) NOT NULL,
  `StudentID` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `eventcommittee`:
--   `EventID`
--       `event` -> `EventID`
--   `CR_ID`
--       `committeerole` -> `CR_ID`
--   `StudentID`
--       `student` -> `StudentID`
--

-- --------------------------------------------------------

--
-- Table structure for table `memberapplication`
--

CREATE TABLE `memberapplication` (
  `MemberApplicationID` varchar(10) NOT NULL,
  `EventID` varchar(10) NOT NULL,
  `Status` varchar(50) NOT NULL,
  `VerifiedBy` int(11) NOT NULL,
  `VerifiedAt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `memberapplication`:
--   `EventID`
--       `event` -> `EventID`
--

-- --------------------------------------------------------

--
-- Table structure for table `merit`
--

CREATE TABLE `merit` (
  `MeritID` varchar(10) NOT NULL,
  `MeritDescription` varchar(10) NOT NULL,
  `MeritScore` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `merit`:
--

-- --------------------------------------------------------

--
-- Table structure for table `meritapplication`
--

CREATE TABLE `meritapplication` (
  `MeritApplicationID` varchar(10) NOT NULL,
  `StaffID` varchar(10) NOT NULL,
  `EventID` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `meritapplication`:
--   `EventID`
--       `event` -> `EventID`
--   `StaffID`
--       `staff` -> `StaffID`
--

-- --------------------------------------------------------

--
-- Table structure for table `meritaward`
--

CREATE TABLE `meritaward` (
  `MeritAwardID` varchar(10) NOT NULL,
  `EventID` varchar(10) NOT NULL,
  `StudentID` varchar(10) NOT NULL,
  `MeritID` varchar(10) NOT NULL,
  `CommitteeID` varchar(10) NOT NULL,
  `QRCodeID` varchar(10) NOT NULL,
  `TotalPoints` int(11) NOT NULL,
  `DateAwarded` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `meritaward`:
--   `EventID`
--       `event` -> `EventID`
--   `StudentID`
--       `student` -> `StudentID`
--   `MeritID`
--       `merit` -> `MeritID`
--   `CommitteeID`
--       `eventcommittee` -> `CommitteeID`
--   `QRCodeID`
--       `qrcode` -> `QRCodeID`
--

-- --------------------------------------------------------

--
-- Table structure for table `meritclaim`
--

CREATE TABLE `meritclaim` (
  `ClaimID` varchar(10) NOT NULL,
  `StaffID` varchar(10) NOT NULL,
  `StudentID` varchar(10) NOT NULL,
  `EventID` varchar(10) NOT NULL,
  `ProofDocument` blob NOT NULL,
  `DateSubmitted` date NOT NULL,
  `MeritClaimStatus` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `meritclaim`:
--   `StaffID`
--       `staff` -> `StaffID`
--   `StudentID`
--       `student` -> `StudentID`
--   `EventID`
--       `event` -> `EventID`
--

-- --------------------------------------------------------

--
-- Table structure for table `qrcode`
--

CREATE TABLE `qrcode` (
  `QRCodeID` varchar(10) NOT NULL,
  `EventID` varchar(10) NOT NULL,
  `Image_URL` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `qrcode`:
--   `EventID`
--       `event` -> `EventID`
--

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `StaffID` varchar(10) NOT NULL,
  `StaffName` varchar(50) NOT NULL,
  `StaffContact` int(11) NOT NULL,
  `StaffEmail` varchar(100) NOT NULL,
  `StaffPassword` varchar(255) NOT NULL,
  `Position` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff`:
--

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `StudentID` varchar(10) NOT NULL,
  `StudentName` varchar(50) NOT NULL,
  `StudentContact` int(11) NOT NULL,
  `StudentEmail` varchar(100) NOT NULL,
  `StudentPassword` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `student`:
--

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`AdminID`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`AttendanceID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `SlotID` (`SlotID`);

--
-- Indexes for table `attendanceslot`
--
ALTER TABLE `attendanceslot`
  ADD PRIMARY KEY (`SlotID`),
  ADD KEY `EventID` (`EventID`),
  ADD KEY `QRCodeID` (`QRCodeID`);

--
-- Indexes for table `committeerole`
--
ALTER TABLE `committeerole`
  ADD PRIMARY KEY (`CR_ID`);

--
-- Indexes for table `event`
--
ALTER TABLE `event`
  ADD PRIMARY KEY (`EventID`),
  ADD KEY `StaffID` (`StaffID`),
  ADD KEY `QRCodeID` (`QRCodeID`);

--
-- Indexes for table `eventcommittee`
--
ALTER TABLE `eventcommittee`
  ADD PRIMARY KEY (`CommitteeID`),
  ADD KEY `EventID` (`EventID`),
  ADD KEY `CR_ID` (`CR_ID`),
  ADD KEY `StudentID` (`StudentID`);

--
-- Indexes for table `memberapplication`
--
ALTER TABLE `memberapplication`
  ADD PRIMARY KEY (`MemberApplicationID`),
  ADD KEY `EventID` (`EventID`);

--
-- Indexes for table `merit`
--
ALTER TABLE `merit`
  ADD PRIMARY KEY (`MeritID`);

--
-- Indexes for table `meritapplication`
--
ALTER TABLE `meritapplication`
  ADD PRIMARY KEY (`MeritApplicationID`),
  ADD KEY `StaffID` (`StaffID`),
  ADD KEY `EventID` (`EventID`);

--
-- Indexes for table `meritaward`
--
ALTER TABLE `meritaward`
  ADD PRIMARY KEY (`MeritAwardID`),
  ADD KEY `EventID` (`EventID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `MeritID` (`MeritID`),
  ADD KEY `CommitteeID` (`CommitteeID`),
  ADD KEY `QRCodeID` (`QRCodeID`);

--
-- Indexes for table `meritclaim`
--
ALTER TABLE `meritclaim`
  ADD PRIMARY KEY (`ClaimID`),
  ADD KEY `StaffID` (`StaffID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `EventID` (`EventID`);

--
-- Indexes for table `qrcode`
--
ALTER TABLE `qrcode`
  ADD PRIMARY KEY (`QRCodeID`),
  ADD KEY `EventID` (`EventID`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`StaffID`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`StudentID`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`StudentID`) REFERENCES `student` (`StudentID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`SlotID`) REFERENCES `attendanceslot` (`SlotID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `attendanceslot`
--
ALTER TABLE `attendanceslot`
  ADD CONSTRAINT `attendanceslot_ibfk_1` FOREIGN KEY (`EventID`) REFERENCES `event` (`EventID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `attendanceslot_ibfk_2` FOREIGN KEY (`QRCodeID`) REFERENCES `qrcode` (`QRCodeID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `event`
--
ALTER TABLE `event`
  ADD CONSTRAINT `event_ibfk_1` FOREIGN KEY (`StaffID`) REFERENCES `staff` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `event_ibfk_2` FOREIGN KEY (`QRCodeID`) REFERENCES `qrcode` (`QRCodeID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `eventcommittee`
--
ALTER TABLE `eventcommittee`
  ADD CONSTRAINT `eventcommittee_ibfk_1` FOREIGN KEY (`EventID`) REFERENCES `event` (`EventID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `eventcommittee_ibfk_2` FOREIGN KEY (`CR_ID`) REFERENCES `committeerole` (`CR_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `eventcommittee_ibfk_3` FOREIGN KEY (`StudentID`) REFERENCES `student` (`StudentID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `memberapplication`
--
ALTER TABLE `memberapplication`
  ADD CONSTRAINT `memberapplication_ibfk_1` FOREIGN KEY (`EventID`) REFERENCES `event` (`EventID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `meritapplication`
--
ALTER TABLE `meritapplication`
  ADD CONSTRAINT `meritapplication_ibfk_1` FOREIGN KEY (`EventID`) REFERENCES `event` (`EventID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `meritapplication_ibfk_2` FOREIGN KEY (`StaffID`) REFERENCES `staff` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `meritaward`
--
ALTER TABLE `meritaward`
  ADD CONSTRAINT `meritaward_ibfk_1` FOREIGN KEY (`EventID`) REFERENCES `event` (`EventID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `meritaward_ibfk_2` FOREIGN KEY (`StudentID`) REFERENCES `student` (`StudentID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `meritaward_ibfk_3` FOREIGN KEY (`MeritID`) REFERENCES `merit` (`MeritID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `meritaward_ibfk_4` FOREIGN KEY (`CommitteeID`) REFERENCES `eventcommittee` (`CommitteeID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `meritaward_ibfk_5` FOREIGN KEY (`QRCodeID`) REFERENCES `qrcode` (`QRCodeID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `meritclaim`
--
ALTER TABLE `meritclaim`
  ADD CONSTRAINT `meritclaim_ibfk_1` FOREIGN KEY (`StaffID`) REFERENCES `staff` (`StaffID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `meritclaim_ibfk_2` FOREIGN KEY (`StudentID`) REFERENCES `student` (`StudentID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `meritclaim_ibfk_3` FOREIGN KEY (`EventID`) REFERENCES `event` (`EventID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `qrcode`
--
ALTER TABLE `qrcode`
  ADD CONSTRAINT `qrcode_ibfk_1` FOREIGN KEY (`EventID`) REFERENCES `event` (`EventID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
