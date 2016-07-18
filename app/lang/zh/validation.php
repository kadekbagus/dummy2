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

    "accepted"             => " :attribute 必须被接受",
    "active_url"           => " :attribute 是不是一个有效的URL",
    "after"                => " :attribute 必须是后一个日期：日期",
    "alpha"                => " :attribute 可能只包含字母",
    "alpha_dash"           => " :attribute 只能包含字母，数字和破折号",
    "alpha_num"            => " :attribute 只能包含字母和数字",
    "array"                => " :attribute 必须是一个数组",
    "before"               => " :attribute 必须是前一个日期：日期",
    "between"              => array(
        "numeric" => " :attribute 必须在：分钟：最大",
        "file"    => " :attribute 必须在：分钟：最大千字节",
        "string"  => " :attribute 必须在：分钟：最大字符",
        "array"   => " :attribute 必须有间：最小和：最大项目",
    ),
    "boolean"              => " :attribute 字段必须是真的还是假的",
    "confirmed"            => " :attribute 确认不匹配",
    "date"                 => " :attribute 是不是有效日期",
    "date_format"          => " :attribute 不匹配的格式：格式",
    "different"            => " ：和：对方必须是不同的",
    "digits"               => " :attribute 必须是：数字位数",
    "digits_between"       => " :attribute 必须在：分钟：最高位",
    "email"                => " :attribute 必须是一个有效的电子邮件地址",
    "exists"               => " ：无效",
    "image"                => " :attribute 必须是图片",
    "in"                   => " ：无效",
    "integer"              => " :attribute 必须是整数",
    "ip"                   => " :attribute 必须是一个有效的IP地址",
    "max"                  => array(
        "numeric" => " ：可能不大于：最大",
        "file"    => " ：可能不大于：最大千字节",
        "string"  => " :attribute 不得超过：MAX个字符",
        "array"   => " :attribute 可能没有超过：最大项目",
    ),
    "mimes"                => " :attribute 必须是文件的类型：值",
    "min"                  => array(
        "numeric" => " :attribute 必须至少为：分",
        "file"    => " :attribute 必须至少为：分千字节",
        "string"  => " :attribute 必须至少为：最小字符",
        "array"   => " :attribute 必须至少有：分项目",
    ),
    "not_in"               => " :attribute 是无效的",
    "numeric"              => " :attribute 必须是一个数字",
    "regex"                => " :attribute 格式无效",
    "required"             => " :attribute 字段是必须的",
    "required_if"          => " :attribute 场时，需要：另一种是：价值",
    "required_with"        => " :attribute 场时，需要：值存在",
    "required_with_all"    => " :attribute 场时，需要：值存在",
    "required_without"     => " :attribute 值不存在：当字段是必须的",
    "required_without_all" => " :attribute 值存在：当不字段是必须的",
    "same"                 => " :attribute 和：等必须匹配",
    "size"                 => array(
        "numeric" => " :attribute 必须是：大小",
        "file"    => " :attribute 必须是：大小千字节",
        "string"  => " :attribute 必须是：大小的字符",
        "array"   => " :attribute 必须包含：大小项目",
    ),
    "unique"               => " :attribute 已有人带走了",
    "url"                  => " :attribute 格式无效",
    "timezone"             => " :attribute 必须是有效的区",

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
            'exists' => '该电子邮件地址已经被采取',
        ),
        'exists' => array(
            'username'              => '用户名已被占用',
            'email'                 => '电子邮件地址已被占用',
            'omid'                  => 'OMID已经采取的又一商人',
            'orid'                  => 'ORID已经采取的又一零售商',
            'category_name'         => '类别名称已被使用',
            'have_product_category' => '家庭无法删除：一个或多个产品连接到这个家庭',
            'product_have_transaction' => '该产品具有一个或多个链接到它的交易，因此它不能被删除',
            'promotion_name'        => '推广名已经被使用',
            'coupon_name'           => '优惠券的名字已被使用',
            'issued_coupon_code'    => '优惠券代码已被赎回',
            'event_name'            => '事件名称已被使用',
            'tax_name'              => '税务名已被使用',
            'tax_link_to_product'   => '该税不能被删除：一个或多个产品连接到这个税',
            'product'               => array(
                'attribute'         => array(
                    'unique'        => 'attribute 名字 \':attrname\' 已经存在',
                    'value'         => array(
                        'transaction'   => 'attribute 组合具有一个或多个链接到它的交易，因此它不能被编辑或删除',
                        'unique'        => 'attribute 值 \':value\' 已经存在.'
                    ),
                ),
                'variant'           => array(
                ),
                'transaction'   => '产品组合 ID :id 具有一个或链接到它的更多的事务，因此它不能被编辑或删除',
                'upc_code'          => 'UPC :upc 已被使用的其它产品的',
                'sku_code'          => 'SKU :sku 已被使用的其它产品的',
                'transaction'       => '积 \':名字\' 具有一个或链接到它的更多的事务，因此它不能被编辑或删除'
            ),
            'product_attribute_have_transaction'           => ' 产品 attribute 具有一个或链接到它的更多的事务，因此它不能被编辑或删除',
            'product_attribute_value_have_transaction'     => ' 产品 attribute 值具有一个或一个以上链接到它的交易数据，所以它不能被编辑或删除',
            'product_attribute_have_product'               => ' 产品 attribute 有一个或多个产品链接到它，因此它不能被编辑或删除',
            'product_attribute_value_have_product_variant' => ' 产品 attribute 值具有链接到它的一个或多个产品的变体，因此它不能被编辑或删除',
            'employeeid'            => ' 雇员 ID 不可',
            'widget_type'           => '与同类型的小部件另一个工具已经存在',
            'merchant_have_retailer' => ' 商家具有一个或多个与它连接的零售商，因此它不能被删除',
            'merchant_retailers_is_box_current_retailer' => ' 商人地位不能设置为无效，因为它的零售商之一设置框当前零售商',
            'deleted_retailer_is_box_current_retailer' => ' 零售商无法删除，因为被设置到框当前零售商',
            'inactive_retailer_is_box_current_retailer' => ' 零售商的状态不能设置为无效，因为设置框当前零售商',
            'lucky_draw_name'        => ' 幸运抽奖的名字已经被使用',
            'lucky_draw_active'      => '只有一个抽奖活动可以在同一时间被激活',
            'news_name'              => ' 消息名称已被使用',
            'mall_have_tenant'       => 'The mall has one or more tenants linked to it, so it cannot be deleted.',
            'mallgroup_have_mall'    => 'The mall group has one or more mall linked to it, so it cannot be deleted.',
            'tenant_id'              => 'The tenant id has already exists.',
            'tenant_on_inactive_have_linked'    => 'Tenant can not be deactivated, because it has links.',
        ),
        'access' => array(
            'forbidden'              => '您没有权限：动作',
            'needtologin'            => '你必须登录才能查看此页',
            'loginfailed'            => '您的邮箱或密码不正确。',
            'tokenmissmatch'         => 'CSRF保护令牌 错过比赛',
            'wrongpassword'          => '密码不正确',
            'old_password_not_match' => '旧密码不正确',
            'view_activity'          => '您没有权限查看活动',
            'view_personal_interest' => '您没有权限查看个人利益',
            'view_role'              => '您没有权限查看角色',
            'inactiveuser'           => '你没有访问所请求的资源',
            'agreement'              => 'Agreement is not accepted yet',
        ),
        'empty' => array(
            'status_link_to'       => 'The Link To must be Y or N.',
            'role'                 => ' Role ID 您指定未找到.',
            'consumer_role'        => ' Consumer role 不存在',
            'token'                => ' Token 您指定未找到.',
            'user'                 => ' User ID 您指定未找到.',
            'merchant'             => ' Merchant ID 您指定未找到.',
            'retailer'             => ' Retailer ID 您指定未找到.',
            'tenant'               => ' Tenant ID 您指定未找到.',
            'product'              => ' Product ID 您指定未找到.',
            'category'             => ' Category ID 您指定未找到.',
            'tax'                  => ' Tax ID 您指定未找到.',
            'promotion'            => ' Promotion ID 您指定未找到.',
            'coupon'               => ' Coupon ID 您指定未找到.',
            'issued_coupon'        => ' Issued Coupon ID 您指定未找到.',
            'event'                => ' Event ID 您指定未找到.',
            'news'                 => 'The News ID you specified is not found.',
            'event_translations'   => 'The Event Translation ID 未找到.',
            'merchant_language'    => 'The Merchant_Language ID 未找到.',
            'language_default'     => 'The language default you specified is not found.',
            'user_status'          => ' user status 您指定未找到.',
            'user_sortby'          => ' 排序您指定的参数无效，则有效值为: status, total_lucky_draw_number, total_usable_coupon, total_redeemed_coupon, username, email, firstname, lastname, registered_date, gender, city, last_visit_shop, last_visit_date, last_spent_amount, mobile_phone, membership_number, membership_since, created_at, updated_at.',
            'merchant_status'      => ' merchant status 您指定未找到.',
            'merchant_sortby'      => ' 排序您指定的参数无效，则有效值为: registered_date, merchant_name, merchant_email, merchant_userid, merchant_description, merchantid, merchant_address1, merchant_address2, merchant_address3, merchant_cityid, merchant_city, merchant_countryid, merchant_country, merchant_phone, merchant_fax, merchant_status, merchant_currency, start_date_activity, total_retailer.',
            'retailer_status'      => ' retailer status 您指定未找到.',
            'retailer_sortby'      => ' 排序论据 for retailer you specified is not valid, the valid values are: orid, registered_date, retailer_name, retailer_email, retailer_userid, retailer_description, retailerid, retailer_address1, retailer_address2, retailer_address3, retailer_cityid, retailer_city, retailer_countryid, retailer_country, retailer_phone, retailer_fax, retailer_status, retailer_currency, contact_person_firstname, merchant_name, retailer_floor, retailer_unit, retailer_external_object_id, retailer_created_at, retailer_updated_at.',
            'tax_status'           => ' tax status 您指定未找到.',
            'tax_sortby'           => ' 排序论据 for tax you specified is not valid, the valid values are: registered_date, merchant_tax_id, tax_name, tax_type, tax_value, tax_order.',
            'tax_type'             => ' tax type 您指定未找到. Valid values are: government, service, luxury.',
            'category_status'      => ' category status 您指定未找到.',
            'category_sortby'      => ' 排序您指定的参数无效，则有效值为: registered_date, category_name, category_level, category_order, description, status.',
            'promotion_status'     => ' promotion status 您指定未找到.',
            'promotion_sortby'     => ' 排序您指定的参数无效，则有效值为: registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status, rule_type, display_discount_value.',
            'promotion_by_retailer_sortby' => ' 排序您指定的参数无效，则有效值为: retailer_name, registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status.',
            'promotion_type'       => ' promotion type 您指定未找到.',
            'rule_type'            => ' rule type 您指定未找到.',
            'rule_object_type'     => ' rule object type 您指定未找到.',
            'rule_object_id1'      => ' rule object ID1 您指定未找到.',
            'rule_object_id2'      => ' rule object ID2 您指定未找到.',
            'rule_object_id3'      => ' rule object ID3 您指定未找到.',
            'rule_object_id4'      => ' rule object ID4 您指定未找到.',
            'rule_object_id5'      => ' rule object ID5 您指定未找到.',
            'discount_object_type' => ' discount object type 您指定未找到.',
            'discount_object_id1'  => ' discount object ID1 您指定未找到.',
            'discount_object_id2'  => ' discount object ID2 您指定未找到.',
            'discount_object_id3'  => ' discount object ID3 您指定未找到.',
            'discount_object_id4'  => ' discount object ID4 您指定未找到.',
            'discount_object_id5'  => ' discount object ID5 您指定未找到.',
            'coupon_status'        => ' coupon status 您指定未找到.',
            'coupon_sortby'        => ' 排序您指定的参数无效，则有效值为: registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status, rule_type, display_discount_value.',
            'coupon_by_issue_retailer_sortby' => ' 排序您指定的参数无效，则有效值为: issue_retailer_name, registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status.',
            'coupon_type'          => ' coupon type 您指定未找到.',
            'issued_coupon_status' => ' issued coupon status 您指定未找到.',
            'issued_coupon_sortby' => ' 排序您指定的参数无效，则有效值为: registered_date, issued_coupon_code, expired_date, issued_date, redeemed_date, status.',
            'issued_coupon_by_retailer_sortby' => ' 排序您指定的参数无效，则有效值为: redeem_retailer_name, registered_date, issued_coupon_code, expired_date, promotion_name, promotion_type, description.',
            'supported_language_status' => '您指定的支持语言的地位没有找到.',
            'event_status'         => ' event status 您指定未找到.',
            'event_sortby'         => ' 排序您指定的参数无效，则有效值为: registered_date, event_name, event_type, description, begin_date, end_date, is_permanent, status.',
            'event_by_retailer_sortby' => ' 排序您指定的参数无效，则有效值为: retailer_name, registered_date, event_name, event_type, description, begin_date, end_date, is_permanent, status.',
            'event_type'           => ' event type 您指定未找到.',
            'link_object_type'     => ' link object type 您指定未找到.',
            'link_object_id1'      => ' link object ID1 您指定未找到.',
            'link_object_id2'      => ' link object ID2 您指定未找到.',
            'link_object_id3'      => ' link object ID3 您指定未找到.',
            'link_object_id4'      => ' link object ID4 您指定未找到.',
            'link_object_id5'      => ' link object ID5 您指定未找到.',
            'category_id1'         => ' Category ID1 您指定未找到.',
            'category_id2'         => ' Category ID2 您指定未找到.',
            'category_id3'         => ' Category ID3 您指定未找到.',
            'category_id4'         => ' Category ID4 您指定未找到.',
            'category_id5'         => ' Category ID5 您指定未找到.',
            'attribute_sortby'     => ' 排序论据 you specified is not valid, valid values are: id, name and created.',
            'attribute'            => ' product attribute ID 您指定未找到.',
            'product_status'       => ' product status 您指定未找到.',
            'product_sortby'       => ' 排序您指定的参数无效，则有效值为: registered_date, product_id, product_name, product_sku, product_code, product_upc, product_price, product_short_description, product_long_description, product_is_new, product_new_until, product_merchant_id, product_status.',
            'product_attr'         => array(
                    'attribute'    => array(
                        'value'         => ' product attribute value ID :id 您指定未找到 or does not belong to this merchant.',
                        'json_property' => 'Missing property of ":property" on your JSON string.',
                        'variant'       => ' product combination ID 您指定未找到.'
                    ),
            ),
            'upc_code'             => ' UPC code of the product is not found.',
            'transaction'          => ' Transaction is not found.',
            'widget'               => ' Widget ID 您指定未找到.',
            'employee'             => array(
                'role'             => ' role ":role" is not found.',
            ),
            'setting_status'       => ' setting status 您指定未找到.',
            'setting_sortby'       => ' 排序您指定的参数无效，则有效值为: registered_date, setting_name, status.',
            'employee_sortby'      => ' 排序您指定的参数无效，则有效值为: username, firstname, lastname, registered_date, employee_id_char, position.',
            'posquickproduct'      => ' pos quick product 您指定未找到.',
            'posquickproduct_sortby' => ' 排序您指定的参数无效，则有效值为: id, price, name, product_order.',
            'activity_sortby'      => ' 排序论据 you specified is not valid, valid values are: id, ip_address, created, registered_at, email, full_name, object_name, product_name, coupon_name, promotion_name, news_name, promotion_news_name, event_name, action_name, action_name_long, activity_type, gender, staff_name, module_name, retailer_name.',
            'transactionhistory'   => array(
                'merchantlist'     => array(
                    'sortby'       => ' 排序您指定的参数无效，则有效值为: name, last_transaction.',
                ),
                'retailerlist'     => array(
                    'sortby'       => ' 排序您指定的参数无效，则有效值为: name, last_transaction.',
                ),
                'productlist'      => array(
                    'sortby'       => ' 排序您指定的参数无效，则有效值为: name, last_transaction.',
                ),
            ),
            'lucky_draw'           => ' lucky draw 您指定未找到.',
            'lucky_draw_status'    => ' lucky draw status 您指定未找到.',
            'lucky_draw_sortby'    => ' 排序您指定的参数无效，则有效值为: registered_date, lucky_draw_name, description, start_date, end_date, status, external_lucky_draw_id.',
            'lucky_draw_number_receipt' => ' lucky draw number receipt 您指定未找到.',
            'lucky_draw_number_receipt_status' => ' lucky draw number receipt status 您指定未找到.',
            'lucky_draw_number_receipt_sortby' => ' 排序您指定的参数无效，则有效值为: lucky_draw_number, lucky_draw_id, user_id.',
            'lucky_draw_number' => ' lucky draw number 您指定未找到.',
            'lucky_draw_number_status' => ' lucky draw number status 您指定未找到.',
            'lucky_draw_number_sortby' => ' 排序您指定的参数无效，则有效值为: lucky_draw_number, lucky_draw_id, user_id.',
            'news_object_type'     => ' news object type 您指定未找到. Valid value: promotion, news',
            'news_status'          => ' news status 您指定未找到.',
            'news_sortby'          => ' 排序您指定的参数无效，则有效值为: registered_date, news_name, object_type, description, begin_date, end_date, status',
            'news'                 => ' News ID 您指定未找到.',
            'link_object_id'       => ' Link Object ID 您指定未找到.',
            'bank_object'          => ' Bank Object ID 您指定未找到.',
            'mall'                 => ' Mall ID 您指定未找到.',
            'language' => ' Language ID 您指定未找到.',
            'merchant_language' => ' Merchant Language ID 您指定未找到.',
            'hour_format'          => ':attribute 是不是有效日期',
            'tenant_floor'          => 'Floor is required',
            'tenant_unit'          => 'Unit is required',
            'lucky_draw_announcement' => 'The lucky draw announcement you specified is not found',
        ),
        'queryerror' => 'Database query error, turn on debug mode to see the full query.',
        'jsonerror'  => array(
            'format' => ' JSON input you specified was not valid.',
            'array'  => ' JSON input you specified must be in array.',
            'field'  => array(
                'format'    => ' JSON input of field :field was not valid JSON input.',
                'array'     => ' JSON input of field :field must be in array.',
                'diffcount' => ' number of items on field :field are different.',
            ),
        ),
        'formaterror' => array(
            'product_attr' => array(
                'attribute' => array(
                    'value' => array(
                        'price'         => '价格应该是数字或小数.',
                        'count'         => ' 的值数必须为5.',
                        'order'         => '无效的属性顺序，从属性`期望值：expect`而是从属性`得到值：got`.',
                        'allnull'       => '这五个属性值不能为空，同时.',
                        'exists'        => ' 属性您发送的组合已经存在.',
                        'nullprepend'   => '空值必须属性值后放.',
                        'duplicate'     => '再次是产品的属性值的副本.',
                        'notsame'       => '你送一个或多个产品的组合不具有顺序相同数量..'
                    ),
                ),
            ),
            'pos_quick_product' => array(
                'array_count'   => ' 项目的数量不宜超过 :number.'
            ),
            'merchant' => array(
                'ticket_header' => array(
                    'max_length' => '商家票头最大长度为40个字符，每行.'
                ),
                'ticket_footer' => array(
                    'max_length' => '商家票脚注最大长度为40个字符，每行.'
                ),
            ),
            'url'   => array(
                'web'   => ' URL 无效. 有效实例 URL 是 www.example.com 要么 www.example.com/sub/page. 无需包括 http:// 要么 https://.'
            ),
            'translation' => array(
                'key' => '指定了无效键翻译.',
                'value' => '指定翻译的值无效.',
            ),
            'date' => array(
                'dmy_date' => 'The :attribute does not match the format dd-mm-yyyy',
                'cannot_future_date' => '出生日期不能在未来',
                'invalid_date' => '出生日期不是一个有效日期',
            ),
        ),
        'actionlist' => array(
            'change_password'           => '更改密码',
            'add_new_user'              => '添加新用户',
            'delete_user'               => '删除用户',
            'delete_your_self'          => '删除您的帐户',
            'update_user'               => '更新用户',
            'view_user'                 => '查看用户',
            'new_merchant'              => '添加新的商户',
            'update_merchant'           => '更新商家',
            'delete_merchant'           => '删除商家',
            'view_merchant'             => '查看商家',
            'new_retailer'              => '增加新的零售商',
            'update_retailer'           => '更新零售商',
            'delete_retailer'           => '删除零售商',
            'view_retailer'             => '鉴于零售商',
            'new_product'               => '增加新产品',
            'update_product'            => '更新产品',
            'delete_product'            => '删除产品',
            'view_product'              => '查看产品',
            'new_tax'                   => '增加新税',
            'update_tax'                => '更新税',
            'delete_tax'                => '删除税',
            'view_tax'                  => '鉴于税',
            'new_category'              => '添加新类别',
            'update_category'           => '更新类别',
            'delete_category'           => '删除类别',
            'view_category'             => '查看类别',
            'new_promotion'             => '添加新推广',
            'update_promotion'          => '最新促销',
            'delete_promotion'          => '删除促销',
            'view_promotion'            => '鉴于促销',
            'new_product_attribute'     => '添加新的产品属性',
            'update_product_attribute'  => '更新产品属性',
            'delete_product_attribute'  => '删除产品属性',
            'view_product_attribute'    => '查看产品属性',
            'new_coupon'                => '添加新的优惠券',
            'update_coupon'             => '更新优惠券',
            'delete_coupon'             => '删除优惠券',
            'view_coupon'               => '查看优惠券',
            'new_issuedcoupon'          => '新增发行的优惠券',
            'update_issuedcoupon'       => '发出的更新优惠券',
            'delete_issuedcoupon'       => '删除发行的优惠券',
            'view_issuedcoupon'         => '鉴于发行的优惠券',
            'add_new_widget'            => '添加新的widget',
            'update_widget'             => '更新部件',
            'delete_widget'             => '删除插件',
            'view_widget'               => '视图部件',
            'new_event'                 => '添加新事件',
            'update_event'              => '更新事件',
            'delete_event'              => '删除事件',
            'view_event'                => '查看事件',
            'update_setting'            => '更新设置',
            'view_setting'              => '视图设置',
            'new_pos_quick_product'     => '添加新的POS快速的产品',
            'update_pos_quick_product'  => '更新POS快速的产品',
            'delete_pos_quick_product'  => '删除POS快速的产品',
            'view_pos_quick_product'    => '【视图】POS快速的产品',
            'view_activity'             => '查看活动',
            'add_new_employee'          => '添加新的员工',
            'update_employee'           => '更新员工',
            'delete_employee'           => '删除员工',
            'view_personal_interest'    => '查看个人兴趣',
            'view_role'                 => '查看角色',
            'view_transaction_history'  => '查看交易历史',
            'shutdown_box'              => '关机或重启',
            'new_lucky_draw'            => '添加新的抽奖',
            'update_lucky_draw'         => '更新抽奖',
            'delete_lucky_draw'         => '删除抽奖',
            'view_lucky_draw'           => '查看抽奖'
        ),
        'max' => array(
            'total_issued_coupons' => 'zh:Number can not be less than current total issued coupons',
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
