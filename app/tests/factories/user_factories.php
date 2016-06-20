<?php
/*
 | Table: users
 | Columns:
 | user_id bigint(20) UN AI PK
 | username    varchar(50)
 | user_password   varchar(100)
 | user_email  varchar(255)
 | user_firstname  varchar(50)
 | user_lastname   varchar(75)
 | user_last_login datetime
 | user_ip varchar(45)
 | user_role_id    int(10) UN
 | status  varchar(20)
 | remember_token  varchar(100)
 | modified_by bigint(20)
 | created_at  timestamp
 | updated_at  timestamp
 */
$factory('User', [
    'username'       => $faker->userName,
    'user_password'  => "Secret",
    'user_email'     => $faker->email,
    'user_firstname' => $faker->firstName,
    'user_lastname'  => $faker->lastName,
    'status'         => 'active',
    'user_role_id'   => 'factory:Role'
]);

$factory('User', 'user_super_admin', [
    'username'       => $faker->userName,
    'user_password'  => "SecretAdmin",
    'user_email'     => $faker->email,
    'user_firstname' => $faker->firstName,
    'user_lastname'  => $faker->lastName,
    'status'         => 'active',
    'user_role_id'   => 'factory:role_super_admin'
]);

$factory('User', 'user_mall_owner', [
    'username'       => $faker->userName,
    'user_password'  => "MallOwner",
    'user_email'     => $faker->email,
    'user_firstname' => $faker->firstName,
    'user_lastname'  => $faker->lastName,
    'status'         => 'active',
    'user_role_id'   => 'factory:role_mall_owner'
]);

$factory('User', 'user_guest', [
    'username'       => $faker->userName,
    'user_password'  => "SecretAdmin",
    'user_email'     => $faker->email,
    'user_firstname' => $faker->firstName,
    'user_lastname'  => $faker->lastName,
    'status'         => 'active',
    'user_role_id'   => 'factory:role_guest'
]);

$factory('User', 'user_consumer', [
    'username'       => $faker->userName,
    'user_password'  => "SecretAdmin",
    'user_email'     => $faker->email,
    'user_firstname' => $faker->firstName,
    'user_lastname'  => $faker->lastName,
    'status'         => 'active',
    'user_role_id'   => 'factory:role_consumer'
]);

$factory('User', 'user_mall_customer_service', [
    'username'       => $faker->userName,
    'user_password'  => "SecretAdmin",
    'user_email'     => $faker->email,
    'user_firstname' => $faker->firstName,
    'user_lastname'  => $faker->lastName,
    'status'         => 'active',
    'user_role_id'   => 'factory:role_mall_customer_service'
]);