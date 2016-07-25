<?php namespace Orbit\Controller\API\v1\Customer;

/**
 * @author kadek <kadek@dominopos.com>
 * @desc Controller for membership Mobile CI Angular
 */
use Orbit\Controller\API\v1\Customer\BaseAPIController;
use OrbitShop\API\v1\ResponseProvider;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \Carbon\Carbon as Carbon;
use \Validator;
use News;
use Mall;
use OrbitShop\API\v1\OrbitShopAPI;
use Activity;
use Setting;
use URL;
use App;
use User;

class MembershipCIAPIController extends BaseAPIController
{
	protected $validRoles = ['super admin', 'consumer', 'guest'];

    public function getMembershipCI()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $this->checkAuth();
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->validRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $mallId = OrbitInput::get('mall_id', null);

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'mall_id'   => $mallId,
                ),
                array(
                    'mall_id'   => 'required|orbit.empty.mall',
                )
            );
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $mall = Mall::excludeDeleted()->where('merchant_id', $mallId)->first();

            $setting = Setting::select('setting_value')
                    ->where('setting_name', '=', 'enable_membership_card')
                    ->where('object_id', '=', $mallId)
                    ->first();

            if (!is_object($setting)) {
                $setting = new \stdclass();
                $setting->setting_value = 'false';
            }

            $membership =  User::select('users.user_id', 'user_firstname', 'user_lastname', 'membership_numbers.membership_number', 'memberships.membership_name', 'media.path')
                           ->leftJoin('membership_numbers','membership_numbers.user_id','=','users.user_id')
                           ->leftJoin('memberships', 'memberships.membership_id', '=', 'membership_numbers.membership_id')
                            ->leftJoin('media', function ($join) {
                                     $join->on('media.object_id', '=', 'memberships.membership_id')
                                          ->where('media.object_name', '=', 'membership')
                                          ->where('media.media_name_long', '=', 'membership_image_orig');
                              })
                           ->where('memberships.merchant_id', '=', $mallId)
                           ->where('users.user_id', '=', $user->user_id)
                           ->first();

            $data = new \stdclass();
            $data->membership_enable = $setting->setting_value;
            $data->membership_data = $membership;

            $activityNote = sprintf('Page viewed: Membership, user Id: %s', $user->user_id);
            $activity->setUser($user)
                ->setActivityName('view_membership')
                ->setActivityNameLong('View Membership')
                ->setModuleName('Membership')
                ->setLocation($mall)
                ->setNotes($activityNote)
                ->responseOK()
                ->save();

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            $activity->setUser($user)
                ->setActivityName('view_membership')
                ->setActivityNameLong('View Membership Failed')
                ->setModuleName('Membership')
                ->setNotes('Failed to view: Membership Page. Err: ' . $e->getMessage())
                ->responseFailed()
                ->save();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;

            $activity->setUser($user)
                ->setActivityName('view_membership')
                ->setActivityNameLong('View Membership Failed')
                ->setModuleName('Membership')
                ->setNotes('Failed to view: Membership Page. Err: ' . $e->getMessage())
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
                ->setActivityName('view_membership')
                ->setActivityNameLong('View Membership Failed')
                ->setModuleName('Membership')
                ->setNotes('Failed to view: Membership Page. Err: ' . $e->getMessage())
                ->responseFailed()
                ->save();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

            $activity->setUser($user)
                ->setActivityName('view_membership')
                ->setActivityNameLong('View Membership Failed')
                ->setModuleName('Membership')
                ->setNotes('Failed to view: Membership Page. Err: ' . $e->getMessage())
                ->responseFailed()
                ->save();
        }

        return $this->render($httpCode);
    }


    protected function registerCustomValidation()
    {
        // Check the existance of mall id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted('merchants')
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return false;
            }

            App::instance('orbit.empty.mall', $mall);

            return true;
        });
    }
}