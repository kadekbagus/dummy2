<?php namespace Orbit\Controller\API\v1\Pub\Sponsor;
/**
 * An API controller for save allowed user notification by cities of cc/wallet user choosen
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
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use Elasticsearch\ClientBuilder;
use DB;
use Validator;
use MallCity;
use UserSponsorAllowedNotification;
use UserSponsorAllowedNotificationCities;
use Carbon\Carbon as Carbon;

class UserSponsorAllowedNotificationCitiesNewAPIController extends PubControllerAPI
{

    /**
     * GET - Save allowed user notification by cities of cc/wallet user choosen
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string country
     * @param array cities
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewUserSponsorAllowedNotificationCities()
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

            $cities = OrbitInput::post('cities', []);
            $userId = $user->user_id;

            $validator = Validator::make(
                array(
                    'cities' => $cities
                ),
                array(
                    'cities' => 'required'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Get mall_cities_id with IN query
            $mallCities = MallCity::whereIn('city', $cities)->get();

            if (! $mallCities->isEmpty()) {

                // Delete old data
                $deleteUserSponsorAllowedNotificationCities = UserSponsorAllowedNotificationCities::where('user_id', $userId)
                                                                    ->delete(true);
                $deleteUserSponsorAllowedNotification = UserSponsorAllowedNotification::where('user_id', $userId)
                                                                    ->delete(true);

                // Insert new data (bulk insert) to userSponsorAllowedNotificationCities and UserSponsorAllowedNotification
                $records = [];
                foreach ($mallCities as $key => $value) {
                    $records []= [
                        'user_id' => $userId,
                        'mall_city_id' => $value->mall_city_id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ];
                }
                userSponsorAllowedNotificationCities::insert($records);

                $recordsAllowed []= [
                    'user_id' => $userId,
                    'is_notification_allowed' => 'Y',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ];
                UserSponsorAllowedNotification::insert($recordsAllowed);
            }

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
            $this->response->data = $records;
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
