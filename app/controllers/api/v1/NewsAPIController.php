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
    protected $newsViewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];
    protected $newsModifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];

    /**
     * POST - Create New News
     *
     * @author Tian <tian@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
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
            $status = OrbitInput::post('status');
            $description = OrbitInput::post('description');
            $begin_date = OrbitInput::post('begin_date');
            $end_date = OrbitInput::post('end_date');
            $sticky_order = OrbitInput::post('sticky_order');
            $link_object_type = OrbitInput::post('link_object_type');
            $id_language_default = OrbitInput::post('id_language_default');
            $is_all_gender = OrbitInput::post('is_all_gender');
            $is_all_age = OrbitInput::post('is_all_age_range');
            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;
            $gender_ids = OrbitInput::post('gender_ids');
            $gender_ids = (array) $gender_ids;
            $age_range_ids = OrbitInput::post('age_range_ids');
            $age_range_ids = (array) $age_range_ids;
            $is_popup = OrbitInput::post('is_popup');

            $validator = Validator::make(
                array(
                    'current_mall'        => $mall_id,
                    'news_name'           => $news_name,
                    'object_type'         => $object_type,
                    'status'              => $status,
                    'begin_date'          => $begin_date,
                    'end_date'            => $end_date,
                    'link_object_type'    => $link_object_type,
                    'id_language_default' => $id_language_default,
                    'is_all_gender'       => $is_all_gender,
                    'is_all_age'          => $is_all_age,
                ),
                array(
                    'current_mall'        => 'required|orbit.empty.mall',
                    'news_name'           => 'required|max:255|orbit.exists.news_name',
                    'object_type'         => 'orbit.empty.news_object_type',
                    'status'              => 'required|orbit.empty.news_status',
                    'link_object_type'    => 'orbit.empty.link_object_type',
                    'begin_date'          => 'required|date|orbit.empty.hour_format',
                    'end_date'            => 'required|date|orbit.empty.hour_format',
                    'id_language_default' => 'required|orbit.empty.language_default',
                    'is_all_gender'       => 'required|orbit.empty.is_all_gender',
                    'is_all_age'          => 'required|orbit.empty.is_all_age',
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

            foreach ($retailer_ids as $retailer_id_check) {
                $validator = Validator::make(
                    array(
                        'retailer_id'   => $retailer_id_check,
                    ),
                    array(
                        'retailer_id'   => 'orbit.empty.tenant',
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
            $newnews = new News();
            $newnews->mall_id = $mall_id;
            $newnews->news_name = $news_name;
            $newnews->object_type = $object_type;
            $newnews->status = $status;
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
            foreach ($retailer_ids as $retailer_id) {
                $newsretailer = new NewsMerchant();
                $newsretailer->merchant_id = $retailer_id;
                $newsretailer->news_id = $newnews->news_id;
                $newsretailer->object_type = 'retailer';
                $newsretailer->save();
                $newsretailers[] = $newsretailer;
            }
            $newnews->tenants = $newsretailers;

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

                foreach (Config::get('orbit.genders') as $key => $value) {
                    if ($key === $gender) {
                        $gender_name = $value;
                    }
                }
                $newsGender->gender_name = $gender_name;

                $newsGenders[] = $newsGender;
            }
            $newnews->gender = $newsGenders;

            Event::fire('orbit.news.postnewnews.after.save', array($this, $newnews));

            //save campaign price
            $campaignbaseprice = CampaignBasePrices::where('merchant_id', '=', $newnews->mall_id)
                                            ->where('campaign_type', '=', 'news')
                                            ->first();

            $baseprice = 0;
            if (! empty($campaignbaseprice->price)) {
                $baseprice = $campaignbaseprice->price;
            }

            $campaignprice = new CampaignPrice();
            $campaignprice->base_price = $baseprice;
            $campaignprice->campaign_type = 'news';
            $campaignprice->campaign_id = $newnews->news_id;
            $campaignprice->save();

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
            $this->response->data = null;

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
            $status = OrbitInput::post('status');
            $link_object_type = OrbitInput::post('link_object_type');
            $end_date = OrbitInput::post('end_date');
            $id_language_default = OrbitInput::post('id_language_default');
            $is_all_gender = OrbitInput::post('is_all_gender');
            $is_all_age = OrbitInput::post('is_all_age_range');

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
                    'news_id'             => 'required|orbit.empty.news',
                    'current_mall'        => 'orbit.empty.mall',
                    'news_name'           => 'sometimes|required|min:5|max:255|news_name_exists_but_me',
                    'object_type'         => 'orbit.empty.news_object_type',
                    'status'              => 'orbit.empty.news_status',
                    'link_object_type'    => 'orbit.empty.link_object_type',
                    'end_date'            => 'date||orbit.empty.hour_format',
                    'id_language_default' => 'required|orbit.empty.language_default',
                    'is_all_gender'       => 'required|orbit.empty.is_all_gender',
                    'is_all_age'          => 'required|orbit.empty.is_all_age',
                ),
                array(
                   'news_name_exists_but_me' => Lang::get('validation.orbit.exists.news_name'),
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

            $updatednews = News::with('tenants')->excludeDeleted()->where('news_id', $news_id)->first();

            $updatednews_default_language = NewsTranslation::excludeDeleted()->where('news_id', $news_id)->where('merchant_id', $mall_id)->where('merchant_language_id', $id_language_default)->first();


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

            OrbitInput::post('status', function($status) use ($updatednews) {
                $updatednews->status = $status;
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

            OrbitInput::post('status', function($status) use ($updatednews_default_language) {
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

            OrbitInput::post('is_all_gender', function($is_all_gender) use ($updatednews) {
                $updatednews->is_all_gender = $is_all_gender;
                if ($is_all_gender == 'Y') {
                    $deleted_campaign_genders = CampaignGender::where('campaign_id', $updatednews->news_id)->get(array('campaign_age_id'))->toArray();
                    $updatednews->gender()->detach($deleted_campaign_genders);
                    $updatednews->load('gender');
                }
            });

            OrbitInput::post('is_all_age', function($is_all_age) use ($updatednews) {
                $updatednews->is_all_age = $is_all_age;
                if ($is_all_age == 'Y') {
                    $deleted_campaign_ages = CampaignAge::where('campaign_id', $updatednews->news_id)->get(array('campaign_age_id'))->toArray();
                    $updatednews->age()->detach($deleted_campaign_ages);
                    $updatednews->load('age');
                }
            });

            OrbitInput::post('retailer_ids', function($retailer_ids) use ($updatednews) {
                // validate retailer_ids
                $retailer_ids = (array) $retailer_ids;
                foreach ($retailer_ids as $retailer_id_check) {
                    $validator = Validator::make(
                        array(
                            'merchant_id'   => $retailer_id_check,
                        ),
                        array(
                            'merchant_id'   => 'orbit.empty.tenant',
                        )
                    );

                    Event::fire('orbit.news.postupdatenews.before.retailervalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.news.postupdatenews.after.retailervalidation', array($this, $validator));
                }
                // sync new set of retailer ids
                $pivotData = array_fill(0, count($retailer_ids), ['object_type' => 'retailer']);
                $syncData = array_combine($retailer_ids, $pivotData);
                $updatednews->tenants()->sync($syncData);

                // reload tenants relation
                $updatednews->load('tenants');
            });

            OrbitInput::post('gender_ids', function($gender_ids) use ($updatednews) {
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
                // sync new set of retailer ids
                // $updatednews->genders()->sync($gender_ids);

                // reload genders relation
                $updatednews->load('genders');
            });

            OrbitInput::post('age_range_ids', function($age_range_ids) use ($updatednews) {
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
                // sync new set of retailer ids
                // $updatednews->ages()->sync($ages);

                // reload ages relation
                $updatednews->load('ages');
            });

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

            // Builder object
            $news = News::select('news.*', 'campaign_price.campaign_price_id', 'campaign_price.base_price')
                        ->leftJoin('campaign_price', function($join)
                            {
                                $join->on('news.news_id', '=', 'campaign_price.campaign_id')
                                     ->where('campaign_price.campaign_type', '=', 'news');
                            })
                        ->excludeDeleted('news');

            // Filter news by Ids
            OrbitInput::get('news_id', function($newsIds) use ($news)
            {
                $news->whereIn('news.news_id', $newsIds);
            });

            // Filter news by mall Ids
            OrbitInput::get('mall_id', function ($mallIds) use ($news) {
                $news->whereIn('news.mall_id', (array)$mallIds);
            });

            // Filter news by mall Ids / dupes, same as above
            OrbitInput::get('merchant_id', function ($mallIds) use ($news) {
                $news->whereIn('news.mall_id', (array)$mallIds);
            });

            // Filter news by news name
            OrbitInput::get('news_name', function($newsname) use ($news)
            {
                $news->whereIn('news.news_name', $newsname);
            });

            // Filter news by matching news name pattern
            OrbitInput::get('news_name_like', function($newsname) use ($news)
            {
                $news->where('news.news_name', 'like', "%$newsname%");
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

            // Filter news by begin date
            OrbitInput::get('begin_date', function($begindate) use ($news)
            {
                $news->where('news.begin_date', '<=', $begindate);
            });

            // Filter news by end date
            OrbitInput::get('end_date', function($enddate) use ($news)
            {
                $news->where('news.end_date', '>=', $enddate);
            });

            // Filter news by sticky order
            OrbitInput::get('sticky_order', function ($stickyorder) use ($news) {
                $news->whereIn('news.sticky_order', $stickyorder);
            });

            // Filter news by status
            OrbitInput::get('status', function ($statuses) use ($news) {
                $news->whereIn('news.status', $statuses);
            });

            // Filter news by link object type
            OrbitInput::get('link_object_type', function ($linkObjectTypes) use ($news) {
                $news->whereIn('news.link_object_type', $linkObjectTypes);
            });

            // Filter news merchants by retailer id
            OrbitInput::get('retailer_id', function ($retailerIds) use ($news) {
                $news->whereHas('tenants', function($q) use ($retailerIds) {
                    $q->whereIn('merchant_id', $retailerIds);
                });
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($news) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'tenants') {
                        $news->with('tenants');
                    } elseif ($relation === 'translations') {
                        $news->with('translations');
                    } elseif ($relation === 'translations.media') {
                        $news->with('translations.media');
                    } elseif ($relation === 'genders') {
                        $news->with('genders');
                    } elseif ($relation === 'ages') {
                        $news->with('ages');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_news = clone $news;

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

            // Default sort by
            $sortBy = 'news.news_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'news.created_at',
                    'news_name'         => 'news.news_name',
                    'object_type'       => 'news.object_type',
                    'description'       => 'news.description',
                    'begin_date'        => 'news.begin_date',
                    'end_date'          => 'news.end_date',
                    'updated_at'        => 'news.updated_at',
                    'status'            => 'news.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            if ($sortBy !== 'news.status') {
                $news->orderBy('news.status', 'asc');
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $news->orderBy($sortBy, $sortMode);

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
            $news = News::excludeDeleted()
                        ->where('news_id', $value)
                        ->first();

            if (empty($news)) {
                return false;
            }

            App::instance('orbit.empty.news', $news);

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
            $news = AgeRanges::excludeDeleted()
                        ->where('age_range_id', $value)
                        ->first();

            if (empty($news)) {
                return false;
            }

            App::instance('orbit.empty.age', $news);

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

}
