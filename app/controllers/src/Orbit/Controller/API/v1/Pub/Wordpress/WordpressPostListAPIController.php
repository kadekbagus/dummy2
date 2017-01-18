<?php namespace Orbit\Controller\API\v1\Pub\Wordpress;
/**
 * Controller for listing posts from Wordpress. This controller
 * uses Wordpress API that produced by WP Rest API v2.
 *
 * @author Rio Astamal <rio@dominopos.com>
 * @todo Make this as generic controller because its job only reading json
 *       file from a file.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Config;
use Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use stdClass;
use Mall;


class WordpressPostListAPIController extends PubControllerAPI
{
    /**
     * Flag for code that goes to Exception but mark it as OK
     * so it returns 200 instead of 500 error.
     *
     * @var boolean
     */
    protected $thrownButOK = FALSE;

    /**
     * Mall object
     *
     * @var Mall
     */
    protected $mall = FALSE;

    public function getPostList()
    {
        $httpCode = 200;
        $user = NULL;
        $this->setMallObject();

        try {
            $this->checkAuth();
            $user = $this->api->user;

            $jsonFile = Config::get('orbit.external_calls.wordpress.cache_file');
            $totalRec = 0;
            $listOfRec = [];
            $message = 'Request OK';

            if (! file_exists($jsonFile)) {
                $this->thrownButOK = TRUE;
                throw new Exception('Wordpress JSON file is not found, no data returned');
            }

            if (is_null($listOfRec = json_decode(file_get_contents($jsonFile)))) {
                $this->thrownButOK = TRUE;
                throw new Exception('Can not parse JSON file from Wordpress');
            }

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = count($listOfRec);
            $data->records = $listOfRec;

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = $message;

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->setEmptyResponseData();
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->setEmptyResponseData();
            $httpCode = 400;
        } catch (Exception $e) {
            $this->response->message = $e->getMessage();
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $httpCode = 500;

            if ($this->thrownButOK) {
                $this->response->code = 0;
                $this->response->status = 'OK';
                $httpCode = 200;
            }
            $this->setEmptyResponseData();
        }

        return $this->render($httpCode);
    }

    protected function setEmptyResponseData()
    {
        $data = new \stdclass();
        $data->returned_records = 0;
        $data->total_records = 0;
        $data->records = [];

        $this->response->data = $data;
    }

    protected function setMallObject()
    {
        $me = $this;
        OrbitInput::get('mall_id', function($mallId) use ($me) {
            $me->mall = Mall::excludeDeleted()->where('merchant_id', $mallId)->first();
        });
    }
}