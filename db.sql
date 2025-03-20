-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.30 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table school_management.class
CREATE TABLE IF NOT EXISTS `class` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_id` int DEFAULT NULL,
  `teacher_id` int NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `schedule_start_date` date NOT NULL,
  `schedule_end_date` date NOT NULL,
  `schedule_time` varchar(20) NOT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `capacity` int NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `class_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teacher_details` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table school_management.class: ~0 rows (approximately)
INSERT INTO `class` (`id`, `course_id`, `teacher_id`, `class_name`, `schedule_start_date`, `schedule_end_date`, `schedule_time`, `room_number`, `capacity`, `description`, `created_at`, `updated_at`) VALUES
	(1, 1, 5, 'Management Information System', '2025-03-16', '2025-03-22', '7:30 - 9:00', '406', 50, 'MIS Class', '2025-03-16 11:21:34', '2025-03-18 09:05:17');

-- Dumping structure for table school_management.courses
CREATE TABLE IF NOT EXISTS `courses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `department` varchar(50) DEFAULT NULL,
  `credits` int NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_code` (`course_code`),
  KEY `idx_course_code` (`course_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table school_management.courses: ~4 rows (approximately)
INSERT INTO `courses` (`id`, `course_code`, `course_name`, `department`, `credits`, `description`) VALUES
	(1, 'CS101', 'Computer Science', 'Science', 4, 'Management Information System, Software Engineering & IT Project Management, Web Development, OOAD, Linux'),
	(2, 'CS102', 'Biology', 'Computer Science', 3, 'Basic software engineering'),
	(3, 'MT101', 'Mathematics', 'Science', 4, 'Maths'),
	(4, 'PH101', 'Physic', 'Science', 4, 'Science');

-- Dumping structure for table school_management.enrollments
CREATE TABLE IF NOT EXISTS `enrollments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `course_id` int DEFAULT NULL,
  `class_id` int NOT NULL,
  `enrollment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','completed','dropped') DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `course_id` (`course_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student_details` (`id`),
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `enrollments_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `class` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table school_management.enrollments: ~0 rows (approximately)
INSERT INTO `enrollments` (`id`, `student_id`, `course_id`, `class_id`, `enrollment_date`, `status`) VALUES
	(1, 1, 1, 1, '2025-03-18 09:13:49', 'active');

-- Dumping structure for table school_management.grades
CREATE TABLE IF NOT EXISTS `grades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `class_id` int NOT NULL,
  `academic_year` varchar(9) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `grade` varchar(2) NOT NULL,
  `gpa_value` decimal(3,1) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student_details` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `class` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grades_chk_1` CHECK (((`score` >= 0) and (`score` <= 100)))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table school_management.grades: ~0 rows (approximately)
INSERT INTO `grades` (`id`, `student_id`, `class_id`, `academic_year`, `score`, `grade`, `gpa_value`, `created_at`) VALUES
	(1, 1, 1, '2025-2025', 85.00, 'A', 4.0, '2025-03-18 16:14:34');

-- Dumping structure for table school_management.student_details
CREATE TABLE IF NOT EXISTS `student_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `enrollment_date` date NOT NULL,
  `graduation_date` date NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `address` text,
  `course_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_graduation_date` (`graduation_date`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `student_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_details_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table school_management.student_details: ~4 rows (approximately)
INSERT INTO `student_details` (`id`, `user_id`, `full_name`, `student_id`, `enrollment_date`, `graduation_date`, `phone_number`, `address`, `course_id`) VALUES
	(1, 2, 'John Doe', 'S00001', '2025-03-15', '2025-03-20', '012345678', 'Student 01', 1),
	(19, 22, 'hengkakada@gmail.com', 'S00002', '2025-03-20', '2029-03-20', '', '', 1),
	(20, 23, 'test', 'S00003', '2025-03-20', '2029-03-20', '', 'asdfasdf\r\nasdfas', 1),
	(21, 24, 'asfda asdfasdf', 'S00004', '2025-03-20', '2029-03-20', '', 'asdfasdf\r\nasdfas', 2);

