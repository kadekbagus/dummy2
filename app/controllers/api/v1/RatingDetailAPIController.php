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

    private function getLanguageId($name)
    {
        $lang = Language::select('language_id')->where('name', $name)->first();
        return $lang->language_id;
    }

    private function getMediaUrl($media, $useCdn, $urlPrefix)
    {
        if (empty($media)) {
            return null;
        }
        if ($useCdn) {
            if (! empty($media->cdn_url)) {
                return $media->cdn_url;
            } else {
                return $urlPrefix . $media->path;
            }
        } else {
            return $urlPrefix . $media->path;
        }
    }

    private function getReviewedObject($objectName, $media, $useCdn, $urlPrefix)
    {
        return (object) [
            'object_name' => $objectName,
            'object_picture' => $this->getMediaUrl($media, $useCdn, $urlPrefix),
        ];
    }

    private function getCoupon($objectId, $langId, $useCdn, $urlPrefix)
    {
        $coupon = Coupon::where('promotion_id', '=', $objectId)->first();
        $translation = $coupon->translations()->where('language_id', $langId)->first();
        $media = $translation->media_orig()->first();
        return $this->getReviewedObject($coupon->promotion_name, $media, $useCdn, $urlPrefix);
    }

    private function getStore($objectId, $langId, $useCdn, $urlPrefix)
    {
        $store = Tenant::where('merchant_id', '=', $objectId)->first();
        $media = $store->mediaOrig()->first();
        return $this->getReviewedObject($store->name, $media, $useCdn, $urlPrefix);
    }

    private function getMall($objectId, $langId, $useCdn, $urlPrefix)
    {
        $mall = Mall::where('merchant_id', '=', $objectId)->first();
        $media = $mall->mediaOrig()
            ->where('media_name_long', 'mall_logo_orig')
            ->first();
        return $this->getReviewedObject($mall->name, $media, $useCdn, $urlPrefix);
    }

    private function getEvent($objectId, $langId, $useCdn, $urlPrefix)
    {
        $event = News::where('news_id', '=', $objectId)->first();
        $translation = $event->translations()->where('language_id', $langId)->first();
        $media = $translation->media_orig()->first();
        return $this->getReviewedObject($event->news_name, $media, $useCdn, $urlPrefix);
    }

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

            $prefix = DB::getTablePrefix();
            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $mongoConfig = Config::get('database.mongodb');
            $mongoClient = MongoClient::create($mongoConfig);

            // Get main review...
            $rating = $mongoClient->setEndPoint("reviews/$ratingId")->request('GET');

            $userWhoReplied = User::select('user_firstname', 'user_lastname')
                ->where('user_id', $rating->data->user_id)->first();

            if (empty($userWhoReplied)) {
                $errorMessage = 'User not found.';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $rating->data->user_name = $userWhoReplied->user_firstname . ' ' . $userWhoReplied->user_lastname;

            // Get object being reviewed.
            $objectId = $rating->data->object_id;
            $objectType = $rating->data->object_type;
            $langId = $this->getLanguageId('id');
            $object = null;
            switch(strtolower($objectType)) {
                case 'coupon':
                    $object = $this->getCoupon($objectId, $langId, $usingCdn, $urlPrefix);
                    break;
                case 'store':
                    $object = $this->getStore($objectId, $langId, $usingCdn, $urlPrefix);
                    break;
                case 'mall':
                    $object = $this->getMall($objectId, $langId, $usingCdn, $urlPrefix);
                    break;
                default:
                    $object = $this->getEvent($objectId, $langId, $usingCdn, $urlPrefix);
            }

            if (! empty($object)) {
                $rating->data->object_name = $object->object_name;
                $rating->data->object_picture = $object->object_picture;
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

        $users = [];
        if (count($usersWhoReplied) > 0) {
            // Get list of user's name who replied..
            $usersList = User::select('user_id', 'user_firstname', 'user_lastname')
                // ->with([
                //     'media' => function($query) {
                //         $query->where('media_name_long', 'user_profile_picture_orig');
                //     }
                // ])
                ->whereIn('user_id', $usersWhoReplied)->get();


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
        }

        foreach($replies->data->replies as $reply) {
            $reply->user_name = isset($users[$reply->user_id]) ?
                $users[$reply->user_id]['name'] : '';

            $reply->user_name_replied = isset($users[$reply->user_id_replied]) ?
                $users[$reply->user_id_replied]['name'] : '';

            // $reply->user_picture = isset($users[$reply->user_id]) ?
            //     $users[$reply->user_id]['picture'] : '';
        }

        return $replies;
    }

    private function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
