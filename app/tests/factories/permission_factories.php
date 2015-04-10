<?php
/*
 | Table: permissions
 | Columns:
 | permission_id   int(10) UN AI PK
 | permission_name varchar(50)
 | permission_label    varchar(50)
 | permission_group    varchar(50)
 | permission_group_label  varchar(50)
 | permission_name_order   int(10) UN
 | permission_group_order  int(10) UN
 | permission_default_value    varchar(3)
 | modified_by bigint(20) UN
 | created_at  timestamp
 | updated_at  timestamp
 */

 $factory('Permission', [
     'permission_name' => $faker->word(),
     'permission_label' => $faker->word(),
     'permission_group' => $faker->word(),
     'permission_group_label' => $faker->word(),
     'permission_group_order'  => $faker->numerify('#'),
     'permission_name_order'   => $faker->numerify('#')
 ]);
