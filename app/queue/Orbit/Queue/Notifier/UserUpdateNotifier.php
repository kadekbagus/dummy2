<?php namespace Orbit\Queue\Notifier;
/**
 * Process queue for notifying that there is event customer update happens.
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @todo 1. Delete job based on max try
 *       2. Email to sender about the failed job
 */
use Log;
use User;
use Config;
use Retailer;
use CurlWrapper;
use CurlWrapperCurlException;
use Exception;
use Validator;
use DB;
use Mall;
use Membership;
use MembershipNumber;

class UserUpdateNotifier
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
     *                      human_error => FALSE
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
        $notifyData = Config::get('orbit-notifier.user-update.' . $retailerId);

        // No need to proceed
        if (empty($notifyData)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] There is no user-update notify data found for retailer id %s.',
                                    $job->getJobId(), $retailerId)
            ];
        }

        if (! $notifyData['enabled']) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Notify user-update found for retailer id %s but it was disabled.',
                                    $job->getJobId(), $retailerId)
            ];
        }

        $url = $notifyData['url'];
        $message = sprintf('[Job ID: `%s`] Notify user-update User ID: `%s` to Retailer: `%s` URL: `%s` -> Success.',
                            $job->getJobId(), $userId, $retailerId, $url);

        try {
            $postData = [
                'user_id' => $user->user_id,
                'external_user_id' => $user->external_user_id,
                'user_email' => $user->user_email,
                'user_firstname' => $user->user_firstname,
                'user_lastname' => $user->user_lastname,
                'membership_number' => $user->membership_number,
                'membership_since' => $user->membership_since,
                'date_of_birth' => $user->userdetail->birthdate,
                'gender' => $user->userdetail->gender,
                'idcard' => $user->userdetail->idcard,
                'address_line1' => $user->userdetail->address_line1,
                'city' => $user->userdetail->city,
                'province' => $user->userdetail->province,
                'postal_code' => $user->userdetail->postal_code,
                'phone1' => $user->userdetail->phone,
                'phone2' => $user->userdetail->phone2,
                'phone3' => $user->userdetail->phone3,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];

            // Update the user object based on the return value of external system
            $insideTransactionFromNotifier = FALSE;

            if ($notifyData['auth_type'] === 'basic') {
                $this->poster->setAuthType();
                $this->poster->setAuthCredentials($notifyData['auth_user'], $notifyData['auth_password']);
            }

            $this->poster->addHeader('Accept', 'application/json');

            Log::info('Orbit Integration -- Update Member -- Post data: ' . serialize($postData));

            $this->poster->setUserAgent(Config::get('orbit-notifier.user-agent'));
            $this->poster->post($url, $postData);

            // Lets try to decode the body
            $httpBody = $this->poster->getResponse();
            Log::info('Orbit Integration -- Update Member -- External response: ' . $httpBody);

            // We are only interesting in 200 OK status
            $httpCode = $this->poster->getTransferInfo('http_code');
            if ((int)$httpCode !== 200) {
                $errorMessage = sprintf('External response: %s', $httpBody);
                throw new Exception($errorMessage);
            }

            $response = json_decode($httpBody);

            // Non-Zero code means an error
            if ((string)$response->code !== '0') {
                $errorMessage = sprintf('External response: %s', $response->message);
                if ($data['human_error']) {
                    $errorMessage = ! empty($response->message) ? $response->message : $errorMessage;
                }
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
            //         "user_email": "user@example.com",
            //         "membership_number": "98754221",
            //         "membership_since": "2015-02-05 11:22:00"
            //     }
            // }
            $validationData = [
                'user_id' => isset($response->data->user_id) ? $response->data->user_id : NULL,
                'user_email' => isset($response->data->user_email) ? $response->data->user_email : NULL,
                'membership_number' => isset($response->data->membership_number) ? $response->data->membership_number : NULL,
                'membership_since' => isset($response->data->membership_since) ? $response->data->membership_since : NULL
            ];
            $validationRule = [
                'user_id' => 'required|orbit.notify.same_id:' . $userId,
                'user_email' => 'required|email|orbit.notify.same_email:' . $user->user_email,
                'membership_number' => '',
                'membership_since' => ''
            ];

            $this->registerCustomValidation();
            $validator = Validator::make($validationData, $validationRule);
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }

            if (! DB::connection()->getPdo()->inTransaction()) {
                DB::connection()->getPdo()->beginTransaction();
                $insideTransactionFromNotifier = TRUE;
            }

            $doSave = FALSE;
            if (! empty($response->data->membership_number)) {
                // Check for the previous membership number, if it was empty assuming this is the first time
                // So activate the user
                if (empty($user->membership_number) && $user->status === 'pending') {
                    $user->status = 'active';
                }

                $user->membership_number = $response->data->membership_number;
                $user->membership_since = $response->data->membership_since;
                $user->save();

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
                    Log::info( sprintf('Orbit Integration -- Update Member -- Membership number not found for user %s not found, creating new one.', $user->user_id) );
                    $membershipNumber = new MembershipNumber();
                    $membershipNumber->user_id = $user->user_id;
                    $membershipNumber->issuer_merchant_id = $retailer->merchant_id;
                    $membershipNumber->membership_id = $card->membership_id;
                    $membershipNumber->status = MembershipNumber::STATUS_ACTIVE;
                }

                $membershipNumber->join_date = $response->data->membership_since;
                $membershipNumber->membership_number = $response->data->membership_number;
                $membershipNumber->save();
            }

            // Everything seems fine lets delete the job
            $job->delete();

            if (DB::connection()->getPdo()->inTransaction() && $insideTransactionFromNotifier === TRUE) {
                DB::connection()->getPdo()->commit();
            }

            Log::info('Orbit Integration -- Update Member -- Result: OK -- Message: ' . $message);
            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (CurlWrapperCurlException $e) {
            $message = sprintf('[Job ID: `%s`] Notify user-update User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s',
                                $job->getJobId(), $userId, $retailerId, $url, $e->getMessage());

            if (DB::connection()->getPdo()->inTransaction() && $insideTransactionFromNotifier === TRUE) {
                DB::connection()->getPdo()->rollBack();
            }

            Log::error($message);
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Notify user-update User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s',
                                $job->getJobId(), $userId, $retailerId, $url, $e->getMessage());

            if (DB::connection()->getPdo()->inTransaction() && $insideTransactionFromNotifier === TRUE) {
                DB::connection()->getPdo()->rollBack();
            }

            Log::error($message);

            if ($data['human_error']) {
                $message = $e->getMessage();
            }
        }

        $job->delete();
        Log::error(sprintf('UpdateMember Integration Error PostData: %s', serialize($postData)));

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