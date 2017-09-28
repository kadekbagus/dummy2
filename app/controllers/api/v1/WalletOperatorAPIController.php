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

            $updateWalletOperator = PaymentProvider::where('payment_provider_id', '=', $payment_provider_id)->first();

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

            Event::fire('orbit.walletoperator.postupdatewalletoperator.after.save', array($this, $updateWalletOperator));

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
                    if ($relation === 'retailers') {
                        $wallOperator->with('retailers');
                    } elseif ($relation === 'retailer_categories') {
                        $wallOperator->with('retailerCategories');
                    } elseif ($relation === 'promotion') {
                        $wallOperator->with('promotion');
                    } elseif ($relation === 'news') {
                        $wallOperator->with('news');
                    } elseif ($relation === 'translations') {
                        $wallOperator->with('translations');
                    } elseif ($relation === 'translations.media') {
                        $wallOperator->with('translations.media');
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


}