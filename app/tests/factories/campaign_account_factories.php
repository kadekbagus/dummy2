<?php

$factory('CampaignAccount', [
    'user_id' 		=> 'factory:User',
    'account_name'   => '',
    'parent_user_id'  => 'factory:User',
    'position'  => '',
    'status'  => 'active'
]);
