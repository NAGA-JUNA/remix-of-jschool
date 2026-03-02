-- JNV School Database Backup
-- Generated: 2026-03-02 13:17:15
-- Server: localhost | Database: svaobtfy_aryanschools
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- Table: admission_notes
DROP TABLE IF EXISTS `admission_notes`;
CREATE TABLE `admission_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admission_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `note` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admission` (`admission_id`),
  KEY `fk_admnote_user` (`user_id`),
  CONSTRAINT `fk_admnote_admission` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_admnote_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admission_notes` (`id`,`admission_id`,`user_id`,`note`,`created_at`) VALUES
('1','4','1','Rejected','2026-02-27 00:27:39');

-- Table: admission_popup_leads
DROP TABLE IF EXISTS `admission_popup_leads`;
CREATE TABLE `admission_popup_leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `country_code` varchar(10) DEFAULT '+91',
  `email` varchar(150) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `lead_source` enum('popup_auto','need_help','exit_intent','manual') NOT NULL DEFAULT 'popup_auto',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lead_source` (`lead_source`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: admission_popup_settings
DROP TABLE IF EXISTS `admission_popup_settings`;
CREATE TABLE `admission_popup_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image_path` varchar(255) DEFAULT 'uploads/popup/default.jpg',
  `left_title` varchar(255) DEFAULT 'Start Your Child''s Journey Today',
  `left_description` text DEFAULT 'Join thousands of parents who trust us with their child\'s future education.',
  `bullet_1` varchar(255) DEFAULT 'Expert Faculty & Modern Curriculum',
  `bullet_2` varchar(255) DEFAULT 'Safe & Nurturing Environment',
  `bullet_3` varchar(255) DEFAULT 'Proven Track Record of Excellence',
  `left_tagline` varchar(255) DEFAULT 'Admissions Open 2025-26',
  `right_heading` varchar(255) DEFAULT 'Get Free Admission Guidance',
  `right_paragraph` text DEFAULT 'Fill in your details and our counselor will call you within 24 hours.',
  `is_enabled` tinyint(1) DEFAULT 1,
  `trigger_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '["page_load","need_help","exit_intent"]' CHECK (json_valid(`trigger_types`)),
  `cities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '["Hyderabad","Bangalore","Chennai","Mumbai"]' CHECK (json_valid(`cities`)),
  `branches` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{"Hyderabad":["Kukatpally","Ameerpet","Dilsukhnagar"],"Bangalore":["Koramangala","Whitefield"],"Chennai":["T Nagar","Anna Nagar"],"Mumbai":["Andheri","Dadar"]}' CHECK (json_valid(`branches`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admission_popup_settings` (`id`,`image_path`,`left_title`,`left_description`,`bullet_1`,`bullet_2`,`bullet_3`,`left_tagline`,`right_heading`,`right_paragraph`,`is_enabled`,`trigger_types`,`cities`,`branches`,`updated_at`) VALUES
('1','uploads/popup/popup_1772212947.png','Start Your Child\'s Journey Today','Join thousands of parents who trust us with their child\'s future education.','Expert Faculty & Modern Curriculum','Safe & Nurturing Environment','Proven Track Record of Excellence','Admissions Open 2025-26','Get Free Admission Guidance','Fill in your details and our counselor will call you within 24 hours.','1','[\"exit_intent\"]','[\"Anantapur\"]','{\"Anantapur\":[]}','2026-02-27 22:57:24');

-- Table: admission_status_history
DROP TABLE IF EXISTS `admission_status_history`;
CREATE TABLE `admission_status_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admission_id` int(10) unsigned NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `changed_by` int(10) unsigned DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admission` (`admission_id`),
  KEY `fk_admhist_user` (`changed_by`),
  CONSTRAINT `fk_admhist_admission` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_admhist_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admission_status_history` (`id`,`admission_id`,`old_status`,`new_status`,`changed_by`,`remarks`,`created_at`) VALUES
('1','4',NULL,'new',NULL,'Application submitted online','2026-02-26 20:01:59'),
('2','4','new','rejected','1','','2026-02-27 00:27:45'),
('3','3','new','interview_scheduled','1','Interview scheduled','2026-02-27 00:31:57');

-- Table: admissions
DROP TABLE IF EXISTS `admissions`;
CREATE TABLE `admissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` varchar(20) DEFAULT NULL,
  `student_name` varchar(100) NOT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `category` varchar(30) DEFAULT NULL,
  `aadhar_no` varchar(20) DEFAULT NULL,
  `class_applied` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `father_phone` varchar(20) DEFAULT NULL,
  `father_occupation` varchar(100) DEFAULT NULL,
  `mother_occupation` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `village` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(10) DEFAULT NULL,
  `previous_school` varchar(200) DEFAULT NULL,
  `documents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`documents`)),
  `status` enum('new','contacted','documents_verified','interview_scheduled','approved','rejected','waitlisted','converted') NOT NULL DEFAULT 'new',
  `remarks` text DEFAULT NULL,
  `interview_date` datetime DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `source` varchar(30) DEFAULT 'online',
  `priority` enum('normal','high','urgent') NOT NULL DEFAULT 'normal',
  `converted_student_id` int(10) unsigned DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_id` (`application_id`),
  KEY `idx_status` (`status`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `idx_phone` (`phone`),
  KEY `idx_is_deleted` (`is_deleted`),
  KEY `fk_admission_deleter` (`deleted_by`),
  CONSTRAINT `fk_admission_deleter` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_admission_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admissions` (`id`,`application_id`,`student_name`,`father_name`,`mother_name`,`dob`,`gender`,`blood_group`,`category`,`aadhar_no`,`class_applied`,`phone`,`email`,`father_phone`,`father_occupation`,`mother_occupation`,`address`,`village`,`district`,`state`,`pincode`,`previous_school`,`documents`,`status`,`remarks`,`interview_date`,`follow_up_date`,`source`,`priority`,`converted_student_id`,`reviewed_by`,`reviewed_at`,`created_at`,`updated_at`,`is_deleted`,`deleted_at`,`deleted_by`) VALUES
('1','ADM-2026-00001','NAGARJUNA Y','sfdgfhgj','dfhgj','2026-02-02','male',NULL,NULL,NULL,'1','8106811171','nagarjuna1014@gmail.com',NULL,NULL,NULL,'Near Saveera Hospital',NULL,NULL,NULL,NULL,'asdfgh',NULL,'approved','',NULL,'2026-02-28','online','normal',NULL,'1','2026-02-13 14:18:25','2026-02-13 14:17:15','2026-02-27 00:29:12','0',NULL,NULL),
('2','ADM-2026-00002','sandeep G','Vijay','Lakshmi','2013-02-22','male',NULL,NULL,NULL,'8','08125252086','gsandeep9891@gmail.com',NULL,NULL,NULL,'Kovvur Nagar',NULL,NULL,NULL,NULL,'Jnm school',NULL,'','',NULL,NULL,'online','normal',NULL,NULL,NULL,'2026-02-22 22:26:55','2026-02-26 19:11:04','0',NULL,NULL),
('3','ADM-2026-00003','NAGARJUNA Y','sfdgfhgj','dfhgj','2026-02-04','male',NULL,NULL,NULL,'10','8106811171','nagarjuna1014@gmail.com',NULL,NULL,NULL,'Near Saveera Hospital',NULL,NULL,NULL,NULL,'asdfgh',NULL,'interview_scheduled','Testing','2026-03-27 01:32:00',NULL,'online','normal',NULL,'1','2026-02-27 00:31:57','2026-02-26 18:28:52','2026-02-27 00:31:57','0',NULL,NULL),
('4','ADM-2026-00004','NAGARJUNA Y','NAGARJUNA','Veni','2026-02-03','male','A+','General','123456789012','7','8106811171','nagarjuna1014@gmail.com','1234567891','Software ENG','Software','Near Saveera Hospital','Anantapur','ATP','Andhra Pradesh','510051','Previous School Name','{\"birth_certificate\":\"uploads\\/documents\\/adm_1772116319_53ef3d08.png\"}','rejected','',NULL,NULL,'online','normal',NULL,'1','2026-02-27 00:27:45','2026-02-26 20:01:59','2026-02-27 00:27:54','1','2026-02-27 00:27:54','1');

-- Table: application_notes
DROP TABLE IF EXISTS `application_notes`;
CREATE TABLE `application_notes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `note` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_app` (`application_id`),
  KEY `fk_an_user` (`user_id`),
  CONSTRAINT `fk_an_app` FOREIGN KEY (`application_id`) REFERENCES `teacher_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_an_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: application_status_history
DROP TABLE IF EXISTS `application_status_history`;
CREATE TABLE `application_status_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `changed_by` int(10) unsigned DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_app` (`application_id`),
  KEY `fk_ash_user` (`changed_by`),
  CONSTRAINT `fk_ash_app` FOREIGN KEY (`application_id`) REFERENCES `teacher_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ash_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `application_status_history` (`id`,`application_id`,`old_status`,`new_status`,`changed_by`,`remarks`,`created_at`) VALUES
('1','1','new','interview_scheduled','1','Interview scheduled','2026-02-27 03:34:41');

-- Table: attendance
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `class` varchar(20) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `remarks` varchar(255) DEFAULT NULL,
  `marked_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`student_id`,`date`),
  KEY `idx_class_date` (`class`,`date`),
  KEY `marked_by` (`marked_by`),
  CONSTRAINT `fk_attendance_marker` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: audit_logs
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=452 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip_address`,`created_at`) VALUES
('381','1','clear_audit_logs','system',NULL,NULL,'106.215.175.44','2026-02-28 19:58:53'),
('382','5','login','user','5',NULL,'106.215.175.44','2026-02-28 21:04:45'),
('383','5','logout','user','5',NULL,'106.215.175.44','2026-02-28 21:27:10'),
('384','1','login','user','1',NULL,'106.215.175.44','2026-02-28 22:22:43'),
('385','1','backup_full','system',NULL,'Full backup (DB + all files) downloaded','106.215.175.44','2026-02-28 22:23:31'),
('386','1','backup_full','system',NULL,'Full backup (DB + all files) downloaded','106.215.175.44','2026-02-28 22:24:00'),
('387','1','update_feature_access','settings',NULL,NULL,'106.215.175.44','2026-02-28 22:26:07'),
('388','1','logout','user','1',NULL,'106.215.175.44','2026-02-28 22:26:18'),
('389','5','login','user','5',NULL,'106.215.171.222','2026-03-01 21:01:49'),
('390','5','logout','user','5',NULL,'106.215.171.222','2026-03-01 21:02:01'),
('391','5','login','user','5',NULL,'49.43.198.226','2026-03-02 10:20:51'),
('392','5','admission_whatsapp','admission','3',NULL,'49.43.198.226','2026-03-02 10:37:33'),
('393','5','login','user','5',NULL,'49.43.198.226','2026-03-02 10:44:14'),
('394','1','login','user','1',NULL,'122.184.73.34','2026-03-02 10:44:34'),
('395','5','logout','user','5',NULL,'49.43.198.226','2026-03-02 10:45:38'),
('396','1','login','user','1',NULL,'106.192.245.54','2026-03-02 10:54:04'),
('397','1','nav_item_updated','nav_menu_items','5',NULL,'106.192.245.54','2026-03-02 10:59:47'),
('398','1','logout','user','1',NULL,'106.192.245.54','2026-03-02 11:00:17'),
('399','5','login','user','5',NULL,'49.43.198.226','2026-03-02 11:05:01'),
('400','5','update_settings','settings',NULL,NULL,'49.43.198.226','2026-03-02 11:06:01'),
('401','5','login','user','5',NULL,'49.43.198.226','2026-03-02 11:24:53'),
('402','5','edit_slider','home_slider','5','Title: Admissions Open 2026-2027','49.43.198.226','2026-03-02 11:26:47'),
('403','5','logout','user','5',NULL,'49.43.198.226','2026-03-02 11:28:12'),
('404','5','login','user','5',NULL,'49.43.198.226','2026-03-02 11:30:44'),
('405','5','create_teacher','teacher','9',NULL,'49.43.198.226','2026-03-02 11:33:23'),
('406','5','update_teacher','teacher','8',NULL,'49.43.198.226','2026-03-02 11:33:57'),
('407','5','update_teacher','teacher','9',NULL,'49.43.198.226','2026-03-02 11:34:29'),
('408','5','logout','user','5',NULL,'49.43.198.226','2026-03-02 11:35:15'),
('409','5','logout','user','5',NULL,'49.43.198.226','2026-03-02 12:03:35'),
('410','5','login','user','5',NULL,'49.43.198.226','2026-03-02 12:07:19'),
('411','1','login','user','1',NULL,'106.192.245.54','2026-03-02 12:13:08'),
('412','5','logout','user','5',NULL,'49.43.198.226','2026-03-02 12:16:29'),
('413','1','update_feature_access','settings',NULL,NULL,'106.192.245.54','2026-03-02 12:16:52'),
('414','1','login','user','1',NULL,'49.43.198.226','2026-03-02 12:16:55'),
('415','1','update_admin_logo','settings',NULL,NULL,'49.43.198.226','2026-03-02 12:20:47'),
('416','1','logout','user','1',NULL,'49.43.198.226','2026-03-02 12:21:17'),
('417','5','login','user','5',NULL,'49.43.198.226','2026-03-02 12:21:21'),
('418','5','logout','user','5',NULL,'49.43.198.226','2026-03-02 12:22:28'),
('419',NULL,'logout','user',NULL,NULL,'49.43.198.226','2026-03-02 12:22:33'),
('420','5','login','user','5',NULL,'49.43.198.226','2026-03-02 12:42:41'),
('421','5','update_teacher','teacher','8',NULL,'49.43.198.226','2026-03-02 12:45:38'),
('422','5','update_teacher','teacher','8',NULL,'49.43.198.226','2026-03-02 12:46:06'),
('423','5','update_teacher','teacher','8',NULL,'49.43.198.226','2026-03-02 12:46:34'),
('424','5','create_teacher','teacher','10',NULL,'49.43.198.226','2026-03-02 12:51:06'),
('425','5','create_teacher','teacher','11',NULL,'49.43.198.226','2026-03-02 12:54:27'),
('426','5','update_teacher','teacher','10',NULL,'49.43.198.226','2026-03-02 12:57:04'),
('427','5','create_teacher','teacher','12',NULL,'49.43.198.226','2026-03-02 13:01:15'),
('428','5','create_teacher','teacher','13',NULL,'49.43.198.226','2026-03-02 13:04:45'),
('429','5','create_teacher','teacher','14',NULL,'49.43.198.226','2026-03-02 13:07:56'),
('430','5','create_teacher','teacher','15',NULL,'49.43.198.226','2026-03-02 13:10:49'),
('431','5','create_teacher','teacher','16',NULL,'49.43.198.226','2026-03-02 13:17:10'),
('432','5','create_teacher','teacher','17',NULL,'49.43.198.226','2026-03-02 13:24:32'),
('433','5','update_teacher','teacher','17',NULL,'49.43.198.226','2026-03-02 13:26:26'),
('434','5','update_teacher','teacher','17',NULL,'49.43.198.226','2026-03-02 13:26:55'),
('435','5','update_teacher','teacher','9',NULL,'49.43.198.226','2026-03-02 13:29:37'),
('436','5','update_teacher','teacher','16',NULL,'49.43.198.226','2026-03-02 13:31:38'),
('437','5','create_teacher','teacher','18',NULL,'49.43.198.226','2026-03-02 13:37:47'),
('438','5','create_teacher','teacher','19',NULL,'49.43.198.226','2026-03-02 13:43:50'),
('439','5','create_teacher','teacher','20',NULL,'49.43.198.226','2026-03-02 13:51:00'),
('440','5','create_teacher','teacher','21',NULL,'49.43.198.226','2026-03-02 13:53:43'),
('441','5','create_teacher','teacher','23',NULL,'49.43.198.226','2026-03-02 13:56:33'),
('442','5','create_teacher','teacher','24',NULL,'49.43.198.226','2026-03-02 13:59:48'),
('443','5','create_teacher','teacher','25',NULL,'49.43.198.226','2026-03-02 14:02:00'),
('444','5','create_teacher','teacher','26',NULL,'49.43.198.226','2026-03-02 14:05:06'),
('445','5','create_teacher','teacher','27',NULL,'49.43.198.226','2026-03-02 14:07:15'),
('446','5','create_teacher','teacher','28',NULL,'49.43.198.226','2026-03-02 14:10:14'),
('447','5','create_teacher','teacher','29',NULL,'49.43.198.226','2026-03-02 14:12:12'),
('448','5','reorder_teachers','teacher','0','Reordered teachers','49.43.198.226','2026-03-02 14:12:49'),
('449','5','logout','user','5',NULL,'49.43.198.226','2026-03-02 14:20:09'),
('450','1','login','user','1',NULL,'216.150.172.17','2026-03-02 18:46:09'),
('451','1','backup_database','system',NULL,'Database backup downloaded','216.150.172.17','2026-03-02 18:46:51');

-- Table: certificates
DROP TABLE IF EXISTS `certificates`;
CREATE TABLE `certificates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'recognition',
  `year` smallint(6) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `thumb_path` varchar(255) DEFAULT NULL,
  `file_type` enum('image','pdf') NOT NULL DEFAULT 'image',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `allow_download` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_featured` (`is_featured`),
  KEY `idx_category` (`category`),
  KEY `idx_order` (`display_order`),
  KEY `idx_deleted` (`is_deleted`),
  KEY `fk_cert_creator` (`created_by`),
  CONSTRAINT `fk_cert_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `certificates` (`id`,`title`,`description`,`category`,`year`,`file_path`,`thumb_path`,`file_type`,`is_featured`,`is_active`,`allow_download`,`display_order`,`is_deleted`,`deleted_at`,`created_by`,`created_at`,`updated_at`) VALUES
