<?php
/**
 * An API controller for managing Advert.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use \Carbon\Carbon as Carbon;
use \Orbit\Helper\Exception\OrbitCustomException;
use DominoPOS\OrbitUploader\Uploader as OrbitUploader;

class PulsaAPIController extends ControllerAPI
{
    protected $viewPulsaRoles = ['super admin'];
    protected $modifyPulsaRoles = ['super admin'];
    protected $returnBuilder = FALSE;
    protected $defaultLanguage = 'en';

    /**
     * POST - Create New Pulsa
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `telco_operator_id`     (optional) - telco_operator_id
     * @param string    `pulsa_code`            (optional) - pulsa_code
     * @param string    `pulsa_display_name`    (optional) - pulsa_display_name
     * @param string    `description`           (optional) - description
     * @param string    `value`                 (optional) - value
     * @param string    `price`                 (optional) - price
     * @param string    `quantity`              (optional) - quantity
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewPulsa()
    {
        $user = NULL;
        $newPulsa = NULL;
        try {
            $httpCode = 200;

            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->modifyPulsaRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $this->registerCustomValidation();

            $telco_operator_id = OrbitInput::post('telco_operator_id');
            $pulsa_code = OrbitInput::post('pulsa_code');
            $pulsa_display_name = OrbitInput::post('pulsa_display_name');
            $description = OrbitInput::post('description');
            $value = OrbitInput::post('value');
            $price = OrbitInput::post('price');
            $quantity = OrbitInput::post('quantity', 0);
            $status = OrbitInput::post('status');
            $vendor_price = OrbitInput::post('vendor_price');
            $displayed = OrbitInput::post('displayed', 'yes');
            $object_type = OrbitInput::post('object_type', 'pulsa');
            $isPromo = OrbitInput::post('is_promo', 'no');
            $errorMessageObjectType = ucwords(str_replace(['_'], ' ', $object_type));

            $validator = Validator::make(
                array(
                    'telco_operator_id'     => $telco_operator_id,
                    'pulsa_code'            => $pulsa_code,
                    'pulsa_display_name'    => $pulsa_display_name,
                    'value'                 => $value,
                    'price'                 => $price,
                    'object_type'           => $object_type,
                    'is_promo'              => $isPromo,
                ),
                array(
                    'telco_operator_id'     => 'required|orbit.empty.telcooperator',
                    'pulsa_code'            => 'required|orbit.exist.pulsa',
                    'pulsa_display_name'    => 'required',
                    'value'                 => 'required',
                    'price'                 => 'required',
                    'object_type'           => 'required|in:pulsa,data_plan',
                    'isPromo'               => 'in:yes,no',
                ),
                array(
                    'pulsa_code.required'                => "{$errorMessageObjectType} Product Name M-Cash field is required",
                    'pulsa_display_name.required'        => "{$errorMessageObjectType} Product Name field is required",
                    'value.required'                     => 'Facial Value field is required',
                    'price.required'                     => 'Selling Price field is required',
                    'telco_operator_id.required'         => "{$errorMessageObjectType} Operator field is required",
                    'orbit.empty.telcooperator'          => "{$errorMessageObjectType} Operator not found",
                    'orbit.exist.pulsa'                  => "{$errorMessageObjectType} Product Name M-Cash must be unique",
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $newPulsa = new Pulsa();
            $newPulsa->telco_operator_id = $telco_operator_id;
            $newPulsa->object_type = $object_type;
            $newPulsa->pulsa_code = $pulsa_code;
            $newPulsa->pulsa_display_name = $pulsa_display_name;
            $newPulsa->description = $description;
            $newPulsa->value = $value;
            $newPulsa->price = $price;
            $newPulsa->quantity = $quantity;
            $newPulsa->status = $status;
            $newPulsa->vendor_price = $vendor_price;
            $newPulsa->displayed = $displayed;
            $newPulsa->is_promo = $isPromo;
            $newPulsa->save();

            // Commit the changes
            $this->commit();

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request OK';
            $this->response->data = $newPulsa;

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
            $this->response->data = $e->getLine();
            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }


    /**
     * POST - Update Pulsa
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `pulsa_item_id`         (optional) - pulsa_item_id
     * @param string    `telco_operator_id`     (optional) - telco_operator_id
     * @param string    `pulsa_code`            (optional) - pulsa_code
     * @param string    `pulsa_display_name`    (optional) - pulsa_display_name
     * @param string    `description`           (optional) - description
     * @param string    `value`                 (optional) - value
     * @param string    `price`                 (optional) - price
     * @param string    `quantity`              (optional) - quantity
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdatePulsa()
    {
        $user = NULL;
        $updatedPulsa = NULL;
        try {
            $httpCode = 200;

            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->modifyPulsaRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $this->registerCustomValidation();

            $pulsa_item_id = OrbitInput::post('pulsa_item_id');
            $telco_operator_id = OrbitInput::post('telco_operator_id');
            $pulsa_code = OrbitInput::post('pulsa_code');
            $pulsa_display_name = OrbitInput::post('pulsa_display_name');
            $value = OrbitInput::post('value');
            $price = OrbitInput::post('price');
            $quantity = OrbitInput::post('quantity');
            $object_type = OrbitInput::post('object_type', 'pulsa');
            $isPromo = OrbitInput::post('is_promo', 'no');
            $errorMessageObjectType = ucwords(str_replace(['_'], ' ', $object_type));

            $validator = Validator::make(
                array(
                    'pulsa_item_id'         => $pulsa_item_id,
                    'telco_operator_id'     => $telco_operator_id,
                    'pulsa_code'            => $pulsa_code,
                    'pulsa_display_name'    => $pulsa_display_name,
                    'value'                 => $value,
                    'price'                 => $price,
                    'is_promo'              => $isPromo,
                ),
                array(
                    'pulsa_item_id'         => 'required',
                    'telco_operator_id'     => 'required|orbit.empty.telcooperator',
                    'pulsa_code'            => 'required|pulsa_code_exists_but_me:'.$pulsa_item_id,
                    'pulsa_display_name'    => 'required',
                    'value'                 => 'required',
                    'price'                 => 'required',
                    'is_promo'              => 'in:yes,no',
                ),
                array(
                    'pulsa_code.required'          => "{$errorMessageObjectType} Product Name M-Cash field is required",
                    'pulsa_display_name.required'  => "{$errorMessageObjectType} Product Name field is required",
                    'value.required'               => 'Facial Value field is required',
                    'price.required'               => 'Selling Price field is required',
                    'telco_operator_id.required'   => "{$errorMessageObjectType} Operator field is required",
                    'orbit.empty.telcooperator'    => "{$errorMessageObjectType} Operator not found",
                    'pulsa_code_exists_but_me'     => "{$errorMessageObjectType} Product Name M-Cash must be unique",
                )

            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $updatedPulsa = Pulsa::where('pulsa_item_id', $pulsa_item_id)->firstOrFail();

            // update Partner
            OrbitInput::post('telco_operator_id', function($telco_operator_id) use ($updatedPulsa) {
                $updatedPulsa->telco_operator_id = $telco_operator_id;
            });

            OrbitInput::post('description', function($description) use ($updatedPulsa) {
                $updatedPulsa->description = $description;
            });

            OrbitInput::post('pulsa_code', function($pulsa_code) use ($updatedPulsa) {
                $updatedPulsa->pulsa_code = $pulsa_code;
            });

            OrbitInput::post('pulsa_display_name', function($pulsa_display_name) use ($updatedPulsa) {
                $updatedPulsa->pulsa_display_name = $pulsa_display_name;
            });

            OrbitInput::post('value', function($value) use ($updatedPulsa) {
                $updatedPulsa->value = $value;
            });

            OrbitInput::post('price', function($price) use ($updatedPulsa) {
                $updatedPulsa->price = $price;
            });

            OrbitInput::post('quantity', function($quantity) use ($updatedPulsa) {
                $updatedPulsa->quantity = $quantity;
            });

            OrbitInput::post('status', function($status) use ($updatedPulsa) {
                $updatedPulsa->status = $status;
            });

            OrbitInput::post('vendor_price', function($vendor_price) use ($updatedPulsa) {
                $updatedPulsa->vendor_price = $vendor_price;
            });

            OrbitInput::post('displayed', function($displayed) use ($updatedPulsa) {
                $updatedPulsa->displayed = $displayed;
            });

            OrbitInput::post('is_promo', function($isPromo) use ($updatedPulsa) {
                $updatedPulsa->is_promo = $isPromo;
            });

            $updatedPulsa->save();
            // Commit the changes
            $this->commit();

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request OK';
            $this->response->data = $updatedPulsa;
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
            $this->response->data = $e->getLine();
            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }


    public function getSearchPulsa()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this mall allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewPulsaRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $object_type = OrbitInput::get('object_type', 'pulsa');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                    'object_type' => $object_type,
                ),
                array(
                    'sort_by' => 'in:pulsa_item_id,pulsa_code,pulsa_display_name,value,price,name,quantity,status',
                    'object_type' => 'required|in:pulsa,data_plan',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.partner.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.partner.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $prefix = DB::getTablePrefix();

            $pulsa = Pulsa::select('pulsa.pulsa_item_id', 'pulsa.pulsa_code', 'pulsa.pulsa_display_name', 'telco_operators.name', 'pulsa.value', 'pulsa.price', 'pulsa.quantity', 'pulsa.status', 'pulsa.vendor_price', 'object_type', 'is_promo')
                          ->leftJoin('telco_operators', 'telco_operators.telco_operator_id', '=', 'pulsa.telco_operator_id')
                          ->where('object_type', $object_type);

            // Filter pulsa by pulsa item id
            OrbitInput::get('pulsa_item_id', function ($pulsaItemId) use ($pulsa) {
                $pulsa->where('pulsa.pulsa_item_id', $pulsaItemId);
            });

            // Filter pulsa by pulsa_code
            OrbitInput::get('pulsa_code', function ($pulsa_code) use ($pulsa) {
                $pulsa->where('pulsa.pulsa_code', $pulsa_code);
            });

            // Filter pulsa by pulsa_code_like
            OrbitInput::get('pulsa_code_like', function ($pulsa_code) use ($pulsa) {
                $pulsa->where('pulsa.pulsa_code', 'like', "%{$pulsa_code}%");
            });

            // Filter pulsa by pulsa_display_name
            OrbitInput::get('pulsa_display_name', function ($pulsa_display_name) use ($pulsa) {
                $pulsa->where('pulsa.pulsa_display_name', $pulsa_display_name);
            });

            // Filter pulsa by pulsa_display_name_like
            OrbitInput::get('pulsa_display_name_like', function ($pulsa_display_name) use ($pulsa) {
                $pulsa->where('pulsa.pulsa_display_name', 'like', "%{$pulsa_display_name}%");
            });

            // Filter pulsa by telco_operators name
            OrbitInput::get('name', function ($name) use ($pulsa) {
                $pulsa->where('telco_operators.name', $name);
            });

            // Filter pulsa by telco_operators name
            OrbitInput::get('name_like', function ($name_like) use ($pulsa) {
                $pulsa->where('telco_operators.name', 'like', "%{$name_like}%");
            });

            // Filter pulsa by value
            OrbitInput::get('value', function($value) use ($pulsa)
            {
                if ($value !== '') {
                    $pulsa->where('pulsa.value', $value);
                }
            });

            // Filter pulsa by price
            OrbitInput::get('price', function($price) use ($pulsa)
            {
                $pulsa->where('pulsa.price', $price);
            });

            // Filter pulsa by is_promo
            OrbitInput::get('is_promo', function($isPromo) use ($pulsa)
            {
                $pulsa->where('pulsa.is_promo', $isPromo);
            });

            // Filter pulsa by quantity
            OrbitInput::get('quantity', function($quantity) use ($pulsa)
            {
                $pulsa->where('pulsa.quantity', $quantity);
            });

            // Filter pulsa by status
            OrbitInput::get('status', function($status) use ($pulsa)
            {
                if ($status !== '') {
                    $pulsa->where('pulsa.status', $status);
                }
            });

            $_pulsa = clone $pulsa;

            // if not printing / exporting data then do pagination.
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
            $pulsa->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $pulsa) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $pulsa->skip($skip);

            // Default sort by
            $sortBy = 'pulsa.pulsa_display_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'pulsa_item_id'      => 'pulsa.pulsa_item_id',
                    'pulsa_display_name' => 'pulsa.pulsa_display_name',
                    'pulsa_code'         => 'pulsa.pulsa_code',
                    'value'              => 'pulsa.value',
                    'price'              => 'pulsa.price',
                    'quantity'           => 'pulsa.quantity',
                    'status'             => 'pulsa.status',
                    'name'               => 'telco_operators.name',
                    'is_promo'           => 'pulsa.is_promo',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $pulsa->orderBy($sortBy, $sortMode);

            $totalRec = RecordCounter::create($_pulsa)->count();
            $listOfRec = $pulsa->get();

            $data = new stdclass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if ($totalRec === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.pulsa');
            }

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request OK';
            $this->response->data = $data;
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
        }

        return $this->render($httpCode);
    }

    public function getDetailPulsa()
    {
        $user = NULL;
        try {
            $httpCode = 200;

            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewPulsaRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $pulsaItemId = OrbitInput::get('pulsa_item_id');

            $validator = Validator::make(
                array(
                    'pulsa_item_id' => $pulsaItemId,
                ),
                array(
                    'pulsa_item_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();
            $pulsa = Pulsa::select('pulsa.*',
                                   DB::raw("(SELECT COUNT(DISTINCT {$prefix}payment_transactions.payment_transaction_id)
                                            FROM {$prefix}payment_transactions
                                            LEFT JOIN {$prefix}payment_transaction_details ON {$prefix}payment_transaction_details.payment_transaction_id = {$prefix}payment_transactions.payment_transaction_id
                                            WHERE {$prefix}payment_transactions.status = 'success' AND {$prefix}payment_transaction_details.object_id = {$this->quote($pulsaItemId)}) as sold"))
                                ->with('telcoOperator')
                                ->where('pulsa_item_id', $pulsaItemId)
                                ->firstOrFail();

            $this->response->data = $pulsa;

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
            $this->response->data = $e->getLine();
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        Validator::extend('orbit.empty.telcooperator', function ($attribute, $value, $parameters) {
            $telco = TelcoOperator::where('telco_operator_id', $value)->first();

            if (empty($telco)) {
                return FALSE;
            }

            App::instance('orbit.empty.telcooperator', $telco);

            return TRUE;
        });

        Validator::extend('orbit.exist.pulsa', function ($attribute, $value, $parameters) {
            $pulsa = Pulsa::where('pulsa_code', $value)->first();

            if (! empty($pulsa)) {
                return FALSE;
            }

            App::instance('orbit.exist.pulsa', $pulsa);

            return TRUE;
        });

        Validator::extend('pulsa_code_exists_but_me', function ($attribute, $value, $parameters) {
            $pulsa_item_id = trim($parameters[0]);

            $pulsa = Pulsa::where('pulsa_code', $value)
                        ->where('pulsa_item_id', '!=', $pulsa_item_id)
                        ->first();

            if (! empty($pulsa)) {
                return FALSE;
            }

            App::instance('pulsa_code_exists_but_me', $pulsa);

            return TRUE;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
