<?php namespace Orbit\Controller\API\v1\Pub\Advert;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use \Exception;
use Config;
use Lang;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use \Carbon\Carbon as Carbon;
use stdClass;
use DB;
use Advert;
use AdvertLinkType;
use AdvertLocation;
use AdvertPlacement;
use Mall;

class AdvertBannerListAPIController extends PubControllerAPI
{
    public function getAdvertBannerList()
    {
        $httpCode = 200;
        try {
            $take = PaginationNumber::parseTakeFromGet('advert');
            $skip = PaginationNumber::parseSkipFromGet();
            $banner_type = OrbitInput::get('banner_type', 'top_banner');
            $location_type = OrbitInput::get('location_type', 'mall');
            $location_id = OrbitInput::get('mall_id', null);
            $country = OrbitInput::get('country');
            $cities = OrbitInput::get('cities');

            $advertHelper = AdvertHelper::create();
            $advertHelper->advertCustomValidator();

            $validator = Validator::make(
                array(
                    'mall_id'       => $location_id,
                    'location_type' => $location_type,
                    'banner_type'   => $banner_type,
                ),
                array(
                    'mall_id'       => 'orbit.empty.mall',
                    'location_type' => 'in:gtm,mall',
                    'banner_type'   => 'in:top_banner,footer_banner',
                ),
                array(
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (empty($location_id) || $location_id === '') {
                $location_type = 'gtm';
                $location_id = '0';
            }

            if ($location_type === 'gtm') {
                $location_id = '0';
            }

            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $prefix = DB::getTablePrefix();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, img.path) as img_url";
            if ($usingCdn) {
                $image = "CASE WHEN (img.cdn_url is null or img.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, img.path) ELSE img.cdn_url END as img_url";
            }

            $advert = DB::table('adverts')
                            ->select(
                                'adverts.advert_id',
                                'adverts.advert_name as title',
                                DB::raw('n.news_name as promotion_name'),
                                DB::raw('c.promotion_name as coupon_name'),
                                'adverts.link_url',
                                'adverts.link_object_id as object_id',
                                DB::raw('alt.advert_type'),
                                'adverts.is_all_location',
                                DB::raw("{$image}"),
                                DB::raw('t.name as store_name'),
                                DB::raw("CASE WHEN alt.advert_type = 'store' and t.name is null THEN t.status
                                            ELSE CASE WHEN alt.advert_type = 'promotion' and  n.news_name is null THEN n.status
                                                ELSE CASE WHEN alt.advert_type = 'coupon' and c.promotion_name is null THEN c.status
                                                    ELSE {$prefix}adverts.status
                                                    END
                                                END
                                            END as status")
                            )
                            ->join('advert_link_types as alt', function ($q) {
                                $q->on(DB::raw('alt.advert_link_type_id'), '=', 'adverts.advert_link_type_id')
                                    ->on(DB::raw('alt.status'), '=', DB::raw("'active'"));
                            })
                            ->leftJoin('advert_locations as al', function ($q) use ($location_type, $location_id, $prefix) {
                                $q->on(DB::raw('al.advert_id'), '=', 'adverts.advert_id')
                                    ->on(DB::raw('al.location_type'), '=', DB::raw("{$this->quote($location_type)}"))
                                    ->on(DB::raw("
                                            (al.location_id = {$this->quote($location_id)} OR `{$prefix}adverts`.`is_all_location` = 'Y')
                                    "), DB::raw(''), DB::raw(''));
                            })
                            ->join('advert_placements as ap', function ($q) use ($banner_type) {
                                $q->on(DB::raw('ap.advert_placement_id'), '=', 'adverts.advert_placement_id')
                                    ->on(DB::raw('ap.placement_type'), '=', DB::raw("{$this->quote($banner_type)}"));
                            })
                            ->leftJoin('media as img', function ($q) {
                                $q->on(DB::raw('img.object_id'), '=', 'adverts.advert_id')
                                    ->on(DB::raw("img.media_name_long"), '=', DB::raw("'advert_image_orig'"));
                            })
                            ->leftJoin('merchants as t', function ($q) {
                                $q->on(DB::raw('t.merchant_id'), '=', 'adverts.link_object_id')
                                    ->on(DB::raw('t.object_type'), '=', DB::raw("'tenant'"))
                                    ->on(DB::raw('t.status'), '=', DB::raw("'active'"));
                            })

                            // For name of promotion, coupon, and store
                            ->leftJoin('news as n', function ($q) {
                                $q->on(DB::raw('n.news_id'), '=', 'adverts.link_object_id');
                            })

                            ->leftJoin('promotions as c', function ($q) {
                                $q->on(DB::raw('c.promotion_id'), '=', 'adverts.link_object_id');
                            })
                            ->having('status', '=', 'active')
                            ->whereRaw("CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '{$timezone}') between {$prefix}adverts.start_date and {$prefix}adverts.end_date");

            OrbitInput::get('country', function($country) use ($advert)
            {
                // Join to country and cities
                $advert->leftJoin('countries', 'countries.country_id', '=', 'adverts.country_id');
                $advert->where('countries.name', $country);
            });

            // Filter in mall level, use advert location and country.
            if ($location_type == 'mall') {
                $advert->where(function ($query) use ($location_id){
                    $query->where(DB::raw('al.location_id'), $location_id)
                          ->orWhere('adverts.is_all_location', '=', 'Y');
                });
            } else {
                $advert->where(function ($query) use ($location_type) {
                    $query->where(DB::raw('al.location_type'), $location_type)
                        ->orWhere('is_all_location', 'Y');
                });

                // Filter city in gtm level
                OrbitInput::get('cities', function($cities) use ($advert)
                {
                    // Join to advert_cities
                    $advert->leftJoin('advert_cities', 'advert_cities.advert_id', '=', 'adverts.advert_id');
                    $advert->leftJoin('mall_cities', 'mall_cities.mall_city_id', '=', 'advert_cities.mall_city_id');
                    $advert->where(function ($query) use ($cities){
                        $query->whereIn('mall_cities.city', (array)$cities)
                              ->orWhere('adverts.is_all_city', '=', 'Y');
                    });
                });
            }

            $advert->groupBy('adverts.advert_id');

            $slideshow = $advert->get();

            $slide_fix = array();
            $random = array();

            // random process
            if (! empty($slideshow)) {
                if (count($slideshow) < $take) {
                    $take = count($slideshow);
                }

                $slides = array();
                $listSlide = array_rand($slideshow, $take);
                if (count($listSlide) > 1) {
                    foreach ($listSlide as $key => $value) {
                        array_push($slides, $slideshow[$value]);
                    }

                    $keys = array_keys($slides);
                    shuffle($keys);
                    foreach ($keys as $key) {
                        array_push($random, $slides[$key]);
                    }
                } else {
                    $random[] = $slideshow[$listSlide];
                }
            } else {
                $random = null;
            }

            $this->response->data = $random;
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
            $this->response->data = null;

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
        } catch (\Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}