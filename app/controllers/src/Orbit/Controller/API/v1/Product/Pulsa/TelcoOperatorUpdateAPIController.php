<?php
namespace Orbit\Controller\API\v1\Product\Pulsa;
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
use Validator;
use Config;
use Lang;
use TelcoOperator;
use Event;
use Pulsa;


class TelcoOperatorUpdateAPIController extends ControllerAPI
{
	protected $viewTelcoRoles = ['product manager'];
    protected $modifyTelcoRoles = ['product manager'];
    protected $returnBuilder = FALSE;
    protected $defaultLanguage = 'en';

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
            $seoText = OrbitInput::post('seo_text');
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
            $updatedTelco->seo_text = $seoText;
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