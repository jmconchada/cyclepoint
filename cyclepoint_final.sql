-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 15, 2026 at 04:02 AM
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
(6, 'welcome to cyclepoint', 'goodluck ', 'success', 'low', 1, '2026-04-15 07:19:44', '2026-04-15 07:19:00', 1);

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
(32, 13, 'used clothes', 'slightly used', 'clothes', 'Brgy.Cuyambay,Tanay Rizal', 'anything that i can use', '2026-04-15 04:19:38', 0, 'active'),
(33, 13, 'shirts and pants', 'never worned', 'clothes', 'Brgy.Cuyambay,Tanay Rizal', 'anything that i can use', '2026-04-15 04:20:48', 0, 'active'),
(34, 13, 'clothes', 'old clothes', 'clothes', 'Brgy.Cuyambay,Tanay Rizal', 'anything', '2026-04-15 04:21:42', 0, 'active'),
(35, 13, 'leather jackets', 'slightly worned', 'clothes', 'Brgy.Cuyambay,Tanay Rizal', 'anything', '2026-04-15 04:22:17', 0, 'active'),
(36, 13, 'denim jackets', 'new', 'clothes', 'Brgy.Cuyambay,Tanay Rizal', 'anything', '2026-04-15 04:23:14', 0, 'active'),
(37, 13, 't-shirts', 'new', 'clothes', 'Brgy.Cuyambay,Tanay Rizal', 'anything', '2026-04-15 04:24:00', 0, 'active'),
(38, 12, 'old phones', 'old and used', 'gadgets', 'Quezon City', 'anything', '2026-04-15 04:27:02', 0, 'active'),
(39, 12, 'used appliances', 'old and worned out', 'appliances', 'Quezon City', 'clothes', '2026-04-15 04:28:20', 0, 'active'),
(40, 12, 'ovens', 'slightly used', 'appliances', 'Quezon City', 'fruits', '2026-04-15 04:29:22', 0, 'active'),
(41, 12, 'used tools', 'moderately used', 'tools', 'Quezon City', 'leather jackets', '2026-04-15 04:30:54', 0, 'active'),
(43, 11, 'old table and chairs', 'still in good condition', 'furniture', 'Quezon City', 'anything', '2026-04-15 04:33:21', 0, 'active'),
(44, 10, 'canned foods', 'still gopd', 'food', 'Antipolo City', 'tools', '2026-04-15 04:38:34', 0, 'active'),
(45, 10, 'fresh fruits', 'organic and fresh', 'food', 'Antipolo City', 'anything', '2026-04-15 04:39:13', 0, 'active'),
(46, 10, 'fresh vegetables', 'organic and fresh', 'food', 'Antipolo City', 'clothes', '2026-04-15 04:40:40', 0, 'completed'),
(47, 11, 'old sofas', 'used', 'furniture', 'Quezon City', 'anything', '2026-04-15 04:42:24', 0, 'active'),
(48, 12, 'powered tools', 'used', 'tools', 'Quezon City', 'anything', '2026-04-15 04:43:40', 0, 'active'),
(49, 10, 'fresh vegetables', 'fresh and organic', 'food', 'Antipolo City', 'anything', '2026-04-15 07:05:31', 0, 'active'),
(50, 12, 'old gadgets', 'not working', 'gadgets', 'Quezon City', 'fruits and vegetables', '2026-04-15 07:06:58', 0, 'active'),
(51, 12, 'old headphones', 'used and old', 'gadgets', 'Quezon City', 'anything', '2026-04-15 07:08:25', 0, 'active');

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
(35, 32, 'uploads/listings/32/img_01.jpg', 0),
(36, 33, 'uploads/listings/33/img_01.jpg', 0),
(37, 34, 'uploads/listings/34/img_01.jpg', 0),
(38, 35, 'uploads/listings/35/img_01.jpg', 0),
(39, 36, 'uploads/listings/36/img_01.jpg', 0),
(40, 37, 'uploads/listings/37/img_01.jpg', 0),
(41, 38, 'uploads/listings/38/img_01.png', 0),
(42, 39, 'uploads/listings/39/img_01.png', 0),
(43, 40, 'uploads/listings/40/img_01.jpg', 0),
(44, 41, 'uploads/listings/41/img_01.jpg', 0),
(46, 43, 'uploads/listings/43/img_01.jpg', 0),
(47, 44, 'uploads/listings/44/img_01.webp', 0),
(48, 45, 'uploads/listings/45/img_01.webp', 0),
(49, 46, 'uploads/listings/46/img_01.jpg', 0),
(50, 47, 'uploads/listings/47/img_01.jpg', 0),
(51, 48, 'uploads/listings/48/img_01.jpg', 0),
(52, 49, 'uploads/listings/49/img_01.jpg', 0),
(53, 50, 'uploads/listings/50/img_01.jpg', 0),
(54, 51, 'uploads/listings/51/img_01.jpg', 0);

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
(143, 12, 10, 'Hi! Is this item still available for trading?\"fresh vegetables\"', 'uploads/chat/item_1776209241_69decd59f2402.jpg', '2026-04-15 07:27:21', 'sent', 0);

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
(26, 1, 'announcement', '📢 New Announcement: sdsadas', 1, '2025-12-08 20:14:03', NULL),
(27, 4, 'announcement', '📢 New Announcement: sdsadas', 0, '2025-12-08 20:14:03', NULL),
(28, 3, 'announcement', '📢 New Announcement: sdsadas', 1, '2025-12-08 20:14:03', NULL),
(29, 3, '', '🤝 Trade completed! ippo has marked \"vegetables\" as traded with you. You can now rate each other!', 0, '2026-04-14 14:02:17', 31),
(30, 9, '', '🤝 Trade completed! You marked \"vegetables\" as traded. You can now rate your trade partner!', 1, '2026-04-14 14:02:17', 31),
(31, 6, '', '🤝 Trade completed! ippo has marked \"srgsdgsdg\" as traded with you. You can now rate each other!', 0, '2026-04-14 16:15:02', 30),
(32, 9, '', '🤝 Trade completed! You marked \"srgsdgsdg\" as traded. You can now rate your trade partner!', 1, '2026-04-14 16:15:02', 30),
(33, 12, '', '🤝 Trade done! jm conchada traded \"fresh vegetables\" for your \"used headphones\". Rate each other now!', 1, '2026-04-14 22:21:35', 46),
(34, 10, '', '🤝 Trade done! You traded \"fresh vegetables\" for \"used headphones\". Rate your trade partner now!', 0, '2026-04-14 22:21:35', 46),
(35, 12, 'announcement', '📢 New Announcement: welcome to cyclepoint', 1, '2026-04-14 23:19:44', NULL),
(36, 10, 'announcement', '📢 New Announcement: welcome to cyclepoint', 0, '2026-04-14 23:19:44', NULL),
(37, 1, 'announcement', '📢 New Announcement: welcome to cyclepoint', 1, '2026-04-14 23:19:44', NULL),
(38, 11, 'announcement', '📢 New Announcement: welcome to cyclepoint', 0, '2026-04-14 23:19:44', NULL),
(39, 13, 'announcement', '📢 New Announcement: welcome to cyclepoint', 0, '2026-04-14 23:19:44', NULL);

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
-- Table structure for table `trades`
--

