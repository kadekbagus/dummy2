<?php namespace Orbit\Controller\API\v1\Pub;

/**
 * An API controller for managing Social Media Share Page.
 */
use Log;
use OrbitShop\API\v1\OrbitShopAPI;
use SocMed\Facebook;
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \View;
use \Lang;
use \Language;
use \MerchantLanguage;
use \Validator;
use \Config;
use \Retailer;
use \Product;
use \Promotion;
use \Coupon;
use \News;
use \CartCoupon;
use \IssuedCoupon;
use Carbon\Carbon as Carbon;
use \stdclass;
use \Exception;
use \DB;
use \Activity;
use \LuckyDraw;
use URL;
use PDO;
use Response;
use OrbitShop\API\v1\Helper\Generator;
use Event;
use \Mall;
use \Tenant;
use Redirect;

class SocMedAPIController extends PubControllerAPI
{
    /**
     * GET - FB Promotion Share dummy page
     *
     * @param string    `id`          (required)
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getPromotionDetailView()
    {
        $languageEnId = null;
        $language = Language::where('name', 'en')->first();

        if (! empty($language)) {
            $languageEnId = $language->language_id;
        }

        $id = OrbitInput::get('id');
        $country = OrbitInput::get('country', null);
        $cities = OrbitInput::get('cities', null);

        $prefix = DB::getTablePrefix();

        $promotion = News::with(['translations' => function($q) use ($languageEnId) {
                        $q->addSelect(['news_translation_id', 'news_id']);
                        $q->with(['media' => function($q2) {
                            $q2->addSelect(['object_id', 'media_name_long', 'path']);
                        }]);
                        $q->where('merchant_language_id', $languageEnId);
                    }])
                    ->select(
                        'news.news_id as news_id',
                        'news_translations.news_name as news_name',
                        'news.object_type',
                        'news_translations.description as description',
                        'news.end_date',
                        'media.path as original_media_path',
                        // query for get status active based on timezone
                        DB::raw("
                                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                        THEN {$prefix}campaign_status.campaign_status_name
                                        ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                    FROM {$prefix}news_merchant onm
                                                                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                    WHERE onm.news_id = {$prefix}news.news_id)
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status,
                                CASE WHEN (SELECT count(onm.merchant_id)
                                            FROM {$prefix}news_merchant onm
                                                LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                            WHERE onm.news_id = {$prefix}news.news_id
                                            AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}news.begin_date and {$prefix}news.end_date) > 0
                                THEN 'true' ELSE 'false' END AS is_started
                        ")
                    )
                    ->join('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                    ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                    ->leftJoin('media', function($q) {
                        $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                        $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                    })
                    ->where('news.news_id', $id)
                    ->where('news_translations.merchant_language_id', '=', $languageEnId)
                    ->where('news.object_type', '=', 'promotion')
                    ->where('news_translations.news_name', '!=', '')
                    ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                    ->first();

        if (! is_object($promotion)) {
            // item not found
            $data = $this->createEmptyViewData();

            return View::make('mobile-ci.templates.fb-sharer', compact('data'));
        }

        $data = new stdclass();
        $data->url = static::getSharedUrl('promotion', $promotion->news_id, $promotion->news_name, $country, $cities);
        $data->title = $promotion->news_name;
        $data->description = $promotion->description;
        $data->mall = new stdclass();
        $data->mall->name = 'Gotomalls.com';

        if (empty($promotion->original_media_path)) {
            $data->image_url = NULL;
        } else {
            $data->image_url = $promotion->original_media_path;
        }

        $data->image_dimension = $this->getImageDimension($promotion->original_media_path);

        return View::make('mobile-ci.templates.fb-sharer', compact('data'));
    }

    /**
     * GET - FB News Share dummy page
     *
     * @param string    `id`          (required)
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getNewsDetailView()
    {
        $languageEnId = null;
        $language = Language::where('name', 'en')->first();

        if (! empty($language)) {
            $languageEnId = $language->language_id;
        }


        $id = OrbitInput::get('id');
        $country = OrbitInput::get('country', null);
        $cities = OrbitInput::get('cities', null);

        $prefix = DB::getTablePrefix();

        $news = News::with(['translations' => function($q) use ($languageEnId) {
                        $q->addSelect(['news_translation_id', 'news_id']);
                        $q->with(['media' => function($q2) {
                            $q2->addSelect(['object_id', 'media_name_long', 'path']);
                        }]);
                        $q->where('merchant_language_id', $languageEnId);
                    }])
                    ->select(
                        'news.news_id as news_id',
                        'news_translations.news_name as news_name',
                        'news.object_type',
                        'news_translations.description as description',
                        'news.end_date',
                        'media.path as original_media_path',
                        // query for get status active based on timezone
                        DB::raw("
                                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                        THEN {$prefix}campaign_status.campaign_status_name
                                        ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                    FROM {$prefix}news_merchant onm
                                                                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                    WHERE onm.news_id = {$prefix}news.news_id)
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status,
                                CASE WHEN (SELECT count(onm.merchant_id)
                                            FROM {$prefix}news_merchant onm
                                                LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                            WHERE onm.news_id = {$prefix}news.news_id
                                            AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}news.begin_date and {$prefix}news.end_date) > 0
                                THEN 'true' ELSE 'false' END AS is_started
                        ")
                    )
                    ->join('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                    ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                    ->leftJoin('media', function($q) {
                        $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                        $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                    })
                    ->where('news.news_id', $id)
                    ->where('news_translations.merchant_language_id', '=', $languageEnId)
                    ->where('news.object_type', '=', 'news')
                    ->where('news_translations.news_name', '!=', '')
                    ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                    ->first();

        if (! is_object($news)) {
            // item not found
            $data = $this->createEmptyViewData();

            return View::make('mobile-ci.templates.fb-sharer', compact('data'));
        }

        $data = new stdclass();
        $data->url = static::getSharedUrl('news', $news->news_id, $news->news_name, $country, $cities);
        $data->title = $news->news_name;
        $data->description = $news->description;
        $data->mall = new stdclass();
        $data->mall->name = 'Gotomalls.com';

        if (empty($news->original_media_path)) {
            $data->image_url = NULL;
        } else {
            $data->image_url = $news->original_media_path;
        }

        $data->image_dimension = $this->getImageDimension($news->original_media_path);

        return View::make('mobile-ci.templates.fb-sharer', compact('data'));
    }

    /**
     * GET - FB Store Share dummy page
     *
     * @param string    `name`          (required)
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getStoreDetailView()
    {
        $languageEnId = null;
        $language = Language::where('name', 'en')->first();

        if (! empty($language)) {
            $languageEnId = $language->language_id;
        }

        $storeId = OrbitInput::get('id');
        $country = OrbitInput::get('country', null);
        $cities = OrbitInput::get('cities', null);

        $prefix = DB::getTablePrefix();

        $store = Tenant::select(
                            'merchants.merchant_id',
                            'merchants.name',
                            DB::Raw("CASE WHEN (
                                            select mt.description
                                            from {$prefix}merchant_translations mt
                                            where mt.merchant_id = {$prefix}merchants.merchant_id
                                                and mt.merchant_language_id = '$languageEnId'
                                        ) = ''
                                        THEN {$prefix}merchants.description
                                        ELSE (
                                            select mt.description
                                            from {$prefix}merchant_translations mt
                                            where mt.merchant_id = {$prefix}merchants.merchant_id
                                                and mt.merchant_language_id = '$languageEnId'
                                        )
                                    END as description
                                "),
                            'merchants.url'
                        )
            ->with(['mediaLogo' => function ($q) {
                $q->select(
                        'media.path',
                        'media.object_id'
                    );
            }, 'mediaImageOrig' => function ($q) {
                $q->select(
                        'media.path',
                        'media.object_id'
                    );
            }, 'mediaImageCroppedDefault' => function ($q) {
                $q->select(
                        'media.path',
                        'media.object_id'
                    );
            }])
            ->join(DB::raw("(select merchant_id, status, parent_id from {$prefix}merchants where object_type = 'mall') as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
            ->where('merchants.status', 'active')
            ->whereRaw("oms.status = 'active'")
            ->where('merchants.merchant_id', $storeId)
            ->orderBy('merchants.created_at', 'asc')
            ->first();

        if (! is_object($store)) {
            // item not found
            $data = $this->createEmptyViewData();

            return View::make('mobile-ci.templates.fb-sharer', compact('data'));
        }

        $data = new stdclass();
        $data->url = static::getSharedUrl('store', $store->merchant_id, $store->name, $country, $cities);
        $data->title = $store->name;
        $data->description = $store->description;
        $data->mall = new stdclass();
        $data->mall->name = 'Gotomalls.com';

        if (empty($store->mediaLogo)) {
            $data->image_url = NULL;
        } else {
            $data->image_url = $store->mediaLogo[0]->path;
        }

        $data->image_dimension = $this->getImageDimension($store->mediaLogo[0]->path);

        return View::make('mobile-ci.templates.fb-sharer', compact('data'));
    }

    /**
     * GET - FB Coupon Share dummy page
     *
     * @param string    `id`          (required)
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getCouponDetailView()
    {
        $languageEnId = null;
        $language = Language::where('name', 'en')->first();

        if (! empty($language)) {
            $languageEnId = $language->language_id;
        }

        $id = OrbitInput::get('id');
        $country = OrbitInput::get('country', null);
        $cities = OrbitInput::get('cities', null);

        $prefix = DB::getTablePrefix();

        $coupon = Coupon::with(['translations' => function($q) use ($languageEnId) {
                        $q->addSelect(['coupon_translation_id', 'promotion_id']);
                        $q->with(['media' => function($q2) {
                            $q2->addSelect(['object_id', 'media_name_long', 'path']);
                        }]);
                        $q->where('merchant_language_id', $languageEnId);
                    }])
                    ->select(
                        'promotions.promotion_id as promotion_id',
                        'coupon_translations.promotion_name as promotion_name',
                        'coupon_translations.description as description',
                        'promotions.end_date',
                        'media.path as original_media_path',
                        // query for get status active based on timezone
                        DB::raw("
                                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                        THEN {$prefix}campaign_status.campaign_status_name
                                        ELSE (CASE WHEN {$prefix}promotions.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                    FROM {$prefix}promotion_retailer opr
                                                                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = opr.retailer_id
                                                                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                    WHERE opr.promotion_id = {$prefix}promotions.promotion_id)
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status,
                                CASE WHEN (SELECT count(opr.retailer_id)
                                            FROM {$prefix}promotion_retailer opr
                                                LEFT JOIN {$prefix}merchants om ON om.merchant_id = opr.retailer_id
                                                LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                            WHERE opr.promotion_id = {$prefix}promotions.promotion_id
                                            AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}promotions.begin_date and {$prefix}promotions.end_date) > 0
                                THEN 'true' ELSE 'false' END AS is_started
                        ")
                    )
                    ->join('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                    ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                    ->leftJoin('media', function($q) {
                        $q->on('media.object_id', '=', 'coupon_translations.coupon_translation_id');
                        $q->on('media.media_name_long', '=', DB::raw("'coupon_translation_image_orig'"));
                    })
                    ->where('promotions.promotion_id', $id)
                    ->where('coupon_translations.merchant_language_id', '=', $languageEnId)
                    ->where('coupon_translations.promotion_name', '!=', '')
                    ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                    ->first();

        if (! is_object($coupon)) {
            // item not found
            $data = $this->createEmptyViewData();

            return View::make('mobile-ci.templates.fb-sharer', compact('data'));
        }

        $data = new stdclass();
        $data->url = static::getSharedUrl('coupon', $coupon->promotion_id, $coupon->promotion_name, $country, $cities);
        $data->title = $coupon->promotion_name;
        $data->description = $coupon->description;
        $data->mall = new stdclass();
        $data->mall->name = 'Gotomalls.com';

        if (empty($coupon->original_media_path)) {
            $data->image_url = NULL;
        } else {
            $data->image_url = $coupon->original_media_path;
        }

        $data->image_dimension = $this->getImageDimension($coupon->original_media_path);

        return View::make('mobile-ci.templates.fb-sharer', compact('data'));
    }


    /**
     * GET - FB Promotional Event Share dummy page
     *
     * @param string    `id`          (required)
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getPromotionalEventDetailView()
    {
        $languageEnId = null;
        $language = Language::where('name', 'en')->first();

        if (! empty($language)) {
            $languageEnId = $language->language_id;
        }


        $id = OrbitInput::get('id');
        $country = OrbitInput::get('country', null);
        $cities = OrbitInput::get('cities', null);

        $prefix = DB::getTablePrefix();

        $news = News::with(['translations' => function($q) use ($languageEnId) {
                        $q->addSelect(['news_translation_id', 'news_id']);
                        $q->with(['media' => function($q2) {
                            $q2->addSelect(['object_id', 'media_name_long', 'path']);
                        }]);
                        $q->where('merchant_language_id', $languageEnId);
                    }])
                    ->select(
                        'news.news_id as news_id',
                        'news_translations.news_name as news_name',
                        'news.object_type',
                        'news_translations.description as description',
                        'news.end_date',
                        'media.path as original_media_path',
                        // query for get status active based on timezone
                        DB::raw("
                                CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                        THEN {$prefix}campaign_status.campaign_status_name
                                        ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                    FROM {$prefix}news_merchant onm
                                                                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                    WHERE onm.news_id = {$prefix}news.news_id)
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status,
                                CASE WHEN (SELECT count(onm.merchant_id)
                                            FROM {$prefix}news_merchant onm
                                                LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                            WHERE onm.news_id = {$prefix}news.news_id
                                            AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}news.begin_date and {$prefix}news.end_date) > 0
                                THEN 'true' ELSE 'false' END AS is_started
                        ")
                    )
                    ->join('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                    ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                    ->leftJoin('media', function($q) {
                        $q->on('media.object_id', '=', 'news_translations.news_translation_id');
                        $q->on('media.media_name_long', '=', DB::raw("'news_translation_image_orig'"));
                    })
                    ->where('news.news_id', $id)
                    ->where('news_translations.merchant_language_id', '=', $languageEnId)
                    ->where('news.object_type', '=', 'news')
                    ->where('news.is_having_reward', '=', 'Y')
                    ->where('news_translations.news_name', '!=', '')
                    ->havingRaw("campaign_status = 'ongoing' AND is_started = 'true'")
                    ->first();

        if (! is_object($news)) {
            // item not found
            $data = $this->createEmptyViewData();

            return View::make('mobile-ci.templates.fb-sharer', compact('data'));
        }

        $data = new stdclass();
        $data->url = static::getSharedUrl('promotional-event', $news->news_id, $news->news_name, $country, $cities);
        $data->title = $news->news_name;
        $data->description = $news->description;
        $data->mall = new stdclass();
        $data->mall->name = 'Gotomalls.com';

        if (empty($news->original_media_path)) {
            $data->image_url = NULL;
        } else {
            $data->image_url = $news->original_media_path;
        }

        $data->image_dimension = $this->getImageDimension($news->original_media_path);

        return View::make('mobile-ci.templates.fb-sharer', compact('data'));
    }

    /**
     * GET - FB Article Share dummy page
     *
     * @param string    `id`          (required)
     *
     * @return Illuminate\View\View
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getArticleDetailView()
    {
        $languageEnId = null;
        $language = Language::where('name', 'en')->first();

        if (! empty($language)) {
            $languageEnId = $language->language_id;
        }


        $id = OrbitInput::get('id');
        $country = OrbitInput::get('country', null);
        $cities = OrbitInput::get('cities', null);

        $prefix = DB::getTablePrefix();

        $article = Article::where('article_id', $articleId)
                            ->where('status', 'active')
                            ->with('mediaCover')
                            ->first();

        if (! is_object($article)) {
            // item not found
            $data = $this->createEmptyViewData();

            return View::make('mobile-ci.templates.fb-sharer', compact('data'));
        }

        $data = new stdclass();
        $data->url = static::getSharedUrl('promotional-event', $article->article_id, $article->title, $country, $cities);
        $data->title = $article->title;
        $data->description = $article->description;
        $data->mall = new stdclass();
        $data->mall->name = 'Gotomalls.com';

        if (empty($article->media_cover[0]->path)) {
            $data->image_url = NULL;
        } else {
            $data->image_url = $article->media_cover[0]->path;
        }

        $data->image_dimension = $this->getImageDimension($article->media_cover[0]->path);

        return View::make('mobile-ci.templates.fb-sharer', compact('data'));
    }

    /**
     * Static method to get shared url
     *
     * @param string $type - ('promotion' | 'news' | 'coupon') - (required)
     * @param string $id - Object ID - (required)
     * @param string $name - Object Name - (optional)
     * @return string $url | Exception
     */
    public static function getSharedUrl($type, $id, $name = '', $country = null, $cities = null)
    {
        Config::set('orbit.session.availability.query_string', false);
        $routeName = NULL;
        $params = array();
        switch ($type) {
            case 'promotion':
                $routeName = 'pub-share-promotion';
                break;
            case 'news':
                $routeName = 'pub-share-news';
                break;
            case 'coupon':
                $routeName = 'pub-share-coupon';
                break;
            case 'article':
                $routeName = 'pub-share-article';
                break;
            case 'store':
                $routeName = 'pub-share-store';
                break;

            case 'promotional-event':
                $routeName = 'pub-share-promotional-event';
                break;

            default:
                OrbitShopAPI::throwInvalidArgument('Sharing type should be provided');
                break;
        }

        if (empty($id)) {
            OrbitShopAPI::throwInvalidArgument('Shared ID should be provided');
        }
        $params['id'] = $id;
        if (! empty($name)) {
            $params['name'] = rawurlencode($name);
        }

        if (! is_null($country)) {
            $params['country'] = rawurlencode($country);
        }

        if (! is_null($cities)) {
            foreach((array) $cities as $city) {
                $params['cities'][] = rawurlencode($city);
            }
        }

        $url = URL::route($routeName, $params);

        return $url;
    }

