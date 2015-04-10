<?php
/*
 | Table: event_retailer
 | Columns:
 | event_retailer_id    int(10) UN AI PK
 | event_id int(10) UN
 | retailer_id  int(10) UN
 | created_at   timestamp
 | updated_at   timestamp
 */

$factory('EventRetailer', [
    'event_id' => 'factory:Event',
    'retailer_id' => 'factory:Merchant'
]);