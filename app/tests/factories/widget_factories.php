<?php
/**
 * Table: users
 * Columns:
 * widget_id char(16) UN PK
 * widget_type    varchar(50)
 * widget_object_id   char(16)
 * widget_slogan  varchar(500)
 * widget_order  tinyint(3)
 * merchant_id   char(16)
 * animation varchar(30)
 * status  varchar(20)
 * created_by bigint(20)
 * modified_by bigint(20)
 * created_at  timestamp
 * updated_at  timestamp
 */
$factory('Widget', [
    'widget_type'   => $faker->randomElement(['tenant', 'promotion', 'news', 'coupon', 'lucky_draw', 'service', 'free_wifi']),
    'merchant_id'   => 'factory:Mall',
    'status'        => 'active',
    'widget_order'  => $faker->randomDigitNotNull
]);

/**
 * Table: widget_retailers
 * Columns:
 * widget_retailer_id  char(16) UN PK
 * widget_id  char(16)
 * retailer_id  char(16)
 * created_at  timestamp
 * updated_at  timestamp
 */
$factory('WidgetRetailer', [
    'widget_id'   => 'factory:Widget',
    'retailer_id'   => 'factory:Mall',
]);