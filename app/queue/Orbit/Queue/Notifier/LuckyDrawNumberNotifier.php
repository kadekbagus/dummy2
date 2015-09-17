<?php namespace Orbit\Queue\Notifier;
/**
 * Process queue for notifying that there is event receipt submission happens.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Log;
use User;
use Config;
use Retailer;
use LuckyDraw;
use LuckyDrawReceipt;
use CurlWrapper;
use CurlWrapperCurlException;
use Exception;
use Validator;
use DB;
use LuckyDrawNumberAPIController;
use OrbitShop\API\v1\Helper\Generator;

class LuckyDrawNumberNotifier
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
     *                      lucky_draw_id => NUM,
     *                      retailer_id => NUM,
     *                      hash => STRING
     * ]
     * @return void
     * @todo Make this testable
     */
    public function fire($job, $data)
    {
        $hash = $data['hash'];
        $luckyDrawId = $data['lucky_draw_id'];
        $userId = $data['user_id'];
        $retailerId = $data['retailer_id'];

        $user = User::excludeDeleted()->find($userId);
        $retailer = Retailer::excludeDeleted()->find($retailerId);
        $luckyDraw = LuckyDraw::excludeDeleted()->find($luckyDrawId);
        $receipts = LuckyDrawReceipt::excludeDeleted()
                                    ->where('user_id', $user->user_id)
                                    ->where('receipt_group', $hash)
                                    ->get();

        // Get list of URL which can be notified
        $notifyData = Config::get('orbit-notifier.lucky-draw-number.' . $retailerId);

        // No need to proceed
        if (empty($notifyData)) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] There is no lucky-draw-number notify data found for retailer id %s.',
                                    $job->getJobId(), $retailerId)
            ];
        }

        if (! $notifyData['enabled']) {
            $job->delete();

            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] Notify lucky-draw-number found for retailer id %s but it was disabled.',
                                    $job->getJobId(), $retailerId)
            ];
        }

        $url = $notifyData['url'];
        $message = sprintf('[Job ID: `%s`] Notify lucky-draw-number User ID: `%s` to Retailer: `%s` URL: `%s` -> Success.',
                            $job->getJobId(), $userId, $retailerId, $url);

        try {
            $this->removeUnusedAttributes($receipts);

            // Transform to JSON
            $jsonReceipts = json_encode($receipts);

            $postData = [
                'user_id' => $user->user_id,
                'external_user_id' => $user->external_user_id,
                'lucky_draw_id' => $luckyDraw->lucky_draw_id,
                'external_lucky_draw_id' => $luckyDraw->external_lucky_draw_id,
                'membership_number' => $user->membership_number,
                'receipt_group' => $hash,
                'receipts' => $jsonReceipts,
            ];

            if ($notifyData['auth_type'] === 'basic') {
                $this->poster->setAuthType();
                $this->poster->setAuthCredentials($notifyData['auth_user'], $notifyData['auth_password']);
            }

            $this->poster->addHeader('Accept', 'application/json');

            Log::info('Post data: ' . $postData);

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
            Log::info('External response: ' . $httpBody);

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
            //         "lucky_draw_id": 1,
            //         "receipt_group: "abcdefghij123456",
            //         "lucky_draw_number_start": 1005,
            //         "lucky_draw_number_end": 1008,
            //     }
            // }
            $validationData = [
                'lucky_draw_id' => (property_exists($response->data, 'lucky_draw_id') ? $response->data->lucky_draw_id : NULL),
                'receipt_group' => (property_exists($response->data, 'receipt_group') ? $response->data->receipt_group : NULL),
                'lucky_draw_number_start' => (property_exists($response->data, 'lucky_draw_number_start') ? $response->data->lucky_draw_number_start : NULL),
                'lucky_draw_number_end' => (property_exists($response->data, 'lucky_draw_number_end') ? $response->data->lucky_draw_number_end : NULL)
            ];
            $validationRule = [
                'lucky_draw_id' => 'required|orbit.notify.same_lucky_draw_id:' . $luckyDraw->lucky_draw_id,
                'receipt_group' => 'required|orbit.notify.same_receipt_group:' . $hash,
                'lucky_draw_number_start' => 'required|numeric',
                'lucky_draw_number_end' => 'required|numeric'
            ];

            $this->registerCustomValidation();
            $validator = Validator::make($validationData, $validationRule);
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }

            // Update the user object based on the return value of external system
            DB::connection()->getPdo()->beginTransaction();

            // Make sure it popup on user mobile phone
            $_POST['popup'] = 'yes';

            // Prepare another post data
            $_POST['user_id'] = $userId;
            $_POST['lucky_draw_id'] = $luckyDraw->lucky_draw_id;
            $_POST['lucky_draw_number_start'] = $response->data->lucky_draw_number_start;
            $_POST['lucky_draw_number_end'] = $response->data->lucky_draw_number_end;
            $_POST['receipts'] = $jsonReceipts;
            $_POST['mall_id'] = $receipts[0]->mall_id;

            // Call our internal API to insert the lucky draw number range
            $this->prepareAPIKey(User::find($retailer->user_id));

            $luckyDrawNumberAPI = LuckyDrawNumberAPIController::create('raw')->setUseTransaction(FALSE);
            $apiResponse = $luckyDrawNumberAPI->postNewLuckyDrawNumber();

            if ($apiResponse->code !== 0) {
                throw new Exception($apiResponse->message, $apiResponse->code);
            }

            // Everything seems fine lets delete the job
            $job->delete();

            DB::connection()->getPdo()->commit();

            Log::info($message);
            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (CurlWrapperCurlException $e) {
            $message = sprintf('[Job ID: `%s`] Notify lucky-draw-number User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s',
                                $job->getJobId(), $userId, $retailerId, $url, $e->getMessage());

            if (DB::connection()->getPdo()->inTransaction()) {
                DB::connection()->getPdo()->rollBack();
            }

            // Release the job back and give some delay
            $job->release((int)$notifyData['release_time']);

            Log::error($message);
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Notify lucky-draw-number User ID: `%s` to Retailer: `%s` URL: `%s` -> Error. Message: %s',
                                $job->getJobId(), $userId, $retailerId, $url, $e->getMessage());

            if (DB::connection()->getPdo()->inTransaction()) {
                DB::connection()->getPdo()->rollBack();
            }

            // Release the job back and give some delay
            $job->release((int)$notifyData['release_time']);

            Log::error($message);
        }

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
        // Data lucky draw id got from external must match with the one we were sent
        Validator::extend('orbit.notify.same_lucky_draw_id', function($attribute, $value, $parameters)
        {
            if ((string)$value !== (string)$parameters[0]) {
                $errorMessage = sprintf('Lucky Draw Id is not same, expected %s got %s.', $parameters[0], $value);
                throw new Exception ($errorMessage);
            }

            return TRUE;
        });

        // Data receipt group we got from external must match with the one we were sent
        Validator::extend('orbit.notify.same_receipt_group', function($attribute, $value, $parameters)
        {
            if ((string)$value !== (string)$parameters[0]) {
                $errorMessage = sprintf('Receipt group is not same, expected %s got %s.', $parameters[0], $value);
                throw new Exception ($errorMessage);
            }

            return TRUE;
        });
    }

    /**
     * Remove some unused attributes on receipts
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array &$receipts - List of Receipt object
     * @return void
     */
    protected function removeUnusedAttributes(&$receipts)
    {
        $unused = [
            'mall_id',
            'user_id',
            'object_type',
            'status',
            'created_by',
            'modified_by',
            'created_at',
            'updated_at'
        ];

        foreach ($receipts as &$receipt) {
            foreach ($unused as $tmp) {
                if (property_exists($receipt, $tmp)) {
                    // Remove
                    unset($receipt->{$tmp});
                }
            }
        }
    }

    /**
     * Prepare API key for internal call.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param User $user - Instance of object User who owns the api key
     * @return void
     */
    protected function prepareAPIKey($user)
    {
        // This will query the database if the apikey has not been set up yet
        $apikey = $user->apikey;

        if (empty($apikey)) {
            // Create new one
            $apikey = $user->createAPiKey();
        }

        // Generate the signature
        $_GET['apikey'] = $apikey->api_key;
        $_GET['apitimestamp'] = time();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v1/foo';

        $signature = Generator::genSignature($apikey->api_secret_key);
        $_SERVER['HTTP_X_ORBIT_SIGNATURE'] = $signature;
    }
}