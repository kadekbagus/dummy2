<?php
/**
 * An API controller for managing TelcoOperator.
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
use DominoPOS\OrbitUploader\Uploader as OrbitUploader;


class TelcoOperatorAPIController extends ControllerAPI
{
    protected $viewTelcoRoles = ['super admin'];
    protected $modifyTelcoRoles = ['super admin'];
    protected $returnBuilder = FALSE;
    protected $defaultLanguage = 'en';

    /**
     * POST - Create New Telco
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewTelcoOperator()
    {
        $user = NULL;
        $newTelco = NULL;
        try {
            $httpCode = 200;

            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->modifyTelcoRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $name = OrbitInput::post('name');
            $countryId = OrbitInput::post('country_id');
            $identificationPrefixNumbers = OrbitInput::post('identification_prefix_numbers');
            $status = OrbitInput::post('status');
            $logo = OrbitInput::files('logo');

            $this->registerCustomValidation();

            // generate array validation image
            $logo_validation = $this->generate_validation_image('telco_logo', $logo, 'orbit.upload.telco.logo');

            $validation_data = [
                'pulsa_operator_name'     => $name,
                'pulsa_operator_country'  => $countryId,
                'identification_prefix_numbers' => $identificationPrefixNumbers,
                'status'                  => $status,
            ];

            $validation_error = [
                'pulsa_operator_name'     => 'required|orbit.telco.unique',
                'pulsa_operator_country'  => 'required',
                'identification_prefix_numbers' => 'required',
                'status'                  => 'required|in:active,inactive',
            ];

            $validation_error_message = [
                'orbit.telco.unique' => 'A Pulsa Operator is already exist with that name'
            ];

            // add validation image
            if (! empty($logo_validation)) {
                $validation_data += $logo_validation['data'];
                $validation_error += $logo_validation['error'];
                $validation_error_message += $logo_validation['error_message'];
            }

            $validator = Validator::make(
                $validation_data,
                $validation_error,
                $validation_error_message
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $newTelco = new TelcoOperator();
            $newTelco->name = $name;
            $newTelco->country_id = $countryId;
            $newTelco->identification_prefix_numbers = $identificationPrefixNumbers;
            $newTelco->status = $status;

            $newTelco->save();

            Event::fire('orbit.telco.postnewtelco.after.save', array($this, $newTelco));

            $this->response->data = $newTelco;

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
            $this->response->data = $e->getLine();

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * POST - Update Existing Telco
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateTelcoOperator()
    {
        $user = NULL;
        $updatedTelco = NULL;
        try {
            $httpCode = 200;

            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->modifyTelcoRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $telcoOperatorId = OrbitInput::post('telco_operator_id');
            $name = OrbitInput::post('name');
            $countryId = OrbitInput::post('country_id');
            $identificationPrefixNumbers = OrbitInput::post('identification_prefix_numbers');
            $status = OrbitInput::post('status');
            $logo = OrbitInput::files('logo');

            $this->registerCustomValidation();

            // generate array validation image
            $logo_validation = $this->generate_validation_image('telco_logo', $logo, 'orbit.upload.telco.logo');

            $validation_data = [
                'telco_operator_id'       => $telcoOperatorId,
                'pulsa_operator_name'     => $name,
                'pulsa_operator_country'  => $countryId,
                'identification_prefix_numbers' => $identificationPrefixNumbers,
                'status'                  => $status,
            ];

            $validation_error = [
                'telco_operator_id'       => 'required',
                'pulsa_operator_name'     => 'required|orbit.telco.updateunique:' . $telcoOperatorId,
                'pulsa_operator_country'  => 'required',
                'identification_prefix_numbers' => 'required',
                'status'                  => 'required|in:active,inactive|orbit.telco.updatestatus:' . $telcoOperatorId,
            ];

            $validation_error_message = [
                'orbit.telco.updateunique' => 'A Pulsa Operator is already exist with that name',
                'orbit.telco.updatestatus' => 'Pulsa Operator cannot be inactivated due to an active Pulsa linked to it'
            ];

            // add validation image
            if (! empty($logo_validation)) {
                $validation_data += $logo_validation['data'];
                $validation_error += $logo_validation['error'];
                $validation_error_message += $logo_validation['error_message'];
            }

            $validator = Validator::make(
                $validation_data,
                $validation_error,
                $validation_error_message
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $updatedTelco = TelcoOperator::where('telco_operator_id', $telcoOperatorId)
                ->firstOrFail();

            $updatedTelco->name = $name;
            $updatedTelco->country_id = $countryId;
            $updatedTelco->identification_prefix_numbers = $identificationPrefixNumbers;
            $updatedTelco->status = $status;

            $updatedTelco->save();

            Event::fire('orbit.telco.postupdatetelco.after.save', array($this, $updatedTelco));

            $this->response->data = $updatedTelco;

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
            $this->response->data = $e->getLine();

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * POST - Get Telco List
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchTelcoOperator()
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
            $validRoles = $this->viewTelcoRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $sortBy = OrbitInput::get('sortby');

            $validator = Validator::make(
                array(
                    'sort_by' => $sortBy,
                ),
                array(
                    'sort_by' => 'in:name,country_name,status',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.telco.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.telco.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $telcos = TelcoOperator::select('telco_operator_id', 'telco_operators.name as name', 'countries.name as country_name', 'telco_operators.status')
                ->leftJoin('countries', 'countries.country_id', '=', 'telco_operators.country_id')
                ->with(['mediaLogo' => function($q) {
                    $q->select('media_id', 'path', 'media_name_long', 'object_id');
                }]);

            // Filter by status
            OrbitInput::get('status', function($status) use ($telcos)
            {
                $telcos->where('telco_operators.status', $status);
            });

            $_telcos = clone $telcos;

            // if not printing / exporting data then do pagination.
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
                $telcos->take($take);

                $skip = 0;
                OrbitInput::get('skip', function ($_skip) use (&$skip, $telcos) {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                });
                $telcos->skip($skip);
            }

            // Default sort by
            $sortBy = 'telco_operators.name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'name'         => 'name',
                    'country_name' => 'country_name',
                    'status'       => 'telco_operators.status',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $telcos->orderBy($sortBy, $sortMode);

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return ['builder' => $telcos, 'count' => RecordCounter::create($_telcos)->count()];
            }

            $totalRec = RecordCounter::create($_telcos)->count();
            $listOfRec = $telcos->get();

            $data = new stdclass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if ($totalRec === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.telco');
            }

            $this->response->data = $data;

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
     * POST - Get Telco Detail
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getDetailTelcoOperator()
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
            $validRoles = $this->viewTelcoRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $telcoOperatorId = OrbitInput::get('telco_operator_id');

            $validator = Validator::make(
                array(
                    'telco_operator_id' => $telcoOperatorId,
                ),
                array(
                    'telco_operator_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $telco = TelcoOperator::select(
                    'telco_operator_id',
                    'telco_operators.name as name',
                    'countries.name as country_name',
                    'countries.country_id as country_id',
                    'identification_prefix_numbers',
                    'telco_operators.status')
                ->leftJoin('countries', 'countries.country_id', '=', 'telco_operators.country_id')
                ->with(['mediaLogo' => function($q) {
                    $q->select('media_id', 'path', 'media_name_long', 'object_id');
                }])
                ->where('telco_operator_id', $telcoOperatorId)
                ->firstOrFail();

            $this->response->data = $telco;

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

    protected function generate_validation_image($image_name, $images, $config, $max_count = 1) {
        $validation = [];
        if (! empty($images)) {
            $images_properties = OrbitUploader::simplifyFilesVar($images);
            $image_config = Config::get($config);
            $image_type =  "image/" . implode(",image/", $image_config['file_type']);
            $image_units = OrbitUploader::bytesToUnits($image_config['file_size']);

            $validation['data'] = [
                $image_name => $images
            ];
            $validation['error'] = [
                $image_name => 'nomore.than:' . $max_count
            ];
            $validation['error_message'] = [
                $image_name . '.nomore.than' => Lang::get('validation.max.array', array('max' => $max_count))
            ];

            foreach ($images_properties as $idx => $image) {
                $ext = strtolower(substr(strrchr($image->name, '.'), 1));
                $idx+=1;

                $validation['data'][$image_name . '_' . $idx . '_type'] = $image->type;
                $validation['data'][$image_name . '_' . $idx . '_size'] = $image->size;

                $validation['error'][$image_name . '_' . $idx . '_type'] = 'in:' . $image_type;
                $validation['error'][$image_name . '_' . $idx . '_size'] = 'orbit.file.max_size:' . $image_config['file_size'];

                $validation['error_message'][$image_name . '_' . $idx . '_type' . '.in'] = Lang::get('validation.orbit.file.type', array('ext' => $ext));
                $validation['error_message'][$image_name . '_' . $idx . '_size' . '.orbit.file.max_size'] = ($max_count > 1) ? Lang::get('validation.orbit.file.max_size', array('size' => $image_units['newsize'], 'unit' => $image_units['unit'])) : Lang::get('validation.orbit.file.max_size_one', array('name' => ucfirst(str_replace('_', ' ', $image_name)), 'size' => $image_units['newsize'], 'unit' => $image_units['unit']));
            }
        }

        return $validation;
    }

    protected function registerCustomValidation()
    {
        Validator::extend('orbit.file.max_size', function ($attribute, $value, $parameters) {
            $config_size = $parameters[0];
            $file_size = $value;

            if ($file_size > $config_size) {
                return false;
            }

            return true;
        });

        Validator::extend('orbit.telco.unique', function ($attribute, $value, $parameters) {
            $name = $value;

            $existingTelco = TelcoOperator::where('name', $name)->first();

            if (is_object($existingTelco)) {
                return false;
            }

            return true;
        });

        Validator::extend('orbit.telco.updateunique', function ($attribute, $value, $parameters) {
            $name = $value;
            $id = $parameters[0];

            $existingTelco = TelcoOperator::where('name', $name)
                ->where('telco_operator_id', '<>', $id)
                ->first();

            if (is_object($existingTelco)) {
                return false;
            }

            return true;
        });

        // cannot be inactivated when there is an active pulsa linked to this operator
        Validator::extend('orbit.telco.updatestatus', function ($attribute, $value, $parameters) {
            $status = $value;
            $id = $parameters[0];

            if ($status === 'inactive') {
                $activePulsa = Pulsa::where('status', 'active')
                    ->where('telco_operator_id', '=', $id)
                    ->first();

                if (is_object($activePulsa)) {
                    return false;
                }
            }

            return true;
        });

        // Check the images, we are allowed array of images but not more than
        Validator::extend('nomore.than', function ($attribute, $value, $parameters) {
            $max_count = $parameters[0];

            if (is_array($value['name']) && count($value['name']) > $max_count) {
                return FALSE;
            }

            return TRUE;
        });
    }

}
