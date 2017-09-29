<?php
/**
 * An API controller for managing Wallet Operator/Payment Provider.
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

            $this->registerCustomValidation();

            $payment_name = OrbitInput::post('payment_name');
            $description = OrbitInput::post('description');
            $status = OrbitInput::post('status', 'active');
            $mdr = OrbitInput::post('mdr');
            $mdr_commission = OrbitInput::post('mdr_commission');
            $deeplink_url = OrbitInput::post('deeplink_url');
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
                    'deeplink_url' => $deeplink_url,
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
                    'deeplink_url' => 'required',
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
            $newWalletOperator->deeplink_url = $deeplink_url;
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

            OrbitInput::post('gtm_banks', function($gtm_banks_json_string) use ($newWalletOperator) {
                $this->validateAndSaveBanks($newWalletOperator, $gtm_banks_json_string, 'create');
            });

            Event::fire('orbit.walletoperator.postnewwalletoperator.after.save', array($this, $newWalletOperator));

            $newWalletOperator->contact = $newContactPerson;

            // Commit the changes
            $this->commit();

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
            $this->response->data = $newWalletOperator;

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

            $this->registerCustomValidation();

            $payment_provider_id = OrbitInput::post('payment_provider_id');
            $status = OrbitInput::post('status');

            $validator = Validator::make(
                array(
                    'payment_provider_id' => $payment_provider_id,
                    'status' => $status,
                ),
                array(
                    'payment_provider_id' => 'required',
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

            $updateWalletOperator = PaymentProvider::where('payment_provider_id', '=', $payment_provider_id)
                                                   ->first();
            $updateContactWalletOperator = ObjectContact::where('object_id', '=', $payment_provider_id)
                                                        ->where('object_type', '=', 'wallet_operator')
                                                        ->first();

            OrbitInput::post('payment_name', function($payment_name) use ($updateWalletOperator) {
                $updateWalletOperator->payment_name = $payment_name;
            });

            OrbitInput::post('description', function($description) use ($updateWalletOperator) {
                $updateWalletOperator->descriptions = $description;
            });

            OrbitInput::post('mdr', function($mdr) use ($updateWalletOperator) {
                $updateWalletOperator->mdr = $mdr;
            });

            OrbitInput::post('mdr_commission', function($mdr_commission) use ($updateWalletOperator) {
                $updateWalletOperator->mdr_commission = $mdr_commission;
            });

            OrbitInput::post('deeplink_url', function($deeplink_url) use ($updateWalletOperator) {
                $updateWalletOperator->deeplink_url = $deeplink_url;
            });

            OrbitInput::post('status', function($status) use ($updateWalletOperator) {
                $updateWalletOperator->status = $status;
            });

            $updateWalletOperator->save();

            OrbitInput::post('contact_person_name', function($contact_name) use ($updateContactWalletOperator) {
                $updateContactWalletOperator->contact_name = $contact_name;
            });

            OrbitInput::post('contact_person_position', function($position) use ($updateContactWalletOperator) {
                $updateContactWalletOperator->position = $position;
            });

            OrbitInput::post('contact_person_phone_number', function($phone_number) use ($updateContactWalletOperator) {
                $updateContactWalletOperator->phone_number = $phone_number;
            });

            OrbitInput::post('contact_person_phone_number_for_sms', function($phone_number_for_sms) use ($updateContactWalletOperator) {
                $updateContactWalletOperator->phone_number_for_sms = $phone_number_for_sms;
            });

            OrbitInput::post('contact_person_email', function($email) use ($updateContactWalletOperator) {
                $updateContactWalletOperator->email = $email;
            });

            $updateContactWalletOperator->save();

            OrbitInput::post('gtm_banks', function($gtm_banks_json_string) use ($updateWalletOperator) {
                $this->validateAndSaveBanks($updateWalletOperator, $gtm_banks_json_string, 'update');
            });

            Event::fire('orbit.walletoperator.postupdatewalletoperator.after.save', array($this, $updateWalletOperator));

            $updateWalletOperator->contact = $updateContactWalletOperator;

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
            $this->response->data = $updateWalletOperator;

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

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:payment_name,description,status,created_at,updated_at',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.event.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.event.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $wallOperator = PaymentProvider::excludeDeleted();

            OrbitInput::get('payment_provider_id', function($payment_provider_id) use ($wallOperator) {
                $wallOperator->where('payment_provider_id', '=', $payment_provider_id);
            });

            OrbitInput::get('payment_name', function($payment_name) use ($wallOperator) {
                $wallOperator->where('payment_name', '=', $payment_name);
            });

            OrbitInput::get('description', function($description) use ($wallOperator) {
                $wallOperator->where('descriptions', '=', $description);
            });

            OrbitInput::get('mdr', function($mdr) use ($wallOperator) {
                $wallOperator->where('mdr', '=', $mdr);
            });

            OrbitInput::get('mdr_commission', function($mdr_commission) use ($wallOperator) {
                $wallOperator->where('mdr_commission', '=', $mdr_commission);
            });

            OrbitInput::get('status', function($status) use ($wallOperator) {
                $wallOperator->where('status', '=', $status);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($wallOperator) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'media') {
                        $wallOperator->with('media');
                    } elseif ($relation === 'media_logo') {
                        $wallOperator->with('mediaLogo');
                    } elseif ($relation === 'contact') {
                        $wallOperator->with('contact');
                    } elseif ($relation === 'banks') {
                        $wallOperator->with('banks');
                    }
                }
            });

            $_wallOperator = clone $wallOperator;

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
            $wallOperator->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $wallOperator)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $wallOperator->skip($skip);

            // Default sort by
            $sortBy = 'payment_providers.payment_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'payment_name'    => 'payment_providers.payment_name',
                    'description'     => 'payment_providers.descriptions',
                    'mdr'             => 'payment_providers.mdr',
                    'mdr_commission'  => 'payment_providers.mdr_commission',
                    'status'          => 'payment_providers.status',
                    'created_at'      => 'payment_providers.created_at',
                    'updated_at'      => 'payment_providers.updated_at',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            if ($sortBy !== 'payment_providers.status') {
                $wallOperator->orderBy('payment_providers.status', 'asc');
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $wallOperator->orderBy($sortBy, $sortMode);

            $list_wallOperator = $wallOperator->get();
            $count = RecordCounter::create($_wallOperator)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($list_wallOperator);
            $this->response->data->records = $list_wallOperator;

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


    /**
     * GET - Listing Banks
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
    public function getSearchBank()
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

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:payment_name,description,status,created_at,updated_at',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.event.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.event.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $banks = Bank::excludeDeleted();

            OrbitInput::get('payment_provider_id', function($payment_provider_id) use ($wallOperator) {
                $wallOperator->where('payment_provider_id', '=', $payment_provider_id);
            });

            OrbitInput::get('payment_name', function($payment_name) use ($wallOperator) {
                $wallOperator->where('payment_name', '=', $payment_name);
            });

            OrbitInput::get('description', function($description) use ($wallOperator) {
                $wallOperator->where('descriptions', '=', $description);
            });

            OrbitInput::get('mdr', function($mdr) use ($wallOperator) {
                $wallOperator->where('mdr', '=', $mdr);
            });

            OrbitInput::get('mdr_commission', function($mdr_commission) use ($wallOperator) {
                $wallOperator->where('mdr_commission', '=', $mdr_commission);
            });

            OrbitInput::get('status', function($status) use ($wallOperator) {
                $wallOperator->where('status', '=', $status);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($wallOperator) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'media') {
                        $wallOperator->with('media');
                    } elseif ($relation === 'media_logo') {
                        $wallOperator->with('mediaLogo');
                    } elseif ($relation === 'contact') {
                        $wallOperator->with('contact');
                    } elseif ($relation === 'banks') {
                        $wallOperator->with('banks');
                    }
                }
            });

            $_wallOperator = clone $wallOperator;

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
            $wallOperator->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $wallOperator)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $wallOperator->skip($skip);

            // Default sort by
            $sortBy = 'payment_providers.payment_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'payment_name'    => 'payment_providers.payment_name',
                    'description'     => 'payment_providers.descriptions',
                    'mdr'             => 'payment_providers.mdr',
                    'mdr_commission'  => 'payment_providers.mdr_commission',
                    'status'          => 'payment_providers.status',
                    'created_at'      => 'payment_providers.created_at',
                    'updated_at'      => 'payment_providers.updated_at',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            if ($sortBy !== 'payment_providers.status') {
                $wallOperator->orderBy('payment_providers.status', 'asc');
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $wallOperator->orderBy($sortBy, $sortMode);

            $list_wallOperator = $wallOperator->get();
            $count = RecordCounter::create($_wallOperator)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($list_wallOperator);
            $this->response->data->records = $list_wallOperator;

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

    protected function registerCustomValidation()
    {
        // Check the existance of link_object_id
        Validator::extend('orbit.empty.link_object_id', function ($attribute, $value, $parameters) {
            $link_object_type = trim($parameters[0]);

            if ($link_object_type === 'bank') {
                $linkObject = Bank::excludeDeleted()
                            ->where('bank_id', $value)
                            ->first();
            }

            if (empty($linkObject)) {
                return FALSE;
            }

            App::instance('orbit.empty.link_object_id', $linkObject);

            return TRUE;
        });
    }

    /**
     * @param EventModel $event
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveBanks($walletOperator, $gtm_banks_json_string, $scenario = 'create')
    {
        /*
         * JSON structure: object with keys = merchant_language_id and values = ProductTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where EventTranslation object is object with keys:
         *   event_name, description
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['account_name', 'account_number', 'bank_address', 'swift_code', 'status'];
        $user = $this->api->user;
        $operations = [];

        $data = @json_decode($gtm_banks_json_string);
        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'banks']));
        }
        foreach ($data as $bank_id => $bankData) {
            $bank = Bank::excludeDeleted()
                        ->where('bank_id', '=', $bank_id)
                        ->first();
            if (empty($bank)) {
                OrbitShopAPI::throwInvalidArgument('Bank not found');
            }
            $existingBank = BankGotomall::excludeDeleted()
                                        ->where('payment_provider_id', '=', $walletOperator->payment_provider_id)
                                        ->where('bank_id', '=', $bank_id)
                                        ->first();
            if ($bankData === null) {
                // deleting, verify exists
                if (empty($existingBank)) {
                    OrbitShopAPI::throwInvalidArgument('Bank not found');
                }
                $operations[] = ['delete', $existingBank];
            } else {
                foreach ($bankData as $field => $value) {
                    if (!in_array($field, $valid_fields, TRUE)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.key'));
                    }
                    if ($value !== null && !is_string($value)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.value'));
                    }
                }
                if (empty($existingBank)) {
                    $operations[] = ['create', $bank_id, $bankData];
                } else {
                    $operations[] = ['update', $existingBank, $bankData];
                }
            }
        }

        $bankDataReturn = [];
        foreach ($operations as $operation) {
            $op = $operation[0];
            if ($op === 'create') {
                $newGtmBank = new BankGotomall();
                $newGtmBank->payment_provider_id = $walletOperator->payment_provider_id;
                $newGtmBank->bank_id = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $newGtmBank->{$field} = $value;
                }
                $newGtmBank->save();
                $bankDataReturn[] = $newGtmBank;
            }
            elseif ($op === 'update') {
                $existingBank = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existingBank->{$field} = $value;
                }
                $existingBank->save();
                $bankDataReturn[] = $existingBank;
            }
            elseif ($op === 'delete') {
                $existingBank = $operation[1];
                $existingBank->delete();
            }
        }
        $walletOperator->banks = $bankDataReturn;
    }
}