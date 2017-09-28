<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * API Controller for generic activity (mobileci group) that doesn't have API
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\CommonAPIControllerTrait;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use \Activity;
use Validator;
use User;
use Lang;
use Mall;
use App;
use Illuminate\Database\Eloquent\Model;

class GenericActivityAPIController extends PubControllerAPI
{
    public function postNewGenericActivity()
    {
        $user = NULL;
        $mall = NULL;
        $httpCode = 200;

        $genericActivityConfig = Config::get('orbit.generic_activity');

        if (empty($genericActivityConfig)) {
            $this->response->code = 1;
            $this->response->status = 'error';
            $this->response->message = 'Activity config is not configured correctly.';
            $this->response->data = null;
            $httpCode = 403;
            return $this->render($httpCode);
        }

        $activityParamName = $genericActivityConfig['parameter_name'];
        $activityNumber = OrbitInput::post($activityParamName, NULL);

        if (empty($activityNumber)) {
            $this->response->code = 1;
            $this->response->status = 'error';
            $this->response->message = 'Activity identifier is required.';
            $this->response->data = null;
            $httpCode = 403;
            return $this->render($httpCode);
        }

        if (! array_key_exists($activityNumber, $genericActivityConfig['activity_list'])) {
            $this->response->code = 1;
            $this->response->status = 'error';
            $this->response->message = 'Activity identifier is not found.';
            $this->response->data = null;
            $httpCode = 403;
            return $this->render($httpCode);
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
            $httpCode = 403;
            return $this->render($httpCode);
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
            $user = $this->getUser();

            $object = null;
            if (! empty($activityObjectType) && ! empty($activityObjectIDParamName)) {
                $object_id = OrbitInput::post($activityObjectIDParamName, null);

                if (! empty($object_id)) {
                    // Model name is provided from frontend, need to double check
                    $objectString = OrbitInput::post($activityObjectTypeParamName, NULL);
                    if ($activityObjectType === '--SET BY object_type_parameter_name--' && ! empty($objectString)) {
                        // map object type from frontend
                        $mapObjectType = [
                            'mall'      => 'Mall',
                            'store'     => 'Tenant',
                            'coupon'    => 'Coupon',
                            'promotion' => 'News',
                            'news'      => 'News',
                            'event'     => 'News'
                        ];

                        $className = array_key_exists(strtolower($objectString), $mapObjectType) ? $mapObjectType[strtolower($objectString)] : null;

                        // check if class exists
                        if (class_exists($className) ) {
                            $activityObjectType = $className;
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

            // set object_display_name
            $object_display_name = OrbitInput::post('display_name', null);
            if(! empty($object_display_name)) {
                $activity->setObjectDisplayName($object_display_name);
            }

            if (! empty($mallId)) {
                $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $mallId)
                        ->first();
            }

            // Get notes
            $notes = OrbitInput::post('notes', null);

            // Menu specific block,
            if ($activityName === 'click_menu') {
                // to set object_display_name to 'sidebar' or else
                $menuSource = OrbitInput::post('menu_location', null);

                if (! empty($menuSource)) {
                    switch ($menuSource) {
                        case 'sidebar':
                            $activity->setObjectDisplayName('Sidebar Menu');
                            break;

                        case 'topmenu':
                        default:
                            $activity->setObjectDisplayName('Top Menu');
                            break;
                    }
                }
            } elseif ($activityName === 'click_tab_menu') {
                $menu = OrbitInput::post('menu', null);
                if (! empty($menu)) {
                    switch ($menu) {
                        case 'promotions':
                            $activity->setObjectDisplayName('Promotions');
                            break;

                        case 'coupons':
                            $activity->setObjectDisplayName('Coupons');
                            break;

                        case 'stores':
                            $activity->setObjectDisplayName('Stores');
                            break;

                        case 'malls':
                            $activity->setObjectDisplayName('Malls');
                            break;

                        case 'events':
                            $activity->setObjectDisplayName('Events');
                            break;

                        default:
                            $activity->setObjectDisplayName('undefined');
                            break;
                    }
                }
            } elseif ($activityName === 'click_filter') {
                $notes = '';
                $filter = OrbitInput::post('filter', null);
                $country = OrbitInput::post('country', null);
                $filter_values = OrbitInput::post('filter_values', null);
                if (! empty($filter_values)) {
                    $notes = implode(',', $filter_values);
                }
                if (! empty($filter)) {
                    switch ($filter) {
                        case 'locations':
                            $activity->setObjectDisplayName('Location');
                            $activity->setObjectName($country);
                            break;

                        case 'categories':
                            $activity->setObjectDisplayName('Category');
                            break;

                        case 'partners':
                            $activity->setObjectDisplayName('Partner');
                            break;

                        case 'orders':
                            $activity->setObjectDisplayName('Sort By');
                            break;

                        default:
                            $activity->setObjectDisplayName('undefined');
                            break;
                    }
                }
            } elseif ($activityName === 'click_get_coupon') {
                $notes = '';
                if ($user->isConsumer()) {
                    $notes = 'Signed in user';
                } else {
                    $notes = 'Guest user';
                }
            } elseif ($activityName === 'click_push_notification') {
                $unusualObject = true;
            }

            if (! isset($unusualObject) || $unusualObject === false) {
                $activity->setObject($object);
            } else {
                $objectId = OrbitInput::post('object_id', null);
                $activity->setObjectId($objectId);
            }

            $activity->setUser($user)
                    ->setActivityName($activityName)
                    ->setActivityNameLong($activityNameLong)
                    ->setModuleName($activityModuleName)
                    ->setLocation($mall)
                    ->setNotes($notes)
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

        return $this->render($httpCode);
    }


}
