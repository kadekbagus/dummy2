<?php namespace Orbit\Queue\Notifier;
/**
 * Process queue for notifying that there is event login happens.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Log;
use User;
use Mall;
use Config;
use Retailer;
use CurlWrapper;
use CurlWrapperCurlException;
use Exception;
use Validator;
use DB;
use Membership;
use MembershipNumber;

class UserLoginNotifier
{
    /**
     * Poster. The object which post the data to external system.
     *
     * @var poster.
     */
    protected $poster = NULL;

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
        $retailer = Mall::excludeDeleted()->find($retailerId);

        // Get list of URL which can be notified
        $notifyData = Config::get('orbit-notifier.user-login.' . $retailerId);

        // No need to proceed
        if (empty($notifyData)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] There is no user-login notify data found for retailer id %s.',
                                    $job->getJobId(), $retailerId)
            ];
        }

        if (! $notifyData['enabled']) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Notify user-login found for retailer id %s but it was disabled.',
                                    $job->getJobId(), $retailerId)
            ];
        }

        $url = $notifyData['url'];
        $message = sprintf('[Job ID: `%s`] Notify user-login User ID: `%s` to Retailer: `%s` URL: `%s` -> Success.',
                            $job->getJobId(), $userId, $retailerId, $url);

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

            Log::info('Orbit Integration -- Check Member -- Post Data: ' . serialize($postData));

            $this->poster->addHeader('Accept', 'application/json');

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
            Log::info('Orbit Integration -- Check Member -- External response: ' . $httpBody);

            $response = json_decode($httpBody);

            // Non-Zero code means an error
            if ((string)$response->code !== '0') {
                $errorMessage = sprintf('Unexpected response code %s, expected 0 (zero).', $response->code);
                throw new Exception($errorMessage);
            }

            // If the data is null then no need to continue the external system
            // does not have the email address and not intented to update membership number
            if (is_null($response->data)) {
                $message .= ' Message: No data returned.';

                // Delete the job we don't interested any more
                $job->delete();

                return [
                    'status' => 'ok',
                    'message' => $message
                ];
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
                'user_firstname' => $response->data->user_firstname,
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

            $this->registerCustomValidation();
            $validator = Validator::make($validationData, $validationRule);
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }

            // Update the user object based on the return value of external system
            $insideTransactionFromNotifier = FALSE;

            if (! DB::connection()->getPdo()->inTransaction()) {
                DB::connection()->getPdo()->beginTransaction();
                $insideTransactionFromNotifier = TRUE;
            }

            // Check for the previous membership number, if it was empty assuming this is the first time
            // So activate the user
            if (empty($user->membership_number) && $user->status === 'pending') {
                $user->status = 'active';
            }

            // Note
            // ----
            // Update the membership number on membership_numbers table
            // As of v1.3 - v2.0 there is only one membership card per mall
            // so we only select the first active membership number
            // Once we implements multiple membership card this routine code
            // SHOULD be updated
            $card = Membership::active()->where('merchant_id', $retailer->merchant_id)->first();
            if (! is_object($card)) {
                $errorMessage = sprintf('Can not find membership card for mall or retailer: %s.', $retailer->name);
                throw new Exception($errorMessage);
            }

            $membershipNumber = MembershipNumber::active()
                                                ->where('user_id', $user->user_id)
                                                ->where('issuer_merchant_id', $retailer->merchant_id)
                                                ->where('membership_id', $card->membership_id)
                                                ->first();
            // Create new membership number if not exists
            if (! is_object($membershipNumber)) {
                Log::info( sprintf('Orbit Integration -- Check Member -- WARNING: Membership number not found for user %s not found, creating new one.', $user->user_id) );
                $membershipNumber = new MembershipNumber();
                $membershipNumber->user_id = $user->user_id;
                $membershipNumber->issuer_merchant_id = $retailer->merchant_id;
                $membershipNumber->membership_id = $card->membership_id;
                $membershipNumber->status = MembershipNumber::STATUS_ACTIVE;
            }

            $membershipNumber->join_date = $response->data->membership_since;
            $membershipNumber->membership_number = $response->data->membership_number;
            $membershipNumber->save();

            $user->external_user_id = $response->data->external_user_id;
            $user->user_firstname = $response->data->user_firstname;
            $user->user_lastname = $response->data->user_lastname;
            $user->membership_number = $response->data->membership_number;
            $user->membership_since = $response->data->membership_since;
            $user->save();

            // Everything seems fine lets delete the job
            $job->delete();

            if (DB::connection()->getPdo()->inTransaction() && $insideTransactionFromNotifier === TRUE) {
                DB::connection()->getPdo()->commit();
            }

            Log::info('Orbit Integration -- Check Member -- Result: OK -- Message: ' . $message);
            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (CurlWrapperCurlException $e) {
            $message = sprintf('[Job ID: `%s`] Notify user-login User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s',
                                $job->getJobId(), $userId, $retailerId, $url, $e->getMessage());

            if (DB::connection()->getPdo()->inTransaction()) {
                DB::connection()->getPdo()->rollBack();
            }
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Notify user-login User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s',
                                $job->getJobId(), $userId, $retailerId, $url, $e->getMessage());

            if (DB::connection()->getPdo()->inTransaction()) {
                DB::connection()->getPdo()->rollBack();
            }
        }

        $job->delete();
        Log::error(sprintf('Orbit Integration -- Check Member -- Result: ERROR -- Message: %s', $message));

        return [
            'status' => 'fail',
            'message' => $message
        ];
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