<?php namespace Orbit\Controller\API\v1\Pub\Article;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \URL;

use Article;
use ArticleCity;
use ArticleLinkToObject;
use Media;

use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use OrbitShop\API\v1\ResponseProvider;
use \Orbit\Helper\Exception\OrbitCustomException;
use Redis;
use Orbit\Controller\API\v1\Article\ArticleHelper;


class ArticleDetailAPIController extends PubControllerAPI
{

    /**
     * GET - get the article detail
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string slug
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getArticleDetail()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;

        try{
            $user = $this->getUser();

            $articleId = OrbitInput::get('article_id', null);
            $slug = OrbitInput::get('slug', null);
            $language = OrbitInput::get('language', 'id');
            $mallId = OrbitInput::get('mall_id', null);
            $country = OrbitInput::get('country', 'null');

            $articleHelper = ArticleHelper::create();
            $articleHelper->articleCustomValidator();
            $validator = Validator::make(
                array(
                    'slug' => $slug,
                    'language' => $language,
                ),
                array(
                    'slug' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                ),
                array(
                    'required' => 'Slug is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $articleHelper->getValidLanguage();

            $prefix = DB::getTablePrefix();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)";
            if ($usingCdn) {
                $image = "CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
            }

            $location = $mallId;
            if (empty($location)) {
                $location = 0;
            }

            // get now date
            date_default_timezone_set('Asia/jakarta');
            $nowDate = date("Y-m-d H:i:s");

            $article = Article::with('category')
                                ->with('video')
                                ->where('slug', $slug)
                                ->where('published_at', '<=', $nowDate)
                                ->first();


            $message = 'Request Ok';
            // If article is inactive/not found, then set custom data so that
            // frontend can take action properly.
            if (! is_object($article)) {
                $httpCode = 404;
                $customData = new \stdClass;
                $customData->type = 'article';
                $customData->location = 0;
                $customData->article = null;

                $this->response->data = $customData;
                $this->response->code = Article::NOT_FOUND_ERROR_CODE;
                $this->response->status = 'error';
                $this->response->message = $message;

                return $this->render($httpCode);
            }

            if ($article->status === 'inactive') {
                $httpCode = 404;
                $customData = new \stdClass;
                $customData->type = 'article';
                $customData->location = 0;
                $customData->status = 'inactive';

                $this->response->data = $customData;
                $this->response->code = Article::NOT_FOUND_ERROR_CODE;
                $this->response->status = 'error';
                $this->response->message = $message;

                return $this->render($httpCode);
            }

            $articleId = $article->article_id;

            // Get the object detail
            $objectNews = ArticleLinkToObject::select(
                    'news.news_id as news_id',
                    DB::Raw("
                        CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                        CASE WHEN (SELECT {$image}
                            FROM orb_media
                            WHERE {$prefix}media.media_name_long = 'news_translation_image_orig'
                            AND {$prefix}media.object_id = {$prefix}news_translations.news_translation_id) is null
                        THEN
                            (SELECT {$image}
                            FROM orb_media
                            WHERE {$prefix}media.media_name_long = 'news_translation_image_orig'
                            AND {$prefix}media.object_id = default_translation.news_translation_id)
                        ELSE
                            (SELECT {$image}
                            FROM orb_media
                            WHERE {$prefix}media.media_name_long = 'news_translation_image_orig'
                            AND {$prefix}media.object_id = {$prefix}news_translations.news_translation_id)
                        END AS original_media_path
                    "),
                    DB::raw("
                        CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                THEN {$prefix}campaign_status.campaign_status_name
                                ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                    FROM {$prefix}news_merchant onm
                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                    WHERE onm.news_id = {$prefix}news.news_id)
                        THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status
                    "),
                    'news.object_type',
                    'news.end_date',
                    'news.is_exclusive',
                    'news.is_having_reward',
                    'news.status',
                    DB::raw("default_translation.news_name as default_name")
                )
                ->join('news', function($q) {
                    $q->on('news_id', '=', 'object_id')
                      ->on('news.object_type', '=', DB::raw("'news'"))
                      ->on('news.status', '=', DB::raw("'active'"));
                })
                ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                ->leftJoin('news_translations', function ($q) use ($valid_language) {
                    $q->on('news_translations.news_id', '=', 'news.news_id')
                      ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                })
                ->leftJoin('news_translations as default_translation', function ($q) use ($prefix){
                    $q->on(DB::raw("default_translation.news_id"), '=', 'news.news_id')
                      ->on(DB::raw("default_translation.merchant_language_id"), '=', 'languages.language_id');
                })
                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                ->where('article_link_to_objects.object_type', 'news')
                ->where('article_id', $articleId)
                ->groupBy('news.news_id')
                ->orderBy('news.end_date', 'asc')
                ->get();

            $linkedNewss = [];
            foreach ($objectNews as $linkedNews) {
                if ($linkedNews->campaign_status == 'ongoing') {
                    $linkedNewss[] = $linkedNews;
                }
            }
            $article['object_news'] = $linkedNewss;


            $objectPromotion = ArticleLinkToObject::select(
                    'news.news_id as promotion_id',
                    DB::Raw("
                        CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as promotion_name,
                        CASE WHEN (SELECT {$image}
                            FROM orb_media
                            WHERE {$prefix}media.media_name_long = 'news_translation_image_orig'
                            AND {$prefix}media.object_id = {$prefix}news_translations.news_translation_id) is null
                        THEN
                            (SELECT {$image}
                            FROM orb_media
                            WHERE {$prefix}media.media_name_long = 'news_translation_image_orig'
                            AND {$prefix}media.object_id = default_translation.news_translation_id)
                        ELSE
                            (SELECT {$image}
                            FROM orb_media
                            WHERE {$prefix}media.media_name_long = 'news_translation_image_orig'
                            AND {$prefix}media.object_id = {$prefix}news_translations.news_translation_id)
                        END AS original_media_path
                    "),
                    DB::raw("
                        CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                THEN {$prefix}campaign_status.campaign_status_name
                                ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                    FROM {$prefix}news_merchant onm
                                        LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                        LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                        LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                    WHERE onm.news_id = {$prefix}news.news_id)
                        THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status
                    "),
                    'news.object_type',
                    'news.end_date',
                    'news.is_exclusive',
                    'news.is_having_reward',
                    DB::raw("default_translation.news_name as default_name")
                )
                ->join('news', function($q) {
                    $q->on('news_id', '=', 'object_id')
                      ->on('news.object_type', '=', DB::raw("'promotion'"))
                      ->on('news.status', '=', DB::raw("'active'"));
                })
                ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                ->leftJoin('news_translations', function ($q) use ($valid_language) {
                    $q->on('news_translations.news_id', '=', 'news.news_id')
                      ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                })
                ->leftJoin('news_translations as default_translation', function ($q) use ($prefix){
                    $q->on(DB::raw("default_translation.news_id"), '=', 'news.news_id')
                      ->on(DB::raw("default_translation.merchant_language_id"), '=', 'languages.language_id');
                })
                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                ->where('article_link_to_objects.object_type', 'promotion')
                ->where('article_id', $articleId)
                ->groupBy('news.news_id')
                ->orderBy('news.news_name', 'asc')
                ->get();

            $linkedPromotions = [];
            foreach ($objectPromotion as $linkedPromotion) {
                if ($linkedPromotion->campaign_status == 'ongoing') {
                    $linkedPromotions[] = $linkedPromotion;
                }
            }
            $article['object_promotion'] = $linkedPromotions;


            $objectCoupon = ArticleLinkToObject::select(
                    'promotions.promotion_id as coupon_id',
                    DB::Raw("
                        CASE WHEN ({$prefix}coupon_translations.promotion_name = '' or {$prefix}coupon_translations.promotion_name is null) THEN default_translation.promotion_name ELSE {$prefix}coupon_translations.promotion_name END as coupon_name,
                        CASE WHEN (SELECT {$image}
                            FROM orb_media
                            WHERE {$prefix}media.media_name_long = 'coupon_translation_image_orig'
                            AND {$prefix}media.object_id = {$prefix}coupon_translations.coupon_translation_id) is null
                        THEN
                            (SELECT {$image}
                            FROM orb_media
                            WHERE {$prefix}media.media_name_long = 'coupon_translation_image_orig'
                            AND {$prefix}media.object_id = default_translation.coupon_translation_id)
                        ELSE
                            (SELECT {$image}
                            FROM orb_media
                            WHERE {$prefix}media.media_name_long = 'coupon_translation_image_orig'
                            AND {$prefix}media.object_id = {$prefix}coupon_translations.coupon_translation_id)
                        END AS original_media_path
                    "),
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
                        THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status
                    "),
                    'promotions.end_date',
                    DB::raw("default_translation.promotion_name as default_name")
                )
                ->join('promotions', function($q) {
                    $q->on('promotion_id', '=', 'object_id')
                      ->on('promotions.status', '=', DB::raw("'active'"))
                      ->on('promotions.available', '!=', DB::raw("'0'"));
                })
                ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                ->leftJoin('coupon_translations', function ($q) use ($valid_language) {
                    $q->on('coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                      ->on('coupon_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                })
                ->leftJoin('coupon_translations as default_translation', function ($q) use ($prefix){
                    $q->on(DB::raw("default_translation.promotion_id"), '=', 'promotions.promotion_id')
                      ->on(DB::raw("default_translation.merchant_language_id"), '=', 'languages.language_id');
                })
                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                ->where('article_link_to_objects.object_type', 'coupon')
                ->where('article_id', $articleId)
                ->groupBy('promotions.promotion_id')
                ->orderBy('promotions.promotion_name', 'asc')
                ->get();

            $linkedCoupons = [];
            foreach ($objectCoupon as $linkedCoupon) {
                if ($linkedCoupon->campaign_status == 'ongoing') {
                    $linkedCoupons[] = $linkedCoupon;
                }
            }

            $article['object_coupon'] = $linkedCoupons;


            $objectMall = ArticleLinkToObject::select(
                    'merchants.merchant_id as mall_id',
                    'merchants.name',
                    DB::raw("{$image} as original_media_path")
                )
                ->join('merchants', function($q) {
                    $q->on('merchants.merchant_id', '=', 'object_id')
                      ->on('merchants.object_type', '=', DB::raw("'mall'"))
                      ->on('merchants.status', '=', DB::raw("'active'"));
                })
                ->leftJoin('media', function($q) {
                    $q->on('media.media_name_long', '=', DB::raw("'mall_logo_orig'"))
                      ->on('media.object_id', '=', 'merchants.merchant_id');
                })
                ->where('article_link_to_objects.object_type', 'mall')
                ->where('article_id',$articleId)
                ->groupBy('merchants.merchant_id')
                ->orderBy('merchants.name', 'asc')
                ->get();

            $article['object_mall'] = $objectMall;


            $objectProduct = ArticleLinkToObject::select(
                    'products.product_id',
                    'products.name',
                    DB::raw("{$image} as original_media_path")
                )
                ->join('products', function($q) {
                    $q->on('products.product_id', '=', 'object_id')
                      ->on('products.status', '=', DB::raw("'active'"));
                })
                ->leftJoin('media', function($q) {
                    $q->on('media.object_id', '=', 'products.product_id')
                      ->on('media.media_name_long', '=', DB::raw("'product_image_orig'"));
                })
                ->where('article_link_to_objects.object_type', 'product')
                ->where('article_id',$articleId)
                ->groupBy('products.product_id')
                ->orderBy('products.name', 'asc')
                ->get();

            $article['object_product'] = $objectProduct;

            $objectArticle = ArticleLinkToObject::select(
                    'articles.article_id',
                    'articles.slug',
                    'articles.title',
                    DB::raw("{$image} as original_media_path")
                )
                ->join('articles', function($q) {
                    $q->on('articles.article_id', '=', 'object_id')
                      ->on('articles.status', '=', DB::raw("'active'"));
                })
                ->leftJoin('media', function($q) {
                    $q->on('media.object_id', '=', 'articles.article_id')
                      ->on('media.media_name_long', '=', DB::raw("'article_cover_image_orig'"));
                })
                ->where('article_link_to_objects.object_type', 'article')
                ->where('article_link_to_objects.article_id', $articleId)
                ->groupBy('articles.article_id')
                ->orderBy('articles.published_at', 'desc')
                ->get();

            $article['object_article'] = $objectArticle;

            $objectPartner = ArticleLinkToObject::select(
                    'partners.partner_id',
                    'partners.partner_name',
                    DB::raw("{$image} as original_media_path")
                )
                ->join('partners', function($q) {
                    $q->on('partners.partner_id', '=', 'object_id')
                      ->on('partners.status', '=', DB::raw("'active'"));
                })
                ->leftJoin('media', function($q) {
                    $q->on('media.object_id', '=', 'partners.partner_id')
                      ->on('media.media_name_long', '=', DB::raw("'partner_logo_orig'"));
                })
                ->where('article_link_to_objects.object_type', 'partner')
                ->where('article_link_to_objects.article_id', $articleId)
                ->groupBy('partners.partner_id')
                ->get();

            $article['object_partner'] = $objectPartner;

            $objectMerchant = ArticleLinkToObject::select(
                    'merchants.merchant_id',
                    'merchants.name',
                    DB::raw("{$image} as original_media_path")
                )
                ->join('merchants', function($q) {
                    $q->on('merchant_id', '=', 'object_id')
                      ->on('merchants.status', '=', DB::raw("'active'"));
                })
                ->leftJoin('media', function($q) {
                    $q->on('media.media_name_long', '=', DB::raw("'retailer_logo_orig'"))
                      ->on('media.object_id', '=', 'merchants.merchant_id');
                })
                ->where('article_link_to_objects.object_type', 'merchant')
                ->where('article_id',$articleId)
                ->groupBy('merchants.merchant_id')
                ->orderBy('merchants.name')
                ->get();

            $article['object_merchant'] = $objectMerchant;

            $cities = ArticleCity::select('mall_cities.mall_city_id', 'mall_cities.city')
                ->join('mall_cities', 'mall_cities.mall_city_id', '=', 'article_cities.mall_city_id')
                ->groupBy('mall_cities.mall_city_id')
                ->get();

            $article['cities'] = $cities;


            $mediaCover = Media::select('media.*',DB::raw("{$image} as original_media_path"))
                ->where('media_name_id', 'article_cover_image')
                ->where('object_id',$articleId)
                ->get();

            $article['media_cover'] = $mediaCover;


            $mediaContent = Media::select('media.*',DB::raw("{$image} as original_media_path"))
                ->where('media_name_id', 'article_content_image')
                ->where('object_id',$articleId)
                ->get();

            $article['media_content'] = $mediaContent;


            $mall = null;
            $mallName = 'gtm';

            $activityNotes = sprintf('Page viewed: Landing Page Article Detail Page');
            $activity->setUser($user)
                ->setActivityName('view_landing_page_article_detail')
                ->setActivityNameLong('View GoToMalls Article Detail')
                ->setObject($article)
                ->setLocation($mall)
                ->setNews($article)
                ->setModuleName('Article')
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();

            // add facebook share url dummy page
            $article->facebook_share_url = SocMedAPIController::getSharedUrl('article', $article->article_id, $article->title, $country);

            $this->response->data = $article;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = $message;

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

        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getCustomData();
            if ($this->response->code === 4040) {
                $httpCode = 404;
            } else {
                $httpCode = 500;
            }

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