-- Dumping structure for table school_management.teacher_details
CREATE TABLE IF NOT EXISTS `teacher_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `hire_date` date NOT NULL,
  `qualification` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `course_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `teacher_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_details_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table school_management.teacher_details: ~0 rows (approximately)
INSERT INTO `teacher_details` (`id`, `user_id`, `full_name`, `hire_date`, `qualification`, `phone_number`, `course_id`) VALUES
	(1, 5, 'Jane Smith', '2025-03-16', 'PhD', '0123456789', 1);

-- Dumping structure for table school_management.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','student','teacher') NOT NULL DEFAULT 'student',
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `password_reset_token` varchar(100) DEFAULT NULL,
  `password_reset_expiry` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `profile_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table school_management.users: ~6 rows (approximately)
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `is_active`, `last_login`, `password_reset_token`, `password_reset_expiry`, `created_at`, `updated_at`, `profile_image`) VALUES
	(1, 'admin', 'admin@school.edu', '$2y$10$kNahKpb3R24p2EZcQhbsbO4/HGqdOkISe.2NZdzKYSacqPjo0YKSy', 'admin', 1, '2025-03-20 07:53:38', 'aa8f419ea817fb96d87bc584ba736bc0', '2025-03-19 11:22:38', '2025-03-08 12:18:10', '2025-03-20 07:53:38', 'https://imgs.search.brave.com/2H2zX1u2EUweA1kYD0BOBaMOLn310c-K6GB9fgzKQvM/rs:fit:500:0:0:0/g:ce/aHR0cHM6Ly9hc3Nl/dHMtY2RuLjEyM3Jm/LmNvbS9pbmRleC9z/dGF0aWMvYXNzZXRz/L2FsbC1pbi1vbmUt/cGxhbi9waG90b3Nf/djIuanBn'),
	(2, 'student01', 'student01@gmail.com', '$2y$10$WkDXuOaRnCX5/VuPY/8CEuVh.31rJC9LCTFg4hotlagvpTTlNeCY2', 'student', 1, '2025-03-19 13:08:03', NULL, NULL, '2025-03-15 14:03:18', '2025-03-19 13:08:03', 'https://imgs.search.brave.com/N8bokkIDadBNikMBzTIhwSwS2t2BQid44-sor_IYvcs/rs:fit:860:0:0:0/g:ce/aHR0cHM6Ly9jZG4u/cGl4YWJheS5jb20v/cGhvdG8vMjAyMy8w/OS8xOS8xMi81OS9l/YWdsZS04MjYyNTU1/XzY0MC5qcGc'),
	(5, 'teacher01', 'teacher01@gmail.com', '$2y$10$quR.MtqK2Wo/VpHJZwEZbeSuuD/i3LaRKEA2icYD4tXfFg6sfrjNe', 'teacher', 1, NULL, NULL, NULL, '2025-03-16 11:18:09', '2025-03-18 12:16:55', NULL),
	(22, 'kakada', 'kakada@gmail.com', '$2y$12$2.VEl7PnlKL2mjrbmUF06.vPIX3kYngeV5oXvtH9E50F7KDagksYy', 'student', 1, '2025-03-20 09:22:18', NULL, NULL, '2025-03-20 09:21:45', '2025-03-20 09:22:18', NULL),
	(23, 'phanchart', 'keosivphanchart@gmail.com', '$2y$12$Ct9WXMmg1DcsAzPl8c4nEegqRUeS3Q8v25I68WKUbnOkSbJQMp9IS', 'student', 1, NULL, NULL, NULL, '2025-03-20 11:17:33', '2025-03-20 11:17:33', NULL),
	(24, 'phanchart1', 'keosivphanchar1t@gmail.com', '$2y$12$Bj3uXHuWYK8Z5PserOKqhOpdSojFZlhC8jQ9GS223YnLwG92bUPCe', 'student', 1, NULL, NULL, NULL, '2025-03-20 11:27:16', '2025-03-20 11:27:16', NULL);

-- Dumping structure for table school_management.user_sessions
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `session_token` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Dumping data for table school_management.user_sessions: ~0 rows (approximately)
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `created_at`, `last_activity`) VALUES
	(1, 2, 'aaee7faf6cb95c6efb009f128882523cd5f0ffb0b744a060fea89f140fc34545', '2025-03-19 13:08:03', '2025-03-19 13:08:03'),
	(2, 1, '46d6601ff212755b01512a540ccf540ca3406a95b31304836a1ad3ab5f6a4090', '2025-03-19 14:55:07', '2025-03-19 14:55:07'),
	(4, 1, 'bce4131c830e17a7be524dec4d808b3d0f0b79cb5a686d6f6d52c88ff9a2f14e', '2025-03-20 07:06:22', '2025-03-20 07:06:22'),
	(5, 1, '85b0385d2c1f42512a01ed298b8e65f65770b5b7f099cd2a80394672660cab9e', '2025-03-20 07:22:30', '2025-03-20 07:22:30'),
	(6, 1, '989a308479ce39b8fbe8d5f0cd22603a0f37c2e06eca6d98ff251e62d1c8cad8', '2025-03-20 07:23:22', '2025-03-20 07:23:22'),
	(7, 1, '9d8d25915ddf5246b206c7fe96dbb4eb0573251e48e37746fff2c33e25236b39', '2025-03-20 07:38:29', '2025-03-20 07:38:29'),
	(9, 1, '5f8f17ac509ded6d518a8f8377420fe9647b903202fcb4c0d5a7ced5b9c1034a', '2025-03-20 07:53:38', '2025-03-20 07:53:38'),
	(13, 22, 'de924409eb50e17f87d8ce06d7dd11bf69ce7d80a0390c5f4cae8032768374de', '2025-03-20 09:22:18', '2025-03-20 09:22:18');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