CREATE TABLE `trades` (
  `id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL COMMENT 'Item that was traded',
  `initiator_id` int(11) NOT NULL COMMENT 'User who marked the trade',
  `partner_id` int(11) NOT NULL COMMENT 'The other user in the trade',
  `status` enum('completed') NOT NULL DEFAULT 'completed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Completed trades between users';

--
-- Dumping data for table `trades`
--

INSERT INTO `trades` (`id`, `listing_id`, `initiator_id`, `partner_id`, `status`, `created_at`) VALUES
(3, 46, 10, 12, 'completed', '2026-04-15 06:21:35');

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
(1, 'jm conchada', 'jeyemm03@gmail.com', '09053716679', 'antipolo city', 'uploads/profiles/profile_1_1776206297.png', '2025-12-07 08:57:37', '$2y$10$9Km2QVAYkFEJCIuO6GPal.1SUU00z7AJePtBrgL/YGHKXuzLaPh0e', NULL, NULL, '2026-04-15 06:25:29', 0, 'admin', NULL, NULL, NULL, NULL),
(10, 'jm conchada', 'jeyemconchada333@gmail.com', '09053716679', 'antipolo city', 'uploads/profiles/profile_10_1776199025.png', '2026-04-15 03:54:30', '$2y$10$fS4hn9RYjvRB8fCvENMpO.Ys42zVINl0SLTbanB6iSfRaVNYAC9F.', NULL, NULL, '2026-04-15 07:13:07', 0, 'user', NULL, NULL, NULL, NULL),
(11, 'kaithlyn obenza', 'kaith.obenza@gmail.com', '09454310276', 'quezon city', 'uploads/profiles/profile_11_1776198750.png', '2026-04-15 03:55:37', '$2y$10$qVEUsZn.tpzdciaVG6QzF.s49/p0s13Ci8xXQRAZ1qpyKjIcdRAR6', NULL, NULL, '2026-04-15 07:02:39', 0, 'user', NULL, NULL, NULL, NULL),
(12, 'angelo de guzman', 'itsme.dg@gmail.com', '09626060436', 'brgy. cuyambay, tanay rizal', 'uploads/profiles/profile_12_1776198370.png', '2026-04-15 03:57:10', '$2y$10$2OYULEkzccg2dz7nTkEbJu7czysVvCOqqtFkm6cZvqod6Q1cKqZwy', NULL, NULL, '2026-04-15 08:51:45', 0, 'user', NULL, NULL, NULL, NULL),
(13, 'daphne sancha', 'sanchadaphne21@gmail.com', '097037360269', 'quezon city', 'uploads/profiles/profile_13_1776197896.png', '2026-04-15 03:58:15', '$2y$10$qar7rNkGr2FyqEdN0c8J1ey1.4d3k6nVyiJwCMKmlRm9Lvb5O/.Iy', NULL, NULL, NULL, 0, 'user', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_ratings`
--

CREATE TABLE `user_ratings` (
  `id` int(11) NOT NULL,
  `rater_id` int(11) NOT NULL,
  `ratee_id` int(11) NOT NULL,
  `rating` tinyint(1) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_ratings`
--

INSERT INTO `user_ratings` (`id`, `rater_id`, `ratee_id`, `rating`, `comment`, `created_at`, `updated_at`) VALUES
(3, 10, 12, 4, 'dtest', '2026-04-15 06:21:49', '2026-04-15 06:21:49');

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
-- Indexes for table `trades`
--
ALTER TABLE `trades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_listing` (`listing_id`),
  ADD KEY `idx_initiator` (`initiator_id`),
  ADD KEY `idx_partner` (`partner_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_ratings`
--
ALTER TABLE `user_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rater_ratee` (`rater_id`,`ratee_id`),
  ADD KEY `idx_ratee` (`ratee_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `listings`
--
ALTER TABLE `listings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `listing_images`
--
ALTER TABLE `listing_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=144;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `restriction_history`
--
ALTER TABLE `restriction_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trades`
--
ALTER TABLE `trades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_ratings`
--
ALTER TABLE `user_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- Constraints for table `trades`
--
ALTER TABLE `trades`
  ADD CONSTRAINT `fk_trade_initiator` FOREIGN KEY (`initiator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_trade_listing` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_trade_partner` FOREIGN KEY (`partner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_ratings`
--
ALTER TABLE `user_ratings`
  ADD CONSTRAINT `fk_rating_ratee` FOREIGN KEY (`ratee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rating_rater` FOREIGN KEY (`rater_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
