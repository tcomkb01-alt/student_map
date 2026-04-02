-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 02, 2026 at 09:07 AM
-- Server version: 10.11.14-MariaDB-0+deb12u2-log
-- PHP Version: 8.4.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(200) NOT NULL DEFAULT '',
  `role` enum('admin','admin_editor') NOT NULL DEFAULT 'admin',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `admins` (`id`, `username`, `password`, `full_name`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$zHL5dHdh6re5NoXvfoH9F.ldrhXaXKcERfCK/xuPxTI.mPmuaRC02', 'ผู้ดูแลระบบ', 'admin', '2026-03-29 19:12:23'),


CREATE TABLE `students` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_number` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'เลขที่ในห้อง',
  `student_id` varchar(20) NOT NULL DEFAULT '' COMMENT 'เลขประจำตัวนักเรียน',
  `prefix` enum('เด็กชาย','เด็กหญิง','นาย','นางสาว','นาง','ด.ช.','ด.ญ.') NOT NULL DEFAULT 'เด็กชาย',
  `first_name` varchar(200) NOT NULL DEFAULT '',
  `last_name` varchar(200) NOT NULL DEFAULT '',
  `class` varchar(50) NOT NULL DEFAULT '' COMMENT 'เช่น ป.1/1, ม.3/2',
  `parent_phone` varchar(20) NOT NULL DEFAULT '',
  `address` text DEFAULT NULL,
  `profile_image` varchar(500) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `visit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `visit_date` date NOT NULL,
  `visit_time` time DEFAULT NULL,
  `result` enum('home_found','not_home','moved','other') NOT NULL DEFAULT 'home_found',
  `note` text DEFAULT NULL,
  `follow_up` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_class` (`class`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_name` (`first_name`,`last_name`);

ALTER TABLE `visit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `admin_id` (`admin_id`);

ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `students`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=260;


ALTER TABLE `visit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `visit_logs`
  ADD CONSTRAINT `visit_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `visit_logs_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;
COMMIT;

