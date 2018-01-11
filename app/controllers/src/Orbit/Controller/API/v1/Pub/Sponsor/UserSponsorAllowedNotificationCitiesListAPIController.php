<?php namespace Orbit\Controller\API\v1\Pub\Sponsor;
/**
 * An API controller for managing list of city user notification in my cc/wallet.
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
use UserSponsorAllowedNotificationCities;

class UserSponsorAllowedNotificationCitiesListAPIController extends PubControllerAPI
{

    /**
     * GET - List of city user notification in my cc/wallet.
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param -
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getUserSponsorAllowedNotificationCities()
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

            $userSponsorAllowedNotificationCities = UserSponsorAllowedNotificationCities::select('city', 'countries.name as country_name')
                                                            ->join('mall_cities', 'mall_cities.mall_city_id', '=', 'user_sponsor_allowed_notification_cities.mall_city_id')
                                                            ->join('countries', 'countries.country_id', '=', 'mall_cities.country_id')
                                                            ->where('user_id', $userId);

            $_userSponsorAllowedNotificationCities = $userSponsorAllowedNotificationCities;

            $take = PaginationNumber::parseTakeFromGet('category');
            $userSponsorAllowedNotificationCities->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $userSponsorAllowedNotificationCities->skip($skip);

            $listData = $userSponsorAllowedNotificationCities->get();

            $count = count($_userSponsorAllowedNotificationCities->get());
            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listData);
            $this->response->data->records = $listData;
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
