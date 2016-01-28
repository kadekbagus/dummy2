<?php namespace Orbit\Queue;
/**
 * Process queue for sending user email after activate. This email
 * contains setup user password link.
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @author Irianto Pratama <irianto@dominopos.com>
 */
use User;
use Mail;
use Config;
use Token;

class NewPasswordMail
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Irianto Pratama <irianto@dominopos.com>
     * @param Job $job
     * @param array $data [user_id => NUM]
     */
    public function fire($job, $data)
    {
        // Get data information from the queue
        $userId = $data['user_id'];
        $user = User::excludeDeleted()->find($userId);
        $email = $user->user_email;

        // Token expiration, fallback to 30 days
        $expireInDays = Config::get('orbit.registration.mobile.activation_expire', 30);

        // Token Settings
        $token = new Token();
        $token->token_name = 'user_setup_password';
        $token->token_value = $token->generateToken($user->user_email);
        $token->status = 'active';
        $token->email = $user->user_email;
        $token->expire = date('Y-m-d H:i:s', strtotime('+' . $expireInDays . ' days'));
        $token->ip_address = $user->user_ip;
        $token->user_id = $userId;
        $token->save();

        // URL Activation link
        $baseUrl = Config::get('orbit.registration.mobile.setup_new_password_url');
        $tokenUrl = sprintf($baseUrl, $token->token_value);
        $contactInfo = Config::get('orbit.contact_information.customer_service');

        $data = array(
            'token'             => $token->token_value,
            'email'             => $user->user_email,
            'first_name'             => $user->user_firstname,
            'token_url'         => $tokenUrl,
            'cs_phone'          => $contactInfo['phone'],
            'cs_email'          => $contactInfo['email'],
            'cs_office_hour'    => $contactInfo['office_hour']
        );
        $mailviews = array(
            'html' => 'emails.registration.setpassword-html',
            'text' => 'emails.registration.setpassword-text'
        );
        Mail::send($mailviews, $data, function($message) use ($user)
        {
            $emailconf = Config::get('orbit.registration.mobile.sender');
            $from = $emailconf['email'];
            $name = $emailconf['name'];

            $message->from($from, $name)->subject('You are almost in Orbit!');
            $message->to($user->user_email);
        });

        // Don't care if the job success or not we will provide user
        // another link to resend the activation
        $job->delete();
    }
}