<?php namespace Orbit\Controller\API\v1\Pub\PromotionalEvent;

/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for get detail page of promotional event
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
use \URL;
use Language;
use Validator;
use Activity;
use Mall;
use Partner;
use News;
use App;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use \Orbit\Helper\Exception\OrbitCustomException;
use Orbit\Helper\PromotionalEvent\PromotionalEventProcessor;

class PromotionalEventDetailAPIController extends PubControllerAPI
{
     public function getPromotionalEventItem()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;

        try{
            $user = $this->getUser();
            $role = $user->role->role_name;

            $newsId = OrbitInput::get('news_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');
            $country = OrbitInput::get('country', null);
            $cities = OrbitInput::get('cities', null);
            $partnerToken = OrbitInput::get('token', null);

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'promotion_id' => $newsId,
                    'language' => $language,
                ),
                array(
                    'promotion_id' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                ),
                array(
                    'required' => 'Promotion ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->getValidLanguage();

            $prefix = DB::getTablePrefix();
            App::setLocale($language);

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
            if ($usingCdn) {
                $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
            }

            $promotionalEvent = News::select(
                            'news.news_id as news_id',
                            'reward_detail_translations.guest_button_label',
                            'reward_detail_translations.logged_in_button_label',
                            'reward_details.is_new_user_only',
                            DB::Raw("
                                CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as news_name,
                                CASE WHEN ({$prefix}news_translations.description = '' or {$prefix}news_translations.description is null) THEN default_translation.description ELSE {$prefix}news_translations.description END as description,
                                CASE WHEN (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'news_translation_image_orig'
                                    AND m.object_id = {$prefix}news_translations.news_translation_id) is null
                                THEN
                                    (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'news_translation_image_orig'
                                    AND m.object_id = default_translation.news_translation_id)
                                ELSE
                                    (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'news_translation_image_orig'
                                    AND m.object_id = {$prefix}news_translations.news_translation_id)
                                END AS original_media_path
                            "),
                            'news.object_type',
                            'news.end_date',
                            'news.is_exclusive',
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
                            "),
                            // query for getting timezone for countdown on the frontend
                            DB::raw("
                                (SELECT
                                    ot.timezone_name
                                FROM {$prefix}news_merchant onm
                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                    LEFT JOIN {$prefix}merchants oms ON oms.merchant_id = om.parent_id
                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                WHERE onm.news_id = {$prefix}news.news_id
                                ORDER BY CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) ASC
                                LIMIT 1
                                ) as timezone
                            ")
                        )
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
                        // For get button label
                        ->join('reward_details', 'reward_details.object_id', '=', 'news.news_id')
                        ->leftJoin('reward_detail_translations', function ($q) use ($valid_language) {
                            $q->on('reward_detail_translations.reward_detail_id', '=', 'reward_details.reward_detail_id')
                              ->on('reward_detail_translations.language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })
                        ->where('news.news_id', $newsId)
                        ->where('news.object_type', '=', 'news')
                        ->where('news.is_having_reward', '=', 'Y')
                        ->first();

            $message = 'Request Ok';
            if (! is_object($promotionalEvent)) {
                OrbitShopAPI::throwInvalidArgument('Promotion that you specify is not found');
            }

            if ($promotionalEvent->is_exclusive === 'Y') {
                // check token
                $partnerTokens = Partner::leftJoin('object_partner', 'partners.partner_id', '=', 'object_partner.partner_id')
                                    ->where('object_partner.object_type', 'promotion')
                                    ->where('object_partner.object_id', $promotionalEvent->news_id)
                                    ->where('partners.is_exclusive', 'Y')
                                    ->where('partners.token', $partnerToken)
                                    ->first();

                if (! is_object($partnerTokens)) {
                    throw new OrbitCustomException('Promotion is exclusive, please specify partner token', News::IS_EXCLUSIVE_ERROR_CODE, NULL);
                }

                $promotionalEvent->is_exclusive = 'N';
            }

            $mall = null;
            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            // check promotional event access and get lucky_draw/promotion code
            $promotionalEvent->code = null;
            $promotionalEvent->message_title = null;
            $promotionalEvent->message_content = null;
            $promotionalEvent->code_message = null;
            $promotionalEvent->with_button = true;

            if ($role != 'Guest') {
                $promotionalEvent = PromotionalEventProcessor::format($user->user_id, $newsId, 'news', $language);
                $promotionalEvent->code = $promotionalEvent['code'];
                $promotionalEvent->message_title = $promotionalEvent['message_title'];
                $promotionalEvent->message_content = $promotionalEvent['message_content'];
                $promotionalEvent->code_message = Lang::get('label.promotional_event.code_message.' . $promotionalEvent['status']);
                $promotionalEvent->with_button = false;

                if ($promotionalEvent['status'] === 'play_button') {
                    $promotionalEvent->with_button = true;
                } elseif ($promotionalEvent['status'] === 'reward_ok') {
                    $updateReward = PromotionalEventProcessor::insertRewardCode($user->user_id, $newsId, 'news', $language);
                }
            }

            if (is_object($mall)) {
                $activityNotes = sprintf('Page viewed: View Promotional Event Page');
                $activity->setUser($user)
                    ->setActivityName('view_promotional_event_detail')
                    ->setActivityNameLong('View Promotional Event Page')
                    ->setObject($promotionalEvent)
                    ->setNews($promotionalEvent)
                    ->setLocation($mall)
                    ->setModuleName('Application')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            } else {
                $activityNotes = sprintf('Page viewed: View Promotional Event Page');
                $activity->setUser($user)
                    ->setActivityName('view_promotional_event_detail')
                    ->setActivityNameLong('View Promotional Event Page')
                    ->setObject($promotionalEvent)
                    ->setNews($promotionalEvent)
                    ->setLocation($mall)
                    ->setModuleName('Application')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            // add facebook share url dummy page
            $promotionalEvent->facebook_share_url = SocMedAPIController::getSharedUrl('promotional-event', $promotionalEvent->news_id, $promotionalEvent->news_name, $country, $cities);

            $this->response->data = $promotionalEvent;
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

    protected function registerCustomValidation()
    {
        // Check language is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->valid_language = $language;
            return TRUE;
        });
    }

    public function getValidLanguage()
    {
        return $this->valid_language;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }


}