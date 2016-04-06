<?php
/**
 * An API controller for managing News.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Carbon\Carbon as Carbon;

class NewsAPIController extends ControllerAPI
{
    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    protected $newsViewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    protected $newsModifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];

    /**
     * POST - Create New News
     *
     * @author Tian <tian@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     * @author Shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `mall_id`               (required) - Mall ID
     * @param string     `object_type`           (optional) - Object type. Valid value: promotion, news.
     * @param string     `news_name`             (required) - News name
     * @param string     `status`                (required) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`           (optional) - Description
     * @param file       `images`                (optional) - News image
     * @param datetime   `begin_date`            (optional) - Begin date. Example: 2015-04-15 00:00:00
     * @param datetime   `end_date`              (optional) - End date. Example: 2015-04-18 23:59:59
     * @param integer    `sticky_order`          (optional) - Sticky order.
     * @param string     `link_object_type`      (optional) - Link object type. Valid value: tenant, tenant_category.
     * @param array      `retailer_ids`          (optional) - Retailer IDs
     * @param integer    `id_language_default`   (optional) - ID language default
     * @param string     `is_all_gender`         (optional) - Is all gender. Valid value: Y, N.
     * @param string     `is_all_age`            (optional) - Is all retailer age group. Valid value: Y, N.
     * @param string     `gender_ids`            (optional) - for Male, Female. Unknown. Valid value: M, F, U.
     * @param string     `age_range_ids`         (optional) - Age Range IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewNews()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newnews = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.news.postnewnews.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.news.postnewnews.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.news.postnewnews.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_news')) {
                Event::fire('orbit.news.postnewnews.authz.notallowed', array($this, $user));
                $createNewsLang = Lang::get('validation.orbit.actionlist.new_news');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createNewsLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->newsViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.news.postnewnews.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $mall_id = OrbitInput::post('current_mall');;
            $news_name = OrbitInput::post('news_name');
            $object_type = OrbitInput::post('object_type');
            $campaignStatus = OrbitInput::post('campaign_status');
            $description = OrbitInput::post('description');
            $begin_date = OrbitInput::post('begin_date');
            $end_date = OrbitInput::post('end_date');
            $sticky_order = OrbitInput::post('sticky_order');
            $link_object_type = OrbitInput::post('link_object_type');
            $id_language_default = OrbitInput::post('id_language_default');
            $is_all_gender = OrbitInput::post('is_all_gender');
            $is_all_age = OrbitInput::post('is_all_age');
            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;
            $gender_ids = OrbitInput::post('gender_ids');
            $gender_ids = (array) $gender_ids;
            $age_range_ids = OrbitInput::post('age_range_ids');
            $age_range_ids = (array) $age_range_ids;
            $keywords = OrbitInput::post('keywords');
            $keywords = (array) $keywords;
            $is_popup = OrbitInput::post('is_popup');

            if (empty($campaignStatus)) {
                $campaignStatus = 'not started';
            }

            $status = 'inactive';
            if ($campaignStatus === 'ongoing') {
                $status = 'active';
            }

            $validator = Validator::make(
                array(
                    'news_name'           => $news_name,
                    'object_type'         => $object_type,
                    'status'              => $status,
                    'begin_date'          => $begin_date,
                    'end_date'            => $end_date,
                    'link_object_type'    => $link_object_type,
                    'id_language_default' => $id_language_default,
                    'is_all_gender'       => $is_all_gender,
                    'is_all_age'          => $is_all_age,
                    'is_popup'            => $is_popup,
                ),
                array(
                    'news_name'           => 'required|max:255|orbit.exists.news_name',
                    'object_type'         => 'required|orbit.empty.news_object_type',
                    'status'              => 'required|orbit.empty.news_status',
                    'link_object_type'    => 'orbit.empty.link_object_type',
                    'begin_date'          => 'required|date|orbit.empty.hour_format',
                    'end_date'            => 'required|date|orbit.empty.hour_format',
                    'id_language_default' => 'required|orbit.empty.language_default',
                    'is_all_gender'       => 'required|orbit.empty.is_all_gender',
                    'is_all_age'          => 'required|orbit.empty.is_all_age',
                    'is_popup'            => 'required|in:Y,N',
                ),
                array(
                    'is_popup.in' => 'is popup must Y or N',
                )
            );

            Event::fire('orbit.news.postnewnews.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            //to do : add validation for tenant

            foreach ($gender_ids as $gender_id_check) {
                $validator = Validator::make(
                    array(
                        'gender_id'   => $gender_id_check,
                    ),
                    array(
                        'gender_id'   => 'orbit.empty.gender',
                    )
                );

                Event::fire('orbit.news.postnewnews.before.gendervalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.news.postnewnews.after.retailervalidation', array($this, $validator));
            }

            foreach ($age_range_ids as $age_range_id_check) {
                $validator = Validator::make(
                    array(
                        'age_range_id'   => $age_range_id_check,
                    ),
                    array(
                        'age_range_id'   => 'orbit.empty.age',
                    )
                );

                Event::fire('orbit.news.postnewnews.before.retailervalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.news.postnewnews.after.retailervalidation', array($this, $validator));
            }

            Event::fire('orbit.news.postnewnews.after.validation', array($this, $validator));

            // Reformat sticky order
            $sticky_order = (string)$sticky_order === 'true' && (string)$sticky_order !== '0' ? 1 : 0;

            // save News.
            $idStatus = CampaignStatus::select('campaign_status_id','campaign_status_name')->where('campaign_status_name', $campaignStatus)->first();

            $newnews = new News();
            $newnews->mall_id = $mall_id;
            $newnews->news_name = $news_name;
            $newnews->object_type = $object_type;
            $newnews->status = $status;
            $newnews->campaign_status_id = $idStatus->campaign_status_id;
            $newnews->description = $description;
            $newnews->begin_date = $begin_date;
            $newnews->end_date = $end_date;
            $newnews->sticky_order = $sticky_order;
            $newnews->link_object_type = $link_object_type;
            $newnews->is_all_age = $is_all_age;
            $newnews->is_all_gender = $is_all_gender;
            $newnews->created_by = $this->api->user->user_id;
            $newnews->is_popup = $is_popup;

            Event::fire('orbit.news.postnewnews.before.save', array($this, $newnews));

            $newnews->save();

            // Return campaign status name
            $newnews->campaign_status = $idStatus->campaign_status_name;

            // save default language translation
            $news_translation_default = new NewsTranslation();
            $news_translation_default->news_id = $newnews->news_id;
            $news_translation_default->merchant_id = $newnews->mall_id;
            $news_translation_default->merchant_language_id = $id_language_default;
            $news_translation_default->news_name = $newnews->news_name;
            $news_translation_default->description = $newnews->description;
            $news_translation_default->status = 'active';
            $news_translation_default->created_by = $this->api->user->user_id;
            $news_translation_default->modified_by = $this->api->user->user_id;
            $news_translation_default->save();

            Event::fire('orbit.news.after.translation.save', array($this, $news_translation_default));

            // save NewsMerchant.
            $newsretailers = array();
            $isMall = 'retailer';
            $mallid = array();
            foreach ($retailer_ids as $retailer_id) {
                $data = @json_decode($retailer_id);
                $tenant_id = $data->tenant_id;
                $mall_id = $data->mall_id;

                if(! in_array($mall_id, $mallid)) {
                    $mallid[] = $mall_id;
                }

                if ($tenant_id === $mall_id) {
                    $isMall = 'mall';
                }

                $newsretailer = new NewsMerchant();
                $newsretailer->merchant_id = $tenant_id;
                $newsretailer->news_id = $newnews->news_id;
                $newsretailer->object_type = $isMall;
                $newsretailer->save();
                $newsretailers[] = $newsretailer;
            }
            $newnews->tenants = $newsretailers;

            //save to user campaign
            $usercampaign = new UserCampaign();
            $usercampaign->user_id = $user->user_id;
            $usercampaign->campaign_id = $newnews->news_id;
            $usercampaign->campaign_type = 'news';
            $usercampaign->save();

            // save CampaignAge
            $newsAges = array();
            foreach ($age_range_ids as $age_range) {
                $newsAge = new CampaignAge();
                $newsAge->campaign_type = $object_type;
                $newsAge->campaign_id = $newnews->news_id;
                $newsAge->age_range_id = $age_range;
                $newsAge->save();
                $newsAges[] = $newsAge;
            }
            $newnews->age = $newsAges;

            // save CampaignGender
            $newsGenders = array();
            foreach ($gender_ids as $gender) {
                $newsGender = new CampaignGender();
                $newsGender->campaign_type = $object_type;
                $newsGender->campaign_id = $newnews->news_id;
                $newsGender->gender_value = $gender;
                $newsGender->save();
                $gender_name = null;
                $newsGenders[] = $newsGender;
            }
            $newnews->gender = $newsGenders;

            // save Keyword
            $newsKeywords = array();
            foreach ($keywords as $keyword) {
                $keyword_id = null;

                $existKeyword = Keyword::excludeDeleted()
                    ->where('keyword', '=', $keyword)
                    ->where('merchant_id', '=', $newnews->mall_id)
                    ->first();

                if (empty($existKeyword)) {
                    $newKeyword = new Keyword();
                    $newKeyword->merchant_id = $newnews->mall_id;
                    $newKeyword->keyword = $keyword;
                    $newKeyword->status = 'active';
                    $newKeyword->created_by = $this->api->user->user_id;
                    $newKeyword->modified_by = $this->api->user->user_id;
                    $newKeyword->save();

                    $keyword_id = $newKeyword->keyword_id;
                    $newsKeywords[] = $newKeyword;
                } else {
                    $keyword_id = $existKeyword->keyword_id;
                    $newsKeywords[] = $existKeyword;
                }

                $newKeywordObject = new KeywordObject();
                $newKeywordObject->keyword_id = $keyword_id;
                $newKeywordObject->object_id = $newnews->news_id;
                $newKeywordObject->object_type = $object_type;
                $newKeywordObject->save();

            }
            $newnews->keywords = $newsKeywords;

            Event::fire('orbit.news.postnewnews.after.save', array($this, $newnews));

            //save campaign price
            $campaignbaseprice = CampaignBasePrice::where('merchant_id', '=', $newnews->mall_id)
                                            ->where('campaign_type', '=', $object_type)
                                            ->first();

            $baseprice = 0;
            if (! empty($campaignbaseprice->price)) {
                $baseprice = $campaignbaseprice->price;
            }

            $campaignprice = new CampaignPrice();
            $campaignprice->base_price = $baseprice;
            $campaignprice->campaign_type = $object_type;
            $campaignprice->campaign_id = $newnews->news_id;
            $campaignprice->save();

            // get action id for campaign history
            $actionstatus = 'activate';
            if ($status === 'inactive') {
                $actionstatus = 'deactivate';
            }
            $activeid = CampaignHistoryAction::getIdFromAction($actionstatus);
            $addtenantid = CampaignHistoryAction::getIdFromAction('add_tenant');

            // campaign history status
            $campaignhistory = new CampaignHistory();
            $campaignhistory->campaign_type = $object_type;
            $campaignhistory->campaign_id = $newnews->news_id;
            $campaignhistory->campaign_history_action_id = $activeid;
            $campaignhistory->number_active_tenants = 0;
            $campaignhistory->campaign_cost = 0;
            $campaignhistory->created_by = $this->api->user->user_id;
            $campaignhistory->modified_by = $this->api->user->user_id;
            $campaignhistory->save();

            //save campaign histories (tenant)
            $withSpending = 'Y';
            foreach ($retailer_ids as $retailer_id) {
                $data = @json_decode($retailer_id);
                $tenant_id = $data->tenant_id;
                $mall_id = $data->mall_id;
                // insert tenant/merchant to campaign history
                $tenantstatus = CampaignLocation::select('status')->where('merchant_id', $tenant_id)->first();
                $spendingrule = SpendingRule::select('with_spending')->where('object_id', $tenant_id)->first();

                if ($spendingrule) {
                    $withSpending = 'Y';
                } else {
                    $withSpending = 'N';
                }

                if (($tenantstatus->status === 'active') && ($withSpending === 'Y')) {
                    $addtenant = new CampaignHistory();
                    $addtenant->campaign_type = $object_type;
                    $addtenant->campaign_id = $newnews->news_id;
                    $addtenant->campaign_external_value = $tenant_id;
                    $addtenant->campaign_history_action_id = $addtenantid;
                    $addtenant->number_active_tenants = 0;
                    $addtenant->created_by = $this->api->user->user_id;
                    $addtenant->modified_by = $this->api->user->user_id;
                    $addtenant->campaign_cost = 0;
                    $addtenant->save();
                }
            }

            //calculate spending
            foreach ($mallid as $mall) {

                $campaign_id = $newnews->news_id;
                $campaign_type = $object_type;
                $procResults = DB::statement("CALL prc_campaign_detailed_cost({$this->quote($campaign_id)}, {$this->quote($campaign_type)}, NULL, NULL, {$this->quote($mall)})");

                if ($procResults === false) {
                    // Do Nothing
                }

                $getspending = DB::table(DB::raw('tmp_campaign_cost_detail'))->first();

                $mallTimezone = $this->getTimezone($mall);
                $nowMall = Carbon::now($mallTimezone);
                $dateNowMall = $nowMall->toDateString();

                // if campaign begin date is same with date now
                if ($dateNowMall === date('Y-m-d', strtotime($begin_date))) {
                    $dailySpending = new CampaignDailySpending();
                    $dailySpending->date = $getspending->date_in_utc;
                    $dailySpending->campaign_type = $campaign_type;
                    $dailySpending->campaign_id = $campaign_id;
                    $dailySpending->mall_id = $mall;
                    $dailySpending->number_active_tenants = $getspending->campaign_number_tenant;
                    $dailySpending->base_price = $getspending->base_price;
                    $dailySpending->campaign_status = $getspending->campaign_status;
                    $dailySpending->total_spending = 0;
                    $dailySpending->save();
                }
            }

            // Save campaign spending with default spending 0
            // remove after migration new table, campaign daily spending
            $campaignSpending = new CampaignSpendingCount();
            $campaignSpending->campaign_id = $newnews->news_id;
            $campaignSpending->campaign_type = $object_type;
            $campaignSpending->spending = 0;
            $campaignSpending->mall_id = $mall_id;
            $campaignSpending->begin_date = $begin_date;
            $campaignSpending->end_date = $end_date;
            $campaignSpending->save();

            // translation for mallnews
            OrbitInput::post('translations', function($translation_json_string) use ($newnews) {
                $this->validateAndSaveTranslations($newnews, $translation_json_string, 'create');
            });

            $this->response->data = $newnews;
            $this->response->data->translation_default = $news_translation_default;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('News Created: %s', $newnews->news_name);
            $activity->setUser($user)
                    ->setActivityName('create_news')
                    ->setActivityNameLong('Create News OK')
                    ->setObject($newnews)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.news.postnewnews.after.commit', array($this, $newnews));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.news.postnewnews.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_news')
                    ->setActivityNameLong('Create News Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.news.postnewnews.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_news')
                    ->setActivityNameLong('Create News Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.news.postnewnews.query.error', array($this, $e));

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

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_news')
                    ->setActivityNameLong('Create News Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.news.postnewnews.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_news')
                    ->setActivityNameLong('Create News Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update News
     *
     * @author Tian <tian@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     * @author Shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `news_id`               (required) - News ID
     * @param integer    `mall_id`               (optional) - Mall ID
     * @param string     `news_name`             (optional) - News name
     * @param string     `object_type`           (optional) - Object type. Valid value: promotion, news.
     * @param string     `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`           (optional) - Description
     * @param datetime   `begin_date`            (optional) - Begin date. Example: 2015-04-15 00:00:00
     * @param datetime   `end_date`              (optional) - End date. Example: 2015-04-18 23:59:59
     * @param integer    `sticky_order`          (optional) - Sticky order.
     * @param file       `images`                (optional) - News image
     * @param string     `link_object_type`      (optional) - Link object type. Valid value: tenant, tenant_category.
     * @param string     `no_retailer`           (optional) - Flag to delete all ORID links. Valid value: Y.
     * @param array      `retailer_ids`          (optional) - Retailer IDs
     * @param integer    `id_language_default`   (optional) - ID language default
     * @param string     `is_all_gender`         (optional) - Is all gender. Valid value: Y, N.
     * @param string     `is_all_age`            (optional) - Is all retailer age group. Valid value: Y, N.
     * @param string     `gender_ids`            (optional) - for Male, Female. Unknown. Valid value: M, F, U.
     * @param string     `age_range_ids`         (optional) - Age Range IDs
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateNews()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatednews = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.news.postupdatenews.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.news.postupdatenews.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.news.postupdatenews.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('update_news')) {
                Event::fire('orbit.news.postupdatenews.authz.notallowed', array($this, $user));
                $updateNewsLang = Lang::get('validation.orbit.actionlist.update_news');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateNewsLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->newsModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.news.postupdatenews.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $news_id = OrbitInput::post('news_id');
            $mall_id = OrbitInput::post('current_mall');;
            $object_type = OrbitInput::post('object_type');
            $campaignStatus = OrbitInput::post('campaign_status');
            $link_object_type = OrbitInput::post('link_object_type');
            $end_date = OrbitInput::post('end_date');
            $begin_date = OrbitInput::post('begin_date');
            $id_language_default = OrbitInput::post('id_language_default');
            $is_all_gender = OrbitInput::post('is_all_gender');
            $is_all_age = OrbitInput::post('is_all_age');
            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;

            $idStatus = CampaignStatus::select('campaign_status_id')->where('campaign_status_name', $campaignStatus)->first();
            $status = 'inactive';
            if ($campaignStatus === 'ongoing') {
                $status = 'active';
            }

            $data = array(
                'news_id'             => $news_id,
                'current_mall'        => $mall_id,
                'object_type'         => $object_type,
                'status'              => $status,
                'link_object_type'    => $link_object_type,
                'end_date'            => $end_date,
                'id_language_default' => $id_language_default,
                'is_all_gender'       => $is_all_gender,
                'is_all_age'          => $is_all_age,
            );

            // Validate news_name only if exists in POST.
            OrbitInput::post('news_name', function($news_name) use (&$data) {
                $data['news_name'] = $news_name;
            });

            $validator = Validator::make(
                $data,
                array(
                    'news_id'             => 'required|orbit.update.news:' . $object_type,
                    'news_name'           => 'sometimes|required|min:5|max:255|news_name_exists_but_me',
                    'object_type'         => 'required|orbit.empty.news_object_type',
                    'status'              => 'orbit.empty.news_status',
                    'link_object_type'    => 'orbit.empty.link_object_type',
                    'end_date'            => 'date||orbit.empty.hour_format',
                    'id_language_default' => 'required|orbit.empty.language_default',
                    'is_all_gender'       => 'required|orbit.empty.is_all_gender',
                    'is_all_age'          => 'required|orbit.empty.is_all_age',
                ),
                array(
                   'news_name_exists_but_me' => Lang::get('validation.orbit.exists.news_name'),
                   'orbit.update.news' => 'Cannot update campaign with status ' . $campaignStatus,
                )
            );

            Event::fire('orbit.news.postupdatenews.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.news.postupdatenews.after.validation', array($this, $validator));

            $retailernew = array();
            $mallid = array();
            foreach ($retailer_ids as $retailer_id) {
                $data = @json_decode($retailer_id);
                $tenant_id = $data->tenant_id;
                $mall_id = $data->mall_id;

                if(! in_array($mall_id, $mallid)) {
                    $mallid[] = $mall_id;
                }

                $retailernew[] = $tenant_id;
            }

            $updatednews = News::with('tenants')->excludeDeleted()->where('news_id', $news_id)->first();

            $statusdb = $updatednews->status;
            $enddatedb = $updatednews->end_date;
            //check get merchant for db
            $newsmerchantdb = NewsMerchant::select('merchant_id')->where('news_id', $news_id)->get()->toArray();
            $merchantdb = array();
            foreach($newsmerchantdb as $merchantdbid) {
                $merchantdb[] = $merchantdbid['merchant_id'];
            }

            $updatednews_default_language = NewsTranslation::excludeDeleted()->where('news_id', $news_id)->where('merchant_language_id', $id_language_default)->first();
            // save News
            OrbitInput::post('mall_id', function($mall_id) use ($updatednews) {
                $updatednews->mall_id = $mall_id;
            });

            OrbitInput::post('news_name', function($news_name) use ($updatednews) {
                $updatednews->news_name = $news_name;
            });

            OrbitInput::post('description', function($description) use ($updatednews) {
                $updatednews->description = $description;
            });

            OrbitInput::post('object_type', function($object_type) use ($updatednews) {
                $updatednews->object_type = $object_type;
            });

            OrbitInput::post('campaign_status', function($campaignStatus) use ($updatednews, $status, $idStatus) {
                $updatednews->status = $status;
                $updatednews->campaign_status_id = $idStatus->campaign_status_id;
            });

            OrbitInput::post('begin_date', function($begin_date) use ($updatednews) {
                $updatednews->begin_date = $begin_date;
            });

            OrbitInput::post('end_date', function($end_date) use ($updatednews) {
                $updatednews->end_date = $end_date;
            });

            OrbitInput::post('is_all_gender', function($is_all_gender) use ($updatednews) {
                $updatednews->is_all_gender = $is_all_gender;
            });

            OrbitInput::post('is_all_age', function($is_all_age) use ($updatednews) {
                $updatednews->is_all_age = $is_all_age;
            });

            OrbitInput::post('is_popup', function($is_popup) use ($updatednews) {
                $updatednews->is_popup = $is_popup;
            });

            OrbitInput::post('sticky_order', function($sticky_order) use ($updatednews) {
                // Reformat sticky order
                $sticky_order = (string)$sticky_order === 'true' && (string)$sticky_order !== '0' ? 1 : 0;

                $updatednews->sticky_order = $sticky_order;
            });

            OrbitInput::post('link_object_type', function($link_object_type) use ($updatednews) {
                if (trim($link_object_type) === '') {
                    $link_object_type = NULL;
                }
                $updatednews->link_object_type = $link_object_type;
            });

            OrbitInput::post('translations', function($translation_json_string) use ($updatednews) {
                $this->validateAndSaveTranslations($updatednews, $translation_json_string, 'update');
            });

            $updatednews->modified_by = $this->api->user->user_id;
            $updatednews->touch();

            //  save news default language
            OrbitInput::post('news_name', function($news_name) use ($updatednews_default_language) {
                $updatednews_default_language->news_name = $news_name;
            });

            OrbitInput::post('description', function($description) use ($updatednews_default_language) {
                $updatednews_default_language->description = $description;
            });

            OrbitInput::post('campaign_status', function($campaignStatus) use ($updatednews_default_language, $status) {
                $updatednews_default_language->status = $status;
            });

            $updatednews_default_language->modified_by = $this->api->user->user_id;

            Event::fire('orbit.news.postupdatenews.before.save', array($this, $updatednews));

            $updatednews->save();
            $updatednews_default_language->save();

            Event::fire('orbit.news.after.translation.save', array($this, $updatednews_default_language));

            // return respones if any upload image or no
            $updatednews_default_language->load('media');


            // save NewsMerchant
            OrbitInput::post('no_retailer', function($no_retailer) use ($updatednews) {
                if ($no_retailer == 'Y') {
                    $deleted_retailer_ids = NewsMerchant::where('news_id', $updatednews->news_id)->get(array('merchant_id'))->toArray();
                    $updatednews->tenants()->detach($deleted_retailer_ids);
                    $updatednews->load('tenants');
                }
            });

            OrbitInput::post('is_all_gender', function($is_all_gender) use ($updatednews, $news_id, $object_type) {
                $updatednews->is_all_gender = $is_all_gender;
                if ($is_all_gender == 'Y') {
                    $deleted_campaign_genders = CampaignGender::where('campaign_id', '=', $news_id)
                                                            ->where('campaign_type', '=', $object_type);
                    $deleted_campaign_genders->delete();
                }
            });

            OrbitInput::post('is_all_age', function($is_all_age) use ($updatednews, $news_id, $object_type) {
                $updatednews->is_all_age = $is_all_age;
                if ($is_all_age == 'Y') {
                    $deleted_campaign_ages = CampaignAge::where('campaign_id', '=', $news_id)
                                                            ->where('campaign_type', '=', $object_type);
                    $deleted_campaign_ages->delete();
                }
            });

            OrbitInput::post('retailer_ids', function($retailer_ids) use ($updatednews, $news_id, $mallid) {
                // validate retailer_ids

                // to do : add validation for tenant

                // Delete old data
                $delete_retailer = NewsMerchant::where('news_id', '=', $news_id);
                $delete_retailer->delete();

                // Insert new data
                $isMall = 'retailer';
                foreach ($retailer_ids as $retailer_id) {
                    $data = @json_decode($retailer_id);
                    $tenant_id = $data->tenant_id;
                    $mall_id = $data->mall_id;

                    if(! in_array($mall_id, $mallid)) {
                        $mallid[] = $mall_id;
                    }

                    if ($tenant_id === $mall_id) {
                        $isMall = 'mall';
                    } else {
                        $isMall = 'retailer';
                    }

                    $newsretailer = new NewsMerchant();
                    $newsretailer->merchant_id = $tenant_id;
                    $newsretailer->news_id = $news_id;
                    $newsretailer->object_type = $isMall;
                    $newsretailer->save();
                }
            });

            OrbitInput::post('gender_ids', function($gender_ids) use ($updatednews, $news_id, $object_type) {
                // validate gender_ids
                $gender_ids = (array) $gender_ids;
                foreach ($gender_ids as $gender_id_check) {
                    $validator = Validator::make(
                        array(
                            'gender_id'   => $gender_id_check,
                        ),
                        array(
                            'gender_id'   => 'orbit.empty.gender',
                        )
                    );

                    Event::fire('orbit.news.postupdatenews.before.gendervalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.news.postupdatenews.after.gendervalidation', array($this, $validator));
                }

                // Delete old data
                $deleted_campaign_genders = CampaignGender::where('campaign_id', '=', $news_id)
                                                        ->where('campaign_type', '=', $object_type);
                $deleted_campaign_genders->delete();

                // Insert new data
                $newsGenders = array();
                foreach ($gender_ids as $gender) {
                    $newsGender = new CampaignGender();
                    $newsGender->campaign_type = $object_type;
                    $newsGender->campaign_id = $news_id;
                    $newsGender->gender_value = $gender;
                    $newsGender->save();
                    $newsGenders[] = $newsGenders;
                }
                $updatednews->gender = $newsGenders;

            });

            OrbitInput::post('age_range_ids', function($age_range_ids) use ($updatednews, $news_id, $object_type) {
                // validate age_range_ids
                $age_range_ids = (array) $age_range_ids;
                foreach ($age_range_ids as $age_range_id_check) {
                    $validator = Validator::make(
                        array(
                            'age_range_id'   => $age_range_id_check,
                        ),
                        array(
                            'age_range_id'   => 'orbit.empty.age',
                        )
                    );

                    Event::fire('orbit.news.postupdatenews.before.agevalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.news.postupdatenews.after.agevalidation', array($this, $validator));
                }

                // Delete old data
                $deleted_campaign_ages = CampaignAge::where('campaign_id', '=', $news_id)
                                                        ->where('campaign_type', '=', $object_type);
                $deleted_campaign_ages->delete();

                // Insert new data
                $newsAges = array();
                foreach ($age_range_ids as $age_range) {
                    $newsAge = new CampaignAge();
                    $newsAge->campaign_type = $object_type;
                    $newsAge->campaign_id = $news_id;
                    $newsAge->age_range_id = $age_range;
                    $newsAge->save();
                    $newsAges[] = $newsAges;
                }
                $updatednews->age = $newsAges;

            });

            // Delete old data
            $deleted_keyword_object = KeywordObject::where('object_id', '=', $news_id)
                                                    ->where('object_type', '=', $object_type);
            $deleted_keyword_object->delete();

            OrbitInput::post('keywords', function($keywords) use ($updatednews, $mall_id, $user, $news_id, $object_type) {
                // Insert new data
                $newsKeywords = array();
                foreach ($keywords as $keyword) {
                    $keyword_id = null;

                    $existKeyword = Keyword::excludeDeleted()
                        ->where('keyword', '=', $keyword)
                        ->where('merchant_id', '=', $mall_id)
                        ->first();

                    if (empty($existKeyword)) {
                        $newKeyword = new Keyword();
                        $newKeyword->merchant_id = $mall_id;
                        $newKeyword->keyword = $keyword;
                        $newKeyword->status = 'active';
                        $newKeyword->created_by = $user->user_id;
                        $newKeyword->modified_by = $user->user_id;
                        $newKeyword->save();

                        $keyword_id = $newKeyword->keyword_id;
                        $newsKeywords[] = $newKeyword;
                    } else {
                        $keyword_id = $existKeyword->keyword_id;
                        $newsKeywords[] = $existKeyword;
                    }


                    $newKeywordObject = new KeywordObject();
                    $newKeywordObject->keyword_id = $keyword_id;
                    $newKeywordObject->object_id = $news_id;
                    $newKeywordObject->object_type = $object_type;
                    $newKeywordObject->save();

                }
                $updatednews->keywords = $newsKeywords;
            });

            //save campaign histories (status)
            $actionhistory = '';
            if ($statusdb != $status) {
                // get action id for campaign history
                $actionstatus = 'activate';
                if ($status === 'inactive') {
                    $actionstatus = 'deactivate';
                }
                $activeid = CampaignHistoryAction::getIdFromAction($actionstatus);

                $campaignhistory = new CampaignHistory();
                $campaignhistory->campaign_type = $object_type;
                $campaignhistory->campaign_id = $news_id;
                $campaignhistory->campaign_history_action_id = $activeid;
                $campaignhistory->number_active_tenants = 0;
                $campaignhistory->campaign_cost = 0;
                $campaignhistory->created_by = $this->api->user->user_id;
                $campaignhistory->modified_by = $this->api->user->user_id;
                $campaignhistory->save();

            } else {
                //check for first time insert for that day
                $utcNow = Carbon::now();
                $checkFirst = CampaignHistory::where('campaign_id', '=', $news_id)->where('created_at', 'like', $utcNow->toDateString().'%')->count();
                if ($checkFirst === 0){
                    $actionstatus = 'activate';
                    if ($statusdb === 'inactive') {
                        $actionstatus = 'deactivate';
                    }
                    $activeid = CampaignHistoryAction::getIdFromAction($actionstatus);
                    $campaignhistory = new CampaignHistory();
                    $campaignhistory->campaign_type = $object_type;
                    $campaignhistory->campaign_id = $news_id;
                    $campaignhistory->campaign_history_action_id = $activeid;
                    $campaignhistory->number_active_tenants = 0;
                    $campaignhistory->campaign_cost = 0;
                    $campaignhistory->created_by = $this->api->user->user_id;
                    $campaignhistory->modified_by = $this->api->user->user_id;
                    $campaignhistory->save();

                }
            }

            //check for add/remove tenant
            $removetenant = array_diff($merchantdb, $retailernew);
            $addtenant = array_diff($retailernew, $merchantdb);
            $withSpending = 'Y';
            if (! empty($removetenant)) {
                $actionhistory = 'delete';
                $addtenantid = CampaignHistoryAction::getIdFromAction('delete_tenant');
                //save campaign histories (tenant)
                foreach ($removetenant as $retailer_id) {
                    // insert tenant/merchant to campaign history
                    $tenantstatus = CampaignLocation::select('status')->where('merchant_id', $retailer_id)->first();
                    $spendingrule = SpendingRule::select('with_spending')->where('object_id', $retailer_id)->first();

                    if ($spendingrule) {
                        $withSpending = 'Y';
                    } else {
                        $withSpending = 'N';
                    }

                    if (($tenantstatus->status === 'active') && ($withSpending === 'Y')) {
                        $tenanthistory = new CampaignHistory();
                        $tenanthistory->campaign_type = $object_type;
                        $tenanthistory->campaign_id = $news_id;
                        $tenanthistory->campaign_external_value = $retailer_id;
                        $tenanthistory->campaign_history_action_id = $addtenantid;
                        $tenanthistory->number_active_tenants = 0;
                        $tenanthistory->campaign_cost = 0;
                        $tenanthistory->created_by = $this->api->user->user_id;
                        $tenanthistory->modified_by = $this->api->user->user_id;
                        $tenanthistory->save();

                    }
                }
            }
            if (! empty($addtenant)) {
                $actionhistory = 'add';
                $addtenantid = CampaignHistoryAction::getIdFromAction('add_tenant');
                //save campaign histories (tenant)
                foreach ($addtenant as $retailer_id) {
                    // insert tenant/merchant to campaign history
                    $tenantstatus = CampaignLocation::select('status')->where('merchant_id', $retailer_id)->first();
                    $spendingrule = SpendingRule::select('with_spending')->where('object_id', $retailer_id)->first();

                    if ($spendingrule) {
                        $withSpending = 'Y';
                    } else {
                        $withSpending = 'N';
                    }

                    if (($tenantstatus->status === 'active') && ($withSpending === 'Y')) {
                        $tenanthistory = new CampaignHistory();
                        $tenanthistory->campaign_type = $object_type;
                        $tenanthistory->campaign_id = $news_id;
                        $tenanthistory->campaign_external_value = $retailer_id;
                        $tenanthistory->campaign_history_action_id = $addtenantid;
                        $tenanthistory->number_active_tenants = 0;
                        $tenanthistory->campaign_cost = 0;
                        $tenanthistory->created_by = $this->api->user->user_id;
                        $tenanthistory->modified_by = $this->api->user->user_id;
                        $tenanthistory->save();
                    }
                }
            }

            //calculate spending
            foreach ($mallid as $mall) {

                $campaign_id = $news_id;
                $campaign_type = $object_type;
                $procResults = DB::statement("CALL prc_campaign_detailed_cost({$this->quote($campaign_id)}, {$this->quote($campaign_type)}, NULL, NULL, {$this->quote($mall)})");

                if ($procResults === false) {
                    // Do Nothing
                }

                $getspending = DB::table(DB::raw('tmp_campaign_cost_detail'))->first();

                $mallTimezone = $this->getTimezone($mall);
                $nowMall = Carbon::now($mallTimezone);
                $dateNowMall = $nowMall->toDateString();
                $beginMall = date('Y-m-d', strtotime($begin_date));
                $endMall = date('Y-m-d', strtotime($end_date));

                // only calculate spending when update date between start and date of campaign
                if ($dateNowMall >= $beginMall && $dateNowMall <= $endMall) {
                    $daily = CampaignDailySpending::where('date', '=', $getspending->date_in_utc)->where('campaign_id', '=', $campaign_id)->where('mall_id', '=', $mall)->first();

                    if ($daily['campaign_daily_spending_id']) {
                        $dailySpending = CampaignDailySpending::find($daily['campaign_daily_spending_id']);
                    } else {
                        $dailySpending = new CampaignDailySpending;
                    }

                    $dailySpending->date = $getspending->date_in_utc;
                    $dailySpending->campaign_type = $campaign_type;
                    $dailySpending->campaign_id = $campaign_id;
                    $dailySpending->mall_id = $mall;
                    $dailySpending->number_active_tenants = $getspending->campaign_number_tenant;
                    $dailySpending->base_price = $getspending->base_price;
                    $dailySpending->campaign_status = $getspending->campaign_status;
                    $dailySpending->total_spending = $getspending->daily_cost;
                    $dailySpending->save();
                }
            }

            Event::fire('orbit.news.postupdatenews.after.save', array($this, $updatednews));
            $this->response->data = $updatednews;
            $this->response->data->translation_default = $updatednews_default_language;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('News updated: %s', $updatednews->news_name);
            $activity->setUser($user)
                    ->setActivityName('update_news')
                    ->setActivityNameLong('Update News OK')
                    ->setObject($updatednews)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.news.postupdatenews.after.commit', array($this, $updatednews));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.news.postupdatenews.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_news')
                    ->setActivityNameLong('Update News Failed')
                    ->setObject($updatednews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.news.postupdatenews.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_news')
                    ->setActivityNameLong('Update News Failed')
                    ->setObject($updatednews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.news.postupdatenews.query.error', array($this, $e));

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

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_news')
                    ->setActivityNameLong('Update News Failed')
                    ->setObject($updatednews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.news.postupdatenews.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_news')
                    ->setActivityNameLong('Update News Failed')
                    ->setObject($updatednews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    /**
     * POST - Delete News
     *
     * @author Tian <tian@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `news_id`                  (required) - ID of the news
     * @param string     `password`                 (required) - master password
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteNews()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deletenews = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.news.postdeletenews.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.news.postdeletenews.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.news.postdeletenews.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('delete_news')) {
                Event::fire('orbit.news.postdeletenews.authz.notallowed', array($this, $user));
                $deleteNewsLang = Lang::get('validation.orbit.actionlist.delete_news');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteNewsLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->newsModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.news.postdeletenews.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $news_id = OrbitInput::post('news_id');
            // $password = OrbitInput::post('password');

            $validator = Validator::make(
                array(
                    'news_id'  => $news_id,
                    // 'password' => $password,
                ),
                array(
                    'news_id'  => 'required|orbit.empty.news',
                    // 'password' => 'required|orbit.masterpassword.delete',
                ),
                array(
                    // 'required.password'             => 'The master is password is required.',
                    // 'orbit.masterpassword.delete'   => 'The password is incorrect.'
                )
            );

            Event::fire('orbit.news.postdeletenews.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.news.postdeletenews.after.validation', array($this, $validator));

            $deletenews = News::excludeDeleted()->where('news_id', $news_id)->first();
            $deletenews->status = 'deleted';
            $deletenews->modified_by = $this->api->user->user_id;

            Event::fire('orbit.news.postdeletenews.before.save', array($this, $deletenews));

            // hard delete news-merchant.
            $deletenewsretailers = NewsMerchant::where('news_id', $deletenews->news_id)->get();
            foreach ($deletenewsretailers as $deletenewsretailer) {
                $deletenewsretailer->delete();
            }

            // hard delete campaign gender
            $deleteCampaignGenders = CampaignGender::where('campaign_id', $deletenews->news_id)->get();
            foreach ($deleteCampaignGenders as $deletenewsretailer) {
                $deletenewsretailer->delete();
            }

            // hard delete campaign age
            $deleteCampaignAges = CampaignAge::where('campaign_id', $deletenews->news_id)->get();
            foreach ($deleteCampaignAges as $deletenewsretailer) {
                $deletenewsretailer->delete();
            }

            foreach ($deletenews->translations as $translation) {
                $translation->modified_by = $this->api->user->user_id;
                $translation->delete();
            }

            $deletenews->save();

            Event::fire('orbit.news.postdeletenews.after.save', array($this, $deletenews));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.news');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('News Deleted: %s', $deletenews->news_name);
            $activity->setUser($user)
                    ->setActivityName('delete_news')
                    ->setActivityNameLong('Delete News OK')
                    ->setObject($deletenews)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.news.postdeletenews.after.commit', array($this, $deletenews));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.news.postdeletenews.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_news')
                    ->setActivityNameLong('Delete News Failed')
                    ->setObject($deletenews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.news.postdeletenews.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_news')
                    ->setActivityNameLong('Delete News Failed')
                    ->setObject($deletenews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.news.postdeletenews.query.error', array($this, $e));

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

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_news')
                    ->setActivityNameLong('Delete News Failed')
                    ->setObject($deletenews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.news.postdeletenews.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_news')
                    ->setActivityNameLong('Delete News Failed')
                    ->setObject($deletenews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);

        // Save the activity
        $activity->save();

        return $output;
    }

    /**
     * GET - Search News
     *
     * @author Tian <tian@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                  (optional) - Valid value: tenants.
     * @param string   `sortby`                (optional) - column order by
     * @param string   `sortmode`              (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `news_id`               (optional) - News ID
     * @param integer  `mall_id`               (optional) - Mall ID
     * @param string   `news_name`             (optional) - News name
     * @param string   `news_name_like`        (optional) - News name like
     * @param string   `object_type`           (optional) - Object type. Valid value: promotion, news.
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param datetime `begin_date`            (optional) - Begin date. Example: 2015-04-15 00:00:00
     * @param datetime `end_date`              (optional) - End date. Example: 2015-04-18 23:59:59
     * @param integer  `sticky_order`          (optional) - Sticky order.
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `link_object_type`      (optional) - Link object type. Valid value: tenant, tenant_category.
     * @param integer  `retailer_id`           (optional) - Retailer IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchNews()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.news.getsearchnews.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.news.getsearchnews.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.news.getsearchnews.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_news')) {
                Event::fire('orbit.news.getsearchnews.authz.notallowed', array($this, $user));
                $viewNewsLang = Lang::get('validation.orbit.actionlist.view_news');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewNewsLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->newsModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.news.getsearchnews.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,news_name,object_type,description,begin_date,end_date,updated_at,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.news_sortby'),
                )
            );

            Event::fire('orbit.news.getsearchnews.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.news.getsearchnews.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.news.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.news.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $object_type = OrbitInput::get('object_type');

            // Builder object
            $prefix = DB::getTablePrefix();
            $news = News::allowedForPMPUser($user, $object_type[0])
                        ->select('news.*', 'campaign_status.order', 'campaign_price.campaign_price_id', 'news_translations.news_name as name_english',
                            DB::raw("(select GROUP_CONCAT(IF({$prefix}merchants.object_type = 'tenant', CONCAT({$prefix}merchants.name,' at ', pm.name), {$prefix}merchants.name) separator ', ') from {$prefix}news_merchant
                                    inner join {$prefix}merchants on {$prefix}merchants.merchant_id = {$prefix}news_merchant.merchant_id
                                    inner join {$prefix}merchants pm on {$prefix}merchants.parent_id = pm.merchant_id
                                    where {$prefix}news_merchant.news_id = {$prefix}news.news_id) as campaign_location_names"),
                            DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}news.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name) FROM {$prefix}merchants om
                                    LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                    WHERE om.merchant_id = {$prefix}news.mall_id)
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END  AS campaign_status"),
                            DB::raw("CASE WHEN {$prefix}campaign_price.base_price is null THEN 0 ELSE {$prefix}campaign_price.base_price END AS base_price, ((CASE WHEN {$prefix}campaign_price.base_price is null THEN 0 ELSE {$prefix}campaign_price.base_price END) * (DATEDIFF({$prefix}news.end_date, {$prefix}news.begin_date) + 1) * (COUNT({$prefix}news_merchant.news_merchant_id))) AS estimated"))
                        ->leftJoin('campaign_price', function ($join) use ($object_type) {
                                $join->on('news.news_id', '=', 'campaign_price.campaign_id')
                                     ->where('campaign_price.campaign_type', '=', $object_type);
                          })
                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                        ->leftJoin('merchant_languages', 'merchant_languages.merchant_language_id', '=', 'news_translations.merchant_language_id')
                        ->leftJoin('languages', 'languages.language_id', '=', 'merchant_languages.language_id')
                        ->where('languages.name', '=', 'en')
                        ->excludeDeleted('news')
                        ->groupBy('news.news_id');
            

            // Filter news by Ids
            OrbitInput::get('news_id', function($newsIds) use ($news)
            {
                $news->whereIn('news.news_id', $newsIds);
            });


            // to do : enable filter for mall
            // Filter news by mall Ids
            // OrbitInput::get('mall_id', function ($mallIds) use ($news)
            // {
            //     $news->whereIn('news.mall_id', (array)$mallIds);
            // });

            // Filter news by mall Ids / dupes, same as above
            // OrbitInput::get('merchant_id', function ($mallIds) use ($news)
            // {
            //     $news->whereIn('news.mall_id', (array)$mallIds);
            // });

            // Filter news by news name
            OrbitInput::get('news_name', function($newsname) use ($news)
            {
                $news->where('news.news_name', '=', $newsname);
            });

            // Filter news by matching news name pattern
            OrbitInput::get('news_name_like', function($newsname) use ($news)
            {
                $news->where('news_translations.news_name', 'like', "%$newsname%");
            });

            // Filter news by object type
            OrbitInput::get('object_type', function($objectTypes) use ($news)
            {
                $news->whereIn('news.object_type', $objectTypes);
            });

            // Filter news by description
            OrbitInput::get('description', function($description) use ($news)
            {
                $news->whereIn('news.description', $description);
            });

            // Filter news by matching description pattern
            OrbitInput::get('description_like', function($description) use ($news)
            {
                $news->where('news.description', 'like', "%$description%");
            });

            // Filter news by date
            OrbitInput::get('end_date', function($enddate) use ($news)
            {
                $news->where('news.begin_date', '<=', $enddate);
            });

            // Filter news by date
            OrbitInput::get('begin_date', function($begindate) use ($news)
            {
                $news->where('news.end_date', '>=', $begindate);
            });

            // Filter news by sticky order
            OrbitInput::get('sticky_order', function ($stickyorder) use ($news) {
                $news->whereIn('news.sticky_order', $stickyorder);
            });

            // Filter news by status
            OrbitInput::get('campaign_status', function ($statuses) use ($news, $prefix) {
                $news->whereIn(DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}news.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                                    FROM {$prefix}merchants om
                                                                                    LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                                    WHERE om.merchant_id = {$prefix}news.mall_id)
                    THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END"), $statuses);
            });

            // Filter news by link object type
            OrbitInput::get('link_object_type', function ($linkObjectTypes) use ($news) {
                $news->whereIn('news.link_object_type', $linkObjectTypes);
            });

            // Filter news merchants by retailer(tenant) id
            OrbitInput::get('retailer_id', function ($retailerIds) use ($news) {
                $news->whereHas('tenants', function($q) use ($retailerIds) {
                    $q->whereIn('merchant_id', $retailerIds);
                });
            });

            // Filter news merchants by retailer(tenant) name
            OrbitInput::get('tenant_name_like', function ($tenant_name_like) use ($news) {
                $news->whereHas('tenants', function($q) use ($tenant_name_like) {
                    $q->where('merchants.name', 'like', "%$tenant_name_like%");
                });
            });

            // Filter news merchants by mall name
            // There is laravel bug regarding nested whereHas on the same table like in this case
            // news->tenant->mall : whereHas('tenant', function($q) { $q->whereHas('mall' ...)}) this is not gonna work
            OrbitInput::get('mall_name_like', function ($mall_name_like) use ($news, $prefix) {
                $quote = function($arg)
                {
                    return DB::connection()->getPdo()->quote($arg);
                };
                $mall_name_like = "%" . $mall_name_like . "%";
                $mall_name_like = $quote($mall_name_like);
                $news->whereRaw(DB::raw("
                    (select count(*) from {$prefix}merchants mtenant
                    inner join {$prefix}news_merchant onm on mtenant.merchant_id = onm.merchant_id
                    where mtenant.object_type = 'tenant' and onm.news_id = {$prefix}news.news_id and (
                        select count(*) from {$prefix}merchants mmall
                        where mmall.object_type = 'mall' and
                        mtenant.parent_id = mmall.merchant_id and
                        mmall.name like {$mall_name_like} and
                        mmall.object_type = 'mall'
                    ) >= 1 and
                    mtenant.object_type = 'tenant' and
                    mtenant.is_mall = 'no' and
                    onm.object_type = 'retailer') >= 1
                "));
            });

            // Filter news by estimated total cost
            OrbitInput::get('etc_from', function ($etcfrom) use ($news) {
                $etcto = OrbitInput::get('etc_to');
                if (empty($etcto)) {
                    $news->havingRaw('estimated >= ' . floatval(str_replace(',', '', $etcfrom)));
                }
            });

            // Filter news by estimated total cost
            OrbitInput::get('etc_to', function ($etcto) use ($news) {
                $etcfrom = OrbitInput::get('etc_from');
                if (empty($etcfrom)) {
                    $etcfrom = 0;
                }
                $news->havingRaw('estimated between ' . floatval(str_replace(',', '', $etcfrom)) . ' and '. floatval(str_replace(',', '', $etcto)));
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($news) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'tenants') {
                        $news->with('tenants');
                    } elseif ($relation === 'tenants.mall') {
                        $news->with('tenants.mall');
                    } elseif ($relation === 'campaignLocations') {
                        $news->with('campaignLocations');
                    } elseif ($relation === 'campaignLocations.mall') {
                        $news->with('campaignLocations.mall');
                    } elseif ($relation === 'translations') {
                        $news->with('translations');
                    } elseif ($relation === 'translations.media') {
                        $news->with('translations.media');
                    } elseif ($relation === 'genders') {
                        $news->with('genders');
                    } elseif ($relation === 'ages') {
                        $news->with('ages');
                    } elseif ($relation === 'keywords') {
                        $news->with('keywords');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_news = clone $news;

            if (! $this->returnBuilder) {
                // Get the take args
                $take = $perPage;
                OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;

                    if ((int)$take <= 0) {
                        $take = $maxRecord;
                    }
                });
                $news->take($take);

                $skip = 0;
                OrbitInput::get('skip', function($_skip) use (&$skip, $news)
                {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                });
                $news->skip($skip);
            }

            // Default sort by
            $sortBy = 'campaign_status';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'news.created_at',
                    'news_name'         => 'news_translations.news_name',
                    'object_type'       => 'news.object_type',
                    'description'       => 'news.description',
                    'begin_date'        => 'news.begin_date',
                    'end_date'          => 'news.end_date',
                    'updated_at'        => 'news.updated_at',
                    'status'            => 'campaign_status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $news->orderBy($sortBy, $sortMode);

            //with name
            if ($sortBy !== 'news_translations.news_name') {
                $news->orderBy('news_translations.news_name', 'asc');
            }

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return ['builder' => $news, 'count' => RecordCounter::create($_news)->count()];
            }

            $totalNews = RecordCounter::create($_news)->count();
            $listOfNews = $news->get();

            $data = new stdclass();
            $data->total_records = $totalNews;
            $data->returned_records = count($listOfNews);
            $data->records = $listOfNews;

            if ($totalNews === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.news');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.news.getsearchnews.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.news.getsearchnews.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.news.getsearchnews.query.error', array($this, $e));

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
            Event::fire('orbit.news.getsearchnews.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.news.getsearchnews.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Search Promotion - List By Retailer
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`                (optional) - column order by. Valid value: retailer_name, registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status.
     * @param string   `sortmode`              (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `promotion_id`          (optional) - Promotion ID
     * @param integer  `merchant_id`           (optional) - Merchant ID
     * @param string   `promotion_name`        (optional) - Promotion name
     * @param string   `promotion_name_like`   (optional) - Promotion name like
     * @param string   `promotion_type`        (optional) - Promotion type. Valid value: product, cart.
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param datetime `begin_date`            (optional) - Begin date. Example: 2015-2-4 00:00:00
     * @param datetime `end_date`              (optional) - End date. Example: 2015-2-4 23:59:59
     * @param string   `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `city`                  (optional) - City name
     * @param string   `city_like`             (optional) - City name like
     * @param integer  `retailer_id`           (optional) - Retailer IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchNewsPromotionByRetailer()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.promotion.getsearchnewspromotionbyretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.promotion.getsearchnewspromotionbyretailer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promotion.getsearchnewspromotionbyretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_promotion')) {
                Event::fire('orbit.promotion.getsearchnewspromotionbyretailer.authz.notallowed', array($this, $user));
                $viewPromotionLang = Lang::get('validation.orbit.actionlist.view_promotion');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewPromotionLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.promotion.getsearchnewspromotionbyretailer.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:retailer_name,registered_date,promotion_name,promotion_type,description,begin_date,end_date,is_permanent,updated_at,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.promotion_by_retailer_sortby'),
                )
            );

            Event::fire('orbit.promotion.getsearchnewspromotionbyretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.promotion.getsearchnewspromotionbyretailer.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int)Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            $prefix = DB::getTablePrefix();
            $nowUTC = Carbon::now();
            // Builder object
            $promotions = News::join('merchants', 'news.mall_id', '=', 'merchants.merchant_id')
                              ->join('timezones', 'merchants.timezone_id', '=', 'timezones.timezone_id')
                              // ->join('news_merchant', 'news.news_id', '=', 'news_merchant.news_id')
                              ->select('merchants.name AS retailer_name', 'news.*', 'news.news_name as promotion_name', 'timezones.timezone_name')
                              // ->where('news.object_type', '=', 'promotion')
                              // ->where('news.status', '!=', 'deleted');
                              ->where('news.status', '=', 'active');


            if (empty(OrbitInput::get('begin_date')) && empty(OrbitInput::get('end_date'))) {
                $promotions->where('begin_date', '<=', DB::raw("CONVERT_TZ('{$nowUTC}','UTC',{$prefix}timezones.timezone_name)"))
                           ->where('end_date', '>=', DB::raw("CONVERT_TZ('{$nowUTC}','UTC',{$prefix}timezones.timezone_name)"));
            }

            // Filter promotion by Ids
            OrbitInput::get('news_id', function($promotionIds) use ($promotions)
            {
                $promotions->whereIn('news.news_id', $promotionIds);
            });

            // Filter promotion by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($promotions) {
                $promotions->whereIn('news.mall_id', $merchantIds);
            });

            // Filter promotion by promotion name
            OrbitInput::get('news_name', function($newsname) use ($promotions)
            {
                $promotions->whereIn('news.news_name', $newsname);
            });

            // Filter promotion by matching promotion name pattern
            OrbitInput::get('news_name_like', function($newsname) use ($promotions)
            {
                $promotions->where('news.news_name', 'like', "%$newsname%");
            });

            // Filter promotion by promotion type
            OrbitInput::get('object_type', function($objectTypes) use ($promotions)
            {
                $promotions->whereIn('news.object_type', $objectTypes);
            });

            // Filter promotion by description
            OrbitInput::get('description', function($description) use ($promotions)
            {
                $promotions->whereIn('news.description', $description);
            });

            // Filter promotion by matching description pattern
            OrbitInput::get('description_like', function($description) use ($promotions)
            {
                $promotions->where('news.description', 'like', "%$description%");
            });

            // Filter promotion by begin date
            OrbitInput::get('begin_date', function($begindate) use ($promotions)
            {
                $promotions->where('news.begin_date', '<=', $begindate);
            });

            // Filter promotion by end date
            OrbitInput::get('end_date', function($enddate) use ($promotions)
            {
                $promotions->where('news.end_date', '>=', $enddate);
            });

            // Filter promotion by status
            OrbitInput::get('status', function ($statuses) use ($promotions) {
                $promotions->whereIn('news.status', $statuses);
            });

            // Filter promotion by city
            OrbitInput::get('city', function($city) use ($promotions)
            {
                $promotions->whereIn('merchants.city', $city);
            });

            // Filter promotion by matching city pattern
            OrbitInput::get('city_like', function($city) use ($promotions)
            {
                $promotions->where('merchants.city', 'like', "%$city%");
            });

            // Filter promotion by retailer Ids
            OrbitInput::get('retailer_id', function ($retailerIds) use ($promotions) {
                $promotions->whereIn('promotion_retailer.retailer_id', $retailerIds);
            });

            OrbitInput::get('with', function ($with) use ($promotions) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'translations') {
                        $promotions->with('translations');
                    } elseif ($relation === 'translations.media') {
                        $promotions->with('translations.media');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_promotions = clone $promotions;

            // Get the take args
            if (trim(OrbitInput::get('take')) === '') {
                $take = $maxRecord;
            } else {
                OrbitInput::get('take', function($_take) use (&$take, $maxRecord)
                {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                });
            }
            if ($take > 0) {
                $promotions->take($take);
            }

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $promotions)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $promotions->skip($skip);
            }

            // Default sort by
            $sortBy = 'news.created_at';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'retailer_name'     => 'retailer_name',
                    'registered_date'   => 'news.created_at',
                    'promotion_name'    => 'news.news_name',
                    'object_type'       => 'news.obect_type',
                    'description'       => 'news.description',
                    'begin_date'        => 'news.begin_date',
                    'end_date'          => 'news.end_date',
                    'updated_at'        => 'news.updated_at',
                    'status'            => 'news.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $promotions->orderBy($sortBy, $sortMode);

            $totalPromotions = $_promotions->count();
            $listOfPromotions = $promotions->get();

            $data = new stdclass();
            $data->total_records = $totalPromotions;
            $data->returned_records = count($listOfPromotions);
            $data->records = $listOfPromotions;

            if ($totalPromotions === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.promotion');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.promotion.getsearchnewspromotionbyretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promotion.getsearchnewspromotionbyretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.promotion.getsearchnewspromotionbyretailer.query.error', array($this, $e));

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
            Event::fire('orbit.promotion.getsearchnewspromotionbyretailer.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.promotion.getsearchnewspromotionbyretailer.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of id_language_default
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $news = MerchantLanguage::excludeDeleted()
                        ->where('merchant_language_id', $value)
                        ->first();

            if (empty($news)) {
                return false;
            }

            App::instance('orbit.empty.language_default', $news);

            return true;
        });

        // Validate the time format for over 23 hour
        Validator::extend('orbit.empty.hour_format', function ($attribute, $value, $parameters) {
            // explode the format Y-m-d H:i:s
            $dateTimeExplode = explode(' ', $value);
            // explode the format H:i:s
            $timeExplode = explode(':', $dateTimeExplode[1]);
            // get the Hour format
            if($timeExplode[0] > 23){
                return false;
            }

            return true;
        });

        // Check the existance of news id
        Validator::extend('orbit.empty.news', function ($attribute, $value, $parameters) {
            $news = News::excludeStoppedOrExpired('news')
                        ->where('news_id', $value)
                        ->first();

            if (empty($news)) {
                return false;
            }

            App::instance('orbit.empty.news', $news);

            return true;
        });

        // Check the existance of news id for update with permission check
        Validator::extend('orbit.update.news', function ($attribute, $value, $parameters) {
            $user = $this->api->user;
            $object_type = $parameters[0];

            $news = News::allowedForPMPUser($user, $object_type)->excludeStoppedOrExpired('news')
                        ->where('news_id', $value)
                        ->first();

            if (empty($news)) {
                return false;
            }

            App::instance('orbit.update.news', $news);

            return true;
        });

        // Check the existance of mall id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return false;
            }

            App::instance('orbit.empty.mall', $mall);

            return true;
        });

        // Check news name, it should not exists
        Validator::extend('orbit.exists.news_name', function ($attribute, $value, $parameters) {

            // this is for fixing OM-578
            // can not make promotion if the name has already been used for news
            $object_type = OrbitInput::post('object_type');
            $mall_id = OrbitInput::post('current_mall');

            if (empty($object_type)) {
                $object_type = 'news';
            }

            $newsName = News::excludeDeleted()
                    ->where('news_name', $value)
                    ->where('object_type', $object_type)
                    ->where('mall_id', $mall_id)
                    ->first();

            if (! empty($newsName)) {
                return false;
            }

            App::instance('orbit.validation.news_name', $newsName);

            return true;
        });

        // Check news name, it should not exists (for update)
        Validator::extend('news_name_exists_but_me', function ($attribute, $value, $parameters) {
            $news_id = trim(OrbitInput::post('news_id'));
            $object_type = trim(OrbitInput::post('object_type'));
            $mall_id = OrbitInput::post('current_mall');

            $news = News::excludeDeleted()
                        ->where('news_name', $value)
                        ->where('news_id', '!=', $news_id)
                        ->where('object_type', $object_type)
                        ->where('mall_id', $mall_id)
                        ->first();

            if (! empty($news)) {
                return false;
            }

            App::instance('orbit.validation.news_name', $news);

            return true;
        });

        // Check the existence of the news status
        Validator::extend('orbit.empty.news_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || true;
            }

            return $valid;
        });

        // Check the existence of the news object type
        Validator::extend('orbit.empty.news_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $objectTypes = array('promotion', 'news');
            foreach ($objectTypes as $objectType) {
                if($value === $objectType) $valid = $valid || true;
            }

            return $valid;
        });

        // Check the existence of the link object type
        Validator::extend('orbit.empty.link_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $linkobjecttypes = array('tenant', 'tenant_category');
            foreach ($linkobjecttypes as $linkobjecttype) {
                if($value === $linkobjecttype) $valid = $valid || true;
            }

            return $valid;
        });

        // Check the existance of tenant id
        Validator::extend('orbit.empty.tenant', function ($attribute, $value, $parameters) {
            $tenant = Tenant::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($tenant)) {
                return false;
            }

            App::instance('orbit.empty.tenant', $tenant);

            return true;
        });

        Validator::extend('orbit.empty.is_all_age', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('Y', 'N');

            if (in_array($value, $statuses)) {
                $valid = true;
            }

            return $valid;
        });

        Validator::extend('orbit.empty.is_all_gender', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('Y', 'N');

            if (in_array($value, $statuses)) {
                $valid = true;
            }

            return $valid;
        });

        Validator::extend('orbit.empty.gender', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('M', 'F', 'U');

            if (in_array($value, $statuses)) {
                $valid = true;
            }

            return $valid;
        });

        Validator::extend('orbit.empty.age', function ($attribute, $value, $parameters) {
            $exist = AgeRange::excludeDeleted()
                        ->where('age_range_id', $value)
                        ->first();

            if (empty($exist)) {
                return false;
            }

            App::instance('orbit.empty.age', $exist);

            return true;
        });

/*
        // News deletion master password
        Validator::extend('orbit.masterpassword.delete', function ($attribute, $value, $parameters) {
            // Current Mall location
            $currentMall = Config::get('orbit.shop.id');

            // Get the master password from settings table
            $masterPassword = Setting::getMasterPasswordFor($currentMall);

            if (! is_object($masterPassword)) {
                // @Todo replace with language
                $message = 'The master password is not set.';
                ACL::throwAccessForbidden($message);
            }

            if (! Hash::check($value, $masterPassword->setting_value)) {
                $message = 'The master password is incorrect.';
                ACL::throwAccessForbidden($message);
            }

            return true;
        });
*/
    }

    /**
     * @param NewsModel $news
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveTranslations($news, $translations_json_string, $scenario = 'create')
    {
        /*
         * JSON structure: object with keys = merchant_language_id and values = ProductTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where NewsTranslation object is object with keys:
         *   news_name, description
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['news_name', 'description'];
        $user = $this->api->user;
        $operations = [];

        $data = @json_decode($translations_json_string);
        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
        }

        // translate for mall
        foreach ($data as $merchant_language_id => $translations) {
            $language = MerchantLanguage::excludeDeleted()
                // ->allowedForUser($user)
                ->where('merchant_language_id', '=', $merchant_language_id)
                ->first();
            if (empty($language)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
            }
            $existing_translation = NewsTranslation::excludeDeleted()
                ->where('news_id', '=', $news->news_id)
                ->where('merchant_language_id', '=', $merchant_language_id)
                ->first();
            if ($translations === null) {
                // deleting, verify exists
                if (empty($existing_translation)) {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
                }
                $operations[] = ['delete', $existing_translation];
            } else {
                foreach ($translations as $field => $value) {
                    if (!in_array($field, $valid_fields, true)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.key'));
                    }
                    if ($value !== null && !is_string($value)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.value'));
                    }
                }
                if (empty($existing_translation)) {
                    if (! empty(trim($translations->news_name))) {
                        $news_translation = NewsTranslation::excludeDeleted()
                                                    ->where('merchant_language_id', '=', $merchant_language_id)
                                                    ->where('news_name', '=', $translations->news_name)
                                                    ->first();
                        if (! empty($news_translation)) {
                            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.exists.news_name'));
                        }
                    }
                    $operations[] = ['create', $merchant_language_id, $translations];
                } else {
                    if (! empty(trim($translations->news_name))) {
                        $news_translation_but_not_me = NewsTranslation::excludeDeleted()
                                                    ->where('merchant_language_id', '=', $merchant_language_id)
                                                    ->where('news_id', '!=', $news->news_id)
                                                    ->where('news_name', '=', $translations->news_name)
                                                    ->first();
                        if (! empty($news_translation_but_not_me)) {
                            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.exists.news_name'));
                        }
                    }
                    $operations[] = ['update', $existing_translation, $translations];
                }
            }
        }

        foreach ($operations as $operation) {
            $op = $operation[0];

            if ($op === 'create') {

                // for translation per mall
                $new_translation = new NewsTranslation();
                $new_translation->news_id = $news->news_id;
                $new_translation->merchant_id = $news->mall_id;
                $new_translation->merchant_language_id = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $new_translation->{$field} = $value;
                }
                $new_translation->created_by = $this->api->user->user_id;
                $new_translation->modified_by = $this->api->user->user_id;
                $new_translation->save();

                // Fire an news which listen on orbit.news.after.translation.save
                // @param ControllerAPI $this
                // @param NewsTranslation $new_transalation
                Event::fire('orbit.news.after.translation.save', array($this, $new_translation));

                $news->setRelation('translation_'. $new_translation->merchant_language_id, $new_translation);
            }
            elseif ($op === 'update') {

                /** @var NewsTranslation $existing_translation */
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->status = $news->status;
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->save();

                // Fire an news which listen on orbit.news.after.translation.save
                // @param ControllerAPI $this
                // @param NewsTranslation $existing_transalation
                Event::fire('orbit.news.after.translation.save', array($this, $existing_translation));

                // return respones if any upload image or no
                $existing_translation->load('media');

                $news->setRelation('translation_'. $existing_translation->merchant_language_id, $existing_translation);

            }
            elseif ($op === 'delete') {
                /** @var NewsTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->delete();
            }
        }

    }

    protected function getTimezone($current_mall)
    {
        $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
            ->where('merchants.merchant_id','=', $current_mall)
            ->first();

        return $timezone->timezone_name;
    }

    protected function getTimezoneOffset($timezone)
    {
        $dt = new DateTime('now', new DateTimeZone($timezone));

        return $dt->format('P');
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    public function setReturnBuilder($bool)
    {
        $this->returnBuilder = $bool;

        return $this;
    }
}
