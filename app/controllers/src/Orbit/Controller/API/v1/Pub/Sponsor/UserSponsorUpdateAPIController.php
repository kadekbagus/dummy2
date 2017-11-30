<?php namespace Orbit\Controller\API\v1\Pub\Sponsor;
/**
 * An API controller for managing list of sponsor.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenExceptio;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use UserSponsor;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use Elasticsearch\ClientBuilder;
use Language;
use DB;
use Validator;

class UserSponsorUpdateAPIController extends PubControllerAPI
{

    /**
     * GET - Get user credit card list
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string area
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUserSponsor()
    {
      $httpCode = 200;
        try {
            $this->checkAuth();
            $user = $this->api->user;

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $prefix = DB::getTablePrefix();
            $sponsorIds = OrbitInput::post('sponsor_ids');
            $sponsorType = OrbitInput::post('sponsor_type');
            $userId = $user->user_id;

            $validator = Validator::make(
                array(
                    'sponsor_id' => $sponsorIds,
                    'sponsor_type' => $sponsorType
                ),
                array(
                    'sponsor_id' => 'required',
                    'sponsor_type' => 'required'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $sponsorIds = @json_decode($sponsorIds);
            if (json_last_error() != JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument('JSON sponsor is not valid');
            }

            // delete existing user sponsor
            $deleteSponsor = UserSponsor::where('user_id', $userId)
                                        ->where('sponsor_type', $sponsorType)
                                        ->delete(true);

            // insert new
            foreach ($sponsorIds as $sponsorId) {
              $userSponsor = new UserSponsor();
              $userSponsor->user_id = $userId;
              $userSponsor->sponsor_id = $sponsorId;
              $userSponsor->sponsor_type = $sponsorType;
              $userSponsor->save();
            }

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
            $this->response->data = null;
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
        } catch (\Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }
}