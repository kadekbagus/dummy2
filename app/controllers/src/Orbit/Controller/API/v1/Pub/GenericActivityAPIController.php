<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * API Controller for generic activity (mobileci group) that doesn't have API
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use IntermediateBaseController;
use OrbitShop\API\v1\OrbitShopAPI;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\CommonAPIControllerTrait;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use OrbitShop\API\v1\ResponseProvider;
use Illuminate\Database\QueryException;
use Orbit\Helper\Net\GuestUserGenerator;
use Config;
use stdClass;
use \Activity;
use Validator;
use User;
use Lang;
use Mall;
use App;
use Orbit\Helper\Session\UserGetter;
use Illuminate\Database\Eloquent\Model;

class GenericActivityAPIController extends IntermediateBaseController
{
    public function postNewGenericActivity()
    {
        $this->response = new ResponseProvider();
        $user = NULL;
        $mall = NULL;
        $httpCode = 200;

        $genericActivityConfig = Config::get('orbit.generic_activity');

        if (empty($genericActivityConfig)) {
            $this->response->code = 1;
            $this->response->status = 'error';
            $this->response->message = 'Activity config is not configured correctly.';
            $this->response->data = null;
            return $this->render($this->response);
        }

        $activityParamName = $genericActivityConfig['parameter_name'];
        $activityNumber = OrbitInput::post($activityParamName, NULL);

        if (empty($activityNumber)) {
            $this->response->code = 1;
            $this->response->status = 'error';
            $this->response->message = 'Activity identifier is required.';
            $this->response->data = null;
            return $this->render($this->response);
        }

        if (! array_key_exists($activityNumber, $genericActivityConfig['activity_list'])) {
            $this->response->code = 1;
            $this->response->status = 'error';
            $this->response->message = 'Activity identifier is not found.';
            $this->response->data = null;
            return $this->render($this->response);
        }

        if (! isset($genericActivityConfig['activity_list'])
            || ! isset($genericActivityConfig['activity_list'][$activityNumber])
            || ! isset($genericActivityConfig['activity_list'][$activityNumber]['name'])
            || empty($genericActivityConfig['activity_list'][$activityNumber]['name'])
            || ! isset($genericActivityConfig['activity_list'][$activityNumber]['name_long'])
            || ! isset($genericActivityConfig['activity_list'][$activityNumber]['module_name'])
            || ! isset($genericActivityConfig['activity_list'][$activityNumber]['type'])
        ) {
            $this->response->code = 1;
            $this->response->status = 'error';
            $this->response->message = 'Activity config is not configured correctly.';
            $this->response->data = null;
            return $this->render($this->response);
        }

        $activityName = $genericActivityConfig['activity_list'][$activityNumber]['name'];
        $activityNameLong = $genericActivityConfig['activity_list'][$activityNumber]['name_long'];
        $activityModuleName = $genericActivityConfig['activity_list'][$activityNumber]['module_name'];
        $activityType = $genericActivityConfig['activity_list'][$activityNumber]['type'];
        $activityObjectType = $genericActivityConfig['activity_list'][$activityNumber]['object_type'];
        $activityObjectIDParamName = $genericActivityConfig['activity_list'][$activityNumber]['parameter_name'];
        // object type is supplied by frontend
        $activityObjectTypeParamName = NULL;
        if (isset($genericActivityConfig['activity_list'][$activityNumber]['object_type_parameter_name'])) {
            $activityObjectTypeParamName = $genericActivityConfig['activity_list'][$activityNumber]['object_type_parameter_name'];
        }

        $activity = Activity::mobileci()->setActivityType($activityType);
        try {
            $this->session->start([], 'no-session-creation');

            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            $object = null;
            if (! empty($activityObjectType) && ! empty($activityObjectIDParamName)) {
                $object_id = OrbitInput::post($activityObjectIDParamName, null);

                if (! empty($object_id)) {
                    // Model name is provided from frontend, need to double check
                    $objectString = OrbitInput::post($activityObjectTypeParamName, NULL);
                    if ($activityObjectType === '--SET BY object_type_parameter_name--' && ! empty($objectString)) {
                        // check if class exists
                        if (class_exists($objectString) ) {
                            $activityObjectType = $objectString;
                            // check if model name is instance of Model
                            if (! (new $activityObjectType instanceof Model)) {
                                OrbitShopAPI::throwInvalidArgument('Invalid object type parameter name');
                            }
                        } else {
                            OrbitShopAPI::throwInvalidArgument('Invalid object type parameter name');
                        }
                    }
                    $object_primary_name = App::make($activityObjectType)->getkeyName();

                    $savedObject = $activityObjectType::excludeDeleted()
                        ->where($object_primary_name, $object_id)
                        ->first();

                    if (is_object($savedObject)) {
                        $object = $savedObject;
                    } else {
                        // should throw error if object is not found when Object Name is provided by frontend
                        if (! empty($objectString)) {
                            OrbitShopAPI::throwInvalidArgument('Object ID not found');
                        }
                    }
                }
            }

            // Get mall object for set setLocation activity
            $mallId = OrbitInput::post('mall_id', null);

            if (! empty($mallId)) {
                $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $mallId)
                        ->first();
            }

            $activity->setUser($user)
                ->setActivityName($activityName)
                ->setActivityNameLong($activityNameLong)
                ->setObject($object)
                ->setModuleName($activityModuleName)
                ->setLocation($mall)
                ->responseOK()
                ->save();

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
            $this->response->data = $activity->activity_id;

        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            $activity->setUser($user)
                ->setActivityName($activityName)
                ->setActivityNameLong($activityNameLong)
                ->setObject(null)
                ->setModuleName($activityModuleName)
                ->setNotes('Failed to save activity: ' . $activityNameLong . '. Error: ' . $e->getMessage())
                ->responseFailed()
                ->save();
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            $activity->setUser($user)
                ->setActivityName($activityName)
                ->setActivityNameLong($activityNameLong)
                ->setObject(null)
                ->setModuleName($activityModuleName)
                ->setNotes('Failed to save activity: ' . $activityNameLong . '. Error: ' . $e->getMessage())
                ->responseFailed()
                ->save();
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

            $activity->setUser($user)
                ->setActivityName($activityName)
                ->setActivityNameLong($activityNameLong)
                ->setObject(null)
                ->setModuleName($activityModuleName)
                ->setNotes('Failed to save activity: ' . $activityNameLong . '. Error: ' . $e->getMessage())
                ->responseFailed()
                ->save();
        } catch (\Exception $e) {

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

            $activity->setUser($user)
                ->setActivityName($activityName)
                ->setActivityNameLong($activityNameLong)
                ->setObject(null)
                ->setModuleName($activityModuleName)
                ->setNotes('Failed to save activity: ' . $activityNameLong . '. Error: ' . $e->getMessage())
                ->responseFailed()
                ->save();
        }

        return $this->render($this->response);
    }


}
