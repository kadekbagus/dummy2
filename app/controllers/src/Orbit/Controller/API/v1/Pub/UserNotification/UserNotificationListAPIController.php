<?php namespace Orbit\Controller\API\v1\Pub\UserNotification;

/**
 * An API controller for getting generic activity.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Config;
use stdClass;
use DB;
use Validator;
use Carbon\Carbon as Carbon;
use Orbit\Helper\MongoDB\Client as MongoClient;
use Orbit\Helper\Util\PaginationNumber;

class UserNotificationListAPIController extends PubControllerAPI
{
    /**
     * post - create user notificatin
     *
     * @author shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string notification_token
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getUserNotificationList()
    {
        $httpCode = 200;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $take = PaginationNumber::parseTakeFromGet('news');
            $skip = PaginationNumber::parseSkipFromGet();

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);

            $updateQueryString = [
                'user_id' => $user->user_id
            ];

            $bodyUpdate = [
                'is_viewed' => true
            ];

            $response = $mongoClient->setQueryString($updateQueryString)
                                    ->setFormParam($bodyUpdate)
                                    ->setEndPoint('user-notifications') // express endpoint
                                    ->request('PUT');

            $queryString = [
                'take'       => $take,
                'skip'       => $skip,
                'user_id'    => $user->user_id,
                'multi_sort' => [['is_read', 'asc'], ['created_at', 'desc']]
            ];

            $response = $mongoClient->setQueryString($queryString)
                                    ->setEndPoint('user-notifications')
                                    ->request('GET');
            $listOfRec = $response->data;

            $data = new \stdclass();
            $data->returned_records = $listOfRec->returned_records;
            $data->total_records = $listOfRec->total_records;
            $data->records = $listOfRec->records;

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

        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

        } catch (QueryException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

        } catch (Exception $e) {

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }
}