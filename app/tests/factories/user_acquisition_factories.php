<?php
/*
 | Table: orb_user_acquisitions
 | Columns:
 | user_acquisition_id char(16) PK
 | user_id char(16)
 | acquirer_id char(16)
 | signup_via  varchar(20)
 | social_id   varchar(40)
 | created_at  timestamp
 | updated_at  timestamp
*/
$factory('UserAcquisition', [
    'user_id'     => 'factory:User',
    'acquirer_id' => 'factory:Mall',
    'signup_via'  => 'cs',
]);