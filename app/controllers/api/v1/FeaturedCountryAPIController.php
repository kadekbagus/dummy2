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

class FeaturedCountryAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    /**
     * GET - featured advert city
     * @author shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string            `advert_id`
     * @param string            `featured_location`
     * @param string            `name_like`                     (optional) - search by name
     * @param string            `exclude_cities`                (optional) - exclude city
     * @param string            `sort_by`                       (optional) - column order by
     * @param string            `sort_mode`                     (optional) - asc or desc
     * @param integer           `take`                          (optional) - limit
     * @param integer           `skip`                          (optional) - limit
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function getFeaturedCountry()
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

            $advertId = OrbitInput::get('advert_id');

            $validator = Validator::make(
                array(
                    'advert_id' => $advertId
                ),
                array(
                    'advert_id' => 'required'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.mall_country.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.mall_country.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $prefix = DB::getTablePrefix();
            $advert = Advert::join('advert_link_types', 'advert_link_types.advert_link_type_id', '=', 'adverts.advert_link_type_id')
                            ->where('advert_id', $advertId)
                            ->first();

            switch ($advert->advert_type) {
                case 'news':
                    $cities = NewsMerchant::select(DB::raw("IF({$prefix}news_merchant.object_type = 'retailer', oms.country_id, {$prefix}merchants.country_id) as country_id, IF({$prefix}news_merchant.object_type = 'retailer', oms.country, {$prefix}merchants.country) as country"))
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->join('news', function ($q) {
                                        $q->on('news_merchant.news_id', '=', 'news.news_id')
                                          ->on('news.object_type', '=', DB::raw("'news'"));
                                    })
                                    ->where('news_merchant.news_id', '=', $advert->link_object_id)
                                    ->groupBy('country_id');
                    break;

                case 'promotion':
                    $cities = NewsMerchant::select(DB::raw("IF({$prefix}news_merchant.object_type = 'retailer', oms.country_id, {$prefix}merchants.country_id) as country_id, IF({$prefix}news_merchant.object_type = 'retailer', oms.country, {$prefix}merchants.country) as country"))
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->join('news', function ($q) {
                                        $q->on('news_merchant.news_id', '=', 'news.news_id')
                                          ->on('news.object_type', '=', DB::raw("'promotion'"));
                                    })
                                    ->where('news_merchant.news_id', '=', $advert->link_object_id)
                                    ->groupBy('country_id');
                    break;

                case 'coupon':
                    $cities = PromotionRetailer::select(DB::raw("IF({$prefix}merchants.object_type = 'tenant', oms.country_id, {$prefix}merchants.country_id) as country_id, IF({{$prefix}merchants.object_type = 'tenant', oms.country, {$prefix}merchants.country) as country"))
                                    ->join('promotions', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->where('promotions.promotion_id', '=', $advert->link_object_id)
                                    ->groupBy('country_id');
                    break;

                case 'store':
                    $tenant = Tenant::select('name', 'country')->where('merchant_id', $advert->link_object_id)->first();
                    $cities = Tenant::select(DB::raw("oms.country_id, oms.country"))
                                ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                ->where('merchants.status', '=', 'active')
                                ->where(DB::raw('oms.status'), '=', 'active')
                                ->where('merchants.name', '=', $tenant->name)
                                ->groupBy('country_id');
                    break;
            }

            // Filter country by name
            OrbitInput::get('name_like', function ($nameLike) use ($cities) {
                $nameLike = substr($this->quote($nameLike), 1, -1);
                $cities->havingRaw("country like '%{$nameLike}%'");
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_cities = clone $cities;

            // Get the take args
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
            $cities->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $cities->skip($skip);

            // Default sort by
            $sortBy = 'city';
            // Default sort mode
            $sortMode = 'asc';
            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'city' => 'city'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $cities->orderBy($sortBy, $sortMode);

            $totalCities = RecordCounter::create($_cities)->count();
            $listCities = $cities->get();

            $data = new stdclass();
            $data->total_records = $totalCities;
            $data->returned_records = count($listCities);
            $data->records = $listCities;

            $this->response->data = $data;
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