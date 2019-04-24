<?php
//TODO: activity keeps growing, maybe it is
//better to move it into dedicated table in database and have functionality to
//add new type of activity in portal
return array(
    'parameter_name' => 'act',
    'activity_list' => array(
        // landing page activity list
        // - Successfully landed in Gotomalls
        '1' => array(
            'name' => 'view_landing_page',
            'name_long' => 'View Landing Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Clicking on a mall in result list
        '2' => array(
            'name' => 'click_mall_list',
            'name_long' => 'Click Mall List',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Mall',
            'parameter_name' => 'object_id'
        ),
        // - Clicking on a mall pin on the map
        '3' => array(
            'name' => 'click_mall_pin',
            'name_long' => 'Click Mall Pin',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Mall',
            'parameter_name' => 'object_id'
        ),
        // - Visiting a mall
        '4' => array(
            'name' => 'view_mall',
            'name_long' => 'View Mall',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => 'Mall',
            'parameter_name' => 'mall_id'
        ),
        // - Viewing mall info
        '5' => array(
            'name' => 'view_mall_info',
            'name_long' => 'View Mall Info',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => 'Mall',
            'parameter_name' => 'object_id'
        ),
        // - Switching user
        '6' => array(
            'name' => 'click_not_me',
            'name_long' => 'Switch User',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // ** Main Page Widgets **
        // - Home Click
        '7' => array(
            'name' => 'click_home_main_page',
            'name_long' => 'Click Home Main Page',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Map Click
        '8' => array(
            'name' => 'click_map_main_page',
            'name_long' => 'Click Map Main Page',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Map View
        '9' => array(
            'name' => 'view_map_main_page',
            'name_long' => 'View Map Main Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Discover Click
        '10' => array(
            'name' => 'click_discover_main_page',
            'name_long' => 'Click Discover Main Page',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Discover View
        '11' => array(
            'name' => 'view_discover_main_page',
            'name_long' => 'View Discover Main Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Malls Click
        '12' => array(
            'name' => 'click_mall_main_page',
            'name_long' => 'Click Mall Main Page',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Malls View
        '13' => array(
            'name' => 'view_mall_main_page',
            'name_long' => 'View Mall Main Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Stores Click
        '14' => array(
            'name' => 'click_stores_main_page',
            'name_long' => 'Click Stores Main Page',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Stores View
        // *** Moved to the getStoreList() ***
        '15' => array(
            'name' => 'view_stores_main_page',
            'name_long' => 'View Stores Main Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Lucky Draws Click
        '16' => array(
            'name' => 'click_lucky_draws_main_page',
            'name_long' => 'Click Lucky Draws Main Page',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Lucky Draws View
        // *** Moved to the getSearchLuckyDraw() ***
        '17' => array(
            'name' => 'view_lucky_draws_main_page',
            'name_long' => 'View Lucky Draws Main Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - PokeMaps Click
        '18' => array(
            'name' => 'click_pokemaps_main_page',
            'name_long' => 'Click Pokemaps Main Page',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - PokeMaps View
        '19' => array(
            'name' => 'view_pokemaps_main_page',
            'name_long' => 'View Pokemaps Main Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // ** End Of Main Page Widgets **
        // - Clicking on a store in list
        '20' => array(
            'name' => 'click_store_list',
            'name_long' => 'Click Store List',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // - Clicking on a lucky draw in list
        '21' => array(
            'name' => 'click_lucky_draw_list',
            'name_long' => 'Click Lucky Draw List',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'LuckyDraw',
            'parameter_name' => 'lucky_draw_id'
        ),
        // - Clicking on walkthrough page 1
        '22' => array(
            'name' => 'click_walkthrough',
            'name_long' => 'Click Walkthrough Page 1',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Clicking on walkthrough page 2
        '23' => array(
            'name' => 'click_walkthrough',
            'name_long' => 'Click Walkthrough Page 2',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Clicking on walkthrough page 3
        '24' => array(
            'name' => 'click_walkthrough',
            'name_long' => 'Click Walkthrough Page 3',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - News Click
        '25' => array(
            'name' => 'click_news_main_page',
            'name_long' => 'Click News Main Page',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - News View
        '26' => array(
            'name' => 'view_news_main_page',
            'name_long' => 'View News Main Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Clicking on a news in list
        '27' => array(
            'name' => 'click_news_list',
            'name_long' => 'Click News List',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),
        // - Promotion Click
        '28' => array(
            'name' => 'click_promotions_main_page',
            'name_long' => 'Click Promotions Main Page',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Promotions View
        '29' => array(
            'name' => 'view_promotions_main_page',
            'name_long' => 'View Promotions Main Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Clicking on a promotion in list
        '30' => array(
            'name' => 'click_promotion_list',
            'name_long' => 'Click Promotion List',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),
        // - Coupon Click
        '31' => array(
            'name' => 'click_coupons_main_page',
            'name_long' => 'Click Coupons Main Page',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Coupons View
        // *** Moved to the getCouponList() ***
        '32' => array(
            'name' => 'view_coupons_main_page',
            'name_long' => 'View Coupons Main Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Clicking on a coupon in list
        '33' => array(
            'name' => 'click_coupon_list',
            'name_long' => 'Click Coupon List',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),

        // - News Location View
        // *** Moved to the getNewsLocations() ***
        '34' => array(
            'name' => 'view_news_location',
            'name_long' => 'View News Location Page',
            'module_name' => 'News',
            'type' => 'view',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),
        // - Clicking on a news location
        '35' => array(
            'name' => 'click_news_location',
            'name_long' => 'Click News Location',
            'module_name' => 'News',
            'type' => 'click',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),

        // - Promotion Location View
        // *** Moved to the getPromotionLocations() ***
        '36' => array(
            'name' => 'view_promotion_location',
            'name_long' => 'View Promotion Location Page',
            'module_name' => 'Promotion',
            'type' => 'view',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),
        // - Clicking on a promotion location
        '37' => array(
            'name' => 'click_promotion_location',
            'name_long' => 'Click Promotion Location',
            'module_name' => 'Promotion',
            'type' => 'click',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),

        // - Coupon Location View
        // *** Moved to the getCouponLocations() ***
        '38' => array(
            'name' => 'view_coupon_location',
            'name_long' => 'View Coupon Location Page',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // - Clicking on a coupon location
        '39' => array(
            'name' => 'click_coupon_location',
            'name_long' => 'Click Coupon Location',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),

        // - Store Location View
        // *** Moved to the getMallDetailStore() ***
        '40' => array(
            'name' => 'view_store_location',
            'name_long' => 'View Store Location Page',
            'module_name' => 'Store',
            'type' => 'view',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // - Clicking on a store location
        '41' => array(
            'name' => 'click_store_location',
            'name_long' => 'Click Store Location',
            'module_name' => 'Store',
            'type' => 'click',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // - View send coupon to email page
        '42' => array(
            'name' => 'view_send_coupon_to_email_page',
            'name_long' => 'View Send Coupon to Email Page',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'coupon_id'
        ),
        // - Clicking on my lucky number list
        '43' => array(
            'name' => 'click_my_lucky_number_list',
            'name_long' => 'Click My Lucky Number List',
            'module_name' => 'LuckyDraw',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),

        // - User clicks on "Events" from mall sidebar menu
        '44' => array(
            'name' => 'click_mall_menu_events',
            'name_long' => 'Click mall menu (Events)',
            'module_name' => 'News',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),

        // - User clicks on "See all" on event carousel in mall page
        '45' => array(
            'name' => 'click_see_all_mall_events',
            'name_long' => 'Click see all (Mall Events)',
            'module_name' => 'News',
            'type' => 'click',
            'object_type' => 'News',
            'parameter_name' => null
        ),

        // - User clicks an event from mall event list page
        '46' => array(
            'name' => 'click_in_mall_event_list',
            'name_long' => 'Click event from mall event list',
            'module_name' => 'News',
            'type' => 'click',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),

        // - User clicks on one of the events featured in event carousel (mall page)
        '47' => array(
            'name' => 'click_mall_featured_event',
            'name_long' => 'Click mall featured event',
            'module_name' => 'News',
            'type' => 'click',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),

        // - User clicks on "Promotions" from mall sidebar menu
        '48' => array(
            'name' => 'click_mall_menu_promotions',
            'name_long' => 'Click mall menu (Promotions)',
            'module_name' => 'Promotion',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),

        // - User clicks on "See all" on promotion carousel in mall page
        '49' => array(
            'name' => 'click_see_all_mall_promotions',
            'name_long' => 'Click see all (Mall Promotions)',
            'module_name' => 'Promotion',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),

        // - User clicks a promotion from mall promotion list page
        '50' => array(
            'name' => 'click_in_mall_promotion_list',
            'name_long' => 'Click promotion from mall promotion list',
            'module_name' => 'Promotion',
            'type' => 'click',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),

        // - User clicks on one of the promotions featured in promotion carousel (mall page)
        '51' => array(
            'name' => 'click_mall_featured_promotion',
            'name_long' => 'Click mall featured promotion',
            'module_name' => 'Promotion',
            'type' => 'click',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),

        // - User clicks on "Lucky draws" from mall sidebar menu Gotomalls
        '52' => array(
            'name' => 'click_mall_menu_lucky_draws',
            'name_long' => 'Click mall menu (Lucky draws)',
            'module_name' => 'LuckyDraw',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks on "See all" on lucky draw carousel in mall page
        '53' => array(
            'name' => 'click_see_all_mall_lucky_draws',
            'name_long' => 'Click see all (Mall Lucky Draws)',
            'module_name' => 'LuckyDraw',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks a lucky draw from mall lucky draw list page
        '54' => array(
            'name' => 'click_in_mall_lucky_draw_list',
            'name_long' => 'Click lucky draw from mall lucky draw list',
            'module_name' => 'LuckyDraw',
            'type' => 'click',
            'object_type' => 'LuckyDraw',
            'parameter_name' => 'object_id'
        ),
        // - User clicks on one of the lucky draw featured in lucky draw carousel (mall page)
        '55' => array(
            'name' => 'click_mall_featured_lucky_draw',
            'name_long' => 'Click mall featured lucky draw',
            'module_name' => 'LuckyDraw',
            'type' => 'click',
            'object_type' => 'LuckyDraw',
            'parameter_name' => 'lucky_draw_id'
        ),
        // - View progressive sign in (name) pop up page
        '56' => array(
            'name' => 'view_prog_login_name',
            'name_long' => 'View progressive sign in (Name)',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User gives name in progressive sign in (name) pop up page
        '57' => array(
            'name' => 'prog_login_name_ok',
            'name_long' => 'Progressive sign in (Name)',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User does not give name or dismiss progressive sign in (name) pop up page
        '58' => array(
            'name' => 'prog_login_name_canceled',
            'name_long' => 'Progressive sign in (Name) canceled',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - View progressive sign in (email) pop up page
        '59' => array(
            'name' => 'view_prog_login_email',
            'name_long' => 'View progressive sign in (Email)',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User gives email in progressive sign in (email) pop up page
        '60' => array(
            'name' => 'prog_login_email_ok',
            'name_long' => 'Progressive sign in (Email)',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User does not give email or dismiss progressive sign in (email) pop up page
        '61' => array(
            'name' => 'prog_login_email_canceled',
            'name_long' => 'Progressive sign in (Email) canceled',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks on "Coupons" from mall sidebar menu
        '62' => array(
            'name' => 'click_mall_menu_coupons',
            'name_long' => 'Click mall menu (Coupons)',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks on "See all" on coupon carousel in mall page
        '63' => array(
            'name' => 'click_see_all_mall_coupons',
            'name_long' => 'Click see all (Mall Coupons)',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks a coupon from mall coupon list page
        '64' => array(
            'name' => 'click_in_mall_coupon_list',
            'name_long' => 'Click coupon from mall coupon list',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // - User clicks on one of the coupons featured in coupon carousel (mall page)
        '65' => array(
            'name' => 'click_mall_featured_coupon',
            'name_long' => 'Click mall featured coupon',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // - User clicks on "Stores" from mall sidebar menu
        '66' => array(
            'name' => 'click_mall_menu_stores',
            'name_long' => 'Click mall menu (Stores)',
            'module_name' => 'Store',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks on "See all" on coupon carousel in mall page
        '67' => array(
            'name' => 'click_see_all_mall_stores',
            'name_long' => 'Click see all (Mall Stores)',
            'module_name' => 'Store',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks a store from mall store list page
        '68' => array(
            'name' => 'click_in_mall_store_list',
            'name_long' => 'Click store from mall store list',
            'module_name' => 'Store',
            'type' => 'click',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // - User clicks on one of the stores featured in store carousel (mall page)
        '69' => array(
            'name' => 'click_mall_featured_store',
            'name_long' => 'Click mall featured store',
            'module_name' => 'Store',
            'type' => 'click',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // - User clicks on mall sidebar menu
        '70' => array(
            'name' => 'click_mall_menu',
            'name_long' => 'Click mall menu',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks on one of the news/event featured in mall carousel (top of mall page)
        '71' => array(
            'name' => 'click_mall_featured_carousel',
            'name_long' => 'Click mall featured carousel',
            'module_name' => 'News',
            'type' => 'click',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),

        // - User clicks on one of the promotion featured in mall carousel (top of mall page)
        '72' => array(
            'name' => 'click_mall_featured_carousel',
            'name_long' => 'Click mall featured carousel',
            'module_name' => 'Promotion',
            'type' => 'click',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),

        // - User clicks on one of the coupon featured in mall carousel (top of mall page)
        '73' => array(
            'name' => 'click_mall_featured_carousel',
            'name_long' => 'Click mall featured carousel',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // - Click share button in detail page
        '74' => array(
            'name' => 'click_share',
            'name_long' => 'Click Share',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // - Click sharing platform e.g: Facebook, email, etc.
        '75' => array(
            'name' => 'click_share',
            'name_long' => 'Click Share Type',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // - User clicks back to Gotomalls home page
        '76' => array(
            'name' => 'click_back_to_GTM',
            'name_long' => 'Click back to Gotomalls',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Mall',
            'parameter_name' => 'mall_id'
        ),
        // - User clicks on "Mall info" from mall sidebar menu
        '77' => array(
            'name' => 'click_mall_menu_info',
            'name_long' => 'Click mall menu (Info)',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Mall',
            'parameter_name' => 'mall_id'
        ),
        // - User clicks on interested coupon
        '78' => array(
            'name' => 'click_interested',
            'name_long' => 'Click Interested',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'coupon_id'
        ),
        // - User clicks on not interested coupon
        '79' => array(
            'name' => 'click_not_interested ',
            'name_long' => 'Click Not Interested',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'coupon_id'
        ),
        // - The campaign appears in the carousel
        '80' => array(
            'name' => 'view_carousel',
            'name_long' => 'View Carousel',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // - User clicks on share us via Facebook/Email
        '81' => array(
            'name' => 'click_share_us',
            'name_long' => 'Click Share Us',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks on Facebook/Instagram/Twitter icon in about us section
        '82' => array(
            'name' => 'click_social_media',
            'name_long' => 'Click GTM Social Media',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Clicking on a lucky draw location
        '83' => array(
            'name' => 'click_lucky_draw_location',
            'name_long' => 'Click Lucky Draw Location',
            'module_name' => 'LuckyDraw',
            'type' => 'click',
            'object_type' => 'LuckyDraw',
            'parameter_name' => 'lucky_draw_id'
        ),
        // - Lucky Draw Location View
        '84' => array(
            'name' => 'view_lucky_draw_location',
            'name_long' => 'View Lucky Draw Location Page',
            'module_name' => 'LuckyDraw',
            'type' => 'view',
            'object_type' => 'LuckyDraw',
            'parameter_name' => 'lucky_draw_id'
        ),
        // - User view the about us page
        '85' => array(
            'name' => 'view_about_us',
            'name_long' => 'View About Us',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Event click in the carousel
        '86' => array(
            'name' => 'click_carousel',
            'name_long' => 'Click Carousel',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // - User clicks on sidebar menu (Put Home, My Coupon, etc in notes)
        '87' => array(
            'name' => 'click_menu',
            'name_long' => 'Click Menu',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - View advertisement on banner.
        '88' => array(
            'name' => 'view_banner',
            'name_long' => 'View Banner',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => 'Advert',
            'parameter_name' => 'object_id'
        ),
        // - Click advertisement on banner.
        '89' => array(
            'name' => 'click_banner',
            'name_long' => 'Click Banner',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Advert',
            'parameter_name' => 'object_id'
        ),
        // - Click blog item on homepage Gotomalls
        '90' => array(
            'name' => 'click_blog_link',
            'name_long' => 'Click Blog Link',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - Click category on homepage
        '91' => array(
            'name' => 'click_category',
            'name_long' => 'Click Category',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Category',
            'parameter_name' => 'category_id'
        ),
        // - User clicks on grab button in mall page.
        '92' => array(
            'name' => 'click_grab',
            'name_long' => 'Click Grab',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks OK to confirm grab.
        '93' => array(
            'name' => 'click_deeplink_confirm_ok',
            'name_long' => 'Click Deeplink Confirmation OK',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'partner_id'
        ),
        // - User clicks Cancel to confirm grab.
        '94' => array(
            'name' => 'click_deeplink_confirm_canceled',
            'name_long' => 'Click Deeplink Confirmation Cancel',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'partner_id'
        ),
        // - Usr clicks on a partner in homepage.
        '95' => array(
            'name' => 'click_partner',
            'name_long' => 'Click Partner',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // - User clicks on deeplink button (Get App) on partner info page.
        '96' => array(
            'name' => 'click_deeplink',
            'name_long' => 'Click Deeplink',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // - User clicks on button that redirect to our partner pre-filtered lists from partner info page.
        '97' => array(
            'name' => 'click_home_partner',
            'name_long' => 'Click Home Partner',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'partner_id'
        ),
        // - User clicks on tabs menu (Promotions, Coupons, Stores, Malls, Events)
        '98' => array(
            'name' => 'click_tab_menu',
            'name_long' => 'Click Tab Menu',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks on filter (Location, Category, Partner, Sort By)
        '99' => array(
            'name' => 'click_filter',
            'name_long' => 'Click Filter',
            'module_name' => 'Filter',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks on get coupon
        '100' => array(
            'name' => 'click_get_coupon',
            'name_long' => 'Click Get Coupon',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // - User click cancel on choose location popup after clicking use
        '101' => array(
            'name' => 'click_cancel_use',
            'name_long' => 'Click Cancel Use',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'coupon_id'
        ),
        // - User clicks on "Next" on choose location popup after clicking "✁ Use
        '102' => array(
            'name' => 'click_next_use',
            'name_long' => 'Click Next Use',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'coupon_id'
        ),
        // - User clicks on "Go Back" on verification popup after clicking "Next"
        '103' => array(
            'name' => 'click_go_back_use',
            'name_long' => 'Click Go Back Use',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'coupon_id'
        ),
        // - User clicks on "Use" button
        '104' => array(
            'name' => 'click_use',
            'name_long' => 'Click Use',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'coupon_id'
        ),
        // - User clicks on expand category button on Homepage
        '105' => array(
            'name' => 'click_expand_category',
            'name_long' => 'Click Expand Category',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks on three dots mobile menu
        '106' => array(
            'name' => 'click_mobile_menu',
            'name_long' => 'Click Mobile Menu',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks on auto complete search result
        '107' => array(
            'name' => 'click_auto_complete_search',
            'name_long' => 'Click Auto Complete Search',
            'module_name' => 'Search',
            'type' => 'click',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // - User clicks search button after entering keyword
        '108' => array(
            'name' => 'click_search',
            'name_long' => 'Click Search',
            'module_name' => 'Search',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks on one of the sign in mehod
        '109' => array(
            'name' => 'click_sign_in_method',
            'name_long' => 'Click Sign In Method',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks on "Edit" profile button
        '110' => array(
            'name' => 'click_edit_profile',
            'name_long' => 'Click Edit Profile',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // - User clicks on "Visit" button on map info popup / Store location
        '111' => array(
            'name' => 'click_mall',
            'name_long' => 'Click Mall',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Mall',
            'parameter_name' => 'object_id'
        ),
        // User clicks on "More->Language" and change the langauge
        '112' => array(
            'name' => 'click_language',
            'name_long' => 'Click Language',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Language',
            'parameter_name' => 'object_id'
        ),
        // User clicks submit button on advertise form
        '113' => array(
            'name' => 'click_submit_advertise',
            'name_long' => 'Click Submit Advertise',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User clicks submit button on feedback form
        '114' => array(
            'name' => 'click_submit_feedback',
            'name_long' => 'Click Submit Feedback',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User view sign in page
        '115' => array(
            'name' => 'view_sign_in_page',
            'name_long' => 'View Sign In Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User clicks call to action button in promotional event detail page
        '116' => array(
            'name' => 'click_call_to_action_button',
            'name_long' => 'Click Call To Action Button',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // User views promotional event sign in page
        '117' => array(
            'name' => 'view_promotional_sign_in_page',
            'name_long' => 'View Promotional Sign In Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // User click promotional event in promotional event history page
        '118' => array(
            'name' => 'click_my_reward',
            'name_long' => 'Click My Reward',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // User views promotion detail in gtm level
        '119' => array(
            'name' => 'view_landing_page_promotion_detail',
            'name_long' => 'View GoToMalls Promotion Detail',
            'module_name' => 'Promotion',
            'type' => 'view',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),
        // User views promotion detail in mall level
        '120' => array(
            'name' => 'view_mall_promotion_detail',
            'name_long' => 'View mall promotion detail',
            'module_name' => 'Promotion',
            'type' => 'view',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),
        // User views event detail in gtm level
        '121' => array(
            'name' => 'view_landing_page_news_detail',
            'name_long' => 'View GoToMalls News Detail',
            'module_name' => 'News',
            'type' => 'view',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),
        // User views event detail in mall level
        '122' => array(
            'name' => 'view_mall_event_detail',
            'name_long' => 'View mall event detail',
            'module_name' => 'News',
            'type' => 'view',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),
        // User views coupon detail in gtm level
        '123' => array(
            'name' => 'view_landing_page_coupon_detail',
            'name_long' => 'View GoToMalls Coupon Detail',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // User views coupon detail in mall level
        '124' => array(
            'name' => 'view_mall_coupon_detail',
            'name_long' => 'View mall coupon detail',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // User views promotional event detail in gtm level
        '125' => array(
            'name' => 'view_landing_page_promotional_event_detail',
            'name_long' => 'View GoToMalls Promotional Event Detail',
            'module_name' => 'News',
            'type' => 'view',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),
        // User views promotional event detail in mall level
        '126' => array(
            'name' => 'view_mall_promotional_event_detail',
            'name_long' => 'View mall promotional event detail',
            'module_name' => 'News',
            'type' => 'view',
            'object_type' => 'News',
            'parameter_name' => 'object_id'
        ),
        // User views store detail in gtm level
        '127' => array(
            'name' => 'view_landing_page_store_detail',
            'name_long' => 'View GoToMalls Store Detail',
            'module_name' => 'Store',
            'type' => 'view',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // User views store detail in mall level
        '128' => array(
            'name' => 'view_mall_store_detail',
            'name_long' => 'View mall store detail',
            'module_name' => 'Store',
            'type' => 'view',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // User views mall detail
        '129' => array(
            'name' => 'view_mall',
            'name_long' => 'View Mall Page',
            'module_name' => 'Mall',
            'type' => 'view',
            'object_type' => 'Mall',
            'parameter_name' => 'object_id'
        ),
        // User views filter page
        '130' => array(
            'name' => 'view_filter_page',
            'name_long' => 'View Filter Page',
            'module_name' => 'Filter',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User tap enter after typing keyword in search bar (keyword searched on notes)
        '131' => array(
            'name' => 'search',
            'name_long' => 'Search',
            'module_name' => 'Filter',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User click apply after selecting Filters (Categories, Sort By and Partner details on notes)
        '132' => array(
            'name' => 'filter',
            'name_long' => 'Filter',
            'module_name' => 'Filter',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User click apply after selecting Location (location detail on notes)
        '133' => array(
            'name' => 'location',
            'name_long' => 'Location',
            'module_name' => 'Filter',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User view email sign in page
        '134' => array(
            'name' => 'view_sign_in_email_page',
            'name_long' => 'View Sign In Email Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User view email sign up page
        '135' => array(
            'name' => 'view_sign_up_email_page',
            'name_long' => 'View Sign Up Email Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User view forgot password page
        '136' => array(
            'name' => 'view_forgot_password_page',
            'name_long' => 'View Forgot Password Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User view sign in page from promotional event
        '137' => array(
            'name' => 'view_sign_in_page_with_reward',
            'name_long' => 'View Sign In Page With Reward',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User clicks on review summary area
        '138' => array(
            'name' => 'click_review_summary',
            'name_long' => 'Click Rating and Review Summary',
            'module_name' => 'Rating',
            'type' => 'click',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // User clicks on review input area
        '139' => array(
            'name' => 'click_review_input_area',
            'name_long' => 'Click Rating and Review Input Area',
            'module_name' => 'Rating',
            'type' => 'click',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // User clicks on review submit button
        '140' => array(
            'name' => 'click_review_submit',
            'name_long' => 'Click Rating and Review Submit',
            'module_name' => 'Rating',
            'type' => 'click',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // - User clicks menu on mobile home page (Put News Icon, Promotions Icon, Coupons Icon, etc in notes)
        '141' => array(
            'name' => 'click_menu',
            'name_long' => 'Click Menu',
            'module_name' => 'Mobile Homepage',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User clicks on notification permission popup (object_display_name: allow, block, dismiss)
        '142' => array(
            'name' => 'click_notification_permission',
            'name_long' => 'Click Notification Permission',
            'module_name' => 'Notification',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User clicks on push notification pop up
        '143' => array(
            'name' => 'click_push_notification',
            'name_long' => 'Click Push Notification',
            'module_name' => 'Notification',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User clicks on download app
        '144' => array(
            'name' => 'click_download_app',
            'name_long' => 'Click Download App',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // User clicks on use and pay
        '145' => array(
            'name' => 'click_use_and_pay',
            'name_long' => 'Click Use and Pay',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // User clicks on payment method
        '146' => array(
            'name' => 'click_payment_method',
            'name_long' => 'Click Payment Method',
            'module_name' => 'Transaction',
            'type' => 'click',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // User clicks on submit payment
        '147' => array(
            'name' => 'click_submit_payment',
            'name_long' => 'Click Submit Payment',
            'module_name' => 'Transaction',
            'type' => 'click',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // User view on my purchase page
        '148' => array(
            'name' => 'view_my_purchase_page',
            'name_long' => 'View My Purchase Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // payment transaction succesful
        '149' => array(
            'name' => 'payment_transaction_successful',
            'name_long' => 'Payment Transaction Successful',
            'module_name' => 'Transaction',
            'type' => 'payment',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // payment transaction failed
        '150' => array(
            'name' => 'payment_transaction_failed',
            'name_long' => 'Payment Transaction Failed',
            'module_name' => 'Transaction',
            'type' => 'payment',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // User clicks inapps notifications
        '151' => array(
            'name' => 'click_inapp_notification',
            'name_long' => 'Click InApp Notification',
            'module_name' => 'Notification',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // user click follow
        '152' => array(
            'name' => 'click_follow',
            'name_long' => 'Click Follow',
            'module_name' => 'Follow',
            'type' => 'click',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // user click unfollow
        '153' => array(
            'name' => 'click_unfollow',
            'name_long' => 'Click Unfollow',
            'module_name' => 'Follow',
            'type' => 'click',
            'object_type' => '--SET BY object_type_parameter_name--',
            'object_type_parameter_name' => 'object_type',
            'parameter_name' => 'object_id'
        ),
        // User views my favorit page
        '154' => array(
            'name' => 'view_my_favorite_page',
            'name_long' => 'View My Favorite Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // user click notification icon
        '155' => array(
            'name' => 'click_notification_icon',
            'name_long' => 'Click Notification Icon',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User view notification page
        '156' => array(
            'name' => 'view_notification_page',
            'name_long' => 'View Notification Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // user click delete single notification
        '157' => array(
            'name' => 'click_delete_single_notification',
            'name_long' => 'Click Delete Single Notification',
            'module_name' => 'Notification',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // user click delete all notification
        '158' => array(
            'name' => 'click_delete_all_notification',
            'name_long' => 'Click Delete All Notification',
            'module_name' => 'Notification',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // user click credit card trigger on
        '159' => array(
            'name' => 'click_cc_trigger_on',
            'name_long' => 'Click My Credit Card Trigger On',
            'module_name' => 'Filter',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // user click credit card trigger off
        '160' => array(
            'name' => 'click_cc_trigger_off',
            'name_long' => 'Click My Credit Card Trigger Off',
            'module_name' => 'Filter',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // user view my credit card page
        '161' => array(
            'name' => 'view_my_credit_card_page',
            'name_long' => 'View My Credit Card/E-Wallet Page',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // user click submit my credit card
        '162' => array(
            'name' => 'click_submit_my_credit_card',
            'name_long' => 'Click Submit My Credit Card/E-Wallet',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // user view my credit card popup
        '163' => array(
            'name' => 'view_my_credit_card_popup',
            'name_long' => 'View My Credit Card/E-Wallet Popup',
            'module_name' => 'Application',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // user click my credit card popup icon
        '164' => array(
            'name' => 'click_my_credit_card_popup_icon',
            'name_long' => 'Click My Credit Card/E-Wallet Popup',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // user add GTM web app to home screen via web app install banner
        '165' => array(
            'name' => 'click_add_homescreen',
            'name_long' => 'Click Add Homescreen',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // Go to my wallet from transaction detail.
        '166' => array(
            'name' => 'click_go_to_my_wallet_from_transaction_detail',
            'name_long' => 'Click Go to My Wallet from Transaction Detail',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // Back to purchase list from transaction detail.
        '167' => array(
            'name' => 'click_back_to_purchase_list_from_transaction_detail',
            'name_long' => 'Click Back to Purchase List from Transaction Detail',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // Back to coupon detail from transaction detail.
        '168' => array(
            'name' => 'click_back_to_coupon_detail_from_transaction_detail',
            'name_long' => 'Click Back to Coupon Detail from Transaction Detail',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // Go to purchase history from transaction detail.
        '169' => array(
            'name' => 'click_go_to_purchase_history_from_transaction_detail',
            'name_long' => 'Click Go to Purchase History from Transaction Detail',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // View transaction detail of successful
        '170' => array(
            'name' => 'view_transaction_page_payment_successful',
            'name_long' => 'View Transaction Page Payment Success',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // View transaction detail of waiting payment
        '171' => array(
            'name' => 'view_transaction_page_payment_waiting',
            'name_long' => 'View Transaction Page Payment Waiting',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // View transaction detail of failed
        '172' => array(
            'name' => 'view_transaction_page_payment_failed',
            'name_long' => 'View Transaction Page Payment Failed',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // View transaction detail of successful payment and got coupon/voucher
        '173' => array(
            'name' => 'view_transaction_page_payment_successful_got_coupon',
            'name_long' => 'View Transaction Page Payment Success Got Coupon',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // View transaction detail of successful payment but waiting for coupon
        '174' => array(
            'name' => 'view_transaction_page_payment_successful_waiting_coupon',
            'name_long' => 'View Transaction Page Payment Success Waiting Coupon',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),

        // Click buy now step 1
        '175' => array(
            'name' => 'click_buy_now_step_1',
            'name_long' => 'Click Buy Now Step 1',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),

        // Click buy now step 2
        '176' => array(
            'name' => 'click_buy_now_step_2',
            'name_long' => 'Click Buy Now Step 2',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),

        // view payment page
        '177' => array(
            'name' => 'view_payment_page_midtrans_payment_page',
            'name_long' => 'View Payment Page (Midtrans Payment Page)',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),

        // view sold out popup step 1
        '178' => array(
            'name' => 'view_sold_out_popup_step_1',
            'name_long' => 'View Sold Out Popup Step 1',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),

        // view sold out popup step 2
        '179' => array(
            'name' => 'view_sold_out_popup_step_2',
            'name_long' => 'View Sold Out Popup Step 2',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),

        // click on redeem
        '180' => array(
            'name' => 'click_on_redeem',
            'name_long' => 'Click on Redeem',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),

        // payment transaction expired
        '181' => array(
            'name' => 'view_transaction_page_payment_expired',
            'name_long' => 'View Transaction Page Payment Expired',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        '182' => array(
            'name' => 'view_transaction_page_payment_successful_coupon_failed',
            'name_long' => 'View Transaction Page Payment Success - Failed Getting Coupon',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // payment transaction canceled
        '183' => array(
            'name' => 'view_transaction_page_payment_canceled',
            'name_long' => 'View Transaction Page Payment Canceled',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // click cancel payment from transaction detail page
        '184' => array(
            'name' => 'click_cancel_payment',
            'name_long' => 'Click Cancel Payment',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // user click feedback button on store detail
        '185' => array(
            'name' => 'click_feedback',
            'name_long' => 'Click Feedback Store',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // user click feedback button on mall detail
        '186' => array(
            'name' => 'click_feedback',
            'name_long' => 'Click Feedback Mall',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => 'Mall',
            'parameter_name' => 'object_id'
        ),
        // User views Stripe payment window.
        '187' => array(
            'name' => 'view_payment_page_stripe_payment_page',
            'name_long' => 'View Payment Page (Stripe Payment Page)',
            'module_name' => 'Coupon',
            'type' => 'view',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // user click brand photo thumbnail on brand detail page
        '188' => array(
            'name' => 'click_brand_photo',
            'name_long' => 'Click Brand Photo Thumbnail',
            'module_name' => 'Merchant',
            'type' => 'click',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // user click brand other photo thumbnail on brand detail page
        '189' => array(
            'name' => 'click_brand_other_photo',
            'name_long' => 'Click Brand Other Photo Thumbnail',
            'module_name' => 'Merchant',
            'type' => 'click',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // user click video thumbnail on brand detail page
        '190' => array(
            'name' => 'click_video_thumbnail',
            'name_long' => 'Click Video Thumbnail',
            'module_name' => 'Merchant',
            'type' => 'click',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // user click video play brand on brand detail page
        '191' => array(
            'name' => 'click_play_video_brand',
            'name_long' => 'Click Video Play Brand',
            'module_name' => 'Merchant',
            'type' => 'click',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // user click brand promotion on brand detail page
        '192' => array(
            'name' => 'click_brand_promotion',
            'name_long' => 'Click Brand Promotion',
            'module_name' => 'Merchant',
            'type' => 'click',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // user click brand event on brand detail page
        '193' => array(
            'name' => 'click_brand_event',
            'name_long' => 'Click Brand Event',
            'module_name' => 'Merchant',
            'type' => 'click',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // user click brand coupon on brand detail page
        '194' => array(
            'name' => 'click_brand_coupon',
            'name_long' => 'Click Brand Coupon',
            'module_name' => 'Merchant',
            'type' => 'click',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // Articles Activities
        '195' => array(
            'name' => 'click_see_all_articles',
            'name_long' => 'Click See All Articles',
            'module_name' => 'Article',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        '196' => array(
            'name' => 'click_tab_menu',
            'name_long' => 'Click Tab Menu',
            'module_name' => 'Application',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        '197' => array(
            'name' => 'click_article_list',
            'name_long' => 'Click Article List',
            'module_name' => 'Article',
            'type' => 'click',
            'object_type' => 'Article',
            'parameter_name' => 'object_id'
        ),
        '198' => array(
            'name' => 'view_article_detail',
            'name_long' => 'View Article Detail Page',
            'module_name' => 'Article',
            'type' => 'view',
            'object_type' => 'Article',
            'parameter_name' => 'object_id'
        ),

        '199' => array(
            'name' => 'click_image_article',
            'name_long' => 'Click Image Article',
            'module_name' => 'Article',
            'type' => 'click',
            'object_type' => 'Article',
            'parameter_name' => 'object_id'
        ),

        '200' => array(
            'name' => 'click_play_video_article',
            'name_long' => 'Click Play Video Article',
            'module_name' => 'Article',
            'type' => 'click',
            'object_type' => 'Article',
            'parameter_name' => 'object_id'
        ),

        '201' => array(
            'name' => 'click_share_article',
            'name_long' => 'Click Share Article',
            'module_name' => 'Article',
            'type' => 'click',
            'object_type' => 'Article',
            'parameter_name' => 'object_id'
        ),
        // user click brand product
        '202' => array(
            'name' => 'click_brand_product',
            'name_long' => 'Click Brand Product',
            'module_name' => 'Product',
            'type' => 'click',
            'object_type' => 'Product',
            'parameter_name' => 'object_id'
        ),
        // user click brand product detail
        '203' => array(
            'name' => 'click_brand_product_detail_link',
            'name_long' => 'Click Brand Product Link',
            'module_name' => 'Product',
            'type' => 'click',
            'object_type' => 'Product',
            'parameter_name' => 'object_id'
        ),
        // user click brand social button on brand detail page
        '204' => array(
            'name' => 'click_brand_social_button',
            'name_long' => 'Click Brand Social Button',
            'module_name' => 'Merchant',
            'type' => 'click',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // user click brand product on brand detail page
        '205' => array(
            'name' => 'click_brand_product',
            'name_long' => 'Click Brand Product',
            'module_name' => 'Merchant',
            'type' => 'click',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // user click brand article on brand detail page
        '206' => array(
            'name' => 'click_brand_article',
            'name_long' => 'Click Brand Article',
            'module_name' => 'Merchant',
            'type' => 'click',
            'object_type' => 'Tenant',
            'parameter_name' => 'object_id'
        ),
        // user click video thumbnail on mall detail page
        '207' => array(
            'name' => 'click_video_thumbnail',
            'name_long' => 'Click Video Thumbnail',
            'module_name' => 'Mall',
            'type' => 'click',
            'object_type' => 'Mall',
            'parameter_name' => 'object_id'
        ),
        // user click video play brand on brand detail page
        '208' => array(
            'name' => 'click_play_video_mall',
            'name_long' => 'Click Video Play Mall',
            'module_name' => 'Mall',
            'type' => 'click',
            'object_type' => 'Mall',
            'parameter_name' => 'object_id'
        ),
        // user clicking pulsa banner in home page
        '209' => array(
            'name' => 'click_home_pulsa_link',
            'name_long' => 'Click Home Pulsa Link',
            'module_name' => 'Pulsa',
            'type' => 'click',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User arrives at pulsa purchase page (where they input phone number)
        '210' => array(
            'name' => 'view_pulsa_purchase_page',
            'name_long' => 'View Pulsa Purchase Page',
            'module_name' => 'Pulsa',
            'type' => 'view',
            'object_type' => null,
            'parameter_name' => null
        ),
        // User arrives at pulsa purchase page (where they input phone number)
        '211' => array(
            'name' => 'click_buy_pulsa',
            'name_long' => 'Click Buy Pulsa',
            'module_name' => 'Pulsa',
            'type' => 'click',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),

        // View transaction detail of successful
        '212' => array(
            'name' => 'view_transaction_page_payment_successful',
            'name_long' => 'View Transaction Page Payment Success',
            'module_name' => 'Pulsa',
            'type' => 'view',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),
        // View transaction detail of waiting payment
        '213' => array(
            'name' => 'view_transaction_page_payment_waiting',
            'name_long' => 'View Transaction Page Payment Waiting',
            'module_name' => 'Pulsa',
            'type' => 'view',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),
        // View transaction detail of failed
        '214' => array(
            'name' => 'view_transaction_page_payment_failed',
            'name_long' => 'View Transaction Page Payment Failed',
            'module_name' => 'Pulsa',
            'type' => 'view',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),
        // View transaction detail of canceled
        '215' => array(
            'name' => 'view_transaction_page_payment_canceled',
            'name_long' => 'View Transaction Page Payment Canceled',
            'module_name' => 'Pulsa',
            'type' => 'view',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),

        // click partner photo
        '216' => array(
            'name' => 'click_partner_photo',
            'name_long' => 'Click Partner Photo',
            'module_name' => 'Partner',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // click partner program
        '217' => array(
            'name' => 'click_partner_program',
            'name_long' => 'Click Partner Program',
            'module_name' => 'Partner',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // click partner video thumbnail
        '218' => array(
            'name' => 'click_partner_video_thumbnail',
            'name_long' => 'Click Partner Video Thumbnail',
            'module_name' => 'Partner',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // click partner play video
        '219' => array(
            'name' => 'click_partner_play_video',
            'name_long' => 'Click Partner Play Video',
            'module_name' => 'Partner',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // click partner promotion
        '220' => array(
            'name' => 'click_partner_promotion',
            'name_long' => 'Click Partner Promotion',
            'module_name' => 'Partner',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // click partner promotion see all
        '221' => array(
            'name' => 'click_partner_promotion_see_all',
            'name_long' => 'Click Partner Promotion See All',
            'module_name' => 'Partner',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // click partner event
        '222' => array(
            'name' => 'click_partner_event',
            'name_long' => 'Click Partner Event',
            'module_name' => 'Partner',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // click partner event see all
        '223' => array(
            'name' => 'click_partner_event_see_all',
            'name_long' => 'Click Partner Event See All',
            'module_name' => 'Partner',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // click partner coupon
        '224' => array(
            'name' => 'click_partner_coupon',
            'name_long' => 'Click Partner Coupon',
            'module_name' => 'Partner',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // click partner coupon see all
        '225' => array(
            'name' => 'click_partner_coupon_see_all',
            'name_long' => 'Click Partner Coupon See All',
            'module_name' => 'Partner',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // click partner article
        '226' => array(
            'name' => 'click_partner_article',
            'name_long' => 'Click Partner Article',
            'module_name' => 'Partner',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // click partner article see all
        '227' => array(
            'name' => 'click_partner_article_see_all',
            'name_long' => 'Click Partner Article See All',
            'module_name' => 'Partner',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // click partner social media button
        '228' => array(
            'name' => 'click_partner_social_media_btn',
            'name_long' => 'Click Partner Social Media Button',
            'module_name' => 'Partner',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // view partner detail
        '229' => array(
            'name' => 'view_partner_detail',
            'name_long' => 'View Partner Detail',
            'module_name' => 'Partner',
            'type' => 'view',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),
        // click partner banner
        '230' => array(
            'name' => 'click_partner_banner',
            'name_long' => 'Click Partner Banner',
            'module_name' => 'Partner',
            'type' => 'click',
            'object_type' => 'Partner',
            'parameter_name' => 'object_id'
        ),

        //--------------begin pulsa transaction detail activity--------------
        // Go to my wallet from pulsa transaction detail.
        '231' => array(
            'name' => 'click_go_to_my_wallet_from_pulsa_transaction_detail',
            'name_long' => 'Click Go to My Wallet from Pulsa Transaction Detail',
            'module_name' => 'Pulsa',
            'type' => 'click',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),
        // Back to purchase history from pulsa transaction detail.
        '232' => array(
            'name' => 'click_back_to_purchase_history_from_pulsa_transaction_detail',
            'name_long' => 'Click Back to Purchase History from Pulsa Transaction Detail',
            'module_name' => 'Pulsa',
            'type' => 'click',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),
        // Back to buy pulsa from pulsa transaction detail.
        '233' => array(
            'name' => 'click_back_to_buy_pulsa_from_pulsa_transaction_detail',
            'name_long' => 'Click Back to Buy Pulsa from Pulsa Transaction Detail',
            'module_name' => 'Pulsa',
            'type' => 'click',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),
        // View pulsa transaction detail of successful
        '234' => array(
            'name' => 'view_pulsa_transaction_page_payment_successful',
            'name_long' => 'View Pulsa Transaction Page Payment Success',
            'module_name' => 'Pulsa',
            'type' => 'view',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),
        // View pulsa transaction detail of waiting payment
        '235' => array(
            'name' => 'view_pulsa_transaction_page_payment_waiting',
            'name_long' => 'View Pulsa Transaction Page Payment Waiting',
            'module_name' => 'Pulsa',
            'type' => 'view',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),
        // View pulsa transaction detail of failed
        '236' => array(
            'name' => 'view_pulsa_transaction_page_payment_failed',
            'name_long' => 'View Pulsa Transaction Page Payment Failed',
            'module_name' => 'Pulsa',
            'type' => 'view',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),
        // View pulsa transaction detail of successful payment but failed to get pulsa
        '237' => array(
            'name' => 'view_pulsa_transaction_page_payment_successful_pulsa_failed',
            'name_long' => 'View Pulsa Transaction Page Payment Success Pulsa Failed',
            'module_name' => 'Pulsa',
            'type' => 'view',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),
        // View pulsa transaction detail of expired
        '238' => array(
            'name' => 'view_pulsa_transaction_page_payment_expired',
            'name_long' => 'View Pulsa Transaction Page Payment Expired',
            'module_name' => 'Pulsa',
            'type' => 'view',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),
        // pulsa payment transaction canceled
        '239' => array(
            'name' => 'view_pulsa_transaction_page_payment_canceled',
            'name_long' => 'View Pulsa Transaction Page Payment Canceled',
            'module_name' => 'Pulsa',
            'type' => 'view',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),
        // click cancel payment from transaction detail page
        '240' => array(
            'name' => 'click_cancel_payment_pulsa',
            'name_long' => 'Click Cancel Payment Pulsa',
            'module_name' => 'Pulsa',
            'type' => 'click',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),
        // click nominal pulsa
        '241' => array(
            'name' => 'click_nominal_pulsa',
            'name_long' => 'Click Nominal Pulsa',
            'module_name' => 'Pulsa',
            'type' => 'click',
            'object_type' => 'Pulsa',
            'parameter_name' => 'object_id'
        ),
        // Click button redeem for Gift N coupon trx detail page.
        '242' => array(
            'name' => 'click_redeem_gift_n_button',
            'name_long' => 'Click Redeem GiftN Button',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
        // Click redeem url for Gift N coupon trx detail page.
        '243' => array(
            'name' => 'click_redeem_gift_n_url',
            'name_long' => 'Click Redeem GiftN URL',
            'module_name' => 'Coupon',
            'type' => 'click',
            'object_type' => 'Coupon',
            'parameter_name' => 'object_id'
        ),
    ),
);
