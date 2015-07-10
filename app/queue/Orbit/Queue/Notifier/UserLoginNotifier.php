<?php namespace Orbit\Queue\Notifier;
/**
 * Process queue for notifying that there is event login happens.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Log;
use User;
use Config;
use Retailer;
use CurlWrapper;
use CurlWrapperCurlException;

class UserLoginNotifier
{
    /**
     * Poster. The object which post the data to external system.
     *
     * @var poster.
     */
    $poster = NULL;

    /**
     * Class constructor.
     *
     * @param string $poster Object used to post the data.
     * @return void
     */
    public function __construct($poster = 'default')
    {
        if ($poster === 'default') {
            $this->poster = new CurlWrapper();
        } else {
            $this->poster = $poster;
        }
    }

    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Job $job
     * @param array $data [
     *                      user_id => NUM,
     *                      retailer_id => NUM,
     * ]
     * @return void
     * @todo Make this testable
     */
    public function fire($job, $data)
    {
        $userId = $data['user_id'];
        $retailerId = $data['retailer_id'];

        $user = User::excludeDeleted()->find($userId);
        $retailer = Retailer::excludeDeleted()->find($retailerId);

        // Get list of URL which can be notified
        $notifyData = Config::get('orbit-notifier.user-login.' . $retailerId);

        // No need to proceed
        if (empty($notifyData)) {
            $job->delete();
        }

        if (! $notifyData['enabled']) {
            $job->delete();
        }

        $url = $notifyData['url'];
        $message = sprintf('Notify post User ID: `%s` to Retailer: `%s` URL: `%s` -> Success.', $userId, $retailerId, $url);

        try {
            $postData = [
                'user_id' => $user->user_id,
                'user_email' => $user->user_email,
                'created_at' => $user->created_at
            ];

            if ($notifyData['auth_type'] === 'basic') {
                $this->poster->setAuthType();
                $this->poster->setAuthCredentials($notifyData['auth_user'], $notifyData['auth_password']);
            }

            $this->poster->setUserAgent(Config::get('orbit-notifier.user-agent'));
            $this->poster->post($url, $postData);

            // We are only interesting in 200 OK status
            $httpCode = $this->poster->getTransferInfo('http_code');
            if ((int)$httpCode !== 200) {
                $errorMessage = sprintf('Unexpected http response code %s, expected 200.', $httpCode);
                throw new Exception($errorMessage);
            }

            // Lets try to decode the body
            $httpBody = $this->poster->getResponse();
            $response = json_decode($httpBody);

            // Non-Zero code means an error
            if ((string)$response->code !== '0') {
                throw new Exception('Unexpected response code %s, expected 0 (zero).');
            }

            // Try to check the existence of expected field.
            // {
            //     "code": 0,
            //     "status": "success",
            //     "message": "Some message",
            //     "data": {
            //         "user_id": 10,
            //         "external_user_id": "C99",
            //         "user_email": "user@example.com",
            //         "user_firstname": "John",
            //         "user_lastname": "Doe",
            //         "membership_number": "98754221",
            //         "membership_since": "2015-02-05 11:22:00"
            //     }
            // }
            $validationData = [
                'user_id' => $response->data->user_id,
                'external_user_id' => $response->data->external_user_id,
                'user_email' => $response->data->user_email,
                'user_firstname' => $response->data->user_fistname,
                'user_lastname' => $response->data->user_lastname,
                'membership_number' => $response->data->membership_number,
                'membership_since' => $response->data->membership_since
            ];
            $validationRule = [
                'user_id' => 'required|orbit.notify.same_id:' . $userId,
                'external_user_id' => 'required',
                'user_email' => 'required|email|orbit.notify.same_email:' . $user->user_email,
                'user_firstname' => 'required',
                'user_lastname' => '',
                'membership_number' => 'required',
                'membership_since' => 'required'
            ];
            $validator = Validator::make($validationData, $validationRule);

            // Everything seems fine lets delete the job
            $job->delete();

            Log::info($message);
        } catch (CurlWrapperCurlException $e) {
            $message = sprintf('Notify post User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s.', $userId, $retailerId, $url, $e->getMessage());

            Log::error($message);
        } catch (Exception $e) {
            $message = sprintf('Notify post User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s.', $userId, $retailerId, $url, $e->getMessage());

            Log::error($message);
        }
    }

    /**
     * Register custom validation.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    protected function registerCustomValidation()
    {
        // Data user id got from external must match with the one we were sent
        Validator::extend('orbit.notify.same_id', function($attribute, $value, $parameters)
        {
            if ((string)$value !== (string)$parameters[0]) {
                $errorMessage = sprintf('User Id is not same, expected %s got %s.', $parameters[0], $value);
                throw new Exception ($errorMessage);
            }

            return TRUE;
        });

        // Data email addr got from external must match with the one we were sent
        Validator::extend('orbit.notify.same_email', function($attribute, $value, $parameters)
        {
            if ((string)$value !== (string)$parameters[0]) {
                $errorMessage = sprintf('Email address is not same, expected %s got %s.', $parameters[0], $value);
                throw new Exception ($errorMessage);
            }

            return TRUE;
        });
    }
}