<?php
/*
 | Table: events
 | Columns:
 | event_id int(10) UN AI PK
 | merchant_id  int(10) UN
 | event_name   varchar(255)
 | event_type   varchar(15)
 | description  varchar(2000)
 | begin_date   datetime
 | end_date datetime
 | is_permanent char(1)
 | status   varchar(15)
 | image    varchar(255)
 | link_object_type varchar(50)
 | link_object_id1  bigint(20) UN
 | link_object_id2  bigint(20) UN
 | link_object_id3  bigint(20) UN
 | link_object_id4  bigint(20) UN
 | link_object_id5  bigint(20) UN
 | widget_object_type   varchar(50)
 | created_by   bigint(20) UN
 | modified_by  bigint(20) UN
 | created_at   timestamp
 | updated_at   timestamp
 */

$factory('EventModel', 'Event', [
    'merchant_id' => 'factory:Merchant',
    'event_name'  => $faker->words(2),
    'event_type'  => $faker->randomElement(['informative', 'link']),
    'status'      => 'active',
    'description' => $faker->sentence(),
    'begin_date'  => $faker->dateTimeBetween('-3 months', '-3 weeks'),
    'end_date'    => $faker->dateTimeBetween('+2 days', '+2 weeks'),
    'is_permanent' => $faker->randomElement(['Y', 'N']),
    'link_object_type' => 'product',
    'link_object_id1'  => 'factory:Product'
]);
