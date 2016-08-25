<?php namespace Orbit\Controller\API\v1\Pub;

use IntermediateBaseController;
use OrbitShop\API\v1\ResponseProvider;
use Orbit\Helper\Session\UserGetter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Orbit\Helper\Net\SessionPreparer;
use OrbitShop\API\v1\OrbitShopAPI;
use Activity;
use Validator;
use User;
use Lang;
use Config;
use stdclass;
use DB;
use Event;
use Hash;

class UserAPIController extends IntermediateBaseController
{
    public function postEditAccount()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUser($this->session);

            $userId = $this->session->read('user_id');
            if (($this->session->read('logged_in') !== TRUE || ! $userId) || ! is_object($user)) {
                OrbitShopAPI::throwInvalidArgument('You need to log in to view this page.');
            }

            // Begin database transaction
            DB::beginTransaction();

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
            DB::commit();

            // Successfull Update
            $activityNotes = sprintf('User updated: %s', $updateUser->username);
            $activity->setUser($user)
                    ->setActivityName('update_user')
                    ->setActivityNameLong('Update User OK')
                    ->setObject($updateUser)
                    ->setNotes($activityNotes)
                    ->responseOK();

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

            $activityNote = sprintf('Update User Account, user Id: %s', $user->user_id);
            $activity->setUser($user)
                ->setActivityName('update_user_account')
                ->setActivityNameLong('Update User Account')
                ->setModuleName('My Account')
                ->setNotes($activityNote)
                ->responseOK()
                ->save();

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
        } catch (ACLForbiddenException $e) {
            DB::rollback();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            $activity->setUser($user)
                ->setActivityName('update_user_account')
                ->setActivityNameLong('Update User Account Failed')
                ->setModuleName('My Account')
                ->setNotes($e->getMessage())
                ->responseFailed()
                ->save();
        } catch (InvalidArgsException $e) {
            DB::rollback();
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;

            $activity->setUser($user)
                ->setActivityName('update_user_account')
                ->setActivityNameLong('Update User Account Failed')
                ->setModuleName('My Account')
                ->setNotes($e->getMessage())
                ->responseFailed()
                ->save();
        } catch (QueryException $e) {
            DB::rollback();
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
                ->setActivityName('update_user_account')
                ->setActivityNameLong('Update User Account Failed')
                ->setModuleName('My Account')
                ->setNotes($e->getMessage())
                ->responseFailed()
                ->save();
        } catch (Exception $e) {
            DB::rollback();
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

            $activity->setUser($user)
                ->setActivityName('update_user_account')
                ->setActivityNameLong('Update User Account Failed')
                ->setModuleName('My Account')
                ->setNotes($e->getMessage())
                ->responseFailed()
                ->save();
        }

        return $this->render($this->response);
    }
}
