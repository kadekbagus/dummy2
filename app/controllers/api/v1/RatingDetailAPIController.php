<?php
/**
 * An API controller for mall location (country,city,etc).
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use DominoPOS\OrbitUploader\Uploader as OrbitUploader;
use Carbon\Carbon as Carbon;
use Orbit\Helper\OneSignal\OneSignal;
use Orbit\Helper\MongoDB\Client as MongoClient;

use Orbit\Helper\Util\PaginationNumber;

class RatingDetailAPIController extends ControllerAPI
{
    protected $viewRoles = ['merchant review admin', 'master review admin'];

    /**
     * GET - Rating detail
     * @author budi <budi@dominopos.com>
     *
     * @param string            `notification_id`
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function getRatingDetail()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }
            
            $withReplies = OrbitInput::get('with_replies', 1);
            $ratingId = OrbitInput::get('rating_id');
            $validator = Validator::make(
                array(
                    'rating_id'     => $ratingId
                ),
                array(
                    'rating_id'     => 'required'
                )
            );

            // // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);

            // Get main review...
            $rating = $mongoClient->setEndPoint("reviews/$ratingId")->request('GET');

            // Get object being reviewed.
            $objectId = $rating->data->object_id;
            $objectType = $rating->data->object_type;

            switch(strtolower($objectType)) {
                case 'coupon':
                    $object = Coupon::select('promotion_name as object_name')->where('promotion_id', '=', $objectId)->first();
                    break;
                case 'store':
                    $object = Tenant::select('name as object_name')->where('merchant_id', '=', $objectId)->first();
                    break;
                case 'mall':
                    $object = Mall::select('name as object_name')->where('merchant_id', '=', $objectId)->first();
                    break;
                default:
                    $object = News::select('news_name as object_name')->where('news_id', '=', $objectId)->first();
            }

            if ($object != null) {
                $rating->data->object_name = $object->object_name;
                $rating->data->object_picture = '';

                // @todo should use eager loading.
                $media = Media::where('object_id', 'like', '%' . $objectId . '%')
                    ->where('media_name_long', 'news_translation_image_orig')->first();
                
                if ($media != null) {
                    $rating->data->object_picture = $urlPrefix . $media->path;

                    if ($usingCdn && ! empty($media->cdn_url)) {
                        $rating->data->object_picture = $media->cdn_url;
                    }
                }
            }

            $replies = null;
            if ($withReplies == 1) {
                $replies = $this->getReplies($ratingId, $mongoClient);
            }

            $data = [
                'review' => $rating->data,
                'replies' => $replies,
            ];

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = 0;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {
            Event::fire('orbit.mall.getsearchmallcountry.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        // Event::fire('orbit.mall.getsearchmallcountry.before.render', array($this, &$output));

        return $output;
    }

    public function getRatingReplies()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }
            
            $ratingId = OrbitInput::get('rating_id');
            $validator = Validator::make(
                array(
                    'rating_id'     => $ratingId
                ),
                array(
                    'rating_id'     => 'required'
                )
            );

            // // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);

            $replies = $this->getReplies($ratingId, $mongoClient);

            $this->response->data = $replies->data->replies;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = 0;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {
            Event::fire('orbit.mall.getsearchmallcountry.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        // Event::fire('orbit.mall.getsearchmallcountry.before.render', array($this, &$output));

        return $output;
    }

    /**
     * Get replies of a review.
     * 
     * @return [type] [description]
     */
    private function getReplies($ratingId, $mongoClient = null)
    {
        $sortMode = OrbitInput::get('sortMode', 'asc');
        $take = PaginationNumber::parseTakeFromGet('news');
        $skip = PaginationNumber::parseSkipFromGet();

        // Get replies of this rating/review.
        $queryString = [
            'take'        => $take,
            'skip'        => $skip,
            'sortBy'      => 'created_at',
            'sortMode'    => $sortMode,
        ];

        $mongoClient->setCustomQuery(TRUE);
        $replies = $mongoClient
            ->setQueryString($queryString)
            ->setEndPoint('reviews/' . $ratingId . '/replies?')
            ->request('GET');

        $usersWhoReplied = $replies->data->user_ids;

        // Get list of user's name who replied..
        $usersList = User::select('user_id', 'user_firstname', 'user_lastname')
            // ->with([
            //     'media' => function($query) {
            //         $query->where('media_name_long', 'user_profile_picture_orig');
            //     }
            // ])
            ->whereIn('user_id', $usersWhoReplied)->get();

        $users = [];
        foreach($usersList as $user) {
            $users[$user->user_id]['name'] = $user->user_firstname . ' ' . 
                $user->user_lastname;

            // $media = $user->media->first();
            // $users[$user->user_id]['picture'] = '';

            // if ($media != null) {
            //     $users[$user->user_id]['picture'] = $urlPrefix . $media->path;

            //     if ($usingCdn && ! empty($media->cdn_url)) {
            //        $users[$user->user_id]['picture'] = $media->cdn_url;
            //     }
            // }
        }

        foreach($replies->data->replies as $reply) {
            $reply->user_name = isset($users[$reply->user_id]) ? 
                $users[$reply->user_id]['name'] : '';

            // $reply->user_picture = isset($users[$reply->user_id]) ?
            //     $users[$reply->user_id]['picture'] : '';
        }

        return $replies;
    }
}