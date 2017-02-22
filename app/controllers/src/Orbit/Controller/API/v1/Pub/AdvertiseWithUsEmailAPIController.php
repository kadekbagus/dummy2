<?php namespace Orbit\Controller\API\v1\Pub;

/**
 * An API controller for sending email on advertise with us.
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
use \Queue;

class AdvertiseWithUsEmailAPIController extends PubControllerAPI
{
    /**
     * post - send advertise with us email
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string advertise_as   (optional)
     * @param string name           (required)
     * @param string email          (required)
     * @param string phone_number   (optional)
     * @param string message        (optional)
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postAdvertiseWithUsEmail()
    {
        $httpCode = 200;

        try {
            $advertise_as = OrbitInput::post('advertise_as');
            $first_name = OrbitInput::post('first_name');
            $last_name = OrbitInput::post('last_name');
            $email = OrbitInput::post('email');
            $phone_number = OrbitInput::post('phone_number');
            $message = OrbitInput::post('message');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'advertise_as'  => $advertise_as,
                    'first_name'    => $first_name,
                    'email'         => $email,
                    'phone_number'  => $phone_number,
                ),
                array(
                    'advertise_as'  => 'required',
                    'first_name'    => 'required',
                    'email'         => 'required|email',
                    'phone_number'  => 'required'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // send the email via queue
            Queue::push('Orbit\\Queue\\AdvertiseWithUsMail', [
                'advertise_as'      => $advertise_as,
                'first_name'        => $first_name,
                'last_name'         => $last_name,
                'email'             => $email,
                'phone_number'      => $phone_number,
                'advertise_message' => $message,
            ]);

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