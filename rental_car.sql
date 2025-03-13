-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 01, 2025 at 08:05 PM
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
  `type` int(11) DEFAULT NULL,
  `image` varchar(255) NOT NULL DEFAULT 'default.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `car`
--

INSERT INTO `car` (`idcar`, `name`, `door`, `bag`, `seat`, `price`, `type`, `image`) VALUES
(6, 'CITROEN C3', 4, 4, 5, 200, 0, '1738430658_Citroen_C3_0MP00NWP_BlancBanquise_FR_1280_720.png'),
(8, 'Dacia Logan', 4, 5, 5, 200, 0, '1738431744_dacia logan.png'),
(9, 'Dacia Sandero', 4, 6, 5, 200, 0, '1738431899_dacia sandero.png'),
(10, 'Clio 4', 4, 4, 5, 200, 0, '1738432170_clio 4.png'),
(11, 'Clio 5', 4, 4, 5, 200, 0, '1738432309_clio 5.png'),
(12, 'Dacia Duster', 4, 6, 5, 200, 0, '1738432554_dacia duster.png'),
(13, 'Renault Megan', 4, 4, 5, 200, 0, '1738433504_renault megan.png'),
(14, 'Range Rover Evoque', 4, 5, 5, 500, 1, '1738433892_range rover.png'),
(15, 'Touareg', 4, 5, 5, 500, 1, '1738434135_touerg.png'),
(16, 'KIA Picanto', 4, 3, 4, 200, 0, '1738434364_kia picanto.png'),
(17, 'Peugeot 208', 4, 4, 5, 200, 0, '1738434489_peugot 208.png');

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
(14, 'agadir', 'marrakech', '15:00:00', '16:00:00', '2025-02-01', '2025-03-06', 6, 12, NULL),
(15, 'agadir', '', '16:00:00', '17:00:00', '2025-02-27', '2025-03-05', 8, 13, NULL);

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
  `password` varchar(200) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `role` varchar(45) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`iduser`, `name`, `fullname`, `email`, `phone`, `password`, `reset_token`, `reset_token_expiry`, `role`) VALUES
(10, 'jamila', 'jamila', 'jamila@gmail.com', '123456789', '$2y$10$2yCbsfVqrEOUOeSuofTaieCy8Ea1oSJ510PonJxOqYoRwsx0qMTNm', NULL, NULL, 'admin'),
(11, 'Mohamed', 'AIT OUAARAB', 'mohammed.ouaarab@gmail.com', '0696183942', '$2y$10$CQhoeKN0czhwXjJZ8hQpz.dQI32XP07uw2ACNofUB4aShSc1jOWO6', NULL, NULL, '0'),
(12, 'Mohamed', 'AIT OUAARAB', 'mohammed.ouaarab@gmail.com', '0696183942', '$2y$10$ILt8ID4EkT3ijE.E1OMmRuiVx8vFiUdDEbtXKg.MSFzbXqzHvA5Ee', NULL, NULL, '0'),
(13, 'Mohamed', 'AIT OUAARAB', 'mohammed.ouaarab@gmail.com', '0696183942', '$2y$10$pJwgRG8fLn3ec2poCStbF.OVL4/vXsd1pGoK4kXRvOFcciyQ2.gUi', NULL, NULL, '0');

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
  MODIFY `idcar` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `reservation`
--
ALTER TABLE `reservation`
  MODIFY `idres` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `iduser` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
