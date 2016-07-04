<?php

/*
 | Table: media
 | Columns:
 | promotion_retailer_id    int(10) UN AI PK
 | promotion_id int(10) UN
 | retailer_id  int(10) UN
 | created_at   timestamp
 | updated_at   timestamp
*/

$factory('Media', [
    'media_name_id' => $faker->safeColorName
]);
