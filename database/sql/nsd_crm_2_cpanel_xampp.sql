-- =============================================================================
-- NSD CRM 2.0 — Complete Schema & Approved Demo Seed Package (cPanel & XAMPP)
-- =============================================================================
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- Compatible with: MySQL 8.0+ and MariaDB 10.4+ (XAMPP / cPanel phpMyAdmin)
-- Total Tables: 61
--
-- HOW TO IMPORT VIA PHPMYADMIN (XAMPP / CPANEL):
-- 1. Open phpMyAdmin and create/select your target database (e.g., `nsd_crm_2`)
--    with Collation set to `utf8mb4_unicode_ci`.
-- 2. Click the "Import" tab in the top navigation bar.
-- 3. Click "Choose File" and select `database/sql/nsd_crm_2_cpanel_xampp.sql`.
-- 4. Keep Format as "SQL" and click "Import" / "Go" at the bottom.
-- 5. Update `.env` with your `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.
--
-- SECURITY NOTICE:
-- Contains ONLY synthetic demo seed data (roles, @nsd.local demo accounts,
-- synthetic worker codes). Zero real PII, Aadhaar numbers, sessions, or secrets.
-- =============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- MySQL dump 10.13  Distrib 26.7.0, for macos27.0 (arm64)
--
-- Host: localhost    Database: nsd_crm_2_sql_rehearsal
-- ------------------------------------------------------
-- Server version	26.7.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned DEFAULT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_project_id_foreign` (`project_id`),
  KEY `activity_logs_site_id_foreign` (`site_id`),
  KEY `activity_logs_user_id_foreign` (`user_id`),
  KEY `act_log_comp_site_time_idx` (`company_id`,`site_id`,`created_at`),
  KEY `act_log_subject_idx` (`company_id`,`subject_type`,`subject_id`),
  CONSTRAINT `activity_logs_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activity_logs_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activity_logs_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_corrections`
--

DROP TABLE IF EXISTS `attendance_corrections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_corrections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `daily_attendance_id` bigint unsigned NOT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `previous_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proposed_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `previous_clock_in` time DEFAULT NULL,
  `proposed_clock_in` time DEFAULT NULL,
  `previous_clock_out` time DEFAULT NULL,
  `proposed_clock_out` time DEFAULT NULL,
  `previous_site_id` bigint unsigned DEFAULT NULL,
  `proposed_site_id` bigint unsigned DEFAULT NULL,
  `previous_worked_hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  `proposed_worked_hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  `previous_overtime_hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  `proposed_overtime_hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `review_notes` text COLLATE utf8mb4_unicode_ci,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendance_corrections_company_id_foreign` (`company_id`),
  KEY `attendance_corrections_daily_attendance_id_foreign` (`daily_attendance_id`),
  KEY `attendance_corrections_requested_by_foreign` (`requested_by`),
  KEY `attendance_corrections_reviewed_by_foreign` (`reviewed_by`),
  KEY `attendance_corrections_previous_site_id_foreign` (`previous_site_id`),
  KEY `attendance_corrections_proposed_site_id_foreign` (`proposed_site_id`),
  CONSTRAINT `attendance_corrections_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_corrections_daily_attendance_id_foreign` FOREIGN KEY (`daily_attendance_id`) REFERENCES `daily_attendances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_corrections_previous_site_id_foreign` FOREIGN KEY (`previous_site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_corrections_proposed_site_id_foreign` FOREIGN KEY (`proposed_site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_corrections_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_corrections_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_corrections`
--

LOCK TABLES `attendance_corrections` WRITE;
/*!40000 ALTER TABLE `attendance_corrections` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_corrections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_flags`
--

DROP TABLE IF EXISTS `attendance_flags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_flags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `worker_id` bigint unsigned NOT NULL,
  `daily_attendance_id` bigint unsigned DEFAULT NULL,
  `attendance_punch_id` bigint unsigned DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `flag_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warning',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `resolved_by` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendance_flags_site_id_foreign` (`site_id`),
  KEY `attendance_flags_worker_id_foreign` (`worker_id`),
  KEY `attendance_flags_daily_attendance_id_foreign` (`daily_attendance_id`),
  KEY `attendance_flags_attendance_punch_id_foreign` (`attendance_punch_id`),
  KEY `attendance_flags_resolved_by_foreign` (`resolved_by`),
  KEY `att_flag_comp_site_status_idx` (`company_id`,`site_id`,`status`),
  KEY `att_flag_comp_worker_date_idx` (`company_id`,`worker_id`,`attendance_date`),
  CONSTRAINT `attendance_flags_attendance_punch_id_foreign` FOREIGN KEY (`attendance_punch_id`) REFERENCES `attendance_punches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_flags_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_flags_daily_attendance_id_foreign` FOREIGN KEY (`daily_attendance_id`) REFERENCES `daily_attendances` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_flags_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_flags_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_flags_worker_id_foreign` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_flags`
--

