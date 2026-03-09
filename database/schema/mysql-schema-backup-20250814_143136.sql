/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `accounting_softwares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounting_softwares` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `causer_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `batch_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `add_on_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `add_on_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `rack_price` varchar(255) NOT NULL,
  `rack_price_type` varchar(255) NOT NULL,
  `discount_type` varchar(255) NOT NULL,
  `discount_price` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `additional_custom_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `additional_custom_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `configuration_id` bigint unsigned NOT NULL,
  `field_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_required` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribute_option` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `attribute_option_rating` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `height_value` int DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `scored` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `additional_info_for_employee_to_get_started`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `additional_info_for_employee_to_get_started` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `configuration_id` bigint unsigned NOT NULL,
  `field_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_required` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribute_option` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `additional_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `additional_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `effective_date` date DEFAULT NULL,
  `effective_end_date` date DEFAULT NULL,
  `updater_id` int unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `state_id` bigint unsigned DEFAULT NULL,
  `city_id` bigint unsigned DEFAULT NULL,
  `overrides_amount` double(8,2) NOT NULL,
  `overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'per kw',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `archived_at` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `additional_pay_frequencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `additional_pay_frequencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `closed_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Open, 1 = Closed',
  `open_status_from_bank` tinyint DEFAULT '0',
  `w2_closed_status` tinyint NOT NULL DEFAULT '0',
  `w2_open_status_from_bank` tinyint NOT NULL DEFAULT '0',
  `type` tinyint NOT NULL COMMENT '1 = Bi Weekly, 2 = Semi Monthly',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `additional_recruters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `additional_recruters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `hiring_id` bigint unsigned DEFAULT NULL,
  `recruiter_id` int DEFAULT NULL,
  `system_per_kw_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `additional_recruters_user_id_foreign` (`user_id`),
  CONSTRAINT `additional_recruters_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adjustement_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adjustement_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `adwance_payment_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adwance_payment_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adwance_setting` enum('automatic','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'automatic',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `announcements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `positions` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `durations` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `end_date` date DEFAULT NULL,
  `file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pin_to_top` int NOT NULL,
  `disable` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approval_and_request_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approval_and_request_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approvals_and_request_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approvals_and_request_status` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approvals_and_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approvals_and_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int DEFAULT NULL,
  `payroll_id` int NOT NULL DEFAULT '0' COMMENT 'payroll table id',
  `employee_payroll_id` bigint DEFAULT NULL,
  `req_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `manager_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `adjustment_type_id` bigint unsigned DEFAULT NULL,
  `pay_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` bigint unsigned DEFAULT NULL,
  `dispute_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cost_tracking_id` bigint unsigned DEFAULT NULL,
  `emi` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_date` datetime DEFAULT NULL,
  `txn_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_date` date DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `adjustment_date` date DEFAULT NULL,
  `pto_hours_perday` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `lunch_adjustment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `break_adjustment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `declined_by` bigint DEFAULT NULL,
  `declined_at` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_mark_paid` tinyint(1) NOT NULL DEFAULT '0',
  `is_next_payroll` tinyint(1) NOT NULL DEFAULT '0',
  `ref_id` int DEFAULT '0',
  `pto_per_day` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_adjustment_date` date DEFAULT NULL,
  `lunch` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `break` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_item_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Old, 1 = In Action Item',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `approvals_and_request_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approvals_and_requests_lock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approvals_and_requests_lock` (
  `id` bigint unsigned NOT NULL,
  `payroll_id` int NOT NULL DEFAULT '0' COMMENT 'payroll table id',
  `req_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `manager_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `adjustment_type_id` bigint unsigned DEFAULT NULL,
  `pay_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` bigint unsigned DEFAULT NULL,
  `dispute_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cost_tracking_id` bigint unsigned DEFAULT NULL,
  `emi` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_date` datetime DEFAULT NULL,
  `txn_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_date` date DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `declined_at` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_mark_paid` tinyint(1) NOT NULL DEFAULT '0',
  `is_next_payroll` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  `action_item_status` tinyint NOT NULL DEFAULT '0',
  `adjustment_date` date DEFAULT NULL,
  `break` varchar(255) DEFAULT NULL,
  `break_adjustment` varchar(255) DEFAULT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `declined_by` bigint DEFAULT NULL,
  `employee_payroll_id` bigint DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `lunch` varchar(255) DEFAULT NULL,
  `lunch_adjustment` varchar(255) DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `pto_hours_perday` varchar(255) DEFAULT NULL,
  `pto_per_day` varchar(255) DEFAULT NULL,
  `ref_id` int DEFAULT '0',
  `start_date` date DEFAULT NULL,
  `time_adjustment_date` date DEFAULT NULL,
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_group` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_group_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_group_permissions` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `group_id` int NOT NULL,
  `permission_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `auth_group_permissions_group_id_permission_id_0cd325b0_uniq` (`group_id`,`permission_id`),
  KEY `auth_group_permissio_permission_id_84c5c92e_fk_auth_perm` (`permission_id`),
  CONSTRAINT `auth_group_permissio_permission_id_84c5c92e_fk_auth_perm` FOREIGN KEY (`permission_id`) REFERENCES `auth_permission` (`id`),
  CONSTRAINT `auth_group_permissions_group_id_b120cbf9_fk_auth_group_id` FOREIGN KEY (`group_id`) REFERENCES `auth_group` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_permission` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_type_id` int NOT NULL,
  `codename` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `auth_permission_content_type_id_codename_01ab375a_uniq` (`content_type_id`,`codename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `password` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_login` datetime(6) DEFAULT NULL,
  `is_superuser` tinyint(1) NOT NULL,
  `username` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_staff` tinyint(1) NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `date_joined` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_user_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_user_groups` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `group_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `auth_user_groups_user_id_group_id_94350c0c_uniq` (`user_id`,`group_id`),
  KEY `auth_user_groups_group_id_97559544_fk_auth_group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `auth_user_user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_user_user_permissions` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `permission_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `auth_user_user_permissions_user_id_permission_id_14a6b632_uniq` (`user_id`,`permission_id`),
  KEY `auth_user_user_permi_permission_id_1fbb5f2c_fk_auth_perm` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `automation_action_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `automation_action_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `automation_rule_id` bigint unsigned DEFAULT NULL,
  `lead_id` bigint unsigned DEFAULT NULL,
  `onboarding_id` bigint unsigned DEFAULT NULL,
  `sub_task_id` bigint unsigned DEFAULT NULL,
  `old_pipeline_lead_status` bigint unsigned DEFAULT NULL,
  `new_pipeline_lead_status` bigint unsigned DEFAULT NULL,
  `event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `trace_log` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `automation_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `automation_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `automation_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rule` json DEFAULT NULL,
  `status` int NOT NULL DEFAULT '1',
  `user_id` int NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `backend_reconciliations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `backend_reconciliations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `day_date` date DEFAULT NULL,
  `backend_setting_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `backend_reconciliations_backend_setting_id_foreign` (`backend_setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `backend_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `backend_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `commission_withheld` double(8,2) DEFAULT NULL,
  `maximum_withheld` double(8,2) DEFAULT NULL,
  `commission_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `billing_frequency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_frequency` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `billing_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `billing_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bucket_by_job`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bucket_by_job` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bucket_id` bigint unsigned NOT NULL,
  `job_id` bigint unsigned NOT NULL,
  `active` tinyint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bucket_by_job_bucket_id_foreign` (`bucket_id`),
  KEY `bucket_by_job_job_id_foreign` (`job_id`),
  CONSTRAINT `bucket_by_job_bucket_id_foreign` FOREIGN KEY (`bucket_id`) REFERENCES `buckets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bucket_by_job_job_id_foreign` FOREIGN KEY (`job_id`) REFERENCES `crm_sale_info` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bucket_subtask`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bucket_subtask` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bucket_id` bigint unsigned NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bucket_subtask_bucket_id_foreign` (`bucket_id`),
  CONSTRAINT `bucket_subtask_bucket_id_foreign` FOREIGN KEY (`bucket_id`) REFERENCES `buckets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bucket_subtask_by_job`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bucket_subtask_by_job` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bucket_sutask_id` bigint unsigned NOT NULL,
  `job_id` bigint unsigned NOT NULL,
  `status` tinyint NOT NULL,
  `date` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bucket_subtask_by_job_bucket_sutask_id_foreign` (`bucket_sutask_id`),
  KEY `bucket_subtask_by_job_job_id_foreign` (`job_id`),
  CONSTRAINT `bucket_subtask_by_job_bucket_sutask_id_foreign` FOREIGN KEY (`bucket_sutask_id`) REFERENCES `bucket_subtask` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bucket_subtask_by_job_job_id_foreign` FOREIGN KEY (`job_id`) REFERENCES `crm_sale_info` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `buckets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `buckets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bucket_type` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` tinyint NOT NULL,
  `hide_status` tinyint NOT NULL,
  `colour_code` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `warning_day` int NOT NULL,
  `danger_day` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `business_address`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `business_address` (
  `id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_ein` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_phone` int DEFAULT NULL,
  `business_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `business_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_ein` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_zone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `state_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cities_state_id_foreign` (`state_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clawback_settlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clawback_settlements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` int NOT NULL DEFAULT '0' COMMENT 'payroll table id',
  `user_id` bigint unsigned NOT NULL,
  `position_id` int NOT NULL,
  `milestone_schema_id` bigint unsigned DEFAULT NULL,
  `sale_user_id` int DEFAULT NULL,
  `product_id` bigint DEFAULT NULL,
  `clawback_amount` double(8,2) DEFAULT NULL,
  `clawback_type` enum('reconciliation','next payroll','m2 update') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `recon_status` tinyint NOT NULL DEFAULT '1' COMMENT '1 = Unpaid, 2 = Partially Paid, 3 = Fully Paid',
  `is_last` tinyint NOT NULL DEFAULT '0' COMMENT 'Default 0, 1 = When last date hits',
  `action_status` tinyint NOT NULL DEFAULT '0',
  `type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'commission',
  `adders_type` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_trigger` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `during` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'm2' COMMENT 'm2, m2 update',
  `redline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Fixed, Shift Based on Location, Shift Based on Product, Shift Based on Product & Location',
  `is_mark_paid` tinyint(1) NOT NULL DEFAULT '0',
  `is_next_payroll` tinyint(1) NOT NULL DEFAULT '0',
  `is_stop_payroll` tinyint NOT NULL DEFAULT '0',
  `is_displayed` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `clawback_status` tinyint NOT NULL DEFAULT '0',
  `ref_id` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_move_to_recon` tinyint DEFAULT '0',
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  `clawback_cal_amount` double(8,2) DEFAULT NULL,
  `clawback_cal_type` enum('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clawback_settlement_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clawback_settlements_lock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clawback_settlements_lock` (
  `id` bigint unsigned NOT NULL,
  `payroll_id` int NOT NULL DEFAULT '0' COMMENT 'payroll table id',
  `user_id` bigint unsigned NOT NULL,
  `position_id` int NOT NULL,
  `milestone_schema_id` bigint unsigned DEFAULT NULL,
  `sale_user_id` int DEFAULT NULL,
  `product_id` bigint DEFAULT NULL,
  `clawback_amount` double(8,2) DEFAULT NULL,
  `clawback_type` enum('reconciliation','next payroll','m2 update') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_code` varchar(255) DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `recon_status` tinyint NOT NULL DEFAULT '1' COMMENT '1 = Unpaid, 2 = Partially Paid, 3 = Fully Paid',
  `is_last` tinyint NOT NULL DEFAULT '0' COMMENT 'Default 0, 1 = When last date hits',
  `action_status` tinyint NOT NULL DEFAULT '0',
  `type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'commission',
  `adders_type` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_type` varchar(255) DEFAULT NULL,
  `schema_name` varchar(255) DEFAULT NULL,
  `schema_trigger` varchar(255) DEFAULT NULL,
  `during` varchar(255) DEFAULT 'm2' COMMENT 'm2, m2 update',
  `redline` varchar(255) DEFAULT NULL,
  `redline_type` varchar(255) DEFAULT NULL COMMENT 'Fixed, Shift Based on Location, Shift Based on Product, Shift Based on Product & Location',
  `is_mark_paid` tinyint(1) NOT NULL DEFAULT '0',
  `is_next_payroll` tinyint(1) NOT NULL DEFAULT '0',
  `is_stop_payroll` tinyint NOT NULL DEFAULT '0',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_move_to_recon` tinyint DEFAULT '0',
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  `is_displayed` enum('0','1') NOT NULL DEFAULT '1',
  `clawback_status` tinyint NOT NULL DEFAULT '0',
  `ref_id` int DEFAULT '0',
  `clawback_cal_amount` double(8,2) DEFAULT NULL,
  `clawback_cal_type` enum('percent','per kw','per sale') DEFAULT NULL,
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `closer_identify_alert`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `closer_identify_alert` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_rep_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_billing_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_billing_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_ein` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `business_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `business_address_1` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address_2` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address_time_zone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_lat` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_long` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_payrolls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_payrolls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `frequency_type_id` bigint unsigned NOT NULL,
  `first_months` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_day` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `day_of_week` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `day_of_months` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_per_days` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_day_pay_of_manths` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `second_pay_day_of_month` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deadline_to_run_payroll` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_pay_period_ends_on` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_payrolls_frequency_type_id_foreign` (`frequency_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `zeal_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Pest',
  `company_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frequency_type_id` tinyint(1) NOT NULL DEFAULT '1',
  `mailing_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_ein` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `business_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_zone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address_1` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address_2` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_lat` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_long` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_address_1` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_address_2` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_lat` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_long` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address_time_zone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_address_time_zone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_margin` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `excel_sample_file_path` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'excel_import/import_format.xlsx',
  `stripe_customer_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_autopayment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `fixed_amount` decimal(8,2) DEFAULT NULL,
  `is_flat` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deduct_any_available_reconciliation_upfront` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_profiles_company_email_unique` (`company_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan_id` bigint unsigned NOT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_requests_plan_id_foreign` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `company_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cost_centers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cost_centers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `credits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `credits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `amount` decimal(8,2) DEFAULT '0.00',
  `old_balance_credit` decimal(8,2) DEFAULT '0.00',
  `used_credit` decimal(8,2) DEFAULT '0.00',
  `balance_credit` decimal(8,2) DEFAULT '0.00',
  `month` date DEFAULT NULL,
  `use_status` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `job_id` int NOT NULL,
  `bucket_id` int NOT NULL,
  `comments_id` int NOT NULL,
  `mask` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `path_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `path` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `job_id` int NOT NULL,
  `bucket_id` int NOT NULL,
  `comments_parent_id` int NOT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_sale_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_sale_info` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_id` int NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_fields` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancel_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crm_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_setting` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `crm_id` bigint unsigned DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` tinyint NOT NULL DEFAULT '0',
  `plan_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_per_job` decimal(8,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_setting_crm_id_foreign` (`crm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `crmsale_custom_field`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crmsale_custom_field` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `visiblecustomer` int NOT NULL,
  `status` int NOT NULL,
  `sort_order` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `custom_field`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `custom_field` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `payroll_id` int DEFAULT NULL,
  `column_id` int DEFAULT NULL,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approved_by` int DEFAULT NULL,
  `is_next_payroll` tinyint NOT NULL DEFAULT '0',
  `is_mark_paid` tinyint NOT NULL DEFAULT '0',
  `ref_id` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `custom_field_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `custom_field_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `payroll_id` int DEFAULT NULL,
  `column_id` int DEFAULT NULL,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approved_by` int DEFAULT NULL,
  `ref_id` int DEFAULT '0',
  `is_mark_paid` tinyint NOT NULL DEFAULT '0',
  `is_next_payroll` tinyint NOT NULL DEFAULT '0',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_onetime_payment` tinyint NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `custom_lead_form_global_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `custom_lead_form_global_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rating_status` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `customer_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_payment_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `daily_pay_frequencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_pay_frequencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `closed_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `open_status_from_bank` int DEFAULT '0',
  `w2_closed_status` tinyint NOT NULL DEFAULT '0',
  `w2_open_status_from_bank` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `deduction_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deduction_alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `position_id` bigint unsigned DEFAULT NULL,
  `amount` double(11,3) DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_status` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `verify_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `devices_user_id_foreign` (`user_id`),
  CONSTRAINT `devices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `digisigner_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `digisigner_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `django_admin_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `django_admin_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `action_time` datetime(6) NOT NULL,
  `object_id` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `object_repr` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_flag` smallint unsigned NOT NULL,
  `change_message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_type_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `django_admin_log_content_type_id_c4bce8eb_fk_django_co` (`content_type_id`),
  KEY `django_admin_log_user_id_c564eba6_fk_auth_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `django_content_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `django_content_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `app_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `django_content_type_app_label_model_76bd3d3b_uniq` (`app_label`,`model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `django_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `django_migrations` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `app` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `applied` datetime(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `doc_history_for_templete`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doc_history_for_templete` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint DEFAULT NULL,
  `template_id` bigint DEFAULT NULL,
  `employee_name` varchar(255) DEFAULT NULL,
  `employee_position` varchar(255) DEFAULT NULL,
  `Company_Name` varchar(255) DEFAULT NULL,
  `manager_name` varchar(255) DEFAULT NULL,
  `currentdate` varchar(255) DEFAULT NULL,
  `building_no` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `pdf` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint unsigned DEFAULT NULL,
  `signature_request_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signed_document_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signed_status` tinyint(1) DEFAULT '0' COMMENT '0=not signed,1=signed',
  `document` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'doc before sign',
  `signed_document` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'doc after sign',
  `signature_request_id_for_callback` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_signers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_signers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `envelope_document_id` bigint DEFAULT NULL,
  `consent` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `signer_type` tinyint DEFAULT '0',
  `signer_sequence` int NOT NULL DEFAULT '0',
  `signer_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_role` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signer_plain_password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_to_upload`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_to_upload` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `configuration_id` bigint unsigned NOT NULL,
  `field_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_required` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `configuration_id` bigint NOT NULL,
  `field_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_required` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_id_from` enum('users','onboarding_employees') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'users',
  `send_by` int DEFAULT NULL COMMENT 'Document send by user id',
  `document_send_date` date DEFAULT NULL,
  `is_active` tinyint NOT NULL DEFAULT '1' COMMENT '0 not active , 1 for active doc',
  `document_type_id` bigint unsigned DEFAULT NULL COMMENT 'template id',
  `template_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `signature_request_document_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'digisigner document id for other document like W9 and etc.',
  `document_response_status` int DEFAULT '0' COMMENT '0 for no action',
  `user_request_change_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `document_uploaded_type` enum('manual_doc','secui_doc_uploaded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_item_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Old, 1 = In Action Item',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_exported` tinyint(1) NOT NULL DEFAULT '0',
  `data_exported_through` enum('Through API','Through Model Event') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `domain_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `domain_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `domain_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '0',
  `email_setting_type` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_configuration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_configuration` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email_from_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_from_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `host_mailer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `host_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_port` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timeout` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `security_protocol` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `authentication_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_app_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `token_app_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint unsigned DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_details_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_logins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_logins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `updated_by_id` int DEFAULT NULL,
  `descriptions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_notification_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_notification_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0',
  `email_setting_type` tinyint DEFAULT '0' COMMENT '1 for allow all domains, 0 for not llow all domains',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `emp_payroll_processing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `emp_payroll_processing` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overrides` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reimbursement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deductions` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reconciliation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adjustment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `net_pay` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `emp_payroll_processing_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_bankings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_bankings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `bank_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `routing_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acconut_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acconut_type` enum('Savings account','Salary account') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_bankings_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_id_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_id_setting` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prefix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_code_no_to_start_from` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onbording_id_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onbording_id_code_no_to_start_from` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onbording_prefix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `select_offer_letter_to_send` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `select_agreement_to_sign` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `automatic_hiring_status` int NOT NULL DEFAULT '0',
  `approval_onboarding_position` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `require_approval_status` tinyint NOT NULL DEFAULT '0',
  `special_approval_status` tinyint NOT NULL DEFAULT '0',
  `approval_position` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_personal_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_personal_detail` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `configuration_id` bigint unsigned NOT NULL,
  `field_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field_required` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribute_option` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `height_value` int DEFAULT NULL,
  `is_deleted` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_positions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_tax_infos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_tax_infos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `ssn` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `filling_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_tax_infos_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `encryption_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `encryption_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `column_name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `methodName` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `old_value_encrypt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `old_value_decrypt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `new_value_encrypt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `new_value_decrypt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `envelope_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `envelope_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `envelope_id` bigint unsigned NOT NULL,
  `is_mandatory` tinyint DEFAULT '1',
  `upload_by_user` tinyint DEFAULT '0',
  `status` tinyint DEFAULT '0',
  `pdf_storage_type` tinyint DEFAULT '0',
  `initial_pdf_path` varchar(255) DEFAULT NULL,
  `processed_pdf_path` varchar(255) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_pdf` tinyint(1) DEFAULT '0',
  `pdf_file_other_parameter` json DEFAULT NULL,
  `is_sign_required_for_hire` tinyint(1) DEFAULT '0',
  `template_name` varchar(255) DEFAULT NULL,
  `is_post_hiring_document` tinyint(1) DEFAULT '0',
  `pdf_pages_as_image` json DEFAULT NULL,
  `template_category_id` int unsigned DEFAULT NULL,
  `template_category_name` varchar(255) DEFAULT NULL,
  `template_category_type` varchar(255) DEFAULT NULL,
  `document_expiry` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_envelope_documents_envelope_id` (`envelope_id`),
  CONSTRAINT `fk_envelope_documents_envelope_id` FOREIGN KEY (`envelope_id`) REFERENCES `envelopes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `envelopes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `envelopes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `envelope_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plain_password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `envelopes_password_unique` (`password`),
  UNIQUE KEY `plain_password` (`plain_password`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_calendars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_calendars` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_date` date DEFAULT NULL,
  `event_time` varchar(225) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `event_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('Meeting','Interview','Career Fair','Company Event','Training','Hired') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_id` bigint unsigned DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `everee_transections_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `everee_transections_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `api_name` varchar(100) DEFAULT NULL,
  `payload` text,
  `response` text,
  `api_url` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `excel_import_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `excel_import_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `uploaded_file` varchar(255) NOT NULL,
  `new_records` int NOT NULL,
  `updated_records` int NOT NULL,
  `error_records` int DEFAULT '0',
  `total_records` int NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Success, 1 = In-Progress, 2 = Failed',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fieldroute_transaction_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fieldroute_transaction_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `ticket_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_url` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_processed` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fine_fees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fine_fees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned DEFAULT NULL,
  `type` enum('Fine','Fee','Bonuses','Incentive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fr_employee_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fr_employee_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `office_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sequifi_id` bigint unsigned DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `fname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `initials` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nickname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `experience` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linked_employee_ids` json DEFAULT NULL,
  `employee_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supervisor_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `roaming_rep` tinyint(1) NOT NULL DEFAULT '0',
  `regional_manager_office_ids` json DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `team_ids` json DEFAULT NULL,
  `primary_team` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_control_profile_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_lat` decimal(10,6) DEFAULT NULL,
  `start_lng` decimal(10,6) DEFAULT NULL,
  `end_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_lat` decimal(10,6) DEFAULT NULL,
  `end_lng` decimal(10,6) DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hire_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `termination_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `roles` json DEFAULT NULL,
  `permissions` json DEFAULT NULL,
  `additional_data` json DEFAULT NULL,
  `two_factor_required` tinyint(1) NOT NULL DEFAULT '0',
  `two_factor_config_due_date` timestamp NULL DEFAULT NULL,
  `skills` json DEFAULT NULL,
  `access_control` json DEFAULT NULL,
  `access_control_profile_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_control_profile_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_updated` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `frequency_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `frequency_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `get_payroll_data`;
/*!50001 DROP VIEW IF EXISTS `get_payroll_data`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `get_payroll_data` AS SELECT 
 1 AS `id`,
 1 AS `user_id`,
 1 AS `position_id`,
 1 AS `commission`,
 1 AS `override`,
 1 AS `reimbursement`,
 1 AS `clawback`,
 1 AS `deduction`,
 1 AS `adjustment`,
 1 AS `reconciliation`,
 1 AS `net_pay`,
 1 AS `pay_period_from`,
 1 AS `pay_period_to`,
 1 AS `status`*/;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `group_masters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_masters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `group_policies_id` bigint unsigned NOT NULL,
  `policies_tabs_id` bigint unsigned NOT NULL,
  `permissions_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `group_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint unsigned NOT NULL,
  `policies` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_policies_role_id_foreign` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gusto_companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gusto_companies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_by_user` bigint unsigned NOT NULL,
  `company_uuid` varchar(255) NOT NULL,
  `user_first_name` varchar(255) NOT NULL,
  `user_last_name` varchar(255) DEFAULT NULL,
  `user_email` varchar(255) DEFAULT NULL,
  `company_name` varchar(255) NOT NULL,
  `company_ein` int unsigned NOT NULL,
  `access_token` varchar(255) DEFAULT NULL,
  `refresh_token` varchar(255) DEFAULT NULL,
  `bank_uuid` varchar(255) DEFAULT NULL,
  `bank_routing_number` varchar(255) DEFAULT NULL,
  `bank_account_type` varchar(255) DEFAULT NULL,
  `bank_account_number` varchar(255) DEFAULT NULL,
  `bank_hidden_account_number` varchar(255) DEFAULT NULL,
  `bank_verification_status` varchar(255) DEFAULT NULL,
  `bank_verification_type` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hiring_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hiring_status` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int DEFAULT '0',
  `hide_status` tinyint DEFAULT '0',
  `colour_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#E4E9FF',
  `show_on_card` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '1=>show, 0=>hide, set for Pipeline Card',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hubspot_transaction_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hubspot_transaction_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `object_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_url` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `missing_keys` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_category_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_category_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sequence` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_mandatory` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `is_custom` tinyint NOT NULL DEFAULT '0',
  `section_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_template_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_template_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint NOT NULL,
  `category_detail_id` bigint NOT NULL,
  `excel_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint NOT NULL,
  `template_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `incomplete_account_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `incomplete_account_alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `alert_id` bigint unsigned NOT NULL,
  `alert_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `number` int DEFAULT NULL,
  `type` enum('day','week','months') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'day',
  `department_id` bigint unsigned NOT NULL,
  `status` tinyint unsigned DEFAULT NULL,
  `position_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `incomplete_account_alerts_alert_id_foreign` (`alert_id`),
  KEY `incomplete_account_alerts_department_id_foreign` (`department_id`),
  KEY `incomplete_account_alerts_position_id_foreign` (`position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `integrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `integrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` tinyint DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `interigation_transaction_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `interigation_transaction_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `interigation_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `response` json DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_progress_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_progress_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'UUID of the job',
  `job_class` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Class name of the job',
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Queue the job is running on',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Current status: queued, processing, completed, failed',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Custom job type identifier (e.g., FR_officeName)',
  `progress_percentage` int NOT NULL DEFAULT '0' COMMENT 'Progress percentage 0-100',
  `total_records` int DEFAULT NULL COMMENT 'Total records to process',
  `processed_records` int DEFAULT NULL COMMENT 'Number of records processed',
  `completed_records` int DEFAULT NULL COMMENT 'Number of records successfully processed before job ended',
  `current_operation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Current operation being performed',
  `last_record_identifier` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Identifier of the last processed record',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Additional status message',
  `metadata` json DEFAULT NULL COMMENT 'Additional metadata as JSON',
  `error` json DEFAULT NULL COMMENT 'Error details if failed',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `is_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_progress_logs_job_id_index` (`job_id`),
  KEY `job_progress_logs_job_class_index` (`job_class`),
  KEY `job_progress_logs_queue_index` (`queue`),
  KEY `job_progress_logs_status_index` (`status`),
  KEY `job_progress_logs_is_hidden_index` (`is_hidden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_comment_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_comment_replies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` bigint unsigned NOT NULL,
  `comment_reply` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `lead_id` int DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `pipeline_lead_status_id` bigint unsigned DEFAULT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_custom_field_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_custom_field_setting` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `custom_fields_columns` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `lead_id` bigint unsigned NOT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_user_prefereces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_user_prefereces` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `move_lead` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` bigint unsigned DEFAULT NULL,
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `interview_date` date DEFAULT NULL,
  `interview_time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_hired_date` datetime DEFAULT NULL,
  `conversion_rate` double DEFAULT NULL,
  `interview_schedule_by_id` bigint unsigned DEFAULT NULL,
  `action_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recruiter_id` bigint unsigned DEFAULT NULL,
  `office_id` bigint unsigned DEFAULT NULL,
  `assign_by_id` bigint unsigned DEFAULT NULL,
  `reporting_manager_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pipeline_status_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `custom_fields_detail` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `pipeline_status_date` date DEFAULT NULL,
  `lead_rating` decimal(5,2) NOT NULL DEFAULT '0.00',
  `custom_rating` decimal(5,2) NOT NULL DEFAULT '0.00',
  `overall_rating` decimal(5,2) NOT NULL DEFAULT '0.00',
  `background_color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#FFFFFF',
  PRIMARY KEY (`id`),
  KEY `leads_state_id_foreign` (`state_id`),
  KEY `leads_interview_schedule_by_id_foreign` (`interview_schedule_by_id`),
  KEY `leads_recruiter_id_foreign` (`recruiter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `legacy_api_data_null`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legacy_api_data_null` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `legacy_data_id` int DEFAULT NULL,
  `aveyo_hs_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ticket_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appointment_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prospect_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panel_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panel_id` int NOT NULL,
  `weekly_sheet_id` bigint unsigned DEFAULT NULL,
  `homeowner_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proposal_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `customer_address_2` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `customer_longitude` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_latitude` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_code` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter_id` int DEFAULT NULL,
  `closer_id` int DEFAULT NULL,
  `employee_id` int DEFAULT NULL,
  `sales_rep_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_rep_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_setter_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_setter_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `install_partner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `install_partner_id` int DEFAULT NULL,
  `customer_signoff` date DEFAULT NULL,
  `m1_date` date DEFAULT NULL,
  `scheduled_install` date DEFAULT NULL,
  `install_complete_date` date DEFAULT NULL,
  `m2_date` date DEFAULT NULL,
  `date_cancelled` date DEFAULT NULL,
  `inactive_date` date DEFAULT NULL,
  `return_sales_date` date DEFAULT NULL,
  `gross_account_value` double(11,3) DEFAULT NULL,
  `cash_amount` double(11,3) DEFAULT NULL,
  `loan_amount` double(11,3) DEFAULT NULL,
  `kw` double(11,3) DEFAULT NULL,
  `balance_age` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `dealer_fee_percentage` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dealer_fee_dollar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dealer_fee_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shows` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_for_acct` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prev_paid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_date_pd` date DEFAULT NULL,
  `m1_this_week` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `install_m2_this_week` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prev_deducted` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_deduction` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lead_cost` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adv_pay_back_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_in_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adders` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_fee` double(11,3) DEFAULT NULL,
  `adders_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `funding_source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `financing_rate` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `financing_term` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `milestone_trigger` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `epc` float DEFAULT NULL,
  `net_epc` float DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_status` int DEFAULT NULL,
  `sales_type` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Missing',
  `action_status` tinyint NOT NULL DEFAULT '0',
  `source_created_at` datetime DEFAULT NULL COMMENT 'date when created at data source',
  `source_updated_at` datetime DEFAULT NULL COMMENT 'date when modified at data source',
  `data_source_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_alert` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `missingrep_alert` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closedpayroll_alert` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `locationredline_alert` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `repredline_alert` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `people_alert` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closedpayroll_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `length_of_agreement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_schedule` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `initial_service_cost` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_pay` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_on_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_payment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_completed` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_service_date` date DEFAULT NULL,
  `initial_service_date` date DEFAULT NULL,
  `bill_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trigger_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `legacy_api_data_null_weekly_sheet_id_foreign` (`weekly_sheet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `legacy_api_raw_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legacy_api_raw_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `legacy_data_id` int DEFAULT NULL,
  `aveyo_hs_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ticket_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appointment_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page` int DEFAULT NULL,
  `weekly_sheet_id` bigint unsigned DEFAULT NULL,
  `homeowner_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proposal_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `customer_address_2` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `customer_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter_id` int DEFAULT NULL,
  `employee_id` int DEFAULT NULL,
  `sales_rep_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_rep_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `install_partner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `install_partner_id` int DEFAULT NULL,
  `customer_signoff` date DEFAULT NULL,
  `m1_date` date DEFAULT NULL,
  `scheduled_install` date DEFAULT NULL,
  `install_complete_date` date DEFAULT NULL,
  `m2_date` date DEFAULT NULL,
  `date_cancelled` date DEFAULT NULL,
  `return_sales_date` date DEFAULT NULL,
  `gross_account_value` double(11,3) DEFAULT NULL,
  `cash_amount` double(11,3) DEFAULT NULL,
  `loan_amount` double(11,3) DEFAULT NULL,
  `kw` double(11,3) DEFAULT NULL,
  `balance_age` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `dealer_fee_percentage` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adders` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_fee` double(11,3) DEFAULT NULL,
  `adders_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `funding_source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `financing_rate` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `financing_term` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `milestone_trigger` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `epc` float DEFAULT NULL,
  `net_epc` float DEFAULT NULL,
  `length_of_agreement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_schedule` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `initial_service_cost` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_pay` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_on_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_payment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_completed` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_service_date` date DEFAULT NULL,
  `bill_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_created_at` datetime DEFAULT NULL COMMENT 'date when created at data source',
  `source_updated_at` datetime DEFAULT NULL COMMENT 'date when modified at data source',
  `data_source_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `legacy_api_raw_data_weekly_sheet_id_foreign` (`weekly_sheet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `legacy_api_raw_data_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legacy_api_raw_data_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `legacy_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ticket_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appointment_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weekly_sheet_id` bigint unsigned DEFAULT NULL,
  `install_partner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `install_partner_id` int DEFAULT NULL,
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `customer_address_2` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `customer_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_code` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `homeowner_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proposal_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter_id` int DEFAULT '0',
  `sales_setter_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_rep_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_id` int DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `initialAppointmentID` bigint unsigned DEFAULT NULL,
  `soldBy` bigint unsigned DEFAULT NULL,
  `soldBy2` bigint unsigned DEFAULT NULL,
  `initialStatusText` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_rep_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kw` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `balance_age` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `date_cancelled` date DEFAULT NULL,
  `customer_signoff` date DEFAULT NULL COMMENT 'Approved date',
  `m1_date` date DEFAULT NULL,
  `m2_date` date DEFAULT NULL,
  `product` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gross_account_value` double(11,3) DEFAULT NULL,
  `epc` decimal(8,4) DEFAULT NULL,
  `net_epc` decimal(8,4) DEFAULT NULL,
  `dealer_fee_percentage` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dealer_fee_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adders` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SOW amount',
  `adders_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_amount_for_acct` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prev_amount_paid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_date_pd` date DEFAULT NULL,
  `m1_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `m2_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prev_deducted_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_fee` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_deduction` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lead_cost_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adv_pay_back_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_amount_in_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `funding_source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `financing_rate` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `financing_term` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scheduled_install` date DEFAULT NULL,
  `install_complete_date` date DEFAULT NULL,
  `return_sales_date` date DEFAULT NULL,
  `cash_amount` double(11,3) DEFAULT NULL,
  `loan_amount` double(11,3) DEFAULT NULL,
  `closer1_id` bigint unsigned DEFAULT NULL,
  `closer2_id` bigint unsigned DEFAULT NULL,
  `setter1_id` bigint unsigned DEFAULT NULL,
  `setter2_id` bigint unsigned DEFAULT NULL,
  `closer1_m1` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer2_m1` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter1_m1` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter2_m1` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer1_m2` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer2_m2` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter1_m2` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter2_m2` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer1_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer2_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter1_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter2_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer1_m1_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer2_m1_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter1_m1_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter2_m1_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer1_m2_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer2_m2_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter1_m2_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter2_m2_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer1_m1_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer2_m1_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter1_m1_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter2_m1_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer1_m2_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer2_m2_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter1_m2_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter2_m2_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mark_account_status_id` bigint unsigned DEFAULT NULL,
  `pid_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `length_of_agreement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_schedule` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `initial_service_cost` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_pay` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_on_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_payment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_completed` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_service_date` date DEFAULT NULL,
  `initial_service_date` date DEFAULT NULL,
  `bill_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_source_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_created_at` datetime DEFAULT NULL COMMENT 'date when created_at data source',
  `source_updated_at` datetime DEFAULT NULL COMMENT 'date when updated_at data source',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `import_to_sales` tinyint DEFAULT '0',
  `excel_import_id` bigint DEFAULT NULL,
  `contract_sign_date` date DEFAULT NULL,
  `job_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trigger_date` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `customer_payment_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `legacy_api_raw_data_histories_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legacy_api_raw_data_histories_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `action_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of action: insert, update, delete',
  `original_id` bigint unsigned DEFAULT NULL COMMENT 'Reference to the original record ID',
  `changed_by` bigint unsigned DEFAULT NULL COMMENT 'User ID who made the change',
  `changed_at` timestamp NULL DEFAULT NULL COMMENT 'When the change was made',
  `legacy_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weekly_sheet_id` bigint unsigned DEFAULT NULL,
  `install_partner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `install_partner_id` int DEFAULT NULL,
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `customer_address_2` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `customer_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_zip` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `homeowner_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proposal_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter_id` int DEFAULT '0',
  `sales_rep_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_id` int DEFAULT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `initialAppointmentID` bigint unsigned DEFAULT NULL,
  `soldBy` bigint unsigned DEFAULT NULL,
  `soldBy2` bigint unsigned DEFAULT NULL,
  `initialStatusText` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_rep_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kw` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `balance_age` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `date_cancelled` date DEFAULT NULL,
  `customer_signoff` date DEFAULT NULL COMMENT 'Approved date',
  `m1_date` date DEFAULT NULL,
  `m2_date` date DEFAULT NULL,
  `product` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gross_account_value` double(11,3) DEFAULT NULL,
  `epc` decimal(8,4) DEFAULT NULL,
  `net_epc` decimal(8,4) DEFAULT NULL,
  `dealer_fee_percentage` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dealer_fee_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adders` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SOW amount',
  `adders_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_amount_for_acct` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prev_amount_paid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_date_pd` date DEFAULT NULL,
  `m1_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `m2_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prev_deducted_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_fee` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_deduction` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lead_cost_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adv_pay_back_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_amount_in_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `funding_source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `financing_rate` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `financing_term` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scheduled_install` date DEFAULT NULL,
  `install_complete_date` date DEFAULT NULL,
  `return_sales_date` date DEFAULT NULL,
  `cash_amount` double(11,3) DEFAULT NULL,
  `loan_amount` double(11,3) DEFAULT NULL,
  `closer1_id` bigint unsigned DEFAULT NULL,
  `closer2_id` bigint unsigned DEFAULT NULL,
  `setter1_id` bigint unsigned DEFAULT NULL,
  `setter2_id` bigint unsigned DEFAULT NULL,
  `closer1_m1` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer2_m1` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter1_m1` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter2_m1` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer1_m2` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer2_m2` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter1_m2` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter2_m2` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer1_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer2_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter1_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter2_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer1_m1_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer2_m1_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter1_m1_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter2_m1_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer1_m2_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer2_m2_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter1_m2_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter2_m2_paid_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer1_m1_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer2_m1_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter1_m1_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter2_m1_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer1_m2_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer2_m2_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter1_m2_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter2_m2_paid_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mark_account_status_id` bigint unsigned DEFAULT NULL,
  `pid_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `length_of_agreement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_schedule` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `initial_service_cost` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_pay` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_on_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_payment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_completed` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_service_date` date DEFAULT NULL,
  `initial_service_date` date DEFAULT NULL,
  `bill_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_source_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_created_at` datetime DEFAULT NULL COMMENT 'date when created_at data source',
  `source_updated_at` datetime DEFAULT NULL COMMENT 'date when updated_at data source',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `import_to_sales` tinyint DEFAULT '0',
  `excel_import_id` bigint DEFAULT NULL,
  `contract_sign_date` date DEFAULT NULL,
  `job_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trigger_date` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `customer_payment_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `legacy_api_raw_data_histories_log_action_type_index` (`action_type`),
  KEY `legacy_api_raw_data_histories_log_original_id_index` (`original_id`),
  KEY `legacy_api_raw_data_histories_log_changed_at_index` (`changed_at`),
  KEY `legacy_api_raw_data_histories_log_pid_index` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `legacy_api_raw_data_update_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legacy_api_raw_data_update_histories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `legacy_excel_raw_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legacy_excel_raw_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ct` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weekly_sheet_id` bigint unsigned DEFAULT NULL,
  `affiliate` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `install_partner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_rep_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_rep_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_setter_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kw` double(8,3) DEFAULT NULL,
  `cancel_date` date DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `m1_date` date DEFAULT NULL,
  `m2_date` date DEFAULT NULL,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gross_account_value` double(11,3) DEFAULT NULL,
  `epc` double(11,3) DEFAULT NULL,
  `net_epc` double(11,3) DEFAULT NULL,
  `dealer_fee_percentage` double(6,3) DEFAULT NULL,
  `dealer_fee_dollar` double(11,3) DEFAULT NULL,
  `show` double(11,3) DEFAULT NULL,
  `redline` double(11,3) DEFAULT NULL,
  `total_for_acct` double(11,3) DEFAULT NULL,
  `prev_paid` double(11,3) DEFAULT NULL,
  `last_date_pd` date DEFAULT NULL,
  `m1_this_week` double(11,3) DEFAULT NULL,
  `install_m2_this_week` double(11,3) DEFAULT NULL,
  `prev_deducted` double(11,3) DEFAULT NULL,
  `cancel_fee` double(11,3) DEFAULT NULL,
  `cancel_deduction` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lead_cost` double(11,3) DEFAULT NULL,
  `adv_pay_back_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_in_period` double(11,3) DEFAULT NULL,
  `inactive_date` date DEFAULT NULL,
  `data_source_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `legacy_excel_raw_data_weekly_sheet_id_foreign` (`weekly_sheet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `legacy_weekly_sheet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legacy_weekly_sheet` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `crm_id` int unsigned DEFAULT '0',
  `week` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `week_date` date DEFAULT NULL,
  `month` int unsigned DEFAULT NULL,
  `year` int unsigned DEFAULT NULL,
  `no_of_records` int DEFAULT NULL,
  `sheet_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_records` int DEFAULT NULL,
  `new_pid` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_records` int DEFAULT NULL,
  `contact_pushed` int DEFAULT NULL,
  `errors` int DEFAULT NULL,
  `status_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `log_file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `in_process` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '0 = Completed, 1 = In-Process',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `location_redline_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `location_redline_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_id` bigint DEFAULT NULL,
  `redline_min` float DEFAULT NULL,
  `redline_standard` float DEFAULT NULL,
  `redline_max` float DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `state_id` bigint unsigned NOT NULL,
  `city_id` bigint unsigned DEFAULT NULL,
  `work_site_id` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `general_code` varchar(225) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `installation_partner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline_min` double(8,2) DEFAULT NULL,
  `redline_standard` double(8,2) DEFAULT NULL,
  `redline_max` double(8,2) DEFAULT NULL,
  `type` enum('Redline','Office') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Redline',
  `created_by` int DEFAULT NULL,
  `date_effective` date DEFAULT NULL,
  `office_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_status` int DEFAULT '1',
  `mailing_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lat` double DEFAULT NULL,
  `long` double DEFAULT NULL,
  `time_zone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `everee_location_id` int DEFAULT NULL,
  `w2_everee_location_id` int DEFAULT NULL,
  `everee_json_response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `archived_at` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `locations_state_id_foreign` (`state_id`),
  KEY `locations_city_id_foreign` (`city_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `management_team_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `management_team_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned DEFAULT NULL,
  `team_lead_id` bigint unsigned DEFAULT NULL,
  `team_member_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `management_team_members_team_member_id_foreign` (`team_member_id`),
  KEY `management_team_members_team_lead_id_foreign` (`team_lead_id`),
  KEY `management_team_members_team_id_foreign` (`team_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `management_teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `management_teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_lead_id` bigint unsigned DEFAULT NULL,
  `location_id` smallint unsigned DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `team_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('lead') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lead',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `management_teams_team_lead_id_foreign` (`team_lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manual_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `manual_overrides` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `manual_user_id` int NOT NULL,
  `user_id` int NOT NULL,
  `overrides_amount` double(8,2) NOT NULL,
  `overrides_type` enum('per sale','per kw','percent') NOT NULL,
  `effective_date` date DEFAULT NULL,
  `product_id` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `manual_overrides_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `manual_overrides_history` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `manual_user_id` int NOT NULL,
  `manual_overrides_id` int NOT NULL,
  `user_id` int NOT NULL,
  `updated_by` int DEFAULT NULL,
  `old_overrides_amount` double(8,2) DEFAULT NULL,
  `old_overrides_type` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overrides_amount` double(8,2) NOT NULL,
  `overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `effective_date` date DEFAULT NULL,
  `product_id` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `margin_of_differences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `margin_of_differences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `difference_parcentage` double(8,2) NOT NULL,
  `applied_to` enum('Setter','Closer','Manager') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Setter',
  `margin_setting_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `margin_of_differences_margin_setting_id_foreign` (`margin_setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `margin_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `margin_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `status` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mark_account_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mark_account_status` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing__deals__settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing__deals__settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reconciliation` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `status` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_deal_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_deal_alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `alert_id` bigint unsigned NOT NULL,
  `alert_type` enum('alert','limit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'alert',
  `department_id` bigint unsigned NOT NULL,
  `position_id` bigint unsigned NOT NULL,
  `personnel_id` int DEFAULT NULL,
  `amount` double(8,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_deal_alerts_alert_id_foreign` (`alert_id`),
  KEY `marketing_deal_alerts_department_id_foreign` (`department_id`),
  KEY `marketing_deal_alerts_position_id_foreign` (`position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_deals_reconciliations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_deals_reconciliations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `day_date` date DEFAULT NULL,
  `marketing_setting_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_deals_reconciliations_marketing_setting_id_foreign` (`marketing_setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `milestone_product_audiotlogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `milestone_product_audiotlogs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reference_id` int NOT NULL,
  `effective_on_date` date DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `group` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `milestone_product_audiotlogs_user_id_foreign` (`user_id`),
  CONSTRAINT `milestone_product_audiotlogs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `milestone_schema_trigger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `milestone_schema_trigger` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `milestone_schema_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `on_trigger` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `milestone_schema_trigger_milestone_schema_id_foreign` (`milestone_schema_id`),
  CONSTRAINT `milestone_schema_trigger_milestone_schema_id_foreign` FOREIGN KEY (`milestone_schema_id`) REFERENCES `milestone_schemas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`admin_flexpwr`@`%`*/ /*!50003 TRIGGER `update_name_after_milestone_schema_trigger_update` AFTER UPDATE ON `milestone_schema_trigger` FOR EACH ROW BEGIN
		    IF OLD.name != NEW.name OR OLD.on_trigger != NEW.on_trigger THEN
		    	UPDATE user_commission
		        SET schema_name = NEW.name, schema_trigger = NEW.on_trigger
		        WHERE user_commission.milestone_schema_id = NEW.id;
		    	UPDATE user_commission_lock
		        SET schema_name = NEW.name, schema_trigger = NEW.on_trigger
		        WHERE user_commission_lock.milestone_schema_id = NEW.id;
		    	UPDATE clawback_settlements
		        SET schema_name = NEW.name, schema_trigger = NEW.on_trigger
		        WHERE clawback_settlements.milestone_schema_id = NEW.id;
		    	UPDATE clawback_settlements_lock
		        SET schema_name = NEW.name, schema_trigger = NEW.on_trigger
		        WHERE clawback_settlements_lock.milestone_schema_id = NEW.id;
		    	UPDATE projection_user_commissions
		        SET schema_name = NEW.name, schema_trigger = NEW.on_trigger
		        WHERE projection_user_commissions.milestone_schema_id = NEW.id;
		    END IF;
		END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `milestone_schemas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `milestone_schemas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prefix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'MS',
  `schema_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `schema_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('1','0') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modules_with_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modules_with_permission` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` int NOT NULL,
  `module_tab_id` int NOT NULL,
  `submodule` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `monthly_pay_frequencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `monthly_pay_frequencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `closed_status` int DEFAULT '0',
  `open_status_from_bank` tinyint NOT NULL DEFAULT '0',
  `w2_closed_status` tinyint NOT NULL DEFAULT '0',
  `w2_open_status_from_bank` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `move_to_recon_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `move_to_recon_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('commission','overrides','clawback','adjustments','deductions','hourlysalary','overtime') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pid` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `move_to_reconciliations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `move_to_reconciliations` (
  `id` int NOT NULL,
  `user_id` bigint DEFAULT NULL,
  `payroll_id` bigint DEFAULT NULL,
  `pid` varchar(55) DEFAULT NULL,
  `commission` double DEFAULT NULL,
  `override` double DEFAULT NULL,
  `status` varchar(55) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `new_sequi_docs_document_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `new_sequi_docs_document_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `template_id` int DEFAULT NULL,
  `document_name` varchar(255) DEFAULT NULL COMMENT 'send document name like offer letter or other doc like W9',
  `user_id_from` enum('users','onboarding_employees') DEFAULT 'onboarding_employees',
  `comment_user_id_from` enum('users','onboarding_employees') DEFAULT 'users',
  `document_send_to_user_id` int DEFAULT NULL,
  `comment_by_id` int DEFAULT NULL,
  `comment_type` varchar(255) DEFAULT NULL,
  `comment` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `new_sequi_docs_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `new_sequi_docs_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_id_from` enum('users','onboarding_employees') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'users',
  `is_external_recipient` tinyint NOT NULL DEFAULT '0',
  `external_user_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_user_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `template_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` int DEFAULT '1' COMMENT '0 not active , 1 for active doc',
  `document_inactive_date` datetime DEFAULT NULL,
  `doc_version` int unsigned DEFAULT '1',
  `send_by` int DEFAULT NULL COMMENT 'Document send by user id',
  `is_document_resend` tinyint DEFAULT '0' COMMENT '0 for send , 1 for resend',
  `upload_document_type_id` int DEFAULT NULL COMMENT 'id from new_sequi_docs_upload_document_types',
  `un_signed_document` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'doc before sign',
  `document_send_date` datetime DEFAULT NULL,
  `document_response_status` int NOT NULL DEFAULT '0' COMMENT '0 for no action',
  `document_response_date` datetime DEFAULT NULL,
  `user_request_change_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `document_uploaded_type` enum('manual_doc','secui_doc_uploaded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual_doc',
  `envelope_id` int DEFAULT NULL,
  `envelope_password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signature_request_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signature_request_document_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'signature requested document id',
  `signed_status` tinyint NOT NULL DEFAULT '0' COMMENT '0=not signed,1=signed',
  `signed_date` datetime DEFAULT NULL,
  `signed_document` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'doc after sign',
  `send_reminder` tinyint DEFAULT '0' COMMENT '0 for no , 1 for yes',
  `reminder_in_days` tinyint DEFAULT '0',
  `max_reminder_times` tinyint DEFAULT '0',
  `reminder_done_times` tinyint DEFAULT '0',
  `last_reminder_sent_at` timestamp NULL DEFAULT NULL,
  `is_post_hiring_document` tinyint DEFAULT '0' COMMENT 'comment 0 for no 1 for yes',
  `is_sign_required_for_hire` tinyint DEFAULT '0' COMMENT '0 for no , 1 for yes',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `imported_from_old` tinyint(1) NOT NULL DEFAULT '0',
  `imported_old_documennt_id` int DEFAULT NULL,
  `signing_attemp_at` datetime DEFAULT NULL,
  `smart_text_template_fied_keyval` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `new_sequi_docs_send_document_with_offer_letters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `new_sequi_docs_send_document_with_offer_letters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` smallint NOT NULL,
  `to_send_template_id` smallint NOT NULL COMMENT 'template id for send with offer letter',
  `category_id` smallint DEFAULT NULL COMMENT 'to send template category id',
  `is_sign_required_for_hire` smallint NOT NULL DEFAULT '0' COMMENT '0 for optional , 1 for Mandatory',
  `is_post_hiring_document` smallint NOT NULL DEFAULT '0' COMMENT '1 for post Hiring , 0 for Onboarding Document',
  `is_document_for_upload` tinyint DEFAULT '0' COMMENT '0 for Signature , 1 for upload file',
  `manual_doc_type_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `new_sequi_docs_send_smart_template_with_offer_letters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `new_sequi_docs_send_smart_template_with_offer_letters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `template_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_id` smallint NOT NULL,
  `to_send_template_id` smallint NOT NULL COMMENT 'template id for send with offer letter',
  `category_id` smallint DEFAULT NULL COMMENT 'to send template category id',
  `is_sign_required_for_hire` smallint NOT NULL DEFAULT '0' COMMENT '0 for optional , 1 for Mandatory',
  `is_post_hiring_document` smallint NOT NULL DEFAULT '0' COMMENT '1 for post Hiring , 0 for Onboarding Document',
  `is_document_for_upload` tinyint DEFAULT '0' COMMENT '0 for Signature , 1 for upload file',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `new_sequi_docs_signature_request_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `new_sequi_docs_signature_request_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ApiName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_array` json DEFAULT NULL,
  `envelope_data` json DEFAULT NULL,
  `send_document_final_array` json DEFAULT NULL,
  `signature_request_response` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `new_sequi_docs_template_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `new_sequi_docs_template_permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `position_type` enum('permission','receipient') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'permission',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `new_sequi_docs_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `new_sequi_docs_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int DEFAULT NULL,
  `template_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `template_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `template_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `completed_step` tinyint DEFAULT '0',
  `is_template_ready` tinyint DEFAULT '0',
  `recipient_sign_req` tinyint DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `is_pdf` int DEFAULT '0' COMMENT '0 for no is blank template , 1 for template is pdf',
  `pdf_file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pdf_file_other_parameter` json DEFAULT NULL,
  `email_subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `send_reminder` tinyint DEFAULT '0' COMMENT '0 for no , 1 for yes',
  `reminder_in_days` tinyint DEFAULT '0',
  `max_reminder_times` tinyint DEFAULT '0',
  `is_deleted` tinyint NOT NULL DEFAULT '0' COMMENT '1 for deleted , 0 for not deleted',
  `template_delete_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `imported_from_old` tinyint(1) NOT NULL DEFAULT '0',
  `imported_old_template_id` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `new_sequi_docs_upload_document_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `new_sequi_docs_upload_document_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint DEFAULT NULL,
  `document_file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `s3_document_file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_version` int unsigned DEFAULT '1',
  `is_deleted` tinyint DEFAULT '0' COMMENT '1 for deleted, 0 for not deleted',
  `delete_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `new_sequi_docs_upload_document_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `new_sequi_docs_upload_document_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint DEFAULT '0' COMMENT '1 for deleted, 0 for not deleted',
  `delete_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `description` text,
  `type` varchar(255) NOT NULL,
  `is_read` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_additional_emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_additional_emails` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `onboarding_user_id` int DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_commission_tiers_level_range`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_commission_tiers_level_range` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `onboarding_commission_id` bigint unsigned DEFAULT NULL,
  `tiers_schema_id` bigint unsigned DEFAULT NULL,
  `tiers_levels_id` bigint unsigned DEFAULT NULL,
  `value` decimal(40,2) NOT NULL,
  `value_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_employee_additional_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_employee_additional_overrides` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `onboarding_location_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `tiers_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned NOT NULL,
  `overrides_amount` decimal(8,2) NOT NULL,
  `overrides_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_employee_deduction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_employee_deduction` (
  `id` int NOT NULL AUTO_INCREMENT,
  `deduction_type` enum('$','%') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_center_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_center_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ammount_par_paycheck` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deduction_setting_id` int DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_employee_direct_override_tiers_range`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_employee_direct_override_tiers_range` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `onboarding_direct_override_id` bigint unsigned DEFAULT NULL,
  `tiers_schema_id` bigint unsigned NOT NULL,
  `tiers_levels_id` bigint unsigned DEFAULT NULL,
  `value` decimal(40,2) NOT NULL,
  `value_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_employee_indirect_override_tiers_range`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_employee_indirect_override_tiers_range` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `onboarding_indirect_override_id` bigint unsigned DEFAULT NULL,
  `tiers_schema_id` bigint unsigned NOT NULL,
  `tiers_levels_id` bigint unsigned DEFAULT NULL,
  `value` decimal(40,2) NOT NULL,
  `value_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_employee_locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_employee_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `state_id` bigint unsigned DEFAULT NULL,
  `city_id` bigint unsigned DEFAULT NULL,
  `overrides_amount` double(8,2) NOT NULL DEFAULT '0.00',
  `overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'per kw',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_employee_office_override_tiers_range`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_employee_office_override_tiers_range` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `onboarding_office_override_id` bigint unsigned DEFAULT NULL,
  `tiers_schema_id` bigint unsigned DEFAULT NULL,
  `tiers_levels_id` bigint unsigned DEFAULT NULL,
  `value` decimal(40,2) NOT NULL,
  `value_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_employee_override`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_employee_override` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `updater_id` int DEFAULT NULL,
  `office_tiers_id` bigint unsigned DEFAULT NULL,
  `indirect_tiers_id` bigint unsigned DEFAULT NULL,
  `direct_tiers_id` bigint unsigned DEFAULT NULL,
  `override_effective_date` date DEFAULT NULL,
  `direct_overrides_amount` double(8,2) DEFAULT NULL,
  `direct_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `indirect_overrides_amount` double(8,2) DEFAULT NULL,
  `indirect_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_overrides_amount` double(8,2) DEFAULT NULL,
  `office_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_stack_overrides_amount` double(8,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_employee_redlines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_employee_redlines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `self_gen_user` tinyint NOT NULL DEFAULT '0',
  `core_position_id` bigint unsigned DEFAULT NULL,
  `updater_id` bigint unsigned DEFAULT NULL,
  `position_id` int NOT NULL DEFAULT '0',
  `redline_amount_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline` decimal(8,2) DEFAULT NULL,
  `redline_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline_effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_employee_upfronts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_employee_upfronts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `position_id` bigint unsigned NOT NULL,
  `core_position_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned NOT NULL,
  `milestone_schema_id` bigint unsigned NOT NULL,
  `milestone_schema_trigger_id` bigint unsigned NOT NULL,
  `self_gen_user` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Not SelfGen, 1 = SelfGen',
  `updater_id` bigint unsigned NOT NULL,
  `tiers_id` bigint unsigned DEFAULT NULL,
  `upfront_pay_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `upfront_sale_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'per sale, per kw',
  `upfront_effective_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_employee_upfronts_tiers_range`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_employee_upfronts_tiers_range` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `onboarding_upfront_id` bigint unsigned DEFAULT NULL,
  `tiers_schema_id` bigint unsigned DEFAULT NULL,
  `tiers_levels_id` bigint unsigned DEFAULT NULL,
  `value` decimal(40,2) NOT NULL,
  `value_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_employee_wages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_employee_wages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `updater_id` int NOT NULL,
  `pay_type` enum('Hourly','Salary') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Salary',
  `pay_rate` decimal(10,2) NOT NULL,
  `pto_hours` decimal(10,2) DEFAULT NULL,
  `unused_pto` enum('Expires Monthly','Expires Annually','Accrues Continuously') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_weekly_hours` decimal(10,2) NOT NULL DEFAULT '40.00',
  `overtime_rate` decimal(10,2) NOT NULL DEFAULT '1.50',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_employee_withhelds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_employee_withhelds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `product_id` int NOT NULL DEFAULT '0',
  `updater_id` bigint unsigned DEFAULT NULL,
  `position_id` int NOT NULL DEFAULT '0',
  `withheld_amount` decimal(8,2) DEFAULT NULL,
  `withheld_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `withheld_effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `lead_id` bigint DEFAULT NULL,
  `aveyo_hs_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sex` enum('male','female','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Employee_profile/default-user.png',
  `zip_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `home_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` bigint unsigned DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city_id` bigint unsigned DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `employee_position_id` bigint unsigned DEFAULT NULL,
  `manager_id` bigint unsigned DEFAULT NULL,
  `team_id` int DEFAULT NULL,
  `old_status_id` int DEFAULT NULL,
  `status_id` int DEFAULT NULL,
  `recruiter_id` int DEFAULT NULL,
  `additional_recruiter_id1` int DEFAULT NULL,
  `additional_recruiter_id2` int DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `sub_position_id` int DEFAULT NULL,
  `worker_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '1099' COMMENT 'W9, 1099',
  `commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_type` enum('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `self_gen_commission` int NOT NULL DEFAULT '0',
  `self_gen_commission_type` enum('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline_amount_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline_type` enum('per sale','per watt') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'per watt',
  `self_gen_redline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `self_gen_redline_amount_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `self_gen_redline_type` enum('per sale','per watt') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upfront_pay_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upfront_sale_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'per sale',
  `withheld_amount` decimal(8,2) DEFAULT NULL,
  `withheld_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `self_gen_withheld_amount` decimal(8,2) DEFAULT NULL,
  `self_gen_withheld_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `self_gen_upfront_amount` double(8,2) NOT NULL DEFAULT '0.00',
  `self_gen_upfront_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_selfgen` double(8,2) NOT NULL DEFAULT '0.00',
  `commission_selfgen_type` enum('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_selfgen_effective_date` date DEFAULT NULL,
  `direct_overrides_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direct_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'per kw',
  `indirect_overrides_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `indirect_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'per kw',
  `office_overrides_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'per kw',
  `office_stack_overrides_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hourly, Salary',
  `pay_rate` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `pay_rate_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Per Hour, Weekly, Monthly, Bi-Weekly, Semi-Monthly',
  `pto_hours` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `unused_pto_expires` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Monthly, Annually, Accrues Continuously',
  `expected_weekly_hours` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overtime_rate` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `probation_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hiring_bonus_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_to_be_paid` date DEFAULT NULL,
  `period_of_agreement_start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `offer_expiry_date` date DEFAULT NULL,
  `type` enum('Manager') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hiring_type` enum('Directly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `offer_include_bonus` enum('1','0') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `is_manager` enum('1','0') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `self_gen_accounts` int DEFAULT NULL,
  `self_gen_type` int DEFAULT NULL,
  `document_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_offer_letter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_background_verificaton` tinyint DEFAULT '0',
  `hired_by_uid` int DEFAULT NULL,
  `offer_review_uid` bigint unsigned DEFAULT NULL,
  `hiring_signature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_item_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Old, 1 = In Action Item',
  `custom_fields` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `status_date` date DEFAULT NULL,
  `experience_level` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_override_office_tiers_ranges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_override_office_tiers_ranges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `onboarding_override_office_id` bigint unsigned DEFAULT NULL,
  `tiers_schema_id` bigint unsigned NOT NULL,
  `tiers_levels_id` bigint unsigned DEFAULT NULL,
  `value` decimal(40,2) NOT NULL,
  `value_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_user_redlines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_user_redlines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `self_gen_user` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Not SelfGen, 1 = SelfGen',
  `updater_id` bigint unsigned DEFAULT NULL,
  `tiers_id` bigint unsigned DEFAULT NULL,
  `redline_amount_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` bigint unsigned DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `commission` double(8,2) DEFAULT NULL,
  `commission_type` enum('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_effective_date` date DEFAULT NULL,
  `upfront_pay_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upfront_sale_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upfront_effective_date` date DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `core_position_id` bigint unsigned DEFAULT NULL,
  `withheld_amount` decimal(8,2) DEFAULT NULL,
  `withheld_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `withheld_effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `one_time_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `one_time_payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `req_id` bigint unsigned DEFAULT NULL,
  `pay_by` bigint unsigned DEFAULT NULL,
  `req_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `everee_external_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `everee_payment_req_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `everee_paymentId` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adjustment_type_id` bigint unsigned DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pay_date` date DEFAULT NULL,
  `everee_status` int NOT NULL DEFAULT '0' COMMENT '0-disabled 1-enabled',
  `payment_status` int DEFAULT '0',
  `everee_json_response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `everee_webhook_response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `everee_payment_status` int NOT NULL DEFAULT '0' COMMENT '0-unpaid 1-paid',
  `quickbooks_journal_entry_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `other_important_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `other_important_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint DEFAULT NULL,
  `ApiName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response_data` json DEFAULT NULL,
  `other_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `other_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `other_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_by` int DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `template_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `template_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `template_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_sign_required_for_hire` smallint DEFAULT '1' COMMENT '0 for not required, 1 for required',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `override__settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `override__settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `settlement_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `override_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `override_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `recruiter_id` int DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `status` int NOT NULL DEFAULT '0',
  `effective_date` date DEFAULT NULL,
  `product_id` bigint NOT NULL DEFAULT '0',
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `override_system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `override_system_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `allow_manual_override_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allow_office_stack_override_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_type` enum('pay all overrides','pay override with the highest value') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `overrides__types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `overrides__types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `overrides_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lock_pay_out_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_limit` double(8,2) DEFAULT NULL,
  `parsonnel_limit` double(8,2) DEFAULT NULL,
  `min_position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` int DEFAULT NULL,
  `override_setting_id` bigint unsigned NOT NULL,
  `is_check` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `overrides__types_override_setting_id_foreign` (`override_setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pay_frequency_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pay_frequency_setting` (
  `id` int NOT NULL AUTO_INCREMENT,
  `frequency_type_id` int DEFAULT NULL,
  `first_months` varchar(255) DEFAULT NULL,
  `first_day` varchar(255) DEFAULT NULL,
  `day_of_week` varchar(255) DEFAULT NULL,
  `day_of_months` varchar(255) DEFAULT NULL,
  `pay_period` varchar(255) DEFAULT NULL,
  `monthly_pay_type` varchar(255) NOT NULL,
  `monthly_per_days` varchar(255) DEFAULT NULL,
  `first_day_pay_of_manths` varchar(255) DEFAULT NULL,
  `second_pay_day_of_month` varchar(255) DEFAULT NULL,
  `deadline_to_run_payroll` varchar(255) DEFAULT NULL,
  `first_pay_period_ends_on` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_alert_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_alert_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` bigint unsigned NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Not Sent, 1 = Sent',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_adjustment_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_adjustment_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `pid` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payroll_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adjustment_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `comment_by` int DEFAULT NULL,
  `cost_center_id` int DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `is_mark_paid` int NOT NULL DEFAULT '0',
  `is_next_payroll` int NOT NULL DEFAULT '0',
  `status` tinyint DEFAULT '1',
  `ref_id` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_move_to_recon` tinyint DEFAULT '0',
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_adjustment_detail_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_adjustment_details_lock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_adjustment_details_lock` (
  `id` bigint unsigned NOT NULL,
  `payroll_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `pid` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_user_id` varchar(255) DEFAULT NULL,
  `payroll_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adjustment_type` varchar(255) DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `comment_by` int DEFAULT NULL,
  `cost_center_id` int DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `is_mark_paid` int NOT NULL DEFAULT '0',
  `is_next_payroll` int NOT NULL DEFAULT '0',
  `status` tinyint DEFAULT '1',
  `ref_id` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_move_to_recon` tinyint DEFAULT '0',
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_adjustments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payroll_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `commission_type` varchar(255) DEFAULT 'commission',
  `commission_amount` double(8,2) DEFAULT NULL,
  `overrides_type` varchar(255) DEFAULT 'overrides',
  `overrides_amount` double(8,2) DEFAULT NULL,
  `adjustments_type` varchar(255) DEFAULT 'adjustments',
  `adjustments_amount` double(8,2) DEFAULT NULL,
  `reimbursements_type` varchar(255) DEFAULT 'reimbursements',
  `reimbursements_amount` double(8,2) DEFAULT NULL,
  `deductions_type` varchar(255) DEFAULT 'deductions',
  `deductions_amount` double(8,2) DEFAULT NULL,
  `clawbacks_type` varchar(255) DEFAULT 'clawbacks',
  `clawbacks_amount` double(8,2) DEFAULT NULL,
  `reconciliations_type` varchar(255) DEFAULT 'reconciliations',
  `reconciliations_amount` double(8,2) DEFAULT NULL,
  `hourlysalary_type` varchar(255) NOT NULL DEFAULT 'hourlysalary',
  `hourlysalary_amount` double(6,2) DEFAULT NULL,
  `overtime_type` varchar(255) NOT NULL DEFAULT 'overtime',
  `overtime_amount` double(6,2) DEFAULT NULL,
  `comment` longtext,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `is_mark_paid` int NOT NULL DEFAULT '0',
  `is_next_payroll` int NOT NULL DEFAULT '0',
  `status` tinyint DEFAULT '1',
  `ref_id` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_move_to_recon` tinyint DEFAULT '0',
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_adjustment_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_adjustments_lock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_adjustments_lock` (
  `id` bigint unsigned NOT NULL,
  `payroll_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `commission_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'commission',
  `commission_amount` double(8,2) DEFAULT NULL,
  `overrides_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'overrides',
  `overrides_amount` double(8,2) DEFAULT NULL,
  `adjustments_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'adjustments',
  `adjustments_amount` double(8,2) DEFAULT NULL,
  `reimbursements_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'reimbursements',
  `reimbursements_amount` double(8,2) DEFAULT NULL,
  `deductions_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'deductions',
  `deductions_amount` double(8,2) DEFAULT NULL,
  `clawbacks_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'clawbacks',
  `clawbacks_amount` double(8,2) DEFAULT NULL,
  `reconciliations_type` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT 'reconciliations',
  `reconciliations_amount` double(8,2) DEFAULT NULL,
  `comment` longtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `is_mark_paid` int NOT NULL DEFAULT '0',
  `is_next_payroll` int NOT NULL DEFAULT '0',
  `status` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_move_to_recon` tinyint DEFAULT '0',
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  `hourlysalary_type` varchar(255) NOT NULL DEFAULT 'hourlysalary',
  `hourlysalary_amount` double(6,2) DEFAULT NULL,
  `overtime_type` varchar(255) NOT NULL DEFAULT 'overtime',
  `overtime_amount` double(6,2) DEFAULT NULL,
  `ref_id` int DEFAULT '0',
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `commission` double(8,2) DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payroll` enum('payroll') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_common`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_common` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orig_payfrom` date DEFAULT NULL,
  `orig_payto` date DEFAULT NULL,
  `payroll_modified_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_deduction_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_deduction_locks` (
  `id` bigint unsigned NOT NULL,
  `payroll_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `cost_center_id` bigint unsigned NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `limit` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `outstanding` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `status` tinyint DEFAULT '1',
  `is_mark_paid` tinyint DEFAULT '0',
  `is_next_payroll` tinyint DEFAULT '0',
  `is_stop_payroll` tinyint DEFAULT '0',
  `ref_id` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_move_to_recon` tinyint DEFAULT '0',
  `is_move_to_recon_paid` tinyint DEFAULT '0',
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_deductions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_deductions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `cost_center_id` bigint unsigned NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `limit` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `outstanding` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `status` tinyint DEFAULT '1',
  `is_mark_paid` tinyint DEFAULT '0',
  `is_next_payroll` tinyint DEFAULT '0',
  `is_stop_payroll` tinyint DEFAULT '0',
  `ref_id` int DEFAULT '0',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `is_move_to_recon` tinyint DEFAULT '0',
  `is_move_to_recon_paid` tinyint DEFAULT '0',
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `position_id` bigint unsigned DEFAULT NULL,
  `everee_status` int NOT NULL DEFAULT '0' COMMENT '0-disabled 1-enabled',
  `everee_external_id` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `everee_payment_requestId` bigint DEFAULT NULL,
  `everee_paymentId` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission` double(8,2) DEFAULT NULL,
  `override` double(8,2) DEFAULT NULL,
  `reimbursement` double(8,2) DEFAULT NULL,
  `clawback` double(8,2) DEFAULT NULL,
  `deduction` double(8,2) DEFAULT NULL,
  `adjustment` double(8,2) DEFAULT NULL,
  `reconciliation` double(8,2) DEFAULT NULL,
  `hourly_salary` double(8,2) DEFAULT '0.00',
  `overtime` double(8,2) DEFAULT '0.00',
  `net_pay` double(8,2) DEFAULT NULL,
  `pay_frequency_date` date DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` int DEFAULT NULL,
  `everee_json_response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `everee_payment_status` int DEFAULT '3' COMMENT '1=send to everee\r\n2=everee payment failed\r\n3=paid from everee',
  `everee_webhook_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pay_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Manualy' COMMENT 'Manualy,Bank',
  `quickbooks_journal_entry_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_payment` double(8,2) DEFAULT NULL,
  `worker_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_hourly_salary`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_hourly_salary` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `position_id` int DEFAULT NULL,
  `date` date DEFAULT NULL,
  `hourly_rate` double(8,2) DEFAULT '0.00',
  `salary` double(8,2) DEFAULT '0.00',
  `regular_hours` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total` double(8,2) DEFAULT '0.00',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `is_mark_paid` tinyint NOT NULL DEFAULT '0',
  `is_next_payroll` tinyint NOT NULL DEFAULT '0',
  `is_stop_payroll` tinyint NOT NULL DEFAULT '0',
  `is_move_to_recon` tinyint DEFAULT '0',
  `ref_id` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_hourly_salary_lock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_hourly_salary_lock` (
  `id` bigint unsigned NOT NULL,
  `payroll_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `position_id` int DEFAULT NULL,
  `date` date DEFAULT NULL,
  `hourly_rate` double(8,2) DEFAULT '0.00',
  `salary` double(8,2) DEFAULT '0.00',
  `regular_hours` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total` double(8,2) DEFAULT '0.00',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `is_mark_paid` tinyint NOT NULL DEFAULT '0',
  `is_next_payroll` tinyint NOT NULL DEFAULT '0',
  `is_stop_payroll` tinyint NOT NULL DEFAULT '0',
  `is_move_to_recon` tinyint DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  `ref_id` int DEFAULT '0',
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_overtimes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_overtimes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `position_id` int DEFAULT NULL,
  `date` date DEFAULT NULL,
  `overtime_rate` double(8,2) DEFAULT '0.00',
  `overtime` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total` double(8,2) DEFAULT '0.00',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `is_mark_paid` tinyint NOT NULL DEFAULT '0',
  `is_next_payroll` tinyint NOT NULL DEFAULT '0',
  `is_stop_payroll` tinyint NOT NULL DEFAULT '0',
  `is_move_to_recon` tinyint DEFAULT '0',
  `ref_id` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_overtimes_lock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_overtimes_lock` (
  `id` bigint unsigned NOT NULL,
  `payroll_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `position_id` int DEFAULT NULL,
  `date` date DEFAULT NULL,
  `overtime_rate` double(8,2) DEFAULT '0.00',
  `overtime` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total` double(8,2) DEFAULT '0.00',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `is_mark_paid` tinyint NOT NULL DEFAULT '0',
  `is_next_payroll` tinyint NOT NULL DEFAULT '0',
  `is_stop_payroll` tinyint NOT NULL DEFAULT '0',
  `is_move_to_recon` tinyint DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  `ref_id` int DEFAULT '0',
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_processes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_processes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_setups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_setups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `field_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `worked_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_shift_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_shift_histories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` int unsigned NOT NULL,
  `moved_by` int unsigned DEFAULT NULL COMMENT 'user_id',
  `pay_period_from` date NOT NULL COMMENT 'move from',
  `pay_period_to` date NOT NULL COMMENT 'move from',
  `new_pay_period_from` date NOT NULL COMMENT 'move to',
  `new_pay_period_to` date NOT NULL COMMENT 'move to',
  `is_undo_done` tinyint NOT NULL DEFAULT '1' COMMENT 'check undo step',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `status` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payrolls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payrolls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `position_id` bigint unsigned DEFAULT NULL,
  `everee_external_id` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission` double(8,2) DEFAULT NULL,
  `override` double(8,2) DEFAULT NULL,
  `reimbursement` double(8,2) DEFAULT NULL,
  `clawback` double(8,2) DEFAULT NULL,
  `deduction` double(8,2) DEFAULT NULL,
  `adjustment` double(8,2) DEFAULT NULL,
  `reconciliation` double(8,2) DEFAULT NULL,
  `hourly_salary` double(8,2) DEFAULT '0.00',
  `overtime` double(8,2) DEFAULT '0.00',
  `net_pay` double(8,2) DEFAULT NULL,
  `gross_pay` double(8,2) DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `status` int DEFAULT NULL,
  `is_mark_paid` tinyint(1) DEFAULT '0' COMMENT '0 for no, 1 for mark as paid',
  `is_next_payroll` tinyint(1) NOT NULL DEFAULT '0',
  `finalize_status` int DEFAULT '0' COMMENT '1 = finalising , 2 = finaliized , 3 = user-not-on-third-party',
  `everee_message` varchar(70) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_stop_payroll` tinyint NOT NULL DEFAULT '0',
  `deduction_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `ref_id` int DEFAULT '0',
  `custom_payment` double(8,2) DEFAULT NULL,
  `worker_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_user_id` (`user_id`),
  KEY `payrolls_pay_period` (`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`admin_flexpwr`@`%`*/ /*!50003 TRIGGER `update_worker_type_on_payroll` BEFORE INSERT ON `payrolls` FOR EACH ROW BEGIN
                DECLARE user_worker_type VARCHAR(255);

                SELECT worker_type INTO user_worker_type 
                FROM users 
                WHERE id = NEW.user_id;

                SET NEW.worker_type = user_worker_type;
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `paystub_employee`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `paystub_employee` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_employee_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_middle_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_zip_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_work_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_home_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_position_id` bigint DEFAULT NULL,
  `user_entity_type` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'individual',
  `user_social_sequrity_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_business_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_business_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_business_ein` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_name_of_bank` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_routing_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_account_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_type_of_account` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `company_zeal_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_phone_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Pest',
  `company_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_business_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_mailing_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_business_ein` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_business_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_business_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `company_business_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_business_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_business_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_mailing_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_mailing_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_mailing_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_time_zone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_business_address_1` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_business_address_2` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_business_lat` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_business_long` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_mailing_address_1` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_mailing_address_2` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_mailing_lat` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_mailing_long` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_business_address_time_zone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_mailing_address_time_zone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_margin` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `company_country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_lat` decimal(10,8) DEFAULT NULL,
  `company_lng` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_submodules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_submodules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_id` int DEFAULT NULL,
  `module_tab` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `policies_tabs_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pipeline_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pipeline_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pipeline_lead_status_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `comment_parent_id` bigint unsigned DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pipeline_lead_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pipeline_lead_status` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `status_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` tinyint DEFAULT NULL,
  `hide_status` tinyint NOT NULL DEFAULT '0',
  `colour_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#E4E9FF',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pipeline_leads_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pipeline_leads_status_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_status_id` int DEFAULT NULL,
  `new_status_id` int DEFAULT NULL,
  `updater_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pipeline_sub_task_complete_by_leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pipeline_sub_task_complete_by_leads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` int NOT NULL,
  `pipeline_sub_task_id` int NOT NULL,
  `completed` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `completed_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `pipeline_lead_status_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pipeline_sub_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pipeline_sub_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pipeline_lead_status_id` int NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pipline_lead_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pipline_lead_status` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `status_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` tinyint NOT NULL,
  `hide_status` tinyint NOT NULL DEFAULT '0',
  `colour_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#E4E9FF',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plan_with_add_on_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plan_with_add_on_plans` (
  `id` int NOT NULL,
  `plan_id` int NOT NULL DEFAULT '0',
  `add_on_plan_id` int NOT NULL DEFAULT '0',
  `created_at` date NOT NULL,
  `updated_at` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unique_pid_rack_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unique_pid_discount_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `m2_rack_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `m2_discount_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sclearance_plan_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `policies_tabs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `policies_tabs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `policies_id` bigint unsigned NOT NULL,
  `tabs` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `policies_tabs_policies_id_foreign` (`policies_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position_commission_deduction_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `position_commission_deduction_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `status` int DEFAULT NULL,
  `deducation_locked` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position_commission_deductions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `position_commission_deductions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `deduction_setting_id` bigint unsigned NOT NULL,
  `position_id` bigint unsigned NOT NULL,
  `cost_center_id` bigint unsigned NOT NULL,
  `deduction_type` enum('$','%') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position_commission_deductions` bigint unsigned DEFAULT NULL,
  `ammount_par_paycheck` double(8,2) DEFAULT NULL,
  `changes_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changes_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `position_commission_deductions_position_id_foreign` (`position_id`),
  KEY `position_commission_deductions_deduction_setting_id_foreign` (`deduction_setting_id`),
  KEY `position_commission_deductions_cost_center_id_foreign` (`cost_center_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position_commission_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `position_commission_overrides` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` bigint unsigned NOT NULL,
  `core_position_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `tiers_id` bigint unsigned DEFAULT NULL,
  `override_id` bigint unsigned NOT NULL,
  `settlement_id` bigint unsigned NOT NULL,
  `override_ammount` double(8,2) DEFAULT NULL,
  `override_ammount_locked` int DEFAULT NULL,
  `type` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `override_type_locked` int DEFAULT NULL,
  `tiers_hiring_locked` tinyint NOT NULL DEFAULT '0',
  `override_limit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `status` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `position_commission_overrides_position_id_foreign` (`position_id`),
  KEY `position_commission_overrides_override_id_foreign` (`override_id`),
  KEY `position_commission_overrides_settlement_id_foreign` (`settlement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position_commission_upfronts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `position_commission_upfronts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` bigint unsigned NOT NULL,
  `core_position_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `tiers_id` bigint unsigned DEFAULT NULL,
  `milestone_schema_id` bigint unsigned DEFAULT NULL,
  `milestone_schema_trigger_id` bigint unsigned DEFAULT NULL,
  `self_gen_user` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Not SelfGen, 1 = SelfGen',
  `status_id` bigint unsigned NOT NULL,
  `upfront_ammount` double(8,2) DEFAULT NULL,
  `upfront_ammount_locked` int DEFAULT NULL,
  `calculated_by` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `calculated_locked` int DEFAULT NULL,
  `upfront_status` int DEFAULT '1',
  `deductible_from_prior` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `upfront_system` enum('Tierd','Fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Fixed',
  `upfront_system_locked` int DEFAULT NULL,
  `tiers_hiring_locked` tinyint NOT NULL DEFAULT '0',
  `tiers_advancement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upfront_limit` double(8,2) DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `position_commission_upfronts_position_id_foreign` (`position_id`),
  KEY `position_commission_upfronts_status_id_foreign` (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position_commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `position_commissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` bigint unsigned NOT NULL,
  `core_position_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `tiers_id` bigint unsigned DEFAULT NULL,
  `self_gen_user` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Not SelfGen, 1 = SelfGen',
  `commission_parentage` double DEFAULT NULL,
  `commission_amount_type` enum('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `commission_status` tinyint DEFAULT '1' COMMENT '0 = Disabled, 1 = Enable',
  `commission_parentag_hiring_locked` int DEFAULT NULL,
  `commission_amount_type_locked` tinyint DEFAULT NULL,
  `tiers_hiring_locked` tinyint NOT NULL DEFAULT '0',
  `tiers_advancement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_structure_type` enum('Tiered','Fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_parentag_type_hiring_locked` int DEFAULT NULL,
  `commission_limit` decimal(15,2) DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `position_commissions_position_id_foreign` (`position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position_override_settlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `position_override_settlements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` bigint unsigned NOT NULL,
  `override_id` bigint unsigned NOT NULL,
  `sattlement_type` enum('Reconciliation','During M2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Reconciliation',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `position_override_settlements_position_id_foreign` (`position_id`),
  KEY `position_override_settlements_override_id_foreign` (`override_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position_pay_frequencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `position_pay_frequencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` bigint unsigned NOT NULL,
  `frequency_type_id` bigint unsigned NOT NULL,
  `first_months` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_day` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `day_of_week` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `day_of_months` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_per_days` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_day_pay_of_manths` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `second_pay_day_of_month` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deadline_to_run_payroll` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_pay_period_ends_on` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `position_pay_frequencies_frequency_type_id_foreign` (`frequency_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `position_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position_reconciliations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `position_reconciliations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` bigint unsigned NOT NULL,
  `core_position_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `commission_withheld` double(8,2) DEFAULT NULL,
  `commission_type` enum('per kw','per sale','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_withheld_locked` int DEFAULT NULL,
  `commission_type_locked` int DEFAULT NULL,
  `maximum_withheld` double(12,2) DEFAULT NULL,
  `tiers_commission_settlement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tiers_override_settlement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `override_settlement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clawback_settlement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stack_settlement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position_tier_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `position_tier_overrides` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` bigint unsigned DEFAULT NULL,
  `tier_status` int DEFAULT NULL,
  `sliding_scale` enum('Fixed','Tiered') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sliding_scale_locked` int DEFAULT NULL,
  `levels` enum('Multiple','Single') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level_locked` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `position_tier_overrides_position_id_foreign` (`position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position_tiers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `position_tiers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` bigint NOT NULL,
  `tiers_schema_id` bigint DEFAULT NULL,
  `tier_advancement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('commission','upfront','override') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `position_tiers_position_id_index` (`position_id`),
  KEY `position_tiers_tiers_schema_id_index` (`tiers_schema_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position_upfront_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `position_upfront_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `upfront_status` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `position_wages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `position_wages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` bigint NOT NULL,
  `pay_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hourly, Salary',
  `pay_type_lock` tinyint NOT NULL COMMENT '0 = Locked, 1 = Un-Locked',
  `pay_rate` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `pay_rate_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Per Hour, Weekly, Monthly, Bi-Weekly, Semi-Monthly',
  `pay_rate_lock` tinyint NOT NULL COMMENT '0 = Locked, 1 = Un-Locked',
  `pto_hours` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `pto_hours_lock` tinyint NOT NULL COMMENT '0 = Locked, 1 = Un-Locked',
  `unused_pto_expires` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Monthly, Annually, Accrues Continuously',
  `unused_pto_expires_lock` tinyint NOT NULL COMMENT '0 = Locked, 1 = Un-Locked',
  `expected_weekly_hours` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_weekly_hours_lock` tinyint NOT NULL COMMENT '0 = Locked, 1 = Un-Locked',
  `overtime_rate` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overtime_rate_lock` tinyint NOT NULL COMMENT '0 = Locked, 1 = Un-Locked',
  `wages_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Disabled, 1 = Enable',
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `positions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `worker_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '1099' COMMENT 'W9, 1099',
  `department_id` bigint unsigned NOT NULL,
  `parent_id` int DEFAULT NULL,
  `org_parent_id` int DEFAULT NULL,
  `group_id` int DEFAULT '1',
  `is_manager` int DEFAULT '1',
  `is_stack` int DEFAULT NULL,
  `is_selfgen` tinyint NOT NULL DEFAULT '0' COMMENT '0 = None, 1 = SelfGen, 2 = Closer, 3 = Setter',
  `order_by` int DEFAULT NULL,
  `offer_letter_template_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `setup_status` tinyint NOT NULL DEFAULT '0',
  `worker_id` enum('W2','1099') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'W2',
  `can_act_as_both_setter_and_closer` tinyint NOT NULL DEFAULT '0' COMMENT '0 = None, 1 = SelfGen, 2 = Closer, 3 = Setter',
  `applied_for_users` tinyint DEFAULT NULL COMMENT '0 for new user, 1 for all user',
  PRIMARY KEY (`id`),
  KEY `positions_department_id_foreign` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `positions_duduction_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `positions_duduction_limits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `deduction_setting_id` bigint unsigned DEFAULT NULL,
  `position_id` bigint unsigned DEFAULT NULL,
  `status` int unsigned DEFAULT '0',
  `limit_type` enum('$','%') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `limit_ammount` double(8,2) DEFAULT NULL,
  `limit` enum('per period') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'per period',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `positions_duduction_limits_position_id_foreign` (`position_id`),
  KEY `positions_duduction_limits_deduction_setting_id_foreign` (`deduction_setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `processed_ticket_counts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `processed_ticket_counts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ticketIDs` json DEFAULT NULL,
  `count` int unsigned NOT NULL DEFAULT '0',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `processed_date` datetime DEFAULT NULL,
  `status` tinyint unsigned NOT NULL DEFAULT '1',
  `integration_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `product_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_milestone_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_milestone_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned DEFAULT NULL,
  `milestone_schema_id` bigint unsigned DEFAULT NULL,
  `clawback_exempt_on_ms_trigger_id` bigint unsigned DEFAULT NULL,
  `override_on_ms_trigger_id` bigint NOT NULL DEFAULT '0',
  `product_redline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_milestone_histories_product_id_foreign` (`product_id`),
  KEY `product_milestone_histories_milestone_schema_id_foreign` (`milestone_schema_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `milestone_schema_id` bigint unsigned DEFAULT NULL,
  `clawback_exempt_on_ms_trigger_id` bigint unsigned DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `status` enum('1','0') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `products_milestone_schema_id_foreign` (`milestone_schema_id`),
  KEY `products_clawback_exempt_on_ms_trigger_id_foreign` (`clawback_exempt_on_ms_trigger_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `profile_access_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_access_permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_id` bigint unsigned DEFAULT NULL,
  `role_id` bigint unsigned DEFAULT NULL,
  `group_policies_id` bigint unsigned DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `profile_access_for` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payroll_history` int DEFAULT NULL,
  `reset_password` int DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `projection_user_commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projection_user_commissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `milestone_schema_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'M1, M2',
  `schema_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_trigger` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_last` tinyint NOT NULL DEFAULT '0' COMMENT 'Default 0, 1 = When last date hits',
  `value_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'm2' COMMENT 'm2, reconciliation',
  `amount` double(11,2) NOT NULL DEFAULT '0.00',
  `customer_signoff` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `projection_user_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projection_user_overrides` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_user_id` int DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position_id` int NOT NULL,
  `kw` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_override` double(8,2) DEFAULT NULL,
  `overrides_amount` double(8,2) DEFAULT NULL,
  `overrides_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `overrides_settlement_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_stop_payroll` tinyint DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recon_adjustment_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recon_adjustment_locks` (
  `id` bigint unsigned NOT NULL,
  `user_id` int DEFAULT NULL,
  `finalize_id` bigint unsigned NOT NULL,
  `pid` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `adjustment_type` enum('commission','override','clawback','deductions') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adjustment_override_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adjustment_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adjustment_comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `adjustment_by_user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payroll_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finalize_count` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_count` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payroll_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `payroll_execute_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sale_user_id` int DEFAULT NULL,
  `move_from_payroll` int DEFAULT '0',
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recon_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recon_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `sale_user_id` int DEFAULT NULL COMMENT 'override id',
  `finalize_id` bigint unsigned NOT NULL,
  `move_from_payroll` int DEFAULT '0' COMMENT 'check row is move to recon or not',
  `pid` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `adjustment_type` enum('commission','override','clawback','deductions') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adjustment_override_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adjustment_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adjustment_comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `adjustment_by_user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payroll_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finalize_count` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_count` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payroll_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `payroll_execute_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recon_clawback_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recon_clawback_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `finalize_id` bigint unsigned NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `move_from_payroll` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_count` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finalize_count` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_amount` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_amount` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payout` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payroll_execute_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `payroll_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_next_payroll` tinyint DEFAULT '0',
  `is_mark_paid` tinyint DEFAULT '0',
  `is_displayed` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sale_user_id` int DEFAULT NULL,
  `adders_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'm2' COMMENT 'override, and clawback types',
  `during` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'm2' COMMENT 'm2, m2 update',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recon_clawback_history_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recon_clawback_history_locks` (
  `id` bigint unsigned NOT NULL,
  `pid` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `finalize_id` bigint unsigned NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `move_from_payroll` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_count` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finalize_count` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_amount` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_amount` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payout` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payroll_execute_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `payroll_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_next_payroll` tinyint DEFAULT '0',
  `is_mark_paid` tinyint DEFAULT '0',
  `is_displayed` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sale_user_id` int DEFAULT NULL,
  `adders_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'm2' COMMENT 'override, and clawback types',
  `during` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'm2' COMMENT 'm2, m2 update',
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recon_commission_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recon_commission_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `finalize_id` bigint unsigned NOT NULL,
  `status` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_last` tinyint NOT NULL DEFAULT '0' COMMENT 'Default 0, 1 = When last date hits',
  `during` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'm2' COMMENT 'm2, m2 update',
  `move_from_payroll` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_count` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'send to payroll count',
  `finalize_count` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'finalize data count',
  `total_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payout` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payroll_execute_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `payroll_id` int DEFAULT '0' COMMENT 'payroll table id',
  `is_next_payroll` tinyint DEFAULT NULL,
  `ref_id` bigint DEFAULT NULL,
  `is_mark_paid` tinyint DEFAULT NULL,
  `is_ineligible` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Eligible, 1 = Ineligible',
  `is_deducted` tinyint NOT NULL DEFAULT '0',
  `is_displayed` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recon_commission_history_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recon_commission_history_locks` (
  `id` bigint unsigned NOT NULL,
  `pid` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `finalize_id` bigint unsigned NOT NULL,
  `status` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_last` tinyint NOT NULL DEFAULT '0' COMMENT 'Default 0, 1 = When last date hits',
  `during` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'm2' COMMENT 'm2, m2 update',
  `move_from_payroll` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_count` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'send to payroll count',
  `finalize_count` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'finalize data count',
  `total_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payout` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payroll_execute_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `payroll_id` int DEFAULT '0' COMMENT 'payroll table id',
  `is_next_payroll` tinyint DEFAULT NULL,
  `ref_id` bigint DEFAULT NULL,
  `is_mark_paid` tinyint DEFAULT NULL,
  `is_displayed` tinyint DEFAULT '1',
  `is_ineligible` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Eligible, 1 = Ineligible',
  `is_deducted` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recon_deduction_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recon_deduction_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_center_id` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finalize_id` bigint unsigned NOT NULL,
  `amount` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `limit` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outstanding` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finalize_count` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `payroll_executed_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `is_mark_paid` tinyint DEFAULT NULL,
  `is_next_payroll` tinyint DEFAULT NULL,
  `is_stop_payroll` tinyint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recon_deduction_history_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recon_deduction_history_locks` (
  `id` bigint unsigned NOT NULL,
  `payroll_id` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_center_id` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finalize_id` bigint unsigned NOT NULL,
  `amount` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `limit` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outstanding` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finalize_count` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `payroll_executed_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `is_mark_paid` tinyint DEFAULT NULL,
  `is_next_payroll` tinyint DEFAULT NULL,
  `is_stop_payroll` tinyint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recon_deduction_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recon_deduction_locks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `cost_center_id` bigint unsigned NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `limit` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `outstanding` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `finalize_count` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payroll_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint DEFAULT '1',
  `is_mark_paid` tinyint DEFAULT '0',
  `is_next_payroll` tinyint DEFAULT '0',
  `is_stop_payroll` tinyint DEFAULT '0',
  `ref_id` int DEFAULT '0',
  `is_move_to_recon` tinyint DEFAULT '0',
  `is_move_to_recon_paid` tinyint DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recon_override_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recon_override_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `override_id` varchar(15) DEFAULT NULL,
  `pid` varchar(199) DEFAULT NULL,
  `user_id` bigint DEFAULT NULL,
  `finalize_id` bigint unsigned NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `sent_count` int DEFAULT NULL,
  `customer_name` varchar(199) DEFAULT NULL,
  `overrider` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `during` varchar(255) DEFAULT 'm2' COMMENT 'override types',
  `move_from_payroll` varchar(255) DEFAULT NULL,
  `kw` double DEFAULT NULL,
  `override_amount` double DEFAULT NULL,
  `total_amount` double DEFAULT NULL,
  `paid` double DEFAULT NULL,
  `percentage` double DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `payroll_execute_status` varchar(10) NOT NULL DEFAULT '0',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `payroll_id` varchar(10) NOT NULL DEFAULT '0',
  `is_next_payroll` tinyint DEFAULT NULL,
  `ref_id` bigint DEFAULT NULL,
  `is_mark_paid` tinyint DEFAULT NULL,
  `is_displayed` enum('0','1') NOT NULL DEFAULT '1' COMMENT '0 = Old, 1 = In Display',
  `is_ineligible` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Eligible, 1 = Ineligible',
  `finalize_count` varchar(255) DEFAULT NULL,
  `overrides_settlement_type` enum('reconciliation','during_m2') NOT NULL DEFAULT 'reconciliation',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recon_override_history_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recon_override_history_locks` (
  `id` bigint unsigned NOT NULL,
  `override_id` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint DEFAULT NULL,
  `finalize_id` bigint unsigned NOT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_count` bigint DEFAULT NULL,
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overrider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `during` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'm2' COMMENT 'override types',
  `move_from_payroll` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kw` double(8,2) DEFAULT NULL,
  `override_amount` double(8,2) DEFAULT NULL,
  `total_amount` double(8,2) DEFAULT NULL,
  `paid` double(8,2) DEFAULT NULL,
  `percentage` double(8,2) DEFAULT NULL,
  `payroll_execute_status` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `payroll_id` bigint DEFAULT NULL,
  `is_next_payroll` tinyint DEFAULT NULL,
  `ref_id` bigint DEFAULT NULL,
  `is_mark_paid` tinyint DEFAULT NULL,
  `is_displayed` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '0 = Old, 1 = In Display',
  `is_ineligible` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Eligible, 1 = Ineligible',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `finalize_count` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overrides_settlement_type` enum('reconciliation','during_m2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reconciliation',
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reconciliation_finalize`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reconciliation_finalize` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `office_id` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `position_id` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `executed_on` date DEFAULT NULL,
  `commissions` double NOT NULL DEFAULT '0',
  `overrides` double NOT NULL DEFAULT '0',
  `total_due` double NOT NULL DEFAULT '0',
  `clawbacks` double NOT NULL DEFAULT '0',
  `adjustments` double NOT NULL DEFAULT '0',
  `deductions` double NOT NULL DEFAULT '0',
  `remaining` double NOT NULL DEFAULT '0',
  `payout_percentage` int NOT NULL,
  `net_amount` double NOT NULL DEFAULT '0',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'finalize' COMMENT 'finalize, payroll',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `is_upfront` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reconciliation_finalize_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reconciliation_finalize_history` (
  `user_id` bigint DEFAULT NULL,
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finalize_id` bigint unsigned NOT NULL,
  `office_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `position_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `executed_on` date DEFAULT NULL,
  `sent_count` int DEFAULT NULL,
  `commission` double DEFAULT NULL,
  `override` double DEFAULT NULL,
  `paid_commission` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_override` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clawback` double DEFAULT NULL,
  `adjustments` double DEFAULT NULL,
  `remaining` double NOT NULL DEFAULT '0',
  `gross_amount` double DEFAULT NULL,
  `payout` double DEFAULT NULL,
  `net_amount` double DEFAULT NULL,
  `percentage_pay_amount` double(8,2) NOT NULL DEFAULT '0.00',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `payroll_id` bigint DEFAULT NULL,
  `is_upfront` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `is_next_payroll` tinyint DEFAULT '0',
  `is_mark_paid` tinyint DEFAULT '0',
  `is_stop_payroll` tinyint DEFAULT '0',
  `is_displayed` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '0 = Old, 1 = In Display',
  `ref_id` int DEFAULT '0',
  `payroll_status` tinyint DEFAULT '1',
  `user_recon_is_skip` tinyint DEFAULT '0',
  `move_from_payroll_flag` tinyint DEFAULT '0',
  `move_from_payroll_row_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deductions` double(8,2) DEFAULT NULL,
  `finalize_count` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `payroll_execute_status` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reconciliation_finalize_history_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reconciliation_finalize_history_locks` (
  `id` bigint unsigned NOT NULL,
  `user_id` bigint DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finalize_id` bigint unsigned NOT NULL,
  `office_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `executed_on` date DEFAULT NULL,
  `commission` double(8,2) DEFAULT NULL,
  `override` double(8,2) DEFAULT NULL,
  `paid_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_override` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clawback` double(8,2) DEFAULT NULL,
  `adjustments` double(8,2) DEFAULT NULL,
  `remaining` double NOT NULL DEFAULT '0',
  `deductions` double(8,2) DEFAULT NULL,
  `gross_amount` double(8,2) DEFAULT NULL,
  `payout` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `net_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `payroll_id` bigint DEFAULT NULL,
  `sent_count` bigint NOT NULL DEFAULT '0',
  `is_mark_paid` tinyint(1) NOT NULL DEFAULT '0',
  `is_next_payroll` tinyint(1) NOT NULL DEFAULT '0',
  `is_stop_payroll` tinyint(1) NOT NULL DEFAULT '0',
  `is_displayed` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1' COMMENT '0 = Old, 1 = In Display',
  `ref_id` bigint DEFAULT NULL,
  `move_from_payroll_row_id` bigint DEFAULT NULL,
  `move_from_payroll_flag` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `finalize_count` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `payroll_execute_status` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  `percentage_pay_amount` double(8,2) NOT NULL DEFAULT '0.00',
  `is_upfront` tinyint(1) NOT NULL DEFAULT '0',
  `payroll_status` tinyint DEFAULT '1',
  `user_recon_is_skip` tinyint DEFAULT '0',
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reconciliation_finalize_lock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reconciliation_finalize_lock` (
  `id` bigint unsigned NOT NULL,
  `office_id` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `position_id` tinytext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `executed_on` date DEFAULT NULL,
  `commissions` double NOT NULL DEFAULT '0',
  `overrides` double NOT NULL DEFAULT '0',
  `total_due` double NOT NULL DEFAULT '0',
  `clawbacks` double NOT NULL DEFAULT '0',
  `adjustments` double NOT NULL DEFAULT '0',
  `deductions` double NOT NULL DEFAULT '0',
  `remaining` double NOT NULL DEFAULT '0',
  `payout_percentage` int NOT NULL,
  `net_amount` double NOT NULL DEFAULT '0',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'finalize' COMMENT 'finalize, payroll',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_upfront` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reconciliation_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reconciliation_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `day_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reconciliations_adjustement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reconciliations_adjustement` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint DEFAULT NULL,
  `reconciliation_id` bigint DEFAULT NULL,
  `payroll_id` int DEFAULT NULL,
  `sent_count` int DEFAULT NULL,
  `pid` varchar(191) DEFAULT NULL,
  `adjustment_type` varchar(255) DEFAULT 'reconciliations',
  `override_type` varchar(191) DEFAULT NULL,
  `payroll_move_status` varchar(191) DEFAULT NULL,
  `commission_due` double DEFAULT NULL,
  `overrides_due` double DEFAULT NULL,
  `reimbursement` double(8,2) DEFAULT NULL,
  `deduction` double(8,2) DEFAULT NULL,
  `adjustment` double(8,2) DEFAULT NULL,
  `reconciliation` double(8,2) DEFAULT NULL,
  `clawback_due` double(8,2) DEFAULT NULL,
  `payroll_status` varchar(255) DEFAULT NULL,
  `start_date` varchar(255) DEFAULT NULL,
  `end_date` varchar(255) DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `comment` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` varchar(255) DEFAULT NULL,
  `comment_by` varchar(255) DEFAULT NULL,
  `payroll_execute_status` varchar(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reconciliations_adjustement_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reconciliations_adjustement_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` bigint DEFAULT NULL,
  `pid` varchar(199) DEFAULT NULL,
  `type` varchar(199) DEFAULT NULL,
  `adjustment_type` varchar(199) DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `comment` text,
  `created_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `comment_by` varchar(255) DEFAULT NULL,
  `payroll_execute_status` varchar(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reconciliationStatusForSkipedUser`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reconciliationStatusForSkipedUser` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `request_approval_by_pid`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `request_approval_by_pid` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `s_clearance_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `s_clearance_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` bigint unsigned DEFAULT NULL,
  `hiring_status` bigint unsigned DEFAULT NULL,
  `is_mandatory` tinyint NOT NULL DEFAULT '0',
  `is_approval_required` tinyint NOT NULL DEFAULT '0',
  `package_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `s_clearance_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `s_clearance_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bundle_id` int DEFAULT NULL,
  `plan_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(10,0) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `s_clearance_screening_request_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `s_clearance_screening_request_lists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `middle_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_type_id` bigint unsigned DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `position_id` bigint unsigned DEFAULT NULL,
  `office_id` bigint unsigned DEFAULT NULL,
  `applicant_id` bigint unsigned DEFAULT NULL,
  `screening_request_id` bigint unsigned DEFAULT NULL,
  `screening_request_applicant_id` bigint unsigned DEFAULT NULL,
  `exam_id` bigint unsigned DEFAULT NULL,
  `is_report_generated` tinyint NOT NULL DEFAULT '0',
  `is_manual_verification` tinyint NOT NULL DEFAULT '0',
  `date_sent` date DEFAULT NULL,
  `report_date` date DEFAULT NULL,
  `report_expiry_date` date DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_declined_by` bigint DEFAULT NULL,
  `exam_attempts` tinyint NOT NULL DEFAULT '0',
  `plan_id` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `s_clearance_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `s_clearance_statuses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `status_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `s_clearance_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `s_clearance_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `mfa_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `token_key_used` tinyint DEFAULT NULL,
  `mfa_token_key_used` tinyint DEFAULT NULL,
  `expiration_time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mfa_expiration_time` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `s_clearance_transunion_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `s_clearance_transunion_responses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `screening_request_applicant_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_manual_verification` tinyint DEFAULT NULL,
  `response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `s_clearance_turn_package_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `s_clearance_turn_package_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `s_clearance_turn_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `s_clearance_turn_responses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `turn_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `worker_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `webhook_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `s_clearance_turn_screening_request_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `s_clearance_turn_screening_request_lists` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `middle_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_middle_name` tinyint NOT NULL DEFAULT '0',
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_type_id` bigint unsigned DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `position_id` bigint unsigned DEFAULT NULL,
  `office_id` bigint unsigned DEFAULT NULL,
  `zipcode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form_check_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `turn_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'The shortened version of UUID',
  `worker_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'full UUID(transaction_uuid) of the worker',
  `date_sent` date DEFAULT NULL,
  `report_date` date DEFAULT NULL,
  `is_report_generated` tinyint NOT NULL DEFAULT '0',
  `approved_declined_by` bigint unsigned DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `s_clearance_turn_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `s_clearance_turn_statuses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `status_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_data_update_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_data_update_logs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `pid` varchar(255) NOT NULL,
  `message_text` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_master_process`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_master_process` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_master_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weekly_sheet_id` bigint unsigned DEFAULT NULL,
  `closer1_id` bigint unsigned DEFAULT NULL,
  `closer2_id` bigint unsigned DEFAULT NULL,
  `setter1_id` bigint unsigned DEFAULT NULL,
  `setter2_id` bigint unsigned DEFAULT NULL,
  `closer1_m1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer2_m1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter1_m1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter2_m1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer1_m2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer2_m2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter1_m2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter2_m2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer1_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer2_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter1_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `setter2_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `closer1_m1_paid_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer2_m1_paid_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter1_m1_paid_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter2_m1_paid_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer1_m2_paid_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer2_m2_paid_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter1_m2_paid_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter2_m2_paid_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer1_m1_paid_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer2_m1_paid_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter1_m1_paid_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter2_m1_paid_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer1_m2_paid_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer2_m2_paid_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter1_m2_paid_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter2_m2_paid_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mark_account_status_id` bigint unsigned DEFAULT NULL,
  `pid_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_source_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`admin_flexpwr`@`%`*/ /*!50003 TRIGGER `before_insert_sale_master_process` BEFORE INSERT ON `sale_master_process` FOR EACH ROW BEGIN
    
    UPDATE sale_masters
    SET closer1_name = (
        SELECT CONCAT(first_name, ' ', last_name)
        FROM `users`
        WHERE id = NEW.closer1_id
        LIMIT 1
    ),
    closer1_id = NEW.closer1_id
    WHERE sale_masters.id = NEW.sale_master_id;

    
    UPDATE sale_masters
    SET closer2_name = (
        SELECT CONCAT(first_name, ' ', last_name)
        FROM `users`
        WHERE id = NEW.closer2_id
        LIMIT 1
    ),
    closer2_id = NEW.closer2_id
    WHERE sale_masters.id = NEW.sale_master_id;

    
    UPDATE sale_masters
    SET setter1_name = (
        SELECT CONCAT(first_name, ' ', last_name)
        FROM `users`
        WHERE id = NEW.setter1_id
        LIMIT 1
    ),
    setter1_id = NEW.setter1_id
    WHERE sale_masters.id = NEW.sale_master_id;

    
    UPDATE sale_masters
    SET setter2_name = (
        SELECT CONCAT(first_name, ' ', last_name)
        FROM `users`
        WHERE id = NEW.setter2_id
        LIMIT 1
    ),
    setter2_id = NEW.setter2_id
    WHERE sale_masters.id = NEW.sale_master_id;

END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`admin_flexpwr`@`%`*/ /*!50003 TRIGGER `before_update_sale_master_process` BEFORE UPDATE ON `sale_master_process` FOR EACH ROW BEGIN
    
    UPDATE sale_masters
    SET closer1_name = (
        SELECT CONCAT(first_name, ' ', last_name)
        FROM `users`
        WHERE id = NEW.closer1_id
        LIMIT 1
    ),
    closer1_id = NEW.closer1_id
    WHERE sale_masters.id = NEW.sale_master_id;

    
    UPDATE sale_masters
    SET closer2_name = (
        SELECT CONCAT(first_name, ' ', last_name)
        FROM `users`
        WHERE id = NEW.closer2_id
        LIMIT 1
    ),
    closer2_id = NEW.closer2_id
    WHERE sale_masters.id = NEW.sale_master_id;

    
    UPDATE sale_masters
    SET setter1_name = (
        SELECT CONCAT(first_name, ' ', last_name)
        FROM `users`
        WHERE id = NEW.setter1_id
        LIMIT 1
    ),
    setter1_id = NEW.setter1_id
    WHERE sale_masters.id = NEW.sale_master_id;

    
    UPDATE sale_masters
    SET setter2_name = (
        SELECT CONCAT(first_name, ' ', last_name)
        FROM `users`
        WHERE id = NEW.setter2_id
        LIMIT 1
    ),
    setter2_id = NEW.setter2_id
    WHERE sale_masters.id = NEW.sale_master_id;

END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `sale_master_projections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_master_projections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer1_id` bigint unsigned DEFAULT NULL,
  `closer2_id` bigint unsigned DEFAULT NULL,
  `setter1_id` bigint unsigned DEFAULT NULL,
  `setter2_id` bigint unsigned DEFAULT NULL,
  `closer1_m1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `closer2_m1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `setter1_m1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `setter2_m1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `closer1_m2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `closer2_m2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `setter1_m2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `setter2_m2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `closer1_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `closer2_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `setter1_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `setter2_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_masters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_masters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ticket_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `initialStatusText` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `appointment_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer1_id` bigint unsigned DEFAULT NULL,
  `setter1_id` bigint unsigned DEFAULT NULL,
  `closer2_id` bigint unsigned DEFAULT NULL,
  `setter2_id` bigint unsigned DEFAULT NULL,
  `closer1_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter1_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer2_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter2_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prospect_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panel_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `panel_id` int NOT NULL,
  `weekly_sheet_id` bigint unsigned DEFAULT NULL,
  `install_partner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `install_partner_id` int DEFAULT NULL,
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `customer_address_2` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `customer_longitude` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_latitude` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_code` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `homeowner_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proposal_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_rep_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_id` int DEFAULT NULL,
  `sales_rep_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kw` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `balance_age` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_cancelled` date DEFAULT NULL,
  `customer_signoff` date DEFAULT NULL COMMENT 'Approved date',
  `m1_date` date DEFAULT NULL,
  `m2_date` date DEFAULT NULL,
  `product` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_exempted` tinyint NOT NULL DEFAULT '0' COMMENT 'Default 0, 1 If exempted',
  `total_commission_amount` double(11,2) NOT NULL DEFAULT '0.00',
  `total_override_amount` double(11,2) NOT NULL DEFAULT '0.00',
  `milestone_trigger` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `gross_account_value` double(15,3) DEFAULT NULL,
  `epc` float DEFAULT NULL,
  `net_epc` float DEFAULT NULL,
  `dealer_fee_percentage` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dealer_fee_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adders` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SOW amount',
  `adders_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` int DEFAULT NULL,
  `total_amount_for_acct` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prev_amount_paid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_date_pd` date DEFAULT NULL,
  `m1_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `m2_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prev_deducted_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_fee` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancel_deduction` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lead_cost_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adv_pay_back_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_amount_in_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `funding_source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `financing_rate` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `financing_term` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scheduled_install` date DEFAULT NULL,
  `install_complete_date` date DEFAULT NULL,
  `return_sales_date` date DEFAULT NULL,
  `cash_amount` double(11,3) DEFAULT NULL,
  `loan_amount` double(11,3) DEFAULT NULL,
  `length_of_agreement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_schedule` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `initial_service_cost` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_pay` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `card_on_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subscription_payment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_completed` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_service_date` date DEFAULT NULL,
  `initial_service_date` date DEFAULT NULL,
  `bill_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_type` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Sales',
  `data_source_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trigger_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_item_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Old, 1 = In Action Item',
  `total_commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `projected_commission` tinyint NOT NULL DEFAULT '1' COMMENT '0 = Non Projected, 1 = Projected',
  `total_override` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `projected_override` tinyint NOT NULL DEFAULT '1' COMMENT '0 = Non Projected, 1 = Projected',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sale_masters_pid_unique` (`pid`),
  KEY `sale_masters_weekly_sheet_id_foreign` (`weekly_sheet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`admin_flexpwr`@`%`*/ /*!50003 TRIGGER `update_sale_invoice_on_kw_update` AFTER UPDATE ON `sale_masters` FOR EACH ROW BEGIN
    DECLARE invoice_id INT;
    IF NEW.kw != OLD.kw THEN
    	SET invoice_id = (SELECT id
                      FROM sales_invoice_details
                      WHERE pid = NEW.pid
                      ORDER BY id DESC
                      LIMIT 1);

       	UPDATE sales_invoice_details
            SET updated_kw = NEW.kw,
                updated_kw_date = NOW()
            WHERE id = invoice_id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `sale_product_master`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_product_master` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `milestone_id` bigint unsigned NOT NULL,
  `milestone_schema_id` bigint unsigned NOT NULL,
  `milestone_date` date DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_last_date` tinyint DEFAULT '0' COMMENT 'Default 0, 1 = When last date hits',
  `is_exempted` tinyint NOT NULL DEFAULT '0' COMMENT 'Default 0, 1 If exempted',
  `is_override` tinyint(1) NOT NULL DEFAULT '0',
  `is_projected` tinyint NOT NULL DEFAULT '1' COMMENT '0 = Non Projected, 1 = Projected',
  `is_paid` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Non Paid, 1 = Paid',
  `setter1_id` bigint unsigned DEFAULT NULL,
  `setter2_id` bigint unsigned DEFAULT NULL,
  `closer1_id` bigint unsigned DEFAULT NULL,
  `closer2_id` bigint unsigned DEFAULT NULL,
  `amount` double(11,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sale_product_master_pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`admin_flexpwr`@`%`*/ /*!50003 TRIGGER `update_m2_date_on_insert_update` AFTER INSERT ON `sale_product_master` FOR EACH ROW BEGIN
        IF NEW.is_last_date = 1 THEN
            UPDATE sale_masters
            SET m2_date = NEW.milestone_date
            WHERE pid = NEW.pid;

            UPDATE legacy_api_data_null
            SET m2_date = NEW.milestone_date
            WHERE pid = NEW.pid;
        END IF;
    END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`admin_flexpwr`@`%`*/ /*!50003 TRIGGER `update_m2_date_on_update` AFTER UPDATE ON `sale_product_master` FOR EACH ROW BEGIN
        IF NEW.is_last_date = 1 THEN
            UPDATE sale_masters
            SET m2_date = NEW.milestone_date
            WHERE pid = NEW.pid;

            UPDATE legacy_api_data_null
            SET m2_date = NEW.milestone_date
            WHERE pid = NEW.pid;
        END IF;
    END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `sale_tiers_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_tiers_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `schema_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `tier_level` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_tiered` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Non Tier, 1 = Tiered',
  `tiers_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Progressive, Retroactive',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Commission, Upfront, Override',
  `sub_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Commission = (Commission), Upfront = (Milestone Like m1, m2), Override = (Office, Additional Office, Direct, InDirect)',
  `is_locked` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Not Locked, 1 = Locked',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sale_tiers_master`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sale_tiers_master` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tier_schema_id` bigint unsigned DEFAULT NULL,
  `tier_schema_level_id` bigint unsigned DEFAULT NULL,
  `setter1_id` bigint unsigned DEFAULT NULL,
  `setter2_id` bigint unsigned DEFAULT NULL,
  `closer1_id` bigint unsigned DEFAULT NULL,
  `closer2_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_invoice_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_invoice_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_master_id` int NOT NULL COMMENT 'if invoice_for = payroll_histry/one_time_paymment then id ',
  `data_from` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if invoice_for = payroll_histry/one_time_paymment then user_first_last_name ',
  `customer_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if invoice_for = payroll_histry/one_time_paymment then everee_external_id ',
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if invoice_for = payroll_histry/one_time_paymment then everee_paymentId ',
  `kw` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'if invoice_for = payroll_histry/one_time_paymment then net_pay ',
  `customer_signoff` date DEFAULT NULL COMMENT 'if invoice_for = payroll_histry/one_time_paymment then everee_payment_requestId ',
  `m1_date` date DEFAULT NULL COMMENT 'if invoice_for = payroll_histry/one_time_paymment then pay_period_from',
  `m2_date` date DEFAULT NULL COMMENT 'if invoice_for = payroll_histry/one_time_paymment then pay_period_to',
  `invoice_for` enum('unique_pid','m2_date','payroll_histry','one_time_paymment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unique_pid',
  `billing_history_id` int NOT NULL COMMENT 'id from subscription_billing_histories ',
  `invoice_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_date` date DEFAULT NULL,
  `updated_kw` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_kw_date` date DEFAULT NULL,
  `is_kw_adjusted_invoice` tinyint DEFAULT '0',
  `invoice_generated_on_kw` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_offices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_offices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `office_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` int DEFAULT NULL,
  `state_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint unsigned NOT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schedule_time_masters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedule_time_masters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `day` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_slot` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `scheduling_approval_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scheduling_approval_setting` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scheduling_setting` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `scheduling_configuration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scheduling_configuration` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `clock_format` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_lunch_dutration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schema_trigger_dates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_trigger_dates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seasonal_users_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seasonal_users_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `api` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `col1` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `col2` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sent_offer_letters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sent_offer_letters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_id` bigint unsigned NOT NULL COMMENT 'template_id of offer letter',
  `onboarding_employee_id` bigint unsigned NOT NULL COMMENT 'offerletter template sent to onboarding_employee',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sequi_docs_document_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sequi_docs_document_comments` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `document_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `template_id` int DEFAULT NULL,
  `document_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'send document name like offer letter or other doc like W9',
  `user_id_from` enum('users','onboarding_employees') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'onboarding_employees',
  `comment_user_id_from` enum('users','onboarding_employees') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'users',
  `document_send_to_user_id` int NOT NULL,
  `comment_by_id` int NOT NULL,
  `comment_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sequi_docs_email_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sequi_docs_email_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tempate_id` int DEFAULT NULL,
  `email_template_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unique_email_template_code` int DEFAULT NULL,
  `tmp_page_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `category_id` int DEFAULT NULL,
  `email_subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_trigger` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 for active, 0 for deactivated',
  `email_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email_template_code` (`unique_email_template_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sequi_docs_send_agreement_with_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sequi_docs_send_agreement_with_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `position_id` int NOT NULL COMMENT 'position_id for send aggrement template to person',
  `aggrement_template_id` int NOT NULL COMMENT 'agreement template id for send',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sequi_docs_template_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sequi_docs_template_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `position_type` enum('permission','receipient') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'permission',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sequi_docs_template_signature`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sequi_docs_template_signature` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `category_id` int NOT NULL,
  `additional_signature` int NOT NULL,
  `required_check` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sequiai_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sequiai_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_request` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `additional_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `additional_min_request` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sequiai_request_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sequiai_request_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `subscription_billing_history_id` int DEFAULT NULL,
  `user_prompt_type` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_prompt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0' COMMENT '0=>Not get in billing, 1=>Billing done',
  `sequiai_plan_id` int DEFAULT NULL,
  `billing_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `set_goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `set_goals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` bigint DEFAULT NULL,
  `earning` varchar(255) DEFAULT NULL,
  `account` varchar(255) DEFAULT NULL,
  `kw_sold` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `setter_identify_alert`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `setter_identify_alert` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_rep_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `setter_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `settings_key_index` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `state_mvr_costs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `state_mvr_costs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `states` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stripe_response_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stripe_response_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sub_commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sub_commissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commissions_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sub_commissions_commissions_id_foreign` (`commissions_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_billing_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_billing_histories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subscription_id` int NOT NULL DEFAULT '0',
  `amount` double NOT NULL DEFAULT '0',
  `paid_status` int NOT NULL DEFAULT '0',
  `invoice_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_date` datetime DEFAULT NULL,
  `plan_id` int DEFAULT NULL,
  `plan_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unique_pid_rack_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unique_pid_discount_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `m2_rack_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `m2_discount_price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_id` int DEFAULT NULL,
  `client_secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payment_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_payment_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plan_type_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0',
  `paid_status` int NOT NULL DEFAULT '0',
  `total_pid` int DEFAULT '0' COMMENT 'count of unique pid',
  `total_m2` int DEFAULT '0' COMMENT 'count of m2',
  `sales_tax_per` decimal(8,2) DEFAULT NULL,
  `sales_tax_amount` decimal(8,2) DEFAULT NULL,
  `amount` double(8,2) NOT NULL DEFAULT '0.00',
  `credit_amount` decimal(8,2) DEFAULT '0.00',
  `used_credit` decimal(8,2) DEFAULT '0.00',
  `balance_credit` decimal(8,2) DEFAULT '0.00',
  `taxable_amount` decimal(8,2) DEFAULT '0.00',
  `minimum_billing` double(10,2) DEFAULT '0.00',
  `grand_total` decimal(8,2) NOT NULL,
  `flat_subscription` tinyint(1) NOT NULL DEFAULT '0',
  `active_user_billing` tinyint NOT NULL DEFAULT '1',
  `paid_active_user_billing` tinyint NOT NULL DEFAULT '0',
  `sale_approval_active_user_billing` tinyint NOT NULL DEFAULT '0',
  `logged_in_active_user_billing` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tax_document_checks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tax_document_checks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_year` year NOT NULL,
  `document_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tax_document_check` (`user_id`,`tax_year`,`document_type`),
  CONSTRAINT `tax_document_checks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `temp_payroll_finalize_execute_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `temp_payroll_finalize_execute_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `net_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'SUCCESS' COMMENT 'ERROR, SUCCESS',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '1099 Finalize, W2 Finalize, Execute',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `template_assigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `template_assigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `template_id` bigint unsigned DEFAULT NULL,
  `assign_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `template_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `template_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `categories` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_type` enum('user_editable','system_fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'user_editable',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `template_generates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `template_generates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `template_id` bigint unsigned DEFAULT NULL,
  `company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manager_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `due` int DEFAULT NULL,
  `due_date` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `template_metas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `template_metas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `meta_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_by` int DEFAULT NULL,
  `categery_id` bigint unsigned DEFAULT NULL,
  `template_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `template_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_sign_required_for_hire` smallint DEFAULT '1' COMMENT '0 for not required, 1 for required',
  `template_content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `template_agreements` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dynamic_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recipient_sign_req` int DEFAULT NULL,
  `self_sign_req` int DEFAULT NULL,
  `add_sign` int DEFAULT NULL,
  `template_comment` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `manager_sign_req` int NOT NULL DEFAULT '0',
  `completed_step` int NOT NULL DEFAULT '0',
  `recruiter_sign_req` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1-sign required 0-not required',
  `add_recruiter_sign_req` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1-sign required 0-not required',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_exported` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `templates_categery_id_foreign` (`categery_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `test_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `test_data` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `attachment_type` varchar(255) NOT NULL,
  `attachment_id` bigint unsigned NOT NULL,
  `original_file_name` varchar(255) NOT NULL,
  `system_file_name` varchar(255) NOT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `size` varchar(255) NOT NULL,
  `jira_id` bigint unsigned DEFAULT NULL,
  `jira_synced` enum('0','1') NOT NULL DEFAULT '0' COMMENT '0 = Not Synced, 1 = Synced',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_attachments_attachment_type_attachment_id_index` (`attachment_type`,`attachment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_faq_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_faq_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `order` tinyint DEFAULT NULL,
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '0 = InActive, 1 = Active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_faqs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `faq_category_id` bigint DEFAULT NULL,
  `question` tinytext,
  `answer` longtext,
  `status` enum('0','1') NOT NULL DEFAULT '1' COMMENT '0 = InActive, 1 = Active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ticket_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jira_id` varchar(255) NOT NULL,
  `jira_key` varchar(255) NOT NULL,
  `jira_summary` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_by` bigint unsigned NOT NULL,
  `ticket_id` varchar(255) NOT NULL,
  `jira_ticket_id` varchar(255) DEFAULT NULL,
  `summary` tinytext NOT NULL,
  `priority` varchar(255) DEFAULT NULL,
  `description` longtext,
  `module` varchar(255) DEFAULT NULL,
  `jira_module_id` varchar(255) DEFAULT NULL,
  `is_jira_created` enum('0','1') NOT NULL DEFAULT '0' COMMENT '0 = Not Created, 1 = Created',
  `last_jira_sync_date` datetime DEFAULT NULL,
  `ticket_status` varchar(255) DEFAULT NULL,
  `estimated_time` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tier_durations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tier_durations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tier_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tier_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `symbol` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tier_system_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tier_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tier_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `status` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tier_systems`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tier_systems` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tiers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tiers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tier_type_id` bigint unsigned NOT NULL,
  `tier_setting_id` bigint unsigned NOT NULL,
  `scale_based_on` enum('Monthly','Bi-Monthly','Quaterly','Semi-Annually','Annually') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shifts_on` enum('Monthly','Bi-Monthly','Quaterly','Semi-Annually','Annually') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rest` enum('Monthly','Bi-Monthly','Quaterly','Semi-Annually','Annually') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tiers_tier_type_id_foreign` (`tier_type_id`),
  KEY `tiers_tier_setting_id_foreign` (`tier_setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tiers_configure`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tiers_configure` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tier_type_id` bigint unsigned NOT NULL,
  `installs_to` double(8,2) DEFAULT NULL,
  `redline_shift` double(8,2) DEFAULT NULL,
  `installs_from` double(8,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tiers_configure_tier_type_id_foreign` (`tier_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tiers_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tiers_levels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tiers_schema_id` int NOT NULL,
  `level` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tiers_position_commisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tiers_position_commisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` int DEFAULT NULL,
  `position_commission_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `tiers_schema_id` int DEFAULT NULL,
  `tiers_levels_id` bigint unsigned DEFAULT NULL,
  `tiers_advancement` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `commission_limit` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tiers_position_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tiers_position_overrides` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` int DEFAULT NULL,
  `position_overrides_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `override_id` int DEFAULT NULL,
  `tiers_schema_id` int DEFAULT NULL,
  `tiers_levels_id` bigint unsigned DEFAULT NULL,
  `override_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `override_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tiers_position_upfronts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tiers_position_upfronts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` int DEFAULT NULL,
  `position_upfront_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `milestone_schema_id` int DEFAULT NULL,
  `milestone_schema_trigger_id` int DEFAULT NULL,
  `tiers_schema_id` int DEFAULT NULL,
  `tiers_levels_id` bigint unsigned DEFAULT NULL,
  `upfront_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upfront_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tiers_reset_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tiers_reset_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `updater_id` bigint unsigned DEFAULT NULL,
  `tier_schema_id` bigint unsigned DEFAULT NULL,
  `tiers_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Progressive, Retroactive',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `reset_date_time` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tiers_schema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tiers_schema` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prefix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TR',
  `schema_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schema_description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tier_system_id` int NOT NULL DEFAULT '0',
  `tier_metrics_id` int NOT NULL DEFAULT '0',
  `tier_metrics_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tier_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tier_duration_id` int NOT NULL DEFAULT '0',
  `levels` int NOT NULL DEFAULT '0',
  `start_day` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_day` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_end_day` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `next_reset_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tiers_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tiers_type` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tier_setting_id` bigint unsigned NOT NULL,
  `is_check` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tiers_type_tier_setting_id_foreign` (`tier_setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tiers_worker_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tiers_worker_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_lead_id` bigint unsigned NOT NULL,
  `tier_schema_id` bigint unsigned DEFAULT NULL,
  `tier_schema_level_id` bigint unsigned DEFAULT NULL,
  `tiers_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Progressive, Retroactive',
  `tiers_metrics` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User, Lead, Manager',
  `reset_date_time` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `timezones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `timezones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transaction_kill_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transaction_kill_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_time` datetime NOT NULL,
  `thread_id` bigint unsigned DEFAULT NULL,
  `trx_id` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `host` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `db` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `command` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seconds_open` int DEFAULT NULL,
  `rows_modified` int DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transaction_kill_log_log_time_index` (`log_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `upfront_system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `upfront_system_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `upfront_for_self_gen` enum('Pay highest value','Pay sum of setter and closer upfront') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Pay highest value',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_additional_office_override_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_additional_office_override_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `updater_id` int NOT NULL,
  `override_effective_date` date DEFAULT NULL,
  `effective_end_date` date DEFAULT NULL,
  `state_id` int NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `office_id` int NOT NULL,
  `office_overrides_amount` double(8,2) DEFAULT NULL,
  `office_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_office_overrides_amount` double(8,2) DEFAULT NULL,
  `old_office_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tiers_id` bigint unsigned DEFAULT NULL,
  `old_tiers_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_additional_office_override_history_tiers_ranges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_additional_office_override_history_tiers_ranges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_add_office_override_history_id` bigint unsigned DEFAULT NULL,
  `tiers_schema_id` bigint unsigned DEFAULT NULL,
  `tiers_levels_id` bigint unsigned DEFAULT NULL,
  `value` decimal(40,2) NOT NULL,
  `value_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_agreement_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_agreement_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `updater_id` bigint unsigned NOT NULL,
  `probation_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_probation_period` int DEFAULT NULL,
  `offer_include_bonus` enum('1','0') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `old_offer_include_bonus` enum('1','0') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `hiring_bonus_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_hiring_bonus_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_to_be_paid` date DEFAULT NULL,
  `old_date_to_be_paid` date DEFAULT NULL,
  `period_of_agreement` date DEFAULT NULL,
  `old_period_of_agreement` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `old_end_date` date DEFAULT NULL,
  `offer_expiry_date` date DEFAULT NULL,
  `old_offer_expiry_date` date DEFAULT NULL,
  `hired_by_uid` int DEFAULT NULL,
  `old_hired_by_uid` int DEFAULT NULL,
  `hiring_signature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_hiring_signature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_attendance_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_attendance_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_attendance_id` bigint NOT NULL,
  `adjustment_id` bigint NOT NULL,
  `office_id` bigint NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'clock in, lunch, end lunch, break, end break, clock out',
  `attendance_date` datetime NOT NULL,
  `entry_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Adjustment, User',
  `created_by` bigint NOT NULL,
  `updated_by` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `current_time` time DEFAULT NULL,
  `lunch_time` time DEFAULT NULL,
  `break_time` time DEFAULT NULL,
  `is_synced` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Noy Synced, 1 = Synced',
  `date` date NOT NULL,
  `everee_shift_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` int DEFAULT '0',
  `is_present` int NOT NULL DEFAULT '1',
  `everee_status` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_commission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_commission` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `payroll_id` int NOT NULL DEFAULT '0' COMMENT 'payroll table id',
  `user_id` int DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `product_id` bigint DEFAULT NULL,
  `milestone_schema_id` bigint unsigned DEFAULT NULL,
  `pid` varchar(255) DEFAULT NULL,
  `product_code` varchar(255) DEFAULT NULL,
  `amount_type` enum('m1','m2','m2 update','reconciliation','reconciliation update') NOT NULL,
  `schema_name` varchar(255) DEFAULT NULL,
  `schema_trigger` varchar(255) DEFAULT NULL,
  `schema_type` varchar(255) DEFAULT NULL,
  `is_last` tinyint NOT NULL DEFAULT '0' COMMENT 'Default 0, 1 = When last date hits',
  `settlement_type` varchar(255) NOT NULL DEFAULT 'during_m2' COMMENT 'during_m2, reconciliation',
  `amount` double(11,2) NOT NULL DEFAULT '0.00',
  `redline` varchar(100) DEFAULT NULL,
  `redline_type` varchar(255) DEFAULT NULL COMMENT 'Fixed, Shift Based on Location, Shift Based on Product, Shift Based on Product & Location',
  `recon_amount` varchar(255) DEFAULT NULL,
  `recon_amount_type` varchar(255) DEFAULT NULL,
  `net_epc` float DEFAULT NULL,
  `kw` varchar(50) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `customer_signoff` date DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `recon_status` tinyint NOT NULL DEFAULT '1' COMMENT '1 = Unpaid, 2 = Partially Paid, 3 = Fully Paid',
  `is_mark_paid` tinyint(1) NOT NULL DEFAULT '0',
  `is_next_payroll` tinyint(1) NOT NULL DEFAULT '0',
  `is_stop_payroll` tinyint NOT NULL DEFAULT '0',
  `is_displayed` enum('0','1') NOT NULL DEFAULT '1',
  `ref_id` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_move_to_recon` tinyint DEFAULT '0',
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  `commission_amount` double(8,2) DEFAULT NULL,
  `commission_type` enum('percent','per kw','per sale') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_commission_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`admin_flexpwr`@`%`*/ /*!50003 TRIGGER `update_sale_product_master_after_user_commission_update` AFTER UPDATE ON `user_commission` FOR EACH ROW BEGIN
		    IF OLD.status = 1 AND NEW.status = 3 THEN
		        UPDATE sale_product_master
		        SET is_paid = 1
		        WHERE sale_product_master.type = NEW.schema_type AND (setter1_id = NEW.user_id OR setter2_id = NEW.user_id
		               OR closer1_id = NEW.user_id OR closer2_id = NEW.user_id);
		    END IF;
		END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `user_commission_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_commission_history` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `updater_id` int DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `old_product_id` bigint unsigned DEFAULT NULL,
  `self_gen_user` tinyint DEFAULT '0',
  `old_self_gen_user` tinyint DEFAULT '0',
  `commission` double(8,2) DEFAULT NULL,
  `commission_type` enum('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_commission` double(8,2) DEFAULT NULL,
  `old_commission_type` enum('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_effective_date` date DEFAULT NULL,
  `effective_end_date` date DEFAULT NULL,
  `position_id` int NOT NULL,
  `core_position_id` bigint unsigned DEFAULT NULL,
  `sub_position_id` int DEFAULT NULL,
  `action_item_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Old, 1 = In Action Item',
  `tiers_id` bigint unsigned DEFAULT NULL,
  `old_tiers_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_commission_history_tiers_ranges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_commission_history_tiers_ranges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_commission_history_id` bigint unsigned DEFAULT NULL,
  `tiers_schema_id` bigint unsigned DEFAULT NULL,
  `tiers_levels_id` bigint unsigned DEFAULT NULL,
  `value` decimal(40,2) NOT NULL,
  `value_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_commission_lock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_commission_lock` (
  `id` bigint unsigned NOT NULL,
  `payroll_id` int NOT NULL DEFAULT '0' COMMENT 'payroll table id',
  `user_id` int DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `product_id` bigint DEFAULT NULL,
  `milestone_schema_id` bigint unsigned DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `product_code` varchar(255) DEFAULT NULL,
  `amount_type` enum('m1','m2','m2 update','reconciliation','reconciliation update') NOT NULL,
  `schema_name` varchar(255) DEFAULT NULL,
  `schema_trigger` varchar(255) DEFAULT NULL,
  `schema_type` varchar(255) DEFAULT NULL,
  `is_last` tinyint NOT NULL DEFAULT '0' COMMENT 'Default 0, 1 = When last date hits',
  `settlement_type` varchar(255) NOT NULL DEFAULT 'during_m2' COMMENT 'during_m2, reconciliation',
  `amount` double(11,2) NOT NULL DEFAULT '0.00',
  `redline` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `redline_type` varchar(255) DEFAULT NULL COMMENT 'Fixed, Shift Based on Location, Shift Based on Product, Shift Based on Product & Location',
  `recon_amount` varchar(255) DEFAULT NULL,
  `recon_amount_type` varchar(255) DEFAULT NULL,
  `net_epc` float DEFAULT NULL,
  `kw` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `date` date DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `customer_signoff` date DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `recon_status` tinyint NOT NULL DEFAULT '1' COMMENT '1 = Unpaid, 2 = Partially Paid, 3 = Fully Paid',
  `is_mark_paid` tinyint(1) NOT NULL DEFAULT '0',
  `is_next_payroll` tinyint(1) NOT NULL DEFAULT '0',
  `is_stop_payroll` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_move_to_recon` tinyint DEFAULT '0',
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  `is_displayed` enum('0','1') NOT NULL DEFAULT '1',
  `ref_id` int DEFAULT '0',
  `commission_amount` double(8,2) DEFAULT NULL,
  `commission_type` enum('percent','per kw','per sale') DEFAULT NULL,
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_current_payroll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_current_payroll` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_deduction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_deduction` (
  `id` int NOT NULL AUTO_INCREMENT,
  `deduction_type` enum('$','%') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_center_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_center_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ammount_par_paycheck` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deduction_setting_id` int DEFAULT NULL,
  `position_id` bigint unsigned DEFAULT NULL,
  `sub_position_id` int DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `is_deleted` tinyint DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `position_id` (`position_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_deduction_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_deduction_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_deduction_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `updater_id` int DEFAULT NULL,
  `self_gen_user` tinyint DEFAULT '0',
  `old_self_gen_user` tinyint DEFAULT '0',
  `cost_center_id` int DEFAULT NULL,
  `amount_par_paycheque` decimal(8,2) DEFAULT NULL,
  `old_amount_par_paycheque` decimal(8,2) DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `effective_end_date` date DEFAULT NULL,
  `sub_position_id` int DEFAULT NULL,
  `limit_value` double(8,2) DEFAULT NULL,
  `changes_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `changes_field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_department_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_department_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `updater_id` int DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `old_department_id` int DEFAULT NULL,
  `effective_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_direct_override_history_tiers_ranges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_direct_override_history_tiers_ranges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_override_history_id` bigint unsigned DEFAULT NULL,
  `tiers_schema_id` bigint unsigned DEFAULT NULL,
  `tiers_levels_id` bigint unsigned DEFAULT NULL,
  `value` decimal(40,2) NOT NULL,
  `value_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_dismiss_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_dismiss_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `dismiss` tinyint NOT NULL DEFAULT '0' COMMENT '0: Not Dismissed, 1: Dismissed',
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_excel_import_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_excel_import_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `uploaded_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_records` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_indirect_override_history_tiers_ranges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_indirect_override_history_tiers_ranges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_override_history_id` bigint unsigned DEFAULT NULL,
  `tiers_schema_id` bigint unsigned DEFAULT NULL,
  `tiers_levels_id` bigint unsigned DEFAULT NULL,
  `value` decimal(40,2) NOT NULL,
  `value_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_infos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_infos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `avatar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `company` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `communication` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marketing` tinyint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_is_manager_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_is_manager_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `updater_id` bigint unsigned DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `effective_end_date` date DEFAULT NULL,
  `is_manager` bigint unsigned DEFAULT NULL,
  `old_is_manager` bigint unsigned DEFAULT NULL,
  `position_id` bigint unsigned DEFAULT NULL,
  `old_position_id` bigint unsigned DEFAULT NULL,
  `sub_position_id` bigint unsigned DEFAULT NULL,
  `old_sub_position_id` bigint unsigned DEFAULT NULL,
  `action_item_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Old, 1 = In Action Item',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_manager_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_manager_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `updater_id` bigint unsigned DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `effective_end_date` date DEFAULT NULL,
  `manager_id` bigint unsigned DEFAULT NULL,
  `old_manager_id` bigint unsigned DEFAULT NULL,
  `team_id` bigint unsigned DEFAULT NULL,
  `old_team_id` bigint unsigned DEFAULT NULL,
  `position_id` bigint unsigned DEFAULT NULL,
  `old_position_id` bigint unsigned DEFAULT NULL,
  `sub_position_id` bigint unsigned DEFAULT NULL,
  `old_sub_position_id` bigint unsigned DEFAULT NULL,
  `system_generated` tinyint NOT NULL DEFAULT '0',
  `action_item_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Old, 1 = In Action Item',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_office_override_history_tiers_ranges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_office_override_history_tiers_ranges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_office_override_history_id` bigint unsigned DEFAULT NULL,
  `tiers_schema_id` bigint unsigned DEFAULT NULL,
  `tiers_levels_id` bigint unsigned DEFAULT NULL,
  `value` decimal(40,2) NOT NULL,
  `value_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_organization_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_organization_history` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `old_self_gen_accounts` int DEFAULT '0',
  `self_gen_accounts` int DEFAULT '0',
  `updater_id` int DEFAULT NULL,
  `old_manager_id` int DEFAULT NULL,
  `manager_id` int DEFAULT NULL,
  `old_team_id` int DEFAULT NULL,
  `team_id` int DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `effective_end_date` date DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `old_position_id` int DEFAULT NULL,
  `sub_position_id` int DEFAULT NULL,
  `existing_employee_new_manager_id` int DEFAULT NULL,
  `is_manager` int DEFAULT '0',
  `old_is_manager` int DEFAULT '0',
  `old_sub_position_id` int DEFAULT NULL,
  `action_item_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Old, 1 = In Action Item',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_override_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_override_history` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `updater_id` int DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `override_effective_date` date DEFAULT NULL,
  `effective_end_date` date DEFAULT NULL,
  `self_gen_user` tinyint DEFAULT '0',
  `old_self_gen_user` tinyint DEFAULT '0',
  `direct_overrides_amount` double(8,2) DEFAULT NULL,
  `direct_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `indirect_overrides_amount` double(8,2) DEFAULT NULL,
  `indirect_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_overrides_amount` double(8,2) DEFAULT NULL,
  `office_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_stack_overrides_amount` double(8,2) DEFAULT NULL,
  `direct_tiers_id` bigint unsigned DEFAULT NULL,
  `old_direct_tiers_id` bigint unsigned DEFAULT NULL,
  `indirect_tiers_id` bigint unsigned DEFAULT NULL,
  `office_tiers_id` bigint unsigned DEFAULT NULL,
  `old_office_tiers_id` bigint unsigned DEFAULT NULL,
  `old_indirect_tiers_id` bigint unsigned DEFAULT NULL,
  `old_product_id` bigint unsigned DEFAULT NULL,
  `old_direct_overrides_amount` double(8,2) DEFAULT NULL,
  `old_direct_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_indirect_overrides_amount` double(8,2) DEFAULT NULL,
  `old_indirect_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_office_overrides_amount` double(8,2) DEFAULT NULL,
  `old_office_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_office_stack_overrides_amount` double(8,2) DEFAULT NULL,
  `action_item_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Old, 1 = In Action Item',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_overrides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_overrides` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` int NOT NULL DEFAULT '0' COMMENT 'payroll table id',
  `user_id` int DEFAULT NULL,
  `product_id` bigint DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `during` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'm2' COMMENT 'm2, m2 update',
  `sale_user_id` int DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `net_epc` float DEFAULT NULL,
  `kw` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overrides_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adjustment_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calculated_redline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calculated_redline_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overrides_settlement_type` enum('reconciliation','during_m2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `is_mark_paid` tinyint DEFAULT '0',
  `is_next_payroll` tinyint DEFAULT '0',
  `is_stop_payroll` tinyint DEFAULT '0',
  `is_displayed` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `status` int DEFAULT NULL,
  `recon_status` tinyint NOT NULL DEFAULT '1' COMMENT '1 = Unpaid, 2 = Partially Paid, 3 = Fully Paid',
  `office_id` int DEFAULT NULL,
  `ref_id` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_move_to_recon` tinyint DEFAULT '0',
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_override_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_overrides_lock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_overrides_lock` (
  `id` bigint unsigned NOT NULL,
  `payroll_id` int NOT NULL DEFAULT '0' COMMENT 'payroll table id',
  `user_id` int DEFAULT NULL,
  `product_id` bigint DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `during` varchar(255) DEFAULT 'm2' COMMENT 'm2, m2 update',
  `sale_user_id` int DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_code` varchar(255) DEFAULT NULL,
  `net_epc` float DEFAULT NULL,
  `kw` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overrides_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adjustment_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calculated_redline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `calculated_redline_type` varchar(255) DEFAULT NULL,
  `overrides_settlement_type` enum('reconciliation','during_m2') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `status` int DEFAULT NULL,
  `recon_status` tinyint NOT NULL DEFAULT '1' COMMENT '1 = Unpaid, 2 = Partially Paid, 3 = Fully Paid',
  `is_mark_paid` tinyint(1) NOT NULL DEFAULT '0',
  `is_next_payroll` tinyint(1) NOT NULL DEFAULT '0',
  `is_stop_payroll` tinyint NOT NULL DEFAULT '0',
  `office_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_move_to_recon` tinyint DEFAULT '0',
  `is_onetime_payment` tinyint(1) NOT NULL DEFAULT '0',
  `one_time_payment_id` bigint unsigned DEFAULT NULL,
  `is_displayed` enum('0','1') NOT NULL DEFAULT '1',
  `ref_id` int DEFAULT '0',
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_overrides_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_overrides_queue` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `processing` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` int DEFAULT NULL,
  `module_id` int DEFAULT NULL,
  `sub_module_id` int DEFAULT NULL,
  `parmission_id` int DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_profile_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_profile_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `batch_no` int NOT NULL DEFAULT '0',
  `updated_by` int NOT NULL,
  `field_name` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_value` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `new_value` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `created_at` datetime NOT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_reconciliation_commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_reconciliation_commissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `amount` double(8,2) DEFAULT NULL,
  `overrides` double(8,2) NOT NULL DEFAULT '0.00',
  `clawbacks` double(8,2) NOT NULL DEFAULT '0.00',
  `total_due` double(8,2) DEFAULT '0.00',
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `payroll_id` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_reconciliation_commissions_lock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_reconciliation_commissions_lock` (
  `id` bigint unsigned NOT NULL,
  `user_id` int DEFAULT NULL,
  `amount` double(8,2) DEFAULT NULL,
  `overrides` double(8,2) NOT NULL DEFAULT '0.00',
  `clawbacks` double(8,2) NOT NULL DEFAULT '0.00',
  `total_due` double(8,2) DEFAULT '0.00',
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `payroll_id` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `unique_id_payroll_combination` (`id`,`payroll_id`,`user_id`,`pay_period_from`,`pay_period_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_reconciliation_commissions_withholding`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_reconciliation_commissions_withholding` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` double(8,2) DEFAULT NULL,
  `overrides` double(8,2) NOT NULL DEFAULT '0.00',
  `clawbacks` double(8,2) NOT NULL DEFAULT '0.00',
  `total_due` double(8,2) DEFAULT '0.00',
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `payroll_id` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_reconciliation_withholds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_reconciliation_withholds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closer_id` bigint unsigned DEFAULT NULL,
  `setter_id` bigint unsigned DEFAULT NULL,
  `payroll_id` int DEFAULT NULL,
  `withhold_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adjustment_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'unpaid',
  `finalize_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `payroll_to_recon_status` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_redline_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_redline_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `updater_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `redline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline_amount_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `self_gen_user` int DEFAULT '0',
  `state_id` bigint unsigned DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `effective_end_date` date DEFAULT NULL,
  `position_type` int DEFAULT NULL,
  `core_position_id` bigint unsigned DEFAULT NULL,
  `sub_position_type` int DEFAULT NULL,
  `old_product_id` bigint unsigned DEFAULT NULL,
  `old_redline_amount_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_self_gen_user` int DEFAULT NULL,
  `old_redline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_redline_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `withheld_amount` decimal(8,2) DEFAULT NULL,
  `withheld_type` enum('per sale','per KW') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `withheld_effective_date` date DEFAULT NULL,
  `action_item_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Old, 1 = In Action Item',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_sales_office_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_sales_office_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `office_id` int DEFAULT NULL,
  `state_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `status` tinyint unsigned NOT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_sales_offices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_sales_offices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `office_id` int DEFAULT NULL,
  `state_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `status` tinyint unsigned NOT NULL,
  `effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_schedule_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_schedule_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `schedule_id` bigint NOT NULL,
  `office_id` bigint NOT NULL,
  `schedule_from` datetime DEFAULT NULL,
  `schedule_to` datetime DEFAULT NULL,
  `lunch_duration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_days` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `repeated_batch` int DEFAULT NULL,
  `updated_by` bigint DEFAULT NULL,
  `updated_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'system, manually',
  `user_attendance_id` int DEFAULT NULL,
  `attendance_status` int NOT NULL DEFAULT '0',
  `is_flexible` int DEFAULT '0',
  `is_worker_absent` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_schedule_times`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_schedule_times` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `day` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time_slot` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_schedules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `scheduled_by` bigint DEFAULT NULL,
  `is_flexible` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Not Flexible, 1 = Flexible',
  `is_repeat` tinyint NOT NULL DEFAULT '0' COMMENT '0 = No Repeat, 1 = Repeat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_self_gen_commmission_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_self_gen_commmission_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `sub_position_id` int DEFAULT NULL,
  `updater_id` int NOT NULL,
  `self_gen_user` tinyint DEFAULT '0',
  `old_self_gen_user` tinyint DEFAULT '0',
  `commission` double(8,2) DEFAULT NULL,
  `commission_type` enum('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_effective_date` date DEFAULT NULL,
  `old_commission` double(8,2) DEFAULT NULL,
  `old_commission_type` enum('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_statuses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_terminate_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_terminate_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `terminate_effective_date` date DEFAULT NULL,
  `is_terminate` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_transfer_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_transfer_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `transfer_effective_date` date DEFAULT NULL,
  `effective_end_date` date DEFAULT NULL,
  `updater_id` int DEFAULT NULL,
  `state_id` int DEFAULT NULL,
  `old_state_id` int DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `old_office_id` int DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `old_department_id` int DEFAULT NULL,
  `position_id` int DEFAULT NULL,
  `old_position_id` int DEFAULT NULL,
  `sub_position_id` int DEFAULT NULL,
  `old_sub_position_id` int DEFAULT NULL,
  `is_manager` int DEFAULT NULL,
  `old_is_manager` int DEFAULT NULL,
  `self_gen_accounts` int DEFAULT NULL,
  `old_self_gen_accounts` int DEFAULT NULL,
  `manager_id` int DEFAULT NULL,
  `old_manager_id` int DEFAULT NULL,
  `team_id` int DEFAULT NULL,
  `old_team_id` int DEFAULT NULL,
  `redline_amount_type` varchar(255) DEFAULT NULL,
  `old_redline_amount_type` varchar(255) DEFAULT NULL,
  `redline` varchar(25) DEFAULT NULL,
  `old_redline` varchar(25) DEFAULT NULL,
  `redline_type` varchar(255) DEFAULT NULL,
  `old_redline_type` varchar(255) DEFAULT NULL,
  `self_gen_redline_amount_type` varchar(255) DEFAULT NULL,
  `old_self_gen_redline_amount_type` varchar(255) DEFAULT NULL,
  `self_gen_redline` varchar(25) DEFAULT NULL,
  `old_self_gen_redline` varchar(25) DEFAULT NULL,
  `self_gen_redline_type` varchar(255) DEFAULT NULL,
  `old_self_gen_redline_type` varchar(255) DEFAULT NULL,
  `existing_employee_new_manager_id` int DEFAULT NULL,
  `existing_employee_old_manager_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_upfront_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_upfront_history` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `updater_id` int DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `old_product_id` bigint unsigned DEFAULT NULL,
  `milestone_schema_id` bigint unsigned DEFAULT NULL,
  `old_milestone_schema_id` bigint unsigned DEFAULT NULL,
  `milestone_schema_trigger_id` bigint unsigned DEFAULT NULL,
  `old_milestone_schema_trigger_id` bigint unsigned DEFAULT NULL,
  `self_gen_user` tinyint DEFAULT '0',
  `old_self_gen_user` tinyint DEFAULT '0',
  `upfront_pay_amount` double(8,2) DEFAULT NULL,
  `old_upfront_pay_amount` double(8,2) DEFAULT NULL,
  `upfront_sale_type` enum('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_upfront_sale_type` enum('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upfront_effective_date` date DEFAULT NULL,
  `effective_end_date` date DEFAULT NULL,
  `position_id` int NOT NULL,
  `core_position_id` bigint unsigned DEFAULT NULL,
  `sub_position_id` int DEFAULT NULL,
  `action_item_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Old, 1 = In Action Item',
  `tiers_id` bigint unsigned DEFAULT NULL,
  `old_tiers_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_upfront_history_tiers_ranges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_upfront_history_tiers_ranges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_upfront_history_id` bigint unsigned DEFAULT NULL,
  `tiers_schema_id` bigint unsigned DEFAULT NULL,
  `tiers_levels_id` bigint unsigned DEFAULT NULL,
  `value` decimal(40,2) NOT NULL,
  `value_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_wages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_wages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `updater_id` int NOT NULL,
  `pay_type` enum('Hourly','Salary') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Salary',
  `pay_rate` decimal(10,2) NOT NULL,
  `pay_rate_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Per Hour, Weekly, Monthly, Bi-Weekly, Semi-Monthly',
  `pto_hours` decimal(10,2) DEFAULT NULL,
  `unused_pto_expires` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Monthly, Annually, Accrues Continuously',
  `expected_weekly_hours` decimal(10,2) NOT NULL DEFAULT '40.00',
  `overtime_rate` decimal(10,2) NOT NULL DEFAULT '1.50',
  `effective_date` date DEFAULT NULL,
  `pto_hours_effective_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_wages_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_wages_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `updater_id` bigint DEFAULT NULL,
  `effective_date` date NOT NULL,
  `effective_end_date` date DEFAULT NULL,
  `pay_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hourly, Salary',
  `old_pay_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hourly, Salary',
  `pay_rate` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `old_pay_rate` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `pay_rate_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Per Hour, Weekly, Monthly, Bi-Weekly, Semi-Monthly',
  `old_pay_rate_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Per Hour, Weekly, Monthly, Bi-Weekly, Semi-Monthly',
  `pto_hours_effective_date` date NOT NULL,
  `pto_hours` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `old_pto_hours` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `unused_pto_expires` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Monthly, Annually, Accrues Continuously',
  `old_unused_pto_expires` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Monthly, Annually, Accrues Continuously',
  `expected_weekly_hours` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_expected_weekly_hours` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overtime_rate` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_overtime_rate` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_item_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Old, 1 = In Action Item',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_withheld_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_withheld_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `updater_id` int DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `old_product_id` bigint unsigned DEFAULT NULL,
  `self_gen_user` tinyint DEFAULT '0',
  `old_self_gen_user` tinyint DEFAULT '0',
  `withheld_amount` decimal(8,2) NOT NULL,
  `old_withheld_amount` decimal(8,2) DEFAULT NULL,
  `withheld_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_withheld_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `withheld_effective_date` date NOT NULL,
  `effective_end_date` date DEFAULT NULL,
  `position_id` int NOT NULL,
  `core_position_id` bigint unsigned DEFAULT NULL,
  `sub_position_id` int DEFAULT NULL,
  `action_item_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Old, 1 = In Action Item',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'for users Gusto uuid',
  `aveyo_hs_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jobnimbus_jnid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jobnimbus_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `everee_workerId` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `self_gen_accounts` int DEFAULT NULL,
  `self_gen_type` int DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sex` enum('male','female','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Employee_profile/default-user.png',
  `zip_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `home_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `home_address_line_1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `home_address_line_2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `home_address_state` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `home_address_city` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `home_address_zip` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `home_address_lat` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `home_address_long` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `home_address_timezone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_address_line_1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_address_line_2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_address_lat` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_address_long` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_address_timezone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_relationship` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergrncy_contact_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergrncy_contact_zip_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergrncy_contact_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergrncy_contact_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `mobile_no` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state_id` bigint unsigned DEFAULT NULL,
  `city_id` bigint unsigned DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `employee_position_id` bigint unsigned DEFAULT NULL,
  `manager_id` bigint unsigned DEFAULT NULL,
  `manager_id_effective_date` date DEFAULT NULL,
  `team_id` int DEFAULT NULL,
  `team_id_effective_date` date DEFAULT NULL,
  `status_id` int DEFAULT NULL,
  `recruiter_id` int DEFAULT NULL,
  `is_super_admin` int DEFAULT '0',
  `is_manager` int DEFAULT '0',
  `is_manager_effective_date` date DEFAULT NULL,
  `team_lead_status` int DEFAULT '0',
  `stop_payroll` int DEFAULT '0',
  `dismiss` int DEFAULT '0',
  `contract_ended` tinyint NOT NULL DEFAULT '0' COMMENT '0: Not ended, 1: Ended',
  `terminate` tinyint(1) NOT NULL DEFAULT '0',
  `disable_login` int DEFAULT '0',
  `additional_recruiter_id1` int DEFAULT NULL,
  `additional_recruiter_id2` int DEFAULT NULL,
  `additional_recruiter1_per_kw_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_recruiter2_per_kw_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position_id` bigint unsigned NOT NULL,
  `position_id_effective_date` date DEFAULT NULL,
  `sub_position_id` bigint unsigned DEFAULT NULL,
  `group_id` int DEFAULT NULL,
  `worker_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '1099' COMMENT 'W9, 1099',
  `commission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_type` enum('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `self_gen_commission` int DEFAULT '0',
  `self_gen_commission_type` enum('percent','per kw','per sale') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline_amount_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redline_type` enum('per sale','per watt') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'per watt',
  `self_gen_redline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `self_gen_redline_amount_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `self_gen_redline_type` enum('per sale','per watt') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upfront_pay_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upfront_sale_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'per sale',
  `self_gen_upfront_amount` double(8,2) DEFAULT '0.00',
  `self_gen_upfront_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direct_overrides_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direct_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'per kw',
  `indirect_overrides_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `indirect_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'per kw',
  `office_overrides_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_overrides_type` enum('per sale','per kw','percent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'per kw',
  `office_stack_overrides_amount` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pay_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hourly, Salary',
  `pay_rate` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `pay_rate_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Per Hour, Weekly, Monthly, Bi-Weekly, Semi-Monthly',
  `pto_hours` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `unused_pto_expires` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Monthly, Annually, Accrues Continuously',
  `expected_weekly_hours` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overtime_rate` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `probation_period` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hiring_bonus_amount` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_to_be_paid` date DEFAULT NULL,
  `period_of_agreement_start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `offer_expiry_date` date DEFAULT NULL,
  `api_token` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rent` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `travel` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_additional_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `phone_Bill` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_sequrity_no` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tax_information` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_of_bank` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `routing_no` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `account_no` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `account_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirm_account_no` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type_of_account` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shirt_size` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hat_size` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_info_for_employee_to_get_started` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `employee_personal_detail` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `onboardProcess` int DEFAULT '0',
  `office_id` int DEFAULT NULL,
  `withheld_amount` decimal(8,2) DEFAULT NULL,
  `withheld_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `self_gen_withheld_amount` decimal(8,2) DEFAULT NULL,
  `self_gen_withheld_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commission_effective_date` date DEFAULT NULL,
  `self_gen_commission_effective_date` date DEFAULT NULL,
  `upfront_effective_date` date DEFAULT NULL,
  `self_gen_upfront_effective_date` date DEFAULT NULL,
  `withheld_effective_date` date DEFAULT NULL,
  `self_gen_withheld_effective_date` date DEFAULT NULL,
  `redline_effective_date` date DEFAULT NULL,
  `self_gen_redline_effective_date` date DEFAULT NULL,
  `override_effective_date` date DEFAULT NULL,
  `entity_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_ein` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `first_time_changed_password` tinyint(1) DEFAULT '0' COMMENT '0 OR 1',
  `is_agreement_accepted` enum('0','1') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `everee_json_response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `action_item_status` tinyint NOT NULL DEFAULT '0' COMMENT '0 = Old, 1 = In Action Item',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `rehire` tinyint(1) NOT NULL DEFAULT '0',
  `everee_embed_onboard_profile` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0 means not completed, 1 means completed',
  `time_format` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '24-hour',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_api_token_unique` (`api_token`),
  KEY `users_state_id_foreign` (`state_id`),
  KEY `users_department_id_foreign` (`department_id`),
  KEY `users_employee_position_id_foreign` (`employee_position_id`),
  KEY `users_manager_id_foreign` (`manager_id`),
  KEY `users_position_id_foreign` (`position_id`),
  KEY `users_city_id_foreign` (`city_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`admin_flexpwr`@`%`*/ /*!50003 TRIGGER `update_worker_type_on_user_change` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
                IF OLD.worker_type != NEW.worker_type THEN
                    UPDATE payrolls 
                    SET worker_type = NEW.worker_type 
                    WHERE user_id = NEW.id;
                END IF;
            END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `users_additional_emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_additional_emails` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_details_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users_business_address`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_business_address` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `business_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address_line_1` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address_line_2` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address_state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address_city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address_zip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address_lat` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address_long` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_address_timezone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `users_business_address_user_id_foreign` (`user_id`),
  CONSTRAINT `users_business_address_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users_current_tier_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_current_tier_level` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `tier_schema_id` bigint unsigned DEFAULT NULL,
  `tier_schema_level_id` bigint unsigned DEFAULT NULL,
  `next_tier_schema_level_id` bigint unsigned DEFAULT NULL,
  `tiers_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Progressive, Retroactive',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Commission, Upfront, Override',
  `sub_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Commission = (Commission), Upfront = (Milestone Like m1, m2), Override = (Office, Additional Office, Direct, InDirect)',
  `current_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remaining_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_level` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remaining_level` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maxed` tinyint DEFAULT NULL COMMENT '0 = NOT MAXED, 1 = MAXED',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1 = ENABLED, 0 = DISABLED',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users_preference`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_preference` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `move_job` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users_tiers_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_tiers_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `tiers_history_id` bigint unsigned NOT NULL,
  `tier_schema_id` bigint unsigned DEFAULT NULL,
  `tier_schema_level_id` bigint unsigned DEFAULT NULL,
  `next_tier_schema_level_id` bigint unsigned DEFAULT NULL,
  `tiers_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Progressive, Retroactive',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Commission, Upfront, Override',
  `sub_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Commission = (Commission), Upfront = (Milestone Like m1, m2), Override = (Office, Additional Office, Direct, InDirect)',
  `current_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remaining_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_level` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remaining_level` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maxed` tinyint DEFAULT NULL COMMENT '0 = NOT MAXED, 1 = MAXED',
  `status` tinyint NOT NULL DEFAULT '1' COMMENT '1 = ENABLED, 0 = DISABLED',
  `reset_date_time` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `visible_signatures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `visible_signatures` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `signature_attributes` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `form_data_attributes` json DEFAULT NULL,
  `document_signer_id` int unsigned DEFAULT NULL,
  `document_id` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `w2_payroll_tax_deductions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `w2_payroll_tax_deductions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `fica_tax` double(8,2) DEFAULT '0.00',
  `medicare_withholding` double(8,2) DEFAULT '0.00',
  `social_security_withholding` double(8,2) DEFAULT '0.00',
  `state_income_tax` double(8,2) DEFAULT '0.00',
  `federal_income_tax` double(8,2) DEFAULT '0.00',
  `medicare_tax` double(8,2) DEFAULT '0.00',
  `social_security_tax` double(8,2) DEFAULT '0.00',
  `additional_medicare_tax` double(8,2) DEFAULT '0.00',
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT '1',
  `payment_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `w2_user_transfer_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `w2_user_transfer_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `updater_id` bigint unsigned NOT NULL DEFAULT '0',
  `period_of_agreement` date DEFAULT NULL,
  `employee_transfer_date` date DEFAULT NULL,
  `contractor_transfer_date` date DEFAULT NULL,
  `type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '1099, w2',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `position_id` int unsigned NOT NULL,
  `wages_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 for enabled, 0 for disabled',
  `pay_type` enum('Hourly','Salary') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pay_type_lock_for_hire` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 for lock, 0 for unlock',
  `pay_rate` decimal(10,2) NOT NULL,
  `pay_rate_lock_for_hire` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 for lock, 0 for unlock',
  `pto_hours` decimal(10,2) DEFAULT NULL,
  `pto_hours_lock_for_hire` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 for lock, 0 for unlock',
  `unused_pto` enum('Expires Monthly','Expires Annually','Accrues Continuously') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unused_pto_lock_for_hire` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 for lock, 0 for unlock',
  `expected_weekly_hours` decimal(10,2) NOT NULL DEFAULT '40.00',
  `ewh_lock_for_hire` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'ewh = expected_weekly_hours; 1 for lock, 0 for unlock',
  `overtime_rate` decimal(10,2) NOT NULL DEFAULT '1.50',
  `ot_rate_lock_for_hire` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'ot = overtime; 1 for lock, 0 for unlock',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `weekly_pay_frequencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `weekly_pay_frequencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pay_period_from` date DEFAULT NULL,
  `pay_period_to` date DEFAULT NULL,
  `closed_status` int DEFAULT '0',
  `open_status_from_bank` tinyint NOT NULL DEFAULT '0',
  `w2_closed_status` tinyint NOT NULL DEFAULT '0',
  `w2_open_status_from_bank` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wp_user_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wp_user_schedule` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `office_id` bigint unsigned DEFAULT NULL,
  `lunch_break` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schedule_date` date NOT NULL,
  `day_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `clock_in` time DEFAULT NULL,
  `clock_out` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `get_payroll_data`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`admin_flexpwr`@`%` SQL SECURITY INVOKER */
/*!50001 VIEW `get_payroll_data` AS select `pd`.`id` AS `id`,`pd`.`user_id` AS `user_id`,`pd`.`position_id` AS `position_id`,`pd`.`commission` AS `commission`,`pd`.`override` AS `override`,`pd`.`reimbursement` AS `reimbursement`,`pd`.`clawback` AS `clawback`,`pd`.`deduction` AS `deduction`,`pd`.`adjustment` AS `adjustment`,`pd`.`reconciliation` AS `reconciliation`,`pd`.`net_pay` AS `net_pay`,`pd`.`pay_period_from` AS `pay_period_from`,`pd`.`pay_period_to` AS `pay_period_to`,`pd`.`status` AS `status` from (select `payrolls`.`id` AS `id`,`payrolls`.`user_id` AS `user_id`,`payrolls`.`position_id` AS `position_id`,`payrolls`.`commission` AS `commission`,`payrolls`.`override` AS `override`,`payrolls`.`reimbursement` AS `reimbursement`,`payrolls`.`clawback` AS `clawback`,`payrolls`.`deduction` AS `deduction`,`payrolls`.`adjustment` AS `adjustment`,`payrolls`.`reconciliation` AS `reconciliation`,`payrolls`.`net_pay` AS `net_pay`,`payrolls`.`pay_period_from` AS `pay_period_from`,`payrolls`.`pay_period_to` AS `pay_period_to`,`payrolls`.`status` AS `status` from `payrolls` union select `payroll_history`.`payroll_id` AS `id`,`payroll_history`.`user_id` AS `user_id`,`payroll_history`.`position_id` AS `position_id`,`payroll_history`.`commission` AS `commission`,`payroll_history`.`override` AS `override`,`payroll_history`.`reimbursement` AS `reimbursement`,`payroll_history`.`clawback` AS `clawback`,`payroll_history`.`deduction` AS `deduction`,`payroll_history`.`adjustment` AS `adjustment`,`payroll_history`.`reconciliation` AS `reconciliation`,`payroll_history`.`net_pay` AS `net_pay`,`payroll_history`.`pay_period_from` AS `pay_period_from`,`payroll_history`.`pay_period_to` AS `pay_period_to`,`payroll_history`.`status` AS `status` from `payroll_history`) `pd` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` VALUES (1,'2014_10_12_000000_create_teams_table',1);
INSERT INTO `migrations` VALUES (2,'2014_10_12_000000_create_users_table',1);
INSERT INTO `migrations` VALUES (3,'2014_10_12_100000_create_password_resets_table',1);
INSERT INTO `migrations` VALUES (4,'2019_08_19_000000_create_failed_jobs_table',1);
INSERT INTO `migrations` VALUES (5,'2019_12_14_000001_create_personal_access_tokens_table',1);
INSERT INTO `migrations` VALUES (6,'2021_02_01_123654_create_group_policies_table',1);
INSERT INTO `migrations` VALUES (7,'2021_03_22_144618_create_permission_tables',1);
INSERT INTO `migrations` VALUES (8,'2021_04_14_044507_create_settings_table',1);
INSERT INTO `migrations` VALUES (9,'2021_06_15_022916_create_user_infos_table',1);
INSERT INTO `migrations` VALUES (10,'2021_06_23_041411_create_activity_log_table',1);
INSERT INTO `migrations` VALUES (11,'2021_06_23_041412_add_event_column_to_activity_log_table',1);
INSERT INTO `migrations` VALUES (12,'2021_06_23_041413_add_batch_uuid_column_to_activity_log_table',1);
INSERT INTO `migrations` VALUES (13,'2022_12_07_102036_create_company_types_table',1);
INSERT INTO `migrations` VALUES (14,'2022_12_07_104614_create_crms_table',1);
INSERT INTO `migrations` VALUES (15,'2022_12_07_105817_create_payroll_processes_table',1);
INSERT INTO `migrations` VALUES (16,'2022_12_07_110710_create_accounting_softwares_table',1);
INSERT INTO `migrations` VALUES (17,'2022_12_07_112232_create_departments_table',1);
INSERT INTO `migrations` VALUES (18,'2022_12_07_112636_create_employee_positions_table',1);
INSERT INTO `migrations` VALUES (19,'2022_12_07_114319_create_commissions_table',1);
INSERT INTO `migrations` VALUES (20,'2022_12_07_115620_create_sub_commissions_table',1);
INSERT INTO `migrations` VALUES (21,'2022_12_07_124549_create_backend_settings_table',1);
INSERT INTO `migrations` VALUES (22,'2022_12_07_124549_create_company_settings_table',1);
INSERT INTO `migrations` VALUES (23,'2022_12_07_124914_create_states_table',1);
INSERT INTO `migrations` VALUES (24,'2022_12_08_062840_create_cities_table',1);
INSERT INTO `migrations` VALUES (25,'2022_12_08_064551_create_backend__reconciliations_table',1);
INSERT INTO `migrations` VALUES (26,'2022_12_08_064551_create_reconciliation_schedule_table',1);
INSERT INTO `migrations` VALUES (27,'2022_12_08_065606_create_marketing__deals__settings_table',1);
INSERT INTO `migrations` VALUES (28,'2022_12_08_070202_create_override__settings_table',1);
INSERT INTO `migrations` VALUES (29,'2022_12_08_070353_create_marketing__deals__reconciliations_table',1);
INSERT INTO `migrations` VALUES (30,'2022_12_08_070603_create_overrides__types_table',1);
INSERT INTO `migrations` VALUES (31,'2022_12_08_071942_create_tiers_table',1);
INSERT INTO `migrations` VALUES (32,'2022_12_08_072153_create_tier_level_names_table',1);
INSERT INTO `migrations` VALUES (33,'2022_12_08_072154_create_tier__level__settings_table',1);
INSERT INTO `migrations` VALUES (34,'2022_12_08_072407_create_alerts_table',1);
INSERT INTO `migrations` VALUES (35,'2022_12_09_121057_create_margin_settings_table',1);
INSERT INTO `migrations` VALUES (36,'2022_12_09_135504_create_frequency_types_table',1);
INSERT INTO `migrations` VALUES (37,'2022_12_09_135728_create_company_payrolls_table',1);
INSERT INTO `migrations` VALUES (38,'2022_12_10_050812_create_locations_table',1);
INSERT INTO `migrations` VALUES (39,'2022_12_10_052326_create_cost_centers_table',1);
INSERT INTO `migrations` VALUES (40,'2022_12_10_063059_create_company_profiles_table',1);
INSERT INTO `migrations` VALUES (41,'2022_12_10_074402_create_migration_of_differences_table',1);
INSERT INTO `migrations` VALUES (42,'2022_12_13_060333_create_marketing_deal_alerts_table',1);
INSERT INTO `migrations` VALUES (43,'2022_12_13_060415_create_incomplete_account_alerts_table',1);
INSERT INTO `migrations` VALUES (44,'2022_12_13_123129_create_positions_table',1);
INSERT INTO `migrations` VALUES (45,'2022_12_14_071442_create_position_commissions_table',1);
INSERT INTO `migrations` VALUES (46,'2022_12_14_072435_create_position_upfront_settings_table',1);
INSERT INTO `migrations` VALUES (47,'2022_12_14_072436_create_position_commission_upfronts_table',1);
INSERT INTO `migrations` VALUES (48,'2022_12_14_075202_create_position_commission_deduction_settings_table',1);
INSERT INTO `migrations` VALUES (49,'2022_12_14_075203_create_position_commission_deductions_table',1);
INSERT INTO `migrations` VALUES (50,'2022_12_14_075203_create_positions_duduction_limits_table',1);
INSERT INTO `migrations` VALUES (51,'2022_12_14_093724_create_position_override_settlements_table',1);
INSERT INTO `migrations` VALUES (52,'2022_12_14_093725_create_position_commission_overrides_table',1);
INSERT INTO `migrations` VALUES (53,'2022_12_14_093726_create_position_tier_overrides_table',1);
INSERT INTO `migrations` VALUES (54,'2022_12_16_083646_create_status_table',1);
INSERT INTO `migrations` VALUES (55,'2022_12_16_105421_create_configure_tiers_table',1);
INSERT INTO `migrations` VALUES (56,'2022_12_17_104409_create_leads_table',1);
INSERT INTO `migrations` VALUES (57,'2022_12_19_112545_create_employee_tax_infos_table',1);
INSERT INTO `migrations` VALUES (58,'2022_12_19_120948_create_employee_bankings_table',1);
INSERT INTO `migrations` VALUES (59,'2022_12_19_132218_create_sequi_docs_template_categories_table',1);
INSERT INTO `migrations` VALUES (60,'2022_12_19_132219_create_sequi_docs_templates_table',1);
INSERT INTO `migrations` VALUES (61,'2022_12_20_095508_create_template_generates_table',1);
INSERT INTO `migrations` VALUES (62,'2022_12_21_075401_create_legacy_weekly_sheet_table',1);
INSERT INTO `migrations` VALUES (63,'2022_12_21_075402_create_legacy_excel_raw_data_table',1);
INSERT INTO `migrations` VALUES (64,'2022_12_23_061726_create_template_metas_table',1);
INSERT INTO `migrations` VALUES (65,'2023_01_07_134227_create_additional_recruters_table',1);
INSERT INTO `migrations` VALUES (66,'2023_01_18_150739_create_legacy_api_raw_data_table',1);
INSERT INTO `migrations` VALUES (67,'2023_01_18_150739_create_m1_m2_deduction_alerts_table',1);
INSERT INTO `migrations` VALUES (68,'2023_01_18_150739_create_sale_masters_table',1);
INSERT INTO `migrations` VALUES (69,'2023_01_19_040037_create_crm_setting_table',1);
INSERT INTO `migrations` VALUES (70,'2023_01_19_110428_create_permission_modules',1);
INSERT INTO `migrations` VALUES (71,'2023_01_19_121331_create_user_permissions',1);
INSERT INTO `migrations` VALUES (72,'2023_01_21_054827_create_modules_with_permission',1);
INSERT INTO `migrations` VALUES (73,'2023_01_21_071407_create_permission_submodules',1);
INSERT INTO `migrations` VALUES (74,'2023_01_24_075351_create_sale_master_process_table',1);
INSERT INTO `migrations` VALUES (75,'2023_01_24_130500_create_closer_identify_alert_table',1);
INSERT INTO `migrations` VALUES (76,'2023_01_24_130522_create_setter_identify_alert_table',1);
INSERT INTO `migrations` VALUES (77,'2023_01_27_130523_create_legacy_api_data_null_table',1);
INSERT INTO `migrations` VALUES (78,'2023_01_28_070405_create_management_teams_table',1);
INSERT INTO `migrations` VALUES (79,'2023_01_28_095404_create_management_team_members_table',1);
INSERT INTO `migrations` VALUES (80,'2023_01_28_104819_create_user_reconciliation_withholds_table',1);
INSERT INTO `migrations` VALUES (81,'2023_01_31_115900_create_mark_account_status_table',1);
INSERT INTO `migrations` VALUES (82,'2023_01_31_120123_create_emp_payroll_processing_table',1);
INSERT INTO `migrations` VALUES (83,'2023_01_31_140843_create_test_data_table',1);
INSERT INTO `migrations` VALUES (84,'2023_02_02_110513_create_user_overrides_table',1);
INSERT INTO `migrations` VALUES (85,'2023_02_04_061306_create_onboarding_employees_table',1);
INSERT INTO `migrations` VALUES (86,'2023_02_04_074710_create_user_current_payroll_table',1);
INSERT INTO `migrations` VALUES (87,'2023_02_04_074710_create_user_statuses_table',1);
INSERT INTO `migrations` VALUES (88,'2023_02_09_064318_create_user_redline_histories_table',1);
INSERT INTO `migrations` VALUES (89,'2023_02_09_065443_create_adjustement_types_table',1);
INSERT INTO `migrations` VALUES (90,'2023_02_09_072136_create_approval_and_request_comments_table',1);
INSERT INTO `migrations` VALUES (91,'2023_02_09_072136_create_approvals_and_requests_table',1);
INSERT INTO `migrations` VALUES (92,'2023_02_09_073930_create_approvals_and_requeststatuses_table',1);
INSERT INTO `migrations` VALUES (93,'2023_02_14_073631_create_fine_fees_table',1);
INSERT INTO `migrations` VALUES (94,'2023_02_15_072556_create_event_calendars_table',1);
INSERT INTO `migrations` VALUES (95,'2023_02_16_075847_create_template_assigns_table',1);
INSERT INTO `migrations` VALUES (96,'2023_02_20_090440_create_additional_locations_table',1);
INSERT INTO `migrations` VALUES (97,'2023_02_20_093919_create_onboarding_employee_locations_table',1);
INSERT INTO `migrations` VALUES (98,'2023_02_22_114210_create_documents_table',1);
INSERT INTO `migrations` VALUES (99,'2023_02_22_114224_create_document_files_table',1);
INSERT INTO `migrations` VALUES (100,'2023_02_22_153337_create_document_types_table',1);
INSERT INTO `migrations` VALUES (101,'2023_02_27_110237_create_position_pay_frequencies_table',1);
INSERT INTO `migrations` VALUES (102,'2023_02_27_133026_create_position_reconciliations_table',1);
INSERT INTO `migrations` VALUES (103,'2023_03_01_082216_create_clawback_settlements_table',1);
INSERT INTO `migrations` VALUES (104,'2023_03_01_102159_create_group_permissions_table',1);
INSERT INTO `migrations` VALUES (105,'2023_03_01_134315_create_policies_tabs_table',1);
INSERT INTO `migrations` VALUES (106,'2023_03_03_065158_create_group_masters_table',1);
INSERT INTO `migrations` VALUES (107,'2023_03_03_065158_create_notifications_table',1);
INSERT INTO `migrations` VALUES (108,'2023_03_03_065158_create_payroll_history_table',1);
INSERT INTO `migrations` VALUES (109,'2023_03_03_065158_create_payrolls_table',1);
INSERT INTO `migrations` VALUES (110,'2023_03_03_065158_get_payroll_data',1);
INSERT INTO `migrations` VALUES (111,'2023_04_11_112248_create_schedule_time_masters_table',1);
INSERT INTO `migrations` VALUES (112,'2023_04_11_130616_create_user_schedule_times_table',1);
INSERT INTO `migrations` VALUES (113,'2023_04_12_101102_create_lead_comments_table',1);
INSERT INTO `migrations` VALUES (114,'2023_04_13_120152_create_employee_id_setting_table',1);
INSERT INTO `migrations` VALUES (115,'2023_04_13_123321_create_employee_personal_detail_table',1);
INSERT INTO `migrations` VALUES (116,'2023_04_14_074952_create_lead_comment_replies_table',1);
INSERT INTO `migrations` VALUES (117,'2023_04_14_115536_create_email_notification_settings_table',1);
INSERT INTO `migrations` VALUES (118,'2023_04_14_122519_create_domain_settings_table',1);
INSERT INTO `migrations` VALUES (119,'2023_04_18_121106_create_document_to_upload_table',1);
INSERT INTO `migrations` VALUES (120,'2023_04_18_121426_create_additional_info_for_employee_to_get_started_table',1);
INSERT INTO `migrations` VALUES (121,'2023_05_09_070243_create_payroll_statuses_table',1);
INSERT INTO `migrations` VALUES (122,'2023_05_20_014136_create_weekly_pay_frequencies_table',1);
INSERT INTO `migrations` VALUES (123,'2023_05_20_093443_create__doc_history_for_templete_table',1);
INSERT INTO `migrations` VALUES (124,'2023_05_22_014452_create_monthly_pay_frequencies_table',1);
INSERT INTO `migrations` VALUES (125,'2023_05_24_023131_create_payroll_adjustments_table',1);
INSERT INTO `migrations` VALUES (126,'2023_05_25_090422_create_reconciliations_adjustement_table',1);
INSERT INTO `migrations` VALUES (127,'2023_05_26_025221_create_user_reconciliation_commissions_table',1);
INSERT INTO `migrations` VALUES (128,'2023_05_26_025221_create_user_reconciliation_commissions_withholding_table',1);
INSERT INTO `migrations` VALUES (129,'2023_05_29_042357_create_onboarding_user_redlines_table',1);
INSERT INTO `migrations` VALUES (130,'2023_05_30_042714_create_payroll_alerts_table',1);
INSERT INTO `migrations` VALUES (131,'2023_06_06_044714_create_announcements_table',1);
INSERT INTO `migrations` VALUES (132,'2023_06_07_044523_create_set_goals_table',1);
INSERT INTO `migrations` VALUES (133,'2023_06_07_063917_create_digisigner_logs_table',1);
INSERT INTO `migrations` VALUES (134,'2023_06_20_094218_create_location_redline_history_table',1);
INSERT INTO `migrations` VALUES (135,'2023_06_21_091220_create_request_approval_by_pid_table',1);
INSERT INTO `migrations` VALUES (136,'2023_06_22_011707_create_override_system_settings_table',1);
INSERT INTO `migrations` VALUES (137,'2023_06_27_054037_create_email_logins_table',1);
INSERT INTO `migrations` VALUES (138,'2023_07_03_021834_create_payroll_adjustment_details_table',1);
INSERT INTO `migrations` VALUES (139,'2023_07_06_084258_create_onboarding_employee_deduction_table',1);
INSERT INTO `migrations` VALUES (140,'2023_07_06_084320_create_user_deduction_table',1);
INSERT INTO `migrations` VALUES (141,'2023_08_07_035717_create__user_commission_history',1);
INSERT INTO `migrations` VALUES (142,'2023_08_07_040822_create_user_upfront_history',1);
INSERT INTO `migrations` VALUES (143,'2023_08_07_041343_create_user_override_history',1);
INSERT INTO `migrations` VALUES (144,'2023_08_24_072135_create_add_on_plans_table',1);
INSERT INTO `migrations` VALUES (145,'2023_08_24_080207_create_billing_types_table',1);
INSERT INTO `migrations` VALUES (146,'2023_08_24_090914_create_subscriptions_table',1);
INSERT INTO `migrations` VALUES (147,'2023_08_24_102836_create_business_addresses_table',1);
INSERT INTO `migrations` VALUES (148,'2023_08_24_111036_create_plans_table',1);
INSERT INTO `migrations` VALUES (149,'2023_09_05_031808_create_payroll_shift_histories_table',1);
INSERT INTO `migrations` VALUES (150,'2023_09_07_061108_create_subscription_billing_histories_table',1);
INSERT INTO `migrations` VALUES (151,'2023_09_13_061535_create_sequi_docs_email_settings_table',1);
INSERT INTO `migrations` VALUES (152,'2023_09_13_065214_create_sequi_docs_template_permissions_table',1);
INSERT INTO `migrations` VALUES (153,'2023_09_22_000001_create_envelopes_table',1);
INSERT INTO `migrations` VALUES (154,'2023_09_22_000002_create_document_signers_table',1);
INSERT INTO `migrations` VALUES (155,'2023_09_22_032059_create_visible_signatures_table',1);
INSERT INTO `migrations` VALUES (156,'2023_09_22_044922_create_digital_signatures_table',1);
INSERT INTO `migrations` VALUES (157,'2023_09_22_081300_create_legacy_api_raw_data_histories_table',1);
INSERT INTO `migrations` VALUES (158,'2023_09_27_031353_create_sequi_docs_send_agreement_with_templates_table',1);
INSERT INTO `migrations` VALUES (159,'2023_09_28_070934_create_sales_invoice_details_table',1);
INSERT INTO `migrations` VALUES (160,'2023_09_29_082829_create_digitally_signed_pdfs_table',1);
INSERT INTO `migrations` VALUES (161,'2023_10_03_073216_create_email_configuration_table',1);
INSERT INTO `migrations` VALUES (162,'2023_10_28_095440_create_jobs_table',1);
INSERT INTO `migrations` VALUES (163,'2023_10_30_065442_create_auth_group_table',1);
INSERT INTO `migrations` VALUES (164,'2023_10_30_070430_create_auth_group_permissions_table',1);
INSERT INTO `migrations` VALUES (165,'2023_10_30_071056_create_auth_permission_table',1);
INSERT INTO `migrations` VALUES (166,'2023_10_30_071335_create_auth_user_table',1);
INSERT INTO `migrations` VALUES (167,'2023_10_30_072040_create_auth_user_groups_table',1);
INSERT INTO `migrations` VALUES (168,'2023_10_30_072356_create_auth_user_user_permissions_table',1);
INSERT INTO `migrations` VALUES (169,'2023_10_31_034554_create_gusto_companies_table',1);
INSERT INTO `migrations` VALUES (170,'2023_10_31_091427_create_legacy_api_raw_data_update_histories_table',1);
INSERT INTO `migrations` VALUES (171,'2023_10_31_094353_create_manual_overrides_table',1);
INSERT INTO `migrations` VALUES (172,'2023_10_31_094858_create_manual_overrides_history_table',1);
INSERT INTO `migrations` VALUES (173,'2023_10_31_100642_create_company_requests_table',1);
INSERT INTO `migrations` VALUES (174,'2023_10_31_101316_create_onboarding_employee_override_table',1);
INSERT INTO `migrations` VALUES (175,'2023_10_31_105258_create_override_status_table',1);
INSERT INTO `migrations` VALUES (176,'2023_10_31_110009_create_pay_frequency_setting_table',1);
INSERT INTO `migrations` VALUES (177,'2023_10_31_111047_create_payroll_deductions_table',1);
INSERT INTO `migrations` VALUES (178,'2023_10_31_150543_create_plan_with_add_on_plans_table',1);
INSERT INTO `migrations` VALUES (179,'2023_11_01_022516_create_sale_data_update_logs_table',1);
INSERT INTO `migrations` VALUES (180,'2023_11_01_025042_create_sequi_docs_template_signature_table',1);
INSERT INTO `migrations` VALUES (181,'2023_11_01_040044_create_user_commission_table',1);
INSERT INTO `migrations` VALUES (182,'2023_11_01_045217_create_user_transfer_history_table',1);
INSERT INTO `migrations` VALUES (183,'2023_11_01_060329_create_user_withheld_history_table',1);
INSERT INTO `migrations` VALUES (184,'2023_11_02_031808_create_excel_import_history_table',1);
INSERT INTO `migrations` VALUES (185,'2023_11_03_064315_create_user_activity_logs_table',1);
INSERT INTO `migrations` VALUES (186,'2023_11_07_020845_create_other_templates_table',1);
INSERT INTO `migrations` VALUES (187,'2023_11_08_064201_create_users_additional_emails_table',1);
INSERT INTO `migrations` VALUES (188,'2023_11_10_025009_create_company_billing_addresses_table',1);
INSERT INTO `migrations` VALUES (189,'2023_11_14_084742_create_onboarding_additional_emails_table',1);
INSERT INTO `migrations` VALUES (190,'2023_11_27_020715_create_user_self_gen_commmission_histories_table',1);
INSERT INTO `migrations` VALUES (191,'2023_11_28_055014_create_credits_table',1);
INSERT INTO `migrations` VALUES (192,'2023_11_29_053520_create_user_additional_office_override_histories_table',1);
INSERT INTO `migrations` VALUES (193,'2023_11_30_020711_create_sequi_docs_document_comments_table',1);
INSERT INTO `migrations` VALUES (194,'2023_12_04_084305_create_new_sequi_docs_templates_table',1);
INSERT INTO `migrations` VALUES (195,'2023_12_05_025753_create_new_sequi_docs_template_permissions_table',1);
INSERT INTO `migrations` VALUES (196,'2023_12_05_071848_create_new_sequi_docs_send_document_with_offer_letters_table',1);
INSERT INTO `migrations` VALUES (197,'2023_12_11_021649_create_reconciliation_status_for_skiped_user_table',1);
INSERT INTO `migrations` VALUES (198,'2023_12_11_063716_create_timezones_table',1);
INSERT INTO `migrations` VALUES (199,'2023_12_12_052452_create_reconciliationfinalize_history_table',1);
INSERT INTO `migrations` VALUES (200,'2023_12_13_035222_update_envelope_table',1);
INSERT INTO `migrations` VALUES (201,'2023_12_13_040609_create_envelope_documets_table',1);
INSERT INTO `migrations` VALUES (202,'2023_12_13_064919_update_document_signers_table',1);
INSERT INTO `migrations` VALUES (203,'2023_12_13_074730_update_visible_signature_table',1);
INSERT INTO `migrations` VALUES (204,'2023_12_19_040521_update_visible_signatures_table_add_doc_id',1);
INSERT INTO `migrations` VALUES (205,'2023_12_20_003141_create_ticket_faqs_table',1);
INSERT INTO `migrations` VALUES (206,'2023_12_20_013526_create_tickets_table',1);
INSERT INTO `migrations` VALUES (207,'2023_12_20_024601_create_ticket_attachments_table',1);
INSERT INTO `migrations` VALUES (208,'2023_12_22_050009_create_new_sequi_docs_documents_table',1);
INSERT INTO `migrations` VALUES (209,'2023_12_28_011953_create_ticket_modules_table',1);
INSERT INTO `migrations` VALUES (210,'2023_12_28_020054_update_envelopes_table_add_plain_password',1);
INSERT INTO `migrations` VALUES (211,'2023_12_28_025850_create_ticket_faq_categories_table',1);
INSERT INTO `migrations` VALUES (212,'2023_12_29_012322_create_email_details_table',1);
INSERT INTO `migrations` VALUES (213,'2023_12_29_015726_create_hiring_status_table',1);
INSERT INTO `migrations` VALUES (214,'2023_12_31_062229_update_envelope_document_table_add_is_pdf_column',1);
INSERT INTO `migrations` VALUES (215,'2024_01_02_013643_create_new_sequi_docs_signature_request_logs_table',1);
INSERT INTO `migrations` VALUES (216,'2024_01_03_003432_create_new_sequi_docs_upload_document_types_table',1);
INSERT INTO `migrations` VALUES (217,'2024_01_03_043448_update_envelope_document_table_add_template_category',1);
INSERT INTO `migrations` VALUES (218,'2024_01_05_004807_create_user_profile_history_table',1);
INSERT INTO `migrations` VALUES (219,'2024_01_09_024440_create_new_sequi_docs_upload_document_files_table',1);
INSERT INTO `migrations` VALUES (220,'2024_01_09_064822_create_one_time_payments_table',1);
INSERT INTO `migrations` VALUES (221,'2024_01_11_005505_create_recon_override_history_table',1);
INSERT INTO `migrations` VALUES (222,'2024_01_15_021936_update_envelope_documents_table_add_template_category_type',1);
INSERT INTO `migrations` VALUES (223,'2024_01_16_133252_update_envelope_documents_table_add_document_expiry_column',1);
INSERT INTO `migrations` VALUES (224,'2024_01_17_092618_create_new_sequi_docs_document_comments_table',1);
INSERT INTO `migrations` VALUES (225,'2024_01_17_160832_update_visible_signatures_table_add_document_id',1);
INSERT INTO `migrations` VALUES (226,'2024_01_23_234333_create_reconciliations_adjustement_details',1);
INSERT INTO `migrations` VALUES (227,'2024_01_28_091420_create_user_commission_lock_table',1);
INSERT INTO `migrations` VALUES (228,'2024_01_28_093648_create_user_overrides_lock_table',1);
INSERT INTO `migrations` VALUES (229,'2024_01_28_095004_create_clawback_settlements_lock_table',1);
INSERT INTO `migrations` VALUES (230,'2024_01_28_101134_create_approvals_and_requests_lock_table',1);
INSERT INTO `migrations` VALUES (231,'2024_01_28_104517_create_payroll_adjustments_lock_table',1);
INSERT INTO `migrations` VALUES (232,'2024_01_28_105721_create_payroll_adjustment_detail_histories_table',1);
INSERT INTO `migrations` VALUES (233,'2024_01_28_111158_create_user_reconciliation_commissions_lock_table',1);
INSERT INTO `migrations` VALUES (234,'2024_02_01_073302_create_other_important_logs_table',1);
INSERT INTO `migrations` VALUES (235,'2024_02_04_231041_create_everee_transactions_log_table',1);
INSERT INTO `migrations` VALUES (236,'2024_02_04_232701_create_paystub_employee_table',1);
INSERT INTO `migrations` VALUES (237,'2024_02_05_052056_create_payroll_adjustment_details_lock_table',1);
INSERT INTO `migrations` VALUES (238,'2024_02_07_003600_add_doc_version_to_new_sequi_docs_documents_table',1);
INSERT INTO `migrations` VALUES (239,'2024_02_07_054005_user_organization_history',1);
INSERT INTO `migrations` VALUES (240,'2024_02_08_055944_alter_new_sequi_docs_upload_document_files_table_add_file_version_column',1);
INSERT INTO `migrations` VALUES (241,'2024_02_09_020950_user_deduction_history',1);
INSERT INTO `migrations` VALUES (242,'2024_02_12_234504_create_move_to_reconciliations_table',1);
INSERT INTO `migrations` VALUES (243,'2024_02_12_235914_create_s_clearance_plans_table',1);
INSERT INTO `migrations` VALUES (244,'2024_02_14_005455_add_effective_date_to_users',1);
INSERT INTO `migrations` VALUES (245,'2024_02_14_061128_add_existing_employee_new_manager_id_to_user_organization_history',1);
INSERT INTO `migrations` VALUES (246,'2024_02_15_064456_create_s_clearance_configurations_table',1);
INSERT INTO `migrations` VALUES (247,'2024_02_20_004603_create_s_clearance_tokens_table',1);
INSERT INTO `migrations` VALUES (248,'2024_02_21_012641_create_s_clearance_screening_request_lists',1);
INSERT INTO `migrations` VALUES (249,'2024_02_22_125111_payroll_common',1);
INSERT INTO `migrations` VALUES (250,'2024_02_29_170201_create_additional_pay_frequencies_table',1);
INSERT INTO `migrations` VALUES (251,'2024_03_04_061830_is_background_verificaton_to_onboarding_employees',1);
INSERT INTO `migrations` VALUES (252,'2024_03_05_162832_create_s_clearance_statuses_table',1);
INSERT INTO `migrations` VALUES (253,'2024_03_07_224747_add_product_name_to_plans_table',1);
INSERT INTO `migrations` VALUES (254,'2024_03_08_152443_add_exam_attempts_to_s_clearance_screening_request_lists',1);
INSERT INTO `migrations` VALUES (255,'2024_03_15_054357_create_user_excel_import_histories_table',2);
INSERT INTO `migrations` VALUES (256,'2024_03_21_024352_add_is_displayed_to_user_commission_table',2);
INSERT INTO `migrations` VALUES (257,'2024_03_21_024412_add_is_displayed_to_clawback_settlements_table',2);
INSERT INTO `migrations` VALUES (258,'2024_03_21_024422_add_is_displayed_to_user_overrides_table',2);
INSERT INTO `migrations` VALUES (259,'2024_03_18_043633_create_pipeline_leads_status_history_table',3);
INSERT INTO `migrations` VALUES (260,'2024_03_18_060429_add_sales_setter_name_to_legacy_api_data_null_table',3);
INSERT INTO `migrations` VALUES (261,'2024_03_18_060439_add_sales_setter_name_to_legacy_api_raw_data_histories_table',3);
INSERT INTO `migrations` VALUES (262,'2024_03_18_122055_create_pipline_lead_status_table',3);
INSERT INTO `migrations` VALUES (263,'2024_03_19_014431_update_new_sequi_docs_send_document_with_offer_letters_add_manual_doc_type_id',3);
INSERT INTO `migrations` VALUES (264,'2024_03_19_032325_update_new_sequi_docs_send_document_with_offer_letters_add_column_last_reminder_sent_at',3);
INSERT INTO `migrations` VALUES (265,'2024_03_20_010004_create_projection_user_overrides_table',3);
INSERT INTO `migrations` VALUES (266,'2024_03_20_124919_sclearance_plan_id_to_plans',3);
INSERT INTO `migrations` VALUES (267,'2024_03_20_125113_plan_id_to_s_clearance_screening_request_lists',3);
INSERT INTO `migrations` VALUES (268,'2024_03_27_005306_add_batch_no_to_user_profile_history_table',3);
INSERT INTO `migrations` VALUES (269,'2024_03_08_152443_add_approved_declined_by_to_s_clearance_screening_request_lists',4);
INSERT INTO `migrations` VALUES (270,'2024_03_21_124429_key_used_mfa_token_to_s_clearance_tokens',4);
INSERT INTO `migrations` VALUES (271,'2024_03_28_042044_user_overrides_queue',4);
INSERT INTO `migrations` VALUES (272,'2024_03_28_045144_add_amount_column_to_projection_user_overrides_table',4);
INSERT INTO `migrations` VALUES (273,'2024_03_28_065015_update_document_signers_table_check_and_add_columns',4);
INSERT INTO `migrations` VALUES (274,'2024_03_28_070830_update_visible_signatures_table_check_and_add_columns',4);
INSERT INTO `migrations` VALUES (275,'2024_03_28_175329_update_envelopes_table_check_and_add_columns',4);
INSERT INTO `migrations` VALUES (276,'2024_04_02_031206_add_action_item_status_to_user_override_history_table',4);
INSERT INTO `migrations` VALUES (277,'2024_04_02_033143_add_action_item_status_to_user_redline_histories_table',4);
INSERT INTO `migrations` VALUES (278,'2024_04_02_034149_add_action_item_status_to_user_commission_history_table',4);
INSERT INTO `migrations` VALUES (279,'2024_04_02_034521_add_action_item_status_to_user_upfront_history_table',4);
INSERT INTO `migrations` VALUES (280,'2024_04_02_034907_add_action_item_status_to_user_upfront_history_table',4);
INSERT INTO `migrations` VALUES (281,'2024_04_02_035633_add_action_item_status_to_user_organization_history_table',4);
INSERT INTO `migrations` VALUES (282,'2024_04_02_040924_add_action_item_status_to_onboarding_employees_table',4);
INSERT INTO `migrations` VALUES (283,'2024_04_02_041310_add_action_item_status_to_approvals_and_requests_table',4);
INSERT INTO `migrations` VALUES (284,'2024_04_02_041623_add_action_item_status_to_documents_table',4);
INSERT INTO `migrations` VALUES (285,'2024_04_02_041911_add_action_item_status_to_users_table',4);
INSERT INTO `migrations` VALUES (286,'2024_04_02_042236_add_action_item_status_to_sale_masters_table',4);
INSERT INTO `migrations` VALUES (287,'2024_04_03_102931_add_pipeline_status_id_to_leads',4);
INSERT INTO `migrations` VALUES (288,'2024_04_03_103650_add_colour_code_to_hiring_status',4);
INSERT INTO `migrations` VALUES (289,'2024_04_04_070045_add_in_process_column_to_legacy_weekly_sheet_table',4);
INSERT INTO `migrations` VALUES (290,'2024_04_04_101455_update_leads_table_set_pipeline_status_id_default_value',4);
INSERT INTO `migrations` VALUES (291,'2024_04_04_145405_add_position_id_to_projection_user_overrides_table',4);
INSERT INTO `migrations` VALUES (292,'2024_04_05_190423_update_table_add_column_show_in_card',4);
INSERT INTO `migrations` VALUES (293,'2024_04_05_232824_add_comment_by_to_payroll_adjustment_details_table',4);
INSERT INTO `migrations` VALUES (294,'2024_04_05_233055_add_comment_by_to_payroll_adjustment_details_lock_table',4);
INSERT INTO `migrations` VALUES (295,'2024_04_06_102056_create_sale_master_projections',4);
INSERT INTO `migrations` VALUES (296,'2024_04_10_102225_change_redline_to_user_transfer_history_table',4);
INSERT INTO `migrations` VALUES (297,'2023_06_22_011707_create_upfront_system_settings_table',5);
INSERT INTO `migrations` VALUES (298,'2024_04_09_102315_create_stripe_response_logs_table',5);
INSERT INTO `migrations` VALUES (299,'2024_04_10_102931_add_pipeline_status_id_to_leads',5);
INSERT INTO `migrations` VALUES (300,'2024_04_10_103650_add_colour_code_to_hiring_status',5);
INSERT INTO `migrations` VALUES (301,'2024_04_10_125358_add_last_payment_message_to_subscription_billing_histories_table',5);
INSERT INTO `migrations` VALUES (302,'2024_04_17_181103_drop_column_pipline_status_id_in_lead',5);
INSERT INTO `migrations` VALUES (303,'2024_04_17_181533_add_columns_in_leads_table',5);
INSERT INTO `migrations` VALUES (304,'2024_04_19_043633_create_pipeline_lead_status_table',5);
INSERT INTO `migrations` VALUES (305,'2024_04_20_1815335433_add_columns_in_onboarding_employees_table',5);
INSERT INTO `migrations` VALUES (306,'2024_04_20_181533_add_columns_in_leads_table',5);
INSERT INTO `migrations` VALUES (307,'2024_04_20_190424_update_table_add_column_pipelie_status_id',5);
INSERT INTO `migrations` VALUES (308,'2024_04_20_190424_update_table_add_column_show_on_card',5);
INSERT INTO `migrations` VALUES (309,'2024_04_20_190426_update_table_add_column_show_on_card',5);
INSERT INTO `migrations` VALUES (310,'2024_04_20_200424_update_table_add_column_pipeline_status_id',5);
INSERT INTO `migrations` VALUES (311,'2024_04_20_200426_update_table_add_column_show_on_card',5);
INSERT INTO `migrations` VALUES (312,'2024_04_20_210424_update_table_add_column_pipeline_status_id',5);
INSERT INTO `migrations` VALUES (313,'2024_04_21_220424_update_table_add_column_pipeline_status_id',5);
INSERT INTO `migrations` VALUES (314,'2024_04_22_200426_update_table_add_column_show_on_card',5);
INSERT INTO `migrations` VALUES (315,'2024_04_18_181533_add_columns_in_locations_table',6);
INSERT INTO `migrations` VALUES (316,'2024_05_01_231111_create_user_manager_histories_table',7);
INSERT INTO `migrations` VALUES (317,'2024_05_01_231123_create_user_is_manager_histories_table',7);
INSERT INTO `migrations` VALUES (318,'2024_05_10_233445_add_minimum_billing_to_subscriptions_table',7);
INSERT INTO `migrations` VALUES (319,'2024_04_23_000606_create_custom_field_table',8);
INSERT INTO `migrations` VALUES (320,'2024_04_25_123347_update_table_custom_field',8);
INSERT INTO `migrations` VALUES (321,'2024_04_26_130539_update_column_in_custom_field_table',8);
INSERT INTO `migrations` VALUES (322,'2024_04_26_430539_add_column_in_custom_field_table',8);
INSERT INTO `migrations` VALUES (323,'2024_04_26_573507_create_custom_field_history_table',8);
INSERT INTO `migrations` VALUES (324,'2024_04_29_010119_add_column_to_table',8);
INSERT INTO `migrations` VALUES (325,'2024_05_15_061901_add_gross_pay_to_payrolls_table',8);
INSERT INTO `migrations` VALUES (326,'2024_05_17_135643_insert_manual__status_into_s_clearance_statuses',8);
INSERT INTO `migrations` VALUES (327,'2024_05_20_061106_add_pay_period_from_to_custom_field_history_table',8);
INSERT INTO `migrations` VALUES (328,' 2024_04_26_124920_add_column_in_payrolls_table',9);
INSERT INTO `migrations` VALUES (329,'2024_04_16_005939_create_payroll_setups_table',10);
INSERT INTO `migrations` VALUES (330,'2024_04_18_193255_add_columns_in_custom_field_table',10);
INSERT INTO `migrations` VALUES (331,'2024_04_22_000606_drop_custom_filed_table',10);
INSERT INTO `migrations` VALUES (332,'2024_04_26_124920_add_column_in_payrolls_table',10);
INSERT INTO `migrations` VALUES (333,'2024_04_26_125449_add_column_in_payroll_history_table',10);
INSERT INTO `migrations` VALUES (334,'2024_05_06_134346_add_column_to_custom_field_history_table',10);
INSERT INTO `migrations` VALUES (335,'2024_05_14_213535_create_adwance_payment_settings_table',10);
INSERT INTO `migrations` VALUES (336,'2024_05_15_000826_add_parent_id_to_approvals_and_requests_table',10);
INSERT INTO `migrations` VALUES (337,'2024_05_15_213007_create_sequiai_plans_table',10);
INSERT INTO `migrations` VALUES (338,'2024_05_15_215351_create_sequiai_request_histories_table',10);
INSERT INTO `migrations` VALUES (339,'2024_05_23_182256_add_offer_letter_template_id_to_positions',10);
INSERT INTO `migrations` VALUES (340,'2024_05_24_070810_add_open_status_from_bank_to_additional_pay_frequencies_table',10);
INSERT INTO `migrations` VALUES (341,'2024_04_12_133925_add_lead_id_to_onboarding_employees_table',11);
INSERT INTO `migrations` VALUES (342,'2024_04_25_100804_add_length_of_agreement_to_legacy_api_data_null_table',11);
INSERT INTO `migrations` VALUES (343,'2024_04_25_100829_add_length_of_agreement_to_legacy_api_raw_data_table',11);
INSERT INTO `migrations` VALUES (344,'2024_04_25_100849_add_length_of_agreement_to_legacy_api_raw_data_histories_table',11);
INSERT INTO `migrations` VALUES (345,'2024_04_25_100913_add_length_of_agreement_to_sale_masters_table',11);
INSERT INTO `migrations` VALUES (346,'2024_05_16_100804_add_initial_service_cost_to_legacy_api_data_null_table',11);
INSERT INTO `migrations` VALUES (347,'2024_05_16_100829_add_initial_service_cost_to_legacy_api_raw_data_table',11);
INSERT INTO `migrations` VALUES (348,'2024_05_16_100849_add_initial_service_cost_to_legacy_api_raw_data_histories_table',11);
INSERT INTO `migrations` VALUES (349,'2024_05_16_100913_add_initial_service_cost_to_sale_masters_table',11);
INSERT INTO `migrations` VALUES (350,'2024_05_17_034510_add_lead_id_to_onboarding_employees_table',11);
INSERT INTO `migrations` VALUES (351,'2024_05_17_161053_create_crm_sale_info_table',11);
INSERT INTO `migrations` VALUES (352,'2024_05_17_161943_create_buckets_table',11);
INSERT INTO `migrations` VALUES (353,'2024_05_17_163348_create_bucket_by_job_table',11);
INSERT INTO `migrations` VALUES (354,'2024_05_17_164416_create_bucket_subtask_table',11);
INSERT INTO `migrations` VALUES (355,'2024_05_17_164802_create_bucket_subtask_by_job_table',11);
INSERT INTO `migrations` VALUES (356,'2024_05_17_171226_add_data_to_buckets',11);
INSERT INTO `migrations` VALUES (357,'2024_05_20_212915_change_column_type_in_users_table',11);
INSERT INTO `migrations` VALUES (358,'2024_05_22_101956_add_column_to_users_table',11);
INSERT INTO `migrations` VALUES (359,'2024_05_22_102704_add_column_to_crm_sale_info_table',11);
INSERT INTO `migrations` VALUES (360,'2024_05_22_113508_create_users_preference_table',11);
INSERT INTO `migrations` VALUES (361,'2024_05_23_114647_create_crm_comments_table',11);
INSERT INTO `migrations` VALUES (362,'2024_05_23_122349_create_crm_attachments_table',11);
INSERT INTO `migrations` VALUES (363,'2024_05_23_172736_alter_to_salemaster_sale_master_process_legacy_api_data_null_table',11);
INSERT INTO `migrations` VALUES (364,'2024_05_24_100241_alter_to_salemaster_legacy_api_data_null_table',11);
INSERT INTO `migrations` VALUES (365,'2024_05_27_105606_add_column_to_users_preference',11);
INSERT INTO `migrations` VALUES (366,'2024_05_28_154703_add_column_to_crm_setting_table',11);
INSERT INTO `migrations` VALUES (367,'2024_05_31_045340_add_pay_period_in_payroll_deductions_table',11);
INSERT INTO `migrations` VALUES (368,'2024_05_31_163958_create_crmsale_custom_field_table',11);
INSERT INTO `migrations` VALUES (369,'2024_05_31_164900_add_new_column_to_crm_sale_info_table',11);
INSERT INTO `migrations` VALUES (370,'2024_06_03_101551_add_clawback_status_in_clawback_settlements_table',11);
INSERT INTO `migrations` VALUES (371,'2024_06_06_001459_add_mark_paid_and_next_payroll_in_payroll_deductions_table',11);
INSERT INTO `migrations` VALUES (372,'2024_06_06_072601_add_deleted_at_to_additional_locations_table',11);
INSERT INTO `migrations` VALUES (373,'2024_06_11_233016_change_primary_key_id_to_excel_import_history_table',12);
INSERT INTO `migrations` VALUES (374,'2024_06_11_234229_add_excel_import_id_to_legacy_api_raw_data_histories_table',12);
INSERT INTO `migrations` VALUES (375,'2024_06_12_165307_update_users_and_user_override_history_tables',12);
INSERT INTO `migrations` VALUES (376,'2024_06_12_165320_update_onboarding_employees_and_onboarding_employee_override_tables',12);
INSERT INTO `migrations` VALUES (377,'2024_06_18_104106_create_payroll_deduction_locks_table',13);
INSERT INTO `migrations` VALUES (378,'2024_06_19_104208_add_column_in_user_deduction_table',13);
INSERT INTO `migrations` VALUES (379,'2024_06_19_104858_add_column_in_user_deduction_history_table',13);
INSERT INTO `migrations` VALUES (380,'2024_06_20_040815_make_user_prompt_type_nullable_in_sequiai_request_histories_table',14);
INSERT INTO `migrations` VALUES (381,'2024_06_24_160557_add_new_column_to_company_profiles_table',14);
INSERT INTO `migrations` VALUES (382,'2024_06_03_145126_create_s_clearance_transunion_responses_table',15);
INSERT INTO `migrations` VALUES (383,'2024_06_25_113302_create_import_categories_table',15);
INSERT INTO `migrations` VALUES (384,'2024_06_25_113850_create_import_templates_table',15);
INSERT INTO `migrations` VALUES (385,'2024_06_25_113851_create_import_template_details_table',15);
INSERT INTO `migrations` VALUES (386,'2024_06_25_122913_create_profile_access_permissions_table',15);
INSERT INTO `migrations` VALUES (387,'2024_06_25_123944_create_import_category_details_table',15);
INSERT INTO `migrations` VALUES (388,'2024_06_26_160935_add_column_in_profile_access_permissions_table',15);
INSERT INTO `migrations` VALUES (389,'2024_06_27_030724_add_is_encrypted_to_settings_table',15);
INSERT INTO `migrations` VALUES (390,'2024_06_28_033052_add_columns_to_import_category_details_table',15);
INSERT INTO `migrations` VALUES (391,'2024_06_28_195911_create_projection_user_commissions_table',15);
INSERT INTO `migrations` VALUES (392,'2024_06_12_144912_add_indexes_to_tables',16);
INSERT INTO `migrations` VALUES (393,'2024_06_25_032610_create_devices_table',16);
INSERT INTO `migrations` VALUES (394,'2024_07_08_012805_add_coulumns_to_sales_invoice_details_table',16);
INSERT INTO `migrations` VALUES (395,'2024_07_09_120851_add_attempting_signing_to_new_sequi_docs_documents_table',16);
INSERT INTO `migrations` VALUES (396,'2024_07_01_182003_add_new_column_to_company_profiles_table',17);
INSERT INTO `migrations` VALUES (397,'2024_07_07_223358_add_during_to_user_overrides_table',17);
INSERT INTO `migrations` VALUES (398,'2024_07_07_223703_add_during_to_clawback_settlements_table',17);
INSERT INTO `migrations` VALUES (399,'2024_07_07_223727_add_during_to_user_overrides_lock_table',17);
INSERT INTO `migrations` VALUES (400,'2024_07_07_223816_add_during_to_clawback_settlements_lock_table',17);
INSERT INTO `migrations` VALUES (401,'2024_07_09_152042_add_new_column_to_subscription_billing_histories_table',17);
INSERT INTO `migrations` VALUES (402,'2024_07_15_011724_add_column_is_stop_payroll_in_payroll_deductions_table',17);
INSERT INTO `migrations` VALUES (403,'2024_07_15_014505_add_column_is_stop_payroll_in_payroll_deduction_locks_table',17);
INSERT INTO `migrations` VALUES (404,'2024_07_19_142630_add_auto_pay_to_legacy_api_data_null_table',17);
INSERT INTO `migrations` VALUES (405,'2024_07_19_142645_add_auto_pay_to_legacy_api_raw_data_table',17);
INSERT INTO `migrations` VALUES (406,'2024_07_19_142659_add_auto_pay_to_legacy_api_raw_data_histories_table',17);
INSERT INTO `migrations` VALUES (407,'2024_07_19_142712_add_auto_pay_to_sale_masters_table',17);
INSERT INTO `migrations` VALUES (408,'2024_07_19_151650_add_card_on_file_to_legacy_api_data_null_table',17);
INSERT INTO `migrations` VALUES (409,'2024_07_19_151707_add_card_on_file_to_legacy_api_raw_data_table',17);
INSERT INTO `migrations` VALUES (410,'2024_07_19_151727_add_card_on_file_to_legacy_api_raw_data_histories_table',17);
INSERT INTO `migrations` VALUES (411,'2024_07_19_151739_add_card_on_file_to_sale_masters_table',17);
INSERT INTO `migrations` VALUES (412,'2024_07_22_225701_add_adjustment_type_to_payroll_adjustment_details_table',17);
INSERT INTO `migrations` VALUES (413,'2024_08_01_192443_add_deleted_at_in_position_commission_deductions_table',17);
INSERT INTO `migrations` VALUES (414,'2024_08_12_155732_add_error_records_to_excel_import_history_table',18);
INSERT INTO `migrations` VALUES (415,'2024_08_28_043014_report_expiry_date_to_s_clearance_screening_request_lists',19);
INSERT INTO `migrations` VALUES (416,'2024_09_09_081638_add_changes_type_in_position_commission_deductions_table',19);
INSERT INTO `migrations` VALUES (417,'2024_09_09_082521_add_changes_type_in_user_deduction_history_table',19);
INSERT INTO `migrations` VALUES (418,'2024_09_18_045320_create_additional_custom_fields_table',20);
INSERT INTO `migrations` VALUES (419,'2024_09_18_045638_add_column_to_leads_table',20);
INSERT INTO `migrations` VALUES (420,'2024_09_18_075448_create_lead_custom_field_setting',20);
INSERT INTO `migrations` VALUES (421,'2024_09_23_093209_create_new_sequi_docs_send_smart_template_with_offer_letters_table',20);
INSERT INTO `migrations` VALUES (422,'2024_09_25_045423_add_system_generated_column_to_user_manager_histories_table',20);
INSERT INTO `migrations` VALUES (423,'2024_09_25_122757_create_daily_pay_frequencies_table',20);
INSERT INTO `migrations` VALUES (424,'2024_09_27_084023_add_smart_text_template_fied_keyval_to_new_sequi_docs_documents_table',20);
INSERT INTO `migrations` VALUES (425,'2024_10_18_001355_add_column_archived_at_to_additional_locations_table',20);
INSERT INTO `migrations` VALUES (426,'2024_10_21_060416_add_fixed_amount_and_is_flat_to_company_profiles_table',20);
INSERT INTO `migrations` VALUES (427,'2024_10_21_073704_add_flat_subscription_to_subscriptions_table',20);
INSERT INTO `migrations` VALUES (428,'2024_10_21_110814_add_ref_id_to_custom_field_table',20);
INSERT INTO `migrations` VALUES (429,'2024_10_22_170224_create_seasonal_users_logs_table',20);
INSERT INTO `migrations` VALUES (430,'2024_10_23_073022_add_rehire_column_to_users_table',20);
INSERT INTO `migrations` VALUES (431,'2024_10_24_110601_add_closer1_id_to_sale_masters_table',20);
INSERT INTO `migrations` VALUES (432,'2024_10_29_230737_add_terminate_column_to_users_table',20);
INSERT INTO `migrations` VALUES (433,'2024_11_06_205112_update_self_gen_upfront_type_enum_in_users_table',20);
INSERT INTO `migrations` VALUES (434,'2024_06_02_172945_change_recon_related_table_column_spelling',21);
INSERT INTO `migrations` VALUES (435,'2024_06_12_174412_update_positions_table_add_worker_type_column',21);
INSERT INTO `migrations` VALUES (436,'2024_06_12_211759_change_confirm_account_no_column_type_in_users_table',21);
INSERT INTO `migrations` VALUES (437,'2024_06_13_120522_create_wages_table',21);
INSERT INTO `migrations` VALUES (438,'2024_06_13_144844_update_position_commissions_table_add_position_commissions_locked',21);
INSERT INTO `migrations` VALUES (439,'2024_06_13_172933_rename_type_to_worker_type_for_time_clock',21);
INSERT INTO `migrations` VALUES (440,'2024_06_14_165132_create_scheduling_configuration',21);
INSERT INTO `migrations` VALUES (441,'2024_06_14_203651_encryption_log_table',21);
INSERT INTO `migrations` VALUES (442,'2024_06_16_133634_create_wp_user_schedule',21);
INSERT INTO `migrations` VALUES (443,'2024_06_17_175007_create_onboarding_employee_wages_table',21);
INSERT INTO `migrations` VALUES (444,'2024_06_19_074927_update_position_commissions_table_rename_position_commissions_locked',21);
INSERT INTO `migrations` VALUES (445,'2024_06_20_021048_update_wages_table_rename_status_column',21);
INSERT INTO `migrations` VALUES (446,'2024_06_20_044142_create_user_wages_table',21);
INSERT INTO `migrations` VALUES (447,'2024_06_20_044309_create_user_wages_histories_table',21);
INSERT INTO `migrations` VALUES (448,'2024_06_20_063151_make_commission_parentage_nullable',21);
INSERT INTO `migrations` VALUES (449,'2024_06_20_173319_add_column_in_approvals_and_requests_table',21);
INSERT INTO `migrations` VALUES (450,'2024_06_23_045047_add_column_in_user_wages_table',21);
INSERT INTO `migrations` VALUES (451,'2024_06_23_045510_add_column_in_user_wages_histories_table',21);
INSERT INTO `migrations` VALUES (452,'2024_06_23_155038_add_cloumn_is_mark_paid_and_is_next_payroll_to_reconciliation_finalize_history_table',21);
INSERT INTO `migrations` VALUES (453,'2024_06_24_162803_add_pto_effective_date_column_in_uuser_wages_table',21);
INSERT INTO `migrations` VALUES (454,'2024_06_24_163144_add_pto_effective_date_column_in_user_wages_histories_table',21);
INSERT INTO `migrations` VALUES (455,'2024_06_25_160117_add_office_id_to_wp_user_schedule_table',21);
INSERT INTO `migrations` VALUES (456,'2024_06_25_165505_add_worker_type_to_positions_table',21);
INSERT INTO `migrations` VALUES (457,'2024_06_25_165531_add_commission_status_to_position_commissions_table',21);
INSERT INTO `migrations` VALUES (458,'2024_06_25_165552_create_position_wages_table',21);
INSERT INTO `migrations` VALUES (459,'2024_06_25_165616_add_pay_type_to_onboarding_employees_table',21);
INSERT INTO `migrations` VALUES (460,'2024_06_25_165735_create_user_wages_history_table',21);
INSERT INTO `migrations` VALUES (461,'2024_06_25_170211_create_user_schedules_table',21);
INSERT INTO `migrations` VALUES (462,'2024_06_25_170231_create_user_schedule_details_table',21);
INSERT INTO `migrations` VALUES (463,'2024_06_25_170257_add_employee_payroll_id_to_approvals_and_requests_table',21);
INSERT INTO `migrations` VALUES (464,'2024_06_25_170344_create_user_attendances_table',21);
INSERT INTO `migrations` VALUES (465,'2024_06_25_170415_create_user_attendance_details_table',21);
INSERT INTO `migrations` VALUES (466,'2024_06_25_173312_add_worker_type_to_users_table',21);
INSERT INTO `migrations` VALUES (467,'2024_06_26_123431_add_column_is_user_recon_skip_in_reconciliation_finalize_history_table',21);
INSERT INTO `migrations` VALUES (468,'2024_06_28_111031_add_unused_pto_expires_in_user_wages_table',21);
INSERT INTO `migrations` VALUES (469,'2024_06_28_120258_add_cloumn_is_move_to_recon_to_payrolls_related_table',21);
INSERT INTO `migrations` VALUES (470,'2024_06_30_163040_create_move_to_recon_histories_table',21);
INSERT INTO `migrations` VALUES (471,'2024_07_01_172606_add_column_in_recon_adjustments_tables',21);
INSERT INTO `migrations` VALUES (472,'2024_07_06_104557_add_cloumn_is_move_to_in_deductiontables',21);
INSERT INTO `migrations` VALUES (473,'2024_07_10_161005_add_flag_cloumn_in_recon_finalize_history_table',21);
INSERT INTO `migrations` VALUES (474,'2024_07_16_103325_add_status_to_user_attendances_table',21);
INSERT INTO `migrations` VALUES (475,'2024_07_16_121414_create_payroll_hourly_salary_table',21);
INSERT INTO `migrations` VALUES (476,'2024_07_16_135826_create_payroll_overtimes_table',21);
INSERT INTO `migrations` VALUES (477,'2024_07_16_174906_create_reconciliation_finalize_history_locks_table',21);
INSERT INTO `migrations` VALUES (478,'2024_07_18_114030_add_new_column_to_user_attendances_table',21);
INSERT INTO `migrations` VALUES (479,'2024_07_19_123507_add_hourly_salary_column_in_payrolls_table',21);
INSERT INTO `migrations` VALUES (480,'2024_07_19_124902_add_hourly_salary_column_in_payroll_history_table',21);
INSERT INTO `migrations` VALUES (481,'2024_07_23_030531_add_new_column_to_payroll_adjustments_table',21);
INSERT INTO `migrations` VALUES (482,'2024_07_23_155906_add_column_is_present_user_attendances_table',21);
INSERT INTO `migrations` VALUES (483,'2024_07_24_052829_create_payroll_hourly_salary_lock_table',21);
INSERT INTO `migrations` VALUES (484,'2024_07_24_053120_create_payroll_overtimes_lock_table',21);
INSERT INTO `migrations` VALUES (485,'2024_07_25_111806_add_column_to_user_schedule_details',21);
INSERT INTO `migrations` VALUES (486,'2024_07_25_185358_create_table_scheduling_approval_setting',21);
INSERT INTO `migrations` VALUES (487,'2024_07_26_010002_add_recon_related_column_override_and_adjustments_table',21);
INSERT INTO `migrations` VALUES (488,'2024_07_26_105703_add_column_everee_status_to_user_attendances',21);
INSERT INTO `migrations` VALUES (489,'2024_07_28_032037_add_everee_embed_onboard_profile_to_users_table',21);
INSERT INTO `migrations` VALUES (490,'2024_07_29_233544_add_cloumn_in_payroll_deduction_table',21);
INSERT INTO `migrations` VALUES (491,'2024_07_30_053304_add_column_in_recon_finalize_history_table',21);
INSERT INTO `migrations` VALUES (492,'2024_07_31_192915_create_w2_payroll_tax_deductions_table',21);
INSERT INTO `migrations` VALUES (493,'2024_08_05_001902_add_column_in_recon_override_history_table',21);
INSERT INTO `migrations` VALUES (494,'2024_08_05_002232_create_recon_override_history_locks_table',21);
INSERT INTO `migrations` VALUES (495,'2024_08_14_050836_add_override_id_in_recon_override_history_table',21);
INSERT INTO `migrations` VALUES (496,'2024_08_14_152158_add_column_is_flexible_to_user_schedule_details',21);
INSERT INTO `migrations` VALUES (497,'2024_08_22_065500_create_recon_commission_histories_table',21);
INSERT INTO `migrations` VALUES (498,'2024_08_22_071000_create_recon_commission_history_locks_table',21);
INSERT INTO `migrations` VALUES (499,'2024_08_22_095257_add_cloumn_finalize_count_in_recon_override_history_table',21);
INSERT INTO `migrations` VALUES (500,'2024_08_22_105519_add_w2_everee_location_id_to_locations_table',21);
INSERT INTO `migrations` VALUES (501,'2024_08_25_020234_add_ref_id_column_in_recon_history_tables',21);
INSERT INTO `migrations` VALUES (502,'2024_08_26_104742_add_effective_date_to_override_status_table',21);
INSERT INTO `migrations` VALUES (503,'2024_08_27_064646_add_enable_for_w2_to_crms_table',21);
INSERT INTO `migrations` VALUES (504,'2024_08_27_071057_drop_enable_for_w2_to_crms_table',21);
INSERT INTO `migrations` VALUES (505,'2024_08_27_130101_change_id_type_from_recon_locks_tables',21);
INSERT INTO `migrations` VALUES (506,'2024_08_28_110521_add_updated_by_to_override_status_table',21);
INSERT INTO `migrations` VALUES (507,'2024_08_29_050312_add_column_in_recon_commission_history_table',21);
INSERT INTO `migrations` VALUES (508,'2024_08_29_130023_add_column_in_recon_override_history_table',21);
INSERT INTO `migrations` VALUES (509,'2024_08_29_155923_create_milestone_schemas',21);
INSERT INTO `migrations` VALUES (510,'2024_08_29_160038_milestone_schema_trigger',21);
INSERT INTO `migrations` VALUES (511,'2024_08_29_160052_products',21);
INSERT INTO `migrations` VALUES (512,'2024_08_29_160152_milestone_product_audiotlogs',21);
INSERT INTO `migrations` VALUES (513,'2024_08_30_003946_create_recon_clawback_histories_table',21);
INSERT INTO `migrations` VALUES (514,'2024_08_30_004136_create_recon_clawback_history_locks_table',21);
INSERT INTO `migrations` VALUES (515,'2024_08_30_054040_add_is_selfgen_to_positions_table',21);
INSERT INTO `migrations` VALUES (516,'2024_08_30_054141_create_position_products_table',21);
INSERT INTO `migrations` VALUES (517,'2024_08_30_054249_add_product_id_to_position_commissions_table',21);
INSERT INTO `migrations` VALUES (518,'2024_08_30_054304_add_product_id_to_position_commission_upfronts_table',21);
INSERT INTO `migrations` VALUES (519,'2024_08_30_054325_add_product_id_to_position_commission_overrides_table',21);
INSERT INTO `migrations` VALUES (520,'2024_08_30_054349_add_product_id_to_position_reconciliations_table',21);
INSERT INTO `migrations` VALUES (521,'2024_08_30_054413_add_product_id_to_onboarding_user_redlines_table',21);
INSERT INTO `migrations` VALUES (522,'2024_08_30_054513_create_onboarding_employee_upfronts_table',21);
INSERT INTO `migrations` VALUES (523,'2024_08_30_054534_add_product_id_to_onboarding_employee_override_table',21);
INSERT INTO `migrations` VALUES (524,'2024_08_30_062022_add_product_id_to_user_commission_history_table',21);
INSERT INTO `migrations` VALUES (525,'2024_08_30_062036_add_product_id_to_user_upfront_history_table',21);
INSERT INTO `migrations` VALUES (526,'2024_08_30_062048_add_product_id_to_user_withheld_history_table',21);
INSERT INTO `migrations` VALUES (527,'2024_08_30_062102_add_product_id_to_user_redline_histories_table',21);
INSERT INTO `migrations` VALUES (528,'2024_08_30_062244_add_product_id_to_user_override_history_table',21);
INSERT INTO `migrations` VALUES (529,'2024_08_30_085357_drop_user_wages_histories_table',21);
INSERT INTO `migrations` VALUES (530,'2024_08_30_104011_add_field__milestone_product_audiotlogs',21);
INSERT INTO `migrations` VALUES (531,'2024_08_30_150028_create_product_milestone_histories_table',21);
INSERT INTO `migrations` VALUES (532,'2024_08_30_152423_add_deleted_at_to_products_table',21);
INSERT INTO `migrations` VALUES (533,'2024_08_30_231408_add_columns_to_products_name',21);
INSERT INTO `migrations` VALUES (534,'2024_09_01_052946_create_recon_adjustments_table',21);
INSERT INTO `migrations` VALUES (535,'2024_09_01_055716_create_recon_adjustment_locks_table',21);
INSERT INTO `migrations` VALUES (536,'2024_09_02_070225_worker_id',21);
INSERT INTO `migrations` VALUES (537,'2024_09_02_224250_can_act_as_both_setter_and_closer',21);
INSERT INTO `migrations` VALUES (538,'2024_09_03_114027_create_recon_deduction_locks_table',21);
INSERT INTO `migrations` VALUES (539,'2024_09_03_141141_create_recon_deduction_histories_table',21);
INSERT INTO `migrations` VALUES (540,'2024_09_03_141253_create_recon_deduction_history_locks_table',21);
INSERT INTO `migrations` VALUES (541,'2024_09_04_074708_add_core_position_id_in_position_commissions--table=position_commissions',21);
INSERT INTO `migrations` VALUES (542,'2024_09_04_075425_add_core_position_id_in_position_commission_upfronts--table=position_commission_upfronts',21);
INSERT INTO `migrations` VALUES (543,'2024_09_04_075650_add_core_position_id_in_position_commission_overrides--table=position_commission_overrides',21);
INSERT INTO `migrations` VALUES (544,'2024_09_04_075851_add_core_position_id_in_position_reconciliations--table=position_reconciliations',21);
INSERT INTO `migrations` VALUES (545,'2024_09_05_045124_add_deduct_any_available_reconciliation_upfront_to_company_profiles_table',21);
INSERT INTO `migrations` VALUES (546,'2024_09_05_072801_change_column_payroll_execute_status_type_recon_table',21);
INSERT INTO `migrations` VALUES (547,'2024_09_05_101613_add_column_recon_clawback_history_tables',21);
INSERT INTO `migrations` VALUES (548,'2024_09_05_153548_add_hourly_format_to_users_table',21);
INSERT INTO `migrations` VALUES (549,'2024_09_09_140115_add_core_position_id_to_onboarding_employee_upfronts_table',21);
INSERT INTO `migrations` VALUES (550,'2024_09_09_140433_add_core_position_id_to_onboarding_user_redlines_table',21);
INSERT INTO `migrations` VALUES (551,'2024_09_09_154407_add_hiring_signature_to_onboarding_employees_table',21);
INSERT INTO `migrations` VALUES (552,'2024_09_11_111502_add_core_position_id_to_user_commission_history_table',21);
INSERT INTO `migrations` VALUES (553,'2024_09_11_111623_add_core_position_id_to_user_redline_histories_table',21);
INSERT INTO `migrations` VALUES (554,'2024_09_11_111716_add_core_position_id_to_user_upfront_history_table',21);
INSERT INTO `migrations` VALUES (555,'2024_09_11_192232_add_core_position_id_to_user_withheld_history_table',21);
INSERT INTO `migrations` VALUES (556,'2024_09_13_005134_add_column_in_recon_finalize_history_table',21);
INSERT INTO `migrations` VALUES (557,'2024_09_13_160452_create_user_agreement_histories_table',21);
INSERT INTO `migrations` VALUES (558,'2024_09_16_023724_create_additional_custom_fields_table',21);
INSERT INTO `migrations` VALUES (559,'2024_09_16_065409_add_is_ineligible_to_recon_commission_histories_table',21);
INSERT INTO `migrations` VALUES (560,'2024_09_16_175634_add_change_fields_to_products_table',21);
INSERT INTO `migrations` VALUES (561,'2024_09_17_010859_add_column_to_leads_table',21);
INSERT INTO `migrations` VALUES (562,'2024_09_17_042131_add_is_ineligible_to_recon_override_history_table',21);
INSERT INTO `migrations` VALUES (563,'2024_09_17_055334_add_column_in_recon_override_historys_table',21);
INSERT INTO `migrations` VALUES (564,'2024_09_17_135730_add_is_ineligible_to_recon_commission_history_locks_table',21);
INSERT INTO `migrations` VALUES (565,'2024_09_17_135746_add_is_ineligible_to_recon_override_history_locks_table',21);
INSERT INTO `migrations` VALUES (566,'2024_09_19_053825_add__fields_to_sale_masters_table',21);
INSERT INTO `migrations` VALUES (567,'2024_09_19_054121_add_fields_to_legacy_api_data_null_table',21);
INSERT INTO `migrations` VALUES (568,'2024_09_19_054215_add_fields_to_legacy_api_raw_data_table',21);
INSERT INTO `migrations` VALUES (569,'2024_09_20_051739_add_applied_for_user_to_position_',21);
INSERT INTO `migrations` VALUES (570,'2024_09_25_122843_add_enum_percent_to_position_reconciliations_table',21);
INSERT INTO `migrations` VALUES (571,'2024_09_26_013136_add_enum_percent_to_onboarding_employees_table',21);
INSERT INTO `migrations` VALUES (572,'2024_09_26_015714_add_enum_percent_to_user_withheld_history_table',21);
INSERT INTO `migrations` VALUES (573,'2024_09_26_061146_add_settlement_type_to_user_commission_table',21);
INSERT INTO `migrations` VALUES (574,'2024_09_26_061205_add_settlement_type_to_user_commission_lock_table',21);
INSERT INTO `migrations` VALUES (575,'2024_09_27_051434_add_column_is_worker_absent_to_user_schedule_details_table',21);
INSERT INTO `migrations` VALUES (576,'2024_09_27_060112_add_enum_percent_to_onboarding_user_redlines_table',21);
INSERT INTO `migrations` VALUES (577,'2024_10_04_032307_add_recon_status_to_user_commission_table',21);
INSERT INTO `migrations` VALUES (578,'2024_10_04_032313_add_recon_status_to_user_commission_lock_table',21);
INSERT INTO `migrations` VALUES (579,'2024_10_04_032320_add_recon_status_to_user_overrides_table',21);
INSERT INTO `migrations` VALUES (580,'2024_10_04_032325_add_recon_status_to_user_overrides_lock_table',21);
INSERT INTO `migrations` VALUES (581,'2024_10_04_032330_add_recon_status_to_clawback_settlements_table',21);
INSERT INTO `migrations` VALUES (582,'2024_10_04_032335_add_recon_status_to_clawback_settlements_lock_table',21);
INSERT INTO `migrations` VALUES (583,'2024_10_07_235222_create_onboarding_employee_redlines_table',21);
INSERT INTO `migrations` VALUES (584,'2024_10_08_000451_create_onboarding_employee_withhelds_table',21);
INSERT INTO `migrations` VALUES (585,'2024_10_08_010830_add_product_id_to_onboarding_employee_locations_table',21);
INSERT INTO `migrations` VALUES (586,'2024_10_08_235204_add_product_id_to_user_additional_office_override_histories',21);
INSERT INTO `migrations` VALUES (587,'2024_10_10_070935_create_onboarding_employee_additional_overrides_table',21);
INSERT INTO `migrations` VALUES (588,'2024_10_14_081058_add_product_id_to_user_organization_history_table',21);
INSERT INTO `migrations` VALUES (589,'2024_10_15_050818_add_deleted_at_to_position_products_table',21);
INSERT INTO `migrations` VALUES (590,'2024_10_17_142915_add_ref_id_to_custom_field_table',21);
INSERT INTO `migrations` VALUES (591,'2024_10_22_043419_add_group_column_to_milestone_product_audiotlogs_table',21);
INSERT INTO `migrations` VALUES (592,'2024_10_23_012945_change_commission_type_column_to_position_commissions_table',21);
INSERT INTO `migrations` VALUES (593,'2024_10_23_012958_change_commission_type_column_to_user_commission_history_table',21);
INSERT INTO `migrations` VALUES (594,'2024_10_23_013011_change_commission_type_column_to_onboarding_user_redlines_table',21);
INSERT INTO `migrations` VALUES (595,'2024_10_23_013031_change_commission_type_column_to_users_table',21);
INSERT INTO `migrations` VALUES (596,'2024_10_23_015458_change_commission_type_column_to_onboarding_employees_table',21);
INSERT INTO `migrations` VALUES (597,'2024_10_23_015634_change_commission_type_column_to_user_self_gen_commmission_histories_table',21);
INSERT INTO `migrations` VALUES (598,'2024_10_23_115847_change_milestone_schema_id_column_in_product_milestone_histories_table',21);
INSERT INTO `migrations` VALUES (599,'2024_10_23_115910_change_milestone_schema_id_column_in_products_table',21);
INSERT INTO `migrations` VALUES (600,'2024_10_25_025949_update_is_selfgen_in_positions_table',21);
INSERT INTO `migrations` VALUES (601,'2024_10_28_053100_add_product_redline_column_to_product_milestone_histories_table',21);
INSERT INTO `migrations` VALUES (602,'2024_10_28_055133_remove_position_id_foraign_key_to_user_deduction_table',21);
INSERT INTO `migrations` VALUES (603,'2024_11_06_032155_add_product_code_to_sale_masters_table',21);
INSERT INTO `migrations` VALUES (604,'2024_11_06_032657_create_sale_product_master_table',21);
INSERT INTO `migrations` VALUES (605,'2024_11_07_034047_add_is_last_to_user_commission_table',21);
INSERT INTO `migrations` VALUES (606,'2024_11_07_034112_add_is_last_to_user_commission_lock_table',21);
INSERT INTO `migrations` VALUES (607,'2024_11_08_094754_add_is_last_to_recon_commission_histories_table',21);
INSERT INTO `migrations` VALUES (608,'2024_11_08_094809_add_is_last_to_recon_commission_history_locks_table',21);
INSERT INTO `migrations` VALUES (609,'2024_11_11_041212_create_tier_systems_table',21);
INSERT INTO `migrations` VALUES (610,'2024_11_11_043249_create_tier_metrics_table',21);
INSERT INTO `migrations` VALUES (611,'2024_11_11_050518_create_tier_duration_table',21);
INSERT INTO `migrations` VALUES (612,'2024_11_11_060458_create_tier_table',21);
INSERT INTO `migrations` VALUES (613,'2024_11_11_061429_create_tier_levels_table',21);
INSERT INTO `migrations` VALUES (614,'2024_11_11_073857_change_redline_type_to_user_commission_table',21);
INSERT INTO `migrations` VALUES (615,'2024_11_11_073934_change_redline_type_to_user_commission_lock_table',21);
INSERT INTO `migrations` VALUES (616,'2024_11_12_045834_add_calculated_redline_type_to_user_overrides_table',21);
INSERT INTO `migrations` VALUES (617,'2024_11_12_045849_add_calculated_redline_type_to_user_overrides_lock_table',21);
INSERT INTO `migrations` VALUES (618,'2024_11_12_142810_create_sales_offices_table',21);
INSERT INTO `migrations` VALUES (619,'2024_11_12_150248_create_user_sales_offices_table',21);
INSERT INTO `migrations` VALUES (620,'2024_11_12_151754_create_user_sales_office_histories_table',21);
INSERT INTO `migrations` VALUES (621,'2024_11_12_211905_add_state_id_to_user_sales_offices_table',21);
INSERT INTO `migrations` VALUES (622,'2024_11_12_232008_add_deleted_at_to_tiers_schema_table',21);
INSERT INTO `migrations` VALUES (623,'2024_11_14_062253_add_tiers_id_to_onboarding_user_redlines_table',21);
INSERT INTO `migrations` VALUES (624,'2024_11_14_062912_create_onboarding_commission_tiers_level_range',21);
INSERT INTO `migrations` VALUES (625,'2024_11_14_063712_create_tiers_position_commisions_table',21);
INSERT INTO `migrations` VALUES (626,'2024_11_14_063746_create_tiers_position_upfronts_table',21);
INSERT INTO `migrations` VALUES (627,'2024_11_14_091629_create_tiers_position_overrides_table',21);
INSERT INTO `migrations` VALUES (628,'2024_11_14_094347_add_type_to_sale_product_master_table',21);
INSERT INTO `migrations` VALUES (629,'2024_11_14_123205_add_schema_type_to_clawback_settlements_table',21);
INSERT INTO `migrations` VALUES (630,'2024_11_14_123220_add_schema_type_to_clawback_settlements_lock_table',21);
INSERT INTO `migrations` VALUES (631,'2024_11_14_214109_add_user_id_to_onboarding_commission_tiers_level_range_table',21);
INSERT INTO `migrations` VALUES (632,'2024_11_14_220856_create_onboarding_employee_upfronts_tiers_range_table',21);
INSERT INTO `migrations` VALUES (633,'2024_11_14_221904_add_tiers_id_to_onboarding_employee_upfronts_table',21);
INSERT INTO `migrations` VALUES (634,'2024_11_14_233520_create_onboarding_employee_direct_override_tiers_range_table',21);
INSERT INTO `migrations` VALUES (635,'2024_11_14_234715_create_onboarding_employee_indirect_override_tiers_range_table',21);
INSERT INTO `migrations` VALUES (636,'2024_11_14_234815_create_onboarding_employee_office_override_tiers_range_table',21);
INSERT INTO `migrations` VALUES (637,'2024_11_14_235014_add_tiers_id_to_onboarding_employee_override_table',21);
INSERT INTO `migrations` VALUES (638,'2024_11_14_235200_add_tiers_id_to_onboarding_employee_additional_overrides_table',21);
INSERT INTO `migrations` VALUES (639,'2024_11_15_024046_change_redline_type_to_user_commission_table',21);
INSERT INTO `migrations` VALUES (640,'2024_11_15_024111_change_redline_type_to_user_commission_lock_table',21);
INSERT INTO `migrations` VALUES (641,'2024_11_15_025154_add_tiers_id_to_user_upfront_history_table',21);
INSERT INTO `migrations` VALUES (642,'2024_11_15_025225_add_tiers_id_to_user_override_history_table',21);
INSERT INTO `migrations` VALUES (643,'2024_11_15_025259_add_tiers_id_to_user_additional_office_override_histories_table',21);
INSERT INTO `migrations` VALUES (644,'2024_11_15_030811_create_user_commission_history_tiers_ranges_table',21);
INSERT INTO `migrations` VALUES (645,'2024_11_15_030919_create_user_upfront_history_tiers_ranges_table',21);
INSERT INTO `migrations` VALUES (646,'2024_11_15_031008_create_user_direct_override_history_tiers_ranges_table',21);
INSERT INTO `migrations` VALUES (647,'2024_11_15_031023_create_user_indirect_override_history_tiers_ranges_table',21);
INSERT INTO `migrations` VALUES (648,'2024_11_15_031418_create_user_additional_office_override_history_tiers_ranges_table',21);
INSERT INTO `migrations` VALUES (649,'2024_11_15_033533_add_tiers_id_to__user_commission_history_table',21);
INSERT INTO `migrations` VALUES (650,'2024_11_15_041500_add_tiers_id_to_position_commissions_table',21);
INSERT INTO `migrations` VALUES (651,'2024_11_15_042815_add_tiers_levels_id_to_tiers_position_commisions_table',21);
INSERT INTO `migrations` VALUES (652,'2024_11_15_053740_add_tiers_id_to_tiers_position_commission_upfronts_table',21);
INSERT INTO `migrations` VALUES (653,'2024_11_15_054510_add_tiers_levels_id_to_tiers_position_upfronts_table',21);
INSERT INTO `migrations` VALUES (654,'2024_11_15_091111_add_is_projected_to_sale_product_master_table',21);
INSERT INTO `migrations` VALUES (655,'2024_11_15_103335_add_tiers_id_to_position_commission_overrides_table',21);
INSERT INTO `migrations` VALUES (656,'2024_11_15_103823_add_tiers_levels_id_to_tiers_position_overrides_table',21);
INSERT INTO `migrations` VALUES (657,'2024_11_15_112114_add_total_commission_to_sale_masters_table',21);
INSERT INTO `migrations` VALUES (658,'2024_11_16_001437_add_is_last_to_projection_user_commissions_table',21);
INSERT INTO `migrations` VALUES (659,'2024_11_18_102436_add_trigger_date_to_legacy_api_raw_data_histories_table',21);
INSERT INTO `migrations` VALUES (660,'2024_11_19_020051_add_product_id_to_legacy_api_raw_data_histories_table',21);
INSERT INTO `migrations` VALUES (661,'2024_11_19_092657_add_is_custom_to_import_category_details_table',21);
INSERT INTO `migrations` VALUES (662,'2024_11_19_222226_update_commission_type_in_onboarding_user_redlines',21);
INSERT INTO `migrations` VALUES (663,'2024_11_19_222904_update_commission_type_in_user_commission_history',21);
INSERT INTO `migrations` VALUES (664,'2024_11_20_002648_add_lead_rating_status_to_additional_custom_fields_table',21);
INSERT INTO `migrations` VALUES (665,'2024_11_20_011501_create_custom_lead_form_global_settings_table',21);
INSERT INTO `migrations` VALUES (666,'2024_11_20_025134_add_lead_rating_to_leads_table',21);
INSERT INTO `migrations` VALUES (667,'2024_11_20_031504_create_onboarding_override_office_tiers_ranges_table',21);
INSERT INTO `migrations` VALUES (668,'2024_11_20_032646_add_tiers_id_to_onboarding_employee_override_table',21);
INSERT INTO `migrations` VALUES (669,'2024_11_20_033903_create_user_office_override_history_tiers_ranges_table',21);
INSERT INTO `migrations` VALUES (670,'2024_11_20_034203_add_tiers_id_to_user_override_history_table',21);
INSERT INTO `migrations` VALUES (671,'2024_11_20_220512_add_column_to_employee_id_setting_table',21);
INSERT INTO `migrations` VALUES (672,'2024_11_21_010711_add_sales_to_calculated_by_enum_in_position_commission_upfronts',21);
INSERT INTO `migrations` VALUES (673,'2024_11_21_011547_add_per_sale_to_commission_amount_type_enum_in_position_commissions',21);
INSERT INTO `migrations` VALUES (674,'2024_11_21_012932_modify_some_column_to_make_nullable_in_custom_field_history_table',21);
INSERT INTO `migrations` VALUES (675,'2024_11_21_063808_update_upfront_sale_type_in_user_upfront_history',21);
INSERT INTO `migrations` VALUES (676,'2024_11_21_093128_add_offer_review_uid_to_onboarding_employees_table',21);
INSERT INTO `migrations` VALUES (677,'2024_11_22_022040_add_status_to_excel_import_history_table',21);
INSERT INTO `migrations` VALUES (678,'2024_11_22_062801_create_sent_offer_letters_table',21);
INSERT INTO `migrations` VALUES (679,'2024_11_23_001209_change_gross_account_to_sale_masters_table',21);
INSERT INTO `migrations` VALUES (680,'2024_11_25_072507_add_new_column_to_onboarding_employees_table',21);
INSERT INTO `migrations` VALUES (681,'2024_11_26_011045_create_integrations_table',21);
INSERT INTO `migrations` VALUES (682,'2024_11_26_015521_create_schema_trigger_dates_table',21);
INSERT INTO `migrations` VALUES (683,'2024_11_26_032454_update_value_columns_in_tiers_tables',21);
INSERT INTO `migrations` VALUES (684,'2024_11_26_033748_add_special_approval_status_to_employee_id_setting_table',21);
INSERT INTO `migrations` VALUES (685,'2024_11_26_051431_add_schema_name_to_projection_user_commissions_table',21);
INSERT INTO `migrations` VALUES (686,'2024_11_26_052459_add_schema_trigger_to_user_commission_table',21);
INSERT INTO `migrations` VALUES (687,'2024_11_26_052514_add_schema_trigger_to_user_commission_lock_table',21);
INSERT INTO `migrations` VALUES (688,'2024_11_26_053724_add_schema_name_to_clawback_settlements_table',21);
INSERT INTO `migrations` VALUES (689,'2024_11_26_053743_add_schema_name_to_clawback_settlements_lock_table',21);
INSERT INTO `migrations` VALUES (690,'2024_11_26_090241_add_milestone_schema_id_to_user_commission_table',21);
INSERT INTO `migrations` VALUES (691,'2024_11_26_090358_add_schema_id_to_clawback_settlements_table',21);
INSERT INTO `migrations` VALUES (692,'2024_11_26_090424_add_schema_id_to_clawback_settlements_lock_table',21);
INSERT INTO `migrations` VALUES (693,'2024_11_26_090440_add_schema_id_to_user_commission_lock_table',21);
INSERT INTO `migrations` VALUES (694,'2024_11_26_093116_add_schema_id_to_projection_user_commissions_table',21);
INSERT INTO `migrations` VALUES (695,'2024_11_26_223212_update_tier_metrics_value_in_tiers_tables',21);
INSERT INTO `migrations` VALUES (696,'2024_11_27_224308_add_ratings_to_leads_table',21);
INSERT INTO `migrations` VALUES (697,'2024_11_28_022532_add_custom_fields_to_onboarding_employees_table',21);
INSERT INTO `migrations` VALUES (698,'2024_11_28_074003_create_leads_sub_tasks_table',21);
INSERT INTO `migrations` VALUES (699,'2024_11_29_011803_create_lead_documents_table',21);
INSERT INTO `migrations` VALUES (700,'2024_11_29_025137_create_pipeline_comments_table',21);
INSERT INTO `migrations` VALUES (701,'2024_11_29_071727_add_soft_deletes_to_pipeline_lead_status',21);
INSERT INTO `migrations` VALUES (702,'2024_12_02_105852_add_deleted_at_to_user_organization_history_table',21);
INSERT INTO `migrations` VALUES (703,'2024_12_02_111603_add_deleted_at_to_user_transfer_history_table',21);
INSERT INTO `migrations` VALUES (704,'2024_12_02_111634_add_deleted_at_to_user_is_manager_histories_table',21);
INSERT INTO `migrations` VALUES (705,'2024_12_02_111647_add_deleted_at_to_user_manager_histories_table',21);
INSERT INTO `migrations` VALUES (706,'2024_12_02_111700_add_deleted_at_to_user_wages_history_table',21);
INSERT INTO `migrations` VALUES (707,'2024_12_02_111710_add_deleted_at_to_user_redline_histories_table',21);
INSERT INTO `migrations` VALUES (708,'2024_12_02_111723_add_deleted_at_to_user_commission_history_table',21);
INSERT INTO `migrations` VALUES (709,'2024_12_02_111736_add_deleted_at_to_user_upfront_history_table',21);
INSERT INTO `migrations` VALUES (710,'2024_12_02_111748_add_deleted_at_to_user_override_history_table',21);
INSERT INTO `migrations` VALUES (711,'2024_12_02_111801_add_deleted_at_to_user_additional_office_override_histories_table',21);
INSERT INTO `migrations` VALUES (712,'2024_12_02_111815_add_deleted_at_to_user_withheld_history_table',21);
INSERT INTO `migrations` VALUES (713,'2024_12_02_111828_add_deleted_at_to_user_deduction_history_table',21);
INSERT INTO `migrations` VALUES (714,'2024_12_02_114837_create_automation_rules_table',21);
INSERT INTO `migrations` VALUES (715,'2024_12_03_025801_insert_hiring_policy_into_policies_tabs',21);
INSERT INTO `migrations` VALUES (716,'2024_12_03_031948_insert_permissions_for_lead_level_rating',21);
INSERT INTO `migrations` VALUES (717,'2024_12_03_041957_update_decimal_columns_in_leads_table',21);
INSERT INTO `migrations` VALUES (718,'2024_12_03_064845_insert_permissions_for_lead_rating',21);
INSERT INTO `migrations` VALUES (719,'2024_12_04_002549_create_pipeline_sub_task_complete_by_leads_table',21);
INSERT INTO `migrations` VALUES (720,'2024_12_04_082038_add_sub_task_id_to_pipeline_sub_task_complete_by_leads_table',21);
INSERT INTO `migrations` VALUES (721,'2024_12_04_190947_drop_status_column_from_pipeline_sub_tasks_table',21);
INSERT INTO `migrations` VALUES (722,'2024_12_05_001001_add_background_color_to_leads_table',21);
INSERT INTO `migrations` VALUES (723,'2024_12_05_022718_change_created_at_to_excel_import_history_table',21);
INSERT INTO `migrations` VALUES (724,'2024_12_05_064142_create_lead_user_prefereces_table',21);
INSERT INTO `migrations` VALUES (725,'2024_12_05_231756_add_override_on_ms_trigger_id_to_product_milestone_histories_table',21);
INSERT INTO `migrations` VALUES (726,'2024_12_05_235312_add_is_override_to_sale_product_master_table',21);
INSERT INTO `migrations` VALUES (727,'2024_12_06_072847_add_automation_permission',21);
INSERT INTO `migrations` VALUES (728,'2024_12_11_123316_create_automation_action_logs_table',21);
INSERT INTO `migrations` VALUES (729,'2024_12_12_043457_add_product_id_to_legacy_api_data_null_table',21);
INSERT INTO `migrations` VALUES (730,'2024_12_16_015044_add_product_id_to_manual_overrides_table',21);
INSERT INTO `migrations` VALUES (731,'2024_12_16_015122_add_product_id_to_manual_overrides_history_table',21);
INSERT INTO `migrations` VALUES (732,'2024_12_16_031856_add_product_id_to_override_status_table',21);
INSERT INTO `migrations` VALUES (733,'2024_12_17_010158_add_trace_log_to_automation_action_logs_table',21);
INSERT INTO `migrations` VALUES (734,'2024_12_07_062321_add_path_to_lead_comments_table',22);
INSERT INTO `migrations` VALUES (735,'2024_12_30_231028_automation_permissions_set',22);
INSERT INTO `migrations` VALUES (736,'2024_12_23_014703_add_effective_end_date_to_user_organization_history_table',23);
INSERT INTO `migrations` VALUES (737,'2024_12_23_014729_add_effective_end_date_to_user_transfer_history_table',23);
INSERT INTO `migrations` VALUES (738,'2024_12_23_014758_add_effective_end_date_to_additional_locations_table',23);
INSERT INTO `migrations` VALUES (739,'2024_12_23_014813_add_effective_end_date_to_user_is_manager_histories_table',23);
INSERT INTO `migrations` VALUES (740,'2024_12_23_014826_add_effective_end_date_to_user_manager_histories_table',23);
INSERT INTO `migrations` VALUES (741,'2024_12_23_014848_add_effective_end_date_to_user_wages_history_table',23);
INSERT INTO `migrations` VALUES (742,'2024_12_23_014901_add_effective_end_date_to_user_redline_histories_table',23);
INSERT INTO `migrations` VALUES (743,'2024_12_23_014913_add_effective_end_date_to_user_commission_history_table',23);
INSERT INTO `migrations` VALUES (744,'2024_12_23_014947_add_effective_end_date_to_user_upfront_history_table',23);
INSERT INTO `migrations` VALUES (745,'2024_12_23_015000_add_effective_end_date_to_user_override_history_table',23);
INSERT INTO `migrations` VALUES (746,'2024_12_23_015015_add_effective_end_date_to_user_additional_office_override_histories_table',23);
INSERT INTO `migrations` VALUES (747,'2024_12_23_015041_add_effective_end_date_to_user_withheld_history_table',23);
INSERT INTO `migrations` VALUES (748,'2024_12_23_015054_add_effective_end_date_to_user_deduction_history_table',23);
INSERT INTO `migrations` VALUES (749,'2025_01_06_013857_employment_package_permission_migration',23);
INSERT INTO `migrations` VALUES (750,'2024_11_20_012355_create_hubspot_transaction_logs_table',24);
INSERT INTO `migrations` VALUES (751,'2024_11_20_034855_alter_table_hubspot_transaction_logs_change_response_datatype',24);
INSERT INTO `migrations` VALUES (752,'2024_11_27_093116_create_integrations_table',24);
INSERT INTO `migrations` VALUES (753,'2024_12_02_063710_add_column_missing_keys_to_hubspot_transaction_logs_table',25);
INSERT INTO `migrations` VALUES (754,'2024_12_30_094819_add_effective_date_to_override_status_table',25);
INSERT INTO `migrations` VALUES (755,'2025_01_13_051034_add_financing_type_to_legacy_api_raw_data_histories_table',25);
INSERT INTO `migrations` VALUES (756,'2025_01_13_051108_add_financing_type_to_sale_masters_table',25);
INSERT INTO `migrations` VALUES (757,'2025_01_13_051123_add_financing_type_to_legacy_api_data_null_table',25);
INSERT INTO `migrations` VALUES (758,'2025_01_16_024141_remove_financing_type_column',25);
INSERT INTO `migrations` VALUES (759,'2024_12_26_092745_add_product_id_to_user_commission_table',26);
INSERT INTO `migrations` VALUES (760,'2024_12_26_093007_add_product_id_to_user_overrides_table',26);
INSERT INTO `migrations` VALUES (761,'2024_12_26_093255_add_product_id_to_clawback_settlements_table',26);
INSERT INTO `migrations` VALUES (762,'2025_01_17_051547_create_permission',26);
INSERT INTO `migrations` VALUES (763,'2025_01_20_064404_add_finalize_and_execute_payroll_permissions_to_permissions_table',26);
INSERT INTO `migrations` VALUES (764,'2025_01_21_005810_insert_tier_system_duration_metrics',26);
INSERT INTO `migrations` VALUES (765,'2025_01_21_045017_create_default_milestone_and_product_migration',26);
INSERT INTO `migrations` VALUES (766,'2025_01_21_050411_change_location_code_for_import_template',26);
INSERT INTO `migrations` VALUES (767,'2024_12_18_014124_create_fieldroute_transaction_log_table',27);
INSERT INTO `migrations` VALUES (768,'2025_01_10_032617_add_product_id_to_projection_user_commissions',27);
INSERT INTO `migrations` VALUES (769,'2025_01_16_101757_add_balance_age_to_legacy_api_raw_data_histories',27);
INSERT INTO `migrations` VALUES (770,'2025_01_16_102115_add_balance_age_to_legacy_api_raw_data',27);
INSERT INTO `migrations` VALUES (771,'2025_01_16_102431_add_balance_age_to_legacy_api_data_null',27);
INSERT INTO `migrations` VALUES (772,'2025_01_16_102742_add_balance_age_to_sale_masters',27);
INSERT INTO `migrations` VALUES (773,'2025_01_16_114432_add_initial_service_date_to_legacy_api_raw_data_histories',27);
INSERT INTO `migrations` VALUES (774,'2025_01_21_022829_add_customer_payment_json_to_legacy_api_raw_data_histories_table',27);
INSERT INTO `migrations` VALUES (775,'2025_01_21_025537_create_customer_payments_table',27);
INSERT INTO `migrations` VALUES (776,'2025_01_21_041720_add_description_to_integrations_table',27);
INSERT INTO `migrations` VALUES (777,'2025_01_22_090958_change_offer_include_bonus_to_user_agreement_histories_table',27);
INSERT INTO `migrations` VALUES (778,'2025_01_22_132339_create_salemaster_triggers',27);
INSERT INTO `migrations` VALUES (779,'2025_01_23_015458_change_location_code_to_customer_state_migration',27);
INSERT INTO `migrations` VALUES (780,'2025_01_28_063550_update_tables_for_primary_key_and_unique_constraints',27);
INSERT INTO `migrations` VALUES (781,'2025_01_28_215713_create_tax_document_checks',27);
INSERT INTO `migrations` VALUES (782,'2025_01_29_035055_change_import_category_data',27);
INSERT INTO `migrations` VALUES (783,'2025_01_31_025714_remove_upfron_limit_on_product_milestone',27);
INSERT INTO `migrations` VALUES (784,'2025_02_10_235100_alter_one_time_payments_table',27);
INSERT INTO `migrations` VALUES (785,'2025_02_25_023307_change_commission_type_to_onboarding_user_redlines',27);
INSERT INTO `migrations` VALUES (786,'2025_02_27_083038_change_data_for_sale_masters_table',27);
INSERT INTO `migrations` VALUES (787,'2024_11_29_114207_add_w2_closed_status_to_weekly_pay_frequencies_table',28);
INSERT INTO `migrations` VALUES (788,'2024_11_29_115017_add_w2_closed_status_to_monthly_pay_frequencies_table',28);
INSERT INTO `migrations` VALUES (789,'2024_11_29_115319_add_w2_closed_status_to_additional_pay_frequencies_table',28);
INSERT INTO `migrations` VALUES (790,'2024_12_03_064845_insert_permissions_for_worker_in_payroll',28);
INSERT INTO `migrations` VALUES (791,'2025_01_24_024943_insert_commission_overrides_adjustment_types',28);
INSERT INTO `migrations` VALUES (792,'2025_01_24_084919_add_finalize_id_recon_commission_histories',28);
INSERT INTO `migrations` VALUES (793,'2025_01_27_031254_add_finalize_id_to_reconciliation_finalize_history',28);
INSERT INTO `migrations` VALUES (794,'2025_01_27_032958_add_finalize_id_to_reconciliation_finalize_history_locks',28);
INSERT INTO `migrations` VALUES (795,'2025_01_27_033607_add_finalize_id_to_recon_adjustments',28);
INSERT INTO `migrations` VALUES (796,'2025_01_27_034015_add_finalize_id_to_recon_adjustment_locks',28);
INSERT INTO `migrations` VALUES (797,'2025_01_27_034547_add_finalize_id_to_recon_commission_history_locks',28);
INSERT INTO `migrations` VALUES (798,'2025_01_27_035321_add_finalize_id_to_recon_override_history',28);
INSERT INTO `migrations` VALUES (799,'2025_01_27_035742_add_finalize_id_to_recon_override_history_locks',28);
INSERT INTO `migrations` VALUES (800,'2025_01_27_040034_add_finalize_id_to_recon_clawback_histories',28);
INSERT INTO `migrations` VALUES (801,'2025_01_27_040442_add_finalize_id_to_recon_clawback_history_locks',28);
INSERT INTO `migrations` VALUES (802,'2025_01_27_040722_add_finalize_id_to_recon_deduction_histories',28);
INSERT INTO `migrations` VALUES (803,'2025_01_27_040906_add_is_onetime_payment_in_custom_field_tables',28);
INSERT INTO `migrations` VALUES (804,'2025_01_27_040906_add_is_onetime_payment_in_other_tables',28);
INSERT INTO `migrations` VALUES (805,'2025_01_27_040906_add_is_onetime_payment_in_reconciliation_tables',28);
INSERT INTO `migrations` VALUES (806,'2025_01_27_040906_add_is_onetime_payment_to_tables',28);
INSERT INTO `migrations` VALUES (807,'2025_01_27_041053_add_finalize_id_to_recon_deduction_history_locks',28);
INSERT INTO `migrations` VALUES (808,'2025_01_27_050103_create_reconciliation_finalize_table',28);
INSERT INTO `migrations` VALUES (809,'2025_01_27_050109_create_reconciliation_finalize_lock_table',28);
INSERT INTO `migrations` VALUES (810,'2025_01_30_033846_add_challenge_type_permission',28);
INSERT INTO `migrations` VALUES (811,'2025_02_12_033349_create_temp_payroll_finalize_execute_details_table',28);
INSERT INTO `migrations` VALUES (812,'2025_02_15_052313_change_payroll_adjustment_details_lock_table',28);
INSERT INTO `migrations` VALUES (813,'2025_02_19_012930_add_worker_type_to_payrolls_table',28);
INSERT INTO `migrations` VALUES (814,'2025_02_27_012448_add_is_upfront_to_reconciliation_finalize',28);
INSERT INTO `migrations` VALUES (815,'2025_02_27_012941_add_is_upfront_to_reconciliation_finalize_history',28);
INSERT INTO `migrations` VALUES (816,'2025_03_04_072747_create_sync_table_schema_procedure',29);
INSERT INTO `migrations` VALUES (817,'2025_03_05_084444_alter_table_custom_field_history_nullable',29);
INSERT INTO `migrations` VALUES (818,'2025_03_11_045831_add_w2_closed_status_to_daily_pay_frequencies',30);
INSERT INTO `migrations` VALUES (819,'2025_03_12_051322_update_tables_for_primary_key_and_unique_constraints_new',30);
INSERT INTO `migrations` VALUES (820,'2025_03_12_072027_add_trigger_for_billing',30);
INSERT INTO `migrations` VALUES (821,'2025_03_13_004954_recreate_get_payroll_data_view',30);
INSERT INTO `migrations` VALUES (822,'2025_03_13_015717_add_tax_column_to_w2_payroll_tax_deductions',30);
INSERT INTO `migrations` VALUES (823,'2025_03_22_024733_add_unique_pid_to_sale_masters_table',31);
INSERT INTO `migrations` VALUES (824,'2025_03_09_235502_add_columns_to_subscriptions_table',32);
INSERT INTO `migrations` VALUES (825,'2025_03_10_064949_add_last_login_at_to_users_table',32);
INSERT INTO `migrations` VALUES (826,'2025_03_12_040313_add_default_position_super_admin_position',32);
INSERT INTO `migrations` VALUES (827,'2025_03_25_045216_add_hiring_permission_tab',33);
INSERT INTO `migrations` VALUES (828,'2025_03_28_032815_create_migration_existing-superadmin-default-position',34);
INSERT INTO `migrations` VALUES (829,'2025_02_20_231436_create_turn_package_configurations_table',35);
INSERT INTO `migrations` VALUES (830,'2025_02_20_235738_add_package_configuration_ids_to_s_clearance_configurations_table',35);
INSERT INTO `migrations` VALUES (831,'2025_02_21_034328_create_s_clearance_turn_screening_request_list_table',35);
INSERT INTO `migrations` VALUES (832,'2025_02_25_232220_create_s_clearance_turn_responses_table',35);
INSERT INTO `migrations` VALUES (833,'2025_02_26_003624_create_sclearance_turn_statuses_table',35);
INSERT INTO `migrations` VALUES (834,'2025_03_12_235738_add_package_id_to_s_clearance_plans_table',35);
INSERT INTO `migrations` VALUES (835,'2025_03_18_015908_create_state_mvr_costs_table',35);
INSERT INTO `migrations` VALUES (836,'2025_03_21_013953_add_columns_to_commission_and_clawback_tables',35);
INSERT INTO `migrations` VALUES (837,'2025_03_24_121842_create_w2_user_transfer_histories_table',35);
INSERT INTO `migrations` VALUES (838,'2025_03_26_224413_add_softdelete_column_to_w2_user_transfer_histories',35);
INSERT INTO `migrations` VALUES (839,'2025_03_31_052628_create_processed_ticket_counts_table',36);
INSERT INTO `migrations` VALUES (840,'2025_04_02_092717_change_office_overrides_type_to_user_additional_office_override_histories_table',37);
INSERT INTO `migrations` VALUES (841,'2025_04_03_015545_change_redline_type_value_to_user_redline_histories_table',37);
INSERT INTO `migrations` VALUES (842,'2025_03_26_024206_create_billing_frequency_table',38);
INSERT INTO `migrations` VALUES (843,'2025_03_26_024733_add_frequency_type_id_to_company_profies_table',38);
INSERT INTO `migrations` VALUES (844,'2025_04_04_031753_create_migration_organization_history_position_existing_super_admin',38);
INSERT INTO `migrations` VALUES (845,'2025_04_05_051500_add_redline_to_clawback_settlements_table',39);
INSERT INTO `migrations` VALUES (846,'2025_04_07_020121_insert_default_crms_data',40);
INSERT INTO `migrations` VALUES (847,'2025_04_07_094006_alter_processed_ticket_counts_table',41);
INSERT INTO `migrations` VALUES (848,'2025_04_10_024733_add_color_code_to_schema_trigger_datesschema_trigger_dates_table',42);
INSERT INTO `migrations` VALUES (849,'2025_04_11_233824_update_old_data_color_to_schema_trigger_date_table',42);
INSERT INTO `migrations` VALUES (850,'2025_02_19_065922_update_columns_in_tiers_position_commissions_table',43);
INSERT INTO `migrations` VALUES (851,'2025_02_19_070317_create_position_tiers_table',43);
INSERT INTO `migrations` VALUES (852,'2025_02_19_070607_update_columns_in_tiers_levels_table',43);
INSERT INTO `migrations` VALUES (853,'2025_02_19_070726_add_start_end_day_table_tiers_schema',43);
INSERT INTO `migrations` VALUES (854,'2025_02_19_070820_rename_columns_table_tiers_levels',43);
INSERT INTO `migrations` VALUES (855,'2025_02_19_070915_update_tiers_position_upfronts_table',43);
INSERT INTO `migrations` VALUES (856,'2025_02_19_071113_update_position_commission_overrides_table',43);
INSERT INTO `migrations` VALUES (857,'2025_02_19_071508_update_onboarding_employee_direct_override_tiers_range_table',43);
INSERT INTO `migrations` VALUES (858,'2025_02_19_071737_update_onboarding_employee_indirect_override_tiers_range_table',43);
INSERT INTO `migrations` VALUES (859,'2025_02_19_071959_update_onboarding_override_office_tiers_ranges_table',43);
INSERT INTO `migrations` VALUES (860,'2025_02_19_072232_update_user_commission_history_tiers_ranges_table',43);
INSERT INTO `migrations` VALUES (861,'2025_02_19_072452_update_user_upfront_history_tiers_ranges_table',43);
INSERT INTO `migrations` VALUES (862,'2025_02_19_072732_update_onboarding_commission_tiers_level_range_table',43);
INSERT INTO `migrations` VALUES (863,'2025_02_19_072927_update_onboarding_employee_upfronts_tiers_range',43);
INSERT INTO `migrations` VALUES (864,'2025_02_19_073126_update_onboarding_employee_office_override_tiers_range_table',43);
INSERT INTO `migrations` VALUES (865,'2025_02_19_073300_update_user_direct_override_history_tiers_ranges_table',43);
INSERT INTO `migrations` VALUES (866,'2025_02_19_073445_update_user_indirect_override_history_tiers_ranges_table',43);
INSERT INTO `migrations` VALUES (867,'2025_02_19_073611_update_user_office_override_history_tiers_ranges_table',43);
INSERT INTO `migrations` VALUES (868,'2025_02_19_074041_update_position_reconciliations_table',43);
INSERT INTO `migrations` VALUES (869,'2025_02_19_074205_update_position_wages_table',43);
INSERT INTO `migrations` VALUES (870,'2025_02_19_074330_add_effective_date_in_position_commission_upfronts_table',43);
INSERT INTO `migrations` VALUES (871,'2025_02_19_074454_add_effective_date_in_position_commission_deductions_table',43);
INSERT INTO `migrations` VALUES (872,'2025_02_20_035722_add_symbol_to_tier_metrics_table',43);
INSERT INTO `migrations` VALUES (873,'2025_02_21_013138_change_from_value_to_tiers_levels_table',43);
INSERT INTO `migrations` VALUES (874,'2025_02_21_051329_add_effective_date_position_tiers_table',43);
INSERT INTO `migrations` VALUES (875,'2025_02_22_043033_create_sale_tiers_master_table',43);
INSERT INTO `migrations` VALUES (876,'2025_02_22_050116_users_current_tier_level',43);
INSERT INTO `migrations` VALUES (877,'2025_02_22_052952_create_users_tiers_histories_table',43);
INSERT INTO `migrations` VALUES (878,'2025_02_23_221959_update_tiers_advancement_in_ tiers_position_commisions_table',43);
INSERT INTO `migrations` VALUES (879,'2025_02_24_013257_drop_value_to_from_in_tiers_position_overrides_table',43);
INSERT INTO `migrations` VALUES (880,'2025_02_24_231635_add_effective_date_position_products_table',43);
INSERT INTO `migrations` VALUES (881,'2025_02_24_235818_add_effective_date_tiers_position_commisions_table',43);
INSERT INTO `migrations` VALUES (882,'2025_02_27_224036_remove_columns_from_tiers_position_upfronts',43);
INSERT INTO `migrations` VALUES (883,'2025_02_28_041945_create_tiers_worker_histories_table',43);
INSERT INTO `migrations` VALUES (884,'2025_03_03_011021_create_tiers_reset_histories_table',43);
INSERT INTO `migrations` VALUES (885,'2025_03_03_222403_create_sale_tiers_details_table',43);
INSERT INTO `migrations` VALUES (886,'2025_03_04_030840_add_column_user_additional_office_override_history_tiers_ranges_table',43);
INSERT INTO `migrations` VALUES (887,'2025_03_06_001649_add_is_locked_to_sale_tiers_details_table',43);
INSERT INTO `migrations` VALUES (888,'2025_03_12_045237_update_commission_limit_and_effective_date_position_commissions_table',43);
INSERT INTO `migrations` VALUES (889,'2025_03_12_051256_update_tiers_position_overrides_table',43);
INSERT INTO `migrations` VALUES (890,'2025_03_12_051958_add_effective_date_to_position_reconciliations_table',43);
INSERT INTO `migrations` VALUES (891,'2025_03_12_121854_add_next_reset_date_to_tiers_schema_table',43);
INSERT INTO `migrations` VALUES (892,'2025_03_24_013728_add_status_to_users_current_tier_level_table',43);
INSERT INTO `migrations` VALUES (893,'2025_03_28_052300_add_levels_to_tiers_schema_table',43);
INSERT INTO `migrations` VALUES (894,'2025_03_28_070440_add_current_level_to_users_current_tier_level_table',43);
INSERT INTO `migrations` VALUES (895,'2025_03_31_062717_add_schema_id_to_sale_tiers_details_table',43);
INSERT INTO `migrations` VALUES (896,'2025_03_31_235721_create_tierss_dropdown_data',43);
INSERT INTO `migrations` VALUES (897,'2025_04_02_051227_add_maxed_to_users_current_tier_level_table',43);
INSERT INTO `migrations` VALUES (898,'2025_04_03_062034_add_tiers_permission_to_permissions_table',43);
INSERT INTO `migrations` VALUES (899,'2025_04_04_002449_change_tiers_schema_id_toposition_tiers_table',43);
INSERT INTO `migrations` VALUES (900,'2025_04_05_060037_add_sale_product_name_to_sale_masters_table',43);
INSERT INTO `migrations` VALUES (901,'2025_04_11_055044_update_sequi_docs_email_settings_data',43);
INSERT INTO `migrations` VALUES (902,'2025_04_14_072255_change_tiers_option',43);
INSERT INTO `migrations` VALUES (903,'2025_04_15_070632_tiers_dropdown_migrations',43);
INSERT INTO `migrations` VALUES (904,'2025_04_15_072050_add_override_type_to_tiers_position_overrides_table',43);
INSERT INTO `migrations` VALUES (905,'2025_04_16_053148_add_customer_id_to_legacy_api_raw_data_histories_table',44);
INSERT INTO `migrations` VALUES (906,'2025_04_16_053148_add_initialstatustext_to_sale_masters_table',44);
INSERT INTO `migrations` VALUES (907,'2025_04_10_001742_create_user_department_histories_table',45);
INSERT INTO `migrations` VALUES (908,'2025_04_10_002411_migrate_user_transfer_history_to_user_department_histories',45);
INSERT INTO `migrations` VALUES (909,'2025_04_14_014741_users_business_address_table',46);
INSERT INTO `migrations` VALUES (910,'2025_02_25_051006_create_interigation_transaction_logs_table',47);
INSERT INTO `migrations` VALUES (911,'2025_04_24_082356_add_export_permissions_to_sales_tabs',48);
INSERT INTO `migrations` VALUES (912,'2025_04_28_100506_create_payment_alert_histories_table',49);
INSERT INTO `migrations` VALUES (913,'2025_05_01_162500_merge_fr_employee_data_tables',50);
INSERT INTO `migrations` VALUES (914,'2025_04_30_233824_update_last_milestone_to_final_date_schema_trigger_table',51);
INSERT INTO `migrations` VALUES (915,'2025_05_07_051545_change_adders_description_with_notes',51);
INSERT INTO `migrations` VALUES (916,'2025_05_15_090634_insert_email_configuration_if_not_exists',52);
INSERT INTO `migrations` VALUES (917,'2025_05_07_051628_add_deductible_from_prior_to_position_commission_upfronts_table',53);
INSERT INTO `migrations` VALUES (918,'2025_05_19_005033_update_status_in_email_configuration_table',53);
INSERT INTO `migrations` VALUES (919,'2025_03_24_022323_add_quickbooks_journal_entry_id_to_payroll_history_table',54);
INSERT INTO `migrations` VALUES (920,'2025_04_25_071540_add_quickbooks_journal_entry_id_to_one_time_payments_table',54);
INSERT INTO `migrations` VALUES (921,'2025_05_04_020705_add_onboarding_id_to_automation_action_logs',54);
INSERT INTO `migrations` VALUES (922,'2025_05_06_032943_add_old_status_id_to_onboarding_employees',54);
INSERT INTO `migrations` VALUES (923,'2025_05_17_012110_add_pid_index_to_sale_product_master_table',55);
INSERT INTO `migrations` VALUES (924,'2025_05_17_130523_create_kill_long_running_transactions_procedure',55);
INSERT INTO `migrations` VALUES (925,'2025_05_17_130750_create_check_long_running_transactions_event',1);
INSERT INTO `migrations` VALUES (926,'2025_05_17_130750_create_check_long_running_transactions_event',55);
INSERT INTO `migrations` VALUES (927,'2025_05_17_131700_create_transaction_kill_log_table',55);
INSERT INTO `migrations` VALUES (928,'2025_05_18_031416_update_kill_long_running_transactions_procedure',55);
INSERT INTO `migrations` VALUES (929,'2025_05_19_075806_create_legacy_api_raw_data_histories_log_table',56);
INSERT INTO `migrations` VALUES (930,'2025_05_21_004511_insert_quickbooks_into_crms_table',57);
INSERT INTO `migrations` VALUES (931,'2025_05_21_013633_alter_defaults_on_legacy_api_data_null_table',57);
INSERT INTO `migrations` VALUES (932,'2025_05_16_121923_create_job_progress_logs_table',58);
INSERT INTO `migrations` VALUES (933,'2025_05_16_172600_add_hidden_flag_to_job_progress_logs',58);
INSERT INTO `migrations` VALUES (934,'2025_03_27_010549_create_user_terminate_histories_table',59);
INSERT INTO `migrations` VALUES (935,'2025_03_27_020055_add_contract_ended_to_users_table',59);
INSERT INTO `migrations` VALUES (936,'2025_03_27_023331_create_user_dismiss_histories_table',59);
INSERT INTO `migrations` VALUES (937,'2025_03_28_052449_modify_users_table_email_and_mobile_no',59);
INSERT INTO `migrations` VALUES (938,'2025_03_31_074210_modify_onboarding_employees_removing_uniqueness_from_email_and_mobile_no',59);
INSERT INTO `migrations` VALUES (939,'2025_04_04_071424_add_column_rehire_to_users_table',59);
INSERT INTO `migrations` VALUES (940,'2025_04_04_082546_add_soft_deletes_to_user_agreement_histories',59);
INSERT INTO `migrations` VALUES (941,'2025_05_20_054718_create_product_codes_table',60);
