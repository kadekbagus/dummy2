<?php
/**
 * An API controller for managing seo text.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;

class WalletOperatorAPIController extends ControllerAPI
{
    protected $viewWalletOperatorRoles = ['super admin', 'mall admin', 'mall owner'];
    protected $modifyWalletOperatorRoles = ['super admin', 'mall admin', 'mall owner'];

    /**
     * POST - Create New Wallet Operator
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `payment_name`    (required) - payment_name
     * @param string     `description`     (required) - description
     * @param string     `status`          (required) - Status. Valid value: active, inactive
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewWalletOperator()
    {
        try {
            $httpCode = 200;
            // Require authentication
            $this->checkAuth();
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->modifyWalletOperatorRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $payment_name = OrbitInput::post('payment_name');
            $description = OrbitInput::post('description');
            $status = OrbitInput::post('status', 'active');
            $mdr = OrbitInput::post('mdr');
            $mdr_commission = OrbitInput::post('mdr_commission');
            $contact_person_name = OrbitInput::post('contact_person_name');
            $contact_person_position = OrbitInput::post('contact_person_position');
            $contact_person_phone_number = OrbitInput::post('contact_person_phone_number');
            $contact_person_phone_number_for_sms = OrbitInput::post('contact_person_phone_number_for_sms');
            $contact_person_email = OrbitInput::post('contact_person_email');
            $contact_person_address = OrbitInput::post('contact_person_address');

            $validator = Validator::make(
                array(
                    'payment_name' => $payment_name,
                    'description' => $description,
                    'status' => $status,
                    'mdr' => $mdr,
                    'contact_person_name' => $contact_person_name,
                    'contact_person_position' => $contact_person_position,
                    'contact_person_phone_number' => $contact_person_phone_number,
                    'contact_person_email' => $contact_person_email,
                    'contact_person_address' => $contact_person_address,
                ),
                array(
                    'payment_name' => 'required',
                    'description' => 'required',
                    'status' => 'in:active,inactive',
                    'mdr' => 'required',
                    'contact_person_name' => 'required',
                    'contact_person_position' => 'required',
                    'contact_person_phone_number' => 'required',
                    'contact_person_email' => 'required',
                    'contact_person_address' => 'required',
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $newWalletOperator = new PaymentProvider();
            $newWalletOperator->payment_name = $payment_name;
            $newWalletOperator->descriptions = $description;
            $newWalletOperator->mdr = $mdr;
            $newWalletOperator->mdr_commission = $mdr_commission;
            $newWalletOperator->status = $status;
            $newWalletOperator->save();

            $newContactPerson = new ObjectContact();
            $newContactPerson->object_id = $newWalletOperator->payment_provider_id;
            $newContactPerson->object_type = 'wallet_operator';
            $newContactPerson->contact_name = $contact_person_name;
            $newContactPerson->position = $contact_person_position;
            $newContactPerson->phone_number = $contact_person_phone_number;
            $newContactPerson->phone_number_for_sms = $contact_person_phone_number_for_sms;
            $newContactPerson->email = $contact_person_email;
            $newContactPerson->save();

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
            $this->response->data = $newWalletOperator;

            // Commit the changes
            $this->commit();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();
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
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * POST - Update Wallet Operator
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `object_type`           (required) - object_type
     * @param string     `status`                (required) - Status. Valid value: active, inactive
     * @param JSON       `translations`          (required) - contain title and description for each language
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateWalletOperator()
    {
        try {
            $httpCode = 200;
            // Require authentication
            $this->checkAuth();
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;

            $validRoles = $this->modifyWalletOperatorRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $country_id = OrbitInput::post('country_id', '0');
            $object_type = OrbitInput::post('object_type');
            $status = OrbitInput::post('status', 'active');
            $translations = OrbitInput::post('translations');

            $validator = Validator::make(
                array(
                    'object_type' => $object_type,
                    'translations' => $translations,
                    'status' => $status,
                ),
                array(
                    'object_type' => 'required|in:seo_promotion_list,seo_coupon_list,seo_event_list,seo_store_list,seo_mall_list,seo_homepage',
                    'translations' => 'required',
                    'status' => 'in:active,inactive',
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $result = $this->validateAndSaveTranslations($country_id, $object_type, $translations, $status, 'update');

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
            $this->response->data = $result;

            // Commit the changes
            $this->commit();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();
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
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * GET - Listing Wallet Operator
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `object_type`    (optional) - object_type
     * @param string     `status`         (optional) - Status. Valid value: active, inactive
     * @param string     `language`       (optional) - language code like en,id,jp,etc
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchWalletOperator()
    {

        try {
            $httpCode = 200;
            // Require authentication
            $this->checkAuth();
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewWalletOperatorRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $object_type = OrbitInput::get('object_type');

            $validator = Validator::make(
                array(
                    'object_type' => $object_type,
                ),
                array(
                    'object_type' => 'in:seo_promotion_list,seo_coupon_list,seo_event_list,seo_store_list,seo_mall_list,seo_homepage',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $seo_texts = Page::select('pages.pages_id', 'pages.title', 'pages.content as description',
                                      'pages.object_type', 'pages.language', 'pages.status', 'languages.language_id')
                                ->leftJoin('languages', 'languages.name', '=', 'pages.language')
                                ->where('object_type', 'like', '%seo_%');

            OrbitInput::get('status', function($status) use ($seo_texts) {
                $seo_texts->where('pages.status', '=', $status);
            });

            OrbitInput::get('object_type', function($object_type) use ($seo_texts) {
                $seo_texts->where('pages.object_type', '=', $object_type);
            });

            OrbitInput::get('language', function($language) use ($seo_texts) {
                $seo_texts->where('pages.language', '=', $language);
            });

            $_seo_texts = clone $seo_texts;

            $list_seo_texts = $seo_texts->get();
            $count = RecordCounter::create($_seo_texts)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($list_seo_texts);
            $this->response->data->records = $list_seo_texts;

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
            $this->response->data = null;
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
        }

        return $this->render($httpCode);
    }


    private function validateAndSaveTranslations($country_id, $object_type, $translations, $status, $operation)
    {
        $valid_fields = ['title', 'description'];
        $data = @json_decode($translations);
        $page = [];
        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
        }

        if ($operation == 'update') {
            // delete all value and them insert all new value
            $existing_translation = Page::where('country_id', '=', '0')
                         ->where('object_type', '=', $object_type)
                         ->delete();
        }

        foreach ($data as $language_id => $translations) {
            $language = Language::where('status', '=', 'active')
                ->where('language_id', '=', $language_id)
                ->first();
            if (empty($language)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
            }

            if ($translations === null) {
                // deleting, verify exists
            } else {

                foreach ($translations as $field => $value) {
                    if (!in_array($field, $valid_fields, TRUE)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.key'));
                    }
                    if ($value !== null && !is_string($value)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.value'));
                    }
                }

                // Insert every single seo per language_id
                $new_page = new Page();
                $new_page->country_id = $country_id;
                $new_page->object_type = $object_type;
                $new_page->language = $language->name;
                $new_page->title = $translations->title;
                $new_page->content = $translations->description;
                $new_page->status =$status;
                $new_page->save();
                $page[] = $new_page;
            }
        }

        return $page;
    }
}