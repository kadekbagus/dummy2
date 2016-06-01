<?php
/*
 | Table: orb_social_media
 | Columns:
 | social_media_id char(16) PK
 | social_media_code   varchar(50)
 | social_media_name   varchar(50)
 | social_media_main_url   varchar(255)
 | created_at  timestamp
 | updated_at  timestamp
 */
$factory('SocialMedia', [
    'social_media_code'     => 'facebook',
    'social_media_name'     => 'Facebook',
    'social_media_main_url' => 'facebook.com'
]);