('1','TEST Certificate','TEST Certificate','govt_approval','2026','uploads/certificates/cert_1771651167_17d07c0a.webp','uploads/certificates/thumbs/thumb_cert_1771651167_17d07c0a.webp','image','1','1','0','1','1','2026-02-27 13:38:01','1','2026-02-21 10:49:27','2026-02-27 13:38:01'),
('2','TEST Certificate','The frontend is there in the code — it just needs to be uploaded to your cPanel server since PHP pages don\'t render in Lovable\'s preview.','board_affiliation','2022','uploads/certificates/cert_1771651553_fe644ea0.webp','uploads/certificates/thumbs/thumb_cert_1771651553_fe644ea0.webp','image','1','1','0','2','1','2026-02-27 13:38:04','1','2026-02-21 10:55:54','2026-02-27 13:38:04'),
('3','TEST Certificate','Added a custom email input field to the Test Email section — if left empty it defaults to your admin email. Upload the updated settings.php to your server.','recognition','2026','uploads/certificates/cert_1771651595_12e28a0e.webp','','image','1','1','0','3','1','2026-02-27 13:38:07','1','2026-02-21 10:56:35','2026-02-27 13:38:07');

-- Table: class_seat_capacity
DROP TABLE IF EXISTS `class_seat_capacity`;
CREATE TABLE `class_seat_capacity` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `class` varchar(20) NOT NULL,
  `section` varchar(10) DEFAULT 'A',
  `total_seats` int(11) NOT NULL DEFAULT 40,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `academic_year` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_class_section_year` (`class`,`section`,`academic_year`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `class_seat_capacity` (`id`,`class`,`section`,`total_seats`,`is_active`,`academic_year`,`created_at`,`updated_at`) VALUES
('1','1','A','40','1','2026-2027','2026-02-26 19:46:46','2026-02-26 19:46:46'),
('2','2','A','40','1','2026-2027','2026-02-26 19:46:46','2026-02-26 19:46:46'),
('3','3','A','40','1','2026-2027','2026-02-26 19:46:46','2026-02-26 19:46:46'),
('4','4','A','40','1','2026-2027','2026-02-26 19:46:46','2026-02-26 19:46:46'),
('5','5','A','40','1','2026-2027','2026-02-26 19:46:46','2026-02-26 19:46:46'),
('6','6','A','40','1','2026-2027','2026-02-26 19:46:46','2026-02-26 19:46:53'),
('7','7','A','40','1','2026-2027','2026-02-26 19:46:46','2026-02-26 19:46:46'),
('8','8','A','40','1','2026-2027','2026-02-26 19:46:46','2026-02-28 13:07:25'),
('9','9','A','40','1','2026-2027','2026-02-26 19:46:46','2026-02-26 19:46:46'),
('10','10','A','40','1','2026-2027','2026-02-26 19:46:46','2026-02-26 19:46:46'),
('11','11','A','40','0','2026-2027','2026-02-26 19:46:46','2026-02-27 23:19:13'),
('12','12','A','40','0','2026-2027','2026-02-26 19:46:46','2026-02-26 19:46:56');

-- Table: core_team
DROP TABLE IF EXISTS `core_team`;
CREATE TABLE `core_team` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `experience_years` int(11) DEFAULT 0,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `photo` varchar(500) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_visible` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_display_order` (`display_order`),
  KEY `idx_visible` (`is_visible`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `core_team` (`id`,`name`,`designation`,`qualification`,`subject`,`experience_years`,`email`,`phone`,`photo`,`bio`,`display_order`,`is_visible`,`is_featured`,`created_at`) VALUES
('2','G. Sandeep','Director','M.Sc, B.Ed','','10','director@aryanschools.edu.in','12345678','/uploads/photos/core_1771782308_7160.webp','Bi Info','2','1','1','2026-02-22 23:15:08'),
('3','M. Lahari','Principal','M.Sc, B.Ed','','10','principal@aryanschools.edu.in','12345678','/uploads/photos/core_1771782348_5317.webp','Bio Info','3','1','1','2026-02-22 23:15:48'),
('4','M. Nagaraju','Correspondent','','','11','correspondent@aryanschools.edu.in','','/uploads/photos/core_1772177478_9969.webp','','1','0','0','2026-02-27 13:01:18');

-- Table: enquiries
DROP TABLE IF EXISTS `enquiries`;
CREATE TABLE `enquiries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('new','contacted','closed') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `enquiries` (`id`,`name`,`phone`,`email`,`message`,`status`,`created_at`) VALUES
('8','Hi','1234567890','nagarjuna1014@gmail.com','Testing','new','2026-02-27 18:39:13'),
('10','Hi','1234567890','nagarjuna1014@gmail.com','Testing','new','2026-02-27 22:54:28'),
('12','raju','919951528113','aryanschool.atp@gmail.com','hello','closed','2026-02-28 12:38:59');

-- Table: events
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `type` enum('sports','cultural','exam','holiday','activity','academic','meeting','other') NOT NULL DEFAULT 'activity',
  `status` enum('active','draft','cancelled','completed') NOT NULL DEFAULT 'active',
  `poster` varchar(255) DEFAULT NULL,
  `views` int(10) unsigned NOT NULL DEFAULT 0,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_date` (`start_date`),
  KEY `created_by` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `idx_featured` (`is_featured`),
  CONSTRAINT `fk_event_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `events` (`id`,`title`,`description`,`start_date`,`end_date`,`start_time`,`end_time`,`location`,`type`,`status`,`poster`,`views`,`is_public`,`is_featured`,`created_by`,`created_at`,`updated_at`) VALUES
