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

    "accepted"             => ":attribute 受け入れなければなりません.",
    "active_url"           => ":attribute 有効なURLではありません.",
    "after"                => ":attribute 日付：後の日付にする必要があります.",
    "alpha"                => ":attribute 文字のみが含まれていてもよいです.",
    "alpha_dash"           => ":attribute 文字、数字、およびダッシュを含めること.",
    "alpha_num"            => ":attribute 文字と数字のみが含まれていてもよいです.",
    "array"                => ":attribute 配列でなければなりません.",
    "before"               => ":attribute 日付：前の日付でなければなりません.",
    "between"              => array(
        "numeric" => ":attribute 分と：最大の間でなければなりません.",
        "file"    => ":attribute 分と：最大の間でなければなりません kilobytes.",
        "string"  => ":attribute 分と：最大の間でなければなりません characters.",
        "array"   => ":attribute 分と：最大の間で持っている必要があります items.",
    ),
    "boolean"              => ":attribute field must be true or false",
    "confirmed"            => ":attribute 確認が一致しません.",
    "date"                 => ":attribute 有効な日付ではありません.",
    "date_format"          => ":attribute フォーマットと一致しません :format.",
    "different"            => ":attribute and :other 異なっている必要があります.",
    "digits"               => ":attribute must be :digits digits.",
    "digits_between"       => ":attribute 分と：最大の間でなければなりません digits.",
    "email"                => ":attribute 有効なメールアドレスでなければなりません.",
    "exists"               => "selected :attribute is invalid.",
    "image"                => ":attribute must be an image.",
    "in"                   => "selected :attribute is invalid.",
    "integer"              => ":attribute must be an integer.",
    "ip"                   => ":attribute must be a valid IP address.",
    "max"                  => array(
        "numeric" => ":attribute may not be greater than :max.",
        "file"    => ":attribute may not be greater than :max kilobytes.",
        "string"  => ":attribute may not be greater than :max characters.",
        "array"   => ":attribute may not have more than :max items.",
    ),
    "mimes"                => ":attribute must be a file of type: :values.",
    "min"                  => array(
        "numeric" => ":attribute must be at least :min.",
        "file"    => ":attribute must be at least :min kilobytes.",
        "string"  => ":attribute must be at least :min characters.",
        "array"   => ":attribute must have at least :min items.",
    ),
    "not_in"               => "selected :attribute is invalid.",
    "numeric"              => ":attribute must be a number.",
    "regex"                => ":attribute format is invalid.",
    "required"             => ":attribute フィールドは必須項目です.",
    "required_if"          => ":attribute フィールドがときに必要な :other is :value.",
    "required_with"        => ":attribute フィールドがときに必要な :values is present.",
    "required_with_all"    => ":attribute フィールドがときに必要な :values is present.",
    "required_without"     => ":attribute フィールドがときに必要な :values is not present.",
    "required_without_all" => ":attribute フィールドがときに必要な none of :values are present.",
    "same"                 => ":attribute and :other 一致している必要があります.",
    "size"                 => array(
        "numeric" => ":attribute である必要があります。サイズ.",
        "file"    => ":attribute である必要があります。サイズ kilobytes.",
        "string"  => ":attribute である必要があります。サイズ characters.",
        "array"   => ":attribute 含まれている必要があります :size items.",
    ),
    "unique"               => ":attribute すでに使用されている.",
    "url"                  => ":attribute 形式が無効です.",
    "timezone"             => ":attribute 有効なゾーンである必要があります.",

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
            'exists' => 'email address すでに使用されている.',
        ),
        'exists' => array(
            'username'              => 'username すでに使用されている.',
            'email'                 => 'Email address すでに使用されている.',
            'omid'                  => 'OMID すでに使用されている by another Merchant.',
            'orid'                  => 'ORID すでに使用されている by another Retailer.',
            'category_name'         => 'category name has already been used.',
            'have_product_category' => 'family cannot be deleted: One or more products are attached to this family.',
            'product_have_transaction' => 'product has one or more transactions linked to it, so it cannot be deleted.',
            'promotion_name'        => 'promotion name has already been used.',
            'coupon_name'           => 'coupon name has already been used.',
            'issued_coupon_code'    => 'coupon code has been redeemed.',
            'event_name'            => 'event name has already been used.',
            'tax_name'              => 'tax name has already been used.',
            'tax_link_to_product'   => 'tax cannot be deleted: One or more products are attached to this tax.',
            'product'               => array(
                'attribute'         => array(
                    'unique'        => 'attribute name \':attrname\' already exists.',
                    'value'         => array(
                        'transaction'   => 'attribute combination has one or more transactions linked to it, so it cannot be edited or deleted.',
                        'unique'        => 'attribute value \':value\' already exists.'
                    ),
                ),
                'variant'           => array(
                    'transaction'   => 'Product combination ID :id has one or more transactions linked to it, so it cannot be edited or deleted.'
                ),
                'upc_code'          => 'UPC :upc has already been used by other product.',
                'sku_code'          => 'SKU :sku has already been used by other product.',
                'transaction'       => 'Product \':name\' has one or more transactions linked to it, so it cannot be edited or deleted.'
            ),
            'product_attribute_have_transaction'           => 'product attribute has one or more transactions linked to it, so it cannot be edited or deleted.',
            'product_attribute_value_have_transaction'     => 'product attribute value has one or more transactions linked to it, so it cannot be edited or deleted.',
            'product_attribute_have_product'               => 'product attribute has one or more products linked to it, so it cannot be edited or deleted.',
            'product_attribute_value_have_product_variant' => 'product attribute value has one or more product variants linked to it, so it cannot be edited or deleted.',
            'employeeid'            => 'employee ID is not available.',
            'widget_type'           => 'Another widget with the same widget type already exists',
            'merchant_have_retailer' => 'merchant has one or more retailers linked to it, so it cannot be deleted.',
            'merchant_retailers_is_box_current_retailer' => 'merchant status cannot be set to inactive, because one of its retailers is set to box current retailer.',
            'deleted_retailer_is_box_current_retailer' => 'retailer cannot be deleted, because is set to box current retailer.',
            'inactive_retailer_is_box_current_retailer' => 'retailer status cannot be set to inactive, because is set to box current retailer.',
            'lucky_draw_name'        => 'lucky draw name has already been used.',
            'lucky_draw_active'      => 'Only one lucky draw campaign can be active at the same time.',
            'news_name'              => 'news name has already been used.',
            'mall_have_tenant'       => 'The mall has one or more tenants linked to it, so it cannot be deleted.',
            'mallgroup_have_mall'    => 'The mall group has one or more mall linked to it, so it cannot be deleted.',
            'tenant_id'              => 'The tenant id has already exists.',
            'tenant_on_inactive_have_linked'    => 'Tenant can not be deactivated, because it has links.',
        ),
        'access' => array(
            'forbidden'              => 'あなたがする権限がありません :action.',
            'needtologin'            => 'このページを表示するにはログインする必要があり.',
            'loginfailed'            => 'メールアドレスまたはパスワードが正しくありません。.',
            'tokenmissmatch'         => 'CSRF保護トークンmissmatch.',
            'wrongpassword'          => 'パスワードが正しくありません。.',
            'old_password_not_match' => '古いパスワードが正しくありません。.',
            'view_activity'          => 'あなたは、ビュー・アクティビティへのアクセスを持っていませ.',
            'view_personal_interest' => 'あなたは個人的な興味を表示するアクセス権を持っていません.',
            'view_role'              => 'あなたは役割を表示するためのアクセス権を持っていません.',
            'inactiveuser'           => 'あなたは、要求されたリソースへのアクセスを持っていません.',
            'agreement'              => 'Agreement is not accepted yet',
        ),
        'empty' => array(
            'status_link_to'       => 'The Link To must be Y or N.',
            'role'                 => 'Role ID あなたが見つかりません指定され.',
            'consumer_role'        => 'Consumer role does not exist.',
            'token'                => 'Token あなたが見つかりません指定され.',
            'user'                 => 'User ID あなたが見つかりません指定され.',
            'merchant'             => 'Merchant ID あなたが見つかりません指定され.',
            'retailer'             => 'Retailer ID あなたが見つかりません指定され.',
            'tenant'               => 'Tenant ID あなたが見つかりません指定され.',
            'product'              => 'Product ID あなたが見つかりません指定され.',
            'category'             => 'Category ID あなたが見つかりません指定され.',
            'tax'                  => 'Tax ID あなたが見つかりません指定され.',
            'promotion'            => 'Promotion ID あなたが見つかりません指定され.',
            'coupon'               => 'Coupon ID あなたが見つかりません指定され.',
            'issued_coupon'        => 'Issued Coupon ID あなたが見つかりません指定され.',
            'event'                => 'Event ID あなたが見つかりません指定され.',
            'news'                 => 'The News ID you specified is not found.',
            'event_translations'   => 'The Event Translation ID 見つかりません.',
            'merchant_language'    => 'The Merchant_Language ID 未找到.',
            'language_default'     => 'The language default you specified is not found.',
            'user_status'          => 'user status あなたが見つかりません指定され.',
            'user_sortby'          => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: status, total_lucky_draw_number, total_usable_coupon, total_redeemed_coupon, username, email, firstname, lastname, registered_date, gender, city, last_visit_shop, last_visit_date, last_spent_amount, mobile_phone, membership_number, membership_since, created_at, updated_at.',
            'merchant_status'      => 'merchant status あなたが見つかりません指定され.',
            'merchant_sortby'      => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: registered_date, merchant_name, merchant_email, merchant_userid, merchant_description, merchantid, merchant_address1, merchant_address2, merchant_address3, merchant_cityid, merchant_city, merchant_countryid, merchant_country, merchant_phone, merchant_fax, merchant_status, merchant_currency, start_date_activity, total_retailer.',
            'retailer_status'      => 'retailer status あなたが見つかりません指定され.',
            'retailer_sortby'      => 'sort by argument for retailer you specified is not valid, 有効な値は、: orid, registered_date, retailer_name, retailer_email, retailer_userid, retailer_description, retailerid, retailer_address1, retailer_address2, retailer_address3, retailer_cityid, retailer_city, retailer_countryid, retailer_country, retailer_phone, retailer_fax, retailer_status, retailer_currency, contact_person_firstname, merchant_name, retailer_floor, retailer_unit, retailer_external_object_id, retailer_created_at, retailer_updated_at.',
            'tax_status'           => 'tax status あなたが見つかりません指定され.',
            'tax_sortby'           => 'あなたは指定された引数でソートが有効ではありません, 有効な値は、: registered_date, merchant_tax_id, tax_name, tax_type, tax_value, tax_order.',
            'tax_type'             => 'tax type あなたが見つかりません指定され. Valid values are: government, service, luxury.',
            'category_status'      => 'category status あなたが見つかりません指定され.',
            'category_sortby'      => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: registered_date, category_name, category_level, category_order, description, status.',
            'promotion_status'     => 'promotion status あなたが見つかりません指定され.',
            'promotion_sortby'     => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status, rule_type, display_discount_value.',
            'promotion_by_retailer_sortby' => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: retailer_name, registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status.',
            'promotion_type'       => 'promotion type あなたが見つかりません指定され.',
            'rule_type'            => 'rule type あなたが見つかりません指定され.',
            'rule_object_type'     => 'rule object type あなたが見つかりません指定され.',
            'rule_object_id1'      => 'rule object ID1 あなたが見つかりません指定され.',
            'rule_object_id2'      => 'rule object ID2 あなたが見つかりません指定され.',
            'rule_object_id3'      => 'rule object ID3 あなたが見つかりません指定され.',
            'rule_object_id4'      => 'rule object ID4 あなたが見つかりません指定され.',
            'rule_object_id5'      => 'rule object ID5 あなたが見つかりません指定され.',
            'discount_object_type' => 'discount object type あなたが見つかりません指定され.',
            'discount_object_id1'  => 'discount object ID1 あなたが見つかりません指定され.',
            'discount_object_id2'  => 'discount object ID2 あなたが見つかりません指定され.',
            'discount_object_id3'  => 'discount object ID3 あなたが見つかりません指定され.',
            'discount_object_id4'  => 'discount object ID4 あなたが見つかりません指定され.',
            'discount_object_id5'  => 'discount object ID5 あなたが見つかりません指定され.',
            'coupon_status'        => 'coupon status あなたが見つかりません指定され.',
            'coupon_sortby'        => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status, rule_type, display_discount_value.',
            'coupon_by_issue_retailer_sortby' => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: issue_retailer_name, registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status.',
            'coupon_type'          => 'coupon type あなたが見つかりません指定され.',
            'issued_coupon_status' => 'issued coupon status あなたが見つかりません指定され.',
            'issued_coupon_sortby' => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: registered_date, issued_coupon_code, expired_date, issued_date, redeemed_date, status.',
            'issued_coupon_by_retailer_sortby' => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: redeem_retailer_name, registered_date, issued_coupon_code, expired_date, promotion_name, promotion_type, description.',
            'supported_language_status' => 'あなたが指定したサポートされている言語のステータスが見つかりません.',
            'event_status'         => 'event status あなたが見つかりません指定され.',
            'event_sortby'         => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: registered_date, event_name, event_type, description, begin_date, end_date, is_permanent, status.',
            'event_by_retailer_sortby' => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: retailer_name, registered_date, event_name, event_type, description, begin_date, end_date, is_permanent, status.',
            'event_type'           => 'event type あなたが見つかりません指定され.',
            'link_object_type'     => 'link object type あなたが見つかりません指定され.',
            'link_object_id1'      => 'link object ID1 あなたが見つかりません指定され.',
            'link_object_id2'      => 'link object ID2 あなたが見つかりません指定され.',
            'link_object_id3'      => 'link object ID3 あなたが見つかりません指定され.',
            'link_object_id4'      => 'link object ID4 あなたが見つかりません指定され.',
            'link_object_id5'      => 'link object ID5 あなたが見つかりません指定され.',
            'category_id1'         => 'Category ID1 あなたが見つかりません指定され.',
            'category_id2'         => 'Category ID2 あなたが見つかりません指定され.',
            'category_id3'         => 'Category ID3 あなたが見つかりません指定され.',
            'category_id4'         => 'Category ID4 あなたが見つかりません指定され.',
            'category_id5'         => 'Category ID5 あなたが見つかりません指定され.',
            'attribute_sortby'     => 'sort by argument you specified is not valid, valid values are: id, name and created.',
            'attribute'            => 'product attribute ID あなたが見つかりません指定され.',
            'product_status'       => 'product status あなたが見つかりません指定され.',
            'product_sortby'       => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: registered_date, product_id, product_name, product_sku, product_code, product_upc, product_price, product_short_description, product_long_description, product_is_new, product_new_until, product_merchant_id, product_status.',
            'product_attr'         => array(
                    'attribute'    => array(
                        'value'         => 'product attribute value ID :id あなたが見つかりません指定され or does not belong to this merchant.',
                        'json_property' => 'Missing property of ":property" on your JSON string.',
                        'variant'       => 'product combination ID あなたが見つかりません指定され.'
                    ),
            ),
            'upc_code'             => 'UPC code of the product is not found.',
            'transaction'          => 'Transaction is not found.',
            'widget'               => 'Widget ID あなたが見つかりません指定され.',
            'employee'             => array(
                'role'             => 'role ":role" is not found.',
            ),
            'setting_status'       => 'setting status あなたが見つかりません指定され.',
            'setting_sortby'       => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: registered_date, setting_name, status.',
            'employee_sortby'      => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: username, firstname, lastname, registered_date, employee_id_char, position.',
            'posquickproduct'      => 'pos quick product あなたが見つかりません指定され.',
            'posquickproduct_sortby' => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: id, price, name, product_order.',
            'activity_sortby'      => 'sort by argument you specified is not valid, valid values are: id, ip_address, created, registered_at, email, full_name, object_name, product_name, coupon_name, promotion_name, news_name, promotion_news_name, event_name, action_name, action_name_long, activity_type, gender, staff_name, module_name, retailer_name.',
            'transactionhistory'   => array(
                'merchantlist'     => array(
                    'sortby'       => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: name, last_transaction.',
                ),
                'retailerlist'     => array(
                    'sortby'       => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: name, last_transaction.',
                ),
                'productlist'      => array(
                    'sortby'       => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: name, last_transaction.',
                ),
            ),
            'lucky_draw'           => 'lucky draw あなたが見つかりません指定され.',
            'lucky_draw_status'    => 'lucky draw status あなたが見つかりません指定され.',
            'lucky_draw_sortby'    => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: registered_date, lucky_draw_name, description, start_date, end_date, status, external_lucky_draw_id.',
            'lucky_draw_number_receipt' => 'lucky draw number receipt あなたが見つかりません指定され.',
            'lucky_draw_number_receipt_status' => 'lucky draw number receipt status あなたが見つかりません指定され.',
            'lucky_draw_number_receipt_sortby' => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: lucky_draw_number, lucky_draw_id, user_id.',
            'lucky_draw_number' => 'lucky draw number あなたが見つかりません指定され.',
            'lucky_draw_number_status' => 'lucky draw number status あなたが見つかりません指定され.',
            'lucky_draw_number_sortby' => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: lucky_draw_number, lucky_draw_id, user_id.',
            'news_object_type'     => 'news object type あなたが見つかりません指定され. Valid value: promotion, news',
            'news_status'          => 'news status あなたが見つかりません指定され.',
            'news_sortby'          => 'あなたは指定された引数によって並べ替えが有効でない、有効な値は、: registered_date, news_name, object_type, description, begin_date, end_date, status',
            'news'                 => 'News ID あなたが見つかりません指定され.',
            'link_object_id'       => 'Link Object ID あなたが見つかりません指定され.',
            'bank_object'          => 'Bank Object ID あなたが見つかりません指定され.',
            'mall'                 => 'Mall ID あなたが見つかりません指定され.',
            'language' => 'Language ID あなたが見つかりません指定され.',
            'merchant_language' => 'Merchant Language ID あなたが見つかりません指定され.',
            'hour_format'          => ':attribute 有効な日付ではありません.',
            'tenant_floor'          => 'Floor is required',
            'tenant_unit'          => 'Unit is required',
            'lucky_draw_announcement' => 'The lucky draw announcement you specified is not found',
        ),
        'queryerror' => 'Database query error, turn on debug mode to see the full query.',
        'jsonerror'  => array(
            'format' => 'JSON input you specified was not valid.',
            'array'  => 'JSON input you specified must be in array.',
            'field'  => array(
                'format'    => 'JSON input of field :field was not valid JSON input.',
                'array'     => 'JSON input of field :field must be in array.',
                'diffcount' => 'number of items on field :field are different.',
            ),
        ),
        'formaterror' => array(
            'product_attr' => array(
                'attribute' => array(
                    'value' => array(
                        'price'         => '価格は数値または小数にする必要があります.',
                        'count'         => '値の数が5である必要があります.',
                        'order'         => '無効な属性の順序、`属性からの期待値：expect`しかし`属性から得た値：got`.',
                        'allnull'       => 'すべての5つの属性値が同時に空にすることはできません.',
                        'exists'        => 'あなたが送信した属性の組み合わせが既に存在しています.',
                        'nullprepend'   => '空の値は、属性値の後に置く必要があります.',
                        'duplicate'     => '製品の属性値の重複があります.',
                        'notsame'       => 'あなたが送信された1つ以上の製品の組み合わせは、オードの同じ番号を持っていませんr.'
                    ),
                ),
            ),
            'pos_quick_product' => array(
                'array_count'   => 'アイテムの数がより多くてはなりません :number.'
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
            ),
            'translation' => array(
                'key' => '翻訳のための無効なキーが指定されました.',
                'value' => '翻訳のための無効な値が指定されました.',
            ),
            'date' => array(
                'dmy_date' => 'The :attribute does not match the format dd-mm-yyyy',
                'cannot_future_date' => '誕生日は将来の日付にすることはできません',
                'invalid_date' => '生年月日は有効な日付ではありません',
            ),
        ),
        'actionlist' => array(
            'change_password'           => 'パスワードを変更する',
            'add_new_user'              => '新しいユーザーを追加します',
            'delete_user'               => 'ユーザーを削除します',
            'delete_your_self'          => 'アカウントの削除',
            'update_user'               => '更新ユーザー',
            'view_user'                 => 'ビューユーザー',
            'new_merchant'              => '新しい商人を追加',
            'update_merchant'           => '更新商人',
            'delete_merchant'           => '商人を削除',
            'view_merchant'             => 'ビュー商人',
            'new_retailer'              => '新しい小売店を追加',
            'update_retailer'           => '更新小売店',
            'delete_retailer'           => '小売店を削除',
            'view_retailer'             => 'ビュー小売店',
            'new_product'               => '新しい製品を追加します',
            'update_product'            => '更新製品',
            'delete_product'            => '商品を削除します',
            'view_product'              => 'ビュー製品',
            'new_tax'                   => '新しい税を追加',
            'update_tax'                => '税金を更新',
            'delete_tax'                => '税金を削除します',
            'view_tax'                  => 'ビュー税',
            'new_category'              => '新しいカテゴリを追加',
            'update_category'           => '更新カテゴリー',
            'delete_category'           => 'カテゴリを削除',
            'view_category'             => 'ビューカテゴリ',
            'new_promotion'             => '新しいプロモーションを追加',
            'update_promotion'          => '更新プロモーション',
            'delete_promotion'          => 'プロモーションを削除します',
            'view_promotion'            => 'ビュープロモーション',
            'new_product_attribute'     => '新製品の属性を追加',
            'update_product_attribute'  => '更新製品の属性',
            'delete_product_attribute'  => '製品の属性を削除します',
            'view_product_attribute'    => 'ビュー製品の属性',
            'new_coupon'                => '新しいクーポンを追加',
            'update_coupon'             => '更新クーポン',
            'delete_coupon'             => 'クーポンを削除',
            'view_coupon'               => 'ビュークーポン',
            'new_issuedcoupon'          => '新しい発行クーポンを追加',
            'update_issuedcoupon'       => 'アップデート発行クーポン',
            'delete_issuedcoupon'       => '発行されたクーポンを削除',
            'view_issuedcoupon'         => 'ビュー発行クーポン',
            'add_new_widget'            => '新しいウィジェットを追加',
            'update_widget'             => '更新ウィジェット',
            'delete_widget'             => 'ウィジェットを削除します',
            'view_widget'               => 'ビューウィジェット',
            'new_event'                 => '新しいイベントを追加',
            'update_event'              => '更新イベント',
            'delete_event'              => 'イベントを削除します',
            'view_event'                => 'ビューイベント',
            'update_setting'            => '更新設定',
            'view_setting'              => 'ビュー設定',
            'new_pos_quick_product'     => '新しいPOSと素早く製品を追加',
            'update_pos_quick_product'  => '更新は、迅速な製品をposに',
            'delete_pos_quick_product'  => 'POS迅速な製品を削除',
            'view_pos_quick_product'    => 'ビューposの迅速な製品',
            'view_activity'             => 'ビュー・アクティビティ',
            'add_new_employee'          => '新しい従業員を追加します',
            'update_employee'           => '更新従業員',
            'delete_employee'           => '従業員を削除します',
            'view_personal_interest'    => '個人的な興味を表示',
            'view_role'                 => 'ビューの役割',
            'view_transaction_history'  => 'ビューの取引履歴',
            'shutdown_box'              => 'シャットダウンや再起動',
            'new_lucky_draw'            => '新しい抽選を追加',
            'update_lucky_draw'         => '抽選を更新',
            'delete_lucky_draw'         => '抽選を削除',
            'view_lucky_draw'           => '抽選を表示'
        ),
        'max' => array(
            'total_issued_coupons' => 'ja:Number can not be less than current total issued coupons',
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
