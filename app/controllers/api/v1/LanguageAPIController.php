<?php

use DominoPOS\OrbitACL\ACL;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

/**
 * Controller to handle listing master languages, merchant languages, and adding languages to merchants.
 *
 * Read only methods do not check ACL.
 */
class LanguageAPIController extends ControllerAPI
{
    /**
     * Returns global languages.
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchLanguage()
    {
        $httpCode = 200;
        try {
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user_login = $this->api->user;

            // @Todo: Use ACL authentication instead
            $user_role = $user_login->role;

            $role_name = ['Campaign Admin', 'Campaign Employee', 'Campaign Owner'];


            $prefix = DB::getTablePrefix();
            $languages = Language::select('languages.language_id', 'languages.name', 'languages.name_native', 'languages.name_long', 'languages.language_order', 'languages.created_at', 'languages.updated_at', 'languages.status')
                                ->leftJoin('object_supported_language', 'object_supported_language.language_id', '=', 'languages.language_id')
                                ->where('object_type', 'pmp_account')
                                ->orderBy('language_order', 'DESC')
                                ->distinct();

            if (in_array($user_role->role_name, $role_name)) {
                $campaign_account = $user_login->campaignAccount()->first();

                if ($campaign_account->is_link_to_all !== 'Y'){
                    $languages->whereRaw("
                                EXISTS (
                                    SELECT 1
                                    FROM {$prefix}campaign_account ca
                                    JOIN {$prefix}campaign_account cap
                                        ON cap.user_id = ca.parent_user_id
                                    WHERE (ca.user_id = {$this->quote($user_login->user_id)} or ca.parent_user_id = {$this->quote($user_login->user_id)})
                                        AND {$prefix}object_supported_language.object_id = cap.campaign_account_id
                                    GROUP BY cap.campaign_account_id
                                )
                                AND {$prefix}object_supported_language.status = 'active'
                            ");
                }
            }

            OrbitInput::get('status', function($status) use ($languages) {
                $languages->where('languages.status', '=', $status);
            });

            $_languages = clone $languages;

            $listlanguages = $languages->get();
            $count = RecordCounter::create($_languages)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listlanguages);
            $this->response->data->records = $listlanguages;
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

    /**
     * Returns languages for a merchant.
     *
     * @return \Illuminate\Support\Facades\Response
     */
    public function getSearchMerchantLanguage()
    {
        $httpCode = 200;
        try {

            $this->checkAuth();

            $this->registerCustomValidation();
            $merchant_id = OrbitInput::get('merchant_id', null);
            $validator = Validator::make(['merchant_id' => $merchant_id],
                ['merchant_id' => 'required|orbit.empty.merchant.public'],
                ['orbit.empty.merchant.public' => Lang::get('validation.orbit.empty.merchant')]);
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);

            }
            $merchant_languages = MerchantLanguage::with('language')
                                                  ->where('merchant_languages.status', '!=', 'deleted')
                                                  ->where('merchant_id', '=', $merchant_id)
                                                  ->join('languages', 'languages.language_id', '=','merchant_languages.language_id')
                                                  ->orderBy('languages.name_long', 'ASC')
                                                  ->get();

            $count = count($merchant_languages);

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = $count;
            $this->response->data->records = $merchant_languages;
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

