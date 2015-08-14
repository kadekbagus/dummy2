<?php

use DominoPOS\OrbitACL\ACL;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

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
            $all_languages = Language::all();
            $count = count($all_languages);
            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = $count;
            $this->response->data->records = $all_languages;
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
            $merchant_languages = MerchantLanguage::excludeDeleted()->where('merchant_id', '=',
                $merchant_id)->with('language')->get();
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
     * Adds a language to a merchant and returns it. Does not create if already exists.
     *
     * Parameters: merchant_id, language_id (global language id)
     *
     * Possible errors: no such merchant, no such language
     *
     * @return \Illuminate\Http\Response
     */
    public function postAddMerchantLanguage()
    {
        $activity = Activity::portal()
            ->setActivityType('create');
        $user = NULL;
        $merchant = NULL;

        $httpCode = 200;

        try {

            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            if (! ACL::create($user)->isAllowed('update_merchant')) {
                $updateMerchantLang = Lang::get('validation.orbit.actionlist.update_merchant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateMerchantLang));
                ACL::throwAccessForbidden($message);
            }

            $this->registerCustomValidation();
            $merchant_id = OrbitInput::post('merchant_id');
            $language_id = OrbitInput::post('language_id');

            $validator = Validator::make(
                ['merchant_id' => $merchant_id, 'language_id' => $language_id],
                [
                    'merchant_id' => 'required|orbit.empty.merchant',
                    'language_id' => 'required|orbit.empty.language',
                ]);
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $merchant = App::make('orbit.empty.merchant');
            $language = App::make('orbit.empty.language');

            $merchant_language = MerchantLanguage::excludeDeleted()
                ->where('merchant_id', '=', $merchant_id)
                ->where('language_id', '=', $language_id)
                ->with('language')
                ->first();
            if (empty($merchant_language)) {
                $merchant_language = new MerchantLanguage();
                $merchant_language->merchant_id = $merchant_id;
                $merchant_language->language_id = $language_id;
                $merchant_language->save();
                $merchant_language = MerchantLanguage::with('language')->find($merchant_language->merchant_language_id);
            }

            $this->response->data = $merchant_language;

            $activityNotes = sprintf('Merchant updated: %s - added language %s', $merchant->name, $language->name);
            $activity->setUser($user)
                ->setActivityName('add_merchant_language')
                ->setActivityNameLong('Add Merchant Language OK')
                ->setObject($merchant_language)
                ->setNotes($activityNotes)
                ->responseOK();

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.merchant.postupdatemerchant.access.forbidden', array($this, $e));

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
            Event::fire('orbit.merchant.postupdatemerchant.invalid.arguments', array($this, $e));

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
            Event::fire('orbit.merchant.postupdatemerchant.query.error', array($this, $e));

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
            Event::fire('orbit.merchant.postupdatemerchant.general.exception', array($this, $e));

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

    public function postDeleteMerchantLanguage()
    {
        $activity = Activity::portal()
            ->setActivityType('delete');
        $user = NULL;
        $merchant = NULL;

        $httpCode = 200;
        $merchant_language = NULL;

        try {

            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            if (! ACL::create($user)->isAllowed('update_merchant')) {
                $updateMerchantLang = Lang::get('validation.orbit.actionlist.update_merchant');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateMerchantLang));
                ACL::throwAccessForbidden($message);
            }

            $this->registerCustomValidation();
            $merchant_id = OrbitInput::post('merchant_id');
            $merchant_language_id = OrbitInput::post('merchant_language_id');

            $validator = Validator::make(
                ['merchant_id' => $merchant_id, 'merchant_language_id' => $merchant_language_id],
                [
                    'merchant_id' => 'required|orbit.empty.merchant',
                    'merchant_language_id' => 'required|orbit.empty.merchant_language',
                ]);
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $merchant_language = App::make('orbit.empty.merchant_language');
            $merchant = App::make('orbit.empty.merchant');
            $language = $merchant_language->language;

            if ((string)$merchant_language->merchant_id != (string)$merchant->merchant_id) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
            }

            $merchant_language->delete();


            $this->response->data = $merchant_language;

            $activityNotes = sprintf('Merchant updated: %s - deleted language %s', $merchant->name, $language->name);
            $activity->setUser($user)
                ->setActivityName('delete_merchant_language')
                ->setActivityNameLong('Delete Merchant Language OK')
                ->setObject($merchant_language)
                ->setNotes($activityNotes)
                ->responseOK();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                ->setActivityName('delete_merchant_language')
                ->setActivityNameLong('Delete Merchant Language Failed')
                ->setObject($merchant_language)
                ->setNotes($e->getMessage())
                ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                ->setActivityName('delete_merchant_language')
                ->setActivityNameLong('Delete Merchant Language Failed')
                ->setObject($merchant_language)
                ->setNotes($e->getMessage())
                ->responseFailed();
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

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                ->setActivityName('delete_merchant_language')
                ->setActivityNameLong('Delete Merchant Language Failed')
                ->setObject($merchant_language)
                ->setNotes($e->getMessage())
                ->responseFailed();
        } catch (Exception $e) {
            $httpCode = 500;
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                ->setActivityName('delete_merchant_language')
                ->setActivityNameLong('Delete Merchant Language Failed')
                ->setObject($merchant_language)
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
            $merchant = Retailer::excludeDeleted()
                ->where('merchant_id', $value)
                ->where('is_mall', 'yes')
                ->first();

            if (empty($merchant)) {
                return false;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return true;
        });

        $user = $this->api->user;
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) use ($user) {
            $merchant = Retailer::excludeDeleted()
                ->allowedForUser($user)
                ->where('merchant_id', $value)
                ->where('is_mall', 'yes')
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
    }

}
