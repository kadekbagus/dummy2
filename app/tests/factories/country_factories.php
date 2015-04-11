<?php
/*
 | Table: orbs_countries
 | Columns:
 | country_id  int(10) UN AI PK
 | name    varchar(75)
 | code    char(2)
 | created_at  timestamp
 | updated_at  timestamp
 */

$factory('Country', [
    'name' => $faker->country,
    'code' => $faker->countryCode
]);
