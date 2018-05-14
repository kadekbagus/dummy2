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

class FeaturedSlotListAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    /**
     * GET - get list featured slot
     * @author firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string            `country_id`                    (optional) - country id
     * @param string            `city`                          (optional) - city
     * @param string            `start_date`                    (optional) - start_date
     * @param string            `end_date`                      (optional) - end_date
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function getListFeaturedSlot()
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

            $mallId = OrbitInput::get('mall_id', 0);
            $objectType = OrbitInput::get('object_type');
            $startDate = OrbitInput::get('start_date');
            $endDate = OrbitInput::get('end_date');

            $validator = Validator::make(
                array(
                    'mall_id' => $mallId,
                    'object_type' => $objectType,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ),
                array(
                    'mall_id' => 'required',
                    'object_type' => 'required',
                    'start_date' => 'required|date',
                    'end_date' => 'required|date',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            // Check advert slot was taken or not
            $advertSlot = AdvertSlotLocation::select(
                                    'adverts.advert_id',
                                    'adverts.advert_name',
                                    'advert_slot_locations.slot_number',
                                    'advert_slot_locations.slot_type',
                                    'adverts.link_object_id',
                                    'advert_slot_locations.location_id',
                                    'advert_slot_locations.start_date',
                                    'advert_slot_locations.end_date'
                                )
                                ->join('adverts', 'adverts.advert_id', '=', 'advert_slot_locations.advert_id')
                                ->where('adverts.status', 'active')
                                ->where('advert_slot_locations.status', 'active')
                                ->where('advert_slot_locations.location_id', $mallId)
                                ->where('advert_slot_locations.slot_type', $objectType)
                                ->where('advert_slot_locations.start_date', '<=', $endDate)
                                ->where('advert_slot_locations.end_date', '>=', $startDate);

            OrbitInput::get('country_id', function($countryId) use ($advertSlot)
            {
                $advertSlot->where('advert_slot_locations.country_id', $countryId);
            });

            OrbitInput::get('city', function($city) use ($advertSlot)
            {
                $advertSlot->where('advert_slot_locations.city', $city);
            });

            $advertSlot = $advertSlot->groupBy('slot_number')
                                     ->orderBy('advert_slot_locations.start_date','asc')
                                     ->get();

            // get image advert
            if (count($advertSlot) > 0) {

                // set image each advert
                foreach ($advertSlot as $key => $val) {

                    if ($val->slot_type == 'promotion' || $val->slot_type == 'news') {

                        $advertImage = News::select(
                                        'news.news_name as advert_name',
                                        'news.news_id as news_id',
                                        'media.path as image'
                                    )
                                    ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                                    ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                                    ->leftJoin('news_translations as default_translation', function ($q) use ($prefix){
                                        $q->on(DB::raw("default_translation.news_id"), '=', 'news.news_id')
                                          ->on(DB::raw("default_translation.merchant_language_id"), '=', 'languages.language_id');
                                    })
                                    ->leftJoin('media', function ($q) use ($prefix){
                                        $q->on('media.object_id', '=', DB::raw("default_translation.news_translation_id"))
                                          ->on('media.media_name_long', '=', DB::raw('"news_translation_image_orig"'));
                                    })
                                    ->where('news.news_id', $val->link_object_id)
                                    ->first();

                    } elseif ($val->slot_type == 'coupon') {

                        $advertImage = Coupon::select(
                                        'promotions.promotion_name as advert_name',
                                        'promotions.promotion_id as promotion_id',
                                        'media.path as image'
                                    )
                                    ->join('campaign_account', 'campaign_account.user_id', '=', 'promotions.created_by')
                                    ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')
                                    ->leftJoin('coupon_translations as default_translation', function ($q) {
                                        $q->on(DB::raw('default_translation.promotion_id'), '=', 'promotions.promotion_id')
                                          ->on(DB::raw('default_translation.merchant_language_id'), '=', 'languages.language_id');
                                    })
                                    ->leftJoin('media', function ($q) use ($prefix){
                                        $q->on('media.object_id', '=', DB::raw("default_translation.coupon_translation_id"))
                                          ->on('media.media_name_long', '=', DB::raw('"coupon_translation_image_orig"'));
                                    })
                                    ->where('promotions.promotion_id', $val->link_object_id)
                                    ->first();

                    } elseif ($val->slot_type == 'store') {

                        $advertImage = Media::select('path as image', 'merchants.name as advert_name')
                                                ->join('merchants', 'merchants.merchant_id', '=', 'media.object_id')
                                                ->where('object_id', $val->link_object_id)
                                                ->where('media_name_long', 'retailer_logo_orig')
                                                ->first();

                    }


                    if (! empty($advertImage)) {
                        $advertSlot[$key]->advert_image = $advertImage->image;
                        $advertSlot[$key]->advert_name = $advertImage->advert_name;
                    } else {
                        $advertSlot[$key]->advert_image = '';
                        $advertSlot[$key]->advert_name = '';
                    }

                }
            }


            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->data = $advertSlot;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
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
        Event::fire('orbit.mall.getsearchmallcountry.before.render', array($this, &$output));

        return $output;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}