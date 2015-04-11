<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInitialTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $sqldump = <<<DUMP
-- MySQL dump 10.13  Distrib 5.5.40, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: orbit_shop
-- ------------------------------------------------------
-- Server version	5.5.40-0ubuntu0.14.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `{{PREFIX}}activities`
--

DROP TABLE IF EXISTS `{{PREFIX}}activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}activities` (
  `activity_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `activity_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `activity_name_long` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `activity_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `module_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `full_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `gender` char(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `group` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `role` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `role_id` int(10) unsigned DEFAULT NULL,
  `object_id` bigint(20) unsigned DEFAULT NULL,
  `object_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_id` bigint(20) DEFAULT NULL,
  `product_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `coupon_id` bigint(20) DEFAULT NULL,
  `coupon_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `promotion_id` bigint(20) DEFAULT NULL,
  `promotion_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `event_id` bigint(20) DEFAULT NULL,
  `event_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `location_id` bigint(20) unsigned DEFAULT NULL,
  `location_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `staff_id` bigint(20) unsigned DEFAULT NULL,
  `staff_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `metadata_user` text COLLATE utf8_unicode_ci,
  `metadata_object` text COLLATE utf8_unicode_ci,
  `metadata_location` text COLLATE utf8_unicode_ci,
  `metadata_staff` text COLLATE utf8_unicode_ci,
  `notes` text COLLATE utf8_unicode_ci,
  `http_method` varchar(8) COLLATE utf8_unicode_ci DEFAULT NULL,
  `request_uri` varchar(4912) COLLATE utf8_unicode_ci DEFAULT NULL,
  `post_data` text COLLATE utf8_unicode_ci,
  `status` varchar(15) COLLATE utf8_unicode_ci DEFAULT 'active',
  `parent_id` bigint(20) DEFAULT NULL,
  `response_status` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`activity_id`),
  KEY `activityid_idx` (`activity_id`),
  KEY `activity_name_idx` (`activity_name`),
  KEY `activity_type_idx` (`activity_type`),
  KEY `userid_idx` (`user_id`),
  KEY `user_email_idx` (`user_email`),
  KEY `group_idx` (`group`),
  KEY `role_idx` (`role`),
  KEY `roleid_idx` (`role_id`),
  KEY `objectid_idx` (`object_id`),
  KEY `locationid_idx` (`location_id`),
  KEY `ip_address_idx` (`ip_address`),
  KEY `user_agent_idx` (`user_agent`),
  KEY `staffid_idx` (`staff_id`),
  KEY `status_idx` (`status`),
  KEY `response_status_idx` (`response_status`),
  KEY `created_at_idx` (`created_at`),
  KEY `http_method_idx` (`http_method`),
  KEY `parentid_idx` (`parent_id`),
  KEY `full_name_idx` (`full_name`),
  KEY `productid_idx` (`product_id`),
  KEY `product_name_idx` (`product_name`),
  KEY `couponid_idx` (`coupon_id`),
  KEY `coupon_name_idx` (`coupon_name`),
  KEY `promotionid_idx` (`promotion_id`),
  KEY `promotion_name_idx` (`promotion_name`),
  KEY `eventid_idx` (`event_id`),
  KEY `event_name_idx` (`event_name`),
  KEY `module_name_idx` (`module_name`),
  KEY `staff_name_idx` (`staff_name`),
  KEY `gender_idx` (`gender`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}apikeys`
--

DROP TABLE IF EXISTS `{{PREFIX}}apikeys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}apikeys` (
  `apikey_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `api_key` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `api_secret_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `status` varchar(15) COLLATE utf8_unicode_ci DEFAULT 'active' COMMENT 'valid: active, blocked, deleted',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`apikey_id`),
  UNIQUE KEY `api_key_unique` (`api_key`),
  UNIQUE KEY `api_secret_key_unique` (`api_secret_key`),
  KEY `api_key_idx` (`api_key`),
  KEY `user_id_idx` (`user_id`),
  KEY `api_key_user_idx` (`api_key`,`user_id`),
  KEY `api_key_status_idx` (`api_key`,`status`),
  KEY `api_key_user_status_idx` (`api_key`,`user_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}cart_coupons`
--

DROP TABLE IF EXISTS `{{PREFIX}}cart_coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}cart_coupons` (
  `cart_coupon_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `issued_coupon_id` bigint(20) NOT NULL,
  `object_type` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `object_id` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`cart_coupon_id`),
  KEY `cart_couponid_idx` (`cart_coupon_id`),
  KEY `issued_couponid_idx` (`issued_coupon_id`),
  KEY `object_type_idx` (`object_type`),
  KEY `objectid_idx` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}cart_details`
--

DROP TABLE IF EXISTS `{{PREFIX}}cart_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}cart_details` (
  `cart_detail_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` bigint(20) unsigned DEFAULT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` int(10) unsigned DEFAULT NULL,
  `product_variant_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `status` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`cart_detail_id`),
  KEY `cart_detailid_idx` (`cart_detail_id`),
  KEY `cartid_idx` (`cart_id`),
  KEY `quantity_idx` (`quantity`),
  KEY `product_variantid_idx` (`product_variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}carts`
--

DROP TABLE IF EXISTS `{{PREFIX}}carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}carts` (
  `cart_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cart_code` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `merchant_id` bigint(20) unsigned DEFAULT NULL,
  `retailer_id` bigint(20) unsigned DEFAULT NULL,
  `total_item` int(10) unsigned DEFAULT NULL,
  `status` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `cashier_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`cart_id`),
  KEY `cartid_idx` (`cart_id`),
  KEY `cartcode_idx` (`cart_code`),
  KEY `customerid_idx` (`customer_id`),
  KEY `merchantid_idx` (`merchant_id`),
  KEY `retailerid_idx` (`retailer_id`),
  KEY `totalitem_idx` (`total_item`),
  KEY `status_idx` (`status`),
  KEY `moved_to_pos_idx` (`cashier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}categories`
--

DROP TABLE IF EXISTS `{{PREFIX}}categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}categories` (
  `category_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(10) unsigned NOT NULL,
  `category_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `category_level` int(10) unsigned DEFAULT NULL,
  `category_order` int(10) unsigned DEFAULT '0',
  `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `status` varchar(15) COLLATE utf8_unicode_ci DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `modified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`category_id`),
  KEY `category_order_idx` (`category_order`),
  KEY `status_idx` (`status`),
  KEY `modified_by_idx` (`modified_by`),
  KEY `created_at_idx` (`created_at`),
  KEY `updated_at_idx` (`updated_at`),
  KEY `category_status_idx` (`category_name`,`status`),
  KEY `category_name_order_idx` (`category_name`,`category_order`),
  KEY `category_name_order_status_idx` (`category_name`,`category_order`,`status`),
  KEY `category_order_status_idx` (`category_order`,`status`),
  KEY `merchant_id_idx` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}countries`
--

DROP TABLE IF EXISTS `{{PREFIX}}countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}countries` (
  `country_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(75) COLLATE utf8_unicode_ci NOT NULL,
  `code` char(2) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`country_id`),
  KEY `name_idx` (`name`),
  KEY `code_idx` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=240 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}custom_permission`
--

DROP TABLE IF EXISTS `{{PREFIX}}custom_permission`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}custom_permission` (
  `custom_permission_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  `allowed` varchar(3) COLLATE utf8_unicode_ci DEFAULT 'no' COMMENT 'valid: yes, no',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`custom_permission_id`),
  KEY `user_id_idx` (`user_id`),
  KEY `permission_id_idx` (`permission_id`),
  KEY `user_perm_idx` (`user_id`,`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}employee_retailer`
--

DROP TABLE IF EXISTS `{{PREFIX}}employee_retailer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}employee_retailer` (
  `employee_retailer_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `retailer_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`employee_retailer_id`),
  KEY `employeeid_idx` (`employee_id`),
  KEY `retaileridx_idx` (`retailer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}employees`
--

DROP TABLE IF EXISTS `{{PREFIX}}employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}employees` (
  `employee_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `employee_id_char` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `position` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `status` varchar(15) COLLATE utf8_unicode_ci DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`employee_id`),
  KEY `userid_idx` (`user_id`),
  KEY `employee_id_char_idx` (`employee_id_char`),
  KEY `position_idx` (`position`),
  KEY `status_idx` (`status`),
  KEY `created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}event_retailer`
--

DROP TABLE IF EXISTS `{{PREFIX}}event_retailer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}event_retailer` (
  `event_retailer_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `retailer_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`event_retailer_id`),
  KEY `event_id_idx` (`event_id`),
  KEY `retailer_id_idx` (`retailer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}events`
--

DROP TABLE IF EXISTS `{{PREFIX}}events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}events` (
  `event_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(10) unsigned NOT NULL,
  `event_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `event_type` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `begin_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `is_permanent` char(1) COLLATE utf8_unicode_ci DEFAULT 'N',
  `status` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `link_object_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `link_object_id1` bigint(20) unsigned DEFAULT NULL,
  `link_object_id2` bigint(20) unsigned DEFAULT NULL,
  `link_object_id3` bigint(20) unsigned DEFAULT NULL,
  `link_object_id4` bigint(20) unsigned DEFAULT NULL,
  `link_object_id5` bigint(20) unsigned DEFAULT NULL,
  `widget_object_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `modified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`event_id`),
  KEY `merchant_id_idx` (`merchant_id`),
  KEY `event_name_idx` (`event_name`),
  KEY `event_type_idx` (`event_type`),
  KEY `status_idx` (`status`),
  KEY `begindate_enddate_idx` (`begin_date`,`end_date`),
  KEY `link_object_type_idx` (`link_object_type`),
  KEY `link_object_id1_idx` (`link_object_id1`),
  KEY `link_object_id2_idx` (`link_object_id2`),
  KEY `link_object_id3_idx` (`link_object_id3`),
  KEY `link_object_id4_idx` (`link_object_id4`),
  KEY `link_object_id5_idx` (`link_object_id5`),
  KEY `created_by_idx` (`created_by`),
  KEY `modified_by_idx` (`modified_by`),
  KEY `created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}issued_coupons`
--

DROP TABLE IF EXISTS `{{PREFIX}}issued_coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}issued_coupons` (
  `issued_coupon_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `promotion_id` int(10) unsigned NOT NULL,
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `issued_coupon_code` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `expired_date` datetime DEFAULT NULL,
  `issued_date` datetime DEFAULT NULL,
  `redeemed_date` datetime DEFAULT NULL,
  `issuer_retailer_id` int(10) unsigned DEFAULT NULL,
  `status` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`issued_coupon_id`),
  KEY `promotion_id_idx` (`promotion_id`),
  KEY `issued_coupon_code_idx` (`issued_coupon_code`),
  KEY `status_idx` (`status`),
  KEY `user_id_idx` (`user_id`),
  KEY `issuer_retailer_id_idx` (`issuer_retailer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}media`
--

DROP TABLE IF EXISTS `{{PREFIX}}media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}media` (
  `media_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `media_name_id` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `media_name_long` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `object_id` bigint(20) unsigned DEFAULT NULL,
  `object_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `file_extension` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `mime_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `path` varchar(500) COLLATE utf8_unicode_ci DEFAULT NULL,
  `realpath` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `metadata` text COLLATE utf8_unicode_ci,
  `modified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`media_id`),
  KEY `media_nameid_idx` (`media_name_id`),
  KEY `media_name_long_idx` (`media_name_long`),
  KEY `objectid_idx` (`object_id`),
  KEY `objectid_name_idx` (`object_name`),
  KEY `file_name_idx` (`file_name`),
  KEY `file_extension_idx` (`file_extension`),
  KEY `file_size_idx` (`file_size`),
  KEY `path_idx` (`path`(255)),
  KEY `realpath_idx` (`realpath`(255)),
  KEY `modified_by_idx` (`modified_by`),
  KEY `objectid_object_name_idx` (`object_id`,`object_name`),
  KEY `objectid_object_name_media_name_idx` (`object_id`,`object_name`,`media_name_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}merchant_taxes`
--

DROP TABLE IF EXISTS `{{PREFIX}}merchant_taxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}merchant_taxes` (
  `merchant_tax_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(10) unsigned NOT NULL,
  `tax_name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `tax_type` varchar(15) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_value` decimal(5,4) NOT NULL DEFAULT '0.0000',
  `tax_order` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `status` varchar(15) COLLATE utf8_unicode_ci DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `modified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`merchant_tax_id`),
  KEY `merchant_id_idx` (`merchant_id`),
  KEY `tax_name_idx` (`tax_name`),
  KEY `merchantid_taxname_idx` (`merchant_id`,`tax_name`),
  KEY `status_idx` (`status`),
  KEY `merchantid_taxorder_idx` (`merchant_id`,`tax_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}merchants`
--

DROP TABLE IF EXISTS `{{PREFIX}}merchants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}merchants` (
  `merchant_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `omid` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `orid` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8_unicode_ci,
  `address_line1` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `address_line2` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `address_line3` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `postal_code` int(11) DEFAULT NULL,
  `city_id` int(10) unsigned DEFAULT NULL,
  `city` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `country_id` int(10) unsigned DEFAULT NULL,
  `country` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fax` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `start_date_activity` datetime DEFAULT NULL,
  `end_date_activity` datetime DEFAULT NULL,
  `status` varchar(15) COLLATE utf8_unicode_ci DEFAULT 'active',
  `logo` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `currency` char(3) COLLATE utf8_unicode_ci DEFAULT 'USD',
  `currency_symbol` char(3) COLLATE utf8_unicode_ci DEFAULT '$',
  `tax_code1` varchar(15) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_code2` varchar(15) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_code3` varchar(15) COLLATE utf8_unicode_ci DEFAULT NULL,
  `slogan` text COLLATE utf8_unicode_ci,
  `vat_included` char(3) COLLATE utf8_unicode_ci DEFAULT 'yes',
  `contact_person_firstname` varchar(75) COLLATE utf8_unicode_ci DEFAULT NULL,
  `contact_person_lastname` varchar(75) COLLATE utf8_unicode_ci DEFAULT NULL,
  `contact_person_position` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `contact_person_phone` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `contact_person_phone2` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `contact_person_email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `sector_of_activity` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `object_type` varchar(15) COLLATE utf8_unicode_ci DEFAULT 'merchant',
  `parent_id` int(10) unsigned DEFAULT NULL,
  `url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `masterbox_number` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `slavebox_number` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `mobile_default_language` varchar(15) COLLATE utf8_unicode_ci DEFAULT NULL,
  `pos_language` varchar(2) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ticket_header` text COLLATE utf8_unicode_ci,
  `ticket_footer` text COLLATE utf8_unicode_ci,
  `modified_by` bigint(20) unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`merchant_id`),
  KEY `merchantid_idx` (`merchant_id`),
  KEY `merchantid_status_idx` (`merchant_id`,`status`),
  KEY `merchantid_status_object_type_idx` (`merchant_id`,`object_type`,`status`),
  KEY `merchantid_email_idx` (`merchant_id`,`email`),
  KEY `merchantid_email_status_idx` (`merchant_id`,`email`,`status`),
  KEY `merchantid_email_status_object_type_idx` (`merchant_id`,`email`,`status`,`object_type`),
  KEY `merchant_userid_idx` (`user_id`),
  KEY `merchant_userid_status_idx` (`user_id`,`status`),
  KEY `merchant_userid_status_object_type_idx` (`user_id`,`status`,`object_type`),
  KEY `merchant_name_idx` (`name`),
  KEY `merchant_status_idx` (`status`),
  KEY `merchant_name_status_idx` (`name`,`status`),
  KEY `merchant_name_status_object_type_idx` (`name`,`status`,`object_type`),
  KEY `merchant_email_status_idx` (`email`,`status`),
  KEY `merchant_email_status_object_type_idx` (`email`,`status`,`object_type`),
  KEY `merchant_cityid_idx` (`city_id`),
  KEY `merchant_city_idx` (`city`),
  KEY `merchant_cityid_status_idx` (`city_id`,`status`),
  KEY `merchant_city_status_idx` (`city`,`status`),
  KEY `merchant_cityid_status_object_type_idx` (`city_id`,`status`,`object_type`),
  KEY `merchant_city_status_object_type_idx` (`city`,`status`,`object_type`),
  KEY `merchant_country_idx` (`country`),
  KEY `merchant_countryid_status_idx` (`country_id`,`status`),
  KEY `merchant_country_status_idx` (`country`,`status`),
  KEY `merchant_countryid_status_object_type_idx` (`country_id`,`status`,`object_type`),
  KEY `merchant_country_status_object_type_idx` (`country`,`status`,`object_type`),
  KEY `merchant_parentid_idx` (`parent_id`),
  KEY `merchant_parentid_status_idx` (`parent_id`,`status`),
  KEY `merchant_parentid_status_object_type_idx` (`parent_id`,`status`,`object_type`),
  KEY `merchant_start_date_activity_idx` (`start_date_activity`),
  KEY `merchant_vat_included_idx` (`vat_included`),
  KEY `modified_by_idx` (`modified_by`),
  KEY `merchant_sector_of_activity_idx` (`sector_of_activity`),
  KEY `merchant_end_date_activity_idx` (`end_date_activity`),
  KEY `omid_idx` (`omid`),
  KEY `orid_idx` (`orid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}permission_role`
--

DROP TABLE IF EXISTS `{{PREFIX}}permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}permission_role` (
  `permission_role_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  `allowed` varchar(3) COLLATE utf8_unicode_ci DEFAULT 'yes' COMMENT 'valid: yes, no',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`permission_role_id`),
  KEY `role_id_idx` (`role_id`),
  KEY `permission_id_idx` (`permission_id`),
  KEY `role_perm_idx` (`role_id`,`permission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=450 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}permissions`
--

DROP TABLE IF EXISTS `{{PREFIX}}permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}permissions` (
  `permission_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `permission_name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `permission_label` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `permission_group` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `permission_group_label` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `permission_name_order` int(10) unsigned NOT NULL,
  `permission_group_order` int(10) unsigned NOT NULL,
  `permission_default_value` varchar(3) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'no',
  `modified_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`permission_id`),
  KEY `permission_name_order_idx` (`permission_name_order`),
  KEY `permission_group_order_idx` (`permission_group_order`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}personal_interests`
--

DROP TABLE IF EXISTS `{{PREFIX}}personal_interests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}personal_interests` (
  `personal_interest_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `personal_interest_name` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `personal_interest_value` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `status` varchar(15) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `modified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`personal_interest_id`),
  KEY `user_personal_interest_name_idx` (`personal_interest_name`),
  KEY `user_personal_interest_value_idx` (`personal_interest_value`),
  KEY `status_idx` (`status`),
  KEY `created_by_idx` (`created_by`),
  KEY `modified_by_idx` (`modified_by`),
  KEY `interestid_status_idx` (`personal_interest_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}pos_quick_products`
--

DROP TABLE IF EXISTS `{{PREFIX}}pos_quick_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}pos_quick_products` (
  `pos_quick_product_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `merchant_id` bigint(20) unsigned NOT NULL,
  `retailer_id` bigint(20) unsigned DEFAULT NULL,
  `product_order` int(10) unsigned NOT NULL,
  `status` varchar(15) COLLATE utf8_unicode_ci DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`pos_quick_product_id`),
  KEY `productid_idx` (`product_id`),
  KEY `merchantid_idx` (`merchant_id`),
  KEY `product_order_idx` (`product_order`),
  KEY `status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}product_attribute_values`
--

DROP TABLE IF EXISTS `{{PREFIX}}product_attribute_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}product_attribute_values` (
  `product_attribute_value_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_attribute_id` int(11) unsigned NOT NULL,
  `value` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `value_order` tinyint(3) unsigned DEFAULT '0',
  `status` varchar(15) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `modified_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`product_attribute_value_id`),
  KEY `product_attributeid_idx` (`product_attribute_id`),
  KEY `value_idx` (`value`),
  KEY `product_attributeid_value_idx` (`product_attribute_id`,`value`),
  KEY `created_by_idx` (`created_by`),
  KEY `modified_by_idx` (`modified_by`),
  KEY `status_idx` (`status`),
  KEY `value_order_idx` (`value_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}product_attributes`
--

DROP TABLE IF EXISTS `{{PREFIX}}product_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}product_attributes` (
  `product_attribute_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_attribute_name` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `merchant_id` int(10) unsigned DEFAULT NULL,
  `status` varchar(15) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `modified_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`product_attribute_id`),
  KEY `product_attribute_name_idx` (`product_attribute_name`),
  KEY `created_by_idx` (`created_by`),
  KEY `modified_by_idx` (`modified_by`),
  KEY `merchantid_idx` (`merchant_id`),
  KEY `status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}product_retailer`
--

DROP TABLE IF EXISTS `{{PREFIX}}product_retailer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}product_retailer` (
  `product_retailer_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `retailer_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`product_retailer_id`),
  UNIQUE KEY `productid_retailerid_UNIQUE` (`product_id`,`retailer_id`),
  KEY `productid_idx` (`product_id`),
  KEY `retailerid_idx` (`retailer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}product_variants`
--

DROP TABLE IF EXISTS `{{PREFIX}}product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}product_variants` (
  `product_variant_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `price` decimal(14,2) DEFAULT NULL,
  `upc` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `sku` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `stock` int(10) unsigned DEFAULT NULL,
  `product_attribute_value_id1` int(10) unsigned DEFAULT NULL,
  `product_attribute_value_id2` int(10) unsigned DEFAULT NULL,
  `product_attribute_value_id3` int(10) unsigned DEFAULT NULL,
  `product_attribute_value_id4` int(10) unsigned DEFAULT NULL,
  `product_attribute_value_id5` int(10) unsigned DEFAULT NULL,
  `merchant_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(15) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'active',
  `retailer_id` bigint(20) unsigned DEFAULT NULL,
  `default_variant` char(3) COLLATE utf8_unicode_ci DEFAULT 'no',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `modified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`product_variant_id`),
  KEY `productid_idx` (`product_id`),
  KEY `price_idx` (`price`),
  KEY `upc_idx` (`upc`),
  KEY `sku_idx` (`sku`),
  KEY `product_attribute_value_id1_idx` (`product_attribute_value_id1`),
  KEY `product_attribute_value_id2_idx` (`product_attribute_value_id2`),
  KEY `product_attribute_value_id3_idx` (`product_attribute_value_id3`),
  KEY `product_attribute_value_id4_idx` (`product_attribute_value_id4`),
  KEY `product_attribute_value_id5_idx` (`product_attribute_value_id5`),
  KEY `merchantid_idx` (`merchant_id`),
  KEY `retailerid_idx` (`retailer_id`),
  KEY `created_by_idx` (`created_by`),
  KEY `modified_by_idx` (`modified_by`),
  KEY `product_variant_idx` (`product_variant_id`,`product_id`),
  KEY `stock_idx` (`stock`),
  KEY `status_idx` (`status`),
  KEY `default_variant_idx` (`default_variant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}products`
--

DROP TABLE IF EXISTS `{{PREFIX}}products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}products` (
  `product_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_code` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `upc_code` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `price` decimal(16,2) DEFAULT '0.00',
  `merchant_tax_id1` int(10) unsigned DEFAULT NULL,
  `merchant_tax_id2` int(10) unsigned DEFAULT NULL,
  `short_description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `long_description` text COLLATE utf8_unicode_ci,
  `is_featured` char(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `new_from` datetime DEFAULT NULL,
  `image` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `new_until` datetime DEFAULT NULL,
  `in_store_localization` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `post_sales_url` text COLLATE utf8_unicode_ci,
  `merchant_id` int(10) unsigned NOT NULL,
  `attribute_id1` int(10) unsigned DEFAULT NULL,
  `attribute_id2` int(10) unsigned DEFAULT NULL,
  `attribute_id3` int(10) unsigned DEFAULT NULL,
  `attribute_id4` int(10) unsigned DEFAULT NULL,
  `attribute_id5` int(10) unsigned DEFAULT NULL,
  `category_id1` int(10) unsigned DEFAULT NULL,
  `category_id2` int(10) unsigned DEFAULT NULL,
  `category_id3` int(10) unsigned DEFAULT NULL,
  `category_id4` int(10) unsigned DEFAULT NULL,
  `category_id5` int(10) unsigned DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `modified_by` bigint(20) DEFAULT NULL,
  `status` varchar(15) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`product_id`),
  KEY `product_code_idx` (`product_code`),
  KEY `product_name_idx` (`product_name`),
  KEY `price_idx` (`price`),
  KEY `new_until_idx` (`new_until`),
  KEY `merchantid_idx` (`merchant_id`),
  KEY `created_by_idx` (`created_by`),
  KEY `modified_by_idx` (`modified_by`),
  KEY `status_idx` (`status`),
  KEY `price_status_idx` (`price`,`status`),
  KEY `new_until_status_idx` (`new_until`,`status`),
  KEY `merchantid_status_idx` (`merchant_id`,`status`),
  KEY `price_merchantid_status_idx` (`price`,`merchant_id`,`status`),
  KEY `upc_code_idx` (`upc_code`),
  KEY `merchantid_isfeatured_idx` (`merchant_id`,`is_featured`),
  KEY `isfeatured_idx` (`is_featured`),
  KEY `category_id1_idx` (`category_id1`),
  KEY `category_id2_idx` (`category_id2`),
  KEY `category_id3_idx` (`category_id3`),
  KEY `category_id4_idx` (`category_id4`),
  KEY `category_id5_idx` (`category_id5`),
  KEY `attribute_id1_idx` (`attribute_id1`),
  KEY `attribute_id2_idx` (`attribute_id2`),
  KEY `attribute_id3_idx` (`attribute_id3`),
  KEY `attribute_id4_idx` (`attribute_id4`),
  KEY `attribute_id5_idx` (`attribute_id5`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}promotion_retailer`
--

DROP TABLE IF EXISTS `{{PREFIX}}promotion_retailer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}promotion_retailer` (
  `promotion_retailer_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `promotion_id` int(10) unsigned NOT NULL,
  `retailer_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`promotion_retailer_id`),
  KEY `promotion_id_idx` (`promotion_id`),
  KEY `retailer_id_idx` (`retailer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}promotion_retailer_redeem`
--

DROP TABLE IF EXISTS `{{PREFIX}}promotion_retailer_redeem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}promotion_retailer_redeem` (
  `promotion_retailer_redeem_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `promotion_id` int(10) unsigned NOT NULL,
  `retailer_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`promotion_retailer_redeem_id`),
  KEY `promotion_id_idx` (`promotion_id`),
  KEY `retailer_id_idx` (`retailer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}promotion_rules`
--

DROP TABLE IF EXISTS `{{PREFIX}}promotion_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}promotion_rules` (
  `promotion_rule_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `promotion_id` int(10) unsigned NOT NULL,
  `rule_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `rule_value` decimal(16,2) DEFAULT '0.00',
  `rule_object_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `rule_object_id1` bigint(20) unsigned DEFAULT NULL,
  `rule_object_id2` bigint(20) unsigned DEFAULT NULL,
  `rule_object_id3` bigint(20) unsigned DEFAULT NULL,
  `rule_object_id4` bigint(20) unsigned DEFAULT NULL,
  `rule_object_id5` bigint(20) unsigned DEFAULT NULL,
  `discount_object_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `discount_object_id1` bigint(20) unsigned DEFAULT NULL,
  `discount_object_id2` bigint(20) unsigned DEFAULT NULL,
  `discount_object_id3` bigint(20) unsigned DEFAULT NULL,
  `discount_object_id4` bigint(20) unsigned DEFAULT NULL,
  `discount_object_id5` bigint(20) unsigned DEFAULT NULL,
  `discount_value` decimal(16,4) DEFAULT '0.0000',
  `is_cumulative_with_coupons` char(1) COLLATE utf8_unicode_ci DEFAULT 'N',
  `is_cumulative_with_promotions` char(1) COLLATE utf8_unicode_ci DEFAULT 'N',
  `coupon_redeem_rule_value` decimal(16,2) DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`promotion_rule_id`),
  KEY `promotion_id_idx` (`promotion_id`),
  KEY `rule_type_idx` (`rule_type`),
  KEY `rule_object_id1_idx` (`rule_object_id1`),
  KEY `discount_object_id1_idx` (`discount_object_id1`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}promotions`
--

DROP TABLE IF EXISTS `{{PREFIX}}promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}promotions` (
  `promotion_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `merchant_id` int(10) unsigned NOT NULL,
  `promotion_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `promotion_type` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `begin_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `is_permanent` char(1) COLLATE utf8_unicode_ci DEFAULT 'N',
  `status` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_coupon` char(1) COLLATE utf8_unicode_ci DEFAULT 'N',
  `maximum_issued_coupon` int(11) DEFAULT NULL,
  `coupon_validity_in_days` int(11) DEFAULT NULL,
  `coupon_notification` char(1) COLLATE utf8_unicode_ci DEFAULT 'N',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `modified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`promotion_id`),
  KEY `merchant_id_idx` (`merchant_id`),
  KEY `promotion_name_idx` (`promotion_name`),
  KEY `promotion_type_idx` (`promotion_type`),
  KEY `status_idx` (`status`),
  KEY `begindate_enddate_idx` (`begin_date`,`end_date`),
  KEY `created_by_idx` (`created_by`),
  KEY `modified_by_idx` (`modified_by`),
  KEY `created_at_idx` (`created_at`),
  KEY `is_permanent_idx` (`is_permanent`),
  KEY `is_coupon_idx` (`is_coupon`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}roles`
--

DROP TABLE IF EXISTS `{{PREFIX}}roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}roles` (
  `role_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_name` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `role_order` int(10) unsigned NOT NULL DEFAULT '0',
  `modified_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`role_id`),
  KEY `role_order_idx` (`role_order`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}settings`
--

DROP TABLE IF EXISTS `{{PREFIX}}settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}settings` (
  `setting_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8_unicode_ci NOT NULL,
  `object_id` bigint(20) unsigned DEFAULT '0',
  `object_type` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `modified_by` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(15) COLLATE utf8_unicode_ci DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`setting_id`),
  KEY `setting_name_idx` (`setting_name`),
  KEY `objectid_idx` (`object_id`),
  KEY `object_type_idx` (`object_type`),
  KEY `status_idx` (`status`),
  KEY `modified_by_idx` (`modified_by`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}tokens`
--

DROP TABLE IF EXISTS `{{PREFIX}}tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}tokens` (
  `token_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `token_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `token_value` varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
  `status` varchar(15) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'active',
  `email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `expire` datetime DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `object_id` bigint(20) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` text COLLATE utf8_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}transaction_detail_coupons`
