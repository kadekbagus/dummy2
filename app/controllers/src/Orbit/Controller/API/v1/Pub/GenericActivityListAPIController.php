<?php namespace Orbit\Controller\API\v1\Pub;

/**
 * An API controller for getting generic activity.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Config;
use stdClass;
use OrbitShop\API\v1\ResponseProvider;

class GenericActivityListAPIController extends ControllerAPI
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

        $httpCode = 200;
        $this->response = new ResponseProvider();
    	try {

    		$genAct = Config::get('orbit.generic_activity.activity_list');

    		// check for empty config
    		if (empty($genAct)) {
    			OrbitShopAPI::throwInvalidArgument('Generic Activity is empty');
    		}

    		foreach($genAct as $key => $value)
			{
				$act[implode('',array_map('ucfirst',explode('_', $value['name'])))][] = array('value' => $key, 'objectParams' => $value['parameter_name']);
				$arr[$key] = $act;
			}

			$tot = count($arr);

			$this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
            $this->response->data = $arr[$tot];

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