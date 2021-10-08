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
use UserSponsor;
use BrandProduct;

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
                            'event'     => 'News',
                            'article'   => 'Article',
                            'paymentprovider' => 'PaymentProvider',
                            'paymenttransaction' => 'PaymentTransaction',
                            'partner' => 'Partner',
                            'user'      => 'User',
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

            // save product info
            $productId = OrbitInput::post('product_id', null);
            if (! empty($productId)) {
                $product = BrandProduct::active()->findOrFail($productId);
                $activity->setProduct($product);
            }

            // Get notes
            $notes = OrbitInput::post('notes', null);

            // notification activities
            $objectNotifName = ['click_push_notification', 'click_inapp_notification', 'click_delete_single_notification'];

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

                        case 'articles':
                            $activity->setObjectDisplayName('Articles');
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
            } elseif ($activityName === 'click_cc_trigger_on') {
                $notes = $this->getBankCreditCardEwallet($user->user_id);
            } elseif (in_array($activityName, $objectNotifName)) {
                $notes = OrbitInput::post('object_id', null);
                $activity->setObjectName('Notification');
            }

            $activity->setUser($user)
                    ->setActivityName($activityName)
                    ->setActivityNameLong($activityNameLong)
                    ->setModuleName($activityModuleName)
                    ->setLocation($mall)
                    ->setObject($object)
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

    public function getBankCreditCardEwallet($userId)
    {
        $str = '';
        $userSponsor = UserSponsor::select('sponsor_providers.name as bank_name', 'sponsor_credit_cards.name as credit_card_name')
                                ->join('sponsor_credit_cards', 'sponsor_credit_cards.sponsor_credit_card_id', '=', 'user_sponsor.sponsor_id')
                                ->join('sponsor_providers', 'sponsor_providers.sponsor_provider_id', '=', 'sponsor_credit_cards.sponsor_provider_id')
                                ->where('user_sponsor.sponsor_type', 'credit_card')
                                ->where('sponsor_credit_cards.status', 'active')
                                ->where('sponsor_providers.status', 'active')
                                ->where('user_sponsor.user_id', $userId)
                                ->orderBy('bank_name', 'asc')
                                ->orderBy('credit_card_name', 'asc')
                                ->get();

        $ewallet = UserSponsor::select('sponsor_providers.name as ewallet_name')
                              ->join('sponsor_providers', 'sponsor_providers.sponsor_provider_id', '=', 'user_sponsor.sponsor_id')
                              ->where('user_sponsor.sponsor_type', 'ewallet')
                              ->where('sponsor_providers.status', 'active')
                              ->where('user_sponsor.user_id', $userId)
                              ->orderBy('ewallet_name', 'asc')
                              ->get();

        $bank = [];
        if (!empty($userSponsor))
        {
            foreach($userSponsor as $key => $value) {
                $bank[$value->bank_name][] = $value->credit_card_name;
            }
        }

        if (!empty($bank))
        {
            foreach($bank as $key => $value) {
                if ($str === '') {
                    $str = $str.$key."(";
                } else {
                    $str = $str.','.$key."(";
                }
                foreach($value as $key1 =>$value1) {
                    if($key1+1 === count($value)){
                        $str = $str.$value1;
                    } else {
                        $str = $str.$value1.", ";
                    }
                }
                $str = $str.")";
            }
        }

        if (!empty($ewallet))
        {
            foreach ($ewallet as $val) {
                if($str !== '') {
                    $str = $str.','.$val->ewallet_name;
                } else {
                    $str = $str.$val->ewallet_name;
                }
            }
        }

        return $str;
    }
}