    /**
     * POST - Adds a supported language.
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer  `merchant_id`           (required) - Merchant ID
     * @param json     `language_statuses      (required)
     *
     * json format :
     * { language_id : {"status" : "active or inactive"}}
     * json example :
     * {"1" : {"status" : "active"},"2" : {"status" : "inactive"}}
     *
     *
     * @return \Illuminate\Http\Response
     */
    public function postUpdateSupportedLanguage()
    {

        $activity = Activity::portal()
            ->setActivityType('create');
        $user = NULL;
        $merchant = NULL;

        $httpCode = 200;

        try {

            Event::fire('orbit.language.postupdatesupportedlanguage.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.language.postupdatesupportedlanguage.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            if (! ACL::create($user)->isAllowed('update_mall')) {
                $updateMerchantLang = Lang::get('validation.orbit.actionlist.update_merchant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateMerchantLang));
                ACL::throwAccessForbidden($message);
            }

            $this->registerCustomValidation();
            $languages_status_json = OrbitInput::post('language_statuses');


            $validator = Validator::make(
                array(
                    'languages_status_json' => $languages_status_json
                ),
                array(
                    'languages_status_json' => 'required',
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $data = @json_decode($languages_status_json);
            if (json_last_error() != JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
            }

            // validation 
            foreach ($data as $key_language_id => $value) {
                $validator = Validator::make(
                    array(
                        'language_id' => $key_language_id,
                        'status'      => $value->status,
                    ),
                    array(
                        'language_id' => 'required|orbit.empty.language',
                        'status'      => 'required|orbit.empty.supported_language_status',
                    )
                );

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }


            Event::fire('orbit.language.postupdatesupportedlanguage.before.validation', array($this, $validator));


            // save all language
            foreach ($data as $key_language_id => $value) {

                $supported_language = Language::find($key_language_id);
                $supported_language->status = $value->status;

                Event::fire('orbit.language.postupdatesupportedlanguage.before.save', array($this, $supported_language));

                $supported_language->save();

                // deleted merchant language
                if ($value->status === 'inactive') {
                    foreach ($supported_language->merchantLanguage as $merchantLanguage) {
                        $merchantLanguage->status = "deleted";
                        $merchantLanguage->save();
                    }
                }else if($value->status === 'active'){
                    foreach ($supported_language->merchantLanguage as $merchantLanguage) {
                        $merchantLanguage->status = "active";
                        $merchantLanguage->save();
                    }
                }

                Event::fire('orbit.language.postupdatesupportedlanguage.after.save', array($this, $supported_language));

                //for return all updated date
                $data_update[$key_language_id] = $supported_language;
            }

            $this->response->data = $data_update;

            // Commit the changes
            $this->commit();

            $activityNotes = sprintf('Supported languages updated');
            $activity->setUser($user)
                ->setActivityName('update_supported_language')
                ->setActivityNameLong('Modif Supported Languages OK')
                ->setObject($supported_language)
                ->setNotes($activityNotes)
                ->responseOK();

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.language.postupdatemerchant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                ->setActivityName('add_merchant_language')
                ->setActivityNameLong('Add Merchant Language Failed')
                ->setNotes($e->getMessage())
                ->responseFailed();
        } catch
        (InvalidArgsException $e) {
            Event::fire('orbit.language.postupdatemerchant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                ->setActivityName('add_merchant_language')
                ->setActivityNameLong('Add Merchant Language Failed')
                ->setNotes($e->getMessage())
                ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.language.postupdatemerchant.query.error', array($this, $e));

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
                ->setActivityName('add_merchant_language')
                ->setActivityNameLong('Add Merchant Language Failed')
                ->setNotes($e->getMessage())
                ->responseFailed();
        } catch (Exception $e) {
            $httpCode = 500;
            Event::fire('orbit.language.postupdatemerchant.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                ->setActivityName('add_merchant_language')
                ->setActivityNameLong('Add Merchant Language Failed')
                ->setNotes($e->getMessage())
                ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);
    }


    private function registerCustomValidation()
    {
        Validator::extend('orbit.empty.merchant.public', function ($attribute, $value, $parameters) {
            $merchant = Mall::excludeDeleted()
                ->where('merchant_id', $value)
                ->first();

            if (empty($merchant)) {
                return false;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return true;
        });

        $user = $this->api->user;
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) use ($user) {
            $merchant = Mall::excludeDeleted()
                /* ->allowedForUser($user) */
                ->where('merchant_id', $value)
                /* ->where('is_mall', 'yes') */
                ->first();

            if (empty($merchant)) {
                return false;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return true;
        });

        Validator::extend('orbit.empty.language', function ($attribute, $value, $parameters) {
            $language = Language::where('language_id', $value)->first();
            if (empty($language)) {
                return false;
            }
            App::instance('orbit.empty.language', $language);
            return true;
        });

        Validator::extend('orbit.empty.merchant_language', function ($attribute, $value, $parameters) {
            $merchant_language = MerchantLanguage::excludeDeleted()
                ->where('merchant_language_id', $value)
                ->first();
            if (empty($merchant_language)) {
                return false;
            }
            App::instance('orbit.empty.merchant_language', $merchant_language);
            return true;
        });

        // Check the existence of the supported language
        Validator::extend('orbit.empty.supported_language_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
