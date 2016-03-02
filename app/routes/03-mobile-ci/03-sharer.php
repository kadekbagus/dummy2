<?php

Route::group(
    array('before' => 'fb-bot'),
    function () {
		Route::get('/sharer/facebook/tenant-detail',
			array(
				'as' => 'share-tenant',
			 	function()
				{
				    return MobileCI\SocMedMobileCIAPIController::create()->getTenantDetailView();
				}
			)
		);

		Route::get('/sharer/facebook/promotion-detail',
			array(
				'as' => 'share-promotion',
			 	function()
				{
		    		return MobileCI\SocMedMobileCIAPIController::create()->getPromotionDetailView();
				}
			)
		);

		Route::get('/sharer/facebook/news-detail',
			array(
				'as' => 'share-news',
			 	function()
				{
				    return MobileCI\SocMedMobileCIAPIController::create()->getNewsDetailView();
				}
			)
		);

		Route::get('/sharer/facebook/coupon-detail',
			array(
				'as' => 'share-coupon',
			 	function()
				{
				    return MobileCI\SocMedMobileCIAPIController::create()->getCouponDetailView();
				}
			)
		);

		Route::group(
            array('before' => 'check-routes-luckydraw-alternative'),
            function() {
                Route::get('/sharer/facebook/luckydraw-detail',
                	array(
						'as' => 'share-lucky-draw',
					 	function()
	                	{
	                    	return MobileCI\SocMedMobileCIAPIController::create()->getLuckyDrawDetailView();
	                	}
                	)
            	);
            }
        );

        Route::get('/sharer/facebook/home',
			array(
				'as' => 'share-home',
			 	function()
				{
				    return MobileCI\SocMedMobileCIAPIController::create()->getHomeView();
				}
			)
		);
	}
);

// Route::get('/sharer/facebook/tenant', function()
// {
//     return MobileCI\SocMedMobileCIAPIController::create()->getTenantDetailView();
//});
