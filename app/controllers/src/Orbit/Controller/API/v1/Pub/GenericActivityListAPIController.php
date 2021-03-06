<?php namespace Orbit\Controller\API\v1\Pub;

/**
 * An API controller for getting generic activity.
 */
use IntermediateBaseController;
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Config;
use stdClass;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;

class GenericActivityListAPIController extends IntermediateBaseController
{
    /**
     * GET - get generic activity list
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getGenericActivityList()
    {
        $this->response = new ResponseProvider();
        $httpCode = 200;
        try {

            $genAct = Config::get('orbit.generic_activity.activity_list');
            $paramName = Config::get('orbit.generic_activity.parameter_name');

            // check for empty config
            if (empty($genAct)) {
                OrbitShopAPI::throwInvalidArgument('Generic Activity is empty');
            }

            $lastKey = 0;
            foreach($genAct as $key => $value)
            {
                $act[camel_case($value['name']).$key] = array(
                        'value' => $key,
                        'objectParams' => $value['parameter_name'],
                        'objectTypeParams' => isset($value['object_type_parameter_name']) ? $value['object_type_parameter_name'] : null
                    );
                $arr[$key] = $act;
                $lastKey = $key;
            }

            $data = new stdClass();
            $data->parameter_name = $paramName;
            $data->activity_list = $arr[$lastKey];

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
            $this->response->data = $data;

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

        } catch (\Exception $e) {

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($this->response);
    }
}