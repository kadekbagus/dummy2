<?php
/*
 | Table: mac_addresses
 | Columns:
 | mac_address_id	bigint(20) UN AI PK
 | user_email	varchar(255)
 | mac_address	char(12)
 | created_at	timestamp
 | updated_at	timestamp
 */

$factory('MacAddress', function(Faker\Generator $faker) {
    return [
        'mac_address' => $faker->macAddress(),
        'user_email'  => $faker->companyEmail()
    ];
});
