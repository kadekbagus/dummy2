<?php

Route::group(
    array('before' => 'orbit-settings'),
    function () {
    
        Route::get(
            '/customer',
            function () {
        
                return MobileCI\MobileCIAPIController::create()->getSignInView();
            }
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
            '/customer/home',
            function () {
        
                return MobileCI\MobileCIAPIController::create()->getHomeView();
            }
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

        Route::post('/app/v1/customer/login', 'IntermediateLoginController@postLoginMobileCI');

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
    }
);
