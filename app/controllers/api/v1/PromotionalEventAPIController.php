<?php
/**
 * An API controller for managing PromotionalEvent.
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
// use \Queue;

class PromotionalEventAPIController extends ControllerAPI
{
    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    protected $promotionalEventViewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    protected $promotionalEventModifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];

    /**
     * POST - Create New PromotionalEvent
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `mall_id`               (required) - Mall ID
     * @param string     `object_type`           (optional) - Object type. Valid value: promotion, promotionalevent.
     * @param string     `promotionalevent_name`             (required) - PromotionalEvent name
     * @param string     `status`                (required) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`           (optional) - Description
     * @param file       `images`                (optional) - PromotionalEvent image
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
     * @param string     `translations`          (optional) - For Translations
     * @param string     `reward_translations`   (optional) - For Reward Translations
     * @param string     `reward_type`
     * @param string     `reward_codes`
     * @param string     `partner_ids`
     * @param string     `is_exclusive`
     * @param string     `keywords`
     * @param string     `campaign_status`
     * @param string     `translations`
     * @param string     `is_new_user_only`
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewPromotionalEvent()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newpromotional_event = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.promotionalevent.postnewpromotionalevent.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.promotionalevent.postnewpromotionalevent.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promotionalevent.postnewpromotionalevent.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->promotionalEventModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.promotionalevent.postnewpromotionalevent.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $mall_id                = OrbitInput::post('current_mall');
            $object_type            = OrbitInput::post('object_type');
            $reward_type            = OrbitInput::post('reward_type');
            $promotional_event_name = OrbitInput::post('promotional_event_name');
            $description            = OrbitInput::post('description');
            $begin_date             = OrbitInput::post('begin_date');
            $end_date               = OrbitInput::post('end_date');
            $reward_codes           = (array) OrbitInput::post('reward_codes', []);
            $retailer_ids           = (array) OrbitInput::post('retailer_ids', []);
            $partner_ids            = (array) OrbitInput::post('partner_ids', []);
            $is_exclusive           = OrbitInput::post('is_exclusive', 'N');
            $keywords               = (array) OrbitInput::post('keywords', []);
            $is_all_gender          = OrbitInput::post('is_all_gender', 'Y');
            $gender_ids             = (array) OrbitInput::post('gender_ids', []);
            $is_all_age             = OrbitInput::post('is_all_age', 'Y');
            $age_range_ids          = (array) OrbitInput::post('age_range_ids', []);
            $campaign_status        = OrbitInput::post('campaign_status');
            $id_language_default    = OrbitInput::post('id_language_default');
            $translations           = OrbitInput::post('translations');
            $reward_translations    = OrbitInput::post('reward_translations');
            $sticky_order           = OrbitInput::post('sticky_order', '0');
            $is_new_user_only       = OrbitInput::post('is_new_user_only', 'N');
            $link_object_type       = OrbitInput::post('link_object_type');

            if (empty($campaign_status)) {
                $campaign_status = 'not started';
            }

            $status = 'inactive';
            if ($campaign_status === 'ongoing') {
                $status = 'active';
            }

            $validator_value = [
                'sticky_order'                => $sticky_order,
                'link_object_type'            => $link_object_type,
                'object_type'                 => $object_type,
                'reward_type'                 => $reward_type,
                'promotional_event_name'      => $promotional_event_name,
                'description'                 => $description,
                'begin_date'                  => $begin_date,
                'end_date'                    => $end_date,
                'reward_codes'                => $reward_codes,
                'retailer_ids'                => $retailer_ids,
                'id_language_default'         => $id_language_default,
                'is_all_gender'               => $is_all_gender,
                'is_all_age'                  => $is_all_age,
                'status'                      => $status,
                'is_new_user_only'            => $is_new_user_only,
            ];
            $validator_validation = [
                'sticky_order'                => 'in:0,1',
                'link_object_type'            => 'orbit.empty.link_object_type',
                'object_type'                 => 'required|orbit.empty.promotional_event_object_type',
                'reward_type'                 => 'required|orbit.empty.promotional_event_reward_type',
                'promotional_event_name'      => 'required|max:255',
                'description'                 => 'required',
                'begin_date'                  => 'required|date|orbit.empty.hour_format',
                'end_date'                    => 'required|date|orbit.empty.hour_format',
                'reward_codes'                => 'required|array',
                'retailer_ids'                => 'required|array',
                'id_language_default'         => 'required|orbit.empty.language_default',
                'is_all_gender'               => 'required|orbit.empty.is_all_gender',
                'is_all_age'                  => 'required|orbit.empty.is_all_age',
                'status'                      => 'required|orbit.empty.promotional_event_status',
                'is_new_user_only'            => 'in:Y,N',
            ];
            $validator_message = [
                'sticky_order.in' => 'The sticky order value must 0 or 1',
            ];

            if (! empty($is_exclusive) && ! empty($partner_ids)) {
                $validator_value['partner_exclusive']               = $is_exclusive;
                $validator_validation['partner_exclusive']          = 'in:Y,N|orbit.empty.exclusive_partner';
                $validator_message['orbit.empty.exclusive_partner'] = 'Partner is not exclusive / inactive';
            }

            $validator = Validator::make(
                $validator_value,
                $validator_validation,
                $validator_message
            );

            Event::fire('orbit.promotionalevent.postnewpromotionalevent.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // validation gender
            foreach ($gender_ids as $gender_id_check) {
                $validator = Validator::make(
                    array(
                        'gender_id'   => $gender_id_check,
                    ),
                    array(
                        'gender_id'   => 'orbit.empty.gender',
                    )
                );

                Event::fire('orbit.promotionalevent.postnewpromotionalevent.before.gendervalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.promotionalevent.postnewpromotionalevent.after.retailervalidation', array($this, $validator));
            }

            // validation age range
            foreach ($age_range_ids as $age_range_id_check) {
                $validator = Validator::make(
                    array(
                        'age_range_id'   => $age_range_id_check,
                    ),
                    array(
                        'age_range_id'   => 'orbit.empty.age',
                    )
                );

                Event::fire('orbit.promotionalevent.postnewpromotionalevent.before.retailervalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.promotionalevent.postnewpromotionalevent.after.retailervalidation', array($this, $validator));
            }

            // validation reward_codes
            $unique_reward_codes = array_unique($reward_codes);

            if (count($reward_codes) !== count($unique_reward_codes)) {
                $get_duplicate = array_diff_key($reward_codes, $unique_reward_codes);
                $errorMessage = '';

                foreach ($get_duplicate as $idx => $reward_code) {
                    $errorMessage = $errorMessage . "\n" . sprintf('The reward codes you supplied have duplicates: %s', $reward_code);
                }

                throw new Exception($errorMessage);
            }

            Event::fire('orbit.promotionalevent.postnewpromotionalevent.after.validation', array($this, $validator));

            // Get data status like ongoing, stopped etc
            $idStatus = CampaignStatus::select('campaign_status_id','campaign_status_name')->where('campaign_status_name', $campaign_status)->first();

            $newpromotional_event = new News();
            $newpromotional_event->mall_id = $mall_id;
            $newpromotional_event->news_name = $promotional_event_name;
            $newpromotional_event->description = $description;
            $newpromotional_event->object_type = $object_type;
            $newpromotional_event->status = $status;
            $newpromotional_event->campaign_status_id = $idStatus->campaign_status_id;
            $newpromotional_event->begin_date = $begin_date;
            $newpromotional_event->end_date = $end_date;
            $newpromotional_event->link_object_type = $link_object_type;
            $newpromotional_event->is_all_age = $is_all_age;
            $newpromotional_event->is_all_gender = $is_all_gender;
            $newpromotional_event->is_having_reward = 'Y';
            $newpromotional_event->created_by = $this->api->user->user_id;
            $newpromotional_event->sticky_order = $sticky_order;
            $newpromotional_event->is_exclusive = $is_exclusive;

            Event::fire('orbit.promotionalevent.postnewpromotionalevent.before.save', array($this, $newpromotional_event));

            // save new promotional event
            $newpromotional_event->save();

            // Return campaign status name
            $newpromotional_event->campaign_status = $idStatus->campaign_status_name;

            // save reward details
            $new_reward_detail = new RewardDetail();
            $new_reward_detail->object_id = $newpromotional_event->news_id;
            $new_reward_detail->object_type = $newpromotional_event->object_type;
            $new_reward_detail->reward_type = $reward_type;
            $new_reward_detail->is_new_user_only = $is_new_user_only;
            $new_reward_detail->save();

            // save reward detail codes
            if (! empty($reward_codes)) {
                foreach ($reward_codes as $reward_code) {
                    $new_reward_detail_code = new RewardDetailCode();
                    $new_reward_detail_code->reward_detail_id = $new_reward_detail->reward_detail_id;
                    $new_reward_detail_code->reward_code = $reward_code;
                    $new_reward_detail_code->expired_date = $end_date;
                    $new_reward_detail_code->status = 'available';
                    $new_reward_detail_code->save();
                }
            }

            // save link to tenant.
            $promotional_event_retailers = array();
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

                $promotional_event_retailer = new NewsMerchant();
                $promotional_event_retailer->merchant_id = $tenant_id;
                $promotional_event_retailer->news_id = $newpromotional_event->news_id;
                $promotional_event_retailer->object_type = $isMall;
                $promotional_event_retailer->save();

                $promotional_event_retailers[] = $promotional_event_retailer;
            }
            $newpromotional_event->tenants = $promotional_event_retailers;

            // save ObjectPartner
            $object_partners = array();
            foreach ($partner_ids as $partner_id) {
                $object_partner = new ObjectPartner();
                $object_partner->object_id = $newpromotional_event->news_id;
                $object_partner->object_type = $object_type;
                $object_partner->partner_id = $partner_id;
                $object_partner->save();
                $object_partners[] = $object_partner;
            }
            $newpromotional_event->partners = $object_partners;

            //save to user campaign
            $user_campaign = new UserCampaign();
            $user_campaign->user_id = $user->user_id;
            $user_campaign->campaign_id = $newpromotional_event->news_id;
            $user_campaign->campaign_type = 'news';
            $user_campaign->save();

            // save CampaignAge
            $promotional_event_ages = array();
            foreach ($age_range_ids as $age_range) {
                $promotional_event_age = new CampaignAge();
                $promotional_event_age->campaign_type = $object_type;
                $promotional_event_age->campaign_id = $newpromotional_event->news_id;
                $promotional_event_age->age_range_id = $age_range;
                $promotional_event_age->save();
                $promotional_event_ages[] = $promotional_event_age;
            }
            $newpromotional_event->age = $promotional_event_ages;

            // save CampaignGender
            $promotional_event_genders = array();
            foreach ($gender_ids as $gender) {
                $promotional_event_gender = new CampaignGender();
                $promotional_event_gender->campaign_type = $object_type;
                $promotional_event_gender->campaign_id = $newpromotional_event->news_id;
                $promotional_event_gender->gender_value = $gender;
                $promotional_event_gender->save();
                $gender_name = null;
                $promotional_event_genders[] = $promotional_event_gender;
            }
            $newpromotional_event->gender = $promotional_event_genders;

            // save Keyword
            $promotional_event_keywords = array();
            foreach ($keywords as $keyword) {
                $keyword_id = null;

                foreach ($mallid as $mall) {
                    $exist_keyword = Keyword::excludeDeleted()
                        ->where('keyword', '=', $keyword)
                        ->where('merchant_id', '=', $mall)
                        ->first();

                    if (empty($exist_keyword)) {
                        $new_keyword = new Keyword();
                        $new_keyword->merchant_id = $mall;
                        $new_keyword->keyword = $keyword;
                        $new_keyword->status = 'active';
                        $new_keyword->created_by = $this->api->user->user_id;
                        $new_keyword->modified_by = $this->api->user->user_id;
                        $new_keyword->save();

                        $keyword_id = $new_keyword->keyword_id;
                        $promotional_event_keywords[] = $new_keyword;
                    } else {
                        $keyword_id = $exist_keyword->keyword_id;
                        $promotional_event_keywords[] = $exist_keyword;
                    }

                    $new_keyword_object = new KeywordObject();
                    $new_keyword_object->keyword_id = $keyword_id;
                    $new_keyword_object->object_id = $newpromotional_event->news_id;
                    $new_keyword_object->object_type = $object_type;
                    $new_keyword_object->save();
                }
            }
            $newpromotional_event->keywords = $promotional_event_keywords;

            Event::fire('orbit.promotionalevent.postnewpromotionalevent.after.save', array($this, $newpromotional_event));

            //save campaign price
            $campaignbaseprice = CampaignBasePrice::where('merchant_id', '=', $newpromotional_event->mall_id)
                                            ->where('campaign_type', '=', $object_type)
                                            ->first();

            $baseprice = 0;
            if (! empty($campaignbaseprice->price)) {
                $baseprice = $campaignbaseprice->price;
            }

            $campaignprice = new CampaignPrice();
            $campaignprice->base_price = $baseprice;
            $campaignprice->campaign_type = $object_type;
            $campaignprice->campaign_id = $newpromotional_event->news_id;
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
            $campaignhistory->campaign_id = $newpromotional_event->news_id;
            $campaignhistory->campaign_history_action_id = $activeid;
            $campaignhistory->number_active_tenants = 0;
            $campaignhistory->campaign_cost = 0;
            $campaignhistory->created_by = $this->api->user->user_id;
            $campaignhistory->modified_by = $this->api->user->user_id;
            $campaignhistory->save();

            //save campaign histories (tenant)
            $withSpending = array('mall' => 'N', 'tenant' => 'Y');
            foreach ($retailer_ids as $retailer_id) {
                $type = 'tenant';
                $data = @json_decode($retailer_id);
                $tenant_id = $data->tenant_id;
                $mall_id = $data->mall_id;
                // insert tenant/merchant to campaign history
                $tenantstatus = CampaignLocation::select('status', 'object_type')->where('merchant_id', $tenant_id)->first();
                $spendingrule = SpendingRule::select('with_spending')->where('object_id', $tenant_id)->first();

                if ($tenantstatus->object_type === 'mall') {
                    $type = 'mall';
                }

                if ($spendingrule) {
                    $spending = $spendingrule->with_spending;
                } else {
                    $spending = $withSpending[$type];
                }

                if (($tenantstatus->status === 'active') && ($spending === 'Y')) {
                    $addtenant = new CampaignHistory();
                    $addtenant->campaign_type = $object_type;
                    $addtenant->campaign_id = $newpromotional_event->news_id;
                    $addtenant->campaign_external_value = $tenant_id;
                    $addtenant->campaign_history_action_id = $addtenantid;
                    $addtenant->number_active_tenants = 0;
                    $addtenant->created_by = $this->api->user->user_id;
                    $addtenant->modified_by = $this->api->user->user_id;
                    $addtenant->campaign_cost = 0;
                    $addtenant->save();
                }
            }

            // translation for promotional event
            OrbitInput::post('translations', function($promotional_event_translations) use ($newpromotional_event) {
                $this->validateAndSaveTranslations($newpromotional_event, $promotional_event_translations, 'create');
            });

            // translation for reward detail
            OrbitInput::post('reward_translations', function($reward_translations) use ($newpromotional_event, $new_reward_detail, $id_language_default) {
                $this->validateAndSaveRewardTranslations($newpromotional_event, $new_reward_detail, $reward_translations, $id_language_default, 'create');
            });

            // Default language for pmp_account is required
            $malls = implode("','", $mallid);
            $prefix = DB::getTablePrefix();
            $isAvailable = NewsTranslation::where('news_id', '=', $newpromotional_event->news_id)
                                        ->whereRaw("
                                            {$prefix}news_translations.merchant_language_id = (
                                                SELECT language_id
                                                FROM {$prefix}languages
                                                WHERE name = (SELECT mobile_default_language FROM {$prefix}campaign_account WHERE user_id = {$this->quote($this->api->user->user_id)})
                                            )
                                        ")
                                        ->where(function($query) {
                                            $query->where('news_name', '=', '')
                                                  ->orWhere('description', '=', '')
                                                  ->orWhereNull('news_name')
                                                  ->orWhereNull('description');
                                          })
                                        ->first();

            $required_name = false;
            $required_desc = false;

            if (is_object($isAvailable)) {
                if ($isAvailable->news_name === '' || empty($isAvailable->news_name)) {
                $required_name = true;
                }
                if ($isAvailable->description === '' || empty($isAvailable->description)) {
                    $required_desc = true;
                }
            }

            if ($required_name === true && $required_desc === true) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_both', ['type' => $object_type]);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } elseif ($required_name === true) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_name', ['type' => $object_type]);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } elseif ($required_desc === true) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_desc', ['type' => $object_type]);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $this->response->data = $newpromotional_event;

            // Commit the changes
            $this->commit();

            // queue for campaign spending promotionalevent & promotion
            Queue::push('Orbit\\Queue\\SpendingCalculation', [
                'campaign_id' => $newpromotional_event->news_id,
                'campaign_type' => $object_type,
            ]);

            // Successfull Creation
            $activityNotes = sprintf('PromotionalEvent Created: %s', $newpromotional_event->promotionalevent_name);
            $activity->setUser($user)
                    ->setActivityName('create_promotionalevent')
                    ->setActivityNameLong('Create PromotionalEvent OK')
                    ->setObject($newpromotional_event)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.promotionalevent.postnewpromotionalevent.after.commit', array($this, $newpromotional_event));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.promotionalevent.postnewpromotionalevent.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_promotionalevent')
                    ->setActivityNameLong('Create PromotionalEvent Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promotionalevent.postnewpromotionalevent.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_promotionalevent')
                    ->setActivityNameLong('Create PromotionalEvent Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.promotionalevent.postnewpromotionalevent.query.error', array($this, $e));

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
                    ->setActivityName('create_promotionalevent')
                    ->setActivityNameLong('Create PromotionalEvent Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.promotionalevent.postnewpromotionalevent.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_promotionalevent')
                    ->setActivityNameLong('Create PromotionalEvent Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update PromotionalEvent
     *
     * @author Tian <tian@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     * @author Shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotionalevent_id`               (required) - PromotionalEvent ID
     * @param integer    `mall_id`               (optional) - Mall ID
     * @param string     `promotionalevent_name`             (optional) - PromotionalEvent name
     * @param string     `object_type`           (optional) - Object type. Valid value: promotion, promotionalevent.
     * @param string     `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`           (optional) - Description
     * @param datetime   `begin_date`            (optional) - Begin date. Example: 2015-04-15 00:00:00
     * @param datetime   `end_date`              (optional) - End date. Example: 2015-04-18 23:59:59
     * @param integer    `sticky_order`          (optional) - Sticky order.
     * @param file       `images`                (optional) - PromotionalEvent image
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
    public function postUpdatePromotionalEvent()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedpromotionalevent = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.promotionalevent.postupdatepromotionalevent.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.promotionalevent.postupdatepromotionalevent.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promotionalevent.postupdatepromotionalevent.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('update_promotionalevent')) {
                Event::fire('orbit.promotionalevent.postupdatepromotionalevent.authz.notallowed', array($this, $user));
                $updatePromotionalEventLang = Lang::get('validation.orbit.actionlist.update_promotionalevent');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updatePromotionalEventLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->promotionaleventModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.promotionalevent.postupdatepromotionalevent.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $promotionalevent_id = OrbitInput::post('promotionalevent_id');
            $mall_id = OrbitInput::post('current_mall');;
            $object_type = OrbitInput::post('object_type');
            $campaign_status = OrbitInput::post('campaign_status');
            $link_object_type = OrbitInput::post('link_object_type');
            $end_date = OrbitInput::post('end_date');
            $begin_date = OrbitInput::post('begin_date');
            $id_language_default = OrbitInput::post('id_language_default');
            $is_all_gender = OrbitInput::post('is_all_gender');
            $is_all_age = OrbitInput::post('is_all_age');
            $translations = OrbitInput::post('translations');
            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;
            $partner_ids = OrbitInput::post('partner_ids');
            $partner_ids = (array) $partner_ids;
            $is_exclusive = OrbitInput::post('is_exclusive');

            $idStatus = CampaignStatus::select('campaign_status_id')->where('campaign_status_name', $campaign_status)->first();
            $status = 'inactive';
            if ($campaign_status === 'ongoing') {
                $status = 'active';
            }

            $data = array(
                'promotionalevent_id'             => $promotionalevent_id,
                'current_mall'        => $mall_id,
                'object_type'         => $object_type,
                'status'              => $status,
                'link_object_type'    => $link_object_type,
                'end_date'            => $end_date,
                'id_language_default' => $id_language_default,
                'is_all_gender'       => $is_all_gender,
                'is_all_age'          => $is_all_age,
                'partner_exclusive'    => $is_exclusive,
            );

            // Validate promotionalevent_name only if exists in POST.
            OrbitInput::post('promotionalevent_name', function($promotionalevent_name) use (&$data) {
                $data['promotionalevent_name'] = $promotionalevent_name;
            });

            $validator = Validator::make(
                $data,
                array(
                    'promotionalevent_id'             => 'required|orbit.update.promotionalevent:' . $object_type,
                    'promotionalevent_name'           => 'sometimes|required|max:255',
                    'object_type'         => 'required|orbit.empty.promotionalevent_object_type',
                    'status'              => 'orbit.empty.promotionalevent_status',
                    'link_object_type'    => 'orbit.empty.link_object_type',
                    'end_date'            => 'date||orbit.empty.hour_format',
                    'id_language_default' => 'required|orbit.empty.language_default',
                    'is_all_gender'       => 'required|orbit.empty.is_all_gender',
                    'is_all_age'          => 'required|orbit.empty.is_all_age',
                    'partner_exclusive'   => 'in:Y,N|orbit.empty.exclusive_partner',
                ),
                array(
                   'promotionalevent_name_exists_but_me' => Lang::get('validation.orbit.exists.promotionalevent_name'),
                   'orbit.update.promotionalevent' => 'Cannot update campaign with status ' . $campaign_status,
                   'orbit.empty.exclusive_partner'  => 'Partner is not exclusive / inactive',
                )
            );

            Event::fire('orbit.promotionalevent.postupdatepromotionalevent.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.promotionalevent.postupdatepromotionalevent.after.validation', array($this, $validator));

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

            $prefix = DB::getTablePrefix();

            $updatedpromotionalevent = PromotionalEvent::with('tenants')->excludeDeleted()->where('promotionalevent_id', $promotionalevent_id)->first();

            // this is for send email to marketing, before and after list
            $beforeUpdatedPromotionalEvent = PromotionalEvent::selectRaw("{$prefix}promotionalevent.*,
                                                        DATE_FORMAT({$prefix}promotionalevent.end_date, '%d/%m/%Y %H:%i') as end_date")
                                    ->with('translations.language', 'translations.media', 'ages.ageRange', 'genders', 'keywords', 'campaign_status')
                                    ->excludeDeleted()
                                    ->where('promotionalevent_id', $promotionalevent_id)
                                    ->first();

            $statusdb = $updatedpromotionalevent->status;
            $enddatedb = $updatedpromotionalevent->end_date;
            //check get merchant for db
            $promotionaleventmerchantdb = PromotionalEventMerchant::select('merchant_id')->where('promotionalevent_id', $promotionalevent_id)->get()->toArray();
            $merchantdb = array();
            foreach($promotionaleventmerchantdb as $merchantdbid) {
                $merchantdb[] = $merchantdbid['merchant_id'];
            }

            // Check for english content
            $jsonTranslations = @json_decode($translations);
            if (json_last_error() != JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
            }

            // save PromotionalEvent
            OrbitInput::post('mall_id', function($mall_id) use ($updatedpromotionalevent) {
                $updatedpromotionalevent->mall_id = $mall_id;
            });

            OrbitInput::post('object_type', function($object_type) use ($updatedpromotionalevent) {
                $updatedpromotionalevent->object_type = $object_type;
            });

            OrbitInput::post('promotionalevent_name', function($promotionalevent_name) use ($updatedpromotionalevent) {
                $updatedpromotionalevent->promotionalevent_name = $promotionalevent_name;
            });

            OrbitInput::post('description', function($description) use ($updatedpromotionalevent) {
                $updatedpromotionalevent->description = $description;
            });

            OrbitInput::post('campaign_status', function($campaign_status) use ($updatedpromotionalevent, $status, $idStatus) {
                $updatedpromotionalevent->status = $status;
                $updatedpromotionalevent->campaign_status_id = $idStatus->campaign_status_id;
            });

            OrbitInput::post('begin_date', function($begin_date) use ($updatedpromotionalevent) {
                $updatedpromotionalevent->begin_date = $begin_date;
            });

            OrbitInput::post('end_date', function($end_date) use ($updatedpromotionalevent) {
                $updatedpromotionalevent->end_date = $end_date;
            });

            OrbitInput::post('is_all_gender', function($is_all_gender) use ($updatedpromotionalevent) {
                $updatedpromotionalevent->is_all_gender = $is_all_gender;
            });

            OrbitInput::post('is_all_age', function($is_all_age) use ($updatedpromotionalevent) {
                $updatedpromotionalevent->is_all_age = $is_all_age;
            });

            OrbitInput::post('is_popup', function($is_popup) use ($updatedpromotionalevent) {
                $updatedpromotionalevent->is_popup = $is_popup;
            });

            OrbitInput::post('sticky_order', function($sticky_order) use ($updatedpromotionalevent) {
                $updatedpromotionalevent->sticky_order = $sticky_order;
            });

            OrbitInput::post('link_object_type', function($link_object_type) use ($updatedpromotionalevent) {
                if (trim($link_object_type) === '') {
                    $link_object_type = NULL;
                }
                $updatedpromotionalevent->link_object_type = $link_object_type;
            });

            OrbitInput::post('is_exclusive', function($is_exclusive) use ($updatedpromotionalevent) {
                $updatedpromotionalevent->is_exclusive = $is_exclusive;
            });

            OrbitInput::post('translations', function($translation_json_string) use ($updatedpromotionalevent) {
                $this->validateAndSaveTranslations($updatedpromotionalevent, $translation_json_string, 'update');
            });

            // Default language for pmp_account is required
            $malls = implode("','", $mallid);
            $prefix = DB::getTablePrefix();
            $isAvailable = NewsTranslation::where('news_id', '=', $promotionalevent_id)
                                        ->whereRaw("
                                            {$prefix}news_translations.merchant_language_id = (
                                                SELECT language_id
                                                FROM {$prefix}languages
                                                WHERE name = (SELECT mobile_default_language FROM {$prefix}campaign_account WHERE user_id = {$this->quote($this->api->user->user_id)})
                                            )
                                        ")
                                        ->where(function($query) {
                                            $query->where('news_name', '=', '')
                                                  ->orWhere('description', '=', '')
                                                  ->orWhereNull('news_name')
                                                  ->orWhereNull('description');
                                          })
                                        ->first();

            $required_name = false;
            $required_desc = false;

            if (is_object($isAvailable)) {
                if ($isAvailable->promotionalevent_name === '' || empty($isAvailable->promotionalevent_name)) {
                $required_name = true;
                }
                if ($isAvailable->description === '' || empty($isAvailable->description)) {
                    $required_desc = true;
                }
            }

            if ($required_name === true && $required_desc === true) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_both', ['type' => $object_type]);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } elseif ($required_name === true) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_name', ['type' => $object_type]);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } elseif ($required_desc === true) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_desc', ['type' => $object_type]);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $updatedpromotionalevent->modified_by = $this->api->user->user_id;
            $updatedpromotionalevent->touch();

            // save PromotionalEventMerchant
            OrbitInput::post('no_retailer', function($no_retailer) use ($updatedpromotionalevent) {
                if ($no_retailer == 'Y') {
                    $deleted_retailer_ids = PromotionalEventMerchant::where('promotionalevent_id', $updatedpromotionalevent->promotionalevent_id)->get(array('merchant_id'))->toArray();
                    $updatedpromotionalevent->tenants()->detach($deleted_retailer_ids);
                    $updatedpromotionalevent->load('tenants');
                }
            });

            OrbitInput::post('is_all_gender', function($is_all_gender) use ($updatedpromotionalevent, $promotionalevent_id, $object_type) {
                $updatedpromotionalevent->is_all_gender = $is_all_gender;
                if ($is_all_gender == 'Y') {
                    $deleted_campaign_genders = CampaignGender::where('campaign_id', '=', $promotionalevent_id)
                                                            ->where('campaign_type', '=', $object_type);
                    $deleted_campaign_genders->delete();
                }
            });

            OrbitInput::post('is_all_age', function($is_all_age) use ($updatedpromotionalevent, $promotionalevent_id, $object_type) {
                $updatedpromotionalevent->is_all_age = $is_all_age;
                if ($is_all_age == 'Y') {
                    $deleted_campaign_ages = CampaignAge::where('campaign_id', '=', $promotionalevent_id)
                                                            ->where('campaign_type', '=', $object_type);
                    $deleted_campaign_ages->delete();
                }
            });

            OrbitInput::post('retailer_ids', function($retailer_ids) use ($updatedpromotionalevent, $promotionalevent_id, $mallid) {
                // validate retailer_ids

                // to do : add validation for tenant

                // Delete old data
                $delete_retailer = PromotionalEventMerchant::where('promotionalevent_id', '=', $promotionalevent_id);
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

                    $promotionaleventretailer = new PromotionalEventMerchant();
                    $promotionaleventretailer->merchant_id = $tenant_id;
                    $promotionaleventretailer->promotionalevent_id = $promotionalevent_id;
                    $promotionaleventretailer->object_type = $isMall;
                    $promotionaleventretailer->save();
                }
            });

            OrbitInput::post('partner_ids', function($partner_ids) use ($updatedpromotionalevent, $promotionalevent_id, $object_type) {
                // validate retailer_ids
                $partner_ids = (array) $partner_ids;

                // Delete old data
                $delete_object_partner = ObjectPartner::where('object_id', '=', $promotionalevent_id);
                $delete_object_partner->delete();

                $objectPartners = array();
                // Insert new data
                if(array_filter($partner_ids)) {
                    foreach ($partner_ids as $partner_id) {
                        $objectPartner = new ObjectPartner();
                        $objectPartner->object_id = $promotionalevent_id;
                        $objectPartner->object_type = $object_type;
                        $objectPartner->partner_id = $partner_id;
                        $objectPartner->save();
                        $objectPartners[] = $objectPartner;
                    }
                }
                $updatedpromotionalevent->partners = $objectPartners;
            });

            OrbitInput::post('gender_ids', function($gender_ids) use ($updatedpromotionalevent, $promotionalevent_id, $object_type) {
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

                    Event::fire('orbit.promotionalevent.postupdatepromotionalevent.before.gendervalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.promotionalevent.postupdatepromotionalevent.after.gendervalidation', array($this, $validator));
                }

                // Delete old data
                $deleted_campaign_genders = CampaignGender::where('campaign_id', '=', $promotionalevent_id)
                                                        ->where('campaign_type', '=', $object_type);
                $deleted_campaign_genders->delete();

                // Insert new data
                $promotionaleventGenders = array();
                foreach ($gender_ids as $gender) {
                    $promotionaleventGender = new CampaignGender();
                    $promotionaleventGender->campaign_type = $object_type;
                    $promotionaleventGender->campaign_id = $promotionalevent_id;
                    $promotionaleventGender->gender_value = $gender;
                    $promotionaleventGender->save();
                    $promotionaleventGenders[] = $promotionaleventGenders;
                }
                $updatedpromotionalevent->gender = $promotionaleventGenders;

            });

            OrbitInput::post('age_range_ids', function($age_range_ids) use ($updatedpromotionalevent, $promotionalevent_id, $object_type) {
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

                    Event::fire('orbit.promotionalevent.postupdatepromotionalevent.before.agevalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.promotionalevent.postupdatepromotionalevent.after.agevalidation', array($this, $validator));
                }

                // Delete old data
                $deleted_campaign_ages = CampaignAge::where('campaign_id', '=', $promotionalevent_id)
                                                        ->where('campaign_type', '=', $object_type);
                $deleted_campaign_ages->delete();

                // Insert new data
                $promotionaleventAges = array();
                foreach ($age_range_ids as $age_range) {
                    $promotionaleventAge = new CampaignAge();
                    $promotionaleventAge->campaign_type = $object_type;
                    $promotionaleventAge->campaign_id = $promotionalevent_id;
                    $promotionaleventAge->age_range_id = $age_range;
                    $promotionaleventAge->save();
                    $promotionaleventAges[] = $promotionaleventAges;
                }
                $updatedpromotionalevent->age = $promotionaleventAges;

            });

            // Delete old data
            $deleted_keyword_object = KeywordObject::where('object_id', '=', $promotionalevent_id)
                                                    ->where('object_type', '=', $object_type);
            $deleted_keyword_object->delete();

            OrbitInput::post('keywords', function($keywords) use ($updatedpromotionalevent, $mall_id, $user, $promotionalevent_id, $object_type, $mallid) {
                // Insert new data
                $promotionaleventKeywords = array();
                foreach ($keywords as $keyword) {
                    $keyword_id = null;

                    foreach ($mallid as $mall) {

                        $existKeyword = Keyword::excludeDeleted()
                            ->where('keyword', '=', $keyword)
                            ->where('merchant_id', '=', $mall)
                            ->first();

                        if (empty($existKeyword)) {
                            $newKeyword = new Keyword();
                            $newKeyword->merchant_id = $mall;
                            $newKeyword->keyword = $keyword;
                            $newKeyword->status = 'active';
                            $newKeyword->created_by = $user->user_id;
                            $newKeyword->modified_by = $user->user_id;
                            $newKeyword->save();

                            $keyword_id = $newKeyword->keyword_id;
                            $promotionaleventKeywords[] = $newKeyword;
                        } else {
                            $keyword_id = $existKeyword->keyword_id;
                            $promotionaleventKeywords[] = $existKeyword;
                        }


                        $newKeywordObject = new KeywordObject();
                        $newKeywordObject->keyword_id = $keyword_id;
                        $newKeywordObject->object_id = $promotionalevent_id;
                        $newKeywordObject->object_type = $object_type;
                        $newKeywordObject->save();
                    }

                }
                $updatedpromotionalevent->keywords = $promotionaleventKeywords;
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
                $campaignhistory->campaign_id = $promotionalevent_id;
                $campaignhistory->campaign_history_action_id = $activeid;
                $campaignhistory->number_active_tenants = 0;
                $campaignhistory->campaign_cost = 0;
                $campaignhistory->created_by = $this->api->user->user_id;
                $campaignhistory->modified_by = $this->api->user->user_id;
                $campaignhistory->save();

            } else {
                //check for first time insert for that day
                $utcNow = Carbon::now();
                $checkFirst = CampaignHistory::where('campaign_id', '=', $promotionalevent_id)->where('created_at', 'like', $utcNow->toDateString().'%')->count();
                if ($checkFirst === 0){
                    $actionstatus = 'activate';
                    if ($statusdb === 'inactive') {
                        $actionstatus = 'deactivate';
                    }
                    $activeid = CampaignHistoryAction::getIdFromAction($actionstatus);
                    $campaignhistory = new CampaignHistory();
                    $campaignhistory->campaign_type = $object_type;
                    $campaignhistory->campaign_id = $promotionalevent_id;
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
            $withSpending = array('mall' => 'N', 'tenant' => 'Y');
            if (! empty($removetenant)) {
                $actionhistory = 'delete';
                $addtenantid = CampaignHistoryAction::getIdFromAction('delete_tenant');
                //save campaign histories (tenant)
                foreach ($removetenant as $retailer_id) {
                    // insert tenant/merchant to campaign history
                    $type = 'tenant';
                    $tenantstatus = CampaignLocation::select('status', 'object_type')->where('merchant_id', $retailer_id)->first();
                    $spendingrule = SpendingRule::select('with_spending')->where('object_id', $retailer_id)->first();

                    if ($tenantstatus->object_type === 'mall') {
                        $type = 'mall';
                    }

                    if ($spendingrule) {
                        $spending = $spendingrule->with_spending;
                    } else {
                        $spending = $withSpending[$type];
                    }

                    if (($tenantstatus->status === 'active') && ($spending === 'Y')) {
                        $tenanthistory = new CampaignHistory();
                        $tenanthistory->campaign_type = $object_type;
                        $tenanthistory->campaign_id = $promotionalevent_id;
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
                    $type = 'tenant';
                    $tenantstatus = CampaignLocation::select('status', 'object_type')->where('merchant_id', $retailer_id)->first();
                    $spendingrule = SpendingRule::select('with_spending')->where('object_id', $retailer_id)->first();

                    if ($tenantstatus->object_type === 'mall') {
                        $type = 'mall';
                    }

                    if ($spendingrule) {
                        $spending = $spendingrule->with_spending;
                    } else {
                        $spending = $withSpending[$type];
                    }

                    if (($tenantstatus->status === 'active') && ($spending === 'Y')) {
                        $tenanthistory = new CampaignHistory();
                        $tenanthistory->campaign_type = $object_type;
                        $tenanthistory->campaign_id = $promotionalevent_id;
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

            $tempContent = new TemporaryContent();
            $tempContent->contents = serialize($beforeUpdatedPromotionalEvent);
            $tempContent->save();

            // update promotion advert
            if ($updatedpromotionalevent->object_type === 'promotion') {
                if (! empty($campaign_status) || $campaign_status !== '') {
                    $promotionAdverts = Advert::excludeDeleted()
                                        ->where('link_object_id', $updatedpromotionalevent->promotionalevent_id)
                                        ->update(['status'     => $updatedpromotionalevent->status]);
                }

                if (! empty($end_date) || $end_date !== '') {
                    $promotionAdverts = Advert::excludeDeleted()
                                        ->where('link_object_id', $updatedpromotionalevent->promotionalevent_id)
                                        ->where('end_date', '>', $updatedpromotionalevent->end_date)
                                        ->update(['end_date'   => $updatedpromotionalevent->end_date]);
                }
            }

            Event::fire('orbit.promotionalevent.postupdatepromotionalevent.after.save', array($this, $updatedpromotionalevent));
            $this->response->data = $updatedpromotionalevent;
            // $this->response->data->translation_default = $updatedpromotionalevent_default_language;

            // Commit the changes
            $this->commit();

            // queue for campaign spending promotionalevent & promotion
            Queue::push('Orbit\\Queue\\SpendingCalculation', [
                'campaign_id' => $promotionalevent_id,
                'campaign_type' => $object_type,
            ]);

            // Successfull Update
            $activityNotes = sprintf('PromotionalEvent updated: %s', $updatedpromotionalevent->promotionalevent_name);
            $activity->setUser($user)
                    ->setActivityName('update_promotionalevent')
                    ->setActivityNameLong('Update PromotionalEvent OK')
                    ->setObject($updatedpromotionalevent)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.promotionalevent.postupdatepromotionalevent.after.commit', array($this, $updatedpromotionalevent, $tempContent->temporary_content_id));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.promotionalevent.postupdatepromotionalevent.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_promotionalevent')
                    ->setActivityNameLong('Update PromotionalEvent Failed')
                    ->setObject($updatedpromotionalevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promotionalevent.postupdatepromotionalevent.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_promotionalevent')
                    ->setActivityNameLong('Update PromotionalEvent Failed')
                    ->setObject($updatedpromotionalevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.promotionalevent.postupdatepromotionalevent.query.error', array($this, $e));

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
                    ->setActivityName('update_promotionalevent')
                    ->setActivityNameLong('Update PromotionalEvent Failed')
                    ->setObject($updatedpromotionalevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.promotionalevent.postupdatepromotionalevent.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = [$e->getMessage(), $e->getFile(), $e->getLine()];

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_promotionalevent')
                    ->setActivityNameLong('Update PromotionalEvent Failed')
                    ->setObject($updatedpromotionalevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    /**
     * POST - Delete PromotionalEvent
     *
     * @author Tian <tian@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotionalevent_id`                  (required) - ID of the promotionalevent
     * @param string     `password`                 (required) - master password
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeletePromotionalEvent()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deletepromotionalevent = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.promotionalevent.postdeletepromotionalevent.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.promotionalevent.postdeletepromotionalevent.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promotionalevent.postdeletepromotionalevent.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('delete_promotionalevent')) {
                Event::fire('orbit.promotionalevent.postdeletepromotionalevent.authz.notallowed', array($this, $user));
                $deletePromotionalEventLang = Lang::get('validation.orbit.actionlist.delete_promotionalevent');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deletePromotionalEventLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->promotionaleventModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.promotionalevent.postdeletepromotionalevent.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $promotionalevent_id = OrbitInput::post('promotionalevent_id');
            // $password = OrbitInput::post('password');

            $validator = Validator::make(
                array(
                    'promotionalevent_id'  => $promotionalevent_id,
                    // 'password' => $password,
                ),
                array(
                    'promotionalevent_id'  => 'required|orbit.empty.promotionalevent',
                    // 'password' => 'required|orbit.masterpassword.delete',
                ),
                array(
                    // 'required.password'             => 'The master is password is required.',
                    // 'orbit.masterpassword.delete'   => 'The password is incorrect.'
                )
            );

            Event::fire('orbit.promotionalevent.postdeletepromotionalevent.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.promotionalevent.postdeletepromotionalevent.after.validation', array($this, $validator));

            $deletepromotionalevent = PromotionalEvent::excludeDeleted()->where('promotionalevent_id', $promotionalevent_id)->first();
            $deletepromotionalevent->status = 'deleted';
            $deletepromotionalevent->modified_by = $this->api->user->user_id;

            Event::fire('orbit.promotionalevent.postdeletepromotionalevent.before.save', array($this, $deletepromotionalevent));

            // hard delete promotionalevent-merchant.
            $deletepromotionaleventretailers = PromotionalEventMerchant::where('promotionalevent_id', $deletepromotionalevent->promotionalevent_id)->get();
            foreach ($deletepromotionaleventretailers as $deletepromotionaleventretailer) {
                $deletepromotionaleventretailer->delete();
            }

            // hard delete campaign gender
            $deleteCampaignGenders = CampaignGender::where('campaign_id', $deletepromotionalevent->promotionalevent_id)->get();
            foreach ($deleteCampaignGenders as $deletepromotionaleventretailer) {
                $deletepromotionaleventretailer->delete();
            }

            // hard delete campaign age
            $deleteCampaignAges = CampaignAge::where('campaign_id', $deletepromotionalevent->promotionalevent_id)->get();
            foreach ($deleteCampaignAges as $deletepromotionaleventretailer) {
                $deletepromotionaleventretailer->delete();
            }

            foreach ($deletepromotionalevent->translations as $translation) {
                $translation->modified_by = $this->api->user->user_id;
                $translation->delete();
            }

            $deletepromotionalevent->save();

            Event::fire('orbit.promotionalevent.postdeletepromotionalevent.after.save', array($this, $deletepromotionalevent));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.promotionalevent');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('PromotionalEvent Deleted: %s', $deletepromotionalevent->promotionalevent_name);
            $activity->setUser($user)
                    ->setActivityName('delete_promotionalevent')
                    ->setActivityNameLong('Delete PromotionalEvent OK')
                    ->setObject($deletepromotionalevent)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.promotionalevent.postdeletepromotionalevent.after.commit', array($this, $deletepromotionalevent));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.promotionalevent.postdeletepromotionalevent.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_promotionalevent')
                    ->setActivityNameLong('Delete PromotionalEvent Failed')
                    ->setObject($deletepromotionalevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promotionalevent.postdeletepromotionalevent.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_promotionalevent')
                    ->setActivityNameLong('Delete PromotionalEvent Failed')
                    ->setObject($deletepromotionalevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.promotionalevent.postdeletepromotionalevent.query.error', array($this, $e));

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
                    ->setActivityName('delete_promotionalevent')
                    ->setActivityNameLong('Delete PromotionalEvent Failed')
                    ->setObject($deletepromotionalevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.promotionalevent.postdeletepromotionalevent.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_promotionalevent')
                    ->setActivityNameLong('Delete PromotionalEvent Failed')
                    ->setObject($deletepromotionalevent)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);

        // Save the activity
        $activity->save();

        return $output;
    }

    /**
     * GET - Search PromotionalEvent
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                  (optional) - Valid value: tenants.
     * @param string   `sortby`                (optional) - column order by
     * @param string   `sortmode`              (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `promotionalevent_id`               (optional) - PromotionalEvent ID
     * @param integer  `mall_id`               (optional) - Mall ID
     * @param string   `promotionalevent_name`             (optional) - PromotionalEvent name
     * @param string   `promotionalevent_name_like`        (optional) - PromotionalEvent name like
     * @param string   `object_type`           (optional) - Object type. Valid value: promotion, promotionalevent.
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
    public function getSearchPromotionalEvent()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.promotionalevent.getsearchpromotionalevent.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.promotionalevent.getsearchpromotionalevent.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promotionalevent.getsearchpromotionalevent.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_promotionalevent')) {
                Event::fire('orbit.promotionalevent.getsearchpromotionalevent.authz.notallowed', array($this, $user));
                $viewPromotionalEventLang = Lang::get('validation.orbit.actionlist.view_promotionalevent');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewPromotionalEventLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->promotionalEventViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.promotionalevent.getsearchpromotionalevent.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,promotional_event_name,object_type,total_location,description,begin_date,end_date,updated_at,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.promotional_event_sortby'),
                )
            );

            Event::fire('orbit.promotionalevent.getsearchpromotionalevent.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.promotionalevent.getsearchpromotionalevent.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.promotionalevent.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.promotionalevent.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $object_type = OrbitInput::get('object_type');

            $filterName = OrbitInput::get('promotional_event_name_like', '');

            // Builder object
            $prefix = DB::getTablePrefix();
            $promotionalevent = News::allowedForPMPUser($user, $object_type[0])
                        ->select('news.*', 'news.news_id as campaign_id', 'news.object_type as campaign_type', 'campaign_status.order', 'campaign_price.campaign_price_id', 'news_translations.news_name as display_name', DB::raw('media.path as image_path'),
                            DB::raw("COUNT(DISTINCT {$prefix}news_merchant.news_merchant_id) as total_location"),
                            DB::raw("(select GROUP_CONCAT(IF({$prefix}merchants.object_type = 'tenant', CONCAT({$prefix}merchants.name,' at ', pm.name), CONCAT('Mall at ',{$prefix}merchants.name) ) separator ', ')
                                from {$prefix}news_merchant
                                    left join {$prefix}merchants on {$prefix}merchants.merchant_id = {$prefix}news_merchant.merchant_id
                                    left join {$prefix}merchants pm on {$prefix}merchants.parent_id = pm.merchant_id
                                    where {$prefix}news_merchant.news_id = {$prefix}news.news_id) as campaign_location_names"),
                            DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}news.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name) FROM {$prefix}merchants om
                                    LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                    WHERE om.merchant_id = {$prefix}news.mall_id)
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END  AS campaign_status"),
                            DB::raw("CASE WHEN {$prefix}campaign_price.base_price is null THEN 0 ELSE {$prefix}campaign_price.base_price END AS base_price, ((CASE WHEN {$prefix}campaign_price.base_price is null THEN 0 ELSE {$prefix}campaign_price.base_price END) * (DATEDIFF({$prefix}news.end_date, {$prefix}news.begin_date) + 1) * (SELECT COUNT(nm.news_merchant_id) FROM {$prefix}news_merchant as nm WHERE nm.object_type != 'mall' and nm.news_id = {$prefix}news.news_id)) AS estimated"))
                        ->leftJoin('campaign_price', function ($join) use ($object_type) {
                                $join->on('news.news_id', '=', 'campaign_price.campaign_id')
                                     ->where('campaign_price.campaign_type', '=', $object_type);
                          })
                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                        ->leftJoin('languages', 'languages.language_id', '=', 'news_translations.merchant_language_id')
                        ->leftJoin(DB::raw("( SELECT * FROM {$prefix}media WHERE media_name_long = 'news_translation_image_resized_default' ) as media"), DB::raw('media.object_id'), '=', 'news_translations.news_translation_id')
                        ->excludeDeleted('news')
                        ->where('is_having_reward', 'Y')
                        ->groupBy('news.news_id');

            if ($filterName === '') {
                $promotionalevent->where('languages.name', '=', DB::raw("(SELECT IF({$prefix}merchants.object_type = 'tenant', pm.mobile_default_language, {$prefix}merchants.mobile_default_language)
                                FROM {$prefix}merchants
                                LEFT JOIN {$prefix}merchants pm ON pm.merchant_id = {$prefix}merchants.parent_id
                                WHERE {$prefix}merchants.merchant_id = (SELECT nm.merchant_id FROM {$prefix}news_merchant nm WHERE nm.news_id = {$prefix}news.news_id LIMIT 1))"));
            }

            // Filter promotionalevent by Ids
            OrbitInput::get('news_id', function($promotionaleventIds) use ($promotionalevent)
            {
                $promotionalevent->whereIn('news.news_id', (array)$promotionaleventIds);
            });


            // to do : enable filter for mall
            // Filter promotionalevent by mall Ids
            // OrbitInput::get('mall_id', function ($mallIds) use ($promotionalevent)
            // {
            //     $promotionalevent->whereIn('promotionalevent.mall_id', (array)$mallIds);
            // });

            // Filter promotionalevent by mall Ids / dupes, same as above
            // OrbitInput::get('merchant_id', function ($mallIds) use ($promotionalevent)
            // {
            //     $promotionalevent->whereIn('promotionalevent.mall_id', (array)$mallIds);
            // });

            // Filter promotionalevent by promotionalevent name
            OrbitInput::get('news_name', function($promotionaleventname) use ($promotionalevent)
            {
                $promotionalevent->where('news.news_name', '=', $promotionaleventname);
            });

            // Filter promotionalevent by matching promotionalevent name pattern
            OrbitInput::get('news_name_like', function($promotionaleventname) use ($promotionalevent)
            {
                $promotionalevent->where('news_translations.news_name', 'like', "%$newsname%");
            });

            // Filter promotionalevent by object type
            OrbitInput::get('object_type', function($objectTypes) use ($promotionalevent)
            {
                $promotionalevent->whereIn('news.object_type', $objectTypes);
            });

            // Filter promotionalevent by description
            OrbitInput::get('description', function($description) use ($promotionalevent)
            {
                $promotionalevent->whereIn('news.description', $description);
            });

            // Filter promotionalevent by matching description pattern
            OrbitInput::get('description_like', function($description) use ($promotionalevent)
            {
                $promotionalevent->where('news.description', 'like', "%$description%");
            });

            // Filter promotionalevent by date
            OrbitInput::get('end_date', function($enddate) use ($promotionalevent)
            {
                $promotionalevent->where('news.begin_date', '<=', $enddate);
            });

            // Filter promotionalevent by date
            OrbitInput::get('begin_date', function($begindate) use ($promotionalevent)
            {
                $promotionalevent->where('news.end_date', '>=', $begindate);
            });

            // Filter promotionalevent by sticky order
            OrbitInput::get('sticky_order', function ($stickyorder) use ($promotionalevent) {
                $promotionalevent->whereIn('news.sticky_order', $stickyorder);
            });

            // Filter promotionalevent by status
            OrbitInput::get('campaign_status', function ($statuses) use ($promotionalevent, $prefix) {
                $promotionalevent->whereIn(DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}news.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                                    FROM {$prefix}merchants om
                                                                                    LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                                    WHERE om.merchant_id = {$prefix}news.mall_id)
                    THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END"), $statuses);
            });

            // Filter promotionalevent by link object type
            OrbitInput::get('link_object_type', function ($linkObjectTypes) use ($promotionalevent) {
                $promotionalevent->whereIn('news.link_object_type', $linkObjectTypes);
            });

            // Filter promotionalevent merchants by retailer(tenant) id
            OrbitInput::get('retailer_id', function ($retailerIds) use ($promotionalevent) {
                $promotionalevent->whereHas('tenants', function($q) use ($retailerIds) {
                    $q->whereIn('merchant_id', $retailerIds);
                });
            });

            // Filter promotionalevent merchants by retailer(tenant) name
            OrbitInput::get('tenant_name_like', function ($tenant_name_like) use ($promotionalevent) {
                $promotionalevent->whereHas('tenants', function($q) use ($tenant_name_like) {
                    $q->where('merchants.name', 'like', "%$tenant_name_like%");
                });
            });

            // Filter promotionalevent merchants by mall name
            // There is laravel bug regarding nested whereHas on the same table like in this case
            // promotionalevent->tenant->mall : whereHas('tenant', function($q) { $q->whereHas('mall' ...)}) this is not gonna work
            OrbitInput::get('mall_name_like', function ($mall_name_like) use ($promotionalevent, $prefix, $user) {
                $user_id = $user->user_id;
                $quote = function($arg)
                {
                    return DB::connection()->getPdo()->quote($arg);
                };
                $mall_name_like = "%" . $mall_name_like . "%";
                $mall_name_like = $quote($mall_name_like);
                $promotionalevent->whereRaw(DB::raw("
                    ((news
                        (select count(mtenant.merchant_id) from {$prefix}merchants mtenant
                            inner join {$prefix}news_merchant onm on mtenant.merchant_id = onm.merchant_id
                            where mtenant.object_type = 'tenant'
                            and onm.news_id = {$prefix}news.news_id
                            and (
                                select count(mmall.merchant_id) from {$prefix}merchants mmall
                                where mmall.object_type = 'mall' and
                                mtenant.parent_id = mmall.merchant_id and
                                mmall.name like {$mall_name_like} and
                                mmall.object_type = 'mall'
                            ) >= 1 and
                            mtenant.object_type = 'tenant' and
                            mtenant.is_mall = 'no' and
                            onm.object_type = 'retailer'
                        ) >= 1
                    )
                    OR
                    (
                        (select count(mmall.merchant_id) from {$prefix}merchants mmall
                            inner join {$prefix}news_merchant onm on mmall.merchant_id = onm.merchant_id
                            inner join {$prefix}user_campaign ucp on ucp.campaign_id = onm.news_id
                            where mmall.object_type = 'mall' and
                            ucp.user_id = '{$user_id}' and
                            mmall.name like {$mall_name_like} and
                            onm.object_type = 'mall'
                        ) >= 1
                    ))
                "));
            });

            // Filter promotionaleventnews by estimated total cost
            OrbitInput::get('etc_from', function ($etcfrom) use ($promotionalevent) {
                $etcto = OrbitInput::get('etc_to');
                if (empty($etcto)) {
                    $promotionalevent->havingRaw('estimated >= ' . floatval(str_replace(',', '', $etcfrom)));
                }
            });

            // Filter promotionalevent by estimated total cost
            OrbitInput::get('etc_to', function ($etcto) use ($promotionalevent) {
                $etcfrom = OrbitInput::get('etc_from');
                if (empty($etcfrom)) {
                    $etcfrom = 0;
                }
                $promotionalevent->havingRaw('estimated between ' . floatval(str_replace(',', '', $etcfrom)) . ' and '. floatval(str_replace(',', '', $etcto)));
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($promotionalevent, $object_type) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'tenants') {
                        $promotionalevent->with('tenants');
                    } elseif ($relation === 'tenants.mall') {
                        $promotionalevent->with('tenants.mall');
                    } elseif ($relation === 'campaignLocations') {
                        $promotionalevent->with('campaignLocations');
                    } elseif ($relation === 'campaignLocations.mall') {
                        $promotionalevent->with('campaignLocations.mall');
                    } elseif ($relation === 'translations') {
                        $promotionalevent->with('translations');
                    } elseif ($relation === 'translations.media') {
                        $promotionalevent->with('translations.media');
                    } elseif ($relation === 'genders') {
                        $promotionalevent->with('genders');
                    } elseif ($relation === 'ages') {
                        $promotionalevent->with('ages');
                    } elseif ($relation === 'keywords') {
                        $promotionalevent->with('keywords');
                    } elseif ($relation === 'campaignObjectPartners') {
                        $promotionalevent->with('campaignObjectPartners');
                    } elseif ($relation === 'rewardDetail') {
                        $promotionalevent->with(['rewardDetail' => function ($q) use ($object_type) {
                            $q->where('reward_details.object_type', '=', $object_type);
                        }]);
                    } elseif ($relation === 'rewardTranslations') {
                        $promotionalevent->with('rewardDetail.rewardTranslations');
                    } elseif ($relation === 'rewardCodes') {
                        $promotionalevent->with('rewardDetail.rewardCodes');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_promotionalevent = clone $promotionalevent;

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
                $promotionalevent->take($take);

                $skip = 0;
                OrbitInput::get('skip', function($_skip) use (&$skip, $promotionalevent)
                {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                });
                $promotionalevent->skip($skip);
            }

            // Default sort by
            $sortBy = 'campaign_status';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'        => 'news.created_at',
                    'promotional_event_name' => 'news_translations.news_name',
                    'object_type'            => 'news.object_type',
                    'total_location'         => 'total_location',
                    'description'            => 'news.description',
                    'begin_date'             => 'news.begin_date',
                    'end_date'               => 'news.end_date',
                    'updated_at'             => 'news.updated_at',
                    'status'                 => 'campaign_status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $promotionalevent->orderBy($sortBy, $sortMode);

            //with name
            if ($sortBy !== 'newsnews_translations.news_name') {
                $promotionalevent->orderBy('news_translations.news_name', 'asc');
            }

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return ['builder' => $promotionalevent, 'count' => RecordCounter::create($_promotionalevent)->count()];
            }

            $totalPromotionalEvent = RecordCounter::create($_promotionalevent)->count();
            $listOfPromotionalEvent = $promotionalevent->get();

            $data = new stdclass();
            $data->total_records = $totalPromotionalEvent;
            $data->returned_records = count($listOfPromotionalEvent);
            $data->records = $listOfPromotionalEvent;

            if ($totalPromotionalEvent === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.news');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.promotionalevent.getsearchpromotionalevent.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promotionalevent.getsearchpromotionalevent.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.promotionalevent.getsearchpromotionalevent.query.error', array($this, $e));

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
            Event::fire('orbit.promotionalevent.getsearchpromotionalevent.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.promotionalevent.getsearchpromotionalevent.before.render', array($this, &$output));

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
    public function getSearchPromotionalEventPromotionByRetailer()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.promotion.getsearchpromotionaleventpromotionbyretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.promotion.getsearchpromotionaleventpromotionbyretailer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promotion.getsearchpromotionaleventpromotionbyretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_promotion')) {
                Event::fire('orbit.promotion.getsearchpromotionaleventpromotionbyretailer.authz.notallowed', array($this, $user));
                $viewPromotionLang = Lang::get('validation.orbit.actionlist.view_promotion');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewPromotionLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.promotion.getsearchpromotionaleventpromotionbyretailer.after.authz', array($this, $user));

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

            Event::fire('orbit.promotion.getsearchpromotionaleventpromotionbyretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.promotion.getsearchpromotionaleventpromotionbyretailer.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int)Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            $prefix = DB::getTablePrefix();
            $nowUTC = Carbon::now();
            // Builder object
            $promotions = PromotionalEvent::join('merchants', 'promotionalevent.mall_id', '=', 'merchants.merchant_id')
                              ->join('timezones', 'merchants.timezone_id', '=', 'timezones.timezone_id')
                              // ->join('promotionalevent_merchant', 'promotionalevent.promotionalevent_id', '=', 'promotionalevent_merchant.promotionalevent_id')
                              ->select('merchants.name AS retailer_name', 'promotionalevent.*', 'promotionalevent.promotionalevent_name as promotion_name', 'timezones.timezone_name')
                              // ->where('promotionalevent.object_type', '=', 'promotion')
                              // ->where('promotionalevent.status', '!=', 'deleted');
                              ->where('promotionalevent.status', '=', 'active');


            if (empty(OrbitInput::get('begin_date')) && empty(OrbitInput::get('end_date'))) {
                $promotions->where('begin_date', '<=', DB::raw("CONVERT_TZ('{$nowUTC}','UTC',{$prefix}timezones.timezone_name)"))
                           ->where('end_date', '>=', DB::raw("CONVERT_TZ('{$nowUTC}','UTC',{$prefix}timezones.timezone_name)"));
            }

            // Filter promotion by Ids
            OrbitInput::get('promotionalevent_id', function($promotionIds) use ($promotions)
            {
                $promotions->whereIn('promotionalevent.promotionalevent_id', $promotionIds);
            });

            // Filter promotion by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($promotions) {
                $promotions->whereIn('promotionalevent.mall_id', $merchantIds);
            });

            // Filter promotion by promotion name
            OrbitInput::get('promotionalevent_name', function($promotionaleventname) use ($promotions)
            {
                $promotions->whereIn('promotionalevent.promotionalevent_name', $promotionaleventname);
            });

            // Filter promotion by matching promotion name pattern
            OrbitInput::get('promotionalevent_name_like', function($promotionaleventname) use ($promotions)
            {
                $promotions->where('promotionalevent.promotionalevent_name', 'like', "%$promotionaleventname%");
            });

            // Filter promotion by promotion type
            OrbitInput::get('object_type', function($objectTypes) use ($promotions)
            {
                $promotions->whereIn('promotionalevent.object_type', $objectTypes);
            });

            // Filter promotion by description
            OrbitInput::get('description', function($description) use ($promotions)
            {
                $promotions->whereIn('promotionalevent.description', $description);
            });

            // Filter promotion by matching description pattern
            OrbitInput::get('description_like', function($description) use ($promotions)
            {
                $promotions->where('promotionalevent.description', 'like', "%$description%");
            });

            // Filter promotion by begin date
            OrbitInput::get('begin_date', function($begindate) use ($promotions)
            {
                $promotions->where('promotionalevent.begin_date', '<=', $begindate);
            });

            // Filter promotion by end date
            OrbitInput::get('end_date', function($enddate) use ($promotions)
            {
                $promotions->where('promotionalevent.end_date', '>=', $enddate);
            });

            // Filter promotion by status
            OrbitInput::get('status', function ($statuses) use ($promotions) {
                $promotions->whereIn('promotionalevent.status', $statuses);
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
            $sortBy = 'promotionalevent.created_at';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'retailer_name'     => 'retailer_name',
                    'registered_date'   => 'promotionalevent.created_at',
                    'promotion_name'    => 'promotionalevent.promotionalevent_name',
                    'object_type'       => 'promotionalevent.obect_type',
                    'description'       => 'promotionalevent.description',
                    'begin_date'        => 'promotionalevent.begin_date',
                    'end_date'          => 'promotionalevent.end_date',
                    'updated_at'        => 'promotionalevent.updated_at',
                    'status'            => 'promotionalevent.status'
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
            Event::fire('orbit.promotion.getsearchpromotionaleventpromotionbyretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promotion.getsearchpromotionaleventpromotionbyretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.promotion.getsearchpromotionaleventpromotionbyretailer.query.error', array($this, $e));

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
            Event::fire('orbit.promotion.getsearchpromotionaleventpromotionbyretailer.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.promotion.getsearchpromotionaleventpromotionbyretailer.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of id_language_default
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $promotionalevent = Language::where('language_id', '=', $value)
                                    ->first();

            if (empty($promotionalevent)) {
                return false;
            }

            App::instance('orbit.empty.language_default', $promotionalevent);

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
        Validator::extend('orbit.empty.promotional_event', function ($attribute, $value, $parameters) {
            $promotionalevent = News::excludeStoppedOrExpired('news')
                        ->where('news_id', $value)
                        ->where('is_having_reward', 'Y')
                        ->first();

            if (empty($promotionalevent)) {
                return false;
            }

            App::instance('orbit.empty.promotional_event', $promotionalevent);

            return true;
        });

        // Check the existance of news id for update with permission check
        Validator::extend('orbit.update.promotional_event', function ($attribute, $value, $parameters) {
            $user = $this->api->user;
            $object_type = $parameters[0];

            $promotionalevent = News::allowedForPMPUser($user, $object_type)->excludeStoppedOrExpired('news')
                        ->where('news_id', $value)
                        ->where('is_having_reward', 'Y')
                        ->first();

            if (empty($promotionalevent)) {
                return false;
            }

            App::instance('orbit.update.promotional_event', $promotionalevent);

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

        // Check the existence of the promotionalevent status
        Validator::extend('orbit.empty.promotional_event_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || true;
            }

            return $valid;
        });

        // Check the existence of the promotionalevent object type
        Validator::extend('orbit.empty.promotional_event_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $objectTypes = array('promotion', 'news');
            foreach ($objectTypes as $objectType) {
                if($value === $objectType) $valid = $valid || true;
            }

            return $valid;
        });

        // Check the existence of the promotional event reward type
        Validator::extend('orbit.empty.promotional_event_reward_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $reward_types = array('promotion', 'lucky_draw');
            foreach ($reward_types as $reward_type) {
                if($value === $reward_type) $valid = $valid || true;
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

        // check the partner exclusive or not if the is_exclusive is set to 'Y'
        Validator::extend('orbit.empty.exclusive_partner', function ($attribute, $value, $parameters) {
            $flag_exclusive = false;
            $is_exclusive = OrbitInput::post('is_exclusive');
            $partner_ids = (array) OrbitInput::post('partner_ids');

            $partner_exclusive = Partner::select('is_exclusive', 'status')
                           ->whereIn('partner_id', $partner_ids)
                           ->get();

            foreach ($partner_exclusive as $exclusive) {
                if ($exclusive->is_exclusive == 'Y' && $exclusive->status == 'active') {
                    $flag_exclusive = true;
                }
            }

            $valid = true;

            if ($is_exclusive == 'Y') {
                if ($flag_exclusive) {
                    $valid = true;
                } else {
                    $valid = false;
                }
            }

            return $valid;
        });
    }

    /**
     * @param NewsModel $promotional_event
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveTranslations($promotional_event, $translations_json_string, $scenario = 'create')
    {
        /*
         * JSON structure: object with keys = merchant_language_id and values = ProductTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where NewsTranslation object is object with keys:
         *   promotional_event_name, description
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

        // translate
        foreach ($data as $merchant_language_id => $translations) {
            $language = Language::where('language_id', '=', $merchant_language_id)
                ->first();
            if (empty($language)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
            }

            $existing_translation = NewsTranslation::excludeDeleted()
                                    ->where('news_id', '=', $promotional_event->news_id)
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
                    $operations[] = ['create', $merchant_language_id, $translations];
                } else {
                    $operations[] = ['update', $existing_translation, $translations];
                }
            }
        }

        foreach ($operations as $operation) {
            $op = $operation[0];

            // for translation per language
            if ($op === 'create') {
                // news translation
                $new_translation = new NewsTranslation();
                $new_translation->news_id = $promotional_event->news_id;
                $new_translation->merchant_id = $promotional_event->mall_id;
                $new_translation->merchant_language_id = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $new_translation->{$field} = $value;
                }
                $new_translation->created_by = $this->api->user->user_id;
                $new_translation->modified_by = $this->api->user->user_id;
                $new_translation->save();

                // Fire an promotional_event which listen on orbit.promotional_event.after.translation.save
                // @param ControllerAPI $this
                // @param PromotionalEventTranslation $new_translation
                $new_translation->object_type = $promotional_event->object_type;
                Event::fire('orbit.promotionalevent.after.translation.save', array($this, $new_translation));

                $promotional_event->setRelation('translation_'. $new_translation->merchant_language_id, $new_translation);
            }
            elseif ($op === 'update') {

                /** @var NewsTranslation $existing_translation */
                // update promotional translation
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->status = $promotional_event->status;
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->save();

                // Fire an promotional_event which listen on orbit.promotional_event.after.translation.save
                // @param ControllerAPI $this
                // @param PromotionalEventTranslation $existing_translation
                $existing_translation->object_type = $promotional_event->object_type;
                Event::fire('orbit.promotionalevent.after.translation.save', array($this, $existing_translation));

                // return respones if any upload image or no
                $existing_translation->load('media');

                $promotional_event->setRelation('translation_'. $existing_translation->merchant_language_id, $existing_translation);
            }
            elseif ($op === 'delete') {
                /** @var PromotionalEventTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->delete();
            }
        }

    }

    /**
     * @param RewardDetailModel $reward_detail
     * @param NewsModel $promotional_event
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveRewardTranslations($promotional_event, $reward_detail, $translations_json_string, $id_language_default, $scenario = 'create')
    {
        /*
         * JSON structure: object with keys = language_id and values = ProductTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where RewardDetailTranslation object is object with keys:
         *   guest_button_label, logged_in_button_label, after_participation_content, email_content
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['guest_button_label', 'logged_in_button_label', 'after_participation_content', 'email_content'];
        $user = $this->api->user;
        $operations = [];

        $data = @json_decode($translations_json_string);
        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'reward translations']));
        }

        if (! array_key_exists($id_language_default, $data)) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.language_default', ['field' => 'id language default']));
        }

        // translate
        foreach ($data as $language_id => $translations) {
            $language = Language::where('language_id', '=', $language_id)
                            ->first();
            if (empty($language)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
            }

            $existing_translation = RewardDetailTranslation::excludeDeleted()
                                        ->where('reward_detail_id', '=', $reward_detail->reward_detail_id)
                                        ->where('language_id', '=', $language_id)
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

                    // additional validation
                    $validator_value[$field] = $value;
                    // required for default language
                    if ($language_id === $id_language_default) {
                        $validation_label = 'required|max:10';
                    } else {
                        $validation_label = 'max:10';
                    }
                    // validation for label
                    if (in_array($field, ['guest_button_label', 'logged_in_button_label'])) {
                        $validator_validation[$field] = $validation_label;
                    }
                    $validator = Validator::make(
                        $validator_value,
                        $validator_validation
                    );
                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }
                }
                if (empty($existing_translation)) {
                    $operations[] = ['create', $language_id, $translations];
                } else {
                    $operations[] = ['update', $existing_translation, $translations];
                }
            }
        }

        foreach ($operations as $operation) {
            $op = $operation[0];

            // for translation per language
            if ($op === 'create') {
                // reward_detail_translaion
                $new_reward_detail_translation = new RewardDetailTranslation();
                $new_reward_detail_translation->reward_detail_id = $reward_detail->reward_detail_id;
                $new_reward_detail_translation->language_id = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $new_reward_detail_translation->{$field} = $value;
                }
                $new_reward_detail_translation->save();

                // Fire an promotional_event which listen on orbit.promotionalevent.after.rewardtranslation.save
                // @param ControllerAPI $this
                // @param RewardDetailTranslation $new_reward_detail_translation
                // Event::fire('orbit.promotionalevent.after.rewardtranslation.save', array($this, $new_reward_detail_translation));

                $promotional_event->setRelation('reward_detail_translation_'. $new_reward_detail_translation->language_id, $new_reward_detail_translation);
            }
            elseif ($op === 'update') {

                /** @var NewsTranslation $existing_translation */
                // update promotional translation
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->status = $promotional_event->status;
                $existing_translation->save();

                // Fire an promotional_event which listen on orbit.promotional_event.after.rewardtranslation.save
                // @param ControllerAPI $this
                // @param RewardDetailTranslation $existing_translation
                Event::fire('orbit.promotionalevent.after.rewardtranslation.save', array($this, $existing_translation));

                // return respones if any upload image or no
                $existing_translation->load('media');

                $promotional_event->setRelation('reward_detail_translation_'. $existing_translation->language_id, $existing_translation);

            }
            elseif ($op === 'delete') {
                /** @var PromotionalEventTranslation $existing_translation */
                $existing_translation = $operation[1];
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
