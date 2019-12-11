<?php namespace Orbit\Controller\API\v1\Pub\Feedback;

use Carbon\Carbon as Carbon;
use Config;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Feedback\Request\NewFeedbackRequest;
use Orbit\Notifications\Feedback\MallFeedbackNotification;
use Orbit\Notifications\Feedback\StoreFeedbackNotification;
use User;

class FeedbackNewAPIController extends PubControllerAPI
{
    /**
     * POST - New feedback report for Mall/Store.
     *
     * @param string store the store name that being reported.
     * @param string mall the mall name that being reported.
     * @param string report the report message.
     * @param string is_mall an indicator if the report is for mall or not.
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Budi <budi@dominopos.com>
     */
    public function postNewFeedback()
    {
        $httpCode = 200;

        try {
            with($feedbackRequest = new NewFeedbackRequest($this))->validate();

            $csEmails = Config::get('orbit.feedback.cs_email', ['cs@gotomalls.com']);
            $csEmails = ! is_array($csEmails) ? [$csEmails] : $csEmails;

            foreach($csEmails as $email) {
                $cs = new User;
                $cs->email = $email;

                if ($feedbackRequest->is_mall === 'Y') {
                    $cs->notify(new MallFeedbackNotification($feedbackRequest->getDataAfterValidation()));
                }
                else {
                    $cs->notify(new StoreFeedbackNotification($feedbackRequest->getDataAfterValidation()));
                }
            }

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
