<?php
/*
 | Table: orb_employees
 | Columns:
 | employee_id char(16) PK
 | user_id char(16)
 | employee_id_char    char(16)
 | position    varchar(50)
 | status  varchar(15)
 | created_at  timestamp
 | updated_at  timestamp
*/
$factory('Employee', [
    'user_id'     => 'factory:User',
    'employee_id_char' => 'EMPL001',
    'position'    => 'Employee',
    'status'      => 'active',
]);
