<?php namespace Orbit\Controller\API\v1\Pub;

use OrbitShop\API\v1\PubControllerAPI;
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
use UserDetail;
use Lang;
use Config;
use stdclass;
use DB;
use Event;
use Hash;
use Queue;
use Orbit\Helper\Util\CdnUrlGenerator;

class UserAPIController extends PubControllerAPI
{
    public function postEditAccount()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');
        $httpCode = 200;
        $user = NULL;
        try{
            $user = $this->getUser();
            $emailUpdateFlag = FALSE;

            $session = SessionPreparer::prepareSession();

            $userId = $session->read('user_id');
            if (($session->read('logged_in') !== TRUE || ! $userId) || ! is_object($user)) {
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

            $updateUserDetail = UserDetail::where('user_id', $user->user_id)
                                            ->first();

            OrbitInput::post('phone', function($phone) use ($updateUserDetail) {
                $validator = Validator::make(
                    array('phone' => $phone),
                    array('phone' => 'required')
                );
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
                $updateUserDetail->phone = $phone;
            });

            OrbitInput::post('gender', function($gender) use ($updateUserDetail) {
                $updateUserDetail->gender = $gender;
            });

            OrbitInput::post('birthdate', function($birthdate) use ($updateUserDetail) {
                $updateUserDetail->birthdate = $birthdate;
            });

            $updateUserDetail->save();

            // Update session fullname and email
            $sessionData = $session->read(NULL);
            $sessionData['fullname'] = $updateUser->user_firstname. ' ' . $updateUser->user_lastname;
            //update gender, phone also
            $sessionData['gender'] = $updateUserDetail->gender;
            $sessionData['phone'] = $updateUserDetail->phone;
            $session->update($sessionData);

            // Even for upload user picture profile
            Event::fire('orbit.user.postupdateuser.after.save', array($this, $updateUser));

            $this->response->data = $updateUser;

            // Commit the changes
            DB::commit();

            if ($emailUpdateFlag) {
                // Resend email process to the queue
                Queue::push('Orbit\\Queue\\RegistrationMail', [
                    'user_id' => $updateUser->user_id,
                    'mode' => 'gotomalls'],
                    Config::get('orbit.registration.mobile.queue_name', 'gtm_email')
                );
            }

            $image = null;
            $cdnConfig = Config::get('orbit.cdn');
            $imgUrl = CdnUrlGenerator::create(['cdn' => $cdnConfig], 'cdn');

            $media = $user->profilePicture()
                ->where('media_name_long', 'user_profile_picture_orig')
                ->get();

            if (count($media) > 0) {
                if (! empty($media[0]->path)) {
                    $localPath = (! empty($media[0]->path)) ? $media[0]->path : '';
                    $cdnPath = (! empty($media[0]->cdn_url)) ? $media[0]->cdn_url : '';
                    $image = $imgUrl->getImageUrl($localPath, $cdnPath);
                }
            }

            $data = new \stdclass();
            $data->email = $updateUser->user_email;
            $data->firstname = $updateUser->user_firstname;
            $data->lastname = $updateUser->user_lastname;
            $data->phone = $updateUserDetail->phone;
            $data->gender = $updateUserDetail->gender;
            $data->birthdate = $updateUserDetail->birthdate;
            $data->image = $image;

            $activityNote = sprintf('Update User Account, user Id: %s', $updateUser->user_id);
            $activity->setUser($updateUser)
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
        } catch (\Exception $e) {
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

        return $this->render($httpCode);
    }
}
