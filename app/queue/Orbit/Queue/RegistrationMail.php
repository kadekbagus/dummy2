<?php namespace Orbit\Queue;
/**
 * Process queue for sending user email after registration. This email
 * contains activation link.
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @author Irianto Pratama <irianto@dominopos.com>
 */
use User;
use Mail;
use Config;
use Token;
use Mall;
use Language;
use App;
use Lang;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Log;

class RegistrationMail
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Irianto <irianto@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     * @param Job $job
     * @param array $data [user_id => NUM]
     */
    public function fire($job, $data)
    {
        $language = (empty($data['languageId']))? 'id' : $data['languageId'];
        $valid_language = Language::where('status', '=', 'active')
                            ->where('name', $language)
                            ->first();

        if (empty($valid_language)) {
            // get from config default language or locale language from country for sign up
            $language = 'id'; // for a while before set a config
        }

        App::setLocale($language);
        $data['languageId'] = $language;

        // Get data information from the queue
        $userId = $data['user_id'];
        $user = User::excludeDeleted()->find($userId);

        if (! is_object($user)) {
            // Bury the job for later inspection
            JobBurier::create($job, function($theJob) {
                // The queue driver does not support bury.
                $theJob->delete();
            })->bury();
            return [
                'status' => 'fail',
                'message' => sprintf('[Job ID: `%s`] *** RegistrationMail Queue: User `%s` not found. ***',
                                    $job->getJobId(),
                                    $userId)
            ];
        }

        try {
            $email = $user->user_email;

            // Token expiration, fallback to 30 days
            $expireInDays = Config::get('orbit.registration.mobile.activation_expire', 30);

            $metadata = NULL;
            $toUrl = '';
            // fill metadata if any
            if (isset($data['redirect_to_url']) && ! is_null($data['redirect_to_url'])) {
                $metadata['redirect_to_url'] = $data['redirect_to_url'];
                $toUrl = $data['redirect_to_url'];
            }
            // json encode metadata if any
            if (! empty($metadata)) {
                $metadata = json_encode($metadata);
            }

            // Token Settings
            $token = new Token();
            $token->token_name = 'user_registration_mobile';
            $token->token_value = $token->generateToken($user->user_email);
            $token->status = 'active';
            $token->email = $user->user_email;
            $token->expire = date('Y-m-d H:i:s', strtotime('+' . $expireInDays . ' days'));
            $token->ip_address = $user->user_ip;
            $token->user_id = $userId;
            $token->metadata = $metadata;
            $token->save();

            switch ($data['mode']) {
                case 'customer_portal':
                    $mallName = '-Unknown-';
                    $retailer = Mall::find($data['merchant_id']);
                    if (is_object($retailer)) {
                        $mallName = $retailer->name;
                    }
                    Config::get('orbit.registration.mobile.activation_base_url');
                    break;

                case 'gotomalls':
                default:
                    $mallName = 'GotoMalls.com';
                    $baseUrl = Config::get('orbit.registration.mobile.gotomalls_activation_base_url');
            }

            $this->sendActivationEmail($user, $token, $data, $mallName, $baseUrl, $toUrl);

            $message = sprintf('[Job ID: `%s`] Registration Email; Status: OK; Type: Activation Email; User ID: %s; User Email: %s; Token: %s',
                                $job->getJobId(),
                                $user->user_id,
                                $user->user_email,
                                $token->token_value);
            Log::info($message);

            // Don't care if the job success or not we will provide user
            // another link to resend the activation
            $job->delete();

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Registration Email; Status: FAIL; Type: Activation Email; User ID: %s; Message: %s, File: %s, Line: %s',
                                $job->getJobId(),
                                $data['user_id'],
                                $e->getMessage(),
                                $e->getFile(),
                                $e->getLine()
                    );

            Log::info($message);
        }

        // Bury the job for later inspection
        JobBurier::create($job, function($theJob) {
            // The queue driver does not support bury.
            $theJob->delete();
        })->bury();

        return [
            'status' => 'fail',
            'message' => $message
        ];
    }

    /**
     * Common routine for sending email.
     *
     * @param User $user The User object
     * @param Token $token The Token object
     * @param array $data
     * @param string $storeName The name of the mall, could be gotomalls.
     * @param string $baseUrl URL for activation email
     * @return void
     */
    protected function sendActivationEmail($user, $token, $data, $mallName, $baseUrl, $toUrl)
    {
        // URL Activation link
        $tokenUrl = sprintf($baseUrl, $token->token_value, $user->user_email, $data['languageId'], $toUrl);
        $contactInfo = Config::get('orbit.contact_information.customer_service');
        $baseLinkUrl = Config::get('app.url') . '/%s?utm_source=gtm-activation-email&utm_medium=email&utm_content=menulink&lang=' . $data['languageId'];

        $dataCopy = $data;
        $data = array(
            'link_malls'      => sprintf($baseLinkUrl, 'malls'),
            'link_stores'     => sprintf($baseLinkUrl, 'stores'),
            'link_promotions' => sprintf($baseLinkUrl, 'promotions'),
            'link_coupons'    => sprintf($baseLinkUrl, 'coupons'),
            'link_events'     => sprintf($baseLinkUrl, 'events'),
            'token'             => $token->token_value,
            'email'             => $user->user_email,
            'first_name'        => $user->user_firstname,
            'last_name'         => $user->user_lastname,
            'token_url'         => $tokenUrl,
            'shop_name'         => $mallName,
            'cs_phone'          => $contactInfo['phone'],
            'cs_email'          => $contactInfo['email'],
            'cs_office_hour'    => $contactInfo['office_hour'],
            'label_malls'       => Lang::get('email.activation.label_malls'),
            'label_stores'      => Lang::get('email.activation.label_stores'),
            'label_promotions'  => Lang::get('email.activation.label_promotions'),
            'label_coupons'     => Lang::get('email.activation.label_coupons'),
            'label_lucky_draws' => Lang::get('email.activation.label_lucky_draws'),
            'label_events'      => Lang::get('email.activation.label_events'),
            'subject'           => Lang::get('email.activation.subject'),
            'greeting'          => Lang::get('email.activation.greeting'),
            'message_part1'     => Lang::get('email.activation.message_part1'),
            'message_part2'     => Lang::get('email.activation.message_part2'),
            'button_activation' => Lang::get('email.activation.button_activation'),
            'message_part3'     => Lang::get('email.activation.message_part3'),
            'list_1'            => Lang::get('email.activation.list_1'),
            'list_2'            => Lang::get('email.activation.list_2'),
            'list_3'            => Lang::get('email.activation.list_3'),
            'list_4'            => Lang::get('email.activation.list_4'),
            'list_5'            => Lang::get('email.activation.list_5'),
            'message_part4'     => Lang::get('email.activation.message_part4'),
            'team_name'         => Lang::get('email.activation.team_name'),
            'ignore_email'      => Lang::get('email.activation.ignore_email'),
            'find_follow'       => Lang::get('email.activation.find_follow'),
        );

        $mailviews = array(
            'html' => 'emails.registration.activation-html',
            'text' => 'emails.registration.activation-text'
        );
        Mail::send($mailviews, $data, function($message) use ($user, $dataCopy)
        {
            $emailconf = Config::get('orbit.registration.mobile.sender');
            $from = $emailconf['email'];
            $name = $emailconf['name'];

            $message->from($from, $name)->subject(Lang::get('email.activation.subject'));
            $message->to($user->user_email);

            if (isset($dataCopy['cc_email']) && !empty($dataCopy['cc_email'])) {
                $message->cc($dataCopy['cc_email']);
            }
        });
    }
}
