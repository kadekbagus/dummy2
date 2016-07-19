<?php

/*
 | Table: keyword_object
 | Columns:
 | keyword_object_id	char(16) PK
 | keyword_id	char(16)
 | object_type	varchar(50)
 | object_id	char(16)
 | created_at	timestamp
 | updated_at	timestamp
 */

$factory('KeywordObject', [
	'keyword_id'  => 'factory:Keyword',
	'object_type' => $faker->randomElement(['tenant', 'service', 'news', 'promotion', 'coupon']),
	'object_id'   => 'factory:Object'
]);