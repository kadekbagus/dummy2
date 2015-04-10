<?php
/*
 | Table: promotion_rules
 | Columns:
 | promotion_rule_id    int(10) UN AI PK
 | promotion_id int(10) UN
 | rule_type    varchar(50)
 | rule_value   decimal(16,2)
 | rule_object_type varchar(50)
 | rule_object_id1  bigint(20) UN
 | rule_object_id2  bigint(20) UN
 | rule_object_id3  bigint(20) UN
 | rule_object_id4  bigint(20) UN
 | rule_object_id5  bigint(20) UN
 | discount_object_type varchar(50)
 | discount_object_id1  bigint(20) UN
 | discount_object_id2  bigint(20) UN
 | discount_object_id3  bigint(20) UN
 | discount_object_id4  bigint(20) UN
 | discount_object_id5  bigint(20) UN
 | discount_value   decimal(16,4)
 | is_cumulative_with_coupons   char(1)
 | is_cumulative_with_promotions    char(1)
 | coupon_redeem_rule_value decimal(16,2)
 | created_at   timestamp
 | updated_at   timestamp
*/

$factory('CouponRule', [
    'rule_type' => $faker->randomElement([
        "cart_discount_by_value",
        "cart_discount_by_percentage",
        "new_product_price",
        "product_discount_by_value",
        "product_discount_by_percentage"
    ]),
    'rule_value' => $faker->numerify('##'),
]);
