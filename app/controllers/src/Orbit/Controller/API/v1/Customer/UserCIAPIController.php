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
use \Validator;
use Mall;
use App;
use Lang;
use User;
use UserDetail;
use Activity;
use Orbit\Helper\Util\CdnUrlGenerator;

class UserCIAPIController extends BaseAPIController
{
    protected $validRoles = ['super admin', 'consumer', 'guest'];
    protected $mall_id = null;

    public function getMyAccountInfo()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $this->checkAuth();
            $user = $this->api->user;

            // Get user detail for provide the phone data
            // note that we need to alias location as user_loc because
            // otherwise UserDetail::getLocationAttribute() will be called
            // when we use $userdetail->location
            $userDetail = UserDetail
                ::select('phone', 'gender', 'location AS user_loc', 'about', 'birthdate')
                ->where('user_id', $user->user_id)
                ->first();

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->validRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
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
            $data->id = $user->user_id;
            $data->email = $user->user_email;
            $data->firstname = $user->user_firstname;
            $data->lastname = $user->user_lastname;
            $data->role = $role->role_name;
            $data->image = $image;
            $data->phone = ! empty($userDetail) ? $userDetail->phone : null;
            $data->gender = ! empty($userDetail) ? $userDetail->gender : null;
            $data->birthdate = ! empty($userDetail) ? $userDetail->birthdate : null;

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
}
