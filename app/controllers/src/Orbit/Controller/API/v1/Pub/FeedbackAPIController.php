<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing feedback.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use Config;
use Validator;
use Activity;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Session\UserGetter;

class FeedbackAPIController extends ControllerAPI
{
    /**
     * POST - send feedback
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string feedback
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postSendFeedback()
    {
        $activity = Activity::mobileci()
                        ->setActivityType('click');

        $user = NULL;
        $httpCode = 200;

        try {
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            $feedback = OrbitInput::post('feedback');
            $cs_email = Config::get('orbit.contact_information.customer_service.email');

            $validator = Validator::make(
                array(
                    'feedback' => $feedback,
                    'cs_email' => $cs_email,
                ),
                array(
                    'feedback' => 'required',
                    'cs_email' => 'required',
                ),
                array(
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Send email process to the queue
            \Queue::push('Orbit\\Queue\\FeedbackMail', [
                'user_email' => $user->user_email,
                'cs_email'   => $cs_email,
                'feedback'   => $feedback,
            ]);

            $activity->setUser($user)
                     ->setActivityName('submit_feedback')
                     ->setActivityNameLong('Submit Feedback')
                     ->setModuleName('Application')
                     ->responseOK()
                     ->save();

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

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {

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
