<?php namespace Orbit\Controller\API\v1\Pub\Advert;

use OrbitShop\API\v1\ControllerAPI;
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

class AdvertListAPIController extends ControllerAPI
{
    public function getAdvertList()
    {
        $httpCode = 200;
        try {
            $take = PaginationNumber::parseTakeFromGet('advert');
            $skip = PaginationNumber::parseSkipFromGet();
            $banner_type = OrbitInput::get('banner_type', 'top_banner');
            $location_type = OrbitInput::get('location_type', 'mall');
            $location_id = OrbitInput::get('mall_id', null);

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

            $now = Carbon::now('Asia/Jakarta'); // now with jakarta timezone
            $prefix = DB::getTablePrefix();

            $advert = DB::table('adverts')
                            ->select(
                                'adverts.advert_id',
                                'adverts.advert_name as title',
                                'adverts.link_url',
                                'adverts.link_object_id as object_id',
                                DB::raw('alt.advert_type'),
                                DB::raw('img.path as img_url'),
                                DB::raw('t.name as store_name')
                            )
                            ->join('advert_link_types as alt', function ($q) {
                                $q->on(DB::raw('alt.advert_link_type_id'), '=', 'adverts.advert_link_type_id')
                                    ->on(DB::raw('alt.status'), '=', DB::raw("'active'"));
                            })
                            ->join('advert_locations as al', function ($q) use ($location_type, $location_id) {
                                $q->on(DB::raw('al.advert_id'), '=', 'adverts.advert_id')
                                    ->on(DB::raw('al.location_type'), '=', DB::raw("{$this->quote($location_type)}"))
                                    ->on(DB::raw('al.location_id'), '=', DB::raw("{$this->quote($location_id)}"));
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
                            ->where('adverts.status', 'active')
                            ->whereRaw("{$this->quote($now)} between {$prefix}adverts.start_date and {$prefix}adverts.end_date")
                            ->orderBy(DB::raw('RAND()'))
                            ->take($take)
                            ->skip($skip)
                            ->get();

            $this->response->data = $advert;
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
        } catch (Exception $e) {
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