<?php namespace Orbit\Controller\API\v1\Customer;

/**
 * @author Ahmad <ahmad@dominopos.com>
 * @desc Controller for Campaign (News, Promotion, Coupon as single entity) specific requests for Mobile CI Angular
 */
use Orbit\Controller\API\v1\Customer\BaseAPIController;
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Setting;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \Carbon\Carbon as Carbon;
use \Validator;
use Tenant;
use Mall;
use MerchantLanguage;
use App;
use Lang;
use News;
use Promotion;
use Coupon;
use User;
use URL;
use Activity;

class CampaignCIAPIController extends BaseAPIController
{
    protected $validRoles = ['super admin', 'consumer', 'guest'];
    protected $mall_id = NULL;

    public function getCampaignPopup()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $this->checkAuth();
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->validRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $this->mall_id = OrbitInput::get('mall_id', NULL);

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'mall_id' => $this->mall_id,
                ),
                array(
                    'mall_id' => 'required|orbit.empty.mall',
                )
            );
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $mall = Mall::excludeDeleted()->where('merchant_id', $this->mall_id)->first();

            $alternateLanguage = null;
            $lang = OrbitInput::get('lang', 'en'); //get user current cookie lang
            $language = \Language::where('name', '=', $lang)->first();
            if (is_object($language)) {
                $alternateLanguage = \MerchantLanguage::excludeDeleted()
                    ->where('merchant_id', '=', $mall->merchant_id)
                    ->where('language_id', '=', $language->language_id)
                    ->first();
            }

            //$alternateLanguage = $this->getAlternateMerchantLanguage($user, $mall);
            $mallTime = Carbon::now($mall->timezone->timezone_name);
            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge = $this->calculateAge($user->userDetail->birthdate);
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender = $user->userDetail->gender;
            }

            $mallid = $mall->merchant_id;
            $prefix = DB::getTablePrefix();

            $promo = DB::table('news')
                ->selectRaw("{$prefix}news.news_id as campaign_id, {$prefix}news.news_name as campaign_name, {$prefix}news.description as campaign_description, {$prefix}news.image as campaign_image, 'promotion' as campaign_type")
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                ->where(function ($q) use ($mallid) {
                    $q->where('merchants.parent_id', '=', $mallid)
                      ->orWhere('merchants.merchant_id', '=', $mallid);
                })
                ->where('news.object_type', '=', 'promotion')
                ->where('news.status', 'active')
                ->where('news.is_popup', 'Y')
                ->whereRaw("? between begin_date and end_date", [$mallTime])
                ->groupBy('news.news_id');

            $news = DB::table('news')
                ->selectRaw("{$prefix}news.news_id as campaign_id, {$prefix}news.news_name as campaign_name, {$prefix}news.description as campaign_description, {$prefix}news.image as campaign_image, 'news' as campaign_type")
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                ->where(function ($q) use ($mallid) {
                    $q->where('merchants.parent_id', '=', $mallid)
                      ->orWhere('merchants.merchant_id', '=', $mallid);
                })
                ->where('news.object_type', '=', 'news')
                ->where('news.status', 'active')
                ->where('news.is_popup', 'Y')
                ->whereRaw("? between begin_date and end_date", [$mallTime])
                ->groupBy('news.news_id');

            $coupon = DB::table('promotions')
                ->selectRaw("{$prefix}promotions.promotion_id as campaign_id, {$prefix}promotions.promotion_name as campaign_name, {$prefix}promotions.description as campaign_description, {$prefix}promotions.image as campaign_image, 'coupon' as campaign_type")
                ->leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'promotions.promotion_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'promotions.promotion_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                ->where(function ($q) use ($mallid) {
                        $q->where('merchants.parent_id', '=', $mallid)
                          ->orWhere('merchants.merchant_id', '=', $mallid);
                    })
                ->where('promotions.is_coupon', '=', 'Y')
                ->where('promotions.is_popup', 'Y')
                ->where('promotions.status', 'active')
                ->whereRaw("? between begin_date and end_date", [$mallTime])
                ->groupBy('promotions.promotion_id');

            if ($userGender !== null) {
                $promo = $promo->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                $news = $news->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
                $coupon = $coupon->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
            }

            if ($userAge !== null) {
                if ($userAge === 0){
                    $promo = $promo->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    $news = $news->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    $coupon = $coupon->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                } else {
                    if ($userAge >= 55) {
                        $promo = $promo->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                        $news = $news->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                        $coupon = $coupon->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                    } else {
                        $promo = $promo->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                        $news = $news->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                        $coupon = $coupon->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    }
                }
            }

            $promo->orderBy(DB::raw('RAND()'));

            $news->orderBy(DB::raw('RAND()'));

            $coupon->orderBy(DB::raw('RAND()'));

            $results = $promo->unionAll($news)->unionAll($coupon)->get();

            //$campaign_card_total = Config::get('campaign_card_popup_number', 5); <----------- should create config for this number
            $campaign_card_total = 5;
            $max_campaign = count($results) > $campaign_card_total ? $campaign_card_total : count($results);

            shuffle($results);

            // slice shuffled results to 2 parts and shuffle again
            $resultsize = count($results);

            $firsthalf = array_slice($results, 0, ($resultsize / 2));
            $secondhalf = array_slice($results, ($resultsize / 2));
            shuffle($firsthalf);
            shuffle($secondhalf);
            $secondresults = array_merge($firsthalf, $secondhalf);
            shuffle($secondresults);

            $end_results = array_slice($secondresults, 0, $max_campaign);

            foreach($end_results as $near_end_result) {
                $near_end_result->campaign_image = NULL;

                if (!empty($alternateLanguage)) {
                    if ($near_end_result->campaign_type === 'promotion' || $near_end_result->campaign_type === 'news') {
                        $campaignTranslation = \NewsTranslation::excludeDeleted()
                            ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                            ->where('news_id', $near_end_result->campaign_id)->first();
                    } elseif ($near_end_result->campaign_type === 'coupon'){
                        $campaignTranslation = \CouponTranslation::excludeDeleted()
                            ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                            ->where('promotion_id', $near_end_result->campaign_id)->first();
                    }

                    if (!empty($campaignTranslation)) {
                        if ($near_end_result->campaign_type === 'promotion' || $near_end_result->campaign_type === 'news') {

                            //if field translation empty or null, value of field back to english (default)
                            if (isset($campaignTranslation->news_name) && $campaignTranslation->news_name !== '') {
                                $near_end_result->campaign_name = $campaignTranslation->news_name;
                            }
                            if (isset($campaignTranslation->description) && $campaignTranslation->description !== '') {
                                $near_end_result->campaign_description = $campaignTranslation->description;
                            }

                            $media = $campaignTranslation->find($campaignTranslation->news_translation_id)
                                ->media_orig()
                                ->first();

                            if (is_object($media)) {
                                $near_end_result->campaign_image = URL::asset($media->path);
                            } else {
                                // back to default image if in the content multilanguage not have image
                                // check the system language
                                $defaultLanguage = $this->getDefaultLanguage($mall);
                                if ($defaultLanguage !== NULL) {
                                    $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                        ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                        ->where('news_id', $near_end_result->campaign_id)->first();

                                    // get default image
                                    $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                        ->media_orig()
                                        ->first();

                                    if (is_object($mediaDefaultLanguage)) {
                                        $near_end_result->campaign_image = URL::asset($mediaDefaultLanguage->path);
                                    }
                                }
                            }
                        } elseif ($near_end_result->campaign_type === 'coupon') {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($campaignTranslation->promotion_name) && $campaignTranslation->promotion_name !== '') {
                                $near_end_result->campaign_name = $campaignTranslation->promotion_name;
                            }
                            if (isset($campaignTranslation->description) && $campaignTranslation->description !== '') {
                                $near_end_result->campaign_description = $campaignTranslation->description;
                            }

                            $media = $campaignTranslation->find($campaignTranslation->coupon_translation_id)
                                ->media_orig()
                                ->first();

                            if (is_object($media)) {
                                $near_end_result->campaign_image = URL::asset($media->path);
                            } else {
                                // back to default image if in the content multilanguage not have image
                                // check the system language
                                $defaultLanguage = $this->getDefaultLanguage($mall);
                                if ($defaultLanguage !== NULL) {
                                    $contentDefaultLanguage = \CouponTranslation::excludeDeleted()
                                        ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                        ->where('promotion_id', $near_end_result->campaign_id)->first();

                                    // get default image
                                    $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->coupon_translation_id)
                                        ->media_orig()
                                        ->first();

                                    if (is_object($mediaDefaultLanguage)) {
                                        $near_end_result->campaign_image = URL::asset($mediaDefaultLanguage->path);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $data = new \stdclass();
            $data->records = $end_results;
            $data->returned_records = count($end_results);
            $data->total_records = count($end_results);
            $data->extras = new \stdclass();

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
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
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    public function postCampaignPopUpActivities()
    {
        $activity = Activity::mobileci();
        $user = null;
        $mall = null;
        $campaign_type = null;
        $campaign_id = null;
        $activity_type = null;

        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $this->checkAuth();
            $user = $this->api->user;

            $this->mall_id = OrbitInput::get('mall_id', NULL);
            $campaign_type = OrbitInput::post('campaign_type');
            $campaign_id   = OrbitInput::post('campaign_id');
            $activity_type = OrbitInput::post('activity_type');

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'mall_id' => $this->mall_id,
                    'campaign_type' => $campaign_type,
                    'campaign_id'   => $campaign_id,
                    'activity_type' => $activity_type,
                ),
                array(
                    'mall_id' => 'required|orbit.empty.mall',
                    'campaign_type' => 'required|in:news,promotion,coupon',
                    'campaign_id'   => 'required',
                    'activity_type' => 'required|in:view,click',
                )
            );
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $mall = Mall::excludeDeleted()->where('merchant_id', $this->mall_id)->first();

            $activity->setActivityType($activity_type);

            $campaign = null;
            if ($campaign_type === 'news' || $campaign_type === 'promotion') {
                $campaign = News::active()->where('news_id', $campaign_id)
                                          ->where('object_type', $campaign_type)
                                          ->first();
                $activity->setNews($campaign);
            }
            if ($campaign_type === 'coupon') {
                $campaign = Coupon::active()->where('promotion_id', $campaign_id)
                                            ->where('is_coupon', 'Y')
                                            ->first();
                $activity->setCoupon($campaign);
            }

            $activityNotes = sprintf('Campaign ' . ucfirst($activity_type) . '. Campaign Id : %s, Campaign Type : %s', $campaign_id, $campaign_type);
            $activity->setUser($user)
                ->setActivityName($activity_type . '_' . $campaign_type . '_popup')
                ->setActivityNameLong(ucfirst($activity_type) . ' ' . ucwords(str_replace('_', ' ', $campaign_type)) . ' Pop Up')
                ->setObject($campaign)
                ->setModuleName(ucfirst($campaign_type))
                ->setLocation($mall)
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $this->rollback();
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $this->rollback();
            $httpCode = 403;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = $e->getLine();
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getFile();

            $this->rollback();
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check the existance of merchant id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });
    }
}
