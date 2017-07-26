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
use Language;
use App;
use Lang;
use Log;
use Orbit\Helper\Util\JobBurier;
use Exception;

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
        try {
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

            $token = Token::leftJoin('users', 'users.user_id', '=', 'tokens.user_id')
                          ->where('token_id','=', $data['tokenId'])
                          ->where('token_name', '=', 'reset_password')
                          ->first();

            if (! is_object($token)) {
                $errorMessage = sprintf('Token id %s not found', $data['tokenId']);
                throw new Exception($errorMessage);
            }

            $baseUrl = Config::get('orbit.reset_password.reset_base_url');
            $tokenUrl = sprintf($baseUrl, $token->token_value, $token->email, $data['languageId']);
            $contactInfo = Config::get('orbit.contact_information.customer_service');

            $dataView['token'] = $token->token_value;
            $dataView['email'] = $token->email;
            $dataView['first_name'] = $token->user_firstname;
            $dataView['token_url'] = $tokenUrl;

            $dataView['subject']       = Lang::get('email.reset_password.subject');
            $dataView['title']         = Lang::get('email.reset_password.title');
            $dataView['greeting']      = Lang::get('email.reset_password.greeting');
            $dataView['message_part1'] = Lang::get('email.reset_password.message_part1');
            $dataView['message_part2'] = Lang::get('email.reset_password.message_part2');
            $dataView['message_part3'] = Lang::get('email.reset_password.message_part3');
            $dataView['button_reset']  = Lang::get('email.reset_password.button_reset');
            $dataView['message_part4'] = Lang::get('email.reset_password.message_part4');
            $dataView['message_part5'] = Lang::get('email.reset_password.message_part5');
            $dataView['message_part6'] = Lang::get('email.reset_password.message_part6');
            $dataView['team_name']     = Lang::get('email.reset_password.team_name');

            $mailViews = array(
                    'html' => 'emails.reset-password.customer-html',
                    'text' => 'emails.reset-password.customer-text'
            );

            $this->sendResetPasswordEmail($mailViews, $dataView);

            $message = sprintf('[Job ID: `%s`] Reset Password Mail; Status: Success;', $job->getJobId());
            Log::info($message);

            $job->delete();

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Reset Password Mail; Status: FAIL; Code: %s; Message: %s',
                    $job->getJobId(),
                    $e->getCode(),
                    $e->getMessage());
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

            $subject = $data['subject'];
            $message->from($from, $name);
            $message->subject($subject);
            $message->to($email);
        });
    }

}