<?php namespace Orbit\Controller\API\v1\Pub\Partner;

/**
 * @author Ahmad <ahmad@dominopos.com>
 * @desc Controller for Partner list and search in landing page
 */

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use Activity;
use Partner;
use Orbit\Controller\API\v1\Pub\Partner\PartnerHelper;
use Validator;
use Orbit\Helper\Database\Cache as OrbitDBCache;

class PartnerDetailAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    /**
     * GET - get partner list, and also provide for searching
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string keyword
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getPartnerDetail()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $keyword = null;
        $user = null;

        try{
            $user = $this->getUser();

            $partnerId = OrbitInput::get('partner_id');
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');
            $no_total_records = OrbitInput::get('no_total_records', null);

            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $partnerHelper = PartnerHelper::create();
            $partnerHelper->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'partner_id' => $partnerId,
                    'language' => $language,
                    'sortby'   => $sort_by,
                ),
                array(
                    'partner_id' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:name,created_date',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $partnerHelper->getValidLanguage();
            $prefix = DB::getTablePrefix();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $logo = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as logo_url";
            $image = "CONCAT({$this->quote($urlPrefix)}, image_media.path) as image_url";
            $photos = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as photo_url";
            if ($usingCdn) {
                $logo = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as logo_url";

                $image = "CASE WHEN (image_media.cdn_url is null or image_media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, image_media.path) ELSE image_media.cdn_url END as image_url";

                // photos and custom photos
                $photos = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as photo_url";
            }

            $partner = Partner::select(
                    'partners.partner_name',
                    'partners.partner_id',
                    'deeplinks.deeplink_url',
                    'partners.meta_title',
                    'partners.meta_description',
                    'partners.working_hours',
                    'partners.custom_photo_section_title',
                    'partners.button_color',
                    'partners.video_id_1',
                    'partners.video_id_2',
                    'partners.video_id_3',
                    'partners.video_id_4',
                    'partners.video_id_5',
                    'partners.video_id_6',
                    'partners.phone',
                    'partners.address',
                    'partners.city',
                    'partners.province',
                    'partners.url',
                    'partners.button_text_color as text_color',
                    DB::raw("{$logo}"),
                    DB::raw("{$image}"),
                    DB::Raw("
                        CASE WHEN ({$prefix}partner_translations.partner_button_label = '' or {$prefix}partner_translations.partner_button_label is null) THEN default_translation.partner_button_label ELSE {$prefix}partner_translations.partner_button_label END as button_label
                    "),
                    DB::Raw("
                        CASE WHEN ({$prefix}partner_translations.description = '' or {$prefix}partner_translations.description is null) THEN default_translation.description ELSE {$prefix}partner_translations.description END as description
                    "),
                    DB::Raw("
                        CASE WHEN ({$prefix}partner_translations.meta_title = '' or {$prefix}partner_translations.meta_title is null) THEN default_translation.meta_title ELSE {$prefix}partner_translations.meta_title END as meta_title
                    "),
                    DB::Raw("
                        CASE WHEN ({$prefix}partner_translations.meta_description = '' or {$prefix}partner_translations.meta_description is null) THEN default_translation.meta_description ELSE {$prefix}partner_translations.meta_description END as meta_description
                    "),
                    DB::raw("(SELECT social_media_uri FROM {$prefix}object_social_media
                                LEFT JOIN {$prefix}social_media on {$prefix}social_media.social_media_id = {$prefix}object_social_media.social_media_id
                              WHERE {$prefix}object_social_media.object_type = 'partner'
                              AND {$prefix}object_social_media.object_id = {$this->quote($partnerId)}
                              AND {$prefix}social_media.social_media_code = 'facebook'
                     ) AS facebook_url"),
                    DB::raw("(SELECT social_media_uri FROM {$prefix}object_social_media
                                LEFT JOIN {$prefix}social_media on {$prefix}social_media.social_media_id = {$prefix}object_social_media.social_media_id
                              WHERE {$prefix}object_social_media.object_type = 'partner'
                              AND {$prefix}object_social_media.object_id = {$this->quote($partnerId)}
                              AND {$prefix}social_media.social_media_code = 'twitter'
                     ) AS twitter_url"),
                    DB::raw("(SELECT social_media_uri FROM {$prefix}object_social_media
                                LEFT JOIN {$prefix}social_media on {$prefix}social_media.social_media_id = {$prefix}object_social_media.social_media_id
                              WHERE {$prefix}object_social_media.object_type = 'partner'
                              AND {$prefix}object_social_media.object_id = {$this->quote($partnerId)}
                              AND {$prefix}social_media.social_media_code = 'instagram'
                     ) AS instagram_url"),
                    DB::raw("(SELECT social_media_uri FROM {$prefix}object_social_media
                                LEFT JOIN {$prefix}social_media on {$prefix}social_media.social_media_id = {$prefix}object_social_media.social_media_id
                              WHERE {$prefix}object_social_media.object_type = 'partner'
                              AND {$prefix}object_social_media.object_id = {$this->quote($partnerId)}
                              AND {$prefix}social_media.social_media_code = 'youtube'
                     ) AS youtube_url"),
                    DB::raw("(SELECT social_media_uri FROM {$prefix}object_social_media
                                LEFT JOIN {$prefix}social_media on {$prefix}social_media.social_media_id = {$prefix}object_social_media.social_media_id
                              WHERE {$prefix}object_social_media.object_type = 'partner'
                              AND {$prefix}object_social_media.object_id = {$this->quote($partnerId)}
                              AND {$prefix}social_media.social_media_code = 'line'
                     ) AS line_url")
                )
                ->leftJoin('media', function ($q) {
                    $q->on('media.object_id', '=', 'partners.partner_id');
                    $q->on('media.object_name', '=', DB::raw("'partner'"));
                    $q->on('media.media_name_long', '=', DB::raw("'partner_logo_orig'"));
                })
                ->leftJoin('media as image_media', function ($q) {
                    $q->on(DB::raw("image_media.object_id"), '=', 'partners.partner_id');
                    $q->on(DB::raw("image_media.object_name"), '=', DB::raw("'partner'"));
                    $q->on(DB::raw("image_media.media_name_long"), '=', DB::raw("'partner_image_orig'"));
                })
                ->leftJoin('partner_translations', function ($q) use ($valid_language) {
                    $q->on('partner_translations.partner_id', '=', 'partners.partner_id')
                      ->on('partner_translations.language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                })
                ->leftJoin('languages', 'languages.name' , '=', 'partners.mobile_default_language')
                ->leftJoin('partner_translations as default_translation', function ($q) use ($prefix){
                    $q->on(DB::raw("default_translation.partner_id"), '=', 'partners.partner_id')
                      ->on(DB::raw("default_translation.language_id"), '=', 'languages.language_id');
                })
                // currently there is only one deeplink for partner,
                // should use lazy load maybe if multiple deeplink is applied
                ->leftJoin('deeplinks', function ($q) {
                    $q->on('deeplinks.object_id', '=', 'partners.partner_id');
                    $q->on('deeplinks.object_type', '=', DB::raw("'partner'"));
                    $q->on('deeplinks.status', '=', DB::raw("'active'"));
                })
                ->with([
                    'banners' => function($q) use ($photos) {
                        $q->select('link_url', 'is_outbound', 'partner_id', 'partner_banner_id')
                            ->with(['media' => function($q2) use ($photos) {
                                $q2->select(DB::raw("{$photos}"), 'object_id', 'media_name_long');
                            }]);
                    },
                    'mediaPhotos' => function($q) use ($photos) {
                        $q->select(DB::raw("{$photos}"), 'object_id', 'media_name_long');
                    },
                    'mediaCustomPhotos' => function($q) use ($photos) {
                        $q->select(DB::raw("{$photos}"), 'object_id', 'media_name_long');
                    }
                ])
                ->where('partners.status', 'active')
                ->where('partners.partner_id', $partnerId)
                ->groupBy('partners.partner_id')
                ->first();

            if (! is_object($partner)) {
                OrbitShopAPI::throwInvalidArgument('Partner that you specify is not found');
            }

            // Cache the result of database calls
            OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($partner);

            $this->response->data = $partner;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request OK';

            $activity->setUser($user)
                ->setActivityName('view_partner')
                ->setActivityNameLong('View Partner')
                ->setObject($partner)
                ->setModuleName('Partner')
                ->responseOK()
                ->save();

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