    private function getImageDimension($url = '') {
        try {
            if(empty($url)) {
                return NULL;
            }

            list($width, $height) = getimagesize($url);

            $dimension = [$width, $height];

            return $dimension;
        } catch (Exception $e) {
            return NULL;
        }
    }

    /**
     * Returns an appropriate MerchantLanguage (if any) that the user wants and the mall supports.
     *
     * @param \Mall $mall the mall
     * @return \MerchantLanguage the language or null if a matching one is not found.
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    private function getDefaultLanguage($mall)
    {
        $language = \Language::where('name', '=', $mall->mobile_default_language)->first();
        if (isset($language) && count($language) > 0) {
            $defaultLanguage = \MerchantLanguage::excludeDeleted()
                ->where('merchant_id', '=', $mall->merchant_id)
                ->where('language_id', '=', $language->language_id)
                ->first();

            if ($defaultLanguage !== null) {
                return $defaultLanguage;
            }
        }

        // above methods did not result in any selected language, use mall default
        return null;
    }

    /**
     *
     */
    private function getEnglishLanguage($mall)
    {
        $language = \Language::where('name', '=', 'en')->first();
        if (is_object($language)) {
            $englishLanguage = \MerchantLanguage::excludeDeleted()
                ->where('merchant_id', '=', $mall->merchant_id)
                ->where('language_id', '=', $language->language_id)
                ->first();

            if ($englishLanguage !== null) {
                return $englishLanguage;
            }
        }

        // above methods did not result in any selected language, use mall default
        return null;
    }

    private function createEmptyViewData()
    {
        $data = new stdclass();
        $data->url = 'http://www.gotomalls.com';
        $data->title = '';
        $data->description = '';
        $data->mall = new stdclass();
        $data->mall->name = 'Gotomalls.com';
        $data->image_url = NULL;
        $data->image_dimension = NULL;

        return $data;
    }
}
