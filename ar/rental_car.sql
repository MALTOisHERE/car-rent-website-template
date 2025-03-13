-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 28, 2025 at 05:08 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rental_car`
--

-- --------------------------------------------------------

--
-- Table structure for table `car`
--

CREATE TABLE `car` (
  `idcar` int(11) NOT NULL,
  `name` varchar(45) DEFAULT NULL,
  `door` int(11) DEFAULT NULL,
  `bag` int(11) DEFAULT NULL,
  `seat` int(11) DEFAULT NULL,
  `price` int(11) DEFAULT NULL,
  `type` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `car`
--

INSERT INTO `car` (`idcar`, `name`, `door`, `bag`, `seat`, `price`, `type`) VALUES
(1, 'Dacia', 4, 3, 5, 200, 0),
(2, 'Honda', 4, 3, 5, 300, 1),
(3, 'Mercedes', 2, 3, 5, 500, 1);

-- --------------------------------------------------------

--
-- Table structure for table `reservation`
--

CREATE TABLE `reservation` (
  `idres` int(11) NOT NULL,
  `depart` varchar(45) DEFAULT NULL,
  `arrive` varchar(45) DEFAULT NULL,
  `heureDebut` time DEFAULT NULL,
  `heureFin` time DEFAULT NULL,
  `Date_debut` date DEFAULT NULL,
  `Date_fin` date DEFAULT NULL,
  `idcar` int(11) DEFAULT NULL,
  `iduser` int(11) DEFAULT NULL,
  `confirm` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `reservation`
--

INSERT INTO `reservation` (`idres`, `depart`, `arrive`, `heureDebut`, `heureFin`, `Date_debut`, `Date_fin`, `idcar`, `iduser`, `confirm`) VALUES
(2, 'Agadir', 'Marrakech', '10:13:56', '01:49:00', '2025-01-01', '2025-01-27', 1, 1, 0),
(3, 'Casa', 'Rabat', '17:17:04', '01:57:00', '2025-01-09', '2025-01-27', 2, 1, 0),
(6, 'Kenitra', 'Agadir', '00:00:13', '00:00:14', '2025-01-24', '2025-01-31', 2, 4, NULL),
(7, 'agadir', 'marrakech', '00:00:13', '00:00:17', '2025-01-23', '2025-01-09', 2, 5, 0),
(8, 'Agadir', '', '00:00:14', '00:00:17', '2025-01-28', '2025-01-28', 1, 6, 0),
(9, 'Agadir', '', '14:00:00', '20:00:00', '2025-01-28', '2025-01-28', 1, 7, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `iduser` int(11) NOT NULL,
  `name` varchar(45) DEFAULT NULL,
  `fullname` varchar(45) DEFAULT NULL,
  `email` varchar(45) DEFAULT NULL,
  `phone` varchar(45) DEFAULT NULL,
  `password` varchar(45) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`iduser`, `name`, `fullname`, `email`, `phone`, `password`, `reset_token`, `reset_token_expiry`) VALUES
(1, 'abdo', 'abdo', 'abdo@gmail.com', '123456789', '123456', NULL, NULL),
(4, 'Mohamed', 'AIT OUAARAB', 'mohammed.ouaarab@gmail.com', '0696183942', 'test', NULL, NULL),
(5, 'Mohamed', 'AIT OUAARAB', 'mohammed.ouaarab@gmail.com', '0696183942', 'test', NULL, NULL),
(6, 'Mohamed', 'AIT OUAARAB', 'mohammed.ouaarab@gmail.com', '0696183942', 'test1234', NULL, NULL),
(7, 'Mohamed', 'AIT OUAARAB', 'mohammed.ouaarab@gmail.com', '0696183942', 'test123456', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `car`
--
ALTER TABLE `car`
  ADD PRIMARY KEY (`idcar`),
  ADD UNIQUE KEY `idcar_UNIQUE` (`idcar`);

--
-- Indexes for table `reservation`
--
ALTER TABLE `reservation`
  ADD PRIMARY KEY (`idres`),
  ADD UNIQUE KEY `idres_UNIQUE` (`idres`),
  ADD KEY `f_car_idx` (`idcar`),
  ADD KEY `f_user_idx` (`iduser`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`iduser`),
  ADD UNIQUE KEY `iduser_UNIQUE` (`iduser`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `car`
--
ALTER TABLE `car`
  MODIFY `idcar` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reservation`
--
ALTER TABLE `reservation`
  MODIFY `idres` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `iduser` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `reservation`
--
ALTER TABLE `reservation`
  ADD CONSTRAINT `f_car` FOREIGN KEY (`idcar`) REFERENCES `car` (`idcar`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `f_user` FOREIGN KEY (`iduser`) REFERENCES `user` (`iduser`) ON DELETE NO ACTION ON UPDATE NO ACTION;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `update_confirm_status` ON SCHEDULE EVERY 1 MINUTE STARTS '2025-01-27 01:41:47' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE reservation
  SET confirm = 0
  WHERE CONCAT(Date_fin, ' ', heureFin) < NOW()$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
