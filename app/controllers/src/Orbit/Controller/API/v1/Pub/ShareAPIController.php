<?php namespace Orbit\Controller\API\v1\Pub;

/**
 * An API controller for share gotomalls landing page via email
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
use Validator;
use Event;

class ShareAPIController extends PubControllerAPI
{
    /**
     * post - called when user share somthing via AddThis
     *
     * @author Zamroni <zamroni@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string service name of share provider, 'facebook', 'lineme', etc
     * @param string object_id, object id
     * @param string object_type, object type
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postShare()
    {

        $httpCode = 200;
        try {

            $user = $this->getUser();

            $service = OrbitInput::post('service');
            $objectId = OrbitInput::post('object_id');
            $objectType = OrbitInput::post('object_type');
            $language = OrbitInput::get('language', 'id');

            $validator = Validator::make(
                [
                    'service'     => $service,
                    'object_id'     => $objectId,
                    'object_type'     => $objectType,
                ],
                [
                    'service'     => 'alpha_num',
                    'object_id'     => 'alpha_dash',
                    'object_type'     => 'alpha_num',
                ]
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $body = [
                'object_type' => $objectType,
                'object_id' => $objectId,
                'object_name' => $service,
            ];

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
            $this->response->data = null;

            Event::fire('orbit.share.post.success', [$user, $body]);

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
