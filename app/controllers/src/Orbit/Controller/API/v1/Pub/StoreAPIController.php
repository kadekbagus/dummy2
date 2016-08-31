<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
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
use Config;
use Mall;
use News;
use Tenant;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;

class StoreAPIController extends ControllerAPI
{
    /**
     * GET - get all store in all mall, group by name
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getStoreList()
    {
        $httpCode = 200;
        try {
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $usingDemo = Config::get('orbit.is_demo', FALSE);

            $prefix = DB::getTablePrefix();

            $store = Tenant::select('merchants.merchant_id', 'merchants.name')
                ->join(DB::raw("(select merchant_id, status, parent_id from {$prefix}merchants where object_type = 'mall') as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                ->where('merchants.status', 'active')
                ->whereRaw("oms.status = 'active'")
                ->groupBy('merchants.name')
                ->orderBy($sort_by, $sort_mode);

            OrbitInput::get('filter_name', function ($filterName) use ($store, $prefix) {
                if (! empty($filterName)) {
                    if ($filterName === '#') {
                        $store->whereRaw("SUBSTR({$prefix}merchants.name,1,1) not between 'a' and 'z'");
                    } else {
                        $filter = explode("-", $filterName);
                        $store->whereRaw("SUBSTR({$prefix}merchants.name,1,1) between {$this->quote($filter[0])} and {$this->quote($filter[1])}");
                    }
                }
            });

            OrbitInput::get('keyword', function ($keyword) use ($store, $prefix) {
                if (! empty($keyword)) {
                    $store = $store->leftJoin('keyword_object', 'merchants.merchant_id', '=', 'keyword_object.object_id')
                                ->leftJoin('keywords', 'keyword_object.keyword_id', '=', 'keywords.keyword_id')
                                ->where(function($query) use ($keyword, $prefix)
                                {
                                    $word = explode(" ", $keyword);
                                    foreach ($word as $key => $value) {
                                        if (strlen($value) === 1 && $value === '%') {
                                            $query->orWhere(function($q) use ($value, $prefix){
                                                $q->whereRaw("{$prefix}merchants.name like '%|{$value}%' escape '|'")
                                                  ->orWhereRaw("{$prefix}merchants.description like '%|{$value}%' escape '|'")
                                                  ->orWhereRaw("{$prefix}keywords.keyword like '%|{$value}%' escape '|'");
                                            });
                                        } else {
                                            $query->orWhere(function($q) use ($value, $prefix){
                                                $q->where('merchants.name', 'like', '%' . $value . '%')
                                                  ->orWhere('merchants.description', 'like', '%' . $value . '%')
                                                  ->orWhere('keywords.keyword', 'like', '%' . $value . '%');
                                            });
                                        }
                                    }
                                });
                }
            });

            $_store = clone $store;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $store->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $store->skip($skip);

            $liststore = $store->get();
            $count = RecordCounter::create($_store)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($liststore);
            $this->response->data->records = $liststore;
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

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * GET - get mall list after click store name
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string filter_name
     * @param string store_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMallStoreList()
    {
        $httpCode = 200;
        try {
            $sort_by = OrbitInput::get('sortby', 'merchants.name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $storename = OrbitInput::get('store_name');
            $keyword = OrbitInput::get('keyword');

            $validator = Validator::make(
                array(
                    'store_name' => $storename,
                ),
                array(
                    'store_name' => 'required',
                ),
                array(
                    'required' => 'Store name is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            // Query without searching keyword
            $mall = Mall::select('merchants.merchant_id', 'merchants.name', 'merchants.ci_domain', 'merchants.city', 'merchants.description', DB::raw("CONCAT({$prefix}merchants.ci_domain, '/customer/tenant?id=', oms.merchant_id) as store_url"))
                    ->join(DB::raw("(select merchant_id, `name`, parent_id from {$prefix}merchants where name = {$this->quote($storename)} and status = 'active') as oms"), DB::raw('oms.parent_id'), '=', 'merchants.merchant_id')
                    ->active();

            // Query list mall based on keyword. Handling description and keyword can be different with other stores
            if (! empty($keyword)) {
                $words = explode(" ", $keyword);
                $keywordSql = " 1=1 ";
                foreach ($words as $key => $value) {
                    if (strlen($value) === 1 && $value === '%') {
                        $keywordSql .= " or {$prefix}merchants.name like '%|{$value}%' escape '|' or {$prefix}merchants.description like '%|{$value}%' escape '|' or {$prefix}keywords.keyword like '%|{$value}%' escape '|' ";
                    } else {
                        // escaping the query
                        $word = '%' . $value . '%';
                        $value = $this->quote($word);
                        $keywordSql .= " or {$prefix}merchants.name like {$value} or {$prefix}merchants.description like {$value} or {$prefix}keywords.keyword like {$value} ";
                    }
                }

                $mall = Mall::select('merchants.merchant_id', 'merchants.name', 'merchants.ci_domain', 'merchants.city', 'merchants.description', DB::raw("CONCAT({$prefix}merchants.ci_domain, '/customer/tenant?id=', oms.merchant_id) as store_url"))
                        ->join(DB::raw("( select {$prefix}merchants.merchant_id, name, parent_id from {$prefix}merchants
                                            left join {$prefix}keyword_object on {$prefix}merchants.merchant_id = {$prefix}keyword_object.object_id
                                            left join {$prefix}keywords on {$prefix}keyword_object.keyword_id = {$prefix}keywords.keyword_id
                                            where name = {$this->quote($storename)}
                                            and {$prefix}merchants.status = 'active'
                                            and (" . $keywordSql . ")
                                        ) as oms"), DB::raw('oms.parent_id'), '=', 'merchants.merchant_id')
                        ->active();
            }

            $mall = $mall->groupBy('merchants.merchant_id')->orderBy($sort_by, $sort_mode);

            $_mall = clone $mall;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $mall->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $mall->skip($skip);

            $listmall = $mall->get();
            $count = RecordCounter::create($_mall)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listmall);
            $this->response->data->records = $listmall;
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

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * GET - get all detail store in all mall, group by name
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getStoreDetail()
    {
        $httpCode = 200;
        try {
            $storename = OrbitInput::get('store_name');

            $validator = Validator::make(
                array(
                    'store_name' => $storename,
                ),
                array(
                    'store_name' => 'required',
                ),
                array(
                    'required' => 'Store name is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $store = Tenant::select('merchants.merchant_id',
                                'merchants.name',
                                'merchants.description',
                                'merchants.url',
                                DB::Raw("COUNT({$prefix}merchants.merchant_id) as total_location")
                            )
                ->with(['categories' => function ($q) {
                        $q->select(
                                'category_name'
                            );
                    }, 'mediaLogo' => function ($q) {
                        $q->select(
                                'media.path',
                                'media.object_id'
                            );
                    }, 'mediaImage' => function ($q) {
                        $q->select(
                                'media.path',
                                'media.object_id'
                            );
                    }])
                ->join(DB::raw("(select merchant_id, status, parent_id from {$prefix}merchants where object_type = 'mall') as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                ->where('merchants.status', 'active')
                ->whereRaw("oms.status = 'active'")
                ->where('merchants.name', $storename)
                ->groupBy('merchants.name')
                ->get();

            $this->response->data = $store;
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

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * GET - get mall detail list after click store name
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string filter_name
     * @param string store_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMallDetailStore()
    {
        $httpCode = 200;
        try {
            $sort_by = OrbitInput::get('sortby', 'merchants.name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $storename = OrbitInput::get('store_name');
            $keyword = OrbitInput::get('keyword');

            $validator = Validator::make(
                array(
                    'store_name' => $storename,
                ),
                array(
                    'store_name' => 'required',
                ),
                array(
                    'required' => 'Store name is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            // Query without searching keyword
            $mall = Mall::select('merchants.merchant_id',
                                    'merchants.name',
                                    'merchants.ci_domain',
                                    'merchants.city',
                                    'merchants.description',
                                    DB::raw("CONCAT({$prefix}merchants.ci_domain, '/customer/tenant?id=', oms.merchant_id) as store_url"))
                    ->with(['tenants' => function ($q) use ($prefix, $storename) {
                            $q->select('merchants.merchant_id',
                                        'merchants.name as title',
                                        'merchants.phone',
                                        'merchants.url',
                                        'merchants.description',
                                        'merchants.parent_id',
                                        DB::raw("(CASE WHEN unit = '' THEN {$prefix}objects.object_name ELSE CONCAT({$prefix}objects.object_name, \" - \", unit) END) AS location")
                                    )
                              ->join('objects', 'objects.object_id', '=', 'merchants.floor_id')
                              ->where('objects.object_type', 'floor')
                              ->where('merchants.name', $storename)
                              ->where('merchants.status', 'active')
                              ->with('mediaMap')
                              ->with(['categories' => function ($q) {
                                    $q->select(
                                            'category_name'
                                        );
                                }, 'mediaMap' => function ($q) {
                                    $q->select(
                                            'media.object_id',
                                            'media.path'
                                        );
                                }]);
                        }]);

            // Query list mall based on keyword. Handling description and keyword can be different with other stores
            if (! empty($keyword)) {
                $words = explode(" ", $keyword);
                $keywordSql = " 1=1 ";
                foreach ($words as $key => $value) {
                    if (strlen($value) === 1 && $value === '%') {
                        $keywordSql .= " or {$prefix}merchants.name like '%|{$value}%' escape '|' or {$prefix}merchants.description like '%|{$value}%' escape '|' or {$prefix}keywords.keyword like '%|{$value}%' escape '|' ";
                    } else {
                        // escaping the query
                        $word = '%' . $value . '%';
                        $value = $this->quote($word);
                        $keywordSql .= " or {$prefix}merchants.name like {$value} or {$prefix}merchants.description like {$value} or {$prefix}keywords.keyword like {$value} ";
                    }
                }

                $mall = $mall->join(DB::raw("( select {$prefix}merchants.merchant_id, name, parent_id from {$prefix}merchants
                                            left join {$prefix}keyword_object on {$prefix}merchants.merchant_id = {$prefix}keyword_object.object_id
                                            left join {$prefix}keywords on {$prefix}keyword_object.keyword_id = {$prefix}keywords.keyword_id
                                            where name = {$this->quote($storename)}
                                            and {$prefix}merchants.status = 'active'
                                            and (" . $keywordSql . ")
                                        ) as oms"), DB::raw('oms.parent_id'), '=', 'merchants.merchant_id')
                            ->active();
            } else {
                $mall = $mall->join(DB::raw("(select merchant_id, `name`, parent_id from {$prefix}merchants where name = {$this->quote($storename)} and status = 'active') as oms"), DB::raw('oms.parent_id'), '=', 'merchants.merchant_id')
                            ->active();
            }

            $mall = $mall->groupBy('merchants.merchant_id')->orderBy($sort_by, $sort_mode);

            $_mall = clone $mall;

            $take = PaginationNumber::parseTakeFromGet('retailer');
            $mall->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $mall->skip($skip);

            $listmall = $mall->get();
            $count = RecordCounter::create($_mall)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listmall);
            $this->response->data->records = $listmall;
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

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * GET - get campaign store list after click store name
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string filter_name
     * @param string store_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCampaignStoreList()
    {
        $httpCode = 200;
        try {
            $sort_by = OrbitInput::get('sortby', 'merchants.name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $store_name = OrbitInput::get('store_name');
            $keyword = OrbitInput::get('keyword');

            $validator = Validator::make(
                array(
                    'store_name' => $store_name,
                ),
                array(
                    'store_name' => 'required',
                ),
                array(
                    'required' => 'Store name is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            // get news promotion list
            $news_promotion = DB::table('news')
                                ->select(
                                        DB::Raw("t.merchant_id as tenant_id"),
                                        DB::Raw("{$prefix}news.news_id as campaign_id"),
                                        DB::Raw("{$prefix}news.news_name as campaign_name"),
                                        DB::Raw("{$prefix}news.object_type as campaign_type"),
                                        DB::Raw("img.path as campaign_image")
                                    )
                                ->leftJoin('news_merchant as nm', DB::Raw('nm.news_id'), '=', 'news.news_id')
                                ->leftJoin('merchants as t', DB::Raw('t.merchant_id'), '=', DB::Raw('nm.merchant_id'))
                                ->leftJoin('merchants as m', DB::Raw('m.merchant_id'), '=', DB::Raw('t.parent_id'))
                                ->leftJoin('media as img', DB::Raw('img.object_id'), '=', 'news.news_id')
                                ->where(DB::Raw('t.status'), 'active')
                                ->where(DB::Raw('t.name'), $store_name)
                                ->where(DB::Raw('m.status'), 'active')
                                ->whereIn(DB::Raw('img.object_name'), ['news_translation', 'promotion_translation'])
                                ->where(DB::Raw('img.media_name_long'), 'like', '%_orig');

            // get coupon list
            $coupon = DB::table('promotions')
                                ->select(
                                        DB::Raw("t.merchant_id as tenant_id"),
                                        DB::Raw("{$prefix}promotions.promotion_id as campaign_id"),
                                        DB::Raw("{$prefix}promotions.promotion_name as campaign_name"),
                                        DB::Raw("'coupon' as campaign_type"),
                                        DB::Raw("img.path as campaign_image")
                                    )
                                ->leftJoin('promotion_retailer as pr', DB::Raw('pr.promotion_id'), '=', 'promotions.promotion_id')
                                ->leftJoin('merchants as t', DB::Raw('t.merchant_id'), '=', DB::Raw('pr.retailer_id'))
                                ->leftJoin('merchants as m', DB::Raw('m.merchant_id'), '=', DB::Raw('t.parent_id'))
                                ->leftJoin('media as img', DB::Raw('img.object_id'), '=', 'promotions.promotion_id')
                                ->where(DB::Raw('t.status'), 'active')
                                ->where(DB::Raw('t.name'), $store_name)
                                ->where(DB::Raw('m.status'), 'active')
                                ->where(DB::Raw('img.object_name'), 'coupon_translation')
                                ->where(DB::Raw('img.media_name_long'), 'like', '%_orig');

            $union_campaign = $news_promotion->union($coupon);

            $querySql = $union_campaign->toSql();

            $campaign = DB::table(DB::Raw("({$querySql}) as campaign"))->mergeBindings($union_campaign);

            $_campaign = clone $campaign;

            $take = PaginationNumber::parseTakeFromGet('campaign');
            $campaign->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $campaign->skip($skip);

            $listcampaign = $campaign->get();

            $this->response->data = new stdClass();
            $this->response->data->total_records = count($_campaign->get());
            $this->response->data->returned_records = count($listcampaign);
            $this->response->data->records = $listcampaign;
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

        $output = $this->render($httpCode);

        return $output;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}