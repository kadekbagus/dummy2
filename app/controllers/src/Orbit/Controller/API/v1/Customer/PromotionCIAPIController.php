<?php namespace Orbit\Controller\API\v1\Customer;

/**
 * @author kadek <kadek@dominopos.com>
 * @desc Controller for promotion Mobile CI Angular
 */
use Orbit\Controller\API\v1\Customer\BaseAPIController;
use OrbitShop\API\v1\ResponseProvider;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \Carbon\Carbon as Carbon;
use \Validator;
use News;
use Mall;
use OrbitShop\API\v1\OrbitShopAPI;

class PromotionCIAPIController extends BaseAPIController
{
	protected $validRoles = ['super admin', 'consumer', 'guest'];
    protected $mall_id = NULL;

    public function getPromotionList()
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

            $validator = Validator::make(
                array(
                    'mall_id' => $this->mall_id,
                ),
                array(
                    'mall_id' => 'required',
                )
            );
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $mall = Mall::where('merchant_id', $this->mall_id)->first();

            if (!is_object($mall)) {
                $errorMessage = "Mall id not found";
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $userAge = 0;
            if ($user->userDetail->birthdate !== '0000-00-00' && $user->userDetail->birthdate !== null) {
                $userAge =  $this->calculateAge($user->userDetail->birthdate); // 27
            }

            $userGender = 'U'; // default is Unknown
            if ($user->userDetail->gender !== '' && $user->userDetail->gender !== null) {
                $userGender =  $user->userDetail->gender;
            }

            $mallTime = Carbon::now($mall->timezone->timezone_name);
            $mallid = $this->mall_id;

            $promotions = News::leftJoin('campaign_gender', 'campaign_gender.campaign_id', '=', 'news.news_id')
                ->leftJoin('campaign_age', 'campaign_age.campaign_id', '=', 'news.news_id')
                ->leftJoin('age_ranges', 'age_ranges.age_range_id', '=', 'campaign_age.age_range_id')
                ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                ->select('news.news_id as promotion_id','news.news_name as name','image', 'news.description as description');

            // filter by age and gender
            if ($userGender !== null) {
                $promotions = $promotions->whereRaw(" ( gender_value = ? OR is_all_gender = 'Y' ) ", [$userGender]);
            }

            if ($userAge !== null) {
                if ($userAge === 0){
                    $promotions = $promotions->whereRaw(" ( (min_value = ? and max_value = ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                } else {
                    if ($userAge >= 55) {
                        $promotions = $promotions->whereRaw( "( (min_value = 55 and max_value = 0 ) or is_all_age = 'Y' ) ");
                    } else {
                        $promotions = $promotions->whereRaw( "( (min_value <= ? and max_value >= ? ) or is_all_age = 'Y' ) ", array([$userAge], [$userAge]));
                    }
                }
            }

            $promotions = $promotions->where('news.status', '=', 'active')
                        ->where(function ($q) use ($mallid) {
                            $q->where('merchants.parent_id', '=', $mallid)
                              ->orWhere('merchants.merchant_id', '=', $mallid);
                        })
                        ->where('news.object_type', 'promotion')
                        ->whereRaw("? between begin_date and end_date", [$mallTime]);

            // OrbitInput::get(
            //     'keyword',
            //     function ($keyword) use ($promotions, $mall, $alternateLanguage) {
            //         $promotions->leftJoin('news_translations', function($join) use ($alternateLanguage){
            //                 $join->on('news.news_id', '=', 'news_translations.news_id');
            //                 $join->where('news_translations.merchant_language_id', '=', $alternateLanguage->language_id);
            //             })
            //             ->leftJoin('keyword_object', function($join) {
            //                 $join->on('news.news_id', '=', 'keyword_object.object_id');
            //                 $join->where('keyword_object.object_type', '=', 'promotion');
            //             })
            //             ->leftJoin('keywords', function($join) use ($retailer) {
            //                 $join->on('keywords.keyword_id', '=', 'keyword_object.keyword_id');
            //                 $join->where('keywords.merchant_id', '=', $retailer->merchant_id);
            //             })
            //             ->where(function($q) use ($keyword) {
            //                 $q->where('news_translations.news_name', 'like', "%$keyword%")
            //                     ->orWhere('news_translations.description', 'like', "%$keyword%")
            //                     ->orWhere('keyword', '=', $keyword);
            //             });
            //     }
            // );

            $promotions = $promotions->groupBy('news.news_id');

            $_promotions = clone($promotions);

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.retailer.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.retailer.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $promotions->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $promotions)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $promotions->skip($skip);

            $promotions->orderBy(DB::raw('RAND()'));

            $totalRec = count($_promotions->get());
            $listOfRec = $promotions->get();

            if (!empty($alternateLanguage) && !empty($listOfRec)) {

                foreach ($listOfRec as $key => $val) {
                    $promotionTranslation = \NewsTranslation::excludeDeleted()
                        ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                        ->where('news_id', $val->news_id)->first();

                    if (!empty($promotionTranslation)) {
                        foreach (['news_name', 'description'] as $field) {
                            //if field translation empty or null, value of field back to english (default)
                            if (isset($promotionTranslation->{$field}) && $promotionTranslation->{$field} !== '') {
                                $val->{$field} = $promotionTranslation->{$field};
                            }
                        }

                        $media = $promotionTranslation->find($promotionTranslation->news_translation_id)
                            ->media_orig()
                            ->first();

                        if (isset($media->path)) {
                            $val->image = $media->path;
                        } else {
                            // back to default image if in the content multilanguage not have image
                            // check the system language
                            $defaultLanguage = $this->getDefaultLanguage($retailer);
                            if ($defaultLanguage !== NULL) {
                                $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                    ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                    ->where('news_id', $val->news_id)->first();

                                // get default image
                                $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                    ->media_orig()
                                    ->first();

                                if (isset($mediaDefaultLanguage->path)) {
                                    $val->image = $mediaDefaultLanguage->path;
                                }
                            }
                        }
                    }
                }
            }

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($_promotions)->count();
            $data->records = $listOfRec;

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
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

    public function getPromotionDetail()
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

            $mallId = OrbitInput::get('mall_id', NULL);
            $promotionId = OrbitInput::get('promotion_id', NULL);

            $validator = Validator::make(
                array(
                    'mall_id' => $mallId,
                    'promotion_id' => $promotionId,
                ),
                array(
                    'mall_id' => 'required',
                    'promotion_id' => 'required',
                )
            );
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $mall = Mall::where('merchant_id', $mallId)->first();

            if (!is_object($mall)) {
                $errorMessage = "Mall id not found";
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $promotion = News::with(['tenants' => function($q) use($mall) {
                    $q->where('merchants.status', 'active');
                    $q->where('merchants.parent_id', $mall->merchant_id);
                }])
                ->select('news.news_id', 'news.news_name', 'news.description as description')
                ->leftJoin('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                ->where(function ($q) use ($mall) {
                    $q->where('merchants.parent_id', '=', $mall->merchant_id)
                      ->orWhere('merchants.merchant_id', '=', $mall->merchant_id);
                })
                ->where('news.object_type', 'promotion')
                ->where('news.news_id', $promotionId)
                ->where('news.status', 'active')
                ->first();

            if (!is_object($promotion)) {
                $errorMessage = "Promotion not found";
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (empty($promotion->image)) {
                $promotion->image = 'mobile-ci/images/default_promotion.png';
            }

            //$promotion->facebook_share_url = $this->getFBShareDummyPage('promotion', $promotion->news_id, $alternateLanguage->language_id);
            
            $_tenants = $promotion->tenants;

            $allTenantInactive = false;

            $inactiveTenant = 0;

            foreach($_tenants as $key => $value)
            {
                if ($value->status === 'inactive') {
                    $inactiveTenant = $inactiveTenant+1;
                }
            }

            if ($inactiveTenant === count($_tenants)) {
                $allTenantInactive = true;
            }

            $_promotion = new \stdclass();
            $_promotion->promotion_id = $promotion->news_id;
            $_promotion->name = $promotion->news_name;
            $_promotion->description = $promotion->description;
            $_promotion->image = $promotion->image;
            $_promotion->all_tenant_inactive = $allTenantInactive;


            if (! empty($alternateLanguage)) {
                $promotionTranslation = \NewsTranslation::excludeDeleted()
                    ->where('merchant_language_id', '=', $alternateLanguage->language_id)
                    ->where('news_id', $promotion->news_id)->first();

                if (!empty($promotionTranslation)) {
                    foreach (['news_name', 'description'] as $field) {
                        //if field translation empty or null, value of field back to english (default)
                        if (isset($promotionTranslation->{$field}) && $promotionTranslation->{$field} !== '') {
                            $promotion->{$field} = $promotionTranslation->{$field};
                        }
                    }

                    $media = $promotionTranslation->find($promotionTranslation->news_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($media->path)) {
                        $promotion->image = $media->path;
                    } else {
                        // back to default image if in the content multilanguage not have image
                        // check the system language
                        $defaultLanguage = $this->getDefaultLanguage($retailer);
                        if ($defaultLanguage !== NULL) {
                            $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                                ->where('merchant_language_id', '=', $defaultLanguage->language_id)
                                ->where('news_id', $promotion->news_id)->first();

                            // get default image
                            $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                                ->media_orig()
                                ->first();

                            if (isset($mediaDefaultLanguage->path)) {
                                $promotion->image = $mediaDefaultLanguage->path;
                            }
                        }
                    }
                }
            }

            $this->response->data = $_promotion;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
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

    public function getPromotionStores()
    {

    }
}