<?php

Route::group(
    array('before' => 'orbit-settings'),
    function () {

        Route::get(
            '/customer', ['as' => 'mobile-ci.signin',
            function () {

                return MobileCI\MobileCIAPIController::create()->getSignInView();
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
            '/customer/cart',
            function () {

                return MobileCI\MobileCIAPIController::create()->getCartView();
            }
        );

        Route::get(
            '/customer/catalogue',
            function () {

                return MobileCI\MobileCIAPIController::create()->getCatalogueView();
            }
        );

        Route::get(
            '/customer/product',
            function () {

                return MobileCI\MobileCIAPIController::create()->getProductView();
            }
        );

        Route::get(
            '/customer/transfer',
            function () {

                return MobileCI\MobileCIAPIController::create()->getTransferCartView();
            }
        );

        Route::get(
            '/customer/payment',
            function () {

                return MobileCI\MobileCIAPIController::create()->getPaymentView();
            }
        );

        Route::get(
            '/customer/paypalpayment',
            function () {

                return MobileCI\MobileCIAPIController::create()->getPaypalPaymentView();
            }
        );

        Route::get(
            '/customer/thankyou',
            function () {

                return MobileCI\MobileCIAPIController::create()->getThankYouView();
            }
        );

        Route::get(
            '/customer/welcome',
            function () {

                return MobileCI\MobileCIAPIController::create()->getWelcomeView();
            }
        );

        Route::get(
            '/customer/search',
            function () {

                return MobileCI\MobileCIAPIController::create()->getSearchProduct();
            }
        );

        Route::get(
            '/customer/promotion',
            function () {

                return MobileCI\MobileCIAPIController::create()->getSearchPromotion();
            }
        );

        Route::get(
            '/customer/promotions',
            function () {

                return MobileCI\MobileCIAPIController::create()->getPromotionList();
            }
        );

        // Route::get(
        //     '/customer/notifications',
        //     function () {

        //         return MobileCI\MobileCIControllerNotifications::create()->getNotificationsView();
        //     }
        // );

        Route::get(
            '/customer/messages',
            function () {

                return MobileCI\MobileCIAPIController::create()->getNotificationsView();
            }
        );

        // Route::get(
        //     '/customer/notification/detail',
        //     function () {

        //         return MobileCI\MobileCIControllerNotifications::create()->getNotificationDetailView();
        //         //return View::make('mobile-ci.mall-notifications-list');
        //     }
        // );

        Route::get(
            '/customer/message/detail',
            function () {

                return MobileCI\MobileCIAPIController::create()->getNotificationDetailView();
            }
        );

        Route::get(
            '/customer/coupon',
            function () {

                return MobileCI\MobileCIAPIController::create()->getSearchCoupon();
            }
        );

        Route::get(
            '/customer/coupons',
            function () {

                return MobileCI\MobileCIAPIController::create()->getCouponList();
            }
        );

        Route::get(
            '/customer/activation',
            function () {

                return MobileCI\MobileCIAPIController::create()->getActivationView();
            }
        );

        Route::get('/customer/logout', 'IntermediateLoginController@getLogoutMobileCI');

        // get product listing for families
        Route::get(
            '/app/v1/customer/products',
            function () {

                return MobileCI\MobileCIAPIController::create()->getProductList();
            }
        );

        // add to cart
        Route::post(
            '/app/v1/customer/addtocart',
            function () {

                return MobileCI\MobileCIAPIController::create()->postAddToCart();
            }
        );

        // update cart
        Route::post(
            '/app/v1/customer/updatecart',
            function () {

                return MobileCI\MobileCIAPIController::create()->postUpdateCart();
            }
        );

        // delete from cart
        Route::post(
            '/app/v1/customer/deletecart',
            function () {

                return MobileCI\MobileCIAPIController::create()->postDeleteFromCart();
            }
        );

        // cart product pop up
        Route::post(
            '/app/v1/customer/cartproductpopup',
            function () {

                return MobileCI\MobileCIAPIController::create()->postCartProductPopup();
            }
        );

        // cart cart-based-promo pop up
        Route::post(
            '/app/v1/customer/cartpromopopup',
            function () {

                return MobileCI\MobileCIAPIController::create()->postCartPromoPopup();
            }
        );

        // cart cart-based-coupon pop up
        Route::post(
            '/app/v1/customer/cartcouponpopup',
            function () {

                return MobileCI\MobileCIAPIController::create()->postCartCouponPopup();
            }
        );

        // catalogue product-based-coupon pop up
        Route::post(
            '/app/v1/customer/productcouponpopup',
            function () {

                return MobileCI\MobileCIAPIController::create()->postProductCouponPopup();
            }
        );

        // cart product-based-coupon pop up
        Route::post(
            '/app/v1/customer/cartproductcouponpopup',
            function () {

                return MobileCI\MobileCIAPIController::create()->postCartProductCouponPopup();
            }
        );

        // delete coupon from cart
        Route::post(
            '/app/v1/customer/deletecouponcart',
            function () {

                return MobileCI\MobileCIAPIController::create()->postDeleteCouponFromCart();
            }
        );

        // add cart based coupon to cart
        Route::post(
            '/app/v1/customer/addcouponcarttocart',
            function () {

                return MobileCI\MobileCIAPIController::create()->postAddCouponCartToCart();
            }
        );

        // add cart based coupon to cart
        Route::post(
            '/app/v1/customer/closecart',
            function () {

                return MobileCI\MobileCIAPIController::create()->postCloseCart();
            }
        );

        // family page
        Route::get(
            '/customer/category',
            function () {

                return MobileCI\MobileCIAPIController::create()->getCategory();
            }
        );

        // add product based coupon to cart
        Route::post(
            '/app/v1/customer/addcouponproducttocart',
            function () {

                return MobileCI\MobileCIAPIController::create()->postAddProductCouponToCart();
            }
        );

        // save transaction and show ticket
        Route::post(
            '/customer/savetransaction',
            function () {

                return MobileCI\MobileCIAPIController::create()->postSaveTransaction();
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

        // track save receipt click activity
        Route::post(
            '/app/v1/customer/savereceiptclickactivity',
            array(
            'as' => 'click-save-receipt-activity',
            function () {

                return MobileCI\MobileCIAPIController::create()->postClickSaveReceiptActivity();
            })
        );

        // track checkout button click activity
        Route::post(
            '/app/v1/customer/checkoutclickactivity',
            array(
            'as' => 'click-checkout-activity',
            function () {

                return MobileCI\MobileCIAPIController::create()->postClickCheckoutActivity();
            })
        );

        // send ticket to email
        Route::post(
            '/app/v1/customer/sendticket',
            array(
            'as' => 'send-ticket',
            function () {

                return MobileCI\MobileCIAPIController::create()->postSendTicket();
            })
        );

        // recognize me
        Route::get(
            '/customer/me',
            function () {

                return MobileCI\MobileCIAPIController::create()->getMeView();
            }
        );

        // reset cart
        Route::post(
            '/app/v1/customer/resetcart',
            array(
            'as' => 'reset-cart',
            function () {

                return MobileCI\MobileCIAPIController::create()->postResetCart();
            })
        );

        Route::get(
            '/customer/tenants', ['as' => 'ci-tenants',
            function () {
                return MobileCI\MobileCIAPIController::create()->getTenantsView();
            }]
        );

        Route::get(
            '/customer/tenant', ['as' => 'ci-tenant',
            function () {

                return MobileCI\MobileCIAPIController::create()->getTenantDetailView();
            }]
        );

        Route::group(
            array('before' => 'check-routes-luckydraw'),
            function() {
                Route::get(
                    '/customer/luckydraw', ['as' => 'ci-luckydraw',
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
            }
        );

        Route::get(
            '/customer/mallcoupons', ['as' => 'ci-mall-coupons',
            function () {
                return MobileCI\MobileCIAPIController::create()->getMallCouponList();
            }]
        );

        Route::get(
            '/customer/mallcoupon',
            function () {

                return MobileCI\MobileCIAPIController::create()->getMallCouponDetailView();
            }
        );

        Route::get(
            '/customer/mallcouponcampaign', ['as' => 'ci-mall-coupon-campaign',
            function () {

                return MobileCI\MobileCIAPIController::create()->getMallCouponCampaignDetailView();
            }]
        );

        Route::get(
            '/customer/mallpromotions', ['as' => 'ci-mall-promotions',
            function () {
                return MobileCI\MobileCIAPIController::create()->getMallPromotionList();
            }]
        );

        Route::get(
            '/customer/mallpromotion', ['as' => 'ci-mall-promotion',
            function () {

                return MobileCI\MobileCIAPIController::create()->getMallPromotionDetailView();
            }]
        );

        Route::get(
            '/customer/mallnews', ['as' => 'ci-mall-news',
            function () {
                return MobileCI\MobileCIAPIController::create()->getMallNewsList();
            }]
        );

        Route::get(
            '/customer/mallnewsdetail', ['as' => 'ci-mall-news-detail',
            function () {
                return MobileCI\MobileCIAPIController::create()->getMallNewsDetailView();
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
        Route::get('/customer/my-account', function()
        {
            return MobileCI\MobileCIAPIController::create()->getMyAccountView();
        });

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

        Route::get('/app/v1/customer/login-callback', ['as' => 'customer-login-callback', 'uses' => 'IntermediateLoginController@getCloudLoginCallback']);
        Route::get('/app/v1/customer/login-callback-show-id', ['as' => 'customer-login-callback-show-id', 'uses' => 'IntermediateLoginController@getCloudLoginCallbackShowId']);
    }
);
