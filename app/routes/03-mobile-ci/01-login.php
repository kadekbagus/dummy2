<?php

Route::group(
    array('before' => ['orbit-settings', 'turn-off-query-string-session']),
    function () {

        // Route::get(
        //     '/customer', ['as' => 'mobile-ci.signin',
        //     function () {

        //         return MobileCI\MobileCIAPIController::create()->getSignInView();
        //     }]
        // );

        Route::get(
            '/customer', ['as' => 'mobile-ci-home',
            function () {

                return MobileCI\MobileCIAPIController::create()->getHomeView();
            }]
        );

        Route::post(
            '/customer/social-login', ['as' => 'mobile-ci.social_login',
                function () {
                    return MobileCI\MobileCIAPIController::create()->postSocialLoginView();
                },
            ]
        );

        Route::get(
            '/customer/social-login-callback', ['as' => 'mobile-ci.social_login_callback',
                function () {
                    return MobileCI\MobileCIAPIController::create()->getSocialLoginCallbackView();
                },
            ]
        );

        Route::get(
            '/customer/social-google-callback', ['as' => 'mobile-ci.social_google_callback',
                function () {
                    return MobileCI\MobileCIAPIController::create()->getGoogleCallbackView();
                },
            ]
        );

        Route::get(
            '/customer/signup',
            function () {

                return MobileCI\MobileCIAPIController::create()->getSignUpView();
            }
        );

        Route::post(
            '/customer/signup',
            function () {

                return MobileCI\MobileCIAPIController::create()->postSignUpView();
            }
        );

        Route::get(
            '/customer/home', ['as' => 'ci-customer-home',
            function () {
                return MobileCI\MobileCIAPIController::create()->getHomeView();
            }]
        );

        Route::get(
            '/customer/messages', ['as' => 'ci-notification-list',
            function () {

                return MobileCI\MobileCIAPIController::create()->getNotificationsView();
            }]
        );

        Route::get(
            '/customer/message/detail', ['as' => 'ci-notification-detail',
            function () {

                return MobileCI\MobileCIAPIController::create()->getNotificationDetailView();
            }]
        );

        Route::get(
            '/customer/activation',
            function () {

                return MobileCI\MobileCIAPIController::create()->getActivationView();
            }
        );

        Route::get('/customer/logout', 'IntermediateLoginController@getLogoutMobileCI');

        // family page
        Route::get(
            '/customer/category',
            function () {

                return MobileCI\MobileCIAPIController::create()->getCategory();
            }
        );

        // track event popup click activity
        Route::post(
            '/app/v1/customer/eventpopupactivity',
            function () {

                return MobileCI\MobileCIAPIController::create()->postEventPopUpActivity();
            }
        );

        // track event popup display activity
        Route::post(
            '/app/v1/customer/displayeventpopupactivity',
            array(
            'as' => 'display-event-popup-activity',
            function () {

                return MobileCI\MobileCIAPIController::create()->postDisplayEventPopUpActivity();
            })
        );

        // track coupon popup display activity
        Route::post(
            '/app/v1/customer/displaycouponpopupactivity',
            array(
            'as' => 'display-coupon-popup-activity',
            function () {

                return MobileCI\MobileCIAPIController::create()->postDisplayCouponPopUpActivity();
            })
        );

        // track widget click activity
        Route::post(
            '/app/v1/customer/widgetclickactivity',
            array(
            'as' => 'click-widget-activity',
            function () {

                return MobileCI\MobileCIAPIController::create()->postClickWidgetActivity();
            })
        );

        Route::get(
            '/customer/tenants', ['as' => 'ci-tenant-list',
            function () {
                return MobileCI\MobileCIAPIController::create()->getTenantsView();
            }]
        );

        Route::get(
            '/customer/services', ['as' => 'ci-service-list',
            function () {
                return MobileCI\MobileCIAPIController::create()->getServiceView();
            }]
        );

        Route::get(
            '/customer/tenant', ['as' => 'ci-tenant-detail',
            function () {
                return MobileCI\MobileCIAPIController::create()->getTenantDetailView();
            }]
        );

        Route::get(
            '/customer/service', ['as' => 'ci-service-detail',
            function () {
                return MobileCI\MobileCIAPIController::create()->getServiceDetailView();
            }]
        );

        Route::group(
            array('before' => 'check-routes-luckydraw'),
            function() {
                Route::get(
                    '/customer/luckydraw', ['as' => 'ci-luckydraw-detail',
                    function () {
                        return MobileCI\MobileCIAPIController::create()->getLuckyDrawView();
                    }]
                );

                Route::get(
                    '/customer/luckydraws', ['as' => 'ci-luckydraw-list',
                    function () {
                        return MobileCI\MobileCIAPIController::create()->getLuckyDrawListView();
                    }]
                );

                Route::post(
                    '/app/v1/customer/luckydrawnumberpopup',
                    function () {

                        return MobileCI\MobileCIAPIController::create()->postLuckyNumberPopup();
                    }
                );

                Route::get(
                    '/customer/luckydraw-announcement', ['as' => 'ci-luckydraw-announcement',
                    function () {
                        return MobileCI\MobileCIAPIController::create()->getLuckyDrawAnnouncementView();
                    }]
                );

                Route::group(
                    array('before' => 'orbit-csrf'),
                    function() {
                        Route::post(
                            '/app/v1/customer/luckydraw-issue', ['as' => 'ci-luckydraw-auto-issue',
                            function () {
                                return MobileCI\MobileCIAPIController::create()->postLuckyDrawAutoIssue();
                            }]
                        );
                    }
                );
            }
        );

        Route::get(
            '/customer/mallcoupons', ['as' => 'ci-coupon-list',
            function () {
                return MobileCI\MobileCIAPIController::create()->getMallCouponList();
            }]
        );

        Route::get(
            '/customer/mallcoupon', ['as' => 'ci-coupon-detail',
            function () {

                return MobileCI\MobileCIAPIController::create()->getMallCouponDetailView();
            }]
        );

        Route::get(
            '/customer/mallpromotions', ['as' => 'ci-promotion-list',
            function () {
                return MobileCI\MobileCIAPIController::create()->getMallPromotionList();
            }]
        );

        Route::get(
            '/customer/mallpromotion', ['as' => 'ci-promotion-detail',
            function () {

                return MobileCI\MobileCIAPIController::create()->getMallPromotionDetailView();
            }]
        );

        Route::get(
            '/customer/mallnews', ['as' => 'ci-news-list',
            function () {
                return MobileCI\MobileCIAPIController::create()->getMallNewsList();
            }]
        );

        Route::get(
            '/customer/mallnewsdetail', ['as' => 'ci-news-detail',
            function () {
                return MobileCI\MobileCIAPIController::create()->getMallNewsDetailView();
            }]
        );

        Route::get(
            '/customer/pokestopdetail', ['as' => 'ci-pokesyop-detail',
            function () {
                return MobileCI\MobileCIAPIController::create()->getMallPokestopDetailView();
            }]
        );

        Route::get(
            '/customer/luckydrawnumber/download', ['as' => 'ci-luckydrawnumber-download',
            function () {
                return MobileCI\MobileCIAPIController::create()->getMallLuckyDrawDownloadList();
            }]
        );

        // set language from pop up selected language to cookies
        Route::post(
            '/customer/setlanguage',
            function () {
                return MobileCI\MobileCIAPIController::create()->postLanguagebySelected();
            }
        );


        /**
         * Read / flag the alert as read
         */
        Route::post('/app/v1/inbox/read', function()
        {
            return InboxAPIController::create()->postReadAlert();
        });


        /**
         * Read / flag the alert as notified
         */
        Route::post('/app/v1/inbox/notified', function()
        {
            return InboxAPIController::create()->postNotifiedMessage();
        });

        /**
         * Flag the alert as read / unread
         */
        Route::post('/app/v1/inbox/read-unread', function()
        {
            return InboxAPIController::create()->postReadUnreadAlert();
        });

        /**
         * Delete the alert
         */
        Route::post('/app/v1/inbox/delete', function()
        {
            return InboxAPIController::create()->postDeleteAlert();
        });

        /**
         * Poll new alert
         */
        Route::get('/app/v1/inbox/unread-count', function()
        {
            return InboxAPIController::create()->getPollMessages();
        });

        /**
         * Search inbox
         */
        Route::get('/app/v1/inbox/list', function()
        {
            return InboxAPIController::create()->getSearchInbox();
        });

        /**
         * My Account
         */
        Route::get('/customer/my-account', ['as' => 'ci-my-account',
            function() {
                return MobileCI\MobileCIAPIController::create()->getMyAccountView();
            }]
        );

        /**
         * Search campaign popup
         */
        Route::get('/app/v1/campaign/list', function()
        {
            return MobileCI\MobileCIAPIController::create()->getSearchCampaignCardsPopUp();
        });

        /**
         * Campaign popup activities
         */
        Route::post('/app/v1/campaign/activities', function()
        {
            return MobileCI\MobileCIAPIController::create()->postCampaignPopUpActivities();
        });

        /**
         * The power search
         */
        Route::get('/app/v1/keyword/search', function()
        {
            return MobileCI\MobileCIAPIController::create()->getPowerSearch();
        });

	    /**
         * Tenant load more
         */
        Route::get('/app/v1/tenant/load-more', function()
        {
            return MobileCI\MobileCIAPIController::create()->getSearchTenant();
        });

        /**
         * Service load more
         */
        Route::get('/app/v1/service/load-more', function()
        {
            return MobileCI\MobileCIAPIController::create()->getSearchService();
        });

        /**
         * Promotion load more
         */
        Route::get('/app/v1/promotion/load-more', function()
        {
            return MobileCI\MobileCIAPIController::create()->getSearchPromotion();
        });

        /**
         * News load more
         */
        Route::get('/app/v1/news/load-more', function()
        {
            return MobileCI\MobileCIAPIController::create()->getSearchNews();
        });

        /**
         * My coupon load more
         */
        Route::get('/app/v1/my-coupon/load-more', function()
        {
            return MobileCI\MobileCIAPIController::create()->getSearchCoupon();
        });

        /**
         * Lucky draw load more
         */
        Route::get('/app/v1/lucky-draw/load-more', function()
        {
            return MobileCI\MobileCIAPIController::create()->getSearchLuckyDraw();
        });

        /**
         * Add coupon to wallet API
         */
        Route::post('/app/v1/coupon/addtowallet', function()
        {
            return MobileCI\MobileCIAPIController::create()->postAddToWallet();
        });

        /**
         * Check user location
         */
        Route::post('/app/v1/customer/check-location', ['as' => 'ci-check-user-location', function()
            {
                return MobileCI\MobileCIAPIController::create()->postCheckUserLocation();
            }]
        );

        Route::get('/app/v1/customer/login-callback', ['as' => 'customer-login-callback', 'uses' => 'IntermediateLoginController@getCloudLoginCallback']);
        Route::get('/app/v1/customer/login-callback-show-id', ['as' => 'customer-login-callback-show-id', 'uses' => 'IntermediateLoginController@getCloudLoginCallbackShowId']);

        Route::post('/app/v1/customer/login', ['as' => 'ci-user-login-v2', function()
            {
                return MobileCI\MobileCIAPIController::create()->postLoginInShopV2();
            }]
        );
    }
);

