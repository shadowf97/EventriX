-- ===================================================
-- EventriX - Event Management and Ticket Booking Platform
-- Database Schema and Starter Seed Data
-- ===================================================

CREATE DATABASE IF NOT EXISTS `eventrix` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `eventrix`;

-- Drop existing tables if re-importing
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `events`;
DROP TABLE IF EXISTS `users`;

-- ---------------------------------------------------
-- 1. Table structure for table `users`
-- ---------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'host', 'user') NOT NULL DEFAULT 'user',
  `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 2. Table structure for table `events`
-- ---------------------------------------------------
CREATE TABLE `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `host_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `event_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `ticket_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_tickets` INT NOT NULL,
  `available_tickets` INT NOT NULL,
  `registration_deadline` DATETIME NOT NULL,
  `status` ENUM('Draft', 'Pending Approval', 'Approved', 'Rejected', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending Approval',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`host_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_events_status` (`status`),
  INDEX `idx_events_category` (`category`),
  INDEX `idx_events_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 3. Table structure for table `bookings`
-- ---------------------------------------------------
CREATE TABLE `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_reference` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `event_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `booking_status` ENUM('Pending Payment', 'Confirmed', 'Cancelled') NOT NULL DEFAULT 'Pending Payment',
  `payment_status` ENUM('Unpaid', 'Paid', 'Failed', 'Refunded') NOT NULL DEFAULT 'Unpaid',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  INDEX `idx_bookings_user` (`user_id`),
  INDEX `idx_bookings_event` (`event_id`),
  INDEX `idx_bookings_ref` (`booking_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- 4. Table structure for table `payments`
-- ---------------------------------------------------
CREATE TABLE `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `payment_reference` VARCHAR(50) NOT NULL UNIQUE,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `payment_status` ENUM('Paid', 'Failed', 'Refunded') NOT NULL DEFAULT 'Paid',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  INDEX `idx_payments_booking` (`booking_id`),
  INDEX `idx_payments_ref` (`payment_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------
-- Starter Seed Data
-- Demo login credentials:
-- Admin: admin / @admin
-- Host:  host / @host
-- User:  user / @user
-- ---------------------------------------------------

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `status`, `created_at`) VALUES
(1, 'System Administrator', 'admin@eventrix.com', '$2y$10$B9Zve2.1QMVTWzcTjz/bPu1.bg77t3RvXautO4UuzUjdqzg7S4IqG', 'admin', 'active', '2026-01-01 10:00:00'),
(2, 'Sarah Jenkins (Host)', 'host1@eventrix.com', '$2y$10$AMBouZSwJJmUetPz2lluiuELZmBfRX4nFkME5SRAv/jdEN/M.trAm', 'host', 'active', '2026-01-05 11:30:00'),
(3, 'Marcus Vance (Host)', 'host2@eventrix.com', '$2y$10$AMBouZSwJJmUetPz2lluiuELZmBfRX4nFkME5SRAv/jdEN/M.trAm', 'host', 'active', '2026-01-10 09:15:00'),
(4, 'Alex Rivera', 'user1@eventrix.com', '$2y$10$BNzcpnXvsu1xlGA9NPBUIOThQSQCRzbV4S/TNOS1C21h/XwfUl/j6', 'user', 'active', '2026-01-12 14:20:00'),
(5, 'Emily Chen', 'user2@eventrix.com', '$2y$10$BNzcpnXvsu1xlGA9NPBUIOThQSQCRzbV4S/TNOS1C21h/XwfUl/j6', 'user', 'active', '2026-01-15 16:45:00');

INSERT INTO `events` (`id`, `host_id`, `title`, `description`, `category`, `image`, `event_date`, `start_time`, `end_time`, `location`, `ticket_price`, `total_tickets`, `available_tickets`, `registration_deadline`, `status`, `created_at`) VALUES
(1, 2, 'Bangladesh Tech & AI Summit 2026', 'Join local engineers, researchers, and innovators to explore Artificial Intelligence, cloud systems, and web engineering.', 'Technology', 'uploads/events/event-tech.jpg', '2026-12-15', '09:00:00', '17:00:00', 'Bangabandhu International Conference Center, Agargaon, Dhaka', 1499.00, 200, 196, '2026-12-14 23:59:59', 'Approved', '2026-02-01 10:00:00'),
(2, 2, 'Dhaka Acoustic Live Night', 'An evening of live classical and acoustic music by talented local performers.', 'Music', 'uploads/events/event-music.jpg', '2026-12-20', '18:30:00', '21:30:00', 'Bangladesh Shilpakala Academy, Segunbagicha, Dhaka', 750.00, 150, 148, '2026-12-19 20:00:00', 'Approved', '2026-02-05 12:00:00'),
(3, 3, 'Startup Bangladesh Bootcamp', 'A full-day workshop on fundraising, product-market fit, and growing a startup in Bangladesh.', 'Business', 'uploads/events/event-business.jpg', '2026-12-28', '10:00:00', '16:00:00', 'ICT Tower, Agargaon, Dhaka', 1999.00, 80, 80, '2026-12-27 18:00:00', 'Approved', '2026-02-08 14:00:00'),
(4, 2, 'Dhaka Marathon & Fitness Expo', 'A community sports event with a 10K run and wellness activities.', 'Sports', 'uploads/events/event-sports.jpg', '2026-12-30', '07:00:00', '12:00:00', 'Hatirjheel Amphitheatre, Dhaka', 500.00, 300, 300, '2026-12-29 18:00:00', 'Pending Approval', '2026-02-10 15:30:00'),
(5, 3, 'Chattogram Photography Masterclass', 'A hands-on workshop on outdoor and studio lighting for photographers.', 'Workshop', 'uploads/events/event-workshop.jpg', '2026-12-22', '13:00:00', '17:00:00', 'Chattogram Shilpakala Academy, Chattogram', 1200.00, 30, 30, '2026-12-21 12:00:00', 'Approved', '2026-02-12 11:00:00');

INSERT INTO `bookings` (`id`, `booking_reference`, `user_id`, `event_id`, `quantity`, `total_amount`, `booking_status`, `payment_status`, `created_at`) VALUES
(1, 'EVX-202602-0001', 4, 1, 2, 2998.00, 'Confirmed', 'Paid', '2026-02-02 14:10:00'),
(2, 'EVX-202602-0002', 4, 2, 2, 1500.00, 'Confirmed', 'Paid', '2026-02-06 17:25:00'),
(3, 'EVX-202602-0003', 5, 1, 2, 2998.00, 'Confirmed', 'Paid', '2026-02-07 11:40:00');

INSERT INTO `payments` (`id`, `booking_id`, `payment_reference`, `amount`, `payment_method`, `payment_status`, `created_at`) VALUES
(1, 1, 'PAY-202602-0001', 2998.00, 'bKash', 'Paid', '2026-02-02 14:12:00'),
(2, 2, 'PAY-202602-0002', 1500.00, 'Nagad', 'Paid', '2026-02-06 17:26:00'),
(3, 3, 'PAY-202602-0003', 2998.00, 'bKash', 'Paid', '2026-02-07 11:41:00');

