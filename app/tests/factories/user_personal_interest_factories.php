<?php
/*
| Table: orb_user_personal_interest
| Columns:
| user_personal_interest_id   char(16) PK
| user_id char(16)
| personal_interest_id    char(16)
| object_type varchar(50)
| personal_interest_name  varchar(50)
| personal_interest_value varchar(100)
| created_at  timestamp
| updated_at  timestamp
*/
$factory('UserPersonalInterest', [
    'user_id'                 => 'factory:User',
    'personal_interest_id'    => 0,
    'object_type'             => $faker->randomElement(['category']),
]);

$factory('UserPersonalInterest', 'user_category_interest', [
    'user_id'                 => 'factory:User',
    'personal_interest_id'    => 'factory:Category',
    'object_type'             => 'category',
]);
