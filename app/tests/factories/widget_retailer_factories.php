<?php

/*
 | Table: orbs_widget_retailer
 | Columns:
 | widget_retailer_id  char(16) PK
 | widget_id   char(16)
 | retailer_id char(16)
 | created_at  timestamp
 | updated_at  timestamp
 */

$factory('WidgetRetailer', [
    'widget_id' => 'factory:Widget',
    'retailer_id' => 'factory:Mall'
]);