('1','No Website? No Problem.','No Website? No Problem.\r\nBuild a Credible Brand With Business Email','2026-02-22','2026-02-24',NULL,NULL,'Ananthapur','sports','completed',NULL,'1','1','0','1','2026-02-22 19:07:17','2026-03-02 00:17:59'),
('2','Add New Event - TEST','Use Draft to save without publishing\r\nFeatured events appear prominently on the public page\r\nPast active events auto-mark as Completed\r\nUpload a poster image for visual appeal\r\nSet end date for multi-day events','2026-02-22','2026-02-24',NULL,NULL,'Ananthapur','cultural','completed','uploads/events/event_1771768875_df09fc46.png','8','1','1','1','2026-02-22 19:31:16','2026-03-02 00:18:25');

-- Table: exam_results
DROP TABLE IF EXISTS `exam_results`;
CREATE TABLE `exam_results` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `exam_name` varchar(100) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `class` varchar(20) NOT NULL,
  `max_marks` int(11) NOT NULL DEFAULT 100,
  `obtained_marks` int(11) NOT NULL DEFAULT 0,
  `grade` varchar(5) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `entered_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_result` (`student_id`,`exam_name`,`subject`),
  KEY `idx_exam_class` (`exam_name`,`class`),
  KEY `entered_by` (`entered_by`),
  CONSTRAINT `fk_result_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_result_teacher` FOREIGN KEY (`entered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: feature_cards
DROP TABLE IF EXISTS `feature_cards`;
CREATE TABLE `feature_cards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `icon_class` varchar(100) NOT NULL DEFAULT 'bi-star',
  `accent_color` varchar(20) NOT NULL DEFAULT 'auto',
  `btn_text` varchar(50) NOT NULL DEFAULT 'Learn More',
  `btn_link` varchar(255) NOT NULL DEFAULT '#',
  `badge_text` varchar(50) DEFAULT NULL,
  `badge_color` varchar(20) DEFAULT '#ef4444',
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `show_stats` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `click_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `feature_cards` (`id`,`slug`,`title`,`description`,`icon_class`,`accent_color`,`btn_text`,`btn_link`,`badge_text`,`badge_color`,`is_visible`,`is_featured`,`show_stats`,`sort_order`,`click_count`,`created_at`,`updated_at`) VALUES
('1','admissions','Admissions','Apply online for admission to Aryan School.','bi-mortarboard-fill','#3b82f6','Apply Now','/public/admission-form.php','Open','#22c55e','1','1','1','1','3','2026-02-21 16:50:07','2026-02-28 17:51:34'),
('2','notifications','Notifications','Stay updated with latest announcements.','bi-bell-fill','#f59e0b','View All','/public/notifications.php',NULL,'#ef4444','1','0','1','2','5','2026-02-21 16:50:07','2026-02-28 14:54:10'),
('3','gallery','Gallery','Explore photos & videos from school life.','bi-images','#10b981','Browse','/public/gallery.php',NULL,'#8b5cf6','1','0','1','3','1','2026-02-21 16:50:07','2026-02-21 17:54:47'),
('4','events','Events','Check upcoming school events & dates.','bi-calendar-event-fill','#ef4444','View Events','/public/events.php',NULL,'#3b82f6','1','0','1','4','2','2026-02-21 16:50:07','2026-02-22 19:07:44');

-- Table: fee_components
DROP TABLE IF EXISTS `fee_components`;
CREATE TABLE `fee_components` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fee_structure_id` int(10) unsigned NOT NULL,
  `component_name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `frequency` enum('one-time','monthly','quarterly','yearly') NOT NULL DEFAULT 'yearly',
  `is_optional` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fee_structure_id` (`fee_structure_id`),
  CONSTRAINT `fk_comp_structure` FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `fee_components` (`id`,`fee_structure_id`,`component_name`,`amount`,`frequency`,`is_optional`,`display_order`) VALUES
('9','4','Admission Fee','4000.00','one-time','0','0'),
('10','4','Tuition Fee','29000.00','yearly','0','1'),
('11','2','Admission Fee','4000.00','one-time','0','0'),
('12','2','Tuition Fee','30000.00','yearly','0','1'),
('13','3','Admission Fee','4000.00','one-time','0','0'),
('14','3','Tuition Fee','30000.00','yearly','0','1');

-- Table: fee_structures
DROP TABLE IF EXISTS `fee_structures`;
CREATE TABLE `fee_structures` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `class` varchar(20) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_class_year` (`class`,`academic_year`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `fk_fee_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `fee_structures` (`id`,`class`,`academic_year`,`is_visible`,`notes`,`created_by`,`created_at`,`updated_at`) VALUES
('2','LKG','2026-2027','0','','1','2026-02-27 10:20:17','2026-02-28 13:53:29'),
('3','UKG','2026-2027','0','','1','2026-02-27 10:22:34','2026-02-28 13:53:39'),
('4','Class 1','2026-2027','0','','1','2026-02-27 10:23:23','2026-02-28 13:53:24');

-- Table: gallery_albums
DROP TABLE IF EXISTS `gallery_albums`;
CREATE TABLE `gallery_albums` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `year` varchar(10) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_category` (`category_id`),
  CONSTRAINT `fk_album_category` FOREIGN KEY (`category_id`) REFERENCES `gallery_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: gallery_categories
DROP TABLE IF EXISTS `gallery_categories`;
CREATE TABLE `gallery_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `gallery_categories` (`id`,`name`,`slug`,`cover_image`,`description`,`sort_order`,`status`,`created_at`) VALUES
('1','Academic','academic',NULL,NULL,'1','active','2026-02-12 18:35:50'),
('2','Cultural','cultural',NULL,NULL,'2','active','2026-02-12 18:35:50'),
('3','Sports','sports',NULL,NULL,'3','active','2026-02-12 18:35:50'),
('4','Events','events',NULL,NULL,'4','active','2026-02-12 18:35:50'),
('5','Infrastructure','infrastructure',NULL,NULL,'5','active','2026-02-12 18:35:50'),
('6','Students','students',NULL,NULL,'6','active','2026-02-12 18:35:50'),
('7','Teachers','teachers',NULL,NULL,'7','active','2026-02-12 18:35:50'),
('8','Campus Life','campus-life',NULL,NULL,'8','active','2026-02-12 18:35:50');

-- Table: gallery_items
DROP TABLE IF EXISTS `gallery_items`;
CREATE TABLE `gallery_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `caption` varchar(500) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `category` varchar(50) DEFAULT 'General',
  `album_id` int(10) unsigned DEFAULT NULL,
  `event_name` varchar(200) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `tags` varchar(500) DEFAULT NULL,
  `visibility` enum('public','private') NOT NULL DEFAULT 'public',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `file_path` varchar(255) NOT NULL,
  `file_type` enum('image','video') NOT NULL DEFAULT 'image',
  `original_size` int(10) unsigned DEFAULT NULL,
  `compressed_size` int(10) unsigned DEFAULT NULL,
  `batch_id` varchar(32) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_batch` (`batch_id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `idx_album` (`album_id`),
  CONSTRAINT `fk_gallery_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_item_album` FOREIGN KEY (`album_id`) REFERENCES `gallery_albums` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `gallery_items` (`id`,`title`,`description`,`caption`,`position`,`category`,`album_id`,`event_name`,`event_date`,`tags`,`visibility`,`is_featured`,`file_path`,`file_type`,`original_size`,`compressed_size`,`batch_id`,`status`,`uploaded_by`,`approved_by`,`approved_at`,`created_at`) VALUES
('2','Upload Gallery (1)','',NULL,'0','academic',NULL,'TEST Gallery',NULL,'TEST','public','0','uploads/gallery/gallery_1770903956_dc0e252b.webp','image','1762088','176516','06fd993bbc85c015895a2618b737ea62','approved','1','1','2026-02-12 19:15:57','2026-02-12 19:15:57'),
('4','Upload Gallery (3)','',NULL,'0','academic',NULL,'TEST Gallery',NULL,'TEST','public','0','uploads/gallery/gallery_1770903957_0ccc2df9.webp','image','136617','23318','06fd993bbc85c015895a2618b737ea62','approved','1','1','2026-02-12 19:15:57','2026-02-12 19:15:57'),
('5','Test Gallery','TEST Gallery',NULL,'0','academic',NULL,'TEST Gallery','2026-02-19','TEST Gallery','public','1','uploads/gallery/gallery_1771676980_680a5706.webp','image','278448','39804',NULL,'approved',NULL,'4','2026-02-21 17:59:40','2026-02-21 17:59:40');

-- Table: home_slider
DROP TABLE IF EXISTS `home_slider`;
CREATE TABLE `home_slider` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) DEFAULT NULL,
  `subtitle` text DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `badge_text` varchar(50) DEFAULT NULL,
  `cta_text` varchar(50) DEFAULT NULL,
  `animation_type` varchar(20) NOT NULL DEFAULT 'fade',
  `overlay_style` varchar(20) NOT NULL DEFAULT 'gradient-dark',
  `text_position` varchar(10) NOT NULL DEFAULT 'left',
  `overlay_opacity` int(11) NOT NULL DEFAULT 70,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `home_slider` (`id`,`title`,`subtitle`,`image_path`,`link_url`,`badge_text`,`cta_text`,`animation_type`,`overlay_style`,`text_position`,`overlay_opacity`,`sort_order`,`is_active`,`created_at`) VALUES
('1','Welcome to ARYAN English Medium School','Nurturing young minds with quality education, discipline, and values since establishment.','uploads/slider/slider_1771676596_b4163daf.webp',NULL,'Welcome','Learn More','fade','gradient-dark','left','70','1','1','2026-02-11 22:27:16'),
('2','Academic Excellence','Our students consistently achieve outstanding results in board examinations and competitive tests.','uploads/slider/slider_1771676497_b7002abc.png',NULL,'Academics','View Results','fade','gradient-dark','center','65','2','1','2026-02-11 22:27:16'),
('3','State-of-the-Art Campus','Modern classrooms, science labs, computer labs, library, sports grounds, and hostel facilities.','uploads/slider/slide3.jpg',NULL,'Campus','Take a Tour','zoom','gradient-dark','left','70','3','0','2026-02-11 22:27:16'),
('4','Sports & Co-Curricular Activities','Developing well-rounded individuals through athletics, cultural events, and extracurricular programs.','uploads/slider/slide4.jpg',NULL,'Activities','Explore','kenburns','solid-dark','right','60','4','1','2026-02-11 22:27:16'),
('5','Admissions Open 2026-2027','Apply now for the upcoming academic session. Limited seats available for Classes VI to XII.','uploads/slider/slide5.jpg',NULL,'Admissions','Apply Now','fade','gradient-dark','center','75','5','1','2026-02-11 22:27:16');

