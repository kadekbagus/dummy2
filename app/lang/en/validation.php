<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    "accepted"             => "The :attribute must be accepted.",
    "active_url"           => "The :attribute is not a valid URL.",
    "after"                => "The :attribute must be a date after :date.",
    "alpha"                => "The :attribute may only contain letters.",
    "alpha_dash"           => "The :attribute may only contain letters, numbers, and dashes.",
    "alpha_num"            => "The :attribute may only contain letters and numbers.",
    "array"                => "The :attribute must be an array.",
    "before"               => "The :attribute must be a date before :date.",
    "between"              => array(
        "numeric" => "The :attribute must be between :min and :max.",
        "file"    => "The :attribute must be between :min and :max kilobytes.",
        "string"  => "The :attribute must be between :min and :max characters.",
        "array"   => "The :attribute must have between :min and :max items.",
    ),
    "boolean"              => "The :attribute field must be true or false",
    "confirmed"            => "The :attribute confirmation does not match.",
    "date"                 => "The :attribute is not a valid date.",
    "date_format"          => "The :attribute does not match the format :format.",
    "different"            => "The :attribute and :other must be different.",
    "digits"               => "The :attribute must be :digits digits.",
    "digits_between"       => "The :attribute must be between :min and :max digits.",
    "email"                => "The :attribute must be a valid email address.",
    "exists"               => "The selected :attribute is invalid.",
    "image"                => "The :attribute must be an image.",
    "in"                   => "The selected :attribute is invalid.",
    "integer"              => "The :attribute must be an integer.",
    "ip"                   => "The :attribute must be a valid IP address.",
    "max"                  => array(
        "numeric" => "The :attribute may not be greater than :max.",
        "file"    => "The :attribute may not be greater than :max kilobytes.",
        "string"  => "The :attribute may not be greater than :max characters.",
        "array"   => "The :attribute may not have more than :max items.",
    ),
    "mimes"                => "The :attribute must be a file of type: :values.",
    "min"                  => array(
        "numeric" => "The :attribute must be at least :min.",
        "file"    => "The :attribute must be at least :min kilobytes.",
        "string"  => "The :attribute must be at least :min characters.",
        "array"   => "The :attribute must have at least :min items.",
    ),
    "not_in"               => "The selected :attribute is invalid.",
    "numeric"              => "The :attribute must be a number.",
    "regex"                => "The :attribute format is invalid.",
    "required"             => "The :attribute field is required.",
    "required_if"          => "The :attribute field is required when :other is :value.",
    "required_with"        => "The :attribute field is required when :values is present.",
    "required_with_all"    => "The :attribute field is required when :values is present.",
    "required_without"     => "The :attribute field is required when :values is not present.",
    "required_without_all" => "The :attribute field is required when none of :values are present.",
    "same"                 => "The :attribute and :other must match.",
    "size"                 => array(
        "numeric" => "The :attribute must be :size.",
        "file"    => "The :attribute must be :size kilobytes.",
        "string"  => "The :attribute must be :size characters.",
        "array"   => "The :attribute must contain :size items.",
    ),
    "unique"               => "The :attribute has already been taken.",
    "url"                  => "The :attribute format is invalid.",
    "timezone"             => "The :attribute must be a valid zone.",

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'orbit' => array(
        // This will moved soon to the 'exists' key
        'email' => array(
            'exists' => 'The email address has already been taken.',
        ),
        'exists' => array(
            'username'              => 'The username has already been taken.',
            'email'                 => 'Email address has already been taken.',
            'omid'                  => 'OMID has already been taken by another Merchant.',
            'orid'                  => 'ORID has already been taken by another Retailer.',
            'category_name'         => 'The category name has already been used.',
            'have_product_category' => 'The family cannot be deleted: One or more products are attached to this family.',
            'product_have_transaction' => 'The product has one or more transactions linked to it, so it cannot be deleted.',
            'promotion_name'        => 'The promotion name has already been used.',
            'coupon_name'           => 'The coupon name has already been used.',
            'issued_coupon_code'    => 'The coupon code has been redeemed.',
            'event_name'            => 'The event name has already been used.',
            'tax_name'              => 'The tax name has already been used.',
            'tax_link_to_product'   => 'The tax cannot be deleted: One or more products are attached to this tax.',
            'product'               => array(
                'attribute'         => array(
                    'unique'        => 'The attribute name \':attrname\' already exists.',
                    'value'         => array(
                        'transaction'   => 'The attribute combination has one or more transactions linked to it, so it cannot be edited or deleted.',
                        'unique'        => 'The attribute value \':value\' already exists.'
                    ),
                ),
                'variant'           => array(
                    'transaction'   => 'Product combination ID :id has one or more transactions linked to it, so it cannot be edited or deleted.'
                ),
                'upc_code'          => 'UPC :upc has already been used by other product.',
                'sku_code'          => 'SKU :sku has already been used by other product.',
                'transaction'       => 'Product \':name\' has one or more transactions linked to it, so it cannot be edited or deleted.'
            ),
            'product_attribute_have_transaction'           => 'The product attribute has one or more transactions linked to it, so it cannot be edited or deleted.',
            'product_attribute_value_have_transaction'     => 'The product attribute value has one or more transactions linked to it, so it cannot be edited or deleted.',
            'product_attribute_have_product'               => 'The product attribute has one or more products linked to it, so it cannot be edited or deleted.',
            'product_attribute_value_have_product_variant' => 'The product attribute value has one or more product variants linked to it, so it cannot be edited or deleted.',
            'employeeid'            => 'The employee ID is not available.',
            'widget_type'           => 'Another widget with the same widget type already exists',
            'merchant_have_retailer' => 'The merchant has one or more retailers linked to it, so it cannot be deleted.',
            'merchant_retailers_is_box_current_retailer' => 'The merchant status cannot be set to inactive, because one of its retailers is set to box current retailer.',
            'deleted_retailer_is_box_current_retailer' => 'The retailer cannot be deleted, because is set to box current retailer.',
            'inactive_retailer_is_box_current_retailer' => 'The retailer status cannot be set to inactive, because is set to box current retailer.',
        ),
        'access' => array(
            'forbidden'              => 'You do not have permission to :action.',
            'needtologin'            => 'You have to login to view this page.',
            'loginfailed'            => 'Your email or password is incorrect.',
            'tokenmissmatch'         => 'CSRF protection token missmatch.',
            'wrongpassword'          => 'Password is incorrect.',
            'old_password_not_match' => 'Old password is incorrect.',
            'view_activity'          => 'You do not have access to view activity',
            'view_personal_interest' => 'You do not have access to view personal interest',
            'view_role'              => 'You do not have access to view role',
        ),
        'empty' => array(
            'role'                 => 'The Role ID you specified is not found.',
            'consumer_role'        => 'The Consumer role does not exist.',
            'token'                => 'The Token you specified is not found.',
            'user'                 => 'The User ID you specified is not found.',
            'merchant'             => 'The Merchant ID you specified is not found.',
            'retailer'             => 'The Retailer ID you specified is not found.',
            'product'              => 'The Product ID you specified is not found.',
            'category'             => 'The Category ID you specified is not found.',
            'tax'                  => 'The Tax ID you specified is not found.',
            'promotion'            => 'The Promotion ID you specified is not found.',
            'coupon'               => 'The Coupon ID you specified is not found.',
            'issued_coupon'        => 'The Issued Coupon ID you specified is not found.',
            'event'                => 'The Event ID you specified is not found.',
            'user_status'          => 'The user status you specified is not found.',
            'user_sortby'          => 'The sort by argument you specified is not valid, the valid values are: username, email, firstname, lastname, registered_date, gender, city, last_visit_shop, last_visit_date, last_spent_amount.',
            'merchant_status'      => 'The merchant status you specified is not found.',
            'merchant_sortby'      => 'The sort by argument you specified is not valid, the valid values are: registered_date, merchant_name, merchant_email, merchant_userid, merchant_description, merchantid, merchant_address1, merchant_address2, merchant_address3, merchant_cityid, merchant_city, merchant_countryid, merchant_country, merchant_phone, merchant_fax, merchant_status, merchant_currency, start_date_activity, total_retailer.',
            'retailer_status'      => 'The retailer status you specified is not found.',
            'retailer_sortby'      => 'The sort by argument for retailer you specified is not valid, the valid values are: orid, registered_date, retailer_name, retailer_email, retailer_userid, retailer_description, retailerid, retailer_address1, retailer_address2, retailer_address3, retailer_cityid, retailer_city, retailer_countryid, retailer_country, retailer_phone, retailer_fax, retailer_status, retailer_currency, contact_person_firstname, merchant_name.',
            'tax_status'           => 'The tax status you specified is not found.',
            'tax_sortby'           => 'The sort by argument for tax you specified is not valid, the valid values are: registered_date, merchant_tax_id, tax_name, tax_type, tax_value, tax_order.',
            'tax_type'             => 'The tax type you specified is not found. Valid values are: government, service, luxury.',
            'category_status'      => 'The category status you specified is not found.',
            'category_sortby'      => 'The sort by argument you specified is not valid, the valid values are: registered_date, category_name, category_level, category_order, description, status.',
            'promotion_status'     => 'The promotion status you specified is not found.',
            'promotion_sortby'     => 'The sort by argument you specified is not valid, the valid values are: registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status, rule_type, display_discount_value.',
            'promotion_by_retailer_sortby' => 'The sort by argument you specified is not valid, the valid values are: retailer_name, registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status.',
            'promotion_type'       => 'The promotion type you specified is not found.',
            'rule_type'            => 'The rule type you specified is not found.',
            'rule_object_type'     => 'The rule object type you specified is not found.',
            'rule_object_id1'      => 'The rule object ID1 you specified is not found.',
            'rule_object_id2'      => 'The rule object ID2 you specified is not found.',
            'rule_object_id3'      => 'The rule object ID3 you specified is not found.',
            'rule_object_id4'      => 'The rule object ID4 you specified is not found.',
            'rule_object_id5'      => 'The rule object ID5 you specified is not found.',
            'discount_object_type' => 'The discount object type you specified is not found.',
            'discount_object_id1'  => 'The discount object ID1 you specified is not found.',
            'discount_object_id2'  => 'The discount object ID2 you specified is not found.',
            'discount_object_id3'  => 'The discount object ID3 you specified is not found.',
            'discount_object_id4'  => 'The discount object ID4 you specified is not found.',
            'discount_object_id5'  => 'The discount object ID5 you specified is not found.',
            'coupon_status'        => 'The coupon status you specified is not found.',
            'coupon_sortby'        => 'The sort by argument you specified is not valid, the valid values are: registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status, rule_type, display_discount_value.',
            'coupon_by_issue_retailer_sortby' => 'The sort by argument you specified is not valid, the valid values are: issue_retailer_name, registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status.',
            'coupon_type'          => 'The coupon type you specified is not found.',
            'issued_coupon_status' => 'The issued coupon status you specified is not found.',
            'issued_coupon_sortby' => 'The sort by argument you specified is not valid, the valid values are: registered_date, issued_coupon_code, expired_date, issued_date, redeemed_date, status.',
            'issued_coupon_by_retailer_sortby' => 'The sort by argument you specified is not valid, the valid values are: redeem_retailer_name, registered_date, issued_coupon_code, expired_date, promotion_name, promotion_type, description.',
            'event_status'         => 'The event status you specified is not found.',
            'event_sortby'         => 'The sort by argument you specified is not valid, the valid values are: registered_date, event_name, event_type, description, begin_date, end_date, is_permanent, status.',
            'event_by_retailer_sortby' => 'The sort by argument you specified is not valid, the valid values are: retailer_name, registered_date, event_name, event_type, description, begin_date, end_date, is_permanent, status.',
            'event_type'           => 'The event type you specified is not found.',
            'link_object_type'     => 'The link object type you specified is not found.',
            'link_object_id1'      => 'The link object ID1 you specified is not found.',
            'link_object_id2'      => 'The link object ID2 you specified is not found.',
            'link_object_id3'      => 'The link object ID3 you specified is not found.',
            'link_object_id4'      => 'The link object ID4 you specified is not found.',
            'link_object_id5'      => 'The link object ID5 you specified is not found.',
            'category_id1'         => 'The Category ID1 you specified is not found.',
            'category_id2'         => 'The Category ID2 you specified is not found.',
            'category_id3'         => 'The Category ID3 you specified is not found.',
            'category_id4'         => 'The Category ID4 you specified is not found.',
            'category_id5'         => 'The Category ID5 you specified is not found.',
            'attribute_sortby'     => 'The sort by argument you specified is not valid, valid values are: id, name and created.',
            'attribute'            => 'The product attribute ID you specified is not found.',
            'product_status'       => 'The product status you specified is not found.',
            'product_sortby'       => 'The sort by argument you specified is not valid, the valid values are: registered_date, product_id, product_name, product_sku, product_code, product_upc, product_price, product_short_description, product_long_description, product_is_new, product_new_until, product_merchant_id, product_status.',
            'product_attr'         => array(
                    'attribute'    => array(
                        'value'         => 'The product attribute value ID :id you specified is not found or does not belong to this merchant.',
                        'json_property' => 'Missing property of ":property" on your JSON string.',
                        'variant'       => 'The product combination ID you specified is not found.'
                    ),
            ),
            'upc_code'             => 'The UPC code of the product is not found.',
            'transaction'          => 'The Transaction is not found.',
            'widget'               => 'The Widget ID you specified is not found.',
            'employee'             => array(
                'role'             => 'The role ":role" is not found.',
            ),
            'setting_status'       => 'The setting status you specified is not found.',
            'setting_sortby'       => 'The sort by argument you specified is not valid, the valid values are: registered_date, setting_name, status.',
            'employee_sortby'      => 'The sort by argument you specified is not valid, the valid values are: username, firstname, lastname, registered_date, employee_id_char, position.',
            'posquickproduct'      => 'The pos quick product you specified is not found.',
            'posquickproduct_sortby' => 'The sort by argument you specified is not valid, the valid values are: id, price, name, product_order.',
            'activity_sortby'      => 'The sort by argument you specified is not valid, valid values are: id, ip_address, created, registered_at, email, full_name, object_name, product_name, coupon_name, promotion_name, event_name, action_name, action_name_long, activity_type, gender, staff_name, module_name.',
            'transactionhistory'   => array(
                'merchantlist'     => array(
                    'sortby'       => 'The sort by argument you specified is not valid, the valid values are: name, last_transaction.',
                ),
                'retailerlist'     => array(
                    'sortby'       => 'The sort by argument you specified is not valid, the valid values are: name, last_transaction.',
                ),
                'productlist'      => array(
                    'sortby'       => 'The sort by argument you specified is not valid, the valid values are: name, last_transaction.',
                ),
            ),
        ),
        'queryerror' => 'Database query error, turn on debug mode to see the full query.',
        'jsonerror'  => array(
            'format' => 'The JSON input you specified was not valid.',
            'array'  => 'The JSON input you specified must be in array.',
            'field'  => array(
                'format'    => 'The JSON input of field :field was not valid JSON input.',
                'array'     => 'The JSON input of field :field must be in array.',
                'diffcount' => 'The number of items on field :field are different.',
            ),
        ),
        'formaterror' => array(
            'product_attr' => array(
                'attribute' => array(
                    'value' => array(
                        'price'         => 'The price should be in numeric or decimal.',
                        'count'         => 'The number of value must be 5.',
                        'order'         => 'Invalid attribute order, expected value from attribute `:expect` but got value from attribute `:got`.',
                        'allnull'       => 'All five attribute values cannot be empty at the same time.',
                        'exists'        => 'The attribute combinations you have sent already exist.',
                        'nullprepend'   => 'Empty value must be put after attribute value.',
                        'duplicate'     => 'There is a duplicate of product attribute value.',
                        'notsame'       => 'One or more product combination you sent does not have the same number of order.'
                    ),
                ),
            ),
            'pos_quick_product' => array(
                'array_count'   => 'The number of item should not be more than :number.'
            ),
            'merchant' => array(
                'ticket_header' => array(
                    'max_length' => 'Merchant ticket header max length is 40 characters for each line.'
                ),
                'ticket_footer' => array(
                    'max_length' => 'Merchant ticket footer max length is 40 characters for each line.'
                ),
            ),
            'url'   => array(
                'web'   => 'The URL is not valid. Examples of valid URL are www.example.com or www.example.com/sub/page. No need to include the http:// or https://.'
            )
        ),
        'actionlist' => array(
            'change_password'           => 'change password',
            'add_new_user'              => 'add new user',
            'delete_user'               => 'delete user',
            'delete_your_self'          => 'delete your account',
            'update_user'               => 'update user',
            'view_user'                 => 'view user',
            'new_merchant'              => 'add new merchant',
            'update_merchant'           => 'update merchant',
            'delete_merchant'           => 'delete merchant',
            'view_merchant'             => 'view merchant',
            'new_retailer'              => 'add new retailer',
            'update_retailer'           => 'update retailer',
            'delete_retailer'           => 'delete retailer',
            'view_retailer'             => 'view retailer',
            'new_product'               => 'add new product',
            'update_product'            => 'update product',
            'delete_product'            => 'delete product',
            'view_product'              => 'view product',
            'new_tax'                   => 'add new tax',
            'update_tax'                => 'update tax',
            'delete_tax'                => 'delete tax',
            'view_tax'                  => 'view tax',
            'new_category'              => 'add new category',
            'update_category'           => 'update category',
            'delete_category'           => 'delete category',
            'view_category'             => 'view category',
            'new_promotion'             => 'add new promotion',
            'update_promotion'          => 'update promotion',
            'delete_promotion'          => 'delete promotion',
            'view_promotion'            => 'view promotion',
            'new_product_attribute'     => 'add new product attribute',
            'update_product_attribute'  => 'update product attribute',
            'delete_product_attribute'  => 'delete product attribute',
            'view_product_attribute'    => 'view product attribute',
            'new_coupon'                => 'add new coupon',
            'update_coupon'             => 'update coupon',
            'delete_coupon'             => 'delete coupon',
            'view_coupon'               => 'view coupon',
            'new_issuedcoupon'          => 'add new issued coupon',
            'update_issuedcoupon'       => 'update issued coupon',
            'delete_issuedcoupon'       => 'delete issued coupon',
            'view_issuedcoupon'         => 'view issued coupon',
            'add_new_widget'            => 'add new widget',
            'update_widget'             => 'update widget',
            'delete_widget'             => 'delete widget',
            'view_widget'               => 'view widget',
            'new_event'                 => 'add new event',
            'update_event'              => 'update event',
            'delete_event'              => 'delete event',
            'view_event'                => 'view event',
            'update_setting'            => 'update setting',
            'view_setting'              => 'view setting',
            'new_pos_quick_product'     => 'add new pos quick product',
            'update_pos_quick_product'  => 'update pos quick product',
            'delete_pos_quick_product'  => 'delete pos quick product',
            'view_pos_quick_product'    => 'view pos quick product',
            'view_activity'             => 'view activity',
            'add_new_employee'          => 'add new employee',
            'update_employee'           => 'update employee',
            'delete_employee'           => 'delete employee',
            'view_personal_interest'    => 'view personal interest',
            'view_role'                 => 'view role',
            'view_transaction_history'  => 'view transaction history',
            'shutdown_box'              => 'shutdown or reboot'
        ),
    ),

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute place-holders
    | with something more reader friendly such as E-Mail Address instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */

    'attributes' => array(
    ),

);