LOCK TABLES `attendance_flags` WRITE;
/*!40000 ALTER TABLE `attendance_flags` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_flags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_locks`
--

DROP TABLE IF EXISTS `attendance_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_locks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `stage` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `submitted_by` bigint unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `site_locked_by` bigint unsigned DEFAULT NULL,
  `site_locked_at` timestamp NULL DEFAULT NULL,
  `payroll_locked_by` bigint unsigned DEFAULT NULL,
  `payroll_locked_at` timestamp NULL DEFAULT NULL,
  `unlocked_by` bigint unsigned DEFAULT NULL,
  `unlocked_at` timestamp NULL DEFAULT NULL,
  `unlock_reason` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `att_lock_comp_site_date_uniq` (`company_id`,`site_id`,`attendance_date`),
  KEY `attendance_locks_site_id_foreign` (`site_id`),
  KEY `attendance_locks_submitted_by_foreign` (`submitted_by`),
  KEY `attendance_locks_site_locked_by_foreign` (`site_locked_by`),
  KEY `attendance_locks_payroll_locked_by_foreign` (`payroll_locked_by`),
  KEY `attendance_locks_unlocked_by_foreign` (`unlocked_by`),
  CONSTRAINT `attendance_locks_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_locks_payroll_locked_by_foreign` FOREIGN KEY (`payroll_locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_locks_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_locks_site_locked_by_foreign` FOREIGN KEY (`site_locked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_locks_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_locks_unlocked_by_foreign` FOREIGN KEY (`unlocked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_locks`
--

LOCK TABLES `attendance_locks` WRITE;
/*!40000 ALTER TABLE `attendance_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendance_punches`
--

DROP TABLE IF EXISTS `attendance_punches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_punches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned DEFAULT NULL,
  `worker_id` bigint unsigned NOT NULL,
  `daily_attendance_id` bigint unsigned DEFAULT NULL,
  `punch_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in',
  `punched_at` timestamp NOT NULL,
  `source` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'supervisor',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `accuracy_m` decimal(8,2) DEFAULT NULL,
  `distance_m` decimal(10,2) DEFAULT NULL,
  `within_geofence` tinyint(1) DEFAULT NULL,
  `device_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `selfie_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marked_by` bigint unsigned DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendance_punches_site_id_foreign` (`site_id`),
  KEY `attendance_punches_project_id_foreign` (`project_id`),
  KEY `attendance_punches_worker_id_foreign` (`worker_id`),
  KEY `attendance_punches_daily_attendance_id_foreign` (`daily_attendance_id`),
  KEY `attendance_punches_marked_by_foreign` (`marked_by`),
  KEY `att_punch_comp_worker_time_idx` (`company_id`,`worker_id`,`punched_at`),
  KEY `att_punch_comp_site_time_idx` (`company_id`,`site_id`,`punched_at`),
  CONSTRAINT `attendance_punches_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_punches_daily_attendance_id_foreign` FOREIGN KEY (`daily_attendance_id`) REFERENCES `daily_attendances` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_punches_marked_by_foreign` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_punches_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_punches_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_punches_worker_id_foreign` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance_punches`
--

LOCK TABLES `attendance_punches` WRITE;
/*!40000 ALTER TABLE `attendance_punches` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_punches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bill_exports`
--

DROP TABLE IF EXISTS `bill_exports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bill_exports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `work_order_id` bigint unsigned NOT NULL,
  `ra_bill_id` bigint unsigned DEFAULT NULL,
  `export_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exported_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bill_exports_company_id_foreign` (`company_id`),
  KEY `bill_exports_ra_bill_id_foreign` (`ra_bill_id`),
  KEY `bill_exports_exported_by_foreign` (`exported_by`),
  KEY `bill_exports_wo_bill_type_idx` (`work_order_id`,`ra_bill_id`,`export_type`),
  CONSTRAINT `bill_exports_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bill_exports_exported_by_foreign` FOREIGN KEY (`exported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bill_exports_ra_bill_id_foreign` FOREIGN KEY (`ra_bill_id`) REFERENCES `ra_bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bill_exports_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bill_exports`
--

LOCK TABLES `bill_exports` WRITE;
/*!40000 ALTER TABLE `bill_exports` DISABLE KEYS */;
/*!40000 ALTER TABLE `bill_exports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `gstin` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pan` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clients_comp_active_idx` (`company_id`,`is_active`),
  CONSTRAINT `clients_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `companies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `registered_address` text COLLATE utf8mb4_unicode_ci,
  `gstin` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pan` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_ifsc` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_branch` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signature_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_prefix` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CIVIL',
  `invoice_starting_number` int unsigned NOT NULL DEFAULT '21',
  `invoice_rounding_mode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paisa',
  `work_order_terms_template` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `companies_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `companies`
--

LOCK TABLES `companies` WRITE;
/*!40000 ALTER TABLE `companies` DISABLE KEYS */;
INSERT INTO `companies` VALUES (1,'NSD Earth Movers & Construction Ltd','NSD',NULL,NULL,NULL,1,'2026-11-01 16:11:07','2026-11-01 16:11:07',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'CIVIL',21,'paisa',NULL);
/*!40000 ALTER TABLE `companies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `company_user`
--

DROP TABLE IF EXISTS `company_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_user_company_id_user_id_unique` (`company_id`,`user_id`),
  KEY `company_user_user_id_foreign` (`user_id`),
  KEY `company_user_role_id_foreign` (`role_id`),
  CONSTRAINT `company_user_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `company_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `company_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `company_user`
--

LOCK TABLES `company_user` WRITE;
/*!40000 ALTER TABLE `company_user` DISABLE KEYS */;
INSERT INTO `company_user` VALUES (1,1,1,2,'active',0,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(2,1,2,3,'active',0,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(3,1,3,4,'active',0,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(4,1,4,5,'active',0,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(5,1,5,5,'active',0,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(6,1,6,6,'active',0,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(7,1,7,7,'active',0,'2026-11-01 16:11:07','2026-11-01 16:11:07');
/*!40000 ALTER TABLE `company_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_notifications`
--

DROP TABLE IF EXISTS `crm_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_notifications_user_id_foreign` (`user_id`),
  KEY `crm_notifications_site_id_foreign` (`site_id`),
  KEY `crm_notif_user_read_idx` (`company_id`,`user_id`,`read_at`),
  CONSTRAINT `crm_notifications_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `crm_notifications_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `crm_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_notifications`
--

LOCK TABLES `crm_notifications` WRITE;
/*!40000 ALTER TABLE `crm_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `crm_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_attendances`
--

DROP TABLE IF EXISTS `daily_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `worker_id` bigint unsigned NOT NULL,
  `shift_id` bigint unsigned DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `clock_in` time DEFAULT NULL,
  `clock_out` time DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'present',
  `worked_hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  `overtime_hours` decimal(5,2) NOT NULL DEFAULT '0.00',
  `late_minutes` int unsigned NOT NULL DEFAULT '0',
  `has_conflict` tinyint(1) NOT NULL DEFAULT '0',
  `conflict_reason` text COLLATE utf8mb4_unicode_ci,
  `approval_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_approval',
  `lock_stage` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `submitted_by` bigint unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `daily_attendances_site_id_foreign` (`site_id`),
  KEY `daily_attendances_project_id_foreign` (`project_id`),
  KEY `daily_attendances_worker_id_foreign` (`worker_id`),
  KEY `daily_attendances_submitted_by_foreign` (`submitted_by`),
  KEY `daily_attendances_approved_by_foreign` (`approved_by`),
  KEY `att_comp_worker_date_idx` (`company_id`,`worker_id`,`attendance_date`),
  KEY `att_comp_site_date_idx` (`company_id`,`site_id`,`attendance_date`),
  KEY `daily_attendances_shift_id_foreign` (`shift_id`),
  CONSTRAINT `daily_attendances_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `daily_attendances_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `daily_attendances_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `daily_attendances_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `daily_attendances_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `daily_attendances_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `daily_attendances_worker_id_foreign` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_attendances`
--

LOCK TABLES `daily_attendances` WRITE;
/*!40000 ALTER TABLE `daily_attendances` DISABLE KEYS */;
INSERT INTO `daily_attendances` VALUES (1,1,1,1,1,NULL,'2026-11-01','07:30:00','12:30:00','present',5.00,0.00,0,0,NULL,'approved','open',4,'2026-11-01 12:11:07',3,'2026-11-01 14:11:07',NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(2,1,2,1,1,NULL,'2026-11-01','14:00:00','18:30:00','present',4.50,0.00,300,0,NULL,'approved','open',4,'2026-11-01 15:11:07',3,'2026-11-01 15:41:07',NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(3,1,1,1,2,NULL,'2026-11-01','08:00:00','17:00:00','present',9.00,1.00,0,0,NULL,'approved','open',4,'2026-11-01 12:11:07',3,'2026-11-01 14:11:07',NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(4,1,1,1,11,NULL,'2026-11-01','08:30:00','17:30:00','present',9.00,1.00,0,0,NULL,'approved','open',4,'2026-11-01 12:11:07',3,'2026-11-01 14:11:07',NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07');
/*!40000 ALTER TABLE `daily_attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `holidays`
--

DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `holidays` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `holiday_date` date NOT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT '1',
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `holidays_site_id_foreign` (`site_id`),
  KEY `holidays_company_id_holiday_date_index` (`company_id`,`holiday_date`),
  CONSTRAINT `holidays_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `holidays_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `holidays`
--

LOCK TABLES `holidays` WRITE;
/*!40000 ALTER TABLE `holidays` DISABLE KEYS */;
/*!40000 ALTER TABLE `holidays` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_stocks`
--

DROP TABLE IF EXISTS `inventory_stocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_stocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `material_id` bigint unsigned NOT NULL,
  `quantity` decimal(14,2) NOT NULL DEFAULT '0.00',
  `reserved_quantity` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_stocks_warehouse_id_material_id_unique` (`warehouse_id`,`material_id`),
  KEY `inventory_stocks_material_id_foreign` (`material_id`),
  KEY `inventory_stocks_company_id_warehouse_id_index` (`company_id`,`warehouse_id`),
  CONSTRAINT `inventory_stocks_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_stocks_material_id_foreign` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_stocks_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_stocks`
--

LOCK TABLES `inventory_stocks` WRITE;
/*!40000 ALTER TABLE `inventory_stocks` DISABLE KEYS */;
INSERT INTO `inventory_stocks` VALUES (1,1,1,4,12500.00,0.00,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(2,1,1,2,1400.00,200.00,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(3,1,1,3,180.00,25.00,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(4,1,2,1,1250.00,0.00,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(5,1,2,4,4500.00,0.00,'2026-11-01 16:11:07','2026-11-01 16:11:07');
/*!40000 ALTER TABLE `inventory_stocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_transaction_items`
--

DROP TABLE IF EXISTS `inventory_transaction_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_transaction_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventory_transaction_id` bigint unsigned NOT NULL,
  `material_id` bigint unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_transaction_items_inventory_transaction_id_foreign` (`inventory_transaction_id`),
  KEY `inventory_transaction_items_material_id_foreign` (`material_id`),
  CONSTRAINT `inventory_transaction_items_inventory_transaction_id_foreign` FOREIGN KEY (`inventory_transaction_id`) REFERENCES `inventory_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_transaction_items_material_id_foreign` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_transaction_items`
--

LOCK TABLES `inventory_transaction_items` WRITE;
/*!40000 ALTER TABLE `inventory_transaction_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_transaction_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_transactions`
--

DROP TABLE IF EXISTS `inventory_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `transaction_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_warehouse_id` bigint unsigned DEFAULT NULL,
  `destination_warehouse_id` bigint unsigned DEFAULT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `purchase_order_id` bigint unsigned DEFAULT NULL,
  `challan_or_invoice_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `performed_by` bigint unsigned NOT NULL,
  `confirmed_by` bigint unsigned DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'confirmed',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_transactions_company_id_transaction_number_unique` (`company_id`,`transaction_number`),
  KEY `inventory_transactions_source_warehouse_id_foreign` (`source_warehouse_id`),
  KEY `inventory_transactions_destination_warehouse_id_foreign` (`destination_warehouse_id`),
  KEY `inventory_transactions_site_id_foreign` (`site_id`),
  KEY `inventory_transactions_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `inventory_transactions_performed_by_foreign` (`performed_by`),
  KEY `inventory_transactions_confirmed_by_foreign` (`confirmed_by`),
  KEY `inventory_transactions_company_id_transaction_type_index` (`company_id`,`transaction_type`),
  CONSTRAINT `inventory_transactions_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_transactions_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transactions_destination_warehouse_id_foreign` FOREIGN KEY (`destination_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transactions_performed_by_foreign` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_transactions_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transactions_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_transactions_source_warehouse_id_foreign` FOREIGN KEY (`source_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_transactions`
--

LOCK TABLES `inventory_transactions` WRITE;
/*!40000 ALTER TABLE `inventory_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `inventory_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_sequences`
--

DROP TABLE IF EXISTS `invoice_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_sequences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `prefix` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CIVIL',
  `financial_year` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_sequence` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_sequences_company_prefix_fy_unique` (`company_id`,`prefix`,`financial_year`),
  CONSTRAINT `invoice_sequences_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_sequences`
--

LOCK TABLES `invoice_sequences` WRITE;
/*!40000 ALTER TABLE `invoice_sequences` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_requests`
--

DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leave_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `worker_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `leave_type_id` bigint unsigned DEFAULT NULL,
  `leave_type_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Paid Leave',
  `is_paid` tinyint(1) NOT NULL DEFAULT '1',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_count` smallint unsigned NOT NULL DEFAULT '1',
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `requested_by` bigint unsigned DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leave_requests_worker_id_foreign` (`worker_id`),
  KEY `leave_requests_site_id_foreign` (`site_id`),
  KEY `leave_requests_leave_type_id_foreign` (`leave_type_id`),
  KEY `leave_requests_requested_by_foreign` (`requested_by`),
  KEY `leave_requests_reviewed_by_foreign` (`reviewed_by`),
  KEY `leave_requests_company_id_status_index` (`company_id`,`status`),
  KEY `leave_requests_company_id_worker_id_start_date_index` (`company_id`,`worker_id`,`start_date`),
  CONSTRAINT `leave_requests_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_leave_type_id_foreign` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leave_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leave_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leave_requests_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leave_requests_worker_id_foreign` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_requests`
--

LOCK TABLES `leave_requests` WRITE;
/*!40000 ALTER TABLE `leave_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `leave_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_types`
--

DROP TABLE IF EXISTS `leave_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leave_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT '1',
  `annual_quota` smallint unsigned NOT NULL DEFAULT '12',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leave_types_company_id_code_unique` (`company_id`,`code`),
  CONSTRAINT `leave_types_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_types`
--

LOCK TABLES `leave_types` WRITE;
/*!40000 ALTER TABLE `leave_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `leave_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `material_categories`
--

DROP TABLE IF EXISTS `material_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `material_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_categories_company_id_code_unique` (`company_id`,`code`),
  CONSTRAINT `material_categories_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `material_categories`
--

LOCK TABLES `material_categories` WRITE;
/*!40000 ALTER TABLE `material_categories` DISABLE KEYS */;
INSERT INTO `material_categories` VALUES (1,1,'Aggregates & Stone Base','AGG','2026-11-01 16:11:07','2026-11-01 16:11:07'),(2,1,'Cement & Binding Materials','CEM','2026-11-01 16:11:07','2026-11-01 16:11:07'),(3,1,'Reinforcement Steel & TMT','STL','2026-11-01 16:11:07','2026-11-01 16:11:07'),(4,1,'Fuel, Oils & Lubricants','FUL','2026-11-01 16:11:07','2026-11-01 16:11:07'),(5,1,'Safety Gear & PPE','PPE','2026-11-01 16:11:07','2026-11-01 16:11:07');
/*!40000 ALTER TABLE `material_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `materials`
--

DROP TABLE IF EXISTS `materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `materials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `category_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_of_measure` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `minimum_stock_level` decimal(12,2) NOT NULL DEFAULT '0.00',
  `standard_unit_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `materials_company_id_code_unique` (`company_id`,`code`),
  KEY `materials_category_id_foreign` (`category_id`),
  CONSTRAINT `materials_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `material_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `materials_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `materials`
--

LOCK TABLES `materials` WRITE;
/*!40000 ALTER TABLE `materials` DISABLE KEYS */;
INSERT INTO `materials` VALUES (1,1,1,'Granular Sub-Base (GSB) 40mm Crushed Stone','GSB-40MM','MT',500.00,650.00,1,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(2,1,2,'OPC 53 Grade High Performance Cement','CEM-OPC-53','Bags',300.00,380.00,1,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(3,1,3,'Fe 500D High Ductility TMT Rebar 16mm','TMT-16MM','MT',50.00,58000.00,1,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(4,1,4,'High Speed Industrial Diesel (HSD)','HSD-DIESEL','Litres',2000.00,92.50,1,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(5,1,5,'Industrial Safety Helmet EN397 (Yellow/White)','PPE-HELMET','Units',50.00,220.00,1,'2026-11-01 16:11:07','2026-11-01 16:11:07');
/*!40000 ALTER TABLE `materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `measurement_entries`
--

DROP TABLE IF EXISTS `measurement_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `measurement_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `work_order_id` bigint unsigned NOT NULL,
  `work_order_item_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `ra_bill_id` bigint unsigned DEFAULT NULL,
  `measured_on` date NOT NULL,
  `location_chainage` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nos` decimal(12,3) NOT NULL DEFAULT '1.000',
  `length` decimal(12,3) DEFAULT NULL,
  `breadth` decimal(12,3) DEFAULT NULL,
  `depth_height` decimal(12,3) DEFAULT NULL,
  `direct_quantity` decimal(14,3) DEFAULT NULL,
  `computed_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `hold_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'verified',
  `recorded_by` bigint unsigned DEFAULT NULL,
  `verified_by` bigint unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `measurement_entries_company_id_foreign` (`company_id`),
  KEY `measurement_entries_work_order_item_id_foreign` (`work_order_item_id`),
  KEY `measurement_entries_site_id_foreign` (`site_id`),
  KEY `measurement_entries_ra_bill_id_foreign` (`ra_bill_id`),
  KEY `measurement_entries_recorded_by_foreign` (`recorded_by`),
  KEY `measurement_entries_verified_by_foreign` (`verified_by`),
  KEY `measurement_entries_wo_item_date_idx` (`work_order_id`,`work_order_item_id`,`measured_on`),
  CONSTRAINT `measurement_entries_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `measurement_entries_ra_bill_id_foreign` FOREIGN KEY (`ra_bill_id`) REFERENCES `ra_bills` (`id`) ON DELETE SET NULL,
  CONSTRAINT `measurement_entries_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `measurement_entries_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `measurement_entries_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `measurement_entries_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `measurement_entries_work_order_item_id_foreign` FOREIGN KEY (`work_order_item_id`) REFERENCES `work_order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `measurement_entries`
--

LOCK TABLES `measurement_entries` WRITE;
/*!40000 ALTER TABLE `measurement_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `measurement_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_10_28_000001_create_tenancy_and_roles_tables',1),(5,'2026_10_28_000002_create_workforce_and_attendance_tables',1),(6,'2026_10_28_000003_create_work_orders_tables',1),(7,'2026_10_28_000004_create_procurement_and_inventory_tables',1),(8,'2026_10_28_000005_add_is_active_to_users_table',1),(9,'2026_10_28_000006_add_location_details_to_sites_table',1),(10,'2026_10_28_000007_create_worker_documents_table',1),(11,'2026_10_28_000008_add_profile_fields_to_workers_table',1),(12,'2026_10_28_000009_create_phase1_projects_sites_extensions',1),(13,'2026_10_28_000010_create_phase2_workforce_extensions',1),(14,'2026_10_28_000011_create_phase3_attendance_engine_tables',1),(15,'2026_10_28_000012_create_phase4_tasks_notes_notifications_tables',1),(16,'2026_11_01_000001_add_pay_types_and_create_payroll_tables',1),(17,'2026_11_01_000002_add_work_order_boq_and_kiosk_columns',1),(18,'2026_11_01_000003_phase_a_jsw_work_orders_items_revisions_and_clients',1),(19,'2026_11_01_000004_phase_b_site_measurements_and_ra_bills',1),(20,'2026_11_01_000005_phase_c_invoice_sequences_and_bill_exports',1),(21,'2026_11_01_000006_phase_f_manager_two_factor_auth',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_entries`
--

DROP TABLE IF EXISTS `payroll_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `payroll_period_id` bigint unsigned NOT NULL,
  `worker_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `pay_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'daily',
  `present_days` decimal(5,2) NOT NULL DEFAULT '0.00',
  `half_days` decimal(5,2) NOT NULL DEFAULT '0.00',
  `paid_leave_days` decimal(5,2) NOT NULL DEFAULT '0.00',
  `holiday_days` decimal(5,2) NOT NULL DEFAULT '0.00',
  `effective_days` decimal(5,2) NOT NULL DEFAULT '0.00',
  `worked_hours` decimal(7,2) NOT NULL DEFAULT '0.00',
  `overtime_hours` decimal(7,2) NOT NULL DEFAULT '0.00',
  `base_rate` decimal(12,2) NOT NULL DEFAULT '0.00',
  `overtime_rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `base_pay` decimal(12,2) NOT NULL DEFAULT '0.00',
  `overtime_pay` decimal(12,2) NOT NULL DEFAULT '0.00',
  `bonus_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `gross_pay` decimal(12,2) NOT NULL DEFAULT '0.00',
  `advance_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `other_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `deduction_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `net_pay` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_entries_period_worker_uniq` (`payroll_period_id`,`worker_id`),
  KEY `payroll_entries_worker_id_foreign` (`worker_id`),
  KEY `payroll_entries_site_id_foreign` (`site_id`),
  KEY `payroll_entries_comp_worker_idx` (`company_id`,`worker_id`),
  CONSTRAINT `payroll_entries_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_entries_payroll_period_id_foreign` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_entries_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_entries_worker_id_foreign` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_entries`
--

LOCK TABLES `payroll_entries` WRITE;
/*!40000 ALTER TABLE `payroll_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `payroll_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_periods`
--

DROP TABLE IF EXISTS `payroll_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_periods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `period_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `total_workers` int unsigned NOT NULL DEFAULT '0',
  `total_gross_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `total_deductions` decimal(14,2) NOT NULL DEFAULT '0.00',
  `total_net_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `calculated_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_periods_comp_code_uniq` (`company_id`,`period_code`),
  KEY `payroll_periods_site_id_foreign` (`site_id`),
  KEY `payroll_periods_calculated_by_foreign` (`calculated_by`),
  KEY `payroll_periods_approved_by_foreign` (`approved_by`),
  KEY `payroll_periods_comp_site_status_idx` (`company_id`,`site_id`,`status`),
  CONSTRAINT `payroll_periods_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_periods_calculated_by_foreign` FOREIGN KEY (`calculated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_periods_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_periods_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_periods`
--

LOCK TABLES `payroll_periods` WRITE;
/*!40000 ALTER TABLE `payroll_periods` DISABLE KEYS */;
/*!40000 ALTER TABLE `payroll_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_documents`
--

DROP TABLE IF EXISTS `project_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `document_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'drawing',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `storage_disk` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint unsigned NOT NULL DEFAULT '0',
  `visibility_roles` json DEFAULT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_documents_project_id_foreign` (`project_id`),
  KEY `project_documents_site_id_foreign` (`site_id`),
  KEY `project_documents_uploaded_by_foreign` (`uploaded_by`),
  KEY `pd_comp_proj_type_idx` (`company_id`,`project_id`,`document_type`),
  KEY `pd_comp_site_idx` (`company_id`,`site_id`),
  CONSTRAINT `project_documents_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_documents_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_documents_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_documents`
--

LOCK TABLES `project_documents` WRITE;
/*!40000 ALTER TABLE `project_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_milestones`
--

DROP TABLE IF EXISTS `project_milestones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_milestones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `due_date` date DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `percent_complete` tinyint unsigned NOT NULL DEFAULT '0',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_milestones_project_id_foreign` (`project_id`),
  KEY `project_milestones_site_id_foreign` (`site_id`),
  KEY `project_milestones_created_by_foreign` (`created_by`),
  KEY `pm_comp_proj_status_idx` (`company_id`,`project_id`,`status`),
  KEY `pm_comp_site_idx` (`company_id`,`site_id`),
  CONSTRAINT `project_milestones_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_milestones_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `project_milestones_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_milestones_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_milestones`
--

LOCK TABLES `project_milestones` WRITE;
/*!40000 ALTER TABLE `project_milestones` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_milestones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_sites`
--

DROP TABLE IF EXISTS `project_sites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_sites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_sites_project_id_site_id_unique` (`project_id`,`site_id`),
  KEY `project_sites_site_id_foreign` (`site_id`),
  KEY `project_sites_company_id_site_id_index` (`company_id`,`site_id`),
  CONSTRAINT `project_sites_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_sites_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_sites_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_sites`
--

LOCK TABLES `project_sites` WRITE;
/*!40000 ALTER TABLE `project_sites` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_sites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manager_id` bigint unsigned DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projects_company_id_code_unique` (`company_id`,`code`),
  KEY `projects_manager_id_foreign` (`manager_id`),
  CONSTRAINT `projects_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `projects_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
INSERT INTO `projects` VALUES (1,1,'Highway NH-48 6-Laning & Grade Separator','NH-48','National Highways Authority of India (NHAI)',3,'active','2026-05-01','2027-11-01','Heavy earthwork, culvert construction and asphalt paving over 45km','2026-11-01 16:11:07','2026-11-01 16:11:07'),(2,1,'Metro Rail Elevated Viaduct Package 3','MTR-P3','Metro Rail Development Corp',3,'active','2026-08-01','2028-05-01','Pier foundation, piling and station 4 structural earthworks','2026-11-01 16:11:07','2026-11-01 16:11:07');
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_order_items`
--

DROP TABLE IF EXISTS `purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint unsigned NOT NULL,
  `material_id` bigint unsigned NOT NULL,
  `quantity_ordered` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `quantity_received` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_order_items_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `purchase_order_items_material_id_foreign` (`material_id`),
  CONSTRAINT `purchase_order_items_material_id_foreign` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_order_items_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_order_items`
--

LOCK TABLES `purchase_order_items` WRITE;
/*!40000 ALTER TABLE `purchase_order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `purchase_request_id` bigint unsigned DEFAULT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `po_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `total_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'issued',
  `created_by` bigint unsigned NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_orders_company_id_po_number_unique` (`company_id`,`po_number`),
  KEY `purchase_orders_purchase_request_id_foreign` (`purchase_request_id`),
  KEY `purchase_orders_site_id_foreign` (`site_id`),
  KEY `purchase_orders_warehouse_id_foreign` (`warehouse_id`),
  KEY `purchase_orders_created_by_foreign` (`created_by`),
  CONSTRAINT `purchase_orders_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_orders_purchase_request_id_foreign` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_orders`
--

LOCK TABLES `purchase_orders` WRITE;
/*!40000 ALTER TABLE `purchase_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_request_items`
--

DROP TABLE IF EXISTS `purchase_request_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_request_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_request_id` bigint unsigned NOT NULL,
  `material_id` bigint unsigned NOT NULL,
  `quantity_requested` decimal(12,2) NOT NULL,
  `estimated_unit_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `purpose_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_request_items_purchase_request_id_foreign` (`purchase_request_id`),
  KEY `purchase_request_items_material_id_foreign` (`material_id`),
  CONSTRAINT `purchase_request_items_material_id_foreign` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_request_items_purchase_request_id_foreign` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_request_items`
--

LOCK TABLES `purchase_request_items` WRITE;
/*!40000 ALTER TABLE `purchase_request_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_request_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_requests`
--

DROP TABLE IF EXISTS `purchase_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `request_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `urgency` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `is_emergency` tinyint(1) NOT NULL DEFAULT '0',
  `emergency_justification` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `review_comments` text COLLATE utf8mb4_unicode_ci,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_requests_company_id_request_number_unique` (`company_id`,`request_number`),
  KEY `purchase_requests_site_id_foreign` (`site_id`),
  KEY `purchase_requests_warehouse_id_foreign` (`warehouse_id`),
  KEY `purchase_requests_requested_by_foreign` (`requested_by`),
  KEY `purchase_requests_approved_by_foreign` (`approved_by`),
  KEY `purchase_requests_company_id_status_index` (`company_id`,`status`),
  CONSTRAINT `purchase_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_requests_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_requests`
--

LOCK TABLES `purchase_requests` WRITE;
/*!40000 ALTER TABLE `purchase_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ra_bill_items`
--

DROP TABLE IF EXISTS `ra_bill_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ra_bill_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `ra_bill_id` bigint unsigned NOT NULL,
  `work_order_item_id` bigint unsigned NOT NULL,
  `sr_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_code` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sac_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specification` text COLLATE utf8mb4_unicode_ci,
  `unit` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'M3',
  `ordered_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `rate` decimal(14,2) NOT NULL DEFAULT '0.00',
  `order_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `previous_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `claimed_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `hold_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `effective_claimed_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `remaining_available_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `current_billable_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `excess_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `cumulative_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `balance_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `consumption_percent` decimal(7,2) NOT NULL DEFAULT '0.00',
  `warning_level` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `previous_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `current_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `cumulative_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `excess_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ra_bill_items_bill_wo_item_unique` (`ra_bill_id`,`work_order_item_id`),
  KEY `ra_bill_items_company_id_foreign` (`company_id`),
  KEY `ra_bill_items_work_order_item_id_foreign` (`work_order_item_id`),
  CONSTRAINT `ra_bill_items_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ra_bill_items_ra_bill_id_foreign` FOREIGN KEY (`ra_bill_id`) REFERENCES `ra_bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ra_bill_items_work_order_item_id_foreign` FOREIGN KEY (`work_order_item_id`) REFERENCES `work_order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ra_bill_items`
--

LOCK TABLES `ra_bill_items` WRITE;
/*!40000 ALTER TABLE `ra_bill_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `ra_bill_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ra_bills`
--

DROP TABLE IF EXISTS `ra_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ra_bills` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `work_order_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `ra_number` int unsigned NOT NULL,
  `ra_label` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `period_from` date NOT NULL,
  `period_to` date NOT NULL,
  `work_order_revision` int unsigned NOT NULL DEFAULT '0',
  `previous_bill_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `current_bill_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `cumulative_bill_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `excess_unbilled_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT '9.00',
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT '9.00',
  `cgst_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `sgst_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `gross_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `retention_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `retention_deduction` decimal(16,2) NOT NULL DEFAULT '0.00',
  `advance_recovery` decimal(16,2) NOT NULL DEFAULT '0.00',
  `other_deductions` decimal(16,2) NOT NULL DEFAULT '0.00',
  `rounding_mode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'paisa',
  `net_payable` decimal(16,2) NOT NULL DEFAULT '0.00',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint unsigned DEFAULT NULL,
  `site_manager_id` bigint unsigned DEFAULT NULL,
  `site_manager_approved_at` timestamp NULL DEFAULT NULL,
  `site_manager_notes` text COLLATE utf8mb4_unicode_ci,
  `senior_manager_id` bigint unsigned DEFAULT NULL,
  `senior_manager_approved_at` timestamp NULL DEFAULT NULL,
  `senior_manager_notes` text COLLATE utf8mb4_unicode_ci,
  `rejected_by` bigint unsigned DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ra_bills_wo_ra_number_unique` (`work_order_id`,`ra_number`),
  KEY `ra_bills_site_id_foreign` (`site_id`),
  KEY `ra_bills_client_id_foreign` (`client_id`),
  KEY `ra_bills_created_by_foreign` (`created_by`),
  KEY `ra_bills_site_manager_id_foreign` (`site_manager_id`),
  KEY `ra_bills_senior_manager_id_foreign` (`senior_manager_id`),
  KEY `ra_bills_rejected_by_foreign` (`rejected_by`),
  KEY `ra_bills_company_id_status_index` (`company_id`,`status`),
  CONSTRAINT `ra_bills_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ra_bills_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ra_bills_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ra_bills_rejected_by_foreign` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ra_bills_senior_manager_id_foreign` FOREIGN KEY (`senior_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ra_bills_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ra_bills_site_manager_id_foreign` FOREIGN KEY (`site_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ra_bills_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ra_bills`
--

LOCK TABLES `ra_bills` WRITE;
/*!40000 ALTER TABLE `ra_bills` DISABLE KEYS */;
/*!40000 ALTER TABLE `ra_bills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permissions` json DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_company_id_slug_unique` (`company_id`,`slug`),
  CONSTRAINT `roles_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,1,'Super Administrator','super_admin','[\"*\"]',NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(2,1,'Company Administrator','company_admin','[\"*\"]',NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(3,1,'Senior Operations Manager','senior_manager','[\"work_orders.approve_senior\", \"work_orders.view\", \"procurement.approve\", \"procurement.view\", \"attendance.view\", \"reports.view\"]',NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(4,1,'Site Manager','site_manager','[\"work_orders.create\", \"work_orders.approve_site\", \"work_orders.view\", \"procurement.request\", \"attendance.record\", \"attendance.view\", \"inventory.view\", \"reports.view\"]',NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(5,1,'Site Supervisor','site_supervisor','[\"attendance.record\", \"attendance.view\", \"inventory.receive\", \"reports.view\"]',NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(6,1,'Warehouse Storekeeper','warehouse_manager','[\"inventory.manage\", \"inventory.issue\", \"inventory.receive\", \"procurement.view\"]',NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(7,1,'Office / Compliance Staff','office_staff','[\"reports.view\", \"work_orders.view\", \"attendance.view\"]',NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salary_advances`
--

DROP TABLE IF EXISTS `salary_advances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_advances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `worker_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `advance_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `deducted_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `payroll_period_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `salary_advances_worker_id_foreign` (`worker_id`),
  KEY `salary_advances_site_id_foreign` (`site_id`),
  KEY `salary_advances_approved_by_foreign` (`approved_by`),
  KEY `salary_advances_payroll_period_id_foreign` (`payroll_period_id`),
  KEY `sal_adv_comp_worker_status_idx` (`company_id`,`worker_id`,`status`),
  CONSTRAINT `salary_advances_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salary_advances_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_advances_payroll_period_id_foreign` FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salary_advances_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salary_advances_worker_id_foreign` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salary_advances`
--

LOCK TABLES `salary_advances` WRITE;
/*!40000 ALTER TABLE `salary_advances` DISABLE KEYS */;
/*!40000 ALTER TABLE `salary_advances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shifts`
--

DROP TABLE IF EXISTS `shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shifts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` time NOT NULL DEFAULT '09:00:00',
  `end_time` time NOT NULL DEFAULT '18:00:00',
  `grace_minutes` smallint unsigned NOT NULL DEFAULT '15',
  `half_day_hours` decimal(4,2) NOT NULL DEFAULT '4.00',
  `full_day_hours` decimal(4,2) NOT NULL DEFAULT '8.00',
  `overtime_after_hours` decimal(4,2) NOT NULL DEFAULT '8.00',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shifts_site_id_foreign` (`site_id`),
  KEY `shifts_company_id_site_id_index` (`company_id`,`site_id`),
  CONSTRAINT `shifts_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shifts_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shifts`
--

LOCK TABLES `shifts` WRITE;
/*!40000 ALTER TABLE `shifts` DISABLE KEYS */;
/*!40000 ALTER TABLE `shifts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_assignments`
--

DROP TABLE IF EXISTS `site_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `worker_id` bigint unsigned DEFAULT NULL,
  `role_on_site` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'worker',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `assigned_by` bigint unsigned DEFAULT NULL,
  `notes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `site_assignments_site_id_foreign` (`site_id`),
  KEY `site_assignments_user_id_foreign` (`user_id`),
  KEY `site_assignments_worker_id_foreign` (`worker_id`),
  KEY `site_assignments_assigned_by_foreign` (`assigned_by`),
  KEY `sa_comp_site_active_idx` (`company_id`,`site_id`,`is_active`),
  KEY `sa_comp_user_active_idx` (`company_id`,`user_id`,`is_active`),
  KEY `sa_comp_worker_active_idx` (`company_id`,`worker_id`,`is_active`),
  CONSTRAINT `site_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `site_assignments_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_assignments_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_assignments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_assignments_worker_id_foreign` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_assignments`
--

LOCK TABLES `site_assignments` WRITE;
/*!40000 ALTER TABLE `site_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `site_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_notes`
--

DROP TABLE IF EXISTS `site_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `category` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `site_notes_project_id_foreign` (`project_id`),
  KEY `site_notes_site_id_foreign` (`site_id`),
  KEY `site_notes_user_id_foreign` (`user_id`),
  KEY `site_notes_company_id_site_id_is_pinned_index` (`company_id`,`site_id`,`is_pinned`),
  KEY `site_notes_company_id_project_id_index` (`company_id`,`project_id`),
  CONSTRAINT `site_notes_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_notes_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_notes_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `site_notes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_notes`
--

LOCK TABLES `site_notes` WRITE;
/*!40000 ALTER TABLE `site_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `site_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sites`
--

DROP TABLE IF EXISTS `sites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `geofence_radius_m` int unsigned NOT NULL DEFAULT '200',
  `shift_start` time DEFAULT '09:00:00',
  `shift_end` time DEFAULT '18:00:00',
  `description` text COLLATE utf8mb4_unicode_ci,
  `supervisor_id` bigint unsigned DEFAULT NULL,
  `site_manager_id` bigint unsigned DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `kiosk_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sites_company_id_code_unique` (`company_id`,`code`),
  UNIQUE KEY `sites_kiosk_token_unique` (`kiosk_token`),
  KEY `sites_project_id_foreign` (`project_id`),
  KEY `sites_supervisor_id_foreign` (`supervisor_id`),
  KEY `sites_site_manager_id_foreign` (`site_manager_id`),
  CONSTRAINT `sites_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sites_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sites_site_manager_id_foreign` FOREIGN KEY (`site_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sites_supervisor_id_foreign` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sites`
--

LOCK TABLES `sites` WRITE;
/*!40000 ALTER TABLE `sites` DISABLE KEYS */;
INSERT INTO `sites` VALUES (1,1,1,'NH-48 Sector 1 (km 24 to 32) Earthworks','SITE-NH48-1','Sector 1 km 24-32 Corridor',NULL,NULL,NULL,NULL,NULL,NULL,200,'09:00:00','18:00:00',NULL,4,NULL,'active','GGzqqBOCVilr0Z8MZ1GOolsD6aTCUputrePMsr4pjZIFLKJB','2026-11-01 16:11:07','2026-11-01 16:11:07'),(2,1,1,'NH-48 Sector 2 (km 32 to 45) Paving & Culverts','SITE-NH48-2','Sector 2 km 32-45 Corridor',NULL,NULL,NULL,NULL,NULL,NULL,200,'09:00:00','18:00:00',NULL,4,NULL,'active','xYz04dswfPgVmvFPc2pZ0g17LByiKWMcfbQRZMTmW2oELRsx','2026-11-01 16:11:07','2026-11-01 16:11:07'),(3,1,2,'Metro Station 4 & Yard Viaduct','SITE-MTR-04','Urban Terminal Station 4 Hub',NULL,NULL,NULL,NULL,NULL,NULL,200,'09:00:00','18:00:00',NULL,5,NULL,'active','rc2IrvGenYFLli6dp4ZVUrgeckki1F5BoVIOnmkpfHs8CLtu','2026-11-01 16:11:07','2026-11-01 16:11:07');
/*!40000 ALTER TABLE `sites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subcontractors`
--

DROP TABLE IF EXISTS `subcontractors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subcontractors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gst_number` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pan_number` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subcontractors_company_id_code_unique` (`company_id`,`code`),
  CONSTRAINT `subcontractors_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subcontractors`
--

LOCK TABLES `subcontractors` WRITE;
/*!40000 ALTER TABLE `subcontractors` DISABLE KEYS */;
INSERT INTO `subcontractors` VALUES (1,1,'Apex Heavy Plant & Earthmoving Works','SUB-APEX','Rajesh Verma (General Manager)','+91 98230 11223','operations@apexplant.com','27AAACA1234A1Z1','AAACA1234A',NULL,1,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(2,1,'Vanguard Bar Bending & Steel Solutions','SUB-VANG','Sunil Patil (Site Incharge)','+91 98221 44556','patil@vanguardsteel.in','27AAACB5678B1Z2','AAACB5678B',NULL,1,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(3,1,'Shree Sai Logistics & Haulage Carriers','SUB-SAI','Manoj Kumar','+91 98212 77889','transport@shreesailogistics.com','27AAACC9012C1Z3','AAACC9012C',NULL,1,'2026-11-01 16:11:07','2026-11-01 16:11:07');
/*!40000 ALTER TABLE `subcontractors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_comments`
--

DROP TABLE IF EXISTS `task_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `task_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_comments_task_id_foreign` (`task_id`),
  KEY `task_comments_user_id_foreign` (`user_id`),
  KEY `task_comments_company_id_task_id_index` (`company_id`,`task_id`),
  CONSTRAINT `task_comments_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_comments_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_comments`
--

LOCK TABLES `task_comments` WRITE;
/*!40000 ALTER TABLE `task_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `priority` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'todo',
  `due_date` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `assigned_to` bigint unsigned DEFAULT NULL,
  `assigned_worker_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tasks_company_id_code_unique` (`company_id`,`code`),
  KEY `tasks_project_id_foreign` (`project_id`),
  KEY `tasks_site_id_foreign` (`site_id`),
  KEY `tasks_assigned_to_foreign` (`assigned_to`),
  KEY `tasks_assigned_worker_id_foreign` (`assigned_worker_id`),
  KEY `tasks_created_by_foreign` (`created_by`),
  KEY `tasks_comp_site_status_idx` (`company_id`,`site_id`,`status`),
  KEY `tasks_comp_proj_status_idx` (`company_id`,`project_id`,`status`),
  KEY `tasks_comp_assignee_status_idx` (`company_id`,`assigned_to`,`status`),
  CONSTRAINT `tasks_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_assigned_worker_id_foreign` FOREIGN KEY (`assigned_worker_id`) REFERENCES `workers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_site`
--

DROP TABLE IF EXISTS `user_site`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_site` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned NOT NULL,
  `role_in_site` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'supervisor',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_site_user_id_site_id_unique` (`user_id`,`site_id`),
  KEY `user_site_site_id_foreign` (`site_id`),
  CONSTRAINT `user_site_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_site_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_site`
--

LOCK TABLES `user_site` WRITE;
/*!40000 ALTER TABLE `user_site` DISABLE KEYS */;
INSERT INTO `user_site` VALUES (1,3,1,'supervisor','2026-11-01 16:11:07','2026-11-01 16:11:07'),(2,4,1,'supervisor','2026-11-01 16:11:07','2026-11-01 16:11:07'),(3,3,2,'supervisor','2026-11-01 16:11:07','2026-11-01 16:11:07'),(4,4,2,'supervisor','2026-11-01 16:11:07','2026-11-01 16:11:07'),(5,3,3,'supervisor','2026-11-01 16:11:07','2026-11-01 16:11:07'),(6,5,3,'supervisor','2026-11-01 16:11:07','2026-11-01 16:11:07');
/*!40000 ALTER TABLE `user_site` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `two_factor_secret` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_expires_at` timestamp NULL DEFAULT NULL,
  `two_factor_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Executive Director','admin@nsd.local',NULL,'$2y$12$OzmABHfJPbARPE9RAAPcLOF24O6KwVAKNDWRQo6N3yB7D.c8UXw6C',1,0,NULL,NULL,NULL,NULL,NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(2,'Vikram Singhania (Senior Operations VP)','senior.manager@nsd.local',NULL,'$2y$12$OzmABHfJPbARPE9RAAPcLOF24O6KwVAKNDWRQo6N3yB7D.c8UXw6C',1,0,NULL,NULL,NULL,NULL,NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(3,'Arun Deshmukh (Lead Site Manager)','site.manager@nsd.local',NULL,'$2y$12$OzmABHfJPbARPE9RAAPcLOF24O6KwVAKNDWRQo6N3yB7D.c8UXw6C',1,0,NULL,NULL,NULL,NULL,NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(4,'Ramesh Pawar (Site 1 Supervisor)','supervisor1@nsd.local',NULL,'$2y$12$OzmABHfJPbARPE9RAAPcLOF24O6KwVAKNDWRQo6N3yB7D.c8UXw6C',1,0,NULL,NULL,NULL,NULL,NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(5,'Suresh Shinde (Site 2 Supervisor)','supervisor2@nsd.local',NULL,'$2y$12$OzmABHfJPbARPE9RAAPcLOF24O6KwVAKNDWRQo6N3yB7D.c8UXw6C',1,0,NULL,NULL,NULL,NULL,NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(6,'Dinesh Kulkarni (Central Depot Keeper)','warehouse@nsd.local',NULL,'$2y$12$OzmABHfJPbARPE9RAAPcLOF24O6KwVAKNDWRQo6N3yB7D.c8UXw6C',1,0,NULL,NULL,NULL,NULL,NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(7,'Pooja Mehta (Compliance Officer)','office@nsd.local',NULL,'$2y$12$OzmABHfJPbARPE9RAAPcLOF24O6KwVAKNDWRQo6N3yB7D.c8UXw6C',1,0,NULL,NULL,NULL,NULL,NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warehouses`
--

DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'central_warehouse',
  `site_id` bigint unsigned DEFAULT NULL,
  `manager_id` bigint unsigned DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warehouses_company_id_code_unique` (`company_id`,`code`),
  KEY `warehouses_site_id_foreign` (`site_id`),
  KEY `warehouses_manager_id_foreign` (`manager_id`),
  CONSTRAINT `warehouses_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `warehouses_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warehouses_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warehouses`
--

LOCK TABLES `warehouses` WRITE;
/*!40000 ALTER TABLE `warehouses` DISABLE KEYS */;
INSERT INTO `warehouses` VALUES (1,1,'NSD Central Supply Depot & Logistics Yard','WH-CENTRAL','central_warehouse',NULL,6,'Highway Interchange Logistics Hub, Plot 14',1,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(2,1,'Site 1 Materials Yard & Fuel Bunker','WH-SITE-1','site_store',1,4,'Sector 1 km 27 Base Camp',1,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(3,1,'Site 2 Asphalt & Aggregate Stockpile','WH-SITE-2','site_store',2,4,'Sector 2 km 38 Batch Plant Camp',1,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(4,1,'Metro Station 4 Steel & Formwork Yard','WH-SITE-3','site_store',3,5,'Station 4 East Enclosure',1,'2026-11-01 16:11:07','2026-11-01 16:11:07');
/*!40000 ALTER TABLE `warehouses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_order_approvals`
--

DROP TABLE IF EXISTS `work_order_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_order_approvals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned NOT NULL,
  `stage` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `comments` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `work_order_approvals_work_order_id_foreign` (`work_order_id`),
  KEY `work_order_approvals_actor_id_foreign` (`actor_id`),
  CONSTRAINT `work_order_approvals_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `work_order_approvals_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_order_approvals`
--

LOCK TABLES `work_order_approvals` WRITE;
/*!40000 ALTER TABLE `work_order_approvals` DISABLE KEYS */;
INSERT INTO `work_order_approvals` VALUES (1,1,'site_manager','approved',3,'Verified with site surveys.','2026-11-01 16:11:07','2026-11-01 16:11:07'),(2,1,'senior_manager','approved',2,'Final authorization granted.','2026-11-01 16:11:07','2026-11-01 16:11:07'),(3,2,'site_manager','approved',3,'Recommended urgently.','2026-11-01 16:11:07','2026-11-01 16:11:07');
/*!40000 ALTER TABLE `work_order_approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_order_attachments`
--

DROP TABLE IF EXISTS `work_order_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_order_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `work_order_id` bigint unsigned NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint unsigned NOT NULL DEFAULT '0',
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `work_order_attachments_work_order_id_foreign` (`work_order_id`),
  KEY `work_order_attachments_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `work_order_attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `work_order_attachments_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_order_attachments`
--

LOCK TABLES `work_order_attachments` WRITE;
/*!40000 ALTER TABLE `work_order_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `work_order_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_order_items`
--

DROP TABLE IF EXISTS `work_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `work_order_id` bigint unsigned NOT NULL,
  `sr_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_code` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sac_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specification` text COLLATE utf8mb4_unicode_ci,
  `unit` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordered_quantity` decimal(14,3) NOT NULL DEFAULT '0.000',
  `rate` decimal(14,2) NOT NULL DEFAULT '0.00',
  `line_amount` decimal(16,2) NOT NULL DEFAULT '0.00',
  `completion_date` date DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `work_order_items_work_order_id_foreign` (`work_order_id`),
  KEY `wo_items_comp_wo_idx` (`company_id`,`work_order_id`),
  CONSTRAINT `work_order_items_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `work_order_items_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_order_items`
--

LOCK TABLES `work_order_items` WRITE;
/*!40000 ALTER TABLE `work_order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `work_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_order_revisions`
--

DROP TABLE IF EXISTS `work_order_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_order_revisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `work_order_id` bigint unsigned NOT NULL,
  `revision_number` int unsigned NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `previous_base_value` decimal(16,2) NOT NULL DEFAULT '0.00',
  `new_base_value` decimal(16,2) NOT NULL DEFAULT '0.00',
  `items_snapshot` json DEFAULT NULL,
  `proposed_items` json DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `changed_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wo_revisions_wo_rev_uniq` (`work_order_id`,`revision_number`),
  KEY `work_order_revisions_company_id_foreign` (`company_id`),
  KEY `work_order_revisions_changed_by_foreign` (`changed_by`),
  KEY `work_order_revisions_approved_by_foreign` (`approved_by`),
  CONSTRAINT `work_order_revisions_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_order_revisions_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_order_revisions_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `work_order_revisions_work_order_id_foreign` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_order_revisions`
--

LOCK TABLES `work_order_revisions` WRITE;
/*!40000 ALTER TABLE `work_order_revisions` DISABLE KEYS */;
/*!40000 ALTER TABLE `work_order_revisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_orders`
--

DROP TABLE IF EXISTS `work_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `work_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `site_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned DEFAULT NULL,
  `order_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'client_issued',
  `order_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `po_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `po_date` date DEFAULT NULL,
  `job_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_department_person` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `client_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contractor_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contractor_gst` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contractor_contact` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_terms` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `scope_of_work` text COLLATE utf8mb4_unicode_ci,
  `boq_items` json DEFAULT NULL,
  `terms_and_conditions` text COLLATE utf8mb4_unicode_ci,
  `estimated_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT '9.00',
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT '9.00',
  `retention_percent` decimal(5,2) NOT NULL DEFAULT '5.00',
  `current_revision` int unsigned NOT NULL DEFAULT '0',
  `base_order_value` decimal(16,2) NOT NULL DEFAULT '0.00',
  `target_start_date` date DEFAULT NULL,
  `target_completion_date` date DEFAULT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint unsigned NOT NULL,
  `site_manager_id` bigint unsigned DEFAULT NULL,
  `site_manager_approved_at` timestamp NULL DEFAULT NULL,
  `site_manager_notes` text COLLATE utf8mb4_unicode_ci,
  `senior_manager_id` bigint unsigned DEFAULT NULL,
  `senior_manager_approved_at` timestamp NULL DEFAULT NULL,
  `senior_manager_notes` text COLLATE utf8mb4_unicode_ci,
  `rejected_by` bigint unsigned DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `work_orders_company_id_order_number_unique` (`company_id`,`order_number`),
  KEY `work_orders_project_id_foreign` (`project_id`),
  KEY `work_orders_site_id_foreign` (`site_id`),
  KEY `work_orders_created_by_foreign` (`created_by`),
  KEY `work_orders_site_manager_id_foreign` (`site_manager_id`),
  KEY `work_orders_senior_manager_id_foreign` (`senior_manager_id`),
  KEY `work_orders_rejected_by_foreign` (`rejected_by`),
  KEY `work_orders_company_id_status_index` (`company_id`,`status`),
  KEY `work_orders_company_id_order_type_index` (`company_id`,`order_type`),
  KEY `work_orders_client_id_foreign` (`client_id`),
  CONSTRAINT `work_orders_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `work_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `work_orders_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `work_orders_rejected_by_foreign` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_senior_manager_id_foreign` FOREIGN KEY (`senior_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `work_orders_site_manager_id_foreign` FOREIGN KEY (`site_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_orders`
--

LOCK TABLES `work_orders` WRITE;
/*!40000 ALTER TABLE `work_orders` DISABLE KEYS */;
INSERT INTO `work_orders` VALUES (1,1,1,1,NULL,'client_issued','WO-NSD-2026-001',NULL,NULL,NULL,NULL,NULL,NULL,'NHAI-PKG-48-WO-889',NULL,NULL,NULL,NULL,'Rock Cutting & Trenching at km 26+400 Culvert Crossing','Excavate 12,000 cu.m rocky strata and construct reinforced bed for precast box culvert.','1. Excavation\\n2. Bed leveling\\n3. Culvert placement',NULL,NULL,450000.00,9.00,9.00,5.00,0,0.00,NULL,NULL,'senior_manager_approved',3,3,'2026-10-29 16:11:07','Verified with site surveys. Equipment allocated.',2,'2026-10-30 16:11:07','Approved for immediate execution under contract item 4.2.',NULL,NULL,NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(2,1,1,2,NULL,'internal','WO-NSD-2026-002',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Emergency Haul Road Grading & Rain Slag Spreading','Strengthen 3.5km detour haul route to prevent dumper skidding during pre-monsoon showers.','Grading and aggregate spreading',NULL,NULL,85000.00,9.00,9.00,5.00,0,0.00,NULL,NULL,'site_manager_approved',3,3,'2026-11-01 04:11:07','Recommended urgently to keep dumpers operational.',NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07'),(3,1,2,3,NULL,'client_issued','WO-NSD-2026-003',NULL,NULL,NULL,NULL,NULL,NULL,'MRDC-METRO-PKG3-042',NULL,NULL,NULL,NULL,'Pier Foundation Pile Cap Reinforcement Station 4','Binding 48 MT TMT Fe500D steel rebar for Pier 14 through Pier 18.','TMT bar cutting, bending and tied cage installation',NULL,NULL,2800000.00,9.00,9.00,5.00,0,0.00,NULL,NULL,'submitted',3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-11-01 16:11:07','2026-11-01 16:11:07');
/*!40000 ALTER TABLE `work_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `worker_documents`
--

DROP TABLE IF EXISTS `worker_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `worker_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `worker_id` bigint unsigned NOT NULL,
  `document_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `storage_disk` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint unsigned NOT NULL DEFAULT '0',
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `worker_documents_uploaded_by_foreign` (`uploaded_by`),
  KEY `worker_documents_company_id_worker_id_index` (`company_id`,`worker_id`),
  KEY `worker_documents_worker_id_document_type_index` (`worker_id`,`document_type`),
  KEY `worker_docs_company_expiry_idx` (`company_id`,`expiry_date`),
  CONSTRAINT `worker_documents_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `worker_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `worker_documents_worker_id_foreign` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `worker_documents`
--

LOCK TABLES `worker_documents` WRITE;
/*!40000 ALTER TABLE `worker_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `worker_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `worker_sequences`
--

DROP TABLE IF EXISTS `worker_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `worker_sequences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `prefix` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `year` smallint unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `worker_seq_comp_prefix_year_unique` (`company_id`,`prefix`,`year`),
  CONSTRAINT `worker_sequences_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `worker_sequences`
--

LOCK TABLES `worker_sequences` WRITE;
/*!40000 ALTER TABLE `worker_sequences` DISABLE KEYS */;
/*!40000 ALTER TABLE `worker_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workers`
--

DROP TABLE IF EXISTS `workers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `worker_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'employee',
  `subcontractor_id` bigint unsigned DEFAULT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alternate_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `aadhaar_last_four` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trade_designation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `skill_level` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_site_id` bigint unsigned DEFAULT NULL,
  `pay_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'daily',
  `daily_rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `monthly_salary` decimal(12,2) DEFAULT '0.00',
  `hourly_rate` decimal(10,2) DEFAULT '0.00',
  `contract_rate` decimal(12,2) DEFAULT '0.00',
  `overtime_rate` decimal(10,2) DEFAULT '0.00',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `joining_date` date DEFAULT NULL,
  `exit_date` date DEFAULT NULL,
  `emergency_contact_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_relation` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_ifsc` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upi_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `must_change_pin` tinyint(1) NOT NULL DEFAULT '1',
  `qr_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workers_company_id_code_unique` (`company_id`,`code`),
  UNIQUE KEY `workers_qr_token_unique` (`qr_token`),
  KEY `workers_subcontractor_id_foreign` (`subcontractor_id`),
  KEY `workers_assigned_site_id_foreign` (`assigned_site_id`),
  KEY `workers_company_id_worker_type_index` (`company_id`,`worker_type`),
  KEY `workers_company_id_assigned_site_id_index` (`company_id`,`assigned_site_id`),
  CONSTRAINT `workers_assigned_site_id_foreign` FOREIGN KEY (`assigned_site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `workers_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `workers_subcontractor_id_foreign` FOREIGN KEY (`subcontractor_id`) REFERENCES `subcontractors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workers`
--

LOCK TABLES `workers` WRITE;
/*!40000 ALTER TABLE `workers` DISABLE KEYS */;
INSERT INTO `workers` VALUES (1,1,'employee',NULL,'W-EMP-001','Anand','Kulkarni',NULL,NULL,'+91 91111 00001',NULL,NULL,NULL,NULL,'Senior Excavator Operator',NULL,NULL,1,'daily',1200.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'WXkBE2TXLUMd7IAoYI2MLwf5Vy28XGdrGU0p14CA','2026-11-01 16:11:07','2026-11-01 16:11:07'),(2,1,'employee',NULL,'W-EMP-002','Ganesh','Jadhav',NULL,NULL,'+91 91111 00002',NULL,NULL,NULL,NULL,'Bulldozer / Motor Grader Pilot',NULL,NULL,1,'daily',1100.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'kC7L7pwNA8MhPcloHDXNUaxnopeY7c15Dmvg9cCY','2026-11-01 16:11:07','2026-11-01 16:11:07'),(3,1,'employee',NULL,'W-EMP-003','Mahesh','Chavan',NULL,NULL,'+91 91111 00003',NULL,NULL,NULL,NULL,'Surveyor & Level Instrument Tech',NULL,NULL,2,'daily',1400.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'SkgbF7viV9UmxuXpd8otbi7s4CsfUKaR2NC8VIlr','2026-11-01 16:11:07','2026-11-01 16:11:07'),(4,1,'employee',NULL,'W-EMP-004','Nilesh','More',NULL,NULL,'+91 91111 00004',NULL,NULL,NULL,NULL,'Mechanical Master Mechanic',NULL,NULL,1,'daily',1300.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'ZALc0gkOJdT5iyxDg7LWKZj9NbfAEvV6voSxls0N','2026-11-01 16:11:07','2026-11-01 16:11:07'),(5,1,'employee',NULL,'W-EMP-005','Santosh','Gaikwad',NULL,NULL,'+91 91111 00005',NULL,NULL,NULL,NULL,'Transit Mixer Operator',NULL,NULL,3,'daily',1050.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'wcSpA1wLcPE5mVyQN296N3B6E2AIDStROFMEvuIj','2026-11-01 16:11:07','2026-11-01 16:11:07'),(6,1,'direct_labour',NULL,'W-LAB-101','Ramu','Kashyap',NULL,NULL,'+91 92222 00001',NULL,NULL,NULL,NULL,'Earth Trenching & Levelling',NULL,NULL,1,'daily',650.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'JtnjWxH9h2V2C21PG6iBR1UKgIhaG0t64CtViAeU','2026-11-01 16:11:07','2026-11-01 16:11:07'),(7,1,'direct_labour',NULL,'W-LAB-102','Babu','Munda',NULL,NULL,'+91 92222 00002',NULL,NULL,NULL,NULL,'Compaction Assistant',NULL,NULL,1,'daily',650.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'Z6iJ5rriBAI1Gsg531ir7OjYSjFUD4ciSo8ZqeWJ','2026-11-01 16:11:07','2026-11-01 16:11:07'),(8,1,'direct_labour',NULL,'W-LAB-103','Chhotu','Yadav',NULL,NULL,'+91 92222 00003',NULL,NULL,NULL,NULL,'General Masonry Helper',NULL,NULL,2,'daily',700.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'E7whZBedVuosK6wmbByYeMKCbvwtiOOnkdk9Q448','2026-11-01 16:11:07','2026-11-01 16:11:07'),(9,1,'direct_labour',NULL,'W-LAB-104','Dinesh','Majhi',NULL,NULL,'+91 92222 00004',NULL,NULL,NULL,NULL,'Drainage Channel Digger',NULL,NULL,2,'daily',650.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'Nn3yi9jmTBQ6ppwrmdjNgc0AFxZ2sij9TG52g7ya','2026-11-01 16:11:07','2026-11-01 16:11:07'),(10,1,'direct_labour',NULL,'W-LAB-105','Somnath','Tudu',NULL,NULL,'+91 92222 00005',NULL,NULL,NULL,NULL,'Concrete Pouring Crew',NULL,NULL,3,'daily',750.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'fIOjgXurJo9njkJNrzjVRB1UPTKKdZCbJFaKRKlp','2026-11-01 16:11:07','2026-11-01 16:11:07'),(11,1,'subcontractor_worker',1,'W-SUB-201','Vijay','Verma',NULL,NULL,'+91 93333 00001',NULL,NULL,NULL,NULL,'Hydraulic Breaker Operator',NULL,NULL,1,'daily',950.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'yozxrv4lWWJsiGDOEGwiJX5OXEuF6888nKvew5Bc','2026-11-01 16:11:07','2026-11-01 16:11:07'),(12,1,'subcontractor_worker',1,'W-SUB-202','Amit','Tiwari',NULL,NULL,'+91 93333 00002',NULL,NULL,NULL,NULL,'JCB Excavator Specialist',NULL,NULL,1,'daily',950.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'f15HOvH2NqlZp6jOk3LiMiDYEcWuPY3KR6LNXxpV','2026-11-01 16:11:07','2026-11-01 16:11:07'),(13,1,'subcontractor_worker',1,'W-SUB-203','Pradeep','Shukla',NULL,NULL,'+91 93333 00003',NULL,NULL,NULL,NULL,'Rock Drill Technician',NULL,NULL,2,'daily',900.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'AF8ySUz4avCW68LFery1Ej6bcpc7gkjsQevr83Cm','2026-11-01 16:11:07','2026-11-01 16:11:07'),(14,1,'subcontractor_worker',2,'W-SUB-301','Mohan','Suthar',NULL,NULL,'+91 94444 00001',NULL,NULL,NULL,NULL,'Master Bar Bender & Cutter',NULL,NULL,3,'daily',900.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'M4sO8QxVfCDjtdTU4j0iBGLiOWW2fW4ZH0WbDOR2','2026-11-01 16:11:07','2026-11-01 16:11:07'),(15,1,'subcontractor_worker',2,'W-SUB-302','Kishore','Panchal',NULL,NULL,'+91 94444 00002',NULL,NULL,NULL,NULL,'Pillar Formwork Carpenter',NULL,NULL,3,'daily',850.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'Ofqe9A4Sadva3fDx5FWrE92jJ0YXuBADzliJeADs','2026-11-01 16:11:07','2026-11-01 16:11:07'),(16,1,'subcontractor_worker',2,'W-SUB-303','Ratan','Lohar',NULL,NULL,'+91 94444 00003',NULL,NULL,NULL,NULL,'Tying Wire Specialist',NULL,NULL,3,'daily',800.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'k9GJrBqQzXGNj4M3RJzKUezwu0pjyPbLrZg3bcuC','2026-11-01 16:11:07','2026-11-01 16:11:07'),(17,1,'subcontractor_worker',3,'W-SUB-401','Gurmeet','Singh',NULL,NULL,'+91 95555 00001',NULL,NULL,NULL,NULL,'10-Wheel Tipper Driver',NULL,NULL,1,'daily',900.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'ctFzLj3zRPWIllc34SL0cGRq0poXO4x150OKxYmg','2026-11-01 16:11:07','2026-11-01 16:11:07'),(18,1,'subcontractor_worker',3,'W-SUB-402','Balwinder','Singh',NULL,NULL,'+91 95555 00002',NULL,NULL,NULL,NULL,'Heavy Dumper Operator',NULL,NULL,2,'daily',900.00,0.00,0.00,0.00,0.00,'active','2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'dQsb5jmr0PfJbOg1wZH77AY8VyjCeOP2pioscDkZ','2026-11-01 16:11:07','2026-11-01 16:11:07');
/*!40000 ALTER TABLE `workers` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-01 21:41:07
SET FOREIGN_KEY_CHECKS = 1;
