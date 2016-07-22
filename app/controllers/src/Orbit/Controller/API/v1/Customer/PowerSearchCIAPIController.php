<?php namespace Orbit\Controller\API\v1\Customer;

/**
 * @author Firmansyah <firmansyah@dominopos.com>
 * @desc Controller for searching Mobile CI Angular
 */
use Orbit\Controller\API\v1\Customer\BaseAPIController;
use Orbit\Helper\Net\UrlChecker as UrlBlock;
use OrbitShop\API\v1\ResponseProvider;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \Carbon\Carbon as Carbon;
use \Validator;
use Mall;
use OrbitShop\API\v1\OrbitShopAPI;
use Activity;
use Setting;
use URL;
use App;
use User;
use Language;
use \stdclass;


class PowerSearchCIAPIController extends BaseAPIController
{
	protected $validRoles = ['super admin', 'consumer', 'guest'];

    /**
     * GET - Get search tenant, coupon, news, promotion, lucky draw
     *
     * @param string    `keyword`        (required) - The search keyword
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getPowerSearch() {
        $user = null;
        $keyword = null;
        $activityPage = Activity::mobileci()
                        ->setActivityType('search');

        try {
            $httpCode = 200;

            $keyword = (string) trim(urldecode(OrbitInput::get('keyword')));
            if (empty($keyword) && $keyword !== '0') {
                throw new Exception ('Empty keyword.');
            }

            // Require authentication
            $user = $this->getLoggedInUser();

            UrlBlock::checkBlockedUrl($user);
            $retailer = $this->getRetailerInfo();


            $lang = OrbitInput::get('lang', 'en');

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'id_language_default' => $lang,
                ),
                array(
                    'id_language_default' => 'orbit.empty.language_default',
                )
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $language = \Language::where('name', '=', $lang)->first();

            $alternateLanguage = null;
            if (is_object($language)) {
                $alternateLanguage = \MerchantLanguage::excludeDeleted()
                    ->where('merchant_id', '=', $retailer->merchant_id)
                    ->where('language_id', '=', $language->language_id)
                    ->first();
            }

            //$alternateLanguage = $this->getAlternateMerchantLanguage($user, $retailer);
            $mallTime = Carbon::now($retailer->timezone->timezone_name);
            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge = $this->calculateAge($user->userDetail->birthdate);
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender = $user->userDetail->gender;
            }

            $prefix = DB::getTablePrefix();

            $mallid = $retailer->merchant_id;

            $promo = DB::table('news')
                ->selectRaw("{$prefix}news.news_id as object_id, {$prefix}news.news_name as object_name, {$prefix}news.description as object_description, {$prefix}news.image as object_image, 'promotion' as object_type")
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->leftJoin('news_translations', function($join) use ($language){
                    $join->on('news.news_id', '=', 'news_translations.news_id');
                    $join->where('news_translations.merchant_language_id', '=', $language->language_id);
                })
                ->leftJoin('keyword_object', function($join) {
                    $join->on('news.news_id', '=', 'keyword_object.object_id');
                    $join->where('keyword_object.object_type', '=', 'promotion');
                })
                ->leftJoin('keywords', function($join) use ($retailer) {
                    $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                    $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
                })
                ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                ->where(function ($q) use ($mallid) {
                            $q->where('merchants.parent_id', '=', $mallid)
                              ->orWhere('merchants.merchant_id', '=', $mallid);
                })
                ->where('news.object_type', '=', 'promotion')
                ->where('news.status', 'active')
                ->whereRaw("? between begin_date and end_date", [$mallTime])
                ->where(function($q) use ($keyword) {
                    $q->where('news_translations.news_name', 'like', "%$keyword%")
                        ->orWhere('news_translations.description', 'like', "%$keyword%")
                        ->orWhere('keyword', '=', $keyword);
                });

            $news = DB::table('news')
                ->selectRaw("{$prefix}news.news_id as object_id, {$prefix}news.news_name as object_name, {$prefix}news.description as object_description, {$prefix}news.image as object_image, 'news' as object_type")
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->leftJoin('news_translations', function($join) use ($language){
                    $join->on('news.news_id', '=', 'news_translations.news_id');
                    $join->where('news_translations.merchant_language_id', '=', $language->language_id);
                })
                ->leftJoin('keyword_object', function($join) {
                    $join->on('news.news_id', '=', 'keyword_object.object_id');
                    $join->where('keyword_object.object_type', '=', 'news');
                })
                ->leftJoin('keywords', function($join) use ($retailer) {
                    $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                    $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
                })
                ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                ->where(function ($q) use ($mallid) {
                            $q->where('merchants.parent_id', '=', $mallid)
                              ->orWhere('merchants.merchant_id', '=', $mallid);
                })
                ->where('news.object_type', '=', 'news')
                ->where('news.status', 'active')
                ->whereRaw("? between begin_date and end_date", [$mallTime])
                ->where(function($q) use ($keyword) {
                    $q->where('news_translations.news_name', 'like', "%$keyword%")
                        ->orWhere('news_translations.description', 'like', "%$keyword%")
                        ->orWhere('keyword', '=', $keyword);
                });

            $coupon = DB::table('promotions')
                ->selectRaw("{$prefix}issued_coupons.promotion_id as object_id, {$prefix}promotions.promotion_name as object_name, {$prefix}promotions.description as object_description, {$prefix}promotions.image as object_image, 'coupon' as object_type")
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'promotions.promotion_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'promotions.promotion_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->leftJoin('coupon_translations', function($join) use ($language){
                    $join->on('promotions.promotion_id', '=', 'coupon_translations.promotion_id');
                    $join->where('coupon_translations.merchant_language_id', '=', $language->language_id);
                })
                ->leftJoin('keyword_object', function($join) {
                    $join->on('promotions.promotion_id', '=', 'keyword_object.object_id');
                    $join->where('keyword_object.object_type', '=', 'coupon');
                })
                ->leftJoin('keywords', function($join) use ($retailer) {
                    $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                    $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
                })
                ->leftJoin('issued_coupons', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                ->where(function ($q) use ($mallid) {
                        $q->where('merchants.parent_id', '=', $mallid)
                          ->orWhere('merchants.merchant_id', '=', $mallid);
                    })
                ->where('issued_coupons.expired_date', '>=', $mallTime)
                ->where('issued_coupons.user_id', '=', $user->user_id)
                ->where('issued_coupons.status', '=', 'active')
                ->where('is_coupon', '=', 'Y')
                ->where('promotions.status', 'active')
                ->where(function($q) use ($keyword) {
                    $q->where('coupon_translations.promotion_name', 'like', "%$keyword%")
                        ->orWhere('coupon_translations.description', 'like', "%$keyword%")
                        ->orWhere('coupon_translations.long_description', 'like', "%$keyword%")
                        ->orWhere('keyword', '=', $keyword);
                });

            $tenant = DB::table('merchants')
                ->selectRaw("{$prefix}merchants.merchant_id as object_id, {$prefix}merchants.name as object_name, {$prefix}merchants.description as object_description, {$prefix}media.path as object_image, 'tenant' as object_type, COUNT(DISTINCT {$prefix}merchants.merchant_id) as counter")
                ->leftJoin('merchant_translations', function($join) use ($language){
                    $join->on('merchants.merchant_id', '=', 'merchant_translations.merchant_id');
                    $join->where('merchant_translations.merchant_language_id', '=', $language->language_id);
                })
                ->leftJoin('keyword_object', function($join) {
                    $join->on('merchants.merchant_id', '=', 'keyword_object.object_id');
                    $join->where('keyword_object.object_type', '=', 'tenant');
                })
                ->leftJoin('keywords', function($join) use ($retailer) {
                    $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                    $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
                })
                ->leftJoin('media', function($join) {
                    $join->on('merchants.merchant_id', '=', 'media.object_id')
                        ->where('media.media_name_id', '=', 'retailer_logo')
                        ->where('media.media_name_long', 'like', '%_orig');
                })
                ->where('merchants.object_type', '=', 'tenant')
                ->where('merchants.status', 'active')
                ->where('parent_id', $retailer->merchant_id)
                ->where(function($q) use ($keyword) {
                    $q->where('merchants.name', 'like', "%$keyword%")
                        ->orWhere('merchant_translations.description', 'like', "%$keyword%")
                        ->orWhere('keyword', '=', $keyword);
                });

            $service = DB::table('merchants')
                ->selectRaw("{$prefix}merchants.merchant_id as object_id, {$prefix}merchants.name as object_name, {$prefix}merchants.description as object_description, {$prefix}media.path as object_image, 'service' as object_type, COUNT(DISTINCT {$prefix}merchants.merchant_id) as counter")
                ->leftJoin('merchant_translations', function($join) use ($language){
                    $join->on('merchants.merchant_id', '=', 'merchant_translations.merchant_id');
                    $join->where('merchant_translations.merchant_language_id', '=', $language->language_id);
                })
                ->leftJoin('keyword_object', function($join) {
                    $join->on('merchants.merchant_id', '=', 'keyword_object.object_id');
                    $join->where('keyword_object.object_type', '=', 'tenant');
                })
                ->leftJoin('keywords', function($join) use ($retailer) {
                    $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
                    $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
                })
                ->leftJoin('media', function($join) {
                    $join->on('merchants.merchant_id', '=', 'media.object_id')
                        ->where('media.media_name_id', '=', 'service_logo')
                        ->where('media.media_name_long', 'like', '%_orig');
                })
                ->where('merchants.object_type', '=', 'service')
                ->where('merchants.status', 'active')
                ->where('parent_id', $retailer->merchant_id)
                ->where(function($q) use ($keyword) {
                    $q->where('merchants.name', 'like', "%$keyword%")
                        ->orWhere('merchant_translations.description', 'like', "%$keyword%")
                        ->orWhere('keyword', '=', $keyword);
                });

            $lucky_draw = DB::table('lucky_draws')
                ->selectRaw("{$prefix}lucky_draws.lucky_draw_id as object_id, {$prefix}lucky_draws.lucky_draw_name as object_name, {$prefix}lucky_draws.description as object_description, {$prefix}lucky_draws.image as object_image, 'lucky_draw' as object_type")
                ->leftJoin('lucky_draw_translations', function($join) use ($alternateLanguage){
                    $join->on('lucky_draws.lucky_draw_id', '=', 'lucky_draw_translations.lucky_draw_id');
                    $join->where('lucky_draw_translations.merchant_language_id', '=', $alternateLanguage->language_id);
                })
                ->where('lucky_draws.status', 'active')
                ->where('mall_id', $retailer->merchant_id)
                ->whereRaw("? between start_date and grace_period_date", [$mallTime])
                ->where(function($q) use ($keyword) {
                    $q->where('lucky_draw_translations.lucky_draw_name', 'like', "%$keyword%")
                        ->orWhere('lucky_draw_translations.description', 'like', "%$keyword%");
                });

            if ($userGender !== null) {
                $promo = $promo->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                $news = $news->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                $coupon = $coupon->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
            }

            if ($userAge !== null) {
                if ($userAge === 0){
                    $promo = $promo->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    $news = $news->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    $coupon = $coupon->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                } else {
                    if ($userAge >= 55) {
                        $promo = $promo->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                        $news = $news->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                        $coupon = $coupon->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                    } else {
                        $promo = $promo->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                        $news = $news->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                        $coupon = $coupon->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    }
                }
            }

            $_promo = clone($promo);
            $_news = clone($news);
            $_coupon = clone($coupon);
            $_tenant = clone($tenant);
            $_service = clone($service);
            $_lucky_draw = clone($lucky_draw);

            $limit = 0; // <---- Config;
            $promo->groupBy('news.news_id')->orderBy(DB::raw('RAND()'))->limit($limit);
            $news->groupBy('news.news_id')->orderBy(DB::raw('RAND()'))->limit($limit);
            $coupon->groupBy('promotions.promotion_id')->orderBy(DB::raw('RAND()'))->limit($limit);
            $tenant->groupBy('merchants.merchant_id')->orderBy(DB::raw('RAND()'))->limit($limit);
            $service->groupBy('merchants.merchant_id')->orderBy(DB::raw('RAND()'))->limit($limit);
            $lucky_draw->groupBy('lucky_draws.lucky_draw_id')->orderBy(DB::raw('RAND()'))->limit($limit);

            $search_results = [];

            foreach ($promo->get() as $promo_result) {
                $search_results[] = $promo_result;
            }
            foreach ($news->get() as $news_result) {
                $search_results[] = $news_result;
            }
            foreach ($coupon->get() as $coupon_result) {
                $search_results[] = $coupon_result;
            }
            foreach ($tenant->get() as $tenant_result) {
                $search_results[] = $tenant_result;
            }
            foreach ($service->get() as $service_result) {
                $search_results[] = $service_result;
            }
            foreach ($lucky_draw->get() as $lucky_draw_result) {
                $search_results[] = $lucky_draw_result;
            }

            $grouped_search_result = new stdclass();
            $grouped_search_result->tenants = [];
            $grouped_search_result->tenants_counts = $_tenant->count();
            $grouped_search_result->tenants_url = UrlBlock::blockedRoute('ci-tenant-list', ['keyword' => htmlspecialchars($keyword)], $this->session);
            // $grouped_search_result->tenants_redirect_url = URL::route('ci-tenant-list', ['keyword' => htmlspecialchars($keyword)]);
            $grouped_search_result->services = [];
            $grouped_search_result->services_counts = $_service->count();
            $grouped_search_result->services_url = UrlBlock::blockedRoute('ci-service-list', ['keyword' => htmlspecialchars($keyword)], $this->session);
            // $grouped_search_result->services_redirect_url = URL::route('ci-service-list', ['keyword' => htmlspecialchars($keyword)]);
            $grouped_search_result->news = [];
            $grouped_search_result->news_counts = $_news->count();
            $grouped_search_result->news_url = UrlBlock::blockedRoute('ci-news-list', ['keyword' => htmlspecialchars($keyword)], $this->session);
            // $grouped_search_result->news_redirect_url = URL::route('ci-news-list', ['keyword' => htmlspecialchars($keyword)]);
            $grouped_search_result->promotions = [];
            $grouped_search_result->promotions_counts = $_promo->count();
            $grouped_search_result->promotions_url = UrlBlock::blockedRoute('ci-promotion-list', ['keyword' => htmlspecialchars($keyword)], $this->session);
            // $grouped_search_result->promotions_redirect_url = URL::route('ci-promotion-list', ['keyword' => htmlspecialchars($keyword)]);
            $grouped_search_result->coupons = [];
            $grouped_search_result->coupons_counts = count($_coupon->groupBy('promotions.promotion_id')->get());
            $grouped_search_result->coupons_url = UrlBlock::blockedRoute('ci-coupon-list', ['keyword' => htmlspecialchars($keyword)], $this->session);
            // $grouped_search_result->coupons_redirect_url = URL::route('ci-coupon-list', ['keyword' => htmlspecialchars($keyword)]);
            $grouped_search_result->lucky_draws = [];
            $grouped_search_result->lucky_draws_counts = $_lucky_draw->count();
            $grouped_search_result->lucky_draws_url = UrlBlock::blockedRoute('ci-luckydraw-list', ['keyword' => htmlspecialchars($keyword)], $this->session);
            // $grouped_search_result->lucky_draws_redirect_url = URL::route('ci-luckydraw-list', ['keyword' => htmlspecialchars($keyword)]);

            foreach($search_results as $near_end_result) {
                if ($near_end_result->object_type === 'promotion') {
                    $near_end_result->object_url = UrlBlock::blockedRoute('ci-promotion-detail', ['id' => $near_end_result->object_id], $this->session);
                    // $near_end_result->object_redirect_url = URL::route('ci-promotion-detail', ['id' => $near_end_result->object_id]);
                    $near_end_result->object_image = URL::asset('mobile-ci/images/default_promotion.png');
                } elseif ($near_end_result->object_type === 'news') {
                    $near_end_result->object_url = UrlBlock::blockedRoute('ci-news-detail', ['id' => $near_end_result->object_id], $this->session);
                    // $near_end_result->object_redirect_url = URL::route('ci-news-detail', ['id' => $near_end_result->object_id]);
                    $near_end_result->object_image = URL::asset('mobile-ci/images/default_news.png');
                } elseif ($near_end_result->object_type === 'coupon') {
                    $near_end_result->object_url = UrlBlock::blockedRoute('ci-coupon-detail', ['id' => $near_end_result->object_id], $this->session);
                    // $near_end_result->object_redirect_url = URL::route('ci-coupon-detail', ['id' => $near_end_result->object_id]);
                    $near_end_result->object_image = URL::asset('mobile-ci/images/default_coupon.png');
                } elseif ($near_end_result->object_type === 'tenant') {
                    $near_end_result->object_url = UrlBlock::blockedRoute('ci-tenant-detail', ['id' => $near_end_result->object_id], $this->session);
                    // $near_end_result->object_redirect_url = URL::route('ci-tenant-detail', ['id' => $near_end_result->object_id]);
                    if (! is_null($near_end_result->object_image)) {
                        $near_end_result->object_image = URL::asset($near_end_result->object_image);
                    } else {
                        $near_end_result->object_image = URL::asset('mobile-ci/images/default_tenants_directory.png');
                    }
                } elseif ($near_end_result->object_type === 'service') {
                    $near_end_result->object_url = UrlBlock::blockedRoute('ci-service-detail', ['id' => $near_end_result->object_id], $this->session);
                    // $near_end_result->object_redirect_url = URL::route('ci-service-detail', ['id' => $near_end_result->object_id]);
                    if (! is_null($near_end_result->object_image)) {
                        $near_end_result->object_image = URL::asset($near_end_result->object_image);
                    } else {
                        $near_end_result->object_image = URL::asset('mobile-ci/images/default_services_directory.png');
                    }
                } elseif ($near_end_result->object_type === 'lucky_draw') {
                    $near_end_result->object_url = UrlBlock::blockedRoute('ci-luckydraw-detail', ['id' => $near_end_result->object_id], $this->session);
                    // $near_end_result->object_redirect_url = URL::route('ci-luckydraw-detail', ['id' => $near_end_result->object_id]);
                    $near_end_result->object_image = URL::asset('mobile-ci/images/default_lucky_number.png');
                }

                if (!empty($alternateLanguage)) {
                    if ($near_end_result->object_type === 'promotion' || $near_end_result->object_type === 'news') {
                        $objectTranslation = \NewsTranslation::excludeDeleted()
                            ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                            ->where('news_id', $near_end_result->object_id)->first();
                    } elseif ($near_end_result->object_type === 'coupon'){
                        $objectTranslation = \CouponTranslation::excludeDeleted()
                            ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                            ->where('promotion_id', $near_end_result->object_id)->first();
                    } elseif ($near_end_result->object_type === 'tenant' || $near_end_result->object_type === 'service'){
                        $objectTranslation = \MerchantTranslation::excludeDeleted()
                            ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                            ->where('merchant_id', $near_end_result->object_id)->first();
                    } elseif ($near_end_result->object_type === 'lucky_draw'){
                        $objectTranslation = \LuckyDrawTranslation::excludeDeleted()
                            ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                            ->where('lucky_draw_id', $near_end_result->object_id)->first();
                    }

                    if (!empty($objectTranslation)) {
                        if ($near_end_result->object_type === 'promotion' || $near_end_result->object_type === 'news') {

                            //if field translation empty or null, value of field back to english (default)
                            if (isset($objectTranslation->news_name) && $objectTranslation->news_name !== '') {
                                $near_end_result->object_name = $objectTranslation->news_name;
                            }
                            if (isset($objectTranslation->description) && $objectTranslation->description !== '') {
                                $near_end_result->object_description = $objectTranslation->description;
                            }

                            $media = $objectTranslation->find($objectTranslation->news_translation_id)
                                ->media_orig()
                                ->first();

                            if (is_object($media)) {
                                $near_end_result->object_image = URL::asset($media->path);
                            } else {
                                // back to default image if in the content multilanguage not have image
                                // check the system language
                                $defaultLanguage = $this->getDefaultLanguage($retailer);
                                if ($defaultLanguage !== NULL) {
                                    $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                        ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                        ->where('news_id', $near_end_result->object_id)->first();

                                    // get default image
                                    $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                        ->media_orig()
                                        ->first();

                                    if (is_object($mediaDefaultLanguage)) {
                                        $near_end_result->object_image = URL::asset($mediaDefaultLanguage->path);
                                    }
                                }
                            }
                        } elseif ($near_end_result->object_type === 'coupon') {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($objectTranslation->promotion_name) && $objectTranslation->promotion_name !== '') {
                                $near_end_result->object_name = $objectTranslation->promotion_name;
                            }
                            if (isset($objectTranslation->description) && $objectTranslation->description !== '') {
                                $near_end_result->object_description = $objectTranslation->description;
                            }

                            $media = $objectTranslation->find($objectTranslation->coupon_translation_id)
                                ->media_orig()
                                ->first();

                            if (is_object($media)) {
                                $near_end_result->object_image = URL::asset($media->path);
                            } else {
                                // back to default image if in the content multilanguage not have image
                                // check the system language
                                $defaultLanguage = $this->getDefaultLanguage($retailer);
                                if ($defaultLanguage !== NULL) {
                                    $contentDefaultLanguage = \CouponTranslation::excludeDeleted()
                                        ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                        ->where('promotion_id', $near_end_result->object_id)->first();

                                    // get default image
                                    $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->coupon_translation_id)
                                        ->media_orig()
                                        ->first();

                                    if (is_object($mediaDefaultLanguage)) {
                                        $near_end_result->object_image = URL::asset($mediaDefaultLanguage->path);
                                    }
                                }
                            }
                        } elseif ($near_end_result->object_type === 'tenant' || $near_end_result->object_type === 'service') {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($objectTranslation->description) && $objectTranslation->description !== '') {
                                $near_end_result->object_description = $objectTranslation->description;
                            }
                        } elseif ($near_end_result->object_type === 'lucky_draw') {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($objectTranslation->lucky_draw_name) && $objectTranslation->lucky_draw_name !== '') {
                                $near_end_result->object_name = $objectTranslation->lucky_draw_name;
                            }
                            if (isset($objectTranslation->description) && $objectTranslation->description !== '') {
                                $near_end_result->object_description = $objectTranslation->description;
                            }

                            $media = $objectTranslation->find($objectTranslation->lucky_draw_translation_id)
                                ->media_orig()
                                ->first();

                            if (is_object($media)) {
                                $near_end_result->object_image = URL::asset($media->path);
                            } else {
                                // back to default image if in the content multilanguage not have image
                                // check the system language
                                $defaultLanguage = $this->getDefaultLanguage($retailer);
                                if ($defaultLanguage !== NULL) {
                                    $contentDefaultLanguage = \LuckyDrawTranslation::excludeDeleted()
                                        ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                        ->where('lucky_draw_id', $near_end_result->object_id)->first();

                                    // get default image
                                    $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->lucky_draw_translation_id)
                                        ->media_orig()
                                        ->first();

                                    if (is_object($mediaDefaultLanguage)) {
                                        $near_end_result->object_image = URL::asset($mediaDefaultLanguage->path);
                                    }
                                }
                            }
                        }
                    }
                }

                $near_end_result->object_description = nl2br($near_end_result->object_description);

                if ($near_end_result->object_type === 'promotion') {
                    $grouped_search_result->promotions[] = $near_end_result;
                } elseif ($near_end_result->object_type === 'news') {
                    $grouped_search_result->news[] = $near_end_result;
                } elseif ($near_end_result->object_type === 'coupon') {
                    $grouped_search_result->coupons[] = $near_end_result;
                } elseif ($near_end_result->object_type === 'tenant') {
                    $grouped_search_result->tenants[] = $near_end_result;
                } elseif ($near_end_result->object_type === 'service') {
                    $grouped_search_result->services[] = $near_end_result;
                } elseif ($near_end_result->object_type === 'lucky_draw') {
                    $grouped_search_result->lucky_draws[] = $near_end_result;
                }
            }

            $data = new stdclass();
            $data->total_records = count($search_results);
            $data->returned_records = count($search_results);
            $data->records = $search_results;
            $data->grouped_records = $grouped_search_result;

            if (count($search_results) === 0) {
                $data->records = null;
                $this->response->message = 'No results found.';
            }

            $activityPageNotes = sprintf('Keyword Searched: %s', $keyword);
            $activityPage->setUser($user)
                    ->setActivityName('search_keyword')
                    ->setActivityNameLong('Search')
                    ->setObject(null)
                    ->setModuleName('Search')
                    ->setNotes($activityPageNotes)
                    ->responseOK()
                    ->save();

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            switch ($e->getCode()) {
                case Session::ERR_UNKNOWN;
                case Session::ERR_IP_MISS_MATCH;
                case Session::ERR_UA_MISS_MATCH;
                case Session::ERR_SESS_NOT_FOUND;
                case Session::ERR_SESS_EXPIRE;
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->grouped_records = null;
                    $data->message = 'session_expired';

                    $this->response->data = $data;

                    break;

                default:
                    $data = new stdclass();
                    $data->total_records = 0;
                    $data->returned_records = 0;
                    $data->records = null;
                    $data->grouped_records = null;
                    $data->message = $e->getMessage();

                    $this->response->data = $data;
            }
        }

        $output = $this->render($httpCode);

        return $output;
    }



    protected function registerCustomValidation()
    {
        // Check the existance of id_language_default
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {

            $language = \Language::where('name', '=', $value)->first();

            if (empty($language)) {
                return false;
            }

            return true;
        });

    }

}