-- Table: hr_employees
DROP TABLE IF EXISTS `hr_employees`;
CREATE TABLE `hr_employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `date_of_joining` date DEFAULT NULL,
  `salary` decimal(12,2) DEFAULT 0.00,
  `probation_months` int(11) DEFAULT 6,
  `reporting_to` varchar(100) DEFAULT NULL,
  `status` enum('active','resigned','terminated') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

INSERT INTO `hr_employees` (`id`,`employee_id`,`name`,`designation`,`department`,`email`,`phone`,`date_of_joining`,`salary`,`probation_months`,`reporting_to`,`status`,`created_at`) VALUES
('1','AEMS2026001','Y NAGARJUNA','Developer','IT','admin@jnvweb.in','8106811171','2026-02-26','1000.00','6','Admin','active','2026-02-28 18:31:19');

-- Table: hr_letters
DROP TABLE IF EXISTS `hr_letters`;
CREATE TABLE `hr_letters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `letter_type` enum('appointment','joining','resignation','hike') NOT NULL,
  `reference_no` varchar(50) NOT NULL,
  `issue_date` date NOT NULL,
  `effective_date` date DEFAULT NULL,
  `salary_old` decimal(12,2) DEFAULT NULL,
  `salary_new` decimal(12,2) DEFAULT NULL,
  `increment_pct` decimal(5,2) DEFAULT NULL,
  `last_working_date` date DEFAULT NULL,
  `notice_period` varchar(50) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `extra_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra_data`)),
  `status` enum('draft','issued') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_no` (`reference_no`),
  UNIQUE KEY `prevent_dup` (`employee_id`,`letter_type`,`effective_date`),
  CONSTRAINT `hr_letters_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

INSERT INTO `hr_letters` (`id`,`employee_id`,`letter_type`,`reference_no`,`issue_date`,`effective_date`,`salary_old`,`salary_new`,`increment_pct`,`last_working_date`,`notice_period`,`reason`,`extra_data`,`status`,`created_by`,`created_at`) VALUES
('1','1','joining','Aryan/HR/JOI/2026/001','2026-02-28','2026-03-03',NULL,'1000.00',NULL,NULL,'','','{\"notice_period\":\"\",\"email_sent\":true,\"email_sent_at\":\"2026-02-28 14:17:44\",\"email_sent_to\":\"admin@jnvweb.in\"}','issued','1','2026-02-28 18:32:04'),
('3','1','appointment','Aryan/HR/APP/2026/001','2026-02-28',NULL,NULL,'1000.00',NULL,NULL,'','','{\"notice_period\":\"\"}','draft','1','2026-02-28 18:47:33');

-- Table: hr_payslips
DROP TABLE IF EXISTS `hr_payslips`;
CREATE TABLE `hr_payslips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `pay_month` varchar(7) NOT NULL,
  `basic_salary` decimal(12,2) DEFAULT 0.00,
  `hra` decimal(12,2) DEFAULT 0.00,
  `da` decimal(12,2) DEFAULT 0.00,
  `other_allowances` decimal(12,2) DEFAULT 0.00,
  `pf_deduction` decimal(12,2) DEFAULT 0.00,
  `tax_deduction` decimal(12,2) DEFAULT 0.00,
  `other_deductions` decimal(12,2) DEFAULT 0.00,
  `net_salary` decimal(12,2) DEFAULT 0.00,
  `status` enum('draft','issued') DEFAULT 'draft',
  `extra_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra_data`)),
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `one_per_month` (`employee_id`,`pay_month`),
  CONSTRAINT `hr_payslips_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `hr_employees` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

INSERT INTO `hr_payslips` (`id`,`employee_id`,`pay_month`,`basic_salary`,`hra`,`da`,`other_allowances`,`pf_deduction`,`tax_deduction`,`other_deductions`,`net_salary`,`status`,`extra_data`,`created_by`,`created_at`) VALUES
('1','1','2026-01','1000.00','400.00','100.00','500.00','300.00','200.00','0.00','1500.00','draft',NULL,'1','2026-02-28 19:36:34');

-- Table: job_openings
DROP TABLE IF EXISTS `job_openings`;
CREATE TABLE `job_openings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `employment_type` enum('full-time','part-time','contract') NOT NULL DEFAULT 'full-time',
  `location` varchar(150) DEFAULT NULL,
  `salary_range` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `fk_job_creator` (`created_by`),
  CONSTRAINT `fk_job_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: leadership_profiles
DROP TABLE IF EXISTS `leadership_profiles`;
CREATE TABLE `leadership_profiles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: letter_templates
DROP TABLE IF EXISTS `letter_templates`;
CREATE TABLE `letter_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `letter_type` enum('appointment','joining','resignation','hike') NOT NULL,
  `template_content` text NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `letter_type` (`letter_type`)
) ENGINE=InnoDB AUTO_INCREMENT=285 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

INSERT INTO `letter_templates` (`id`,`letter_type`,`template_content`,`status`,`updated_at`) VALUES
('1','appointment','<div style=\"font-family:\'Times New Roman\',serif;max-width:800px;margin:0 auto;padding:40px;\">\r\n<div style=\"text-align:center;margin-bottom:30px;\">\r\n    <img src=\"{{school_logo}}\" style=\"height:80px;margin-bottom:10px;\" alt=\"Logo\">\r\n    <h2 style=\"margin:0;color:#1e40af;\">{{school_name}}</h2>\r\n    <p style=\"margin:5px 0;color:#666;font-size:14px;\">{{school_address}}</p>\r\n    <hr style=\"border:2px solid #1e40af;margin:15px 0;\">\r\n</div>\r\n<p style=\"text-align:right;\"><strong>Ref:</strong> {{reference_no}}<br><strong>Date:</strong> {{issue_date}}</p>\r\n<h3 style=\"text-align:center;text-decoration:underline;margin:30px 0;\">APPOINTMENT LETTER</h3>\r\n<p>Dear <strong>{{employee_name}}</strong>,</p>\r\n<p>We are pleased to inform you that you have been selected for the position of <strong>{{designation}}</strong> in the <strong>{{department}}</strong> department at {{school_name}}.</p>\r\n<p><strong>Date of Joining:</strong> {{date_of_joining}}</p>\r\n<p><strong>Monthly Salary:</strong> ?{{salary_new}}</p>\r\n<p><strong>Probation Period:</strong> {{probation_months}} months</p>\r\n<p><strong>Reporting To:</strong> {{reporting_to}}</p>\r\n<h4 style=\"margin-top:25px;\">Terms & Conditions:</h4>\r\n<ol style=\"line-height:2;\">\r\n    <li>You will be on probation for a period of {{probation_months}} months from the date of joining.</li>\r\n    <li>Working hours: 8:00 AM to 4:00 PM, Monday to Saturday.</li>\r\n    <li>You are entitled to casual leave (12 days), sick leave (6 days), and earned leave (15 days) per year.</li>\r\n    <li>Either party may terminate employment with one month\'s written notice during probation.</li>\r\n    <li>You shall maintain confidentiality regarding all institutional matters.</li>\r\n</ol>\r\n<p>We look forward to your valuable contribution to our institution.</p>\r\n<div style=\"margin-top:60px;\">\r\n    <div style=\"float:right;text-align:center;\">\r\n{{digital_signature}}\r\n        <div style=\"border-top:1px solid #333;padding-top:5px;width:200px;\">\r\n            <strong>Principal / HR Manager</strong><br>\r\n            {{school_name}}\r\n        </div>\r\n    </div>\r\n    <div style=\"clear:both;\"></div>\r\n</div>\r\n</div>','active','2026-02-28 19:06:42'),
('2','joining','<div style=\"font-family:\'Times New Roman\',serif;max-width:800px;margin:0 auto;padding:40px;\">\r\n<div style=\"text-align:center;margin-bottom:30px;\">\r\n    <img src=\"{{school_logo}}\" style=\"height:80px;margin-bottom:10px;\" alt=\"Logo\">\r\n    <h2 style=\"margin:0;color:#1e40af;\">{{school_name}}</h2>\r\n    <p style=\"margin:5px 0;color:#666;font-size:14px;\">{{school_address}}</p>\r\n    <hr style=\"border:2px solid #1e40af;margin:15px 0;\">\r\n</div>\r\n<p style=\"text-align:right;\"><strong>Ref:</strong> {{reference_no}}<br><strong>Date:</strong> {{issue_date}}</p>\r\n<h3 style=\"text-align:center;text-decoration:underline;margin:30px 0;\">JOINING CONFIRMATION LETTER</h3>\r\n<p>Dear <strong>{{employee_name}}</strong>,</p>\r\n<p>We are pleased to confirm that your probation period has been successfully completed. You are hereby confirmed as a permanent employee of <strong>{{school_name}}</strong> effective from <strong>{{effective_date}}</strong>.</p>\r\n<p><strong>Employee ID:</strong> {{employee_id}}</p>\r\n<p><strong>Designation:</strong> {{designation}}</p>\r\n<p><strong>Department:</strong> {{department}}</p>\r\n<p><strong>Revised Monthly Salary:</strong> ?{{salary_new}}</p>\r\n<p><strong>Reporting Manager:</strong> {{reporting_to}}</p>\r\n<p>All other terms and conditions as per your appointment letter remain unchanged.</p>\r\n<p>We appreciate your dedication and look forward to your continued contribution.</p>\r\n<div style=\"margin-top:60px;\">\r\n    <div style=\"float:right;text-align:center;\">\r\n{{digital_signature}}\r\n        <div style=\"border-top:1px solid #333;padding-top:5px;width:200px;\">\r\n            <strong>Principal / HR Manager</strong><br>\r\n            {{school_name}}\r\n        </div>\r\n    </div>\r\n    <div style=\"clear:both;\"></div>\r\n</div>\r\n</div>','active','2026-02-28 19:47:36'),
('3','resignation','<div style=\"font-family:\'Times New Roman\',serif;max-width:800px;margin:0 auto;padding:40px;\">\n<div style=\"text-align:center;margin-bottom:30px;\">\n    <img src=\"{{school_logo}}\" style=\"height:80px;margin-bottom:10px;\" alt=\"Logo\">\n    <h2 style=\"margin:0;color:#1e40af;\">{{school_name}}</h2>\n    <p style=\"margin:5px 0;color:#666;font-size:14px;\">{{school_address}}</p>\n    <hr style=\"border:2px solid #1e40af;margin:15px 0;\">\n</div>\n<p style=\"text-align:right;\"><strong>Ref:</strong> {{reference_no}}<br><strong>Date:</strong> {{issue_date}}</p>\n<h3 style=\"text-align:center;text-decoration:underline;margin:30px 0;\">RESIGNATION ACCEPTANCE LETTER</h3>\n<p>Dear <strong>{{employee_name}}</strong>,</p>\n<p>This is to acknowledge and accept your resignation from the position of <strong>{{designation}}</strong> in the <strong>{{department}}</strong> department.</p>\n<p><strong>Last Working Date:</strong> {{last_working_date}}</p>\n<p><strong>Notice Period:</strong> {{notice_period}}</p>\n<p>Please ensure the following before your last working day:</p>\n<ul style=\"line-height:2;\">\n    <li>Complete all pending work and hand over responsibilities.</li>\n    <li>Return all institutional property including ID card, keys, and equipment.</li>\n    <li>Clear any outstanding dues.</li>\n    <li>Obtain clearance from all departments.</li>\n</ul>\n<p>Your final settlement will be processed after completion of the clearance procedure.</p>\n<p>We wish you all the best in your future endeavours.</p>\n<div style=\"margin-top:60px;\">\n    <div style=\"float:right;text-align:center;\">\n        <div style=\"border-top:1px solid #333;padding-top:5px;width:200px;\">\n            <strong>Principal / HR Manager</strong><br>\n            {{school_name}}\n        </div>\n    </div>\n    <div style=\"clear:both;\"></div>\n</div>\n</div>','active','2026-02-28 18:20:01'),
('4','hike','<div style=\"font-family:\'Times New Roman\',serif;max-width:800px;margin:0 auto;padding:40px;\">\n<div style=\"text-align:center;margin-bottom:30px;\">\n    <img src=\"{{school_logo}}\" style=\"height:80px;margin-bottom:10px;\" alt=\"Logo\">\n    <h2 style=\"margin:0;color:#1e40af;\">{{school_name}}</h2>\n    <p style=\"margin:5px 0;color:#666;font-size:14px;\">{{school_address}}</p>\n    <hr style=\"border:2px solid #1e40af;margin:15px 0;\">\n</div>\n<p style=\"text-align:right;\"><strong>Ref:</strong> {{reference_no}}<br><strong>Date:</strong> {{issue_date}}</p>\n<h3 style=\"text-align:center;text-decoration:underline;margin:30px 0;\">SALARY REVISION / INCREMENT LETTER</h3>\n<p>Dear <strong>{{employee_name}}</strong>,</p>\n<p>We are pleased to inform you that based on your performance and contribution, the management has decided to revise your salary effective from <strong>{{effective_date}}</strong>.</p>\n<table style=\"width:100%;border-collapse:collapse;margin:20px 0;\" border=\"1\" cellpadding=\"10\">\n    <tr style=\"background:#f0f4ff;\">\n        <td><strong>Previous Salary</strong></td>\n        <td style=\"text-align:right;\">?{{salary_old}} /month</td>\n    </tr>\n    <tr style=\"background:#e8ffe8;\">\n        <td><strong>Revised Salary</strong></td>\n        <td style=\"text-align:right;font-size:1.1em;color:#16a34a;\"><strong>?{{salary_new}} /month</strong></td>\n    </tr>\n    <tr>\n        <td><strong>Increment</strong></td>\n        <td style=\"text-align:right;\">{{increment_pct}}%</td>\n    </tr>\n</table>\n<p><strong>Reason:</strong> {{reason}}</p>\n<p>All other terms and conditions of your employment remain unchanged.</p>\n<p>We appreciate your hard work and look forward to your continued excellence.</p>\n<div style=\"margin-top:60px;\">\n    <div style=\"float:right;text-align:center;\">\n        <div style=\"border-top:1px solid #333;padding-top:5px;width:200px;\">\n            <strong>Principal / HR Manager</strong><br>\n            {{school_name}}\n        </div>\n    </div>\n    <div style=\"clear:both;\"></div>\n</div>\n</div>','active','2026-02-28 18:20:01');

