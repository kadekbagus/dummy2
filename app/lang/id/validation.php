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

    "accepted"             => ":attribute harus diterima.",
    "active_url"           => ":attribute bukan URL yang valid.",
    "after"                => ":attribute harus tanggal setelah tanggal :date.",
    "alpha"                => ":attribute harus berupa huruf.",
    "alpha_dash"           => ":attribute harus berupa huruf, angka atau tanda garis.",
    "alpha_num"            => ":attribute harus berupa huruf atau angka.",
    "array"                => ":attribute harus berupa array.",
    "before"               => ":attribute harus tanggal sebelum tanggal :date.",
    "between"              => array(
        "numeric" => ":attribute harus antara :min sampai :max.",
        "file"    => ":attribute harus antara :min sampai :max kilobytes.",
        "string"  => ":attribute harus antara :min sampai :max karakter.",
        "array"   => ":attribute harus antara :min sampai :max item.",
    ),
    "boolean"              => ":attribute field harus berupa true atau false",
    "confirmed"            => ":attribute konfirmasi tidak sesuai.",
    "date"                 => ":attribute merupakan tanggal yang tidak valid.",
    "date_format"          => ":attribute tidak sesuai dengan format :format.",
    "different"            => ":attribute dan :other harus berbeda.",
    "digits"               => ":attribute harus :digits digit.",
    "digits_between"       => ":attribute harus antara :min sampai :max digit.",
    "email"                => ":attribute harus berupa alamat email yang valid.",
    "exists"               => ":attribute yang dipilih tidak valid.",
    "image"                => ":attribute harus berupa gambar.",
    "in"                   => ":attribute yang dipilih tidak valid.",
    "integer"              => ":attribute harus berupa integer.",
    "ip"                   => ":attribute harus berupa alamat IP yang valid.",
    "max"                  => array(
        "numeric" => ":attribute tidak boleh melebihi :max.",
        "file"    => ":attribute tidak boleh melebihi :max kilobytes.",
        "string"  => ":attribute tidak boleh melebihi :max karakter.",
        "array"   => ":attribute tidak boleh melebihi :max item.",
    ),
    "mimes"                => "Tipe file :attribute harus berupa: :values.",
    "min"                  => array(
        "numeric" => ":attribute minimum harus :min.",
        "file"    => ":attribute minimum harus :min kilobytes.",
        "string"  => ":attribute minimum harus :min karakter.",
        "array"   => ":attribute minimum harus :min item.",
    ),
    "not_in"               => ":attribute yang dipilih tidak valid.",
    "numeric"              => ":attribute harus berupa angka.",
    "regex"                => "Format :attribute tidak valid.",
    "required"             => ":attribute field diperlukan.",
    "required_if"          => ":attribute field diperlukan ketika :other bernilai :value.",
    "required_with"        => ":attribute field diperlukan ketika :values ada nilainya.",
    "required_with_all"    => ":attribute field diperlukan ketika :values ada nilainya.",
    "required_without"     => ":attribute field diperlukan ketika :values tidak ada nilainya.",
    "required_without_all" => ":attribute field diperlukan ketika tidak ada :values yang bernilai.",
    "same"                 => ":attribute dan :other harus sesuai.",
    "size"                 => array(
        "numeric" => ":attribute harus sebesar :size.",
        "file"    => ":attribute harus sebesar :size kilobytes.",
        "string"  => ":attribute harus mengandung :size karakter.",
        "array"   => ":attribute harus mengandung :size item.",
    ),
    "unique"               => ":attribute sudah ada.",
    "url"                  => "Format :attribute tidak valid.",
    "timezone"             => ":attribute harus zona yang valid.",

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
            'exists' => 'Alamat email sudah ada.',
        ),
        'exists' => array(
            'username'              => 'Username sudah ada.',
            'email'                 => 'Alamat email sudah ada.',
            'omid'                  => 'OMID telah diambil oleh Merchant lain.',
            'orid'                  => 'ORID telah diambil oleh Retailer lain.',
            'category_name'         => 'Nama kategori sudah terpakai.',
            'have_product_category' => 'Kategori tidak bisa dihapus: Satu atau lebih produk masih terhubung dengan kategori ini.',
            'product_have_transaction' => 'Produk ini memiliki satu atau lebih transaksi yang terhubung, sehingga tidak bisa dihapus.',
            'promotion_name'        => 'Nama promosi sudah terpakai.',
            'coupon_name'           => 'Nama kupon sudah terpakai.',
            'issued_coupon_code'    => 'Kode kupon sudah tertebus.',
            'event_name'            => 'Nama event sudah terpakai.',
            'tax_name'              => 'Nama pajak sudah terpakai.',
            'tax_link_to_product'   => 'Pajak tidak dapat dihapus: Satu atau lebih produk masih terhubung dengan pajak ini.',
            'product'               => array(
                'attribute'         => array(
                    'unique'        => 'Nama atribut \':attrname\' sudah ada.',
                    'value'         => array(
                        'transaction'   => 'Kombinasi atribut memiliki satu atau lebih transaksi yang terhubung, sehingga tidak dapat diedit atau dihapus.',
                        'unique'        => 'Nilai atribut \':value\' sudah ada.'
                    ),
                ),
                'variant'           => array(
                    'transaction'   => 'Produk variant ID :id memiliki satu atau lebih transaksi yang terhubung, sehingga tidak dapat diedit atau dihapus.'
                ),
                'transaction'       => 'Produk \':name\' memiliki satu atau lebih transaksi yang terhubung, sehingga tidak dapat diedit atau dihapus.'
            ),
            'employeeid'            => 'Employee ID tidak tersedia.',
            'widget_type'           => 'Terdapat widget lain yang memiliki tipe yang sama.',
            'mall_have_tenant'      => 'Mall mempunyai satu atau lebih tenant terhubung, sehingga tidak bisa dihapus.',
            'mallgroup_have_mall'   => 'Mall Group mempunyai satu atau lebih mall terhubung, sehingga tidak bisa dihapus.',
        ),
        'access' => array(
            'forbidden'              => 'Anda tidak memiliki akses untuk :action.',
            'needtologin'            => 'Anda harus login untuk melihat halaman ini.',
            'loginfailed'            => 'Email atau password Anda tidak sesuai.',
            'tokenmissmatch'         => 'CSRF protection token tidak sesuai.',
            'wrongpassword'          => 'Password salah.',
            'old_password_not_match' => 'Password lama Anda salah.',
            'view_activity'          => 'Anda tidak memiliki akses untuk melihat aktivitas',
            'view_personal_interest' => 'Anda tidak memiliki akses untuk melihat personal interest',
            'view_role'              => 'Anda tidak memiliki akses untuk melihat role',
        ),
        'empty' => array(
            'status_link_to'       => 'The Link To must be Y or N.',
            'role'                 => 'Role ID tidak ditemukan.',
            'consumer_role'        => 'Consumer role tidak ditemukan.',
            'token'                => 'Token tidak ditemukan.',
            'user'                 => 'User ID tidak ditemukan.',
            'merchant'             => 'Merchant ID tidak ditemukan.',
            'retailer'             => 'Retailer ID tidak ditemukan.',
            'product'              => 'Product ID tidak ditemukan.',
            'category'             => 'Category ID tidak ditemukan.',
            'tax'                  => 'Tax ID tidak ditemukan.',
            'promotion'            => 'Promotion ID tidak ditemukan.',
            'coupon'               => 'Coupon ID tidak ditemukan.',
            'issued_coupon'        => 'Issued Coupon ID tidak ditemukan.',
            'event'                => 'Event ID tidak ditemukan.',
            'event_translations'   => 'The Event Translation ID tidak ditemukan.',
            'merchant_language'    => 'The Merchant_Language ID tidak ditemukan.',
            'user_status'          => 'User status tidak ditemukan.',
            'user_sortby'          => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: username, email, firstname, lastname, and registered_date.',
            'merchant_status'      => 'Merchant status tidak ditemukan.',
            'merchant_sortby'      => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: registered_date, merchant_name, merchant_email, merchant_userid, merchant_description, merchantid, merchant_address1, merchant_address2, merchant_address3, merchant_cityid, merchant_city, merchant_countryid, merchant_country, merchant_phone, merchant_fax, merchant_status, merchant_currency.',
            'retailer_status'      => 'Retailer status tidak ditemukan.',
            'retailer_sortby'      => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: registered_date, retailer_name, retailer_email, and orid.',
            'tax_status'           => 'Tax status tidak ditemukan.',
            'tax_sortby'           => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: registered_date, merchant_tax_id, tax_name, tax_type, tax_value, tax_order.',
            'tax_type'             => 'Tipe tax tidak ditemukan. Nilai yang valid adalah: government, service, luxury.',
            'category_status'      => 'Category status tidak ditemukan.',
            'category_sortby'      => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: registered_date, category_name, category_level, category_order, description, status.',
            'promotion_status'     => 'Promotion status tidak ditemukan.',
            'promotion_sortby'     => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status, rule_type, display_discount_value.',
            'promotion_by_retailer_sortby' => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: retailer_name, registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status.',
            'promotion_type'       => 'Tipe promosi tidak ditemukan.',
            'rule_type'            => 'Rule type tidak ditemukan.',
            'rule_object_type'     => 'Rule object type tidak ditemukan.',
            'rule_object_id1'      => 'Rule object ID1 tidak ditemukan.',
            'rule_object_id2'      => 'Rule object ID2 tidak ditemukan.',
            'rule_object_id3'      => 'Rule object ID3 tidak ditemukan.',
            'rule_object_id4'      => 'Rule object ID4 tidak ditemukan.',
            'rule_object_id5'      => 'Rule object ID5 tidak ditemukan.',
            'discount_object_type' => 'Discount object type tidak ditemukan.',
            'discount_object_id1'  => 'Discount object ID1 tidak ditemukan.',
            'discount_object_id2'  => 'Discount object ID2 tidak ditemukan.',
            'discount_object_id3'  => 'Discount object ID3 tidak ditemukan.',
            'discount_object_id4'  => 'Discount object ID4 tidak ditemukan.',
            'discount_object_id5'  => 'Discount object ID5 tidak ditemukan.',
            'coupon_status'        => 'Coupon status tidak ditemukan.',
            'coupon_sortby'        => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status, rule_type, display_discount_value.',
            'coupon_by_issue_retailer_sortby' => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: issue_retailer_name, registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status.',
            'coupon_type'          => 'Tipe coupon tidak ditemukan.',
            'issued_coupon_status' => 'Issued coupon status tidak ditemukan.',
            'issued_coupon_sortby' => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: registered_date, issued_coupon_code, expired_date, issued_date, redeemed_date, status.',
            'issued_coupon_by_retailer_sortby' => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: redeem_retailer_name, registered_date, issued_coupon_code, expired_date, promotion_name, promotion_type, description.',
            'event_status'         => 'Event status tidak ditemukan.',
            'event_sortby'         => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: registered_date, event_name, event_type, description, begin_date, end_date, is_permanent, status.',
            'event_by_retailer_sortby' => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: retailer_name, registered_date, event_name, event_type, description, begin_date, end_date, is_permanent, status.',
            'event_type'           => 'Tipe event tidak ditemukan.',
            'link_object_type'     => 'Link object type tidak ditemukan.',
            'link_object_id1'      => 'Link object ID1 tidak ditemukan.',
            'link_object_id2'      => 'Link object ID2 tidak ditemukan.',
            'link_object_id3'      => 'Link object ID3 tidak ditemukan.',
            'link_object_id4'      => 'Link object ID4 tidak ditemukan.',
            'link_object_id5'      => 'Link object ID5 tidak ditemukan.',
            'category_id1'         => 'Category ID1 tidak ditemukan.',
            'category_id2'         => 'Category ID2 tidak ditemukan.',
            'category_id3'         => 'Category ID3 tidak ditemukan.',
            'category_id4'         => 'Category ID4 tidak ditemukan.',
            'category_id5'         => 'Category ID5 tidak ditemukan.',
            'attribute_sortby'     => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: id, name and created.',
            'attribute'            => 'Product attribute ID tidak ditemukan.',
            'product_status'       => 'Product status tidak ditemukan.',
            'product_sortby'       => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: registered_date, product_id, product_name, product_code, product_price, product_tax_code, product_short_description, product_long_description, product_is_new, product_new_until, product_merchant_id, product_status.',
            'product_attr'         => array(
                    'attribute'    => array(
                        'value'         => 'Product attribute value ID :id tidak ditemukan atau bukan milik merchant ini.',
                        'json_property' => 'Properti tidak ada ":property" pada JSON string.',
                        'variant'       => 'Product variant ID tidak ditemukan.'
                    ),
            ),
            'upc_code'             => 'Kode UPC produk tidak ditemukan.',
            'transaction'          => 'Transaction tidak ditemukan.',
            'widget'               => 'Widget ID tidak ditemukan.',
            'employee'             => array(
                'role'             => 'Role ":role" tidak ditemukan.',
            ),
            'setting_status'       => 'Status setting tidak ditemukan.',
            'setting_sortby'       => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: registered_date, setting_name, status.',
            'employee_sortby'      => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: username, firstname, lastname, registered_date, employee_id_char, position.',
            'posquickproduct'      => 'Pos quick product tidak ditemukan.',
            'posquickproduct_sortby' => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: id, price, name, product_order.',
            'transactionhistory'   => array(
                'merchantlist'     => array(
                    'sortby'       => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: name, last_transaction.',
                ),
                'retailerlist'     => array(
                    'sortby'       => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: name, last_transaction.',
                ),
                'productlist'      => array(
                    'sortby'       => 'Argument \'sort by\' Anda tidak valid, nilai yang valid adalah: name, last_transaction.',
                ),
            ),
            'language' => 'Language ID tidak ditemukan',
            'merchant_language' => 'Merchant Language ID tidak ditemukan',
        ),
        'queryerror' => 'Error pada database query, nyalakan mode debug untuk melihat seluruh query.',
        'jsonerror'  => array(
            'format' => 'JSON input Anda tidak valid.',
            'array'  => 'JSON input Anda harus berupa array.',
            'field'  => array(
                'format'    => 'JSON input field :field bukan format JSON yang valid.',
                'array'     => 'JSON input field :field harus berupa array.',
                'diffcount' => 'Jumlah item pada field :field berbeda.',
            ),
        ),
        'formaterror' => array(
            'product_attr' => array(
                'attribute' => array(
                    'value' => array(
                        'price'         => 'harga harus berupa angka atau desimal.',
                        'count'         => 'jumlah nilai harus 5.',
                        'order'         => 'attribute ID order tidak valid, yang diharapkan :expect tapi mendapat :got.',
                        'allnull'       => 'kelima nilai atribut tidak boleh kosong pada saat yang bersamaan.',
                        'exists'        => 'kombinasi atribut sudah ada.',
                        'nullprepend'   => 'Nilai null harus diletakkan setelah nilai atribut.',
                        'duplicate'     => 'Terdapat duplikat nilai atribut produk.',
                        'notsame'       => 'Beberapa kombinasi produk yang Anda kirim tidak memiliki jumlah order yang sama.'
                    ),
                ),
            ),
            'pos_quick_product' => array(
                'array_count'   => 'Jumlah item tidak boleh melebihi :number.'
            ),
            'merchant' => array(
                'ticket_header' => array(
                    'max_length' => 'Panjang karakter untuk Merchant ticket header adalah 40 karakter untuk setiap barisnya.'
                ),
                'ticket_footer' => array(
                    'max_length' => 'Panjang karakter untuk Merchant ticket footer adalah 40 karakter untuk setiap barisnya.'
                ),
            ),
            'translation' => array(
                'key' => 'Terdapat key yang invalid untuk terjemahan.',
                'value' => 'Terdapat value yang invalid untuk terjemahan.',
            ),
            'date' => array(
                'dmy_date' => 'Format tanggal tidak sesuai dd-mm-yyyy',
                'cannot_future_date' => 'Tanggal lahir tidak bisa tanggal di masa depan',
                'invalid_date' => 'Tanggal lahir merupakan tanggal yang tidak valid',
            ),
        ),
        'actionlist' => array(
            'change_password'           => 'update password',
            'add_new_user'              => 'buat user baru',
            'delete_user'               => 'hapus user',
            'delete_your_self'          => 'hapus akun Anda',
            'update_user'               => 'update user',
            'view_user'                 => 'lihat user',
            'new_merchant'              => 'buat merchant baru',
            'update_merchant'           => 'update merchant',
            'delete_merchant'           => 'hapus merchant',
            'view_merchant'             => 'lihat merchant',
            'new_retailer'              => 'buat retailer baru',
            'update_retailer'           => 'update retailer',
            'delete_retailer'           => 'hapus retailer',
            'view_retailer'             => 'lihat retailer',
            'new_product'               => 'buat product baru',
            'update_product'            => 'update product',
            'delete_product'            => 'hapus product',
            'view_product'              => 'lihat product',
            'new_tax'                   => 'buat pajak baru',
            'update_tax'                => 'update pajak',
            'delete_tax'                => 'hapus pajak',
            'view_tax'                  => 'lihat pajak',
            'new_category'              => 'buat kategori baru',
            'update_category'           => 'update kategori',
            'delete_category'           => 'hapus kategori',
            'view_category'             => 'lihat kategori',
            'new_promotion'             => 'buat promosi baru',
            'update_promotion'          => 'update promosi',
            'delete_promotion'          => 'hapus promosi',
            'view_promotion'            => 'lihat promosi',
            'new_product_attribute'     => 'buat atribut produk baru',
            'update_product_attribute'  => 'update atribut produk',
            'delete_product_attribute'  => 'hapus atribut produk',
            'view_product_attribute'    => 'lihat atribut produk',
            'new_coupon'                => 'buat kupon baru',
            'update_coupon'             => 'update kupon',
            'delete_coupon'             => 'hapus kupon',
            'view_coupon'               => 'lihat kupon',
            'new_issuedcoupon'          => 'buat issued kupon baru',
            'update_issuedcoupon'       => 'update issued kupon',
            'delete_issuedcoupon'       => 'hapus issued kupon',
            'view_issuedcoupon'         => 'lihat issued kupon',
            'add_new_widget'            => 'buat widget baru',
            'update_widget'             => 'update widget',
            'delete_widget'             => 'hapus widget',
            'view_widget'               => 'lihat widget',
            'new_event'                 => 'buat event baru',
            'update_event'              => 'update event',
            'delete_event'              => 'hapus event',
            'view_event'                => 'lihat event',
            'update_setting'            => 'update setting',
            'view_setting'              => 'lihat setting',
            'new_pos_quick_product'     => 'buat pos quick product baru',
            'update_pos_quick_product'  => 'update pos quick product',
            'delete_pos_quick_product'  => 'hapus pos quick product',
            'view_pos_quick_product'    => 'lihat pos quick product',
            'view_activity'             => 'lihat activity',
            'add_new_employee'          => 'buat employee baru',
            'update_employee'           => 'update employee',
            'delete_employee'           => 'hapus employee',
            'view_personal_interest'    => 'lihat personal interest',
            'view_role'                 => 'lihat role',
            'view_transaction_history'  => 'lihat transaction history'
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
