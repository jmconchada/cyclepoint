-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 08, 2025 at 10:30 PM
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
-- Database: `cyclepoint_final`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL COMMENT 'Announcement title',
  `message` text NOT NULL COMMENT 'Announcement message/content',
  `type` enum('info','success','warning','urgent') NOT NULL DEFAULT 'info' COMMENT 'Type of announcement',
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium' COMMENT 'Display priority',
  `created_by` int(11) NOT NULL COMMENT 'Admin user who created the announcement',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'When announcement was created',
  `expiry_date` datetime DEFAULT NULL COMMENT 'When announcement expires (NULL = permanent)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Whether announcement is active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System-wide announcements from admins';

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `message`, `type`, `priority`, `created_by`, `created_at`, `expiry_date`, `is_active`) VALUES
(2, 'test', 'welcome to cycpoint', 'info', 'high', 1, '2025-12-09 01:00:26', '2025-12-10 01:00:00', 1),
(3, 'test', 'haopppy wekkend', 'info', 'high', 1, '2025-12-09 02:03:50', '2025-12-10 05:03:00', 1),
(4, '123123123', 'asdasdsadsa', 'warning', 'high', 1, '2025-12-09 02:06:31', '2025-12-24 02:06:00', 1),
(5, 'sdsadas', 'dasdasdasdsa', 'info', 'high', 1, '2025-12-09 04:14:03', '2025-12-11 08:13:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `listings`
--

CREATE TABLE `listings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `desired_trade` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `sort_order` int(11) DEFAULT 0,
  `status` enum('active','pending','completed','deleted') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `listings`
--

INSERT INTO `listings` (`id`, `user_id`, `title`, `description`, `category`, `location`, `desired_trade`, `created_at`, `sort_order`, `status`) VALUES
(17, 2, 'awewefgwfas', 'dfasdfasfas', 'gadgets', 'brgy cuyambay  tanay rizal', 'saging', '2025-12-07 17:40:30', 0, 'active'),
(18, 2, 'sfasfasf', 'asfasfas', 'furniture', 'antipolo, city brgy. mambugan', 'saging', '2025-12-07 17:41:53', 0, 'active'),
(19, 2, 'sdasdsadas', 'dsadasdsadas', 'tools', 'antipolo city', 'anything', '2025-12-07 17:42:21', 0, 'active'),
(20, 1, 'h gsdtgsd', 'gdsgsdgsd', 'furniture', 'city of antipolo', 'kiss', '2025-12-07 19:35:46', 0, 'active'),
(21, 2, 'asdfasdfas', 'dsadsadsad', 'appliances', 'brgy. cuyambay, tanay rizal', 'anything', '2025-12-07 23:37:44', 0, 'active'),
(22, 2, 'tgsdtg', 'sdgsdgsdg', 'gadgets', 'Brgy.Cuyambay,Tanay Rizal', 'asdsdasdas', '2025-12-08 00:36:46', 0, 'active'),
(23, 1, 'dvsdvsvsd', 'vsdvsdvsd', 'clothes', 'Brgy.Cuyambay,Tanay Rizal', 'sdasdasd', '2025-12-08 06:33:15', 0, 'active'),
(24, 6, 'qfasf', 'asfasfasf', 'clothes', 'Quezon City', 'safasf', '2025-12-08 09:02:35', 0, 'active'),
(25, 6, 'dasdsa', 'asdasdsa', 'clothes', 'Antipolo City', 'anything', '2025-12-08 20:35:03', 0, 'active'),
(26, 6, 'ASDSAD', 'ASDASDSA', 'clothes', 'Quezon City', 'ASDASDSA', '2025-12-08 22:08:40', 0, 'active'),
(27, 6, 'asdasd', 'asdasd', 'clothes', 'Brgy.Cuyambay,Tanay Rizal', 'asdsad', '2025-12-09 01:27:02', 0, 'active'),
(28, 6, 'sadasdasd', 'sadasdasd', 'gadgets', 'Antipolo City', 'tsetset', '2025-12-09 02:28:27', 0, 'active'),
(29, 3, 'sdasa', 'dasdasdas', 'furniture', 'Antipolo City', 'dasdasdas', '2025-12-09 04:55:24', 0, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `listing_images`
--

CREATE TABLE `listing_images` (
  `id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `path` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `listing_images`
--

INSERT INTO `listing_images` (`id`, `listing_id`, `path`, `sort_order`) VALUES
(14, 17, 'uploads/listings/17/img_01.jpg', 0),
(15, 18, 'uploads/listings/18/img_01.jpg', 0),
(16, 19, 'uploads/listings/19/img_01.png', 0),
(17, 20, 'uploads/listings/20/img_01.jpg', 0),
(18, 21, 'uploads/listings/21/img_01.png', 0),
(19, 22, 'uploads/listings/22/img_01.jpg', 0),
(20, 23, 'uploads/listings/23/img_01.jpg', 0),
(21, 24, 'uploads/listings/24/img_01.jpg', 0),
(22, 24, 'uploads/listings/24/img_02.png', 1),
(23, 24, 'uploads/listings/24/img_03.jpg', 2),
(24, 24, 'uploads/listings/24/img_04.png', 3),
(25, 24, 'uploads/listings/24/img_05.png', 4),
(26, 24, 'uploads/listings/24/img_06.jpg', 5),
(27, 25, 'uploads/listings/25/img_01.jpg', 0),
(28, 26, 'uploads/listings/26/img_01.jpg', 0),
(29, 27, 'uploads/listings/27/img_01.jpg', 0),
(30, 28, 'uploads/listings/28/img_01.jpg', 0),
(31, 28, 'uploads/listings/28/img_02.jpg', 1),
(32, 29, 'uploads/listings/29/img_01.jpg', 0);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'unread',
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `image_path`, `timestamp`, `status`, `is_read`) VALUES
(1, 2, 1, 'hey', NULL, '2025-12-07 08:58:25', 'sent', 1),
(2, 1, 2, 'adfgdfgzdg', NULL, '2025-12-07 19:31:46', 'sent', 1),
(3, 2, 1, 'gsdagdsgsdg', NULL, '2025-12-07 19:31:56', 'sent', 1),
(4, 2, 1, 'dadsadsadasdasdas', NULL, '2025-12-08 03:00:36', 'sent', 1),
(5, 2, 1, 'dsadsadsadas', NULL, '2025-12-08 03:01:14', 'sent', 1),
(6, 2, 1, 'sadasdsada', NULL, '2025-12-08 03:01:20', 'sent', 1),
(7, 2, 1, 'sadasdasdsa', NULL, '2025-12-08 03:01:21', 'sent', 1),
(8, 2, 1, 'adsadasdsa', NULL, '2025-12-08 03:01:23', 'sent', 1),
(9, 2, 1, 'dasdasdasdas', NULL, '2025-12-08 03:25:56', 'sent', 1),
(10, 2, 1, 'dsadasdasdasdas', NULL, '2025-12-08 03:26:07', 'sent', 1),
(11, 2, 1, 'fasfasfasfasf', NULL, '2025-12-08 03:35:36', 'sent', 1),
(12, 2, 1, 'dasdasdasd', NULL, '2025-12-08 03:35:46', 'sent', 1),
(13, 2, 1, 'dasdasdas', NULL, '2025-12-08 03:35:56', 'sent', 1),
(14, 2, 1, 'sdasdasd', NULL, '2025-12-08 03:36:01', 'sent', 1),
(15, 2, 1, 'dasdasdasd', NULL, '2025-12-08 03:36:05', 'sent', 1),
(16, 2, 1, 'asdasdasdasdsa', NULL, '2025-12-08 03:36:13', 'sent', 1),
(17, 2, 1, 'afsafsafas', NULL, '2025-12-08 04:01:09', 'sent', 1),
(18, 2, 1, 'dasdasda', NULL, '2025-12-08 04:01:27', 'sent', 1),
(19, 2, 1, 'dsadasdasd', NULL, '2025-12-08 04:01:40', 'sent', 1),
(20, 2, 1, 'dasdasdasd', NULL, '2025-12-08 04:01:46', 'sent', 1),
(21, 2, 1, 'dasdasdasdsa', NULL, '2025-12-08 04:02:30', 'sent', 1),
(22, 2, 1, 'fasfasfas', NULL, '2025-12-08 04:04:52', 'sent', 1),
(23, 1, 2, 'kabfnkabfkjabsfkas', NULL, '2025-12-08 04:06:16', 'sent', 1),
(24, 2, 2, 'dasdasdsadasd', NULL, '2025-12-08 04:25:43', 'sent', 1),
(25, 2, 1, 'sdasdasdsad', NULL, '2025-12-08 04:38:06', 'sent', 1),
(26, 2, 1, 'sdasdasdasdsa', NULL, '2025-12-08 04:38:33', 'sent', 1),
(27, 2, 1, 'dASDSADASDASDSA', NULL, '2025-12-08 04:40:54', 'sent', 1),
(28, 2, 1, 'sdsadasdsadsa', NULL, '2025-12-08 04:41:41', 'sent', 1),
(29, 2, 1, 'tanginamo', NULL, '2025-12-08 04:53:56', 'sent', 1),
(30, 1, 1, 'sfasfasfa', NULL, '2025-12-08 04:55:24', 'sent', 1),
(31, 1, 2, 'fasfasfasfas', NULL, '2025-12-08 04:57:31', 'sent', 1),
(32, 1, 2, 'rwrqwrqrqw', NULL, '2025-12-08 04:57:55', 'sent', 1),
(33, 2, 2, 'fefwefwefwe', NULL, '2025-12-08 05:05:27', 'sent', 1),
(34, 2, 2, 'dsafasfasa', NULL, '2025-12-08 05:06:01', 'sent', 1),
(35, 2, 1, 'fgasdfafafas', NULL, '2025-12-08 05:34:12', 'sent', 1),
(36, 1, 1, 'sdasdasdasdas', NULL, '2025-12-08 05:34:50', 'sent', 1),
(37, 1, 2, 'gfgdfgdf', NULL, '2025-12-08 05:35:07', 'sent', 1),
(38, 1, 2, 'gdfgdfgfdgfd', NULL, '2025-12-08 05:35:14', 'sent', 1),
(39, 1, 2, 'fgdfgdfgdfg', NULL, '2025-12-08 05:35:18', 'sent', 1),
(40, 2, 1, 'dsadasda', NULL, '2025-12-08 05:36:11', 'sent', 1),
(41, 2, 1, 'sdasdasda', NULL, '2025-12-08 05:36:49', 'sent', 1),
(42, 1, 2, 'sadasdasdsadasdsad', NULL, '2025-12-08 05:37:06', 'sent', 1),
(43, 1, 2, 'hdfhdfhdfhdfhdfh', NULL, '2025-12-08 05:38:06', 'sent', 1),
(44, 1, 2, 'dasdasdasdas', NULL, '2025-12-08 05:38:14', 'sent', 1),
(77, 1, 4, '[Image]', 'uploads/chat/chat_1_69361477168280.96314334.jpg', '2025-12-08 07:57:43', 'sent', 0),
(81, 1, 6, 'dfsdfsdfsdfsd', NULL, '2025-12-08 08:14:02', 'sent', 1),
(82, 6, 1, '😊', NULL, '2025-12-08 08:14:44', 'sent', 1),
(83, 6, 1, '[Image]', 'uploads/chat/chat_6_69361895b829c7.03279231.jpg', '2025-12-08 08:15:17', 'sent', 1),
(84, 6, 1, '[Image]', 'uploads/chat/chat_6_69361895bb89b1.93936800.jpg', '2025-12-08 08:15:17', 'sent', 1),
(85, 6, 1, '[Image]', 'uploads/chat/chat_6_69361895bc5361.21913489.jpg', '2025-12-08 08:15:17', 'sent', 1),
(86, 6, 1, '[Image]', 'uploads/chat/chat_6_69361895bd4653.24231221.jpg', '2025-12-08 08:15:17', 'sent', 1),
(87, 6, 1, '🤪', NULL, '2025-12-08 08:16:08', 'sent', 1),
(88, 6, 1, '😘', NULL, '2025-12-08 08:16:19', 'sent', 1),
(89, 6, 1, '🤨', NULL, '2025-12-08 08:16:23', 'sent', 1),
(90, 6, 1, '🤪', NULL, '2025-12-08 08:16:28', 'sent', 1),
(91, 6, 1, '🤪', NULL, '2025-12-08 08:16:38', 'sent', 1),
(92, 6, 1, '🤨', NULL, '2025-12-08 08:16:43', 'sent', 1),
(93, 1, 6, 'sadsadasdas', NULL, '2025-12-08 08:22:47', 'sent', 1),
(94, 1, 6, 'dsadasdas', NULL, '2025-12-08 08:22:57', 'sent', 1),
(95, 1, 6, '😗', NULL, '2025-12-08 08:36:55', 'sent', 1),
(96, 1, 6, '[Image]', 'uploads/chat/chat_1_69361da7221457.44510541.jpg', '2025-12-08 08:36:55', 'sent', 1),
(97, 1, 6, '[Image]', 'uploads/chat/chat_1_69361dc57c8593.08085495.jpg', '2025-12-08 08:37:25', 'sent', 1),
(98, 1, 6, '😇😘', NULL, '2025-12-08 08:37:32', 'sent', 1),
(99, 1, 6, '[Image]', 'uploads/chat/chat_1_69361dfeb1cee9.52351435.jpg', '2025-12-08 08:38:22', 'sent', 1),
(100, 6, 1, 'tanginamo boi', NULL, '2025-12-08 08:38:54', 'sent', 1),
(101, 1, 6, 'Hi! Is this item still available for trading?\"qfasf\"', NULL, '2025-12-08 09:14:48', 'sent', 1),
(104, 1, 6, 'Hi! Is this item still available for trading?\"qfasf\"', NULL, '2025-12-08 19:35:42', 'sent', 1),
(105, 8, 6, 'Hi! Is this item still available for trading?\"ASDSAD\"', NULL, '2025-12-09 00:04:51', 'sent', 1),
(106, 8, 6, '[Image]', 'uploads/chat/chat_8_6936f7374d4a42.35749314.jpg', '2025-12-09 00:05:11', 'sent', 1),
(107, 8, 6, 'zsdasdasd', NULL, '2025-12-09 00:05:33', 'sent', 1),
(108, 8, 6, '[Image]', 'uploads/chat/chat_8_6936f74d06fc69.15851939.jpg', '2025-12-09 00:05:33', 'sent', 1),
(109, 8, 6, 'Hi! Is this item still available for trading?\"ASDSAD\"', NULL, '2025-12-09 00:06:27', 'sent', 1),
(110, 8, 6, 'asdsadsad', NULL, '2025-12-09 00:06:51', 'sent', 1),
(111, 8, 6, '[Image]', 'uploads/chat/chat_8_6936f79ba4ab42.43801327.jpg', '2025-12-09 00:06:51', 'sent', 1),
(112, 8, 6, 'Hi! Is this item still available for trading?\"qfasf\"', NULL, '2025-12-09 00:46:27', 'sent', 1),
(113, 8, 6, 'Hi! Is this item still available for trading?\"asdasd\"', NULL, '2025-12-09 01:27:55', 'sent', 1),
(114, 6, 8, 'adsadasdsad', NULL, '2025-12-09 02:55:09', 'sent', 0),
(118, 3, 6, 'Hi! Is this item still available for trading?\"ASDSAD\"', 'uploads/chat/item_1765222486_693728567fac8.jpg', '2025-12-09 03:34:46', 'sent', 1),
(120, 5, 6, 'Hi! Is this item still available for trading?\"ASDSAD\"', 'uploads/chat/item_1765222691_693729234694b.jpg', '2025-12-09 03:38:11', 'sent', 1),
(121, 3, 6, 'sadasddsad', NULL, '2025-12-09 04:14:53', 'sent', 1),
(122, 6, 3, 'sdadasda', NULL, '2025-12-09 04:34:35', 'sent', 1),
(123, 3, 6, 'sadsdad', NULL, '2025-12-09 04:34:51', 'sent', 1),
(124, 3, 6, 'asdasdasadsd', NULL, '2025-12-09 04:35:11', 'sent', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('announcement','view') NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `item_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `message`, `is_read`, `created_at`, `item_id`) VALUES
(1, 8, 'announcement', '📢 Announcement Reactivated: test', 0, '2025-12-08 17:58:09', NULL),
(2, 5, 'announcement', '📢 Announcement Reactivated: test', 1, '2025-12-08 17:58:09', NULL),
(3, 7, 'announcement', '📢 Announcement Reactivated: test', 0, '2025-12-08 17:58:09', NULL),
(4, 6, 'announcement', '📢 Announcement Reactivated: test', 1, '2025-12-08 17:58:09', NULL),
(5, 1, 'announcement', '📢 Announcement Reactivated: test', 1, '2025-12-08 17:58:09', NULL),
(6, 4, 'announcement', '📢 Announcement Reactivated: test', 0, '2025-12-08 17:58:09', NULL),
(7, 3, 'announcement', '📢 Announcement Reactivated: test', 1, '2025-12-08 17:58:09', NULL),
(8, 8, 'announcement', '📢 New Announcement: test', 0, '2025-12-08 18:03:50', NULL),
(9, 5, 'announcement', '📢 New Announcement: test', 1, '2025-12-08 18:03:50', NULL),
(10, 7, 'announcement', '📢 New Announcement: test', 0, '2025-12-08 18:03:50', NULL),
(11, 6, 'announcement', '📢 New Announcement: test', 1, '2025-12-08 18:03:50', NULL),
(12, 1, 'announcement', '📢 New Announcement: test', 1, '2025-12-08 18:03:50', NULL),
(13, 4, 'announcement', '📢 New Announcement: test', 0, '2025-12-08 18:03:50', NULL),
(14, 3, 'announcement', '📢 New Announcement: test', 1, '2025-12-08 18:03:50', NULL),
(15, 8, 'announcement', '📢 New Announcement: 123123123', 0, '2025-12-08 18:06:31', NULL),
(16, 5, 'announcement', '📢 New Announcement: 123123123', 1, '2025-12-08 18:06:31', NULL),
(17, 7, 'announcement', '📢 New Announcement: 123123123', 0, '2025-12-08 18:06:31', NULL),
(18, 6, 'announcement', '📢 New Announcement: 123123123', 1, '2025-12-08 18:06:31', NULL),
(19, 1, 'announcement', '📢 New Announcement: 123123123', 1, '2025-12-08 18:06:31', NULL),
(20, 4, 'announcement', '📢 New Announcement: 123123123', 0, '2025-12-08 18:06:31', NULL),
(21, 3, 'announcement', '📢 New Announcement: 123123123', 1, '2025-12-08 18:06:31', NULL),
(22, 8, 'announcement', '📢 New Announcement: sdsadas', 0, '2025-12-08 20:14:03', NULL),
(23, 5, 'announcement', '📢 New Announcement: sdsadas', 0, '2025-12-08 20:14:03', NULL),
(24, 7, 'announcement', '📢 New Announcement: sdsadas', 0, '2025-12-08 20:14:03', NULL),
(25, 6, 'announcement', '📢 New Announcement: sdsadas', 1, '2025-12-08 20:14:03', NULL),
(26, 1, 'announcement', '📢 New Announcement: sdsadas', 1, '2025-12-08 20:14:03', NULL),
(27, 4, 'announcement', '📢 New Announcement: sdsadas', 0, '2025-12-08 20:14:03', NULL),
(28, 3, 'announcement', '📢 New Announcement: sdsadas', 1, '2025-12-08 20:14:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `restriction_history`
--

CREATE TABLE `restriction_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User who was restricted',
  `admin_id` int(11) NOT NULL COMMENT 'Admin who applied the restriction',
  `action` enum('applied','modified','removed') NOT NULL COMMENT 'Action taken',
  `restriction_type` enum('banned','read_only','no_post','no_comment','limited') DEFAULT NULL,
  `reason` text DEFAULT NULL COMMENT 'Reason for the action',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='History of user restrictions';

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `password` varchar(255) NOT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `last_seen` datetime DEFAULT NULL,
  `banned` tinyint(1) NOT NULL DEFAULT 0,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `restriction_type` enum('banned','read_only','no_post','no_comment','limited') DEFAULT NULL COMMENT 'Type of restriction applied',
  `restriction_reason` text DEFAULT NULL COMMENT 'Reason for the restriction',
  `restriction_date` datetime DEFAULT NULL COMMENT 'When restriction was applied',
  `restriction_expiry` datetime DEFAULT NULL COMMENT 'When restriction expires (NULL = permanent)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `contact_number`, `area`, `profile_picture`, `created_at`, `password`, `reset_token`, `reset_expires`, `last_seen`, `banned`, `role`, `restriction_type`, `restriction_reason`, `restriction_date`, `restriction_expiry`) VALUES
(1, 'jm conchada', 'jeyemm03@gmail.com', '09053716679', 'antipolo city', 'uploads/profiles/profile_1_1765228741.png', '2025-12-07 08:57:37', '$2y$10$9Km2QVAYkFEJCIuO6GPal.1SUU00z7AJePtBrgL/YGHKXuzLaPh0e', NULL, NULL, '2025-12-09 05:12:23', 0, 'admin', NULL, NULL, NULL, NULL),
(3, 'tanginamo', 'tangina@gmail.com', '09053716679', 'quezon city', 'uploads/profiles/profile_3_1765219414.jpg', '2025-12-08 05:40:05', '$2y$10$9ZoosjnKNbPOZ2AsDGfODufnuKEcv6yBMLsayTn3Wxh0v4vSKfZpm', NULL, NULL, '2025-12-09 05:09:36', 0, 'user', NULL, NULL, NULL, NULL),
(4, 'kaithlyn', 'kaith@gmail.com', '09876476354', 'quezon city', NULL, '2025-12-08 07:44:24', '$2y$10$sCeI8c3xuclgfHAksQgzMeduqYJ1PJJ613vr7NhR7PXGXb9HMsvnG', NULL, NULL, NULL, 0, 'user', NULL, NULL, NULL, NULL),
(5, 'Daphne Sancha', 'daph@gmail.com', '09878675423', 'quezon city', NULL, '2025-12-08 07:45:06', '$2y$10$0MBNNX3qtkuwnV3xfx64JO0OiVcA2l1PCtOuVNsJLakZkk3wlIpLO', NULL, NULL, '2025-12-09 03:38:09', 0, 'user', NULL, NULL, NULL, NULL),
(6, 'angelo', 'itsme.dg@gmail.com', '09684538672', 'brgy. cuyambay, tanay rizal', 'uploads/profiles/profile_6_1765214793.jpg', '2025-12-08 07:45:49', '$2y$10$Dumx30DiC4c4cjhTAoWiqOAJuxMueCPc9UpRTK0DjHbJNF1vY50bO', 'aa411b76b5fab5a8a05322e635497943cdd96dd468e067237ad5b6518f6a5499', '2025-12-08 16:28:41', '2025-12-09 04:43:54', 0, 'user', NULL, NULL, NULL, NULL),
(7, 'angelo', 'itsme.dg16@gmail.com', '09053716622', 'quezon city', NULL, '2025-12-08 22:29:48', '$2y$10$.ueW1e0HQ10ilomH.N1kk.UDZTZbg5q/U6FyGua/fbZiUIG.czCle', '7defe5fdaa13bee7444a02ec8229edac9e8444aa39fe1a8331a720f242d6b560', '2025-12-08 16:30:03', NULL, 0, 'user', 'limited', 'scamming people ', '2025-12-09 02:23:16', '2025-12-09 19:23:16'),
(8, 'jm conchadaasd', 'angelodugz16@gmail.com', '098764761231', 'brgy. cuyambay, tanay rizal', 'uploads/profiles/profile_8_1765211679.jpg', '2025-12-08 22:32:47', '$2y$10$8JFvn0oXmK7Q1hk/uQokAOvyN0nr.uEF4iMUWZUTkevEpKURG1EGK', NULL, NULL, '2025-12-09 01:28:10', 0, 'user', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_expiry_date` (`expiry_date`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `listings`
--
ALTER TABLE `listings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `listing_images`
--
ALTER TABLE `listing_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `listing_id` (`listing_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_receiver_read` (`receiver_id`,`is_read`),
  ADD KEY `idx_image_path` (`image_path`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `restriction_history`
--
ALTER TABLE `restriction_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `item_id` (`item_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `listings`
--
ALTER TABLE `listings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `listing_images`
--
ALTER TABLE `listing_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=125;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `restriction_history`
--
ALTER TABLE `restriction_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_announcements_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `listing_images`
--
ALTER TABLE `listing_images`
  ADD CONSTRAINT `listing_images_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `restriction_history`
--
ALTER TABLE `restriction_history`
  ADD CONSTRAINT `fk_history_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
