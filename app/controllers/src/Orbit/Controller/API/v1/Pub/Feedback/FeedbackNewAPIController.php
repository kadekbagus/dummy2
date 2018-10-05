<?php namespace Orbit\Controller\API\v1\Pub\Feedback;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Config;
use stdClass;
use Validator;
use Lang;
use \Exception;
use Activity;
use User;
use Orbit\Helper\Security\Encrypter;
use \Orbit\Helper\Exception\OrbitCustomException;
use Carbon\Carbon as Carbon;

use Orbit\Notifications\Feedback\MallFeedbackNotification;
use Orbit\Notifications\Feedback\StoreFeedbackNotification;

class FeedbackNewAPIController extends PubControllerAPI
{
    /**
     * POST - New feedback report for Mall/Store.
     *
     * @param string store
     * @param string mall
     * @param string report
     * @param string is_mall
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Budi <budi@dominopos.com>
     */
    public function postNewFeedback()
    {
        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You must login to access this.';
                ACL::throwAccessForbidden($message);
            }

            $feedback = [];
            $feedback['store'] = OrbitInput::post('store');
            $feedback['mall'] = OrbitInput::post('mall');
            $feedback['report'] = OrbitInput::post('report');
            $feedback['is_mall'] = OrbitInput::post('is_mall', 'Y');

            $validator = Validator::make($feedback, [
                'mall'      => 'required',
                'report'    => 'required',
                'is_mall'   => 'required',
            ]);

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $feedback['user'] = $user->user_firstname . ' ' . $user->user_lastname;
            $feedback['email'] = $user->email;
            $feedback['date'] = Carbon::now()->format('d F Y');

            $cs = new User;
            $cs->email = Config::get('orbit.contact_information.customer_service.email', 'cs@gotomalls.com');

            if ($feedback['is_mall'] === 'Y') {
                $cs->notify(new MallFeedbackNotification($feedback));
            }
            else {
                $cs->notify(new StoreFeedbackNotification($feedback));
            }

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
            $this->rollback();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = $e->getLine();
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getFile();
        }

        return $this->render();
    }
}
