<?php
/*
 | Table: merchants
 | Columns:
 | merchant_id int(10) UN AI PK
 | omid    varchar(100)
 | orid    varchar(100)
 | user_id bigint(20) UN
 | email   varchar(255)
 | name    varchar(100)
 | description text
 | address_line1   varchar(2000)
 | address_line2   varchar(2000)
 | address_line3   varchar(2000)
 | postal_code int(11)
 | city_id int(10) UN
 | city    varchar(100)
 | country_id  int(10) UN
 | country varchar(100)
 | phone   varchar(50)
 | fax varchar(50)
 | start_date_activity datetime
 | end_date_activity   datetime
 | status  varchar(15)
 | logo    varchar(255)
 | currency    char(3)
 | currency_symbol char(3)
 | tax_code1   varchar(15)
 | tax_code2   varchar(15)
 | tax_code3   varchar(15)
 | slogan  text
 | vat_included    char(3)
 | contact_person_firstname    varchar(75)
 | contact_person_lastname varchar(75)
 | contact_person_position varchar(30)
 | contact_person_phone    varchar(50)
 | contact_person_phone2   varchar(50)
 | contact_person_email    varchar(255)
 | sector_of_activity  varchar(100)
 | object_type varchar(15)
 | parent_id   int(10) UN
 | url varchar(255)
 | masterbox_number    varchar(20)
 | slavebox_number varchar(20)
 | mobile_default_language varchar(15)
 | pos_language    varchar(2)
 | ticket_header   text
 | ticket_footer   text
 | modified_by bigint(20) UN
 | created_at  timestamp
 | updated_at  timestamp
 */
$factory('Merchant', [
    'name'    => $faker->company,
    'user_id'   => 'factory:User',
    'contact_person_firstname' => $faker->firstName,
    'contact_person_lastname'  => $faker->lastName,
    'contact_person_phone'     => $faker->phoneNumber,
    'mobile_default_language'  => 'en'
]);


$factory('Retailer', [
    'parent_id' => 'factory:Merchant',
    'name'      => $faker->company,
    'user_id'   => 'factory:User',
    'contact_person_firstname' => $faker->firstName,
    'contact_person_lastname'  => $faker->lastName,
    'contact_person_phone'     => $faker->phoneNumber,
    'mobile_default_language'  => 'en'
]);

$factory('Retailer', 'retailer_mall', [
    'parent_id' => 'factory:Merchant',
    'name'      => $faker->company,
    'user_id'   => 'factory:User',
    'is_mall'   => 'yes',
    'contact_person_firstname' => $faker->firstName,
    'contact_person_lastname'  => $faker->lastName,
    'contact_person_phone'     => $faker->phoneNumber,
    'mobile_default_language'  => 'en'
]);

$factory('MallGroup', [
    'name'      => $faker->company,
    'user_id'   => 'factory:User',
    'contact_person_firstname' => $faker->firstName,
    'contact_person_lastname'  => $faker->lastName,
    'contact_person_phone'     => $faker->phoneNumber,
    'mobile_default_language'  => 'en'
]);

$factory('Mall', [
    'parent_id' => 'factory:MallGroup',
    'name'      => $faker->company,
    'user_id'   => 'factory:User',
    'contact_person_firstname' => $faker->firstName,
    'contact_person_lastname'  => $faker->lastName,
    'contact_person_phone'     => $faker->phoneNumber,
    'timezone_id'     => 'factory:Timezone',
    'mobile_default_language'  => 'en'
]);

$factory('Tenant', [
    'parent_id' => 'factory:Mall',
    'name'      => $faker->company,
    'user_id'   => 'factory:User',
    'contact_person_firstname' => $faker->firstName,
    'contact_person_lastname'  => $faker->lastName,
    'contact_person_phone'     => $faker->phoneNumber,
    'mobile_default_language'  => 'en'
]);
