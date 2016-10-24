-- phpMyAdmin SQL Dump
-- version 4.3.13
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Nov 30, 2015 at 11:57 PM
-- Server version: 5.5.44-0+deb8u1
-- PHP Version: 5.6.12-0+deb8u1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `userdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `PovoleneSMTP`
--

CREATE TABLE IF NOT EXISTS `PovoleneSMTP` (
  `id` int(11) NOT NULL,
  `IPAdresa_id` int(11) NOT NULL,
  `datum_vlozeni` datetime NOT NULL,
  `poznamka` varchar(255) COLLATE utf8_czech_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `PovoleneSMTP`
--
ALTER TABLE `PovoleneSMTP`
  ADD PRIMARY KEY (`id`), ADD KEY `IPAdresa_id` (`IPAdresa_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `PovoleneSMTP`
--
ALTER TABLE `PovoleneSMTP`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `PovoleneSMTP`
--
ALTER TABLE `PovoleneSMTP`
ADD CONSTRAINT `PovoleneSMTP_ibfk_1` FOREIGN KEY (`IPAdresa_id`) REFERENCES `IPAdresa` (`id`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
