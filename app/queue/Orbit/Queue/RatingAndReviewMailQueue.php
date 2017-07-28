<?php namespace Orbit\Queue;
/**
 * Process queue for sending user email after registration. This email
 * contains activation link.
 *
 */
use User;
use News;
use Promotion;
use Tenant;
use Mall;
use CampaignLocation;
use Mail;
use Config;
use DB;
use Log;
use Orbit\Helper\Util\JobBurier;
use Exception;
use Orbit\Helper\MongoDB\Client as MongoClient;

class RatingAndReviewMailQueue
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author shelgi <shelgi@dominopos.com>
     * @param Job $job
     * @param array $data [user_id => NUM]
     */
    public function fire($job, $data)
    {

        try {
            $prefix = DB::getTablePrefix();
            $mongoConfig = Config::get('database.mongodb');

            $userId = (! empty($data['user_id'])) ? $data['user_id'] : '';
            $objectId = (! empty($data['object_id'])) ? $data['object_id'] : '';
            $objectType = (! empty($data['object_type'])) ? $data['object_type'] : '';
            $locationId = (! empty($data['location_id'])) ? $data['location_id'] : '';
            $reviewDate = (! empty($data['updated_at'])) ? $data['updated_at'] : '';
            $rating = (! empty($data['rating'])) ? $data['rating'] : '';
            $review = (! empty($data['review'])) ? $data['review'] : '';

            $mongoClient = MongoClient::create($mongoConfig);
            $reviewId = '';
            if (! empty($data['_id'])) {
                $endPoint = "reviews/" . $data['_id'];
                $response = $mongoClient->setEndPoint($endPoint)
                                        ->request('GET');

                $userId = $response->data->user_id;
                $objectId = $response->data->object_id;
                $objectType = $response->data->object_type;
                $locationId = $response->data->location_id;
                $reviewDate = $response->data->updated_at;
                $rating = $response->data->rating;
                $review = $response->data->review;
                $reviewId = $data['_id'];
            } else {
                $queryString = [
                    'user_id'     => $userId,
                    'object_id'   => $objectId,
                    'object_type' => $objectType,
                    'location_id' => $locationId
                ];

                $endPoint = "reviews";
                $response = $mongoClient->setQueryString($queryString)
                                    ->setEndPoint($endPoint)
                                    ->request('GET');

                $listOfRec = $response->data;
                foreach ($listOfRec->records as $rating) {
                    $reviewId = $rating->_id;
                }
            }

            // get user name and email
            $user = User::select('users.user_email', DB::raw("(CONCAT({$prefix}users.user_firstname, ' ', {$prefix}users.user_lastname)) as user_name"))
                                  ->where('users.user_id', $userId)
                                  ->first();

            // get location
            $location = CampaignLocation::select(DB::raw("IF({$prefix}merchants.object_type = 'tenant', CONCAT({$prefix}merchants.name,' at ', oms.name), {$prefix}merchants.name) as location_name"))
                                        ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                        ->where('merchants.merchant_id', $locationId)
                                        ->first();

            // get campaign/store/mall name
            switch ($objectType) {
                case 'news':
                    $object = News::select(DB::raw("{$prefix}news.news_name as object_name"))->where('object_type', 'news')->where('news_id', $objectId)->first();
                    break;

                case 'promotion':
                    $object = News::select(DB::raw("{$prefix}news.news_name as object_name"))->where('object_type', 'promotion')->where('news_id', $objectId)->first();
                    break;

                case 'coupon':
                    $object = Promotion::select(DB::raw("{$prefix}promotions.promotion_name as object_name"))->where('promotion_id', $objectId)->first();
                    break;

                case 'store':
                    $object = Tenant::select(DB::raw("{$prefix}merchants.name as object_name"))->where('merchant_id', $objectId)->first();
                    break;

                case 'mall':
                    $object = Mall::select(DB::raw("{$prefix}merchants.name as object_name"))->where('merchant_id', $objectId)->first();
                    break;
            }

            // generate the subject based on config
            $subjectConfig = Config::get('orbit.rating_review.email.subject');
            $subject = str_replace('{{LOCATION}}', $object->object_name, $subjectConfig);
            $subject = str_replace('{{USER_EMAIL}}', $user->user_email, $subject);

            $date = date('d-m-y H:i:s', strtotime($reviewDate));

            // data send to the mail view
            $dataView['subject'] = $subject;
            $dataView['date'] = $date;
            $dataView['type'] = $objectType;
            $dataView['location'] = $object->object_name;
            $dataView['location_detail'] = $location->location_name;
            $dataView['name'] = $user->user_name;
            $dataView['email'] = $user->user_email;
            $dataView['review_id'] = $reviewId;
            $dataView['rating'] = $rating;
            $dataView['review'] = $review;

            $mailViews = array(
                        'html' => 'emails.rating.review-rating-html',
                        'text' => 'emails.rating.review-rating-text'
            );

            $this->sendReviewEmail($mailViews, $dataView);

            $message = sprintf('[Job ID: `%s`] Rating - Review Mail; Status: Success;', $job->getJobId());
            Log::info($message);

            $job->delete();

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Exception $e) {
            $message = sprintf('[Job ID: `%s`] Rating - Review Mail; Status: FAIL; Code: %s; Message: %s',
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
    protected function sendReviewEmail($mailviews, $data)
    {

        Mail::send($mailviews, $data, function($message) use ($data)
        {
            $emailConf = Config::get('orbit.generic_email.sender');
            $from = $emailConf['email'];
            $name = $emailConf['name'];

            $email = Config::get('orbit.rating_review.email.to');

            $subject = $data['subject'];

            $message->from($from, $name);
            $message->subject($subject);
            $message->to($email);
        });
    }

}