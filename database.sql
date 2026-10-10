-- Millennium India Group - Event Ticket Booking System
-- Database Schema & Seed Data (MySQL / MariaDB)
-- Compatible with Hostinger, cPanel, and Localhost

-- Disable foreign key checks for clean setup
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Admins Table
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Events Table
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(120) UNIQUE NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `venue` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `banner_image` VARCHAR(255),
  `is_published` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_slug` (`slug`),
  INDEX `idx_published` (`is_published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Ticket Categories Table
DROP TABLE IF EXISTS `ticket_categories`;
CREATE TABLE `ticket_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `people_allowed` INT DEFAULT 1,
  `total_quantity` INT NOT NULL,
  `available_quantity` INT NOT NULL,
  `status` ENUM('active', 'sold_out', 'hidden') DEFAULT 'active',
  INDEX `idx_event_id` (`event_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bookings Table
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_uid` VARCHAR(30) UNIQUE NOT NULL,
  `event_id` INT NOT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_phone` VARCHAR(15) NOT NULL,
  `customer_email` VARCHAR(120) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('direct_upi', 'gateway') NOT NULL,
  `payment_status` ENUM('pending', 'verified', 'failed', 'refunded') DEFAULT 'pending',
  `transaction_ref` VARCHAR(100) NULL,
  `qr_token` VARCHAR(64) UNIQUE NOT NULL,
  `is_checked_in` TINYINT(1) DEFAULT 0,
  `checked_in_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_booking_uid` (`booking_uid`),
  INDEX `idx_qr_token` (`qr_token`),
  INDEX `idx_payment_status` (`payment_status`),
  INDEX `idx_is_checked_in` (`is_checked_in`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Booking Items Table
DROP TABLE IF EXISTS `booking_items`;
CREATE TABLE `booking_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `ticket_category_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  INDEX `idx_booking_id` (`booking_id`),
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`ticket_category_id`) REFERENCES `ticket_categories`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Site Settings Table
DROP TABLE IF EXISTS `site_settings`;
CREATE TABLE `site_settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- SEED DATA

-- Admin Account (User: admin / Password: Admin@MIG2026!)
INSERT INTO `admins` (`username`, `password_hash`, `email`) VALUES
('admin', '$2y$10$Ov0hF27rsu.h2Y9cn99F9e.PilWHS4vlCyMbSgT/Vpfjd6HbUcMSK', 'admin@millenniumindiagroup.com');

-- Launch Event: Jashn-E-Garba 2026
INSERT INTO `events` (`id`, `slug`, `title`, `date`, `start_time`, `end_time`, `venue`, `description`, `banner_image`, `is_published`) VALUES
(1, 'jashn-e-garba-2026', 'Jashn-E-Garba 2026', '2026-10-16', '17:00:00', '21:00:00', 'Unique Garden, Patna', 
'Step into the most grand, culturally vibrant and electrifying Dandiya & Garba Mahotsav of the year brought to you by Millennium India Group! Experience live authentic Gujarati folk band, celebrity DJ performances, mouthwatering festive food stalls, royal photo booths, traditional prize competitions (Best Attire, Best Garba Performer, Best Couple Dancer), and high-security family friendly environment. Book your passes early to secure guaranteed entry!', 
'assets/uploads/garba-banner.jpg', 1);

-- Ticket Categories for Jashn-E-Garba 2026
INSERT INTO `ticket_categories` (`event_id`, `name`, `price`, `people_allowed`, `total_quantity`, `available_quantity`, `status`) VALUES
(1, 'Early Bird - Only Entry', 999.00, 1, 500, 485, 'active'),
(1, 'Early Bird Couple', 1800.00, 2, 300, 292, 'active'),
(1, 'Early Bird Family', 2999.00, 4, 150, 147, 'active'),
(1, 'VIP Celebrity Lounge', 4999.00, 2, 50, 48, 'active');

-- Default Site Settings
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'Millennium India Group'),
('site_tagline', 'Premier Cultural Events & Festivities'),
('contact_email', 'support@millenniumindiagroup.com'),
('contact_phone', '+91 98765 43210'),
('contact_address', 'Exhibition Road / Bailey Road, Patna, Bihar 800001'),
('upi_id', 'millenniumindia@upi'),
('upi_payee_name', 'Millennium India Group'),
('razorpay_key_id', 'rzp_test_migDemoKey123'),
('razorpay_key_secret', 'migSecretLiveDemoKey987654321'),
('gateway_mode', 'test'),
('currency_symbol', '₹'),
('terms_conditions', '1. Tickets once purchased are non-refundable.\n2. Entry wristbands/passes will be issued at the gate against valid digital QR ticket scan.\n3. Traditional Dandiya attire is recommended.\n4. Outside food, beverages, and restricted substances are strictly prohibited.\n5. Management reserves right of admission.');