--

DROP TABLE IF EXISTS `{{PREFIX}}transaction_detail_coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}transaction_detail_coupons` (
  `transaction_detail_coupon_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_detail_id` bigint(20) unsigned DEFAULT NULL,
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `promotion_id` bigint(20) unsigned DEFAULT NULL,
  `promotion_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `promotion_type` varchar(15) COLLATE utf8_unicode_ci DEFAULT NULL,
  `rule_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `rule_value` decimal(16,2) DEFAULT NULL,
  `rule_object_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `category_id1` bigint(20) unsigned DEFAULT NULL,
  `category_id2` bigint(20) unsigned DEFAULT NULL,
  `category_id3` bigint(20) unsigned DEFAULT NULL,
  `category_id4` bigint(20) unsigned DEFAULT NULL,
  `category_id5` bigint(20) unsigned DEFAULT NULL,
  `category_name1` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `category_name2` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `category_name3` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `category_name4` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `category_name5` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `discount_object_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `discount_value` decimal(16,2) DEFAULT NULL,
  `value_after_percentage` decimal(16,2) DEFAULT NULL,
  `coupon_redeem_rule_value` decimal(16,2) DEFAULT NULL,
  `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `begin_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`transaction_detail_coupon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}transaction_detail_promotions`
--

DROP TABLE IF EXISTS `{{PREFIX}}transaction_detail_promotions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}transaction_detail_promotions` (
  `transaction_detail_promotion_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_detail_id` bigint(20) unsigned DEFAULT NULL,
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `promotion_id` bigint(20) unsigned DEFAULT NULL,
  `promotion_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `promotion_type` varchar(15) COLLATE utf8_unicode_ci DEFAULT NULL,
  `rule_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `rule_value` decimal(16,2) DEFAULT NULL,
  `discount_object_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `discount_value` decimal(16,2) DEFAULT NULL,
  `value_after_percentage` decimal(16,2) DEFAULT NULL,
  `description` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `begin_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`transaction_detail_promotion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}transaction_detail_taxes`
--

DROP TABLE IF EXISTS `{{PREFIX}}transaction_detail_taxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}transaction_detail_taxes` (
  `transaction_detail_tax_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `transaction_detail_id` bigint(20) unsigned DEFAULT NULL,
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `tax_id` int(10) unsigned DEFAULT NULL,
  `tax_name` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_value` decimal(16,4) DEFAULT NULL,
  `total_tax` decimal(16,4) DEFAULT NULL,
  `tax_order` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`transaction_detail_tax_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}transaction_details`
--

DROP TABLE IF EXISTS `{{PREFIX}}transaction_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}transaction_details` (
  `transaction_detail_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint(20) unsigned DEFAULT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `product_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `price` decimal(16,2) DEFAULT NULL,
  `product_code` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `upc` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `sku` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `quantity` int(10) unsigned DEFAULT NULL,
  `product_variant_id` bigint(20) unsigned DEFAULT NULL,
  `currency` char(3) COLLATE utf8_unicode_ci DEFAULT 'USD',
  `variant_price` decimal(14,2) DEFAULT NULL,
  `variant_upc` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `variant_sku` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `variant_stock` int(10) unsigned DEFAULT NULL,
  `product_attribute_value_id1` int(10) unsigned DEFAULT NULL,
  `product_attribute_value_id2` int(10) unsigned DEFAULT NULL,
  `product_attribute_value_id3` int(10) unsigned DEFAULT NULL,
  `product_attribute_value_id4` int(10) unsigned DEFAULT NULL,
  `product_attribute_value_id5` int(10) unsigned DEFAULT NULL,
  `product_attribute_value1` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_attribute_value2` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_attribute_value3` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_attribute_value4` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_attribute_value5` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `merchant_tax_id1` int(10) unsigned DEFAULT NULL,
  `merchant_tax_id2` int(10) unsigned DEFAULT NULL,
  `attribute_id1` int(10) unsigned DEFAULT NULL,
  `attribute_id2` int(10) unsigned DEFAULT NULL,
  `attribute_id3` int(10) unsigned DEFAULT NULL,
  `attribute_id4` int(10) unsigned DEFAULT NULL,
  `attribute_id5` int(10) unsigned DEFAULT NULL,
  `product_attribute_name1` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_attribute_name2` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_attribute_name3` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_attribute_name4` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_attribute_name5` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`transaction_detail_id`),
  KEY `transaction_detailid_idx` (`transaction_detail_id`),
  KEY `transactionid_idx` (`transaction_id`),
  KEY `price_idx` (`price`),
  KEY `productcode_idx` (`product_code`),
  KEY `upc_idx` (`upc`),
  KEY `sku_idx` (`sku`),
  KEY `quantity_idx` (`quantity`),
  KEY `product_variantid_idx` (`product_variant_id`),
  KEY `product_name_idx` (`product_name`),
  KEY `variant_price_idx` (`variant_price`),
  KEY `variant_upc_idx` (`variant_upc`),
  KEY `variant_sku_idx` (`variant_sku`),
  KEY `variant_stock_idx` (`variant_stock`),
  KEY `product_attribute_value_id1_idx` (`product_attribute_value_id1`),
  KEY `product_attribute_value_id2_idx` (`product_attribute_value_id2`),
  KEY `product_attribute_value_id3_idx` (`product_attribute_value_id3`),
  KEY `product_attribute_value_id4_idx` (`product_attribute_value_id4`),
  KEY `product_attribute_value_id5_idx` (`product_attribute_value_id5`),
  KEY `product_attribute_value1_idx` (`product_attribute_value1`),
  KEY `product_attribute_value2_idx` (`product_attribute_value2`),
  KEY `product_attribute_value3_idx` (`product_attribute_value3`),
  KEY `product_attribute_value4_idx` (`product_attribute_value4`),
  KEY `product_attribute_value5_idx` (`product_attribute_value5`),
  KEY `merchant_tax_id1_idx` (`merchant_tax_id1`),
  KEY `merchant_tax_id2_idx` (`merchant_tax_id2`),
  KEY `attribute_id1_idx` (`attribute_id1`),
  KEY `attribute_id2_idx` (`attribute_id2`),
  KEY `attribute_id3_idx` (`attribute_id3`),
  KEY `attribute_id4_idx` (`attribute_id4`),
  KEY `attribute_id5_idx` (`attribute_id5`),
  KEY `product_attribute_name1_idx` (`product_attribute_name1`),
  KEY `product_attribute_name2_idx` (`product_attribute_name2`),
  KEY `product_attribute_name3_idx` (`product_attribute_name3`),
  KEY `product_attribute_name4_idx` (`product_attribute_name4`),
  KEY `product_attribute_name5_idx` (`product_attribute_name5`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}transactions`
--

DROP TABLE IF EXISTS `{{PREFIX}}transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}transactions` (
  `transaction_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_code` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `cashier_id` bigint(20) unsigned DEFAULT NULL,
  `customer_id` bigint(20) unsigned DEFAULT NULL,
  `merchant_id` bigint(20) unsigned DEFAULT NULL,
  `retailer_id` bigint(20) unsigned DEFAULT NULL,
  `total_item` int(10) unsigned DEFAULT NULL,
  `subtotal` decimal(16,2) DEFAULT NULL,
  `vat` decimal(16,2) DEFAULT NULL,
  `currency` char(3) COLLATE utf8_unicode_ci DEFAULT 'USD',
  `currency_symbol` char(3) COLLATE utf8_unicode_ci DEFAULT '$',
  `total_to_pay` decimal(16,2) DEFAULT NULL,
  `tendered` decimal(16,2) DEFAULT NULL,
  `change` decimal(16,2) DEFAULT NULL,
  `payment_method` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `status` varchar(15) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`transaction_id`),
  KEY `transactionid_idx` (`transaction_id`),
  KEY `transactioncode_idx` (`transaction_code`),
  KEY `cashierid_idx` (`cashier_id`),
  KEY `customerid_idx` (`customer_id`),
  KEY `merchantid_idx` (`merchant_id`),
  KEY `retailerid_idx` (`retailer_id`),
  KEY `totalitem_idx` (`total_item`),
  KEY `subtotal_idx` (`subtotal`),
  KEY `vat_idx` (`vat`),
  KEY `totaltopay_idx` (`total_to_pay`),
  KEY `paymentmethod_idx` (`payment_method`),
  KEY `tendered_idx` (`tendered`),
  KEY `change_idx` (`change`),
  KEY `status_idx` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=111111 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}user_details`
--

DROP TABLE IF EXISTS `{{PREFIX}}user_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}user_details` (
  `user_detail_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `merchant_id` int(10) unsigned DEFAULT NULL,
  `merchant_acquired_date` datetime DEFAULT NULL,
  `retailer_id` int(10) unsigned DEFAULT NULL,
  `address_line1` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `address_line2` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `address_line3` varchar(2000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `postal_code` int(10) unsigned DEFAULT NULL,
  `city_id` int(10) unsigned DEFAULT NULL,
  `city` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `province_id` int(10) unsigned DEFAULT NULL,
  `province` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `country_id` int(10) unsigned DEFAULT NULL,
  `country` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `currency` char(3) COLLATE utf8_unicode_ci DEFAULT NULL,
  `currency_symbol` varchar(3) COLLATE utf8_unicode_ci DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` char(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `relationship_status` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `phone2` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `photo` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `preferred_language` char(2) COLLATE utf8_unicode_ci DEFAULT 'en',
  `occupation` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `sector_of_activity` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `company_name` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_education_degree` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `avg_annual_income1` decimal(16,2) DEFAULT '0.00',
  `avg_annual_income2` decimal(16,2) DEFAULT '0.00',
  `avg_monthly_spent1` decimal(16,2) DEFAULT NULL,
  `avg_monthly_spent2` decimal(16,2) DEFAULT NULL,
  `has_children` char(1) COLLATE utf8_unicode_ci DEFAULT NULL,
  `number_of_children` smallint(5) unsigned DEFAULT NULL,
  `car_model` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `car_year` mediumint(8) unsigned DEFAULT NULL,
  `number_visit_all_shop` int(10) unsigned DEFAULT '0',
  `amount_spent_all_shop` decimal(16,2) DEFAULT '0.00',
  `average_spent_per_month_all_shop` decimal(16,2) DEFAULT '0.00',
  `last_visit_any_shop` datetime DEFAULT NULL,
  `last_visit_shop_id` int(10) unsigned DEFAULT NULL,
  `last_purchase_any_shop` datetime DEFAULT NULL,
  `last_purchase_shop_id` int(10) unsigned DEFAULT NULL,
  `last_spent_any_shop` decimal(16,2) DEFAULT '0.00',
  `last_spent_shop_id` int(10) unsigned DEFAULT NULL,
  `modified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`user_detail_id`),
  KEY `user_idx` (`user_id`),
  KEY `merchant_id_idx` (`merchant_id`),
  KEY `userid_merchantid_idx` (`user_id`,`merchant_id`),
  KEY `merchant_acquired_date_idx` (`merchant_acquired_date`),
  KEY `userid_merchantid_acquired_idx` (`user_id`,`merchant_id`,`merchant_acquired_date`),
  KEY `userid_city_idx` (`user_id`,`city`),
  KEY `userid_cityid_idx` (`user_id`,`city_id`),
  KEY `userid_country_idx` (`user_id`,`country`),
  KEY `userid_countryid_idx` (`user_id`,`country_id`),
  KEY `city_id_idx` (`city_id`),
  KEY `city_idx` (`city`),
  KEY `country_id_idx` (`country_id`),
  KEY `country_idx` (`country`),
  KEY `cityid_countryid_idx` (`city_id`,`country_id`),
  KEY `city_country_idx` (`city`,`country`),
  KEY `currency_idx` (`currency`),
  KEY `birthdate_idx` (`birthdate`),
  KEY `gender_idx` (`gender`),
  KEY `relationship_status_idx` (`relationship_status`),
  KEY `number_visit_all_shop_idx` (`number_visit_all_shop`),
  KEY `number_visit_city_idx` (`number_visit_all_shop`,`city`),
  KEY `number_visit_cityid_idx` (`number_visit_all_shop`,`city_id`),
  KEY `number_visit_gender_idx` (`number_visit_all_shop`,`gender`),
  KEY `amount_spent_all_shop_idx` (`amount_spent_all_shop`),
  KEY `amount_spent_city_idx` (`amount_spent_all_shop`,`city`),
  KEY `amount_spent_cityid_idx` (`amount_spent_all_shop`,`city_id`),
  KEY `amount_spent_gender_idx` (`amount_spent_all_shop`,`gender`),
  KEY `average_spent_per_month_all_shop_idx` (`average_spent_per_month_all_shop`),
  KEY `average_spent_city_idx` (`average_spent_per_month_all_shop`,`city`),
  KEY `average_spent_cityid_idx` (`average_spent_per_month_all_shop`,`city_id`),
  KEY `average_spent_gender_idx` (`average_spent_per_month_all_shop`,`gender`),
  KEY `last_visit_any_shop_idx` (`last_visit_any_shop`),
  KEY `last_visit_shop_id_idx` (`last_visit_shop_id`),
  KEY `last_purchase_any_shop_idx` (`last_purchase_any_shop`),
  KEY `last_purchase_shop_id_idx` (`last_purchase_shop_id`),
  KEY `last_spent_any_shop_idx` (`last_spent_any_shop`),
  KEY `last_spent_shop_id_idx` (`last_spent_shop_id`),
  KEY `modified_by_idx` (`modified_by`),
  KEY `created_at_idx` (`created_at`),
  KEY `updated_at_idx` (`updated_at`),
  KEY `province_id_idx` (`province_id`),
  KEY `province_idx` (`province`),
  KEY `number_visit_provinceid_idx` (`number_visit_all_shop`,`province_id`),
  KEY `number_visit_province_idx` (`number_visit_all_shop`,`province`),
  KEY `amount_spent_provinceid_idx` (`amount_spent_all_shop`,`province_id`),
  KEY `amount_spent_province_idx` (`amount_spent_all_shop`,`province`),
  KEY `average_spent_provinceid_idx` (`average_spent_per_month_all_shop`,`province_id`),
  KEY `average_spent_province_idx` (`average_spent_per_month_all_shop`,`province`),
  KEY `remember_token_idx` (`preferred_language`),
  KEY `annual_salary_range1_idx` (`avg_annual_income1`),
  KEY `annual_salary_range2_idx` (`avg_annual_income2`),
  KEY `annual_salary_range12_idx` (`avg_annual_income1`,`avg_annual_income2`),
  KEY `has_children_idx` (`has_children`),
  KEY `number_of_children_idx` (`number_of_children`),
  KEY `has_children_number_idx` (`has_children`,`number_of_children`),
  KEY `retailerid_idx` (`retailer_id`),
  KEY `avg_monthly_spent1_idx` (`avg_monthly_spent1`),
  KEY `avg_monthly_spent2_idx` (`avg_monthly_spent2`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}user_personal_interest`
--

DROP TABLE IF EXISTS `{{PREFIX}}user_personal_interest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}user_personal_interest` (
  `user_personal_interest_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `personal_interest_id` int(10) unsigned NOT NULL,
  `personal_interest_name` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `personal_interest_value` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`user_personal_interest_id`),
  KEY `userid_idx` (`user_id`),
  KEY `personalid_idx` (`personal_interest_id`),
  KEY `user_personal_idx` (`user_id`,`personal_interest_id`),
  KEY `user_personal_interest_name_idx` (`personal_interest_name`),
  KEY `user_personal_interest_value_idx` (`personal_interest_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}users`
--

DROP TABLE IF EXISTS `{{PREFIX}}users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}users` (
  `user_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `user_password` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `user_email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `user_firstname` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_lastname` varchar(75) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_last_login` datetime DEFAULT NULL,
  `user_ip` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_role_id` int(10) unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8_unicode_ci DEFAULT 'pending' COMMENT 'valid: active, pending, blocked, or deleted',
  `remember_token` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `modified_by` bigint(20) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`user_id`),
  KEY `username_idx` (`username`),
  KEY `username_pwd_idx` (`username`,`user_password`),
  KEY `email_idx` (`user_email`),
  KEY `username_pwd_status_idx` (`username`,`user_password`,`status`),
  KEY `user_ip_idx` (`user_ip`),
  KEY `user_role_id_idx` (`user_role_id`),
  KEY `status_idx` (`status`),
  KEY `modified_by_idx` (`modified_by`),
  KEY `created_at_idx` (`created_at`),
  KEY `updated_at_idx` (`updated_at`),
  KEY `remember_token_idx` (`remember_token`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}widget_retailer`
--

DROP TABLE IF EXISTS `{{PREFIX}}widget_retailer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}widget_retailer` (
  `widget_retailer_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `widget_id` bigint(20) unsigned NOT NULL,
  `retailer_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`widget_retailer_id`),
  KEY `widgetid_idx` (`widget_id`),
  KEY `retailerid_idx` (`retailer_id`),
  KEY `widget_retailer_idx` (`widget_id`,`retailer_id`),
  KEY `created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `{{PREFIX}}widgets`
--

DROP TABLE IF EXISTS `{{PREFIX}}widgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `{{PREFIX}}widgets` (
  `widget_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `widget_type` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  `widget_object_id` bigint(20) unsigned DEFAULT NULL,
  `widget_slogan` varchar(500) COLLATE utf8_unicode_ci DEFAULT NULL,
  `widget_order` tinyint(3) unsigned DEFAULT '0',
  `merchant_id` bigint(20) unsigned DEFAULT NULL,
  `animation` varchar(30) COLLATE utf8_unicode_ci DEFAULT 'none',
  `status` varchar(15) COLLATE utf8_unicode_ci DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `modified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`widget_id`),
  KEY `widget_type_idx` (`widget_type`),
  KEY `widget_objectid_idx` (`widget_object_id`),
  KEY `merchantid_idx` (`merchant_id`),
  KEY `status_idx` (`status`),
  KEY `created_by_idx` (`created_by`),
  KEY `modified_by_idx` (`modified_by`),
  KEY `created_at_idx` (`created_at`),
  KEY `type_object_status_idx` (`widget_type`,`widget_object_id`,`status`),
  KEY `widget_order_idx` (`widget_order`),
  KEY `animation_idx` (`animation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2015-04-10 21:29:47
DUMP;

        // Replace the prefix
        $tablePrefix = DB::getTablePrefix();
        $sqldump = str_replace('{{PREFIX}}', $tablePrefix, $sqldump);
        DB::unprepared($sqldump);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tables = DB::select('SHOW TABLES');
        $query = '';
        $default = Config::get('database.default');
        $dbname = Config::get('database.connections.' . $default . '.database');
        $prefix = DB::getTablePrefix();

        foreach ($tables as $table) {
            $name = $table->{'Tables_in_' . $dbname};

            if ($name === $prefix . 'migrations') {
                continue;
            }
            $query .= sprintf("DROP TABLE %s;\n", $name);
        }

        DB::unprepared($query);
    }

}
