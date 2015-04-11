<?php
/*
 | Table: products
 | Columns:
 | product_id  bigint(20) UN AI PK
 | product_code    varchar(20)
 | upc_code    varchar(100)
 | product_name    varchar(255)
 | price   decimal(16,2)
 | merchant_tax_id1    int(10) UN
 | merchant_tax_id2    int(10) UN
 | short_description   varchar(2000)
 | long_description    text
 | is_featured char(1)
 | new_from    datetime
 | image   varchar(255)
 | new_until   datetime
 | in_store_localization   varchar(255)
 | post_sales_url  text
 | merchant_id int(10) UN
 | attribute_id1   int(10) UN
 | attribute_id2   int(10) UN
 | attribute_id3   int(10) UN
 | attribute_id4   int(10) UN
 | attribute_id5   int(10) UN
 | category_id1    int(10) UN
 | category_id2    int(10) UN
 | category_id3    int(10) UN
 | category_id4    int(10) UN
 | category_id5    int(10) UN
 | created_by  bigint(20)
 | modified_by bigint(20)
 | status  varchar(15)
 | created_at  timestamp
 | updated_at  timestamp
*/

$factory('Product', function (Faker\Generator $faker) {
    $price = $faker->numerify('###000.00');
    $price = floatval($price);
    $new_from = $faker->dateTimeBetween('-3 months' , '-2 weeks');

    return [
        'product_code' => $faker->bothify('SKU-??-####'),
        'upc_code'     => $faker->ean13(),
        'product_name' => $faker->words(3),
        'price'        => $price,
        'new_from'     => $new_from,
        'new_until'    => $faker->dateTimeBetween($new_from),
        'short_description' => $faker->sentence(),
        'long_description'  => $faker->sentences(4),
        'status'       => 'active'
    ];
});
