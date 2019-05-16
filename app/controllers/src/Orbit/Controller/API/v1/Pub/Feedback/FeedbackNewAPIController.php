<?php namespace Orbit\Controller\API\v1\Pub\Feedback;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Config;
use Cache;
use Validator;
use Str;
use \Exception;
use Activity;
use User;
use Mall;
use \Orbit\Helper\Exception\OrbitCustomException;
use Carbon\Carbon as Carbon;

use Orbit\Notifications\Feedback\MallFeedbackNotification;
use Orbit\Notifications\Feedback\StoreFeedbackNotification;

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
        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You must login to access this.';
                ACL::throwAccessForbidden($message);
            }

            $this->registerCustomValidations();

            $feedback = [];
            $feedback['store'] = OrbitInput::post('store');
            $feedback['mall'] = OrbitInput::post('mall');
            $feedback['report'] = OrbitInput::post('report');
            $feedback['is_mall'] = OrbitInput::post('is_mall', 'Y');
            $feedback['user'] = $user->user_id;
            $feedback['mall_id'] = OrbitInput::post('mall_id');
            $storeName = Str::slug($feedback['store']);

            $validator = Validator::make(
                $feedback,
                [
                    'mall_id'   => 'required|orbit.exists.mall',
                    'mall'      => 'required',
                    'report'    => 'required',
                    'is_mall'   => 'required',
                    'user'      => "required|orbit.request.throttle:{$feedback['mall_id']},{$storeName}",
                ],
                [
                    'orbit.exists.mall' => 'Invalid mall ID.',
                    'orbit.request.throttle' => "WAIT_BEFORE_MAKING_NEW_FEEDBACK",
                ]
            );

            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $feedback['user'] = $user->user_firstname . ' ' . $user->user_lastname;
            $feedback['email'] = $user->user_email;
            $feedback['date'] = Carbon::now()->format('d F Y');

            $csEmails = Config::get('orbit.feedback.cs_email', ['cs@gotomalls.com']);
            $csEmails = ! is_array($csEmails) ? [$csEmails] : $csEmails;

            foreach($csEmails as $email) {
                $cs = new User;
                $cs->email = $email;

                if ($feedback['is_mall'] === 'Y') {
                    $cs->notify(new MallFeedbackNotification($feedback));
                }
                else {
                    $cs->notify(new StoreFeedbackNotification($feedback));
                }
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

    /**
     * Register custom validation.
     */
    private function registerCustomValidations()
    {
        // Check if mall is exists.
        Validator::extend('orbit.exists.mall', function($attributes, $value, $parameters) {
            $mall = Mall::excludeDeleted()->select('merchant_id')->where('merchant_id', $value)->first();

            return ! empty($mall);
        });

        // TODO: Refactor to middleware (a feature ticket, not hotfix)
        Validator::extend('orbit.request.throttle', function($attributes, $value, $parameters) {

            // Assume mall is valid and not empty because it passed orbit.exists.mall
            $mallId = $parameters[0];
            $storeName = isset($parameters[1]) ? $parameters[1] : null;

            $cacheKeyPrefix = 'feedback_time_mall__%s_%s'; // Can be set in config if needed.
            $cacheKey = sprintf($cacheKeyPrefix, $mallId, $value);
            if (! empty($storeName)) {
                $cacheKeyPrefix .= '_%s';
                $cacheKey = sprintf($cacheKeyPrefix, $mallId, $value, $storeName);
            }

            $now = Carbon::now();
            $lastRequest = Cache::get($cacheKey, $now);

            $first = true;
            if (! $lastRequest instanceof Carbon) {
                $lastRequest = Carbon::parse($lastRequest);
                $first = false;
            }

            // If we can do feedback, then store current time
            // as the last valid feedback.
            $availableIn = Config::get('orbit.feedback.limit', 10);
            $canDoFeedback = $lastRequest->addMinutes($availableIn)->lte($now) || $first;
            if ($canDoFeedback) {
                Cache::put($cacheKey, $now->format('Y-m-d H:i:s'), $availableIn);
            }

            return $canDoFeedback;
        });
    }
}
