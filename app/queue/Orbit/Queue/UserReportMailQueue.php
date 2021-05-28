<?php namespace Orbit\Queue;
/**
 * Process queue for sending user email after registration. This email
 * contains activation link.
 *
 */
use Mail;
use Config;
use DB;
use Language;
use Lang;
use App;
use Log;
use Orbit\Helper\Util\JobBurier;
use Exception;

class UserReportMailQueue
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
            $prefix = DB::getTablePrefix();

            if (! empty($data['language'])) {
                App::setLocale($data['language']);
            }

            $dataView = $data;
            $dataView['landing_page_url'] = Config::get('orbit.base_landing_page_url', 'https://www.gotomalls.com');
            $dataView['pulsa_page_url'] = $dataView['landing_page_url'].'/pulsa?country=Indonesia';
            $dataView['game_voucher_page_url'] = $dataView['landing_page_url'].'/game-voucher?country=Indonesia';
            $dataView['product_page_url'] = $dataView['landing_page_url'].'/products/affiliate?country=Indonesia';
            $dataView['pln_page_url'] = $dataView['landing_page_url'].'/pln-token?country=Indonesia';
            $dataView['mall_list_page_url'] = $dataView['landing_page_url'].'/malls';
            $dataView['store_list_page_url'] = $dataView['landing_page_url'].'/stores';
            $dataView['coupon_list_page_url'] = $dataView['landing_page_url'].'/coupons';
            $dataView['promotion_list_page_url'] = $dataView['landing_page_url'].'/promotions';
            $dataView['event_list_page_url'] = $dataView['landing_page_url'].'/events';
            $dataView['article_list_page_url'] = $dataView['landing_page_url'].'/articles';
            $dataView['contact'] = Config::get('orbit.contact_information.customer_service');

            $mailViews = array(
                'html' => 'emails.user-report.user-report-html',
                'text' => 'emails.user-report.user-report-text',
            );

            $this->sendUserReportEmail($mailViews, $dataView);

            $message = sprintf('[Job ID: `%s`] User Report Mail; Status: Success;', $job->getJobId());
            Log::info($message);

            $job->delete();

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] User Report Mail; Status: FAIL; Code: %s; Message: %s; Line: %s',
                    $job->getJobId(),
                    $e->getCode(),
                    $e->getMessage(),
                    $e->getLine());
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
    protected function sendUserReportEmail($mailviews, $data)
    {
        Mail::send($mailviews, $data, function($message) use ($data)
        {
            $emailconf = Config::get('orbit.generic_email.sender');
            $from = $emailconf['email'];
            $name = $emailconf['name'];
            $email = $data['user_email'];
            $subject = trans('email-report.subject');

            $message->from($from, $name);
            $message->subject($subject);
            $message->to($email);
        });
    }

}
