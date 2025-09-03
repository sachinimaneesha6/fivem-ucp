-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 31, 2025 at 08:40 AM
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
-- Database: `fivem`
--

-- --------------------------------------------------------

--
-- Table structure for table `apartments`
--

CREATE TABLE `apartments` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `citizenid` varchar(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `apartments`
--

INSERT INTO `apartments` (`id`, `name`, `type`, `label`, `citizenid`) VALUES
(1, 'apartment4523620', 'apartment4', 'Tinsel Towers 523620', 'RJK33376'),
(2, 'apartment2804469', 'apartment2', 'Morningwood Blvd 804469', 'TPL51089'),
(3, 'apartment2654737', 'apartment2', 'Morningwood Blvd 654737', 'PWK83577'),
(4, 'apartment3574062', 'apartment3', 'Integrity Way 574062', 'HEB49438');

-- --------------------------------------------------------

--
-- Table structure for table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL,
  `citizenid` varchar(11) DEFAULT NULL,
  `account_name` varchar(50) DEFAULT NULL,
  `account_balance` int(11) NOT NULL DEFAULT 0,
  `account_type` enum('shared','job','gang') NOT NULL,
  `users` longtext DEFAULT '[]'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_accounts`
--

INSERT INTO `bank_accounts` (`id`, `citizenid`, `account_name`, `account_balance`, `account_type`, `users`) VALUES
(1, NULL, 'mechanic3', 0, 'job', '[]'),
(2, NULL, 'judge', 0, 'job', '[]'),
(3, NULL, 'bus', 0, 'job', '[]'),
(4, NULL, 'tow', 0, 'job', '[]'),
(5, NULL, 'mechanic2', 0, 'job', '[]'),
(6, NULL, 'lawyer', 0, 'job', '[]'),
(7, NULL, 'vineyard', 0, 'job', '[]'),
(8, NULL, 'ambulance', 0, 'job', '[]'),
(9, NULL, 'police', 0, 'job', '[]'),
(10, NULL, 'taxi', 0, 'job', '[]'),
(11, NULL, 'mechanic', 0, 'job', '[]'),
(12, NULL, 'hotdog', 0, 'job', '[]'),
(13, NULL, 'reporter', 0, 'job', '[]'),
(14, NULL, 'garbage', 0, 'job', '[]'),
(15, NULL, 'unemployed', 0, 'job', '[]'),
(16, NULL, 'realestate', 0, 'job', '[]'),
(17, NULL, 'bennys', 0, 'job', '[]'),
(18, NULL, 'trucker', 0, 'job', '[]'),
(19, NULL, 'beeker', 0, 'job', '[]'),
(20, NULL, 'cardealer', 0, 'job', '[]');

-- --------------------------------------------------------

--
-- Table structure for table `bank_statements`
--

CREATE TABLE `bank_statements` (
  `id` int(11) NOT NULL,
  `citizenid` varchar(11) DEFAULT NULL,
  `account_name` varchar(50) DEFAULT 'checking',
  `amount` int(11) DEFAULT NULL,
  `reason` varchar(50) DEFAULT NULL,
  `statement_type` enum('deposit','withdraw') DEFAULT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bans`
--

CREATE TABLE `bans` (
  `id` int(11) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `license` varchar(50) DEFAULT NULL,
  `discord` varchar(50) DEFAULT NULL,
  `ip` varchar(50) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `expire` int(11) DEFAULT NULL,
  `bannedby` varchar(255) NOT NULL DEFAULT 'LeBanhammer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crypto`
--

CREATE TABLE `crypto` (
  `crypto` varchar(50) NOT NULL DEFAULT 'qbit',
  `worth` int(11) NOT NULL DEFAULT 0,
  `history` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `crypto`
--

INSERT INTO `crypto` (`crypto`, `worth`, `history`) VALUES
('qbit', 1000, '[{\"PreviousWorth\":1000,\"NewWorth\":1000},{\"PreviousWorth\":1000,\"NewWorth\":1000}]');

-- --------------------------------------------------------

--
-- Table structure for table `crypto_transactions`
--

CREATE TABLE `crypto_transactions` (
  `id` int(11) NOT NULL,
  `citizenid` varchar(11) DEFAULT NULL,
  `title` varchar(50) DEFAULT NULL,
  `message` varchar(50) DEFAULT NULL,
  `date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dealers`
--

CREATE TABLE `dealers` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL DEFAULT '0',
  `coords` longtext DEFAULT NULL,
  `time` longtext DEFAULT NULL,
  `createdby` varchar(50) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `houselocations`
--

CREATE TABLE `houselocations` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `coords` text DEFAULT NULL,
  `owned` tinyint(1) DEFAULT NULL,
  `price` int(11) DEFAULT NULL,
  `tier` tinyint(4) DEFAULT NULL,
  `garage` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `house_plants`
--

CREATE TABLE `house_plants` (
  `id` int(11) NOT NULL,
  `building` varchar(50) DEFAULT NULL,
  `stage` int(11) DEFAULT 1,
  `sort` varchar(50) DEFAULT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `food` int(11) DEFAULT 100,
  `health` int(11) DEFAULT 100,
  `progress` int(11) DEFAULT 0,
  `coords` text DEFAULT NULL,
  `plantid` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventories`
--

CREATE TABLE `inventories` (
  `id` int(11) NOT NULL,
  `identifier` varchar(50) NOT NULL,
  `items` longtext DEFAULT '[]'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lapraces`
--

CREATE TABLE `lapraces` (
  `id` int(11) NOT NULL,
  `name` varchar(50) DEFAULT NULL,
  `checkpoints` text DEFAULT NULL,
  `records` text DEFAULT NULL,
  `creator` varchar(50) DEFAULT NULL,
  `distance` int(11) DEFAULT NULL,
  `raceid` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `occasion_vehicles`
--

CREATE TABLE `occasion_vehicles` (
  `id` int(11) NOT NULL,
  `seller` varchar(50) DEFAULT NULL,
  `price` int(11) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `plate` varchar(50) DEFAULT NULL,
  `model` varchar(50) DEFAULT NULL,
  `mods` text DEFAULT NULL,
  `occasionid` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `phone_gallery`
--

CREATE TABLE `phone_gallery` (
  `citizenid` varchar(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `phone_invoices`
--

CREATE TABLE `phone_invoices` (
  `id` int(10) NOT NULL,
  `citizenid` varchar(11) DEFAULT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `society` tinytext DEFAULT NULL,
  `sender` varchar(50) DEFAULT NULL,
  `sendercitizenid` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `phone_messages`
--

CREATE TABLE `phone_messages` (
  `id` int(11) NOT NULL,
  `citizenid` varchar(11) DEFAULT NULL,
  `number` varchar(50) DEFAULT NULL,
  `messages` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `phone_tweets`
--

CREATE TABLE `phone_tweets` (
  `id` int(11) NOT NULL,
  `citizenid` varchar(11) DEFAULT NULL,
  `firstName` varchar(25) DEFAULT NULL,
  `lastName` varchar(25) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `date` datetime DEFAULT current_timestamp(),
  `url` text DEFAULT NULL,
  `picture` varchar(512) DEFAULT './img/default.png',
  `tweetId` varchar(25) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `players`
--

CREATE TABLE `players` (
  `id` int(11) NOT NULL,
  `citizenid` varchar(11) NOT NULL,
  `cid` int(11) DEFAULT NULL,
  `license` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `money` text NOT NULL,
  `charinfo` text DEFAULT NULL,
  `job` text NOT NULL,
  `gang` text DEFAULT NULL,
  `position` text NOT NULL,
  `metadata` text NOT NULL,
  `inventory` longtext DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `players`
--

INSERT INTO `players` (`id`, `citizenid`, `cid`, `license`, `name`, `money`, `charinfo`, `job`, `gang`, `position`, `metadata`, `inventory`, `last_updated`) VALUES
(14, 'HEB49438', 2, 'license:5c76eb7cf75ce7bd554d135a7c3940490e56f3cc', 'Isuru', '{\"cash\":500,\"crypto\":0,\"bank\":5000}', '{\"nationality\":\"Albania\",\"cid\":2,\"phone\":\"4192876236\",\"gender\":0,\"account\":\"US05QBCore7502541872\",\"firstname\":\"idsq\",\"lastname\":\"os\",\"birthdate\":\"2025-08-31\"}', '{\"payment\":10,\"isboss\":false,\"onduty\":false,\"label\":\"Civilian\",\"grade\":{\"name\":\"Freelancer\",\"level\":0},\"type\":\"none\",\"name\":\"unemployed\"}', '{\"label\":\"No Gang Affiliation\",\"name\":\"none\",\"isboss\":false,\"grade\":{\"name\":\"none\",\"level\":0}}', '{\"x\":-779.024169921875,\"y\":326.1758117675781,\"z\":196.076171875}', '{\"bloodtype\":\"O+\",\"fingerprint\":\"ZN908B11SQy5893\",\"callsign\":\"NO CALLSIGN\",\"injail\":0,\"thirst\":100,\"ishandcuffed\":false,\"tracker\":false,\"status\":[],\"walletid\":\"QB-62983642\",\"armor\":0,\"isdead\":false,\"jailitems\":[],\"rep\":[],\"licences\":{\"business\":false,\"driver\":true,\"weapon\":false},\"stress\":0,\"inside\":{\"apartment\":[]},\"inlaststand\":false,\"phonedata\":{\"SerialNumber\":34016361,\"InstalledApps\":[]},\"phone\":[],\"criminalrecord\":{\"hasRecord\":false},\"hunger\":100}', '[]', '2025-08-31 06:39:17'),
(8, 'PWK83577', 1, 'license:5c76eb7cf75ce7bd554d135a7c3940490e56f3cc', 'Isuru', '{\"cash\":500,\"crypto\":0,\"bank\":5000}', '{\"gender\":0,\"nationality\":\"Sri Lanka\",\"phone\":\"1665017099\",\"firstname\":\"Isuru\",\"account\":\"US05QBCore4778647399\",\"cid\":1,\"lastname\":\"Pramodya\",\"birthdate\":\"1998-02-16\"}', '{\"payment\":10,\"isboss\":false,\"name\":\"unemployed\",\"label\":\"Civilian\",\"onduty\":false,\"type\":\"none\",\"grade\":{\"level\":0,\"isboss\":false,\"name\":\"Freelancer\"}}', '{\"label\":\"No Gang\",\"name\":\"none\",\"grade\":{\"level\":0,\"isboss\":false,\"name\":\"Unaffiliated\"},\"isboss\":false}', '{\"x\":-1287.4945068359376,\"y\":-428.9274597167969,\"z\":6.195556640625}', '{\"bloodtype\":\"AB-\",\"fingerprint\":\"pr678N90PEl5519\",\"callsign\":\"NO CALLSIGN\",\"injail\":0,\"thirst\":96.2,\"ishandcuffed\":false,\"hunger\":95.8,\"criminalrecord\":{\"hasRecord\":false},\"status\":[],\"currentapartment\":\"apartment2654737\",\"phone\":[],\"isdead\":false,\"phonedata\":{\"SerialNumber\":29419331,\"InstalledApps\":[]},\"rep\":[],\"inlaststand\":false,\"stress\":0,\"inside\":{\"apartment\":{\"apartmentId\":\"apartment2654737\",\"apartmentType\":\"apartment2\"}},\"tracker\":false,\"licences\":{\"business\":false,\"driver\":true,\"weapon\":false},\"walletid\":\"QB-63148586\",\"armor\":0,\"jailitems\":[]}', '[{\"name\":\"phone\",\"info\":[],\"slot\":1,\"amount\":1,\"type\":\"item\"},{\"name\":\"driver_license\",\"info\":{\"birthdate\":\"1998-02-16\",\"firstname\":\"Isuru\",\"lastname\":\"Pramodya\",\"type\":\"Class C Driver License\"},\"slot\":2,\"amount\":1,\"type\":\"item\"},{\"name\":\"id_card\",\"info\":{\"nationality\":\"Sri Lanka\",\"citizenid\":\"PWK83577\",\"lastname\":\"Pramodya\",\"firstname\":\"Isuru\",\"birthdate\":\"1998-02-16\",\"gender\":0},\"slot\":3,\"amount\":1,\"type\":\"item\"}]', '2025-08-31 06:38:47');

-- --------------------------------------------------------

--
-- Table structure for table `playerskins`
--

CREATE TABLE `playerskins` (
  `id` int(11) NOT NULL,
  `citizenid` varchar(11) NOT NULL,
  `model` varchar(255) NOT NULL,
  `skin` text NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `playerskins`
--

INSERT INTO `playerskins` (`id`, `citizenid`, `model`, `skin`, `active`) VALUES
(1, 'RJK33376', '1885233650', '{\"accessory\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"arms\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"makeup\":{\"defaultTexture\":1,\"item\":-1,\"defaultItem\":-1,\"texture\":1},\"ageing\":{\"defaultTexture\":0,\"item\":-1,\"defaultItem\":-1,\"texture\":0},\"facemix\":{\"skinMix\":0,\"defaultSkinMix\":0.0,\"defaultShapeMix\":0.0,\"shapeMix\":0},\"nose_0\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"neck_thikness\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"vest\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"cheek_3\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"watch\":{\"defaultTexture\":0,\"item\":-1,\"defaultItem\":-1,\"texture\":0},\"nose_2\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"face2\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"glass\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"jaw_bone_back_lenght\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"nose_3\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"chimp_bone_width\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"eye_color\":{\"defaultTexture\":0,\"item\":-1,\"defaultItem\":-1,\"texture\":0},\"eyebrown_high\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"torso2\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"ear\":{\"defaultTexture\":0,\"item\":-1,\"defaultItem\":-1,\"texture\":0},\"lipstick\":{\"defaultTexture\":1,\"item\":-1,\"defaultItem\":-1,\"texture\":1},\"decals\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"face\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"chimp_hole\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"nose_5\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"eyebrows\":{\"defaultTexture\":1,\"item\":-1,\"defaultItem\":-1,\"texture\":1},\"hat\":{\"defaultTexture\":0,\"item\":-1,\"defaultItem\":-1,\"texture\":0},\"chimp_bone_lenght\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"chimp_bone_lowering\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"beard\":{\"defaultTexture\":1,\"item\":-1,\"defaultItem\":-1,\"texture\":1},\"jaw_bone_width\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"hair\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"eye_opening\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"cheek_2\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"mask\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"cheek_1\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"eyebrown_forward\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"nose_1\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"blush\":{\"defaultTexture\":1,\"item\":-1,\"defaultItem\":-1,\"texture\":1},\"lips_thickness\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"shoes\":{\"defaultTexture\":0,\"item\":1,\"defaultItem\":1,\"texture\":0},\"t-shirt\":{\"defaultTexture\":0,\"item\":1,\"defaultItem\":1,\"texture\":0},\"bag\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"pants\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"bracelet\":{\"defaultTexture\":0,\"item\":-1,\"defaultItem\":-1,\"texture\":0},\"nose_4\":{\"defaultTexture\":0,\"item\":0,\"defaultItem\":0,\"texture\":0},\"moles\":{\"defaultTexture\":0,\"item\":-1,\"defaultItem\":-1,\"texture\":0}}', 1),
(2, 'PWK83577', '1975732938', '{\"pants\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"ageing\":{\"defaultTexture\":0,\"defaultItem\":-1,\"item\":-1,\"texture\":0},\"jaw_bone_width\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"eyebrown_high\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"hat\":{\"defaultTexture\":0,\"defaultItem\":-1,\"item\":-1,\"texture\":0},\"bag\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"nose_1\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"cheek_1\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"blush\":{\"defaultTexture\":1,\"defaultItem\":-1,\"item\":-1,\"texture\":1},\"torso2\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"decals\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"mask\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"nose_4\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"arms\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"vest\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"bracelet\":{\"defaultTexture\":0,\"defaultItem\":-1,\"item\":-1,\"texture\":0},\"nose_0\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"facemix\":{\"shapeMix\":0,\"skinMix\":0,\"defaultShapeMix\":0.0,\"defaultSkinMix\":0.0},\"face\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"cheek_2\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"eye_color\":{\"defaultTexture\":0,\"defaultItem\":-1,\"item\":-1,\"texture\":0},\"t-shirt\":{\"defaultTexture\":0,\"defaultItem\":1,\"item\":1,\"texture\":0},\"nose_5\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"neck_thikness\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"makeup\":{\"defaultTexture\":1,\"defaultItem\":-1,\"item\":-1,\"texture\":1},\"chimp_hole\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"chimp_bone_width\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"chimp_bone_lenght\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"chimp_bone_lowering\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"beard\":{\"defaultTexture\":1,\"defaultItem\":-1,\"item\":-1,\"texture\":1},\"eyebrows\":{\"defaultTexture\":1,\"defaultItem\":-1,\"item\":-1,\"texture\":1},\"nose_2\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"nose_3\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"jaw_bone_back_lenght\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"moles\":{\"defaultTexture\":0,\"defaultItem\":-1,\"item\":-1,\"texture\":0},\"eyebrown_forward\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"glass\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"ear\":{\"defaultTexture\":0,\"defaultItem\":-1,\"item\":-1,\"texture\":0},\"eye_opening\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"lips_thickness\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"hair\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"watch\":{\"defaultTexture\":0,\"defaultItem\":-1,\"item\":-1,\"texture\":0},\"accessory\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"cheek_3\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"shoes\":{\"defaultTexture\":0,\"defaultItem\":1,\"item\":1,\"texture\":0},\"face2\":{\"defaultTexture\":0,\"defaultItem\":0,\"item\":0,\"texture\":0},\"lipstick\":{\"defaultTexture\":1,\"defaultItem\":-1,\"item\":-1,\"texture\":1}}', 1),
(3, 'HEB49438', '1461287021', '{\"cheek_1\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"eyebrown_forward\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"lips_thickness\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"chimp_bone_lenght\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"nose_5\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"vest\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"blush\":{\"defaultItem\":-1,\"defaultTexture\":1,\"item\":-1,\"texture\":1},\"ear\":{\"defaultItem\":-1,\"defaultTexture\":0,\"item\":-1,\"texture\":0},\"cheek_2\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"watch\":{\"defaultItem\":-1,\"defaultTexture\":0,\"item\":-1,\"texture\":0},\"t-shirt\":{\"defaultItem\":1,\"defaultTexture\":0,\"item\":1,\"texture\":0},\"face2\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"nose_1\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"nose_2\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"hat\":{\"defaultItem\":-1,\"defaultTexture\":0,\"item\":-1,\"texture\":0},\"glass\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"bag\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"chimp_hole\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"eyebrown_high\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"face\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"hair\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"nose_4\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"neck_thikness\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"beard\":{\"defaultItem\":-1,\"defaultTexture\":1,\"item\":-1,\"texture\":1},\"facemix\":{\"shapeMix\":0,\"skinMix\":0,\"defaultSkinMix\":0.0,\"defaultShapeMix\":0.0},\"eyebrows\":{\"defaultItem\":-1,\"defaultTexture\":1,\"item\":-1,\"texture\":1},\"eye_opening\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"chimp_bone_width\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"ageing\":{\"defaultItem\":-1,\"defaultTexture\":0,\"item\":-1,\"texture\":0},\"bracelet\":{\"defaultItem\":-1,\"defaultTexture\":0,\"item\":-1,\"texture\":0},\"makeup\":{\"defaultItem\":-1,\"defaultTexture\":1,\"item\":-1,\"texture\":1},\"pants\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"nose_0\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"cheek_3\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"decals\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"shoes\":{\"defaultItem\":1,\"defaultTexture\":0,\"item\":1,\"texture\":0},\"torso2\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"jaw_bone_back_lenght\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"arms\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"moles\":{\"defaultItem\":-1,\"defaultTexture\":0,\"item\":-1,\"texture\":0},\"nose_3\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"accessory\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"lipstick\":{\"defaultItem\":-1,\"defaultTexture\":1,\"item\":-1,\"texture\":1},\"chimp_bone_lowering\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"eye_color\":{\"defaultItem\":-1,\"defaultTexture\":0,\"item\":-1,\"texture\":0},\"jaw_bone_width\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0},\"mask\":{\"defaultItem\":0,\"defaultTexture\":0,\"item\":0,\"texture\":0}}', 1);

-- --------------------------------------------------------

--
-- Table structure for table `player_contacts`
--

CREATE TABLE `player_contacts` (
  `id` int(11) NOT NULL,
  `citizenid` varchar(11) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `number` varchar(50) DEFAULT NULL,
  `iban` varchar(50) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player_houses`
--

CREATE TABLE `player_houses` (
  `id` int(255) NOT NULL,
  `house` varchar(50) NOT NULL,
  `identifier` varchar(50) DEFAULT NULL,
  `citizenid` varchar(11) DEFAULT NULL,
  `keyholders` text DEFAULT NULL,
  `decorations` text DEFAULT NULL,
  `stash` text DEFAULT NULL,
  `outfit` text DEFAULT NULL,
  `logout` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player_mails`
--

CREATE TABLE `player_mails` (
  `id` int(11) NOT NULL,
  `citizenid` varchar(11) DEFAULT NULL,
  `sender` varchar(50) DEFAULT NULL,
  `subject` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `read` tinyint(4) DEFAULT 0,
  `mailid` int(11) DEFAULT NULL,
  `date` timestamp NULL DEFAULT current_timestamp(),
  `button` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player_outfits`
--

CREATE TABLE `player_outfits` (
  `id` int(11) NOT NULL,
  `citizenid` varchar(11) DEFAULT NULL,
  `outfitname` varchar(50) NOT NULL,
  `model` varchar(50) DEFAULT NULL,
  `skin` text DEFAULT NULL,
  `outfitId` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player_vehicles`
--

CREATE TABLE `player_vehicles` (
  `id` int(11) NOT NULL,
  `license` varchar(50) DEFAULT NULL,
  `citizenid` varchar(11) DEFAULT NULL,
  `vehicle` varchar(50) DEFAULT NULL,
  `hash` varchar(50) DEFAULT NULL,
  `mods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `plate` varchar(8) NOT NULL,
  `fakeplate` varchar(8) DEFAULT NULL,
  `garage` varchar(50) DEFAULT NULL,
  `fuel` int(11) DEFAULT 100,
  `engine` float DEFAULT 1000,
  `body` float DEFAULT 1000,
  `state` int(11) DEFAULT 1,
  `depotprice` int(11) NOT NULL DEFAULT 0,
  `drivingdistance` int(50) DEFAULT NULL,
  `status` text DEFAULT NULL,
  `balance` int(11) NOT NULL DEFAULT 0,
  `paymentamount` int(11) NOT NULL DEFAULT 0,
  `paymentsleft` int(11) NOT NULL DEFAULT 0,
  `financetime` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_accounts`
--

CREATE TABLE `user_accounts` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `license` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `user_accounts`
--

INSERT INTO `user_accounts` (`id`, `username`, `email`, `password_hash`, `license`, `created_at`, `last_login`, `is_active`) VALUES
(1, 'issa', 'issa@mail.com', '$2a$11$kkmlXsodka2cJ74KV1bZZufJUHkPVXJ9zUKWV6.Pvw/w5k3sv6qp2', 'license:5c76eb7cf75ce7bd554d135a7c3940490e56f3cc', '2025-08-31 06:28:24', '2025-08-31 06:28:53', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `apartments`
--
ALTER TABLE `apartments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `citizenid` (`citizenid`),
  ADD KEY `name` (`name`);

--
-- Indexes for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD UNIQUE KEY `account_name` (`account_name`);

--
-- Indexes for table `bank_statements`
--
ALTER TABLE `bank_statements`
  ADD PRIMARY KEY (`id`) USING BTREE,
  ADD KEY `citizenid` (`citizenid`);

--
-- Indexes for table `bans`
--
ALTER TABLE `bans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `license` (`license`),
  ADD KEY `discord` (`discord`),
  ADD KEY `ip` (`ip`);

--
-- Indexes for table `crypto`
--
ALTER TABLE `crypto`
  ADD PRIMARY KEY (`crypto`);

--
-- Indexes for table `crypto_transactions`
--
ALTER TABLE `crypto_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `citizenid` (`citizenid`);

--
-- Indexes for table `dealers`
--
ALTER TABLE `dealers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `houselocations`
--
ALTER TABLE `houselocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`);

--
-- Indexes for table `house_plants`
--
ALTER TABLE `house_plants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `building` (`building`),
  ADD KEY `plantid` (`plantid`);

--
-- Indexes for table `inventories`
--
ALTER TABLE `inventories`
  ADD PRIMARY KEY (`identifier`),
  ADD KEY `id` (`id`);

--
-- Indexes for table `lapraces`
--
ALTER TABLE `lapraces`
  ADD PRIMARY KEY (`id`),
  ADD KEY `raceid` (`raceid`);

--
-- Indexes for table `occasion_vehicles`
--
ALTER TABLE `occasion_vehicles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `occasionId` (`occasionid`);

--
-- Indexes for table `phone_invoices`
--
ALTER TABLE `phone_invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `citizenid` (`citizenid`);

--
-- Indexes for table `phone_messages`
--
ALTER TABLE `phone_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `citizenid` (`citizenid`),
  ADD KEY `number` (`number`);

--
-- Indexes for table `phone_tweets`
--
ALTER TABLE `phone_tweets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `citizenid` (`citizenid`);

--
-- Indexes for table `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`citizenid`),
  ADD KEY `id` (`id`),
  ADD KEY `last_updated` (`last_updated`),
  ADD KEY `license` (`license`);

--
-- Indexes for table `playerskins`
--
ALTER TABLE `playerskins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `citizenid` (`citizenid`),
  ADD KEY `active` (`active`);

--
-- Indexes for table `player_contacts`
--
ALTER TABLE `player_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `citizenid` (`citizenid`);

--
-- Indexes for table `player_houses`
--
ALTER TABLE `player_houses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `house` (`house`),
  ADD KEY `citizenid` (`citizenid`),
  ADD KEY `identifier` (`identifier`);

--
-- Indexes for table `player_mails`
--
ALTER TABLE `player_mails`
  ADD PRIMARY KEY (`id`),
  ADD KEY `citizenid` (`citizenid`);

--
-- Indexes for table `player_outfits`
--
ALTER TABLE `player_outfits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `citizenid` (`citizenid`),
  ADD KEY `outfitId` (`outfitId`);

--
-- Indexes for table `player_vehicles`
--
ALTER TABLE `player_vehicles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plate` (`plate`),
  ADD KEY `citizenid` (`citizenid`),
  ADD KEY `license` (`license`);

--
-- Indexes for table `user_accounts`
--
ALTER TABLE `user_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `license` (`license`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_license` (`license`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `apartments`
--
ALTER TABLE `apartments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `bank_statements`
--
ALTER TABLE `bank_statements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bans`
--
ALTER TABLE `bans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crypto_transactions`
--
ALTER TABLE `crypto_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dealers`
--
ALTER TABLE `dealers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `houselocations`
--
ALTER TABLE `houselocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `house_plants`
--
ALTER TABLE `house_plants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventories`
--
ALTER TABLE `inventories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lapraces`
--
ALTER TABLE `lapraces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `occasion_vehicles`
--
ALTER TABLE `occasion_vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `phone_invoices`
--
ALTER TABLE `phone_invoices`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `phone_messages`
--
ALTER TABLE `phone_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `phone_tweets`
--
ALTER TABLE `phone_tweets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `players`
--
ALTER TABLE `players`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `playerskins`
--
ALTER TABLE `playerskins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `player_contacts`
--
ALTER TABLE `player_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `player_houses`
--
ALTER TABLE `player_houses`
  MODIFY `id` int(255) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `player_mails`
--
ALTER TABLE `player_mails`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `player_outfits`
--
ALTER TABLE `player_outfits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `player_vehicles`
--
ALTER TABLE `player_vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_accounts`
--
ALTER TABLE `user_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
