<?php namespace Orbit\Queue;
/**
 * Process queue for sending user email after registration. This email
 * contains activation link.
 *
 */
use User;
use Mail;
use Config;
use Token;

class ResetPasswordMail
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author kadek <kadek@dominopos.com>
     * @param Job $job
     * @param array $data [user_id => NUM]
     */
    public function fire($job, $data)
    {
        $token = Token::leftJoin('users', 'users.user_id', '=', 'tokens.user_id')
                      ->where('token_id','=', $data['tokenId'])
                      ->where('token_name', '=', 'reset_password')
                      ->first();

        $baseUrl = Config::get('orbit.reset_password.reset_base_url');
        $tokenUrl = sprintf($baseUrl, $token->token_value, $token->email);
        $contactInfo = Config::get('orbit.contact_information.customer_service');

        $dataView['token'] = $token->token_value;
        $dataView['email'] = $token->email;
        $dataView['first_name'] = $token->user_firstname;
        $dataView['token_url'] = $tokenUrl;

        $mailViews = array(
                'html' => 'emails.reset-password.customer-html',
                'text' => 'emails.reset-password.customer-text'
        );

        $this->sendResetPasswordEmail($mailViews, $dataView);

        // Don't care if the job success or not we will provide user
        // another link to resend the activation
        $job->delete();
    }

    /**
     * Common routine for sending email.
     *
     * @param array $data
     * @return void
     */
    protected function sendResetPasswordEmail($mailviews, $data)
    {

        Mail::send($mailviews, $data, function($message) use ($data)
        {
            $emailconf = Config::get('orbit.reset_password.sender');
            $from = $emailconf['email'];
            $name = $emailconf['name'];
            $email = $data['email'];

            $subject = 'Password Reset Request';
            $message->from($from, $name);
            $message->subject($subject);
            $message->to($email);
        });
    }

}