<?php namespace Orbit\Controller\API\v1\Pub\Feedback\Request;

use Cache;
use Carbon\Carbon;
use Config;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Orbit\Helper\Request\ValidateRequest;
use Orbit\Helper\Request\Validators\MallExistsValidator;

/**
 * Validation for new feedback request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class CreateRequest extends ValidateRequest
{
    /**
     * Allowed roles to access this request.
     * @var array
     */
    protected $roles = ['guest', 'consumer'];

    private $lastFeedback = null;

    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'email'     => 'required',
            'mall_id'   => 'required|orbit.exists.mall',
            'mall'      => 'required',
            'report'    => 'required|array',
            'is_mall'   => 'required',
            'user'      => "required|orbit.throttle.feedback",
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'orbit.exists.mall' => 'Invalid mall ID.',
            'orbit.throttle.feedback' => "WAIT_BEFORE_MAKING_NEW_FEEDBACK",
        ];
    }

    /**
     * @override
     * @return void
     */
    public function validate(array $data = [], array $rules = [], array $messages = [])
    {
        $data['user'] = ! empty($this->user) ? $this->user->user_id : null;

        return parent::validate($data, $rules, $messages);
    }

    /**
     * @override
     */
    public function getValidationErrorMessage()
    {
        $errorMessage = parent::getValidationErrorMessage();

        if ($errorMessage === 'WAIT_BEFORE_MAKING_NEW_FEEDBACK') {
            $errorMessage .= '|' . $this->lastFeedback;
        }

        return $errorMessage;
    }

    /**
     * Register custom validations rules.
     *
     * @return void
     */
    public function registerCustomValidations()
    {
        // Check if mall is exists.
        Validator::extend('orbit.exists.mall', 'Orbit\Helper\Request\Validators\MallExistsValidator@validate');

        Validator::extend('orbit.throttle.feedback', function($attributes, $userId, $parameters, $validator) {
            $mallId = $this->mall_id;
            $storeName = $this->store;

            $cacheKeyPrefix = 'feedback_time_mall__%s_%s'; // Can be set in config if needed.
            if (! empty($storeName)) {
                $cacheKeyPrefix .= '_%s';
                $cacheKey = sprintf($cacheKeyPrefix, $mallId, $userId, $storeName);
            }
            else {
                $cacheKey = sprintf($cacheKeyPrefix, $mallId, $userId);
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
            $diffInMinutes = $lastRequest->diffInMinutes($now);
            $canDoFeedback = $lastRequest->addMinutes($availableIn)->lte($now) || $first;
            if ($canDoFeedback) {
                Cache::put($cacheKey, $now->format('Y-m-d H:i:s'), $availableIn);
            }
            else {
                $this->lastFeedback = $diffInMinutes;
            }

            return $canDoFeedback;
        });
    }
}
