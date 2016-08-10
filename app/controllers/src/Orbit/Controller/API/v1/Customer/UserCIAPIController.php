<?php namespace Orbit\Controller\API\v1\Customer;

/**
 * @author Ahmad <ahmad@dominopos.com>
 * @desc Controller for User specific requests for Mobile CI Angular
 */
use Orbit\Controller\API\v1\Customer\BaseAPIController;
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \Carbon\Carbon as Carbon;
use \Validator;
use Tenant;
use Mall;
use App;
use Employee;
use Coupon;
use News;
use Lang;
use User;
use Event;
use Hash;

class UserCIAPIController extends BaseAPIController
{
    protected $validRoles = ['super admin', 'consumer', 'guest'];
    protected $mall_id = null;

    public function getMyAccountInfo()
    {
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

            $image = null;
            $media = $user->profilePicture()
                ->where('media_name_long', 'user_profile_picture_orig')
                ->get();

            if (count($media) > 0) {
                if (! empty($media[0]->path)) {
                    $image = $media[0]->path;
                }
            }

            $data = new \stdclass();
            $data->email = $user->user_email;
            $data->firstname = $user->user_firstname;
            $data->lastname = $user->user_lastname;
            $data->image = $image;

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
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
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    public function postEditAccount()
    {
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

            $this->registerCustomValidation();

            // Begin database transaction
            $this->beginTransaction();

            $updateUser = User::with('userdetail')->excludeDeleted()->find($user->user_id);

            OrbitInput::post('user_firstname', function($user_firstname) use ($updateUser) {
                $validator = Validator::make(
                    array('user_firstname' => $user_firstname),
                    array('user_firstname' => 'required')
                );
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                $updateUser->user_firstname = $user_firstname;
            });

            OrbitInput::post('user_lastname', function($user_lastname) use ($updateUser) {
                $validator = Validator::make(
                    array('user_lastname' => $user_lastname),
                    array('user_lastname' => 'required')
                );
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                $updateUser->user_lastname = $user_lastname;
            });

            OrbitInput::post('password', function($newPassword) use ($updateUser) {
                $validator = Validator::make(
                    array('password' => $newPassword),
                    array('password' => 'required|min:6')
                );
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                $updateUser->user_password = Hash::make($newPassword);
            });

            $updateUser->save();

            // Even for upload user picture profile
            Event::fire('orbit.user.postupdateuser.after.save', array($this, $updateUser));

            $this->response->data = $updateUser;

            // Commit the changes
            $this->commit();

            // Successfull Update
            // $activityNotes = sprintf('User updated: %s', $updateUser->username);
            // $activity->setUser($user)
            //         ->setActivityName('update_user')
            //         ->setActivityNameLong('Update User OK')
            //         ->setObject($updateUser)
            //         ->setNotes($activityNotes)
            //         ->responseOK();

            $image = null;
            $media = $user->profilePicture()
                ->where('media_name_long', 'user_profile_picture_orig')
                ->get();

            if (count($media) > 0) {
                if (! empty($media[0]->path)) {
                    $image = $media[0]->path;
                }
            }

            $data = new \stdclass();
            $data->email = $user->user_email;
            $data->firstname = $user->user_firstname;
            $data->lastname = $user->user_lastname;
            $data->image = $image;

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';

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
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check the existance of merchant id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });
    }
}
