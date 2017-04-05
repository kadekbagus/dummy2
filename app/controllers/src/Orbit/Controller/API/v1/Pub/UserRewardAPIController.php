<?php namespace Orbit\Controller\API\v1\Pub;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use Activity;
use stdClass;
use Validator;
use Language;
use App;
use Lang;
use DB;
use UserReward;
use Orbit\Helper\Util\PaginationNumber;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\CdnUrlGenerator;

class UserRewardAPIController extends PubControllerAPI
{
    /**
     * get - get list promotional event history per user
     *
     * @author firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string language       (required)
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getUserReward()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;

            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $sort_by = OrbitInput::get('sortby', 'redeemed_date');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'language' => $language,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->getValidLanguage();

            $prefix = DB::getTablePrefix();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
            if ($usingCdn) {
                $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
            }

            $userReward = UserReward::select(
                            'news.news_id as news_id',
                            DB::Raw("
                                        CASE WHEN ({$prefix}news_translations.news_name = '' or {$prefix}news_translations.news_name is null) THEN default_translation.news_name ELSE {$prefix}news_translations.news_name END as name,
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
                             // query for get status active based on timezone
                            DB::raw("
                                    CASE
                                        WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name
                                        WHEN {$prefix}campaign_status.campaign_status_name = 'stopped' THEN 'expired'
                                        ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                                                        FROM {$prefix}news_merchant onm
                                                                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                                        WHERE onm.news_id = {$prefix}news.news_id)
                                    THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status
                            "),
                            'news.end_date'
                        )
                        //Join for get news and descriprion
                        ->join('reward_details', 'reward_details.reward_detail_id', '=', 'user_rewards.reward_detail_id')
                        ->join('news', 'reward_details.object_id', '=', 'news.news_id')
                        // Join for get translation
                        ->leftJoin('news_translations', function ($q) use ($valid_language) {
                            $q->on('news_translations.news_id', '=', 'news.news_id')
                              ->on('news_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })
                        // Join for get default language
                        ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                        ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                        ->leftJoin('news_translations as default_translation', function ($q) use ($prefix){
                            $q->on(DB::raw("default_translation.news_id"), '=', 'news.news_id')
                              ->on(DB::raw("default_translation.merchant_language_id"), '=', 'languages.language_id');
                        })
                        //Join for get campaign status
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->where('user_rewards.user_id', $user->user_id)
                        ->whereIn('user_rewards.status', array('redeemed', 'pending'))
                        //Default Order by
                        ->orderBy('redeemed_date', 'desc')
                        ->orderBy('campaign_status', 'desc')
                        ->groupBy('user_reward_id');

            $_coupon = clone $userReward;

            $take = PaginationNumber::parseTakeFromGet('coupon');
            $userReward->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $userReward->skip($skip);

            $listUserReward = $userReward->get();
            $count = RecordCounter::create($_coupon)->count();

            if (empty($skip)) {
                $activityNotes = '';
                $activity->setUser($user)
                    ->setActivityName('view_my_reward_page')
                    ->setActivityNameLong('View My Reward Page')
                    ->setObject(null)
                    ->setLocation('GTM')
                    ->setModuleName('Application')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listUserReward);
            $this->response->data->records = $listUserReward;
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

        $output = $this->render($httpCode);

        return $output;
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