-- Table: nav_menu_items
DROP TABLE IF EXISTS `nav_menu_items`;
CREATE TABLE `nav_menu_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(100) NOT NULL,
  `url` varchar(255) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `link_type` enum('internal','external') NOT NULL DEFAULT 'internal',
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `is_cta` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `parent_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `nav_menu_items` (`id`,`label`,`url`,`icon`,`link_type`,`is_visible`,`is_cta`,`sort_order`,`created_at`,`updated_at`,`parent_id`) VALUES
('1','Home','/','bi-house-fill','internal','1','0','1','2026-02-21 20:07:36','2026-02-21 20:07:36',NULL),
('2','About Us','/public/about.php','bi-info-circle','internal','1','0','2','2026-02-21 20:07:36','2026-02-27 23:35:00',NULL),
('3','Our Teachers','/public/teachers.php','bi-person-badge','internal','1','0','3','2026-02-21 20:07:36','2026-02-27 13:31:26','2'),
('4','Notifications','/public/notifications.php','bi-bell','internal','1','0','4','2026-02-21 20:07:36','2026-02-21 20:07:36',NULL),
('5','Gallery','/public/gallery.php','bi-images','internal','1','0','5','2026-02-21 20:07:36','2026-03-02 10:59:47',NULL),
('6','Events','/public/events.php','bi-calendar-event','internal','1','0','6','2026-02-21 20:07:36','2026-02-27 13:32:35',NULL),
('7','Fee Structure','/public/fee-structure.php','bi-cash-stack','internal','1','0','7','2026-02-21 20:07:36','2026-02-27 13:32:00','2'),
('8','Certificates','/public/certificates.php','bi-award','internal','1','0','8','2026-02-21 20:07:36','2026-02-27 13:32:08','2'),
('9','Apply Now','/public/admission-form.php','bi-pencil-square','internal','1','1','9','2026-02-21 20:07:36','2026-02-21 20:07:36',NULL),
('10','Join Us','/join-us.php','bi-briefcase-fill','internal','1','0','7','2026-02-27 03:05:05','2026-02-28 13:26:36',NULL);

