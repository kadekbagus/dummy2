<?php
/*
 | Table: orbs_merchants
 | Columns:
 | merchant_id	char(16) PK
 | omid	varchar(100)
 | orid	varchar(100)
 | user_id	char(16)
 | timezone_id	char(16)
 | email	varchar(255)
 | name	varchar(100)
 | description	text
 | address_line1	varchar(2000)
 | address_line2	varchar(2000)
 | address_line3	varchar(2000)
 | postal_code	int(11)
 | city_id	char(16)
 | city	varchar(100)
 | province	varchar(100)
 | country_id	char(16)
 | country	varchar(100)
 | phone	varchar(50)
 | fax	varchar(50)
 | start_date_activity	datetime
 | end_date_activity	datetime
 | status	varchar(15)
 | logo	varchar(255)
 | currency	char(3)
 | currency_symbol	char(3)
 | tax_code1	varchar(15)
 | tax_code2	varchar(15)
 | tax_code3	varchar(15)
 | slogan	text
 | vat_included	char(3)
 | contact_person_firstname	varchar(75)
 | contact_person_lastname	varchar(75)
 | contact_person_position	varchar(30)
 | contact_person_phone	varchar(50)
 | contact_person_phone2	varchar(50)
 | contact_person_email	varchar(255)
 | sector_of_activity	varchar(100)
 | object_type	varchar(15)
 | location_id	char(16)
 | location_type	varchar(32)
 | parent_id	char(16)
 | is_mall	varchar(3)
 | url	varchar(255)
 | ci_domain	varchar(100)
 | box_url	varchar(255)
 | masterbox_number	varchar(20)
 | slavebox_number	varchar(20)
 | mobile_default_language	varchar(15)
 | pos_language	varchar(2)
 | ticket_header	text
 | ticket_footer	text
 | floor	varchar(30)
 | unit	varchar(30)
 | external_object_id	char(16)
 | modified_by	char(16)
 | enable_shopping_cart	varchar(3)
 | created_at	timestamp
 | updated_at	timestamp
 */

$factory('TenantStoreAndService',  'tenant_store', [
	'parent_id' => 'factory:Mall',
	'name' => $faker->company,
	'user_id' => 'factory:User',
	'contact_person_firstname' => $faker->firstName,
	'contact_person_lastname' => $faker->lastName,
	'contact_person_phone' => $faker->phoneNumber,
	'floor' => $faker->randomDigitNotNull,
	'unit' => $faker->buildingNumber,
	'object_type' => 'tenant'
]);

$factory('TenantStoreAndService',  'tenant_service', [
	'parent_id' => 'factory:Mall',
	'name' => $faker->company,
	'user_id' => 'factory:User',
	'contact_person_firstname' => $faker->firstName,
	'contact_person_lastname' => $faker->lastName,
	'contact_person_phone' => $faker->phoneNumber,
	'floor' => $faker->randomDigitNotNull,
	'unit' => $faker->buildingNumber,
	'object_type' => 'service'
]);
