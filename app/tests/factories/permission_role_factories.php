<?php
/*
 | Table: permission_role
 | Columns:
 | permission_role_id  bigint(20) UN AI PK
 | role_id int(10) UN
 | permission_id   int(10) UN
 | allowed varchar(3)
 | created_at  timestamp
 | updated_at  timestamp
 */

$factory('PermissionRole', [
    'role_id' => 'factory:role_admin',
    'permission_id' => 'factory:Permission'
]);