-- Table: notification_attachments
DROP TABLE IF EXISTS `notification_attachments`;
CREATE TABLE `notification_attachments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` int(10) unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT 0,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `notification_id` (`notification_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `notification_attachments_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notification_attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: notification_reads
DROP TABLE IF EXISTS `notification_reads`;
CREATE TABLE `notification_reads` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `read_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_read` (`notification_id`,`user_id`),
  KEY `fk_nread_user` (`user_id`),
  CONSTRAINT `fk_nread_notif` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nread_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `notification_reads` (`id`,`notification_id`,`user_id`,`read_at`) VALUES
('1','2','1','2026-02-14 08:02:53'),
('2','1','1','2026-02-14 08:03:00');

-- Table: notification_versions
DROP TABLE IF EXISTS `notification_versions`;
CREATE TABLE `notification_versions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` int(10) unsigned NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `type` varchar(20) DEFAULT NULL,
  `priority` varchar(20) DEFAULT NULL,
  `target_audience` varchar(20) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `tags` varchar(500) DEFAULT NULL,
  `changed_by` int(10) unsigned DEFAULT NULL,
  `changed_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `notification_id` (`notification_id`),
  KEY `changed_by` (`changed_by`),
  CONSTRAINT `notification_versions_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notification_versions_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `notification_versions` (`id`,`notification_id`,`title`,`content`,`type`,`priority`,`target_audience`,`category`,`tags`,`changed_by`,`changed_at`) VALUES
('1','4','Holiday','HOLIDAY','holiday','normal','all','holiday','SUNDAY','1','2026-02-21 14:06:28');

-- Table: notifications
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `type` enum('general','academic','exam','holiday','event','urgent') NOT NULL DEFAULT 'general',
  `priority` enum('normal','important','urgent') NOT NULL DEFAULT 'normal',
  `category` varchar(50) DEFAULT 'general',
  `tags` varchar(500) DEFAULT NULL,
  `target_audience` enum('all','students','teachers','parents','class','section') NOT NULL DEFAULT 'all',
  `target_class` varchar(20) DEFAULT NULL,
  `target_section` varchar(10) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('draft','pending','approved','published','expired','rejected') DEFAULT 'pending',
  `posted_by` int(10) unsigned DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `reject_reason` text DEFAULT NULL,
  `schedule_at` datetime DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `show_popup` tinyint(1) NOT NULL DEFAULT 0,
  `show_banner` tinyint(1) NOT NULL DEFAULT 0,
  `show_marquee` tinyint(1) NOT NULL DEFAULT 0,
  `show_dashboard` tinyint(1) NOT NULL DEFAULT 0,
  `view_count` int(10) unsigned NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `posted_by` (`posted_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `fk_notif_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notif_poster` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `notifications` (`id`,`title`,`content`,`type`,`priority`,`category`,`tags`,`target_audience`,`target_class`,`target_section`,`attachment`,`is_public`,`status`,`posted_by`,`approved_by`,`approved_at`,`reject_reason`,`schedule_at`,`expires_at`,`is_pinned`,`show_popup`,`show_banner`,`show_marquee`,`show_dashboard`,`view_count`,`is_deleted`,`deleted_at`,`deleted_by`,`created_at`,`updated_at`) VALUES
('1','TEST NOTIFICATION','All micro-animations are now in place — KPI cards get a scale+lift+shimmer hover with staggered entrance, table rows slide with a brand-colored left accent, buttons have lift+ripple effects, and quick action buttons get enhanced hover shadows. Upload to your server and test in both themes!','exam','important','general',NULL,'all','','',NULL,'1','expired','1','1','2026-02-13 13:29:13',NULL,'2026-02-13 13:29:00','2026-02-24','0','1','0','0','1','0','0',NULL,NULL,'2026-02-13 13:29:13','2026-02-25 15:01:37'),
('2','TEST Notification','schema.sql — Added notification_versions + notification_attachments tables, category/tags columns, expanded status enum (draft/pending/approved/published/expired/rejected)\r\nadmin/ajax/notification-actions.php — New AJAX endpoint for version history, restore, attachments CRUD, and engagement analytics\r\nadmin/notifications.php — Complete rewrite with: advanced filter bar, 8 status tabs, floating bulk toolbar, right-side preview drawer (Student/Teacher views), version history modal, engagement analytics modal, WhatsApp sharing, column visibility toggle, saved filter views, PDF export via jsPDF, categories & tags, multi-attachment support, draft/publish workflow\r\nteacher/post-notification.php — Updated with draft support, categories, tags, multi-attachment upload','exam','important','academic','TEST Notification','all','','',NULL,'1','expired','1','1','2026-02-13 15:21:53',NULL,'2026-02-13 15:21:00','2026-02-25','0','1','1','1','1','0','0',NULL,NULL,'2026-02-13 15:21:53','2026-02-26 20:18:39'),
('3','TEST','TEST','general','normal','general','TEST','all','','',NULL,'1','approved','1','1','2026-02-16 11:07:26',NULL,'2026-02-12 11:07:00','2026-03-12','0','1','1','1','1','0','0',NULL,NULL,'2026-02-16 11:07:26','2026-02-16 11:07:26'),
('4','Holiday','HOLIDAY','holiday','normal','holiday','SUNDAY','all','','',NULL,'1','expired','1','1','2026-02-21 14:05:05',NULL,'2026-02-22 14:04:00','2026-02-22','0','1','1','1','1','0','0',NULL,NULL,'2026-02-21 14:05:05','2026-02-25 15:01:37'),
('5','HOLIDAY NOTIFICATION 22/FEB/2026','HOLIDAY NOTIFICATION 22/FEB/2026\r\n\r\n\r\nTest information','general','important','general','HOLIDAY','students','','',NULL,'1','expired','1','1','2026-02-21 14:43:18',NULL,'2026-02-21 14:42:00','2026-02-23','0','1','1','1','1','0','0',NULL,NULL,'2026-02-21 14:43:18','2026-02-25 15:01:37');

-- Table: popup_ads
DROP TABLE IF EXISTS `popup_ads`;
CREATE TABLE `popup_ads` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `image_path` varchar(255) DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `redirect_url` varchar(500) DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `show_on_home` tinyint(1) NOT NULL DEFAULT 1,
  `show_once_per_day` tinyint(1) NOT NULL DEFAULT 1,
  `disable_on_mobile` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `popup_ads` (`id`,`image_path`,`is_enabled`,`start_date`,`end_date`,`redirect_url`,`button_text`,`show_on_home`,`show_once_per_day`,`disable_on_mobile`,`created_at`,`updated_at`) VALUES
('1','uploads/ads/popup_1771773271_77cf0910.png','1','2026-02-22','2026-03-14','https://jnvschool.awayindia.com/public/admission-form.php','Apply Now','1','0','0','2026-02-22 20:33:20','2026-02-27 23:24:22');

-- Table: popup_analytics
DROP TABLE IF EXISTS `popup_analytics`;
CREATE TABLE `popup_analytics` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `popup_id` int(10) unsigned NOT NULL,
  `view_date` date NOT NULL,
  `views_count` int(10) unsigned NOT NULL DEFAULT 0,
  `clicks_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_popup_date` (`popup_id`,`view_date`),
  CONSTRAINT `fk_analytics_popup` FOREIGN KEY (`popup_id`) REFERENCES `popup_ads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=344 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `popup_analytics` (`id`,`popup_id`,`view_date`,`views_count`,`clicks_count`,`created_at`) VALUES
('1','1','2026-02-22','35','1','2026-02-22 20:44:46'),
('37','1','2026-02-23','1','0','2026-02-23 06:34:04'),
('38','1','2026-02-24','1','0','2026-02-24 16:44:09'),
('39','1','2026-02-25','26','2','2026-02-25 09:32:56'),
('67','1','2026-02-26','60','1','2026-02-26 07:06:54'),
('128','1','2026-02-27','111','6','2026-02-27 07:56:24'),
('245','1','2026-02-28','48','0','2026-02-28 05:47:06'),
('293','1','2026-03-01','20','1','2026-03-01 06:26:31'),
('314','1','2026-03-02','29','1','2026-03-02 06:37:54');

-- Table: settings
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=746 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES
('1','school_name','Aryan English Medium School','2026-02-13 12:31:01'),
('2','school_short_name','Aryan','2026-02-13 12:31:03'),
('3','school_tagline','Better Education for a Better World','2026-02-13 12:31:05'),
('4','school_email','contact@aryanschools.edu.in','2026-02-13 12:31:05'),
('5','school_phone','+91-9951528113','2026-02-16 10:38:40'),
('6','school_address','Anantapuramu, Andhra Pradesh, India','2026-02-13 12:31:09'),
('7','school_logo','school_logo.png','2026-02-11 22:30:07'),
('8','primary_color','#e11d48','2026-03-02 11:06:01'),
('9','secondary_color','#3b82f6','2026-02-11 22:27:16'),
('10','academic_year','2026-2027','2026-02-16 10:38:40'),
('11','admission_open','1','2026-02-28 17:48:36'),
('12','facebook_url','','2026-02-11 22:27:16'),
('13','twitter_url','','2026-02-11 22:27:16'),
('14','instagram_url','','2026-02-11 22:27:16'),
('15','youtube_url','','2026-02-11 22:27:16'),
('16','popup_ad_image','popup_ad.png','2026-02-15 14:18:55'),
('17','popup_ad_active','1','2026-02-15 14:18:55'),
('18','social_facebook','','2026-02-11 22:27:16'),
('19','social_twitter','','2026-02-11 22:27:16'),
('20','social_instagram','','2026-02-11 22:27:16'),
('21','social_youtube','','2026-02-11 22:27:16'),
('22','social_linkedin','','2026-02-11 22:27:16'),
('23','about_history','Join with Aryan English Medium School\r\n\"Welcome to Aryan English Medium School. Best English Medium School in Anantapur where every student\'s journey begins with a promise – a promise of discovery, growth, and excellence.\r\n\r\nWe invite you to embark on an educational adventure where passion meets purpose, and together, we shape futures filled with knowledge, compassion, and endless possibilities.\r\n\r\nStep into a community that fosters a love for learning and a commitment to success. Welcome to a place where education is not just a destination; it\'s a lifelong journey.\"','2026-02-27 07:58:55'),
('24','about_vision','\"We are Aryan English Medium School – a dedicated community of educators, mentors, and learners. With a shared vision of excellence, we believe in fostering an environment that values curiosity, embraces diversity, and shapes the future leaders of tomorrow.\"','2026-02-27 07:58:55'),
('25','about_mission','\"In the pursuit of academic brilliance and holistic development, we take pride by providing a transformative learning experience that goes beyond textbooks, fostering critical thinking, creativity, and the skills necessary to thrive in a dynamic world.\"','2026-02-27 07:58:55'),
('26','whatsapp_api_number','+91-9951528113','2026-02-27 18:05:31'),
('27','sms_gateway_key','','2026-02-11 22:27:16'),
('28','school_favicon','favicon.png','2026-02-13 12:02:44'),
('29','core_value_1_title','Excellence','2026-02-11 22:27:16'),
('30','core_value_1_desc','We strive for the highest standards in academics, character, and personal growth.','2026-02-11 22:27:16'),
('31','core_value_2_title','Integrity','2026-02-11 22:27:16'),
('32','core_value_2_desc','We foster honesty, transparency, and ethical behavior in all our actions.','2026-02-11 22:27:16'),
('33','core_value_3_title','Innovation','2026-02-11 22:27:16'),
('34','core_value_3_desc','We embrace creativity and modern teaching methods to inspire learning.','2026-02-11 22:27:16'),
('35','core_value_4_title','Community','2026-02-11 22:27:16'),
('36','core_value_4_desc','We build a supportive, inclusive environment where everyone belongs.','2026-02-11 22:27:16'),
('37','home_marquee_text','','2026-02-11 22:27:16'),
('38','home_hero_show','1','2026-02-11 22:27:16'),
('39','home_stats_show','0','2026-02-13 20:52:12'),
('40','home_stats_students_label','Students','2026-02-11 22:27:16'),
('41','home_stats_teachers_label','Teachers','2026-02-11 22:27:16'),
('42','home_stats_classes_label','Classes','2026-02-11 22:27:16'),
('43','home_stats_classes_value','12','2026-02-11 22:27:16'),
('44','home_stats_dedication_label','Dedication','2026-02-11 22:27:16'),
('45','home_stats_dedication_value','100%','2026-02-11 22:27:16'),
('46','home_quicklinks_show','1','2026-02-11 22:27:16'),
('47','home_cta_admissions_title','Admissions','2026-02-11 22:27:16'),
('48','home_cta_admissions_desc','Apply online for admission to JNV School.','2026-02-11 22:27:16'),
('49','home_cta_notifications_title','Notifications','2026-02-11 22:27:16'),
('50','home_cta_notifications_desc','Stay updated with latest announcements.','2026-02-11 22:27:16'),
('51','home_cta_gallery_title','Gallery','2026-02-11 22:27:16'),
('52','home_cta_gallery_desc','Explore photos & videos from school life.','2026-02-11 22:27:16'),
('53','home_cta_events_title','Events','2026-02-11 22:27:16'),
('54','home_cta_events_desc','Check upcoming school events & dates.','2026-02-11 22:27:16'),
('55','home_core_team_show','1','2026-02-11 22:27:16'),
('56','home_core_team_title','Our Core Team','2026-02-11 22:27:16'),
('57','home_core_team_subtitle','Meet the dedicated leaders guiding our school\'s vision and mission.','2026-02-11 22:27:16'),
('58','home_contact_show','1','2026-02-22 22:01:51'),
('59','home_footer_cta_show','1','2026-02-11 22:27:16'),
('60','home_footer_cta_title','','2026-02-11 22:27:16'),
('61','home_footer_cta_desc','','2026-02-11 22:27:16'),
('62','home_footer_cta_btn_text','Get In Touch','2026-02-11 22:27:16'),
('63','about_hero_title','Learn with Passion to Live with Purpose','2026-02-27 07:57:11'),
('64','about_hero_subtitle','\"At Aryan English Medium School, we foster a culture where learning with passion becomes the cornerstone of shaping purposeful lives. Empowering young minds to discover their potential, we believe education goes beyond the classroom, igniting a lifelong journey of purpose and impact.\"','2026-02-11 22:53:26'),
('65','about_hero_badge','About Our School','2026-02-11 22:27:16'),
('66','about_history_show','1','2026-02-11 22:27:16'),
('67','about_vision_mission_show','1','2026-02-11 22:27:16'),
('68','about_core_values_show','1','2026-02-11 22:27:16'),
('69','about_quote_show','1','2026-02-11 22:27:16'),
('70','about_footer_cta_show','1','2026-02-11 22:27:16'),
('71','teachers_hero_title','Our Teachers','2026-02-11 22:27:16'),
('72','teachers_hero_subtitle','Meet our dedicated team of qualified educators who inspire, guide, and shape the future of every student.','2026-02-11 22:27:16'),
('73','teachers_hero_badge','Our Educators','2026-02-11 22:27:16'),
('74','teachers_core_team_show','1','2026-02-11 22:27:16'),
('75','teachers_grid_title','Meet Our Faculty','2026-02-11 22:27:16'),
('76','teachers_grid_subtitle','Hover on a card to learn more about each teacher','2026-02-11 22:27:16'),
('77','teachers_all_show','1','2026-02-11 22:27:16'),
('78','teachers_footer_cta_show','1','2026-02-11 22:27:16'),
('79','gallery_hero_title','Photo Gallery','2026-02-11 22:27:16'),
('80','gallery_hero_subtitle','','2026-02-11 22:27:16'),
('81','gallery_hero_icon','bi-images','2026-02-11 22:27:16'),
('82','gallery_footer_cta_show','1','2026-02-11 22:27:16'),
('83','events_hero_title','Events','2026-02-11 22:27:16'),
('84','events_hero_subtitle','','2026-02-11 22:27:16'),
('85','events_hero_icon','bi-calendar-event-fill','2026-02-11 22:27:16'),
('86','events_footer_cta_show','1','2026-02-11 22:27:16'),
('87','notifications_hero_title','Notifications','2026-02-11 22:27:16'),
('88','notifications_hero_subtitle','','2026-02-11 22:27:16'),
('89','notifications_hero_icon','bi-bell-fill','2026-02-11 22:27:16'),
('90','notifications_footer_cta_show','1','2026-02-11 22:27:16'),
('91','admission_hero_title','Apply for Admission','2026-02-11 22:27:16'),
('92','admission_hero_subtitle','','2026-02-11 22:27:16'),
('93','admission_hero_icon','bi-file-earmark-plus-fill','2026-02-11 22:27:16'),
('94','admission_footer_cta_show','1','2026-02-27 03:21:36'),
('95','global_navbar_show_top_bar','1','2026-02-21 18:13:48'),
('96','global_navbar_show_login','1','2026-02-11 22:27:16'),
('97','global_navbar_show_notif_bell','1','2026-02-11 22:27:16'),
('98','global_footer_cta_title','','2026-02-11 22:27:16'),
('99','global_footer_cta_desc','','2026-02-11 22:27:16'),
('100','global_footer_cta_btn_text','Get In Touch','2026-02-11 22:27:16');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES
('101','footer_description','A professional and modern school with years of experience in nurturing children with senior teachers and a clean environment.','2026-02-11 22:27:16'),
('102','footer_quick_links','[{\"label\":\"About Us\",\"url\":\"\\/public\\/about.php\"},{\"label\":\"Our Teachers\",\"url\":\"\\/public\\/teachers.php\"},{\"label\":\"Admissions\",\"url\":\"\\/public\\/admission-form.php\"},{\"label\":\"Gallery\",\"url\":\"\\/public\\/gallery.php\"},{\"label\":\"Events\",\"url\":\"\\/public\\/events.php\"},{\"label\":\"Admin Login\",\"url\":\"\\/login.php\"}]','2026-02-11 22:29:51'),
('103','footer_programs','[{\"label\":\"Pre-Primary (LKG & UKG)\"},{\"label\":\"Primary School (1-5)\"},{\"label\":\"Upper Primary (6-8)\"},{\"label\":\"Co-Curricular Activities\"},{\"label\":\"Sports Programs\"}]','2026-02-11 22:27:16'),
('104','footer_contact_address','Anantapuramu, Andhra Pradesh, India','2026-02-11 22:29:51'),
('105','footer_contact_phone','+91 9063806806','2026-02-27 17:42:48'),
('106','footer_contact_email','contact@aryanschools.edu.in','2026-02-11 22:29:51'),
('107','footer_contact_hours','Mon - Sat: 8:00 AM - 5:00 PM','2026-02-11 22:27:16'),
('108','footer_social_facebook','https://www.facebook.com/','2026-02-11 22:29:51'),
('109','footer_social_twitter','https://x.com/','2026-02-11 22:29:51'),
('110','footer_social_instagram','https://www.instagram.com/','2026-02-11 22:29:51'),
('111','footer_social_youtube','https://www.youtube.com/','2026-02-11 22:29:51'),
('112','footer_social_linkedin','','2026-02-11 22:27:16'),
('136','about_leadership_show','1','2026-02-12 11:23:58'),
('137','about_leadership_title','Meet Our Leadership','2026-02-12 11:23:58'),
('138','about_leadership_subtitle','With dedication and passion, our team creates an environment where every student thrives.','2026-02-12 11:23:58'),
('139','gallery_layout_style','premium','2026-02-12 18:35:50'),
('140','gallery_bg_style','dark','2026-02-12 18:35:50'),
('141','gallery_particles_show','1','2026-02-12 18:35:50'),
('160','logo_updated_at','1772169098','2026-02-27 10:41:38'),
('162','favicon_updated_at','1772169105','2026-02-27 10:41:45'),
('189','smtp_host','mail.jnvweb.in','2026-02-26 15:40:01'),
('190','smtp_port','465','2026-02-13 20:40:29'),
('191','smtp_user','admin@jschool.jnvweb.in','2026-02-26 15:30:53'),
('192','smtp_pass','sa1T4HXr@7602626264','2026-02-26 15:28:18'),
('193','smtp_from_name','JNV School','2026-02-13 20:40:29'),
('194','smtp_encryption','ssl','2026-02-13 20:40:29'),
('241','home_certificates_show','1','2026-02-21 10:37:31'),
('242','home_certificates_max','6','2026-02-21 10:37:31'),
('243','certificates_page_enabled','1','2026-02-21 10:37:31'),
('260','brand_primary','#47477b','2026-02-27 17:28:46'),
('261','brand_secondary','#202060','2026-02-27 10:36:45'),
('262','brand_accent','#200040','2026-02-27 10:36:45'),
('263','brand_colors_auto','0','2026-02-27 17:28:46'),
('316','maintenance_mode','0','2026-02-27 17:26:59'),
('327','feature_admissions','1','2026-02-22 11:57:24'),
('328','feature_gallery','1','2026-02-22 11:57:24'),
('329','feature_events','1','2026-02-22 11:57:24'),
('330','feature_slider','1','2026-02-22 11:57:24'),
('331','feature_notifications','1','2026-02-22 11:57:24'),
('332','feature_reports','1','2026-02-22 11:57:24'),
('333','feature_audit_logs','0','2026-02-22 11:57:24'),
('334','school_map_enabled','1','2026-02-22 21:03:35'),
('335','school_map_embed_url','https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3859.957725395399!2d77.59933397510729!3d14.658340585834946!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3bb14bb1832d7bcd%3A0x1f8ef6ac88e1a1b1!2sAryan%20English%20Medium%20School!5e0!3m2!1sen!2sin!4v1771774295963!5m2!1sen!2sin','2026-02-22 21:03:35'),
('336','school_latitude','','2026-02-22 21:03:35'),
('337','school_longitude','','2026-02-22 21:03:35'),
('338','school_landmark','','2026-02-22 21:03:35'),
('434','whatsapp_admission_template','Hello {name}, this is regarding your admission application ({app_id}) for Class {class}. Please contact us for further details.','2026-02-26 23:29:40'),
('435','recruitment_enabled','0','2026-02-28 12:51:15'),
('436','whatsapp_recruitment_template','Hello {name}, this is regarding your application ({app_id}) for the position of {position}. We will review your application and get back to you shortly.','2026-02-27 01:51:57'),
('437','email_recruitment_template','<div style=\"font-family:Inter,sans-serif;max-width:600px;margin:0 auto;padding:2rem;\"><h2>Application Received</h2><p>Dear {name},</p><p>Thank you for applying for the position of <strong>{position}</strong>. Your application ID is <strong>{app_id}</strong>.</p><p>We will review your application and contact you shortly.</p><hr><p style=\"color:#64748b;font-size:0.8rem;\">{school_name}</p></div>','2026-02-27 01:51:57'),
('537','admin_logo','/uploads/branding/admin_logo_1772434247.jpg','2026-03-02 12:20:47'),
('538','admin_logo_updated_at','1772434247','2026-03-02 12:20:47'),
('690','hr_logo','/uploads/hr/hr_logo_1772285575.png','2026-02-28 19:02:55'),
('691','hr_digital_signature','/uploads/hr/signature_1772285599.png','2026-02-28 19:03:19'),
('702','feature_hr','1','2026-03-02 12:16:52'),
('703','feature_recruitment','1','2026-03-02 12:16:52'),
('704','feature_fee_structure','1','2026-03-02 12:16:52'),
('705','feature_certificates','1','2026-02-28 19:53:31'),
('706','feature_feature_cards','1','2026-02-28 19:53:31'),
('707','feature_core_team','1','2026-02-28 19:53:31');

-- Table: site_quotes
DROP TABLE IF EXISTS `site_quotes`;
CREATE TABLE `site_quotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `quote_text` text NOT NULL,
  `author_name` varchar(200) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_quote_user` (`updated_by`),
  CONSTRAINT `fk_quote_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `site_quotes` (`id`,`quote_text`,`author_name`,`is_active`,`updated_by`,`updated_at`,`created_at`) VALUES
('1','Education is the most powerful weapon which you can use to change the world...','Nelson Mandela','1','1','2026-02-27 07:57:11','2026-02-11 22:27:16');

-- Table: students
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admission_no` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `class` varchar(20) DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL,
  `roll_no` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `category` varchar(30) DEFAULT NULL,
  `aadhar_no` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','alumni','tc_issued') NOT NULL DEFAULT 'active',
  `admission_date` date DEFAULT NULL,
  `leaving_date` date DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `admission_no` (`admission_no`),
  KEY `idx_class` (`class`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: teacher_applications
DROP TABLE IF EXISTS `teacher_applications`;
CREATE TABLE `teacher_applications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` varchar(20) NOT NULL,
  `job_opening_id` int(10) unsigned DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `qualification` varchar(200) DEFAULT NULL,
  `experience_years` int(10) unsigned DEFAULT 0,
  `current_school` varchar(200) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `resume_path` varchar(500) DEFAULT NULL,
  `cover_letter` text DEFAULT NULL,
  `status` enum('new','reviewed','shortlisted','interview_scheduled','approved','rejected') NOT NULL DEFAULT 'new',
  `interview_date` datetime DEFAULT NULL,
  `interview_notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_application_id` (`application_id`),
  KEY `idx_status` (`status`),
  KEY `idx_deleted` (`is_deleted`),
  KEY `idx_job` (`job_opening_id`),
  KEY `fk_app_reviewer` (`reviewed_by`),
  KEY `fk_app_deleter` (`deleted_by`),
  CONSTRAINT `fk_app_deleter` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_app_job` FOREIGN KEY (`job_opening_id`) REFERENCES `job_openings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_app_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `teacher_applications` (`id`,`application_id`,`job_opening_id`,`full_name`,`email`,`phone`,`dob`,`gender`,`qualification`,`experience_years`,`current_school`,`address`,`resume_path`,`cover_letter`,`status`,`interview_date`,`interview_notes`,`admin_notes`,`reviewed_by`,`reviewed_at`,`is_deleted`,`deleted_at`,`deleted_by`,`created_at`,`updated_at`) VALUES
('1','APP-2026-00001',NULL,'Y NAGARJUNA','nagarjuna1014@gmail.com','8106811171','2026-02-18','male','Btech','5','Abababa','Nuvvuvunda high digbndhnam','uploads/resumes/resume_1772143400_77898dea.pdf','Tesy','interview_scheduled','2026-03-05 18:35:00',NULL,NULL,'1','2026-02-27 03:34:41','0',NULL,NULL,'2026-02-27 03:33:20','2026-02-27 03:34:41'),
('2','APP-2026-00002',NULL,'Tesy','nagarjuna1014@gmail.com','918106811171','2026-02-09','male','Hhh','5','Gvvv','Hhbb',NULL,'Test','new',NULL,NULL,NULL,NULL,NULL,'0',NULL,NULL,'2026-02-27 03:40:55',NULL),
('3','APP-2026-00003',NULL,'Hnhn','nagarjuna1014@gmail.com','+917602626264','2026-02-08','male','','0','','',NULL,'','new',NULL,NULL,NULL,NULL,NULL,'0',NULL,NULL,'2026-02-27 03:41:41',NULL);

-- Table: teachers
DROP TABLE IF EXISTS `teachers`;
CREATE TABLE `teachers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `employee_id` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `designation` varchar(100) DEFAULT 'Teacher',
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `qualification` varchar(100) DEFAULT NULL,
  `experience_years` int(11) DEFAULT 0,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `status` enum('active','inactive','resigned','retired') NOT NULL DEFAULT 'active',
  `is_core_team` tinyint(1) NOT NULL DEFAULT 0,
  `bio` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_display_order` (`display_order`),
  CONSTRAINT `fk_teacher_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `teachers` (`id`,`user_id`,`employee_id`,`name`,`designation`,`email`,`phone`,`subject`,`qualification`,`experience_years`,`dob`,`gender`,`address`,`photo`,`joining_date`,`status`,`is_core_team`,`bio`,`created_at`,`updated_at`,`display_order`,`is_visible`,`is_featured`) VALUES
('1',NULL,'TCH202602117119','TEST','Teacher','','','TEST','BTech','0',NULL,NULL,'','/uploads/photos/teacher_1770830322_e9c2f64d.png',NULL,'inactive','0','Our experienced faculty members bring passion and expertise to create an engaging learning experience for every student.','2026-02-11 22:48:42','2026-02-22 23:06:24','1','1','1'),
('2',NULL,'PRIN20260211247','M. Lahari','Principal','principal@aryanschools.edu.in','','','M.Sc, B.Ed','0',NULL,'','','/uploads/photos/teacher_1770830409_1137036e.webp',NULL,'inactive','1','\"Welcome to Aryan English Medium School. Our dedicated educators strive to inspire a love for learning, creativity, and character development in every student. Together, we build a brighter future of your child.\"','2026-02-11 22:50:09','2026-02-22 23:33:23','0','1','0'),
('3',NULL,'AEMS001','M. Nagaraju','Correspondent','correspondent@aryanschools.edu.in','12345678','TEST','BTech','10',NULL,'male','','teacher_1771781208_5816.webp',NULL,'inactive','1','Show Core Team Section','2026-02-22 22:35:12','2026-02-22 23:33:41','2','1','1'),
('4',NULL,'AEMS002','TEST TEACHER','Teacher','mail@aryanschools.edu.in','12345678','','BTech','4',NULL,'male','','teacher_1771781371_2164.webp',NULL,'inactive','1','','2026-02-22 22:59:15','2026-02-22 23:33:31','0','1','0'),
('8',NULL,'PRIN20260225821','MANDALA LAHARI','Teacher','mandalalahari85@gmail.com','8897478444','Psychology','M.Sc, B.Ed','8','1986-06-14','female','','/uploads/photos/teacher_1772031570_bf708294.webp','2023-04-01','active','1','\"At Aryan English Medium School, we foster a culture where learning with passion becomes the cornerstone of shaping purposeful lives. Empowering young minds to discover their potential, we believe education goes beyond the classroom, igniting a lifelong journey of purpose and impact.\"','2026-02-25 20:29:30','2026-03-02 14:12:49','5','1','0'),
('9',NULL,'001','MAJJARI NAGARAJU','Teacher','nagaraju@aryanschools.edu.in','9951528113','MATHEMATICS','M.Tech,B.ed','13','1990-12-18','male','','/uploads/photos/teacher_1772438377_4546.jpeg','2023-06-05','active','0','','2026-03-02 11:33:23','2026-03-02 14:12:49','1','1','0'),
('10',NULL,'004','KONDALA SUKANYA','Teacher','thoreddysukanya@gmail.com','6303599124','Hindi','HPT,MA','10','1992-06-15','female','','','2024-07-01','active','0','','2026-03-02 12:51:06','2026-03-02 14:12:49','9','1','0'),
('11',NULL,'002','G SANDEEP','Teacher','gsandeep9891@gmail.com','08125252086','English','M.Tech,B.ed','13','1989-10-28','male','','','2023-05-01','active','0','','2026-03-02 12:54:27','2026-03-02 14:12:49','3','1','0'),
('12',NULL,'006','ABDUL KHADAR','Teacher','khadar.abdul77@gmail.com','8309082782','Physical Science','M.Tech,B.ed','14','1992-10-30','male','','','2026-02-05','active','0','','2026-03-02 13:01:15','2026-03-02 14:12:49','11','1','0'),
('13',NULL,'8','PIT PIT ZEEBA PARVEEN','Teacher','zeeba141995@gmail.com','9392653699','English','B.sc.,B.ed','7','1995-03-14','female','','',NULL,'active','0','','2026-03-02 13:04:45','2026-03-02 14:12:49','13','1','0'),
('14',NULL,'9','NALLARI KAVITHA','Teacher','nkavitha2022@gmail.com','9959708705','MATHEMATICS','M.Sc, B.Ed','15','1986-05-10','female','','','2025-06-12','active','0','','2026-03-02 13:07:56','2026-03-02 14:12:49','15','1','0'),
('15',NULL,'12','PALLA ASHWINI','Teacher','pallaaswini1999@gmail.com','9704301293','Telugu','B.sc.,B.ed','1','1999-08-11','female','','','2025-06-01','active','0','','2026-03-02 13:10:49','2026-03-02 14:12:49','17','1','0'),
('16',NULL,'13','B. BHARATH KUMAR','Teacher','bharathkumar554973@gmail.com','9533636396','MATHEMATICS','B.tech.,B.ed','3','1994-05-09','male','','/uploads/photos/teacher_1772438498_2841.jpeg','2023-07-01','active','0','','2026-03-02 13:17:10','2026-03-02 14:12:49','19','1','0'),
('17',NULL,'14','G.Upendra Kumar','Teacher','gollaupendrakumar25@gmail.com','8309306859','MATHEMATICS','M.Sc','2','1997-08-25','male','','/uploads/photos/teacher_1772438186_5197.jpeg','2024-09-01','active','0','','2026-03-02 13:24:32','2026-03-02 14:12:49','21','1','0'),
('18',NULL,'15','K. NARESH KUMAR','Teacher','mrdraculnaresh@gmail.com','9618856901','Physical Education Teacher','B.P.ed','1','2002-06-18','male','','','2025-10-31','active','0','','2026-03-02 13:37:47','2026-03-02 14:12:49','23','1','0'),
('19',NULL,'16','ANGADI SAHITHYA','Teacher','sahityaroyal@gmail.com','9701500745','SCIENCE','B.sc','3','1994-08-15','female','','','2024-02-16','active','0','','2026-03-02 13:43:50','2026-03-02 14:12:49','29','1','0'),
('20',NULL,'18','K.G.HARI KISHORE','Teacher','harikishorekaranam@gmail.com','9703576621','English','M.A .,B.ed','35','1967-07-12','male','','','2024-06-12','active','0','','2026-03-02 13:51:00','2026-03-02 14:12:49','25','1','0'),
('21',NULL,'17','T ANITHA DEVI','Teacher','tanithadevi9866@gmail.com','8688041718','Hindi','B.sc','3','2002-08-20','female','','','2023-06-01','active','0','','2026-03-02 13:53:43','2026-03-02 14:12:49','31','1','0'),
('23',NULL,'19','P SUGUNA','Teacher','palayamammu@gmail.com','9441847802','Physical Education Teacher','PED','0','1996-06-02','','','','2025-07-27','active','0','','2026-03-02 13:56:33','2026-03-02 14:12:49','27','1','0'),
('24',NULL,'21','B.Madhuri','Teacher','','9390527522','English','B.sc','1','2003-07-31','female','','','2025-11-02','active','0','','2026-03-02 13:59:48','2026-03-02 14:12:49','35','1','0'),
('25',NULL,'22','VADLA MANJULA','Teacher','vadlamanjula123@gmail.com','8143718659','Telugu','M.A .,B.ed','4','1987-04-21','female','','','2024-06-05','active','0','','2026-03-02 14:02:00','2026-03-02 14:12:49','37','1','0'),
('26',NULL,'23','B.Aruna Kamala','Teacher','arunanaganamania@gmail.com','8096617867','SOCIAL','B.A. B.ed','8','1989-06-01','female','','','2026-02-04','active','0','','2026-03-02 14:05:06','2026-03-02 14:12:49','39','1','0'),
('27',NULL,'24','M.Prashanthi','Teacher','','7981279373','MATHEMATICS','M.Sc, B.Ed','3','1993-03-13','female','','','2026-02-11','active','0','','2026-03-02 14:07:15','2026-03-02 14:12:49','33','1','0'),
('28',NULL,'25','JANAKI KAMALA KUMARI','Teacher','jankirammorthy@gmail.com','9052613055','','B.com','18','1974-03-27','female','','','2025-12-22','active','0','','2026-03-02 14:10:14','2026-03-02 14:12:49','41','1','0'),
('29',NULL,'4','N Bhargavi','Teacher','','','Physical Science','','0',NULL,'female','','',NULL,'active','0','','2026-03-02 14:12:12','2026-03-02 14:12:49','7','1','0');

-- Table: users
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','teacher','office') NOT NULL DEFAULT 'teacher',
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`,`name`,`email`,`password`,`role`,`phone`,`avatar`,`is_active`,`reset_token`,`reset_expires`,`last_login`,`created_at`,`updated_at`) VALUES
('1','Super Admin','admin@jnvweb.in','$2y$10$muVBipep.vPeVq4hE07tTecNEyFU4WBMVeUJQp33q38oHa65fSdH6','super_admin',NULL,NULL,'1',NULL,NULL,'2026-03-02 18:46:09','2026-02-11 22:27:16','2026-03-02 18:46:09'),
('2','Teacher','teacher@school.com','$2y$10$8c8OLTz1I9vTDzTjZARxT.EPX2JaKuI6UoX9yr.ITJ6suBHgMCtRu','teacher',NULL,NULL,'1',NULL,NULL,'2026-02-13 21:46:12','2026-02-12 17:36:02','2026-02-13 21:46:12'),
('5','Admin','admin@aryanschools.edu.in','$2y$10$wmz/s8QFSaei8bsTq7oceu8QpQqKFU.280njM/1v4y8oenUaDdb1O','admin',NULL,NULL,'1',NULL,NULL,'2026-03-02 12:42:41','2026-02-22 11:55:33','2026-03-02 12:42:41'),
('6','G. Sandeep','director@aryanschools.edu.in','$2y$10$cF7SvtIW6dMT0T9ejain6u8ky9bckeCdXjxhik3lv966q..wMIJVC','admin',NULL,NULL,'1',NULL,NULL,NULL,'2026-02-22 22:59:15','2026-02-27 12:13:04'),
('7','MAJJARI NAGARAJU','nagaraju@aryanschools.edu.in','$2y$10$4mmowGRpwvN42D5/X/3FueIMf7Q/ugSG/VhmxAObLokKspzeorgJG','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 11:33:23','2026-03-02 11:33:23'),
('8','KONDALA SUKANYA','thoreddysukanya@gmail.com','$2y$10$g6v4Ywji6msXShkWtIA11O6xZPff2ESDwab70FQdyRI2AVhJlf8eK','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 12:51:06','2026-03-02 12:51:06'),
('9','G SANDEEP','gsandeep9891@gmail.com','$2y$10$4pm6tDlmhGIDbaWD0LFsdOrtlIjI41bMNv/BOQwebnryN2SCzEEK.','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 12:54:27','2026-03-02 12:54:27'),
('10','ABDUL KHADAR','khadar.abdul77@gmail.com','$2y$10$h39RjagkJsEEI8TQRW2UNOLGjYmetPE0PQmJtVc3Xw8tF6H1THOly','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 13:01:15','2026-03-02 13:01:15'),
('11','PIT PIT ZEEBA PARVEEN','zeeba141995@gmail.com','$2y$10$Iz1lIH2R0MHIx0OFp5Svn.Qrd3wpuvj5NpCWodhbN0ujKolJZFCH2','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 13:04:45','2026-03-02 13:04:45'),
('12','NALLARI KAVITHA','nkavitha2022@gmail.com','$2y$10$pfRsjx4uxfzhgN4jDv8KJOIkPOlv/yB4l68JcOPD/0RPPjP4SJmvK','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 13:07:56','2026-03-02 13:07:56'),
('13','PALLA ASHWINI','pallaaswini1999@gmail.com','$2y$10$vADlnryu2I9csHLSfwWSRefoV5CimQrYwUGsUj7PSSrNDOE5PzSxa','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 13:10:49','2026-03-02 13:10:49'),
('14','B. BHARATH KUMAR','bharathkumar554973@gmail.com','$2y$10$/h9A0mlEAIhvd8OAxXZKwOw1TDjuvAUXBJBNEtt54ie8l1FrKWxJi','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 13:17:10','2026-03-02 13:17:10'),
('15','G.Upendra Kumar','gollaupendrakumar25@gmail.com','$2y$10$bgO3LTErHFBJAOu3IlRxquJTW2B9LbEM1sad9gxPLs3nGMlaJqWVK','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 13:24:32','2026-03-02 13:24:32'),
('16','K. NARESH KUMAR','mrdraculnaresh@gmail.com','$2y$10$7tCYXO3OVBOnX/FD9vB5EOzwb7baGw9Sv1ivWu8lxugtw.LZFRiv6','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 13:37:48','2026-03-02 13:37:48'),
('17','ANGADI SAHITHYA','sahityaroyal@gmail.com','$2y$10$2EUcPwBJOPzaODdU8jBYK.MuIRQW4akD.wNy408xdI3ijEv/.AYPS','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 13:43:50','2026-03-02 13:43:50'),
('18','K.G.HARI KISHORE','harikishorekaranam@gmail.com','$2y$10$Nh3595ligUXlOk.jT8nhEuERFkwG4KG6qQEew7fsxJt8qZKjpOuSq','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 13:51:00','2026-03-02 13:51:00'),
('19','T ANITHA DEVI','tanithadevi9866@gmail.com','$2y$10$.vjQ2yncbNP8hgDSHoZttO9ih00TqKFu7LPBwB7/XmGqJRjsBRg8m','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 13:53:43','2026-03-02 13:53:43'),
('20','P SUGUNA','palayamammu@gmail.com','$2y$10$Ll1LktY3CrF.nDzEvatgieyrDDy9eLwjn2yfFeFdAW8wpDGkQfTl6','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 13:56:33','2026-03-02 13:56:33'),
('21','VADLA MANJULA','vadlamanjula123@gmail.com','$2y$10$VATR/pZ.npBb8pSVtc5I9e84Zu18OhZVOXFpJxLuFRvpXgGqeCfTa','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 14:02:00','2026-03-02 14:02:00'),
('22','B.Aruna Kamala','arunanaganamania@gmail.com','$2y$10$Y3FYm3EeRc2lWjl2xK0euefTTl.aCLkisViBVz5sZUB8scrNfZdw6','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 14:05:06','2026-03-02 14:05:06'),
('23','JANAKI KAMALA KUMARI','jankirammorthy@gmail.com','$2y$10$I/B9jdulBI2PNvNI5NkWH.JSkcDB4m3qoFhMVfGyEoWHnO4jrF8CC','teacher',NULL,NULL,'1',NULL,NULL,NULL,'2026-03-02 14:10:14','2026-03-02 14:10:14');

SET FOREIGN_KEY_CHECKS=1;
