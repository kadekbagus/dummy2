<?php

/*
 | Table: orbs_inboxes
 | Columns:
 | inbox_id	char(16) PK
 | user_id	char(16)
 | merchant_id	char(16)
 | from_id	char(16)
 | from_name	varchar(20)
 | subject	varchar(250)
 | content	text
 | inbox_type	varchar(20)
 | is_read	char(1)
 | status	varchar(15)
 | created_by	char(16)
 | modified_by	char(16)
 | created_at	timestamp
 | updated_at	timestamp
 | is_notified	char(1)
*/

$factory('Inbox', 'inbox', [
    'merchant_id' => 'factory:Mall',
    'is_read' => 'N',
    'status' => 'active',
    'is_notified' => 'N'
]);