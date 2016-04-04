<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
 */
use \IntermediateBaseController;
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitSession\Session as OrbitSession;
use DominoPOS\OrbitSession\SessionConfig;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use Mall;
use stdClass;
use Activity;
use User;
use UserDetail;
use Hash;
use Role;
use Lang;
use Validator;
use DB;
use Artdarek\OAuth\Facade\OAuth;
use Redirect;

class LoginAPIController extends IntermediateBaseController
{
	protected $response = NULL;

	protected $session = NULL;

	public function __construct() {
		$this->response = new ResponseProvider();
		// Instantiate the OrbitSession object
        $sessConfig = new SessionConfig(Config::get('orbit.session'));
		$this->session = new OrbitSession($sessConfig);
	}

	/**
	 * POST - Login customer from gotomalls.com
	 *
	 * @author Ahmad ahmad@dominopos.com
	 * @param email
	 * @param password
	 * @param from_mall (yes/no)
	 * @param mode (login/registration)
	 */
	public function postLoginCustomer() {
		$roles=['Consumer'];
		$modes=['login', 'registration'];
		$activity = Activity::portal()
	                        ->setActivityType('login');
	    try {
	        $email = trim(OrbitInput::post('email'));
	        $password = trim(OrbitInput::post('password'));
	        $from_mall = OrbitInput::post('from_mall', 'no');
	        $mode = OrbitInput::post('mode', 'login');

	        if (trim($email) === '') {
	            $errorMessage = Lang::get('validation.required', array('attribute' => 'email'));
	            OrbitShopAPI::throwInvalidArgument($errorMessage);
	        }

	        if (trim($password) === '') {
	            $errorMessage = Lang::get('validation.required', array('attribute' => 'password'));
	            OrbitShopAPI::throwInvalidArgument($errorMessage);
	        }

	        if (! in_array($mode, $modes)) {
	        	$mode = 'login';
	        }
	        
	        // Return the current mall object if this login process coming from the mobileci
	        // todo: check later for login from mall
	        $from_mall = $from_mall === 'yes' ? TRUE : FALSE;
	        $mall_id = NULL;
	        $mall = NULL;
	        
	        $user = User::with('role')
	                    ->excludeDeleted()
	                    ->where('user_email', $email);

	        $user = $user->first();
	        
	        if (! is_object($user) && $mode === 'registration') {
	        	$firstname = OrbitInput::post('first_name');
	        	$lastname = OrbitInput::post('last_name');
	        	$gender = OrbitInput::post('gender');
	        	$birthdate = OrbitInput::post('birthdate');
	        	$password_confirmation = trim(OrbitInput::post('password_confirmation'));
                list($newuser, $userdetail, $apikey) = $this->createCustomerUser($email, $password, $password_confirmation, $firstname, $lastname, $gender, $birthdate);
                $user = $newuser;
	        }

	        if (! is_object($user)) {
	            $message = Lang::get('validation.orbit.access.inactiveuser');
	            OrbitShopAPI::throwInvalidArgument($message);
	        }

	        if (! Hash::check($password, $user->user_password)) {
	            $message = Lang::get('validation.orbit.access.loginfailed');
	            OrbitShopAPI::throwInvalidArgument($message);
	        }

	        $roleIds = Role::roleIdsByName($roles);
	        if (! in_array($user->user_role_id, $roleIds)) {
	            $message = Lang::get('validation.orbit.access.forbidden', [
	                'action' => 'Login (Role Denied)'
	            ]);
	            OrbitShopAPI::throwInvalidArgument($message);
	        }

	        // Successfull login
	        $activity->setUser($user)
	                 ->setActivityName('login_ok')
	                 ->setActivityNameLong('Sign in')
	                 ->responseOK();

	        $this->response->data = $user;
	        $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Login Success';

	    } catch (ACLForbiddenException $e) {
	        $this->response->code = $e->getCode();
	        $this->response->status = 'error';
	        $this->response->message = $e->getMessage();
	        $this->response->data = null;

	        $activity->setUser('guest')
	                 ->setActivityName('login_failed')
	                 ->setActivityNameLong('Login Failed')
	                 ->setNotes($e->getMessage())
	                 ->responseFailed();
	    } catch (InvalidArgsException $e) {
	        $this->response->code = $e->getCode();
	        $this->response->status = 'error';
	        $this->response->message = $e->getMessage();
	        $this->response->data = null;

	        $activity->setUser('guest')
	                 ->setActivityName('login_failed')
	                 ->setActivityNameLong('Login Failed')
	                 ->setNotes($e->getMessage())
	                 ->responseFailed();
	    } catch (Exception $e) {
	        $this->response->code = Status::UNKNOWN_ERROR;
	        $this->response->status = 'error';
	        $this->response->message = $e->getMessage();
	        $this->response->data = null;

	        $activity->setUser('guest')
	                 ->setActivityName('login_failed')
	                 ->setActivityNameLong('Login Failed')
	                 ->setNotes($e->getMessage())
	                 ->responseFailed();
	    }

	    // Save the activity
	    $activity->setModuleName('Application')->save();

	    return $this->render($this->response);
	}

	/**
     * Check email exist in sign in
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string        `email`             (optional)
     * @return Illuminate\Support\Facades\Response
     */
	public function checkEmailSignUp() {
		$email = OrbitInput::post('email');
        $validator = Validator::make(
            array(
                'email' => $email,
            ),
            array(
                'email' => 'required',
            )
        );

        $users = User::select('users.user_email', 'users.user_firstname', 'users.user_lastname', 'users.user_lastname', 'users.user_id', 'user_details.birthdate', 'user_details.gender', 'users.status')
                ->join('user_details', 'user_details.user_id', '=', 'users.user_id')
                ->where('users.user_email', $email)
                ->get();

        return $users;
	}

	/**
     * Validate the registration data.
     *
     * @author Rio Astamal <rio@dominopos.com>
     * @param string $email Consumer's email
     * @return array|string
     * @throws Exception
     */
    protected function validateRegistrationData($firstname, $lastname, $gender, $birthdate, $password, $password_confirmation)
    {
        // Only do the validation if there is 'mode=registration' on post body.
        $mode = OrbitInput::post('mode');
        if ($mode !== 'registration') {
            return '';
        }

        $input = array(
            'first_name' => $firstname,
            'last_name'  => $lastname,
            'gender'     => $gender,
            'birth_date' => $birthdate,
        );

        $validator = Validator::make(
            array(
                'first_name' => $firstname,
                'last_name'  => $lastname,
                'gender'     => $gender,
                'birth_date' => $birthdate,
                'password_confirmation' => $password_confirmation,
                'password' => $password,
            ),
            array(
                'first_name' => 'required',
                'last_name'  => 'required',
                'gender'     => 'required|in:m,f',
                'birth_date' => 'required|date_format:d-m-Y',
                'password_confirmation' => 'required|min:5',
                'password'  => 'min:5|confirmed',
            ),
            array(
                'birth_date.date_format' => Lang::get('validation.orbit.formaterror.date.dmy_date')
            )
        );

        if ($validator->fails()) {
            $errorMessage = $validator->messages()->first();
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        return TRUE;
    }

	/**
     * Creates a customer user and the associated user detail and API key objects.
     *
     * May throw exception if cannot find retailer or cannot find consumer role.
     *
     * Uses retailer set using setRetailerId() or the one in config.
     *
     * @param string $email the user's email
     * @param string|null $userId the unique ID (if provided) of the user to create - used in box to match data on cloud
     * @param string|null $userDetailId .... of the user detail to create - used in box to match data on cloud
     * @param string|null $apiKeyId .... of the API key to create - used in box to match data on cloud
     * @param string|null $userStatus the user status on cloud
     * @return array [User, UserDetail, ApiKey]
     * @throws Exception
     */
    public function createCustomerUser($email, $password, $password_confirmation, $firstname, $lastname, $gender, $birthdate, $userId = null, $userDetailId = null, $apiKeyId = null, $userStatus = null)
    {
    	$validation = $this->validateRegistrationData($firstname, $lastname, $gender, $birthdate, $password, $password_confirmation);
    	if ($validation) {
    		try {
		        $customerRole = Role::where('role_name', 'Consumer')->first();
		        if (empty($customerRole)) {
		            $errorMessage = Lang::get('validation.orbit.empty.consumer_role');
		            throw new Exception($errorMessage);
		        }
		        DB::beginTransaction();
		        $new_user = new User();
		        if (isset($userId)) {
		            $new_user->user_id = $userId;
		        }
		        $new_user->username = strtolower($email);
		        $new_user->user_email = strtolower($email);
		        $new_user->user_password = Hash::make($password);
		        $new_user->user_firstname = $firstname;
		        $new_user->user_lastname = $lastname;
		        $new_user->status = isset($userStatus) ? $userStatus : 'pending';
		        $new_user->user_role_id = $customerRole->role_id;
		        $new_user->user_ip = $_SERVER['REMOTE_ADDR'];
		        $new_user->external_user_id = 0;

		        $new_user->save();

		        $user_detail = new UserDetail();

		        if (isset($userDetailId)) {
		            $user_detail->user_detail_id = $userDetailId;
		        }
		        $user_detail->gender = $gender === 'm' ? 'm' : $gender === 'f' ? 'f' : NULL;
		        $user_detail->birthdate = date('Y-m-d', strtotime($birthdate));

		        // Save the user details
		        $user_detail = $new_user->userdetail()->save($user_detail);

		        // Generate API key for this user (with given ID if specified)
		        $apikey = $new_user->createApiKey($apiKeyId);

		        $new_user->setRelation('userDetail', $user_detail);
		        $new_user->user_detail = $user_detail;

		        $new_user->setRelation('apikey', $apikey);
		        $new_user->apikey = $apikey;

		        DB::commit();

		        return [$new_user, $user_detail, $apikey];
		    } catch (Exception $e) {
		    	DB::rollback();
		    	$this->response->code = $e->getCode();
		        $this->response->status = 'error';
		        $this->response->message = $e->getMessage();
		        $this->response->data = null;	

		        return $this->render($this->response);
		    }
	    } else {
	    	return $validation;
	    }
    }

    public function getGoogleSignInView() {
     	$oldRouteSessionConfigValue = Config::get('orbit.session.availability.query_string');
        Config::set('orbit.session.availability.query_string', false);

        $recognized = \Input::get('recognized', 'none');
        $code = \Input::get('code', NULL);


        $googleService = OAuth::consumer( 'Google' );
        if ( !empty( $code ) ) {
            try {

                Config::set('orbit.session.availability.query_string', $oldRouteSessionConfigValue);
                $token = $googleService->requestAccessToken( $code );

                $user = json_decode( $googleService->request( 'https://www.googleapis.com/oauth2/v1/userinfo' ), true );

                $userEmail = isset($user['email']) ? $user['email'] : '';
                $firstName = isset($user['given_name']) ? $user['given_name'] : '';
                $lastName = isset($user['family_name']) ? $user['family_name'] : '';
                $gender = isset($user['gender']) ? $user['gender'] : '';
                $socialid = isset($user['id']) ? $user['id'] : '';

                $data = [
                    'email' => $userEmail,
                    'fname' => $firstName,
                    'lname' => $lastName,
                    'gender' => $gender,
                    'login_from'  => 'google',
                    'social_id'  => $socialid,
                    'mac' => \Input::get('mac_address', ''),
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'is_captive' => 'yes',
                    'recognized' => $recognized
                ];

                $orbit_origin = \Input::get('orbit_origin', 'google');
                $this->prepareSession();

                // There is a chance that user not 'grant' his email while approving our app
                // so we double check it here
                if (empty($userEmail)) {
                    return Redirect::to(Congig::get('orbit.shop.after_social_sign_in') . '?error=no_email');
                }

                $key = $this->getPayloadEncryptionKey();
                $payload = (new Encrypter($key))->encrypt(http_build_query($data));
                $query = ['payload' => $payload, 'email' => $userEmail, 'auto_login' => 'yes', 'isInProgress' => 'true'];
                if (\Input::get('from_captive') === 'yes') {
                    $query['from_captive'] = 'yes';
                }

                $expireTime = Config::get('orbit.session.session_origin.cookie.expire');

                setcookie('orbit_email', $userEmail, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                setcookie('orbit_firstname', $firstName, time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);
                setcookie('login_from', 'Google', time() + $expireTime, '/', Domain::getRootDomain('http://' . $_SERVER['HTTP_HOST']), FALSE, FALSE);

                // todo can we not do this directly
                return Redirect::to(Congig::get('orbit.shop.after_social_sign_in') . $query);

            } catch (Exception $e) {
                $errorMessage = 'Error: ' . $e->getMessage();
                return Redirect::to(Congig::get('orbit.shop.after_social_sign_in') . '?error=' . $errorMessage);
            }

        } else {
            try {
                // get googleService authorization
                $url = $googleService->getAuthorizationUri();
                return Redirect::to( (string)$url );
            } catch (Exception $e) {
                $errorMessage = 'Error: ' . $e->getMessage();
                return Redirect::to(Congig::get('orbit.shop.after_social_sign_in') . '?error=' . $errorMessage);
            }
        }   
    }
}
