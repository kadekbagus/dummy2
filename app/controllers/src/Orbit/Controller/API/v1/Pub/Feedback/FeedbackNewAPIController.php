<?php namespace Orbit\Controller\API\v1\Pub\Feedback;

use Carbon\Carbon as Carbon;
use Config;
use Exception;
use OrbitShop\API\v1\PubControllerAPI;
use Orbit\Controller\API\v1\Pub\Feedback\Request\CreateRequest;
use Orbit\Notifications\Feedback\MallFeedbackNotification;
use Orbit\Notifications\Feedback\StoreFeedbackNotification;
use User;

class FeedbackNewAPIController extends PubControllerAPI
{
    /**
     * POST - New feedback report for Mall/Store.
     *
     * @param CreateRequest $request create new feedback request
     *
     * @return Illuminate\Support\Facades\Response
     *
     * @author Budi <budi@dominopos.com>
     */
    public function postNewFeedback(CreateRequest $request)
    {
        $httpCode = 200;

        try {

            $csEmails = Config::get('orbit.feedback.cs_email', ['cs@gotomalls.com']);
            $csEmails = ! is_array($csEmails) ? explode(',', $csEmails) : $csEmails;

            $notifData = array_merge(
                $request->getData(),
                [
                    'user' => $request->user()->fullName,
                    'date' => Carbon::now()->format('d F Y'),
                ]
            );

            if ($request->is_mall === 'Y') {
                (new MallFeedbackNotification($csEmails, $notifData))->send();
            }
            else {
                (new StoreFeedbackNotification($csEmails, $notifData))->send();
            }

        } catch (Exception $e) {
            return $this->handleException($e, false);
        }

        return $this->render($httpCode);
    }
}
