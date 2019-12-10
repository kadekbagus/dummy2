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
    protected $newsModifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];

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
     * @param string     `is_all_gender`         (optional) - Is all gender. Valid value: F, M. Y(mean is all gender is Yes)
     * @param string     `is_all_age`            (optional) - Is all retailer age group. Valid value: Y, N.
     * @param string     `age_range_ids`         (optional) - Age Range IDs
     * @param string     `translations`          (optional) - For Translations
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

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->newsModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.news.postnewnews.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $mall_id = OrbitInput::post('current_mall');
            $news_name = OrbitInput::post('news_name');
            $object_type = OrbitInput::post('object_type');
            $campaignStatus = OrbitInput::post('campaign_status');
            $description = OrbitInput::post('description');
            $begin_date = OrbitInput::post('begin_date');
            $end_date = OrbitInput::post('end_date');
            $link_object_type = OrbitInput::post('link_object_type');
            $id_language_default = OrbitInput::post('id_language_default');
            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;
            $keywords = OrbitInput::post('keywords');
            $keywords = (array) $keywords;
            $productTags = OrbitInput::post('product_tags');
            $productTags = (array) $productTags;
            $translations = OrbitInput::post('translations');
            $sticky_order = OrbitInput::post('sticky_order');
            $partner_ids = OrbitInput::post('partner_ids');
            $partner_ids = (array) $partner_ids;
            $is_exclusive = OrbitInput::post('is_exclusive', 'N');
            $is_sponsored = OrbitInput::post('is_sponsored', 'N');
            $sponsor_ids = OrbitInput::post('sponsor_ids');
            $gender = OrbitInput::post('gender', 'A');
            $isHotEvent = OrbitInput::post('is_hot_event', 'no');
            $hotEventLink = OrbitInput::post('hot_event_link');

            if (empty($campaignStatus)) {
                $campaignStatus = 'not started';
            }

            $status = 'inactive';
            if ($campaignStatus === 'ongoing') {
                $status = 'active';
            }

            $validator_value = [
                'news_name'           => $news_name,
                'object_type'         => $object_type,
                'status'              => $status,
                'begin_date'          => $begin_date,
                'end_date'            => $end_date,
                'link_object_type'    => $link_object_type,
                'id_language_default' => $id_language_default,
                'sticky_order'        => $sticky_order,
                'is_hot_event'        => $isHotEvent,
                'hot_event_link'      => $hotEventLink,
            ];
            $validator_validation = [
                'news_name'           => 'required|max:255',
                'object_type'         => 'required|orbit.empty.news_object_type',
                'status'              => 'required|orbit.empty.news_status',
                'link_object_type'    => 'orbit.empty.link_object_type',
                'begin_date'          => 'required|date|orbit.empty.hour_format',
                'end_date'            => 'required|date|orbit.empty.hour_format',
                'id_language_default' => 'required|orbit.empty.language_default',
                'sticky_order'        => 'in:0,1',
                'is_hot_event'        => 'sometimes|required|in:yes,no',
                'hot_event_link'      => 'required_if:is_hot_event,yes|max:500',
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

            Event::fire('orbit.news.postnewnews.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $sponsorIds = array();
            if ($is_sponsored === 'Y') {
                $sponsorIds = @json_decode($sponsor_ids);
                if (json_last_error() != JSON_ERROR_NONE) {
                    OrbitShopAPI::throwInvalidArgument('JSON sponsor is not valid');
                }
            }

            // A means all gender
            if ($gender === 'A') {
                $gender = 'Y';
            }

            Event::fire('orbit.news.postnewnews.after.validation', array($this, $validator));

            // Get data status like ongoing, stopped etc
            $idStatus = CampaignStatus::select('campaign_status_id','campaign_status_name')->where('campaign_status_name', $campaignStatus)->first();

            $newnews = new News();
            $newnews->mall_id = $mall_id;
            $newnews->news_name = $news_name;
            $newnews->description = $description;
            $newnews->object_type = $object_type;
            $newnews->status = $status;
            $newnews->campaign_status_id = $idStatus->campaign_status_id;
            $newnews->begin_date = $begin_date;
            $newnews->end_date = $end_date;
            $newnews->link_object_type = $link_object_type;
            $newnews->is_all_age = 'Y';
            $newnews->is_all_gender = $gender;
            $newnews->created_by = $this->api->user->user_id;
            $newnews->sticky_order = $sticky_order;
            $newnews->is_exclusive = $is_exclusive;
            $newnews->is_sponsored = $is_sponsored;
            $newnews->is_hot_event = $isHotEvent;

            if ($isHotEvent === 'yes' && ! empty($hotEventLink)) {
                $newnews->hot_event_link = $hotEventLink;
            }

            // Check for english content
            $dataTranslations = @json_decode($translations);
            if (json_last_error() != JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
            }

            // Event::fire('orbit.news.postnewnews.before.save', array($this, $newnews));

            $newnews->save();

            // Return campaign status name
            $newnews->campaign_status = $idStatus->campaign_status_name;

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
                } else {
                    $isMall = 'retailer';
                }

                $newsretailer = new NewsMerchant();
                $newsretailer->merchant_id = $tenant_id;
                $newsretailer->news_id = $newnews->news_id;
                $newsretailer->object_type = $isMall;
                $newsretailer->save();
                $newsretailers[] = $newsretailer;
            }
            $newnews->tenants = $newsretailers;

            // save ObjectPartner
            $objectPartners = array();
            foreach ($partner_ids as $partner_id) {
                $objectPartner = new ObjectPartner();
                $objectPartner->object_id = $newnews->news_id;
                $objectPartner->object_type = $object_type;
                $objectPartner->partner_id = $partner_id;
                $objectPartner->save();
                $objectPartners[] = $objectPartner;
            }
            $newnews->partners = $objectPartners;

            //save to user campaign
            $usercampaign = new UserCampaign();
            $usercampaign->user_id = $user->user_id;
            $usercampaign->campaign_id = $newnews->news_id;
            $usercampaign->campaign_type = 'news';
            $usercampaign->save();

            // save Keyword
            $newsKeywords = array();
            foreach ($keywords as $keyword) {
                $keyword_id = null;

                $existKeyword = Keyword::excludeDeleted()
                    ->where('keyword', '=', $keyword)
                    ->where('merchant_id', '=', 0)
                    ->first();

                if (empty($existKeyword)) {
                    $newKeyword = new Keyword();
                    $newKeyword->merchant_id = 0;
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

            // Save product tags
            $newsProductTags = array();
            foreach ($productTags as $productTag) {
                $product_tag_id = null;

                $existProductTag = ProductTag::excludeDeleted()
                    ->where('product_tag', '=', $productTag)
                    ->where('merchant_id', '=', 0)
                    ->first();

                if (empty($existProductTag)) {
                    $newProductTag = new ProductTag();
                    $newProductTag->merchant_id = 0;
                    $newProductTag->product_tag = $productTag;
                    $newProductTag->status = 'active';
                    $newProductTag->created_by = $this->api->user->user_id;
                    $newProductTag->modified_by = $this->api->user->user_id;
                    $newProductTag->save();

                    $product_tag_id = $newProductTag->product_tag_id;
                    $newsProductTags[] = $newProductTag;
                } else {
                    $product_tag_id = $existProductTag->product_tag_id;
                    $newsProductTags[] = $existProductTag;
                }

                $newProductTagObject = new ProductTagObject();
                $newProductTagObject->product_tag_id = $product_tag_id;
                $newProductTagObject->object_id = $newnews->news_id;
                $newProductTagObject->object_type = $object_type;
                $newProductTagObject->save();
            }
            $newnews->product_tags = $newsProductTags;

            Event::fire('orbit.news.postnewnews.after.save', array($this, $newnews));

            // translation for mallnews
            OrbitInput::post('translations', function($translation_json_string) use ($newnews, $mallid) {
                $this->validateAndSaveTranslations($newnews, $translation_json_string, 'create');
            });

            // Default language for pmp_account is required
            $malls = implode("','", $mallid);
            $prefix = DB::getTablePrefix();
            $isAvailable = NewsTranslation::where('news_id', '=', $newnews->news_id)
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

            if ($is_sponsored === 'Y' && (! empty($sponsorIds))) {
                $uniqueSponsor = array();
                foreach ($sponsorIds as $sponsorData) {
                    foreach ((array) $sponsorData as $key => $value) {
                        if (in_array($key, $uniqueSponsor)) {
                            $errorMessage = "Duplicate Sponsor (bank or e-wallet)";
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        $uniqueSponsor[] = $key;

                        //credit card must be filled
                        if ((count($value) == 0) || ($value === '')) {
                            $sponsorProvider = SponsorProvider::where('sponsor_provider_id', $key)->first();

                            if ($sponsorProvider->object_type === 'bank') {
                                $errorMessage = "Credit card is required";
                                OrbitShopAPI::throwInvalidArgument($errorMessage);
                            }
                        }

                        $objectSponsor = new ObjectSponsor();
                        $objectSponsor->sponsor_provider_id = $key;
                        $objectSponsor->object_id = $newnews->news_id;
                        $objectSponsor->object_type = $object_type;

                        $allCreditCard = 'N';
                        if ($value === 'all_credit_card') {
                            $allCreditCard = 'Y';
                        }
                        $objectSponsor->is_all_credit_card = $allCreditCard;
                        $objectSponsor->save();

                        if (($allCreditCard === 'N') && (count($value) > 0)) {
                            if (is_array($value)) {
                                foreach ($value as $creditCardId) {
                                    $objectSponsorCreditCard = new ObjectSponsorCreditCard();
                                    $objectSponsorCreditCard->object_sponsor_id = $objectSponsor->object_sponsor_id;
                                    $objectSponsorCreditCard->sponsor_credit_card_id = $creditCardId;
                                    $objectSponsorCreditCard->save();
                                }
                            }
                        }
                    }
                }
            }

            $this->response->data = $newnews;

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
            $translations = OrbitInput::post('translations');
            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;
            $partner_ids = OrbitInput::post('partner_ids');
            $partner_ids = (array) $partner_ids;
            $is_exclusive = OrbitInput::post('is_exclusive');
            $is_sponsored = OrbitInput::post('is_sponsored', 'N');
            $sponsor_ids = OrbitInput::post('sponsor_ids');
            $isHotEvent = OrbitInput::post('is_hot_event', 'no');
            $hotEventLink = OrbitInput::post('hot_event_link');

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
                'partner_exclusive'    => $is_exclusive,
                'is_hot_event'        => $isHotEvent,
                'hot_event_link'      => $hotEventLink,
            );

            // Validate news_name only if exists in POST.
            OrbitInput::post('news_name', function($news_name) use (&$data) {
                $data['news_name'] = $news_name;
            });

            $validator = Validator::make(
                $data,
                array(
                    'news_id'             => 'required|orbit.update.news:' . $object_type,
                    'news_name'           => 'sometimes|required|max:255',
                    'object_type'         => 'required|orbit.empty.news_object_type',
                    'status'              => 'orbit.empty.news_status',
                    'link_object_type'    => 'orbit.empty.link_object_type',
                    'end_date'            => 'date||orbit.empty.hour_format',
                    'id_language_default' => 'required|orbit.empty.language_default',
                    'partner_exclusive'   => 'in:Y,N|orbit.empty.exclusive_partner',
                    'is_hot_event'        => 'required|in:yes,no',
                    'hot_event_link'      => 'required_if:is_hot_event,yes|max:500',
                ),
                array(
                   'news_name_exists_but_me' => Lang::get('validation.orbit.exists.news_name'),
                   'orbit.update.news' => 'Cannot update campaign with status ' . $campaignStatus,
                   'orbit.empty.exclusive_partner'  => 'Partner is not exclusive / inactive',
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

            // Remove all key in Redis when campaign is stopped
            if ($status == 'inactive') {
                if (Config::get('orbit.cache.ng_redis_enabled', FALSE)) {
                    $type = $object_type == 'news' ? 'event' : 'promotion';
                    $redis = Cache::getRedis();
                    $keyName = array($type,'home');
                    foreach ($keyName as $value) {
                        $keys = $redis->keys("*$value*");
                        if (! empty($keys)) {
                            foreach ($keys as $key) {
                                $redis->del($key);
                            }
                        }
                    }
                }
            }

            $mallid = array();
            foreach ($retailer_ids as $retailer_id) {
                $data = @json_decode($retailer_id);
                $mall_id = $data->mall_id;

                if(! in_array($mall_id, $mallid)) {
                    $mallid[] = $mall_id;
                }
            }

            $prefix = DB::getTablePrefix();

            $updatednews = News::with('tenants')->excludeDeleted()->where('news_id', $news_id)->first();

            // this is for send email to marketing, before and after list
            $beforeUpdatedNews = News::selectRaw("{$prefix}news.*,
                                                        DATE_FORMAT({$prefix}news.end_date, '%d/%m/%Y %H:%i') as end_date")
                                    ->with('translations.language', 'translations.media', 'ages.ageRange', 'keywords', 'product_tags', 'campaign_status')
                                    ->excludeDeleted()
                                    ->where('news_id', $news_id)
                                    ->first();

            $statusdb = $updatednews->status;
            $enddatedb = $updatednews->end_date;
            //check get merchant for db
            $newsmerchantdb = NewsMerchant::select('merchant_id')->where('news_id', $news_id)->get()->toArray();
            $merchantdb = array();
            foreach($newsmerchantdb as $merchantdbid) {
                $merchantdb[] = $merchantdbid['merchant_id'];
            }

            // Check for english content
            $jsonTranslations = @json_decode($translations);
            if (json_last_error() != JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
            }

            // save News
            OrbitInput::post('mall_id', function($mall_id) use ($updatednews) {
                $updatednews->mall_id = $mall_id;
            });

            OrbitInput::post('object_type', function($object_type) use ($updatednews) {
                $updatednews->object_type = $object_type;
            });

            OrbitInput::post('news_name', function($news_name) use ($updatednews) {
                $updatednews->news_name = $news_name;
            });

            OrbitInput::post('description', function($description) use ($updatednews) {
                $updatednews->description = $description;
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

            OrbitInput::post('is_popup', function($is_popup) use ($updatednews) {
                $updatednews->is_popup = $is_popup;
            });

            OrbitInput::post('sticky_order', function($sticky_order) use ($updatednews) {
                $updatednews->sticky_order = $sticky_order;
            });

            OrbitInput::post('link_object_type', function($link_object_type) use ($updatednews) {
                if (trim($link_object_type) === '') {
                    $link_object_type = NULL;
                }
                $updatednews->link_object_type = $link_object_type;
            });

            OrbitInput::post('gender', function($gender) use ($updatednews) {
                if ($gender === 'A') {
                    $gender = 'Y';
                }

                $updatednews->is_all_gender = $gender;
            });

            OrbitInput::post('is_exclusive', function($is_exclusive) use ($updatednews) {
                $updatednews->is_exclusive = $is_exclusive;
            });

            OrbitInput::post('is_sponsored', function($is_sponsored) use ($updatednews, $news_id, $object_type) {
                $updatednews->is_sponsored = $is_sponsored;

                if ($is_sponsored === 'N') {
                    // delete before insert new
                    $objectSponsor = ObjectSponsor::where('object_id', $news_id)
                                                  ->where('object_type', $object_type);

                    $objectSponsorIds = $objectSponsor->lists('object_sponsor_id');

                    // delete ObjectSponsorCreditCard
                    if (! empty($objectSponsorIds)) {
                        $objectSponsorCreditCard = ObjectSponsorCreditCard::whereIn('object_sponsor_id', $objectSponsorIds)->delete();
                        $objectSponsor->delete();
                    }
                }
            });

            OrbitInput::post('sponsor_ids', function($sponsor_ids) use ($updatednews, $news_id, $object_type) {
                $sponsorIds = @json_decode($sponsor_ids);
                if (json_last_error() != JSON_ERROR_NONE) {
                    OrbitShopAPI::throwInvalidArgument('JSON sponsor is not valid');
                }

                // delete before insert new
                $objectSponsor = ObjectSponsor::where('object_id', $news_id)
                                              ->where('object_type', $object_type);

                $objectSponsorIds = $objectSponsor->lists('object_sponsor_id');

                // delete ObjectSponsorCreditCard
                if (! empty($objectSponsorIds)) {
                    $objectSponsorCreditCard = ObjectSponsorCreditCard::whereIn('object_sponsor_id', $objectSponsorIds)->delete();
                    $objectSponsor->delete();
                }

                $uniqueSponsor = array();
                foreach ($sponsorIds as $sponsorData) {
                    foreach ((array) $sponsorData as $key => $value) {
                        if (in_array($key, $uniqueSponsor)) {
                            $errorMessage = "Duplicate Sponsor (bank or e-wallet)";
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        $uniqueSponsor[] = $key;

                        //credit card must be filled
                        if ((count($value) == 0) || ($value === '')) {
                            $sponsorProvider = SponsorProvider::where('sponsor_provider_id', $key)->first();

                            if ($sponsorProvider->object_type === 'bank') {
                                $errorMessage = "Credit card is required";
                                OrbitShopAPI::throwInvalidArgument($errorMessage);
                            }
                        }

                        $objectSponsor = new ObjectSponsor();
                        $objectSponsor->sponsor_provider_id = $key;
                        $objectSponsor->object_id = $news_id;
                        $objectSponsor->object_type = $object_type;

                        $allCreditCard = 'N';
                        if ($value === 'all_credit_card') {
                            $allCreditCard = 'Y';
                        }
                        $objectSponsor->is_all_credit_card = $allCreditCard;
                        $objectSponsor->save();

                        if (($allCreditCard === 'N') && (count($value) > 0)) {
                            if (is_array($value)) {
                                foreach ($value as $creditCardId) {
                                    $objectSponsorCreditCard = new ObjectSponsorCreditCard();
                                    $objectSponsorCreditCard->object_sponsor_id = $objectSponsor->object_sponsor_id;
                                    $objectSponsorCreditCard->sponsor_credit_card_id = $creditCardId;
                                    $objectSponsorCreditCard->save();
                                }
                            }
                        }
                    }
                }
            });

            OrbitInput::post('translations', function($translation_json_string) use ($updatednews) {
                $this->validateAndSaveTranslations($updatednews, $translation_json_string, 'update');
            });

            // Default language for pmp_account is required
            $malls = implode("','", $mallid);
            $prefix = DB::getTablePrefix();
            $isAvailable = NewsTranslation::where('news_id', '=', $news_id)
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

            $updatednews->is_hot_event = $isHotEvent;
            $updatednews->hot_event_link = $isHotEvent === 'yes'
                ? $hotEventLink : null;

            $updatednews->modified_by = $this->api->user->user_id;
            $updatednews->touch();

            // save NewsMerchant
            OrbitInput::post('no_retailer', function($no_retailer) use ($updatednews) {
                if ($no_retailer == 'Y') {
                    $deleted_retailer_ids = NewsMerchant::where('news_id', $updatednews->news_id)->get(array('merchant_id'))->toArray();
                    $updatednews->tenants()->detach($deleted_retailer_ids);
                    $updatednews->load('tenants');
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

            OrbitInput::post('partner_ids', function($partner_ids) use ($updatednews, $news_id, $object_type) {
                // validate retailer_ids
                $partner_ids = (array) $partner_ids;

                // Delete old data
                $delete_object_partner = ObjectPartner::where('object_id', '=', $news_id);
                $delete_object_partner->delete();

                $objectPartners = array();
                // Insert new data
                if(array_filter($partner_ids)) {
                    foreach ($partner_ids as $partner_id) {
                        $objectPartner = new ObjectPartner();
                        $objectPartner->object_id = $news_id;
                        $objectPartner->object_type = $object_type;
                        $objectPartner->partner_id = $partner_id;
                        $objectPartner->save();
                        $objectPartners[] = $objectPartner;
                    }
                }
                $updatednews->partners = $objectPartners;
            });

            // Delete old data
            $deleted_keyword_object = KeywordObject::where('object_id', '=', $news_id)
                                                    ->where('object_type', '=', $object_type);
            $deleted_keyword_object->delete();

            OrbitInput::post('keywords', function($keywords) use ($updatednews,$user, $news_id, $object_type, $mallid) {
                // Insert new data
                $newsKeywords = array();
                foreach ($keywords as $keyword) {
                    $keyword_id = null;

                    $existKeyword = Keyword::excludeDeleted()
                        ->where('keyword', '=', $keyword)
                        ->where('merchant_id', '=', 0)
                        ->first();

                    if (empty($existKeyword)) {
                        $newKeyword = new Keyword();
                        $newKeyword->merchant_id = 0;
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

            // Update product tags
            $deleted_product_tags_object = ProductTagObject::where('object_id', '=', $news_id)
                                                    ->where('object_type', '=', $object_type);
            $deleted_product_tags_object->delete();

            OrbitInput::post('product_tags', function($productTags) use ($updatednews, $user, $news_id, $object_type, $mallid) {
                // Insert new data
                $newsProductTags = array();
                foreach ($productTags as $productTag) {
                    $product_tag_id = null;

                    $existProductTag = ProductTag::excludeDeleted()
                        ->where('product_tag', '=', $productTag)
                        ->where('merchant_id', '=', 0)
                        ->first();

                    if (empty($existProductTag)) {
                        $newProductTag = new ProductTag();
                        $newProductTag->merchant_id = 0;
                        $newProductTag->product_tag = $productTag;
                        $newProductTag->status = 'active';
                        $newProductTag->created_by = $user->user_id;
                        $newProductTag->modified_by = $user->user_id;
                        $newProductTag->save();

                        $product_tag_id = $newProductTag->product_tag_id;
                        $newsProductTags[] = $newProductTag;
                    } else {
                        $product_tag_id = $existProductTag->product_tag_id;
                        $newsProductTags[] = $existProductTag;
                    }

                    $newProductTagObject = new ProductTagObject();
                    $newProductTagObject->product_tag_id = $product_tag_id;
                    $newProductTagObject->object_id = $news_id;
                    $newProductTagObject->object_type = $object_type;
                    $newProductTagObject->save();
                }
                $updatednews->product_tags = $newsProductTags;
            });

            if (! empty($campaignStatus) || $campaignStatus !== '') {
                $promotionAdverts = Advert::excludeDeleted()
                                    ->where('link_object_id', $updatednews->news_id)
                                    ->update(['status'     => $updatednews->status]);
            }

            if (! empty($end_date) || $end_date !== '') {
                $promotionAdverts = Advert::excludeDeleted()
                                    ->where('link_object_id', $updatednews->news_id)
                                    ->where('end_date', '>', $updatednews->end_date)
                                    ->update(['end_date'   => $updatednews->end_date]);
            }

            Event::fire('orbit.news.postupdatenews.after.save', array($this, $updatednews));
            $this->response->data = $updatednews;
            // $this->response->data->translation_default = $updatednews_default_language;

            // Commit the changes
            $this->commit();


            // Push notification
            $queueName = Config::get('queue.connections.gtm_notification.queue', 'gtm_notification');

            Queue::push('Orbit\\Queue\\Notification\\NewsMallNotificationQueue', [
                'news_id' => $updatednews->news_id,
            ], $queueName);
            Queue::push('Orbit\\Queue\\Notification\\NewsStoreNotificationQueue', [
                'news_id' => $updatednews->news_id,
            ], $queueName);

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
            $this->response->data = [$e->getMessage(), $e->getFile(), $e->getLine()];

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

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->newsViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.news.getsearchnews.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $keywords = OrbitInput::get('keywords');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                    'keywords' => $keywords,
                ),
                array(
                    'sort_by' => 'in:registered_date,news_name,object_type,total_location,description,begin_date,end_date,updated_at,status',
                    'keywords' => 'min:3',
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

            $prefix = DB::getTablePrefix();

            // optimize orb_media query greatly when news_id is present
            $mediaJoin = "";
            $mediaOptimize = " AND (object_name = 'news_translation') ";
            $mediaObjectIds = (array) OrbitInput::get('news_id', []);
            if (! empty ($mediaObjectIds)) {
                $mediaObjectIds = "'" . implode("', '", $mediaObjectIds) . "'";
                $mediaJoin = " LEFT JOIN {$prefix}news_translations mont ON mont.news_translation_id = {$prefix}media.object_id ";
                $mediaOptimize = " AND object_name = 'news_translation' AND mont.news_id IN ({$mediaObjectIds}) ";
            }

            $filterName = OrbitInput::get('news_name_like', '');

            // Builder object
            $news = News::allowedForPMPUser($user, $object_type[0])
                        ->select('news.news_id',
                                 'news.news_name',
                                 'news.begin_date',
                                 'news.end_date',
                                 'news.updated_at',
                                 'news.news_id as campaign_id',
                                 'news.object_type as campaign_type',
                                 // 'campaign_status.order',
                                 'news_translations.news_name as display_name',
                            DB::raw("(SELECT {$prefix}media.path FROM {$prefix}media
                                    {$mediaJoin}
                                    WHERE media_name_long = 'news_translation_image_resized_default'
                                    {$mediaOptimize} AND
                                    {$prefix}media.object_id = {$prefix}news_translations.news_translation_id
                                    LIMIT 1) AS image_path"),
                            DB::raw("COUNT(DISTINCT {$prefix}news_merchant.news_merchant_id) as total_location"),
                            // DB::raw("(SELECT GROUP_CONCAT(IF({$prefix}merchants.object_type = 'tenant', CONCAT({$prefix}merchants.name,' at ', pm.name), CONCAT('Mall at ',{$prefix}merchants.name) ) separator ', ')
                            //     FROM {$prefix}news_merchant
                            //         LEFT JOIN {$prefix}merchants ON {$prefix}merchants.merchant_id = {$prefix}news_merchant.merchant_id
                            //         LEFT JOIN {$prefix}merchants pm ON {$prefix}merchants.parent_id = pm.merchant_id
                            //         where {$prefix}news_merchant.news_id = {$prefix}news.news_id) as campaign_location_names"),
                            DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}news.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name) FROM {$prefix}merchants om
                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = om.timezone_id
                                    WHERE om.merchant_id = {$prefix}news.mall_id)
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END  AS campaign_status"),
                            DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.order ELSE (CASE WHEN {$prefix}news.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name) FROM {$prefix}merchants om
                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = om.timezone_id
                                    WHERE om.merchant_id = {$prefix}news.mall_id)
                                THEN 5 ELSE {$prefix}campaign_status.order END) END  AS campaign_status_order")
                            // DB::raw("IF({$prefix}news.is_all_gender = 'Y', 'A', {$prefix}news.is_all_gender) as gender")
                        )
                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                        ->leftJoin('languages', 'languages.language_id', '=', 'news_translations.merchant_language_id')
                        ->excludeDeleted('news')
                        ->groupBy('news.news_id');

            if ($filterName === '') {
                // handle role campaign admin cause not join with campaign account
                if ($role->role_name === 'Campaign Admin' ) {
                    $news->where('languages.name', '=', DB::raw("(select ca.mobile_default_language from {$prefix}campaign_account ca where ca.user_id = {$this->quote($user->user_id)})"));
                } else {
                    $news->where('languages.name', '=', DB::raw("ca.mobile_default_language"));
                }
            }

            // Filter news by Ids
            OrbitInput::get('news_id', function($newsIds) use ($news)
            {
                $news->whereIn('news.news_id', (array)$newsIds);
            });

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

            // Filter news by keywords for advert link to
            OrbitInput::get('keywords', function($keywords) use ($news)
            {
                $news->where('news_translations.news_name', 'like', "$keywords%");
            });

            // Filter news by object type
            OrbitInput::get('object_type', function($objectTypes) use ($news)
            {
                $news->whereIn('news.object_type', $objectTypes);
            });

            // Filter news by is_having_reward
            OrbitInput::get('is_having_reward', function($havingReward) use ($news)
            {
                $news->where('news.is_having_reward', $havingReward);
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
            OrbitInput::get('mall_name_like', function ($mall_name_like) use ($news, $prefix, $user) {
                $user_id = $user->user_id;
                $quote = function($arg)
                {
                    return DB::connection()->getPdo()->quote($arg);
                };
                $mall_name_like = "%" . $mall_name_like . "%";
                $mall_name_like = $quote($mall_name_like);
                $news->whereRaw(DB::raw("
                    ((
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

            // Add new relation based on request
            // OrbitInput::get('with', function ($with) use ($news) {
            //     $with = (array) $with;

            //     foreach ($with as $relation) {
            //         if ($relation === 'tenants') {
            //             $news->with('tenants');
            //         } elseif ($relation === 'tenants.mall') {
            //             $news->with('tenants.mall');
            //         } elseif ($relation === 'campaignLocations') {
            //             $news->with('campaignLocations');
            //         } elseif ($relation === 'campaignLocations.mall') {
            //             $news->with('campaignLocations.mall');
            //         } elseif ($relation === 'translations') {
            //             $news->with('translations');
            //         } elseif ($relation === 'translations.media') {
            //             $news->with('translations.media');
            //         } elseif ($relation === 'ages') {
            //             $news->with('ages');
            //         } elseif ($relation === 'keywords') {
            //             $news->with('keywords');
            //         } elseif ($relation === 'product_tags') {
            //             $news->with('product_tags');
            //         } elseif ($relation === 'campaignObjectPartners') {
            //             $news->with('campaignObjectPartners');
            //         }
            //     }
            // });

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
                    'registered_date' => 'news.created_at',
                    'news_name'       => 'news_translations.news_name',
                    'object_type'     => 'news.object_type',
                    'total_location'  => 'total_location',
                    'description'     => 'news.description',
                    'begin_date'      => 'news.begin_date',
                    'end_date'        => 'news.end_date',
                    'updated_at'      => 'news.updated_at',
                    'status'          => 'campaign_status_order'
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
                if ($sortBy === 'campaign_status_order') {
                    $news->orderBy('news.updated_at', 'desc');
                }
                else {
                    $news->orderBy('news_translations.news_name', 'asc');
                }
            }

            //TODO:need to rethink about improving RecordCounter
            //RecordCounter will transform original query into
            //(SELECT COUNT(*) As aggregate FROM (original_query))
            //which make useless resource consumption as RDBMS must execute
            //original query (which often quite expensive and complex) just to count
            //how many row it returns

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

    public function getDetailNews()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.news.getdetailnews.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.news.getdetailnews.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.news.getdetailnews.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->newsViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.news.getdetailnews.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $news_id = OrbitInput::get('news_id');
            $object_type = OrbitInput::get('object_type');

            $validator = Validator::make(
                array(
                    'news_id' => $news_id,
                    'object_type' => $object_type,
                ),
                array(
                    'news_id' => 'required|orbit.exist.news',
                    'object_type' => 'required|in:news,promotion',
                ),
                array(
                    'orbit.exist.news' => 'news id not found',
                )
            );

            Event::fire('orbit.news.getdetailnews.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.news.getdetailnews.after.validation', array($this, $validator));

            $prefix = DB::getTablePrefix();

            // optimize orb_media query greatly when news_id is present
            $mediaJoin = "";
            $mediaOptimize = " AND (object_name = 'news_translation') ";
            $mediaObjectIds = (array) OrbitInput::get('news_id', []);

            $filterName = OrbitInput::get('news_name_like', '');

            $obj = (array) $object_type;

            // Builder object
            $news = News::allowedForPMPUser($user, $obj[0])
                        ->with(['tenants', 'tenants.mall', 'campaignLocations', 'campaignLocations.mall', 'translations', 'translations.media', 'ages', 'keywords', 'product_tags', 'campaignObjectPartners'])
                        ->select('news.*', 'news.news_id as campaign_id', 'news.object_type as campaign_type', 'campaign_status.order', 'news_translations.news_name as display_name',
                            DB::raw("(SELECT {$prefix}media.path FROM {$prefix}media
                                    {$mediaJoin}
                                    WHERE media_name_long = 'news_translation_image_resized_default'
                                    {$mediaOptimize} AND
                                    {$prefix}media.object_id = {$prefix}news_translations.news_translation_id
                                    LIMIT 1) AS image_path"),
                            DB::raw("COUNT(DISTINCT {$prefix}news_merchant.news_merchant_id) as total_location"),
                            DB::raw("(SELECT GROUP_CONCAT(IF({$prefix}merchants.object_type = 'tenant', CONCAT({$prefix}merchants.name,' at ', pm.name), CONCAT('Mall at ',{$prefix}merchants.name) ) separator ', ')
                                FROM {$prefix}news_merchant
                                    LEFT JOIN {$prefix}merchants ON {$prefix}merchants.merchant_id = {$prefix}news_merchant.merchant_id
                                    LEFT JOIN {$prefix}merchants pm ON {$prefix}merchants.parent_id = pm.merchant_id
                                    where {$prefix}news_merchant.news_id = {$prefix}news.news_id) as campaign_location_names"),
                            DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$prefix}news.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name) FROM {$prefix}merchants om
                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = om.timezone_id
                                    WHERE om.merchant_id = {$prefix}news.mall_id)
                                THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END  AS campaign_status"),
                            DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.order ELSE (CASE WHEN {$prefix}news.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name) FROM {$prefix}merchants om
                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = om.timezone_id
                                    WHERE om.merchant_id = {$prefix}news.mall_id)
                                THEN 5 ELSE {$prefix}campaign_status.order END) END  AS campaign_status_order"),
                            DB::raw("IF({$prefix}news.is_all_gender = 'Y', 'A', {$prefix}news.is_all_gender) as gender")
                        )
                        ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                        ->leftJoin('news_translations', 'news_translations.news_id', '=', 'news.news_id')
                        ->leftJoin('languages', 'languages.language_id', '=', 'news_translations.merchant_language_id')
                        ->where('news.news_id', '=', $news_id)
                        ->where('news.object_type', '=', $object_type)
                        ->excludeDeleted('news')
                        ->first();

            $this->response->data = $news;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.news.getdetailnews.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.news.getdetailnews.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.news.getdetailnews.query.error', array($this, $e));

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
            Event::fire('orbit.news.getdetailnews.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.news.getdetailnews.before.render', array($this, &$output));

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
            $news = Language::where('language_id', '=', $value)
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

        // Check the existance of news id
        Validator::extend('orbit.exist.news', function ($attribute, $value, $parameters) {
            $news = News::where('news_id', $value)
                        ->first();

            if (empty($news)) {
                return false;
            }

            App::instance('orbit.exist.news', $news);

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
            $partner_ids = OrbitInput::post('partner_ids');
            $partner_ids = (array) $partner_ids;

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
            $language = Language::where('language_id', '=', $merchant_language_id)
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
                    $operations[] = ['create', $merchant_language_id, $translations];
                } else {
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
                $new_translation->object_type = $news->object_type;
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
                $existing_translation->object_type = $news->object_type;
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
