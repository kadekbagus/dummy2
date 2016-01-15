<?php
/**
 * An API controller for managing Campaign report.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class CampaignReportAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'mall customer service'];

    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    /**
     * GET - Campaign Report List
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`                (optional) - Column order by. Valid value: updated_date, created_at, campaign_name, campaign_type, location, campaign_dates, pages_views, views, clicks, daily, estimated_total, spending, status
     * @param string   `sortmode`              (optional) - ASC or DESC
     * @param string   `redeemed_by            (optional) - Filtering redeemed by cs or tenant only
     * @param integer  `take`                  (optional) - Limit
     * @param integer  `skip`                  (optional) - Limit offset
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCampaignReport()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.campaignreport.getcampaignreport.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.campaignreport.getcampaignreport.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.campaignreport.getcampaignreport.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_coupon_report')) {
                Event::fire('orbit.campaignreport.getcampaignreport.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_coupon_report');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.campaignreport.getcampaignreport.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $current_mall = OrbitInput::get('current_mall');
            $start_date = OrbitInput::get('start_date');
            $end_date = OrbitInput::get('end_date');
            $sort_by = OrbitInput::get('sortby');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'current_mall' => $current_mall,
                    'sort_by' => $sort_by,
                ),
                array(
                    'current_mall' => 'required|orbit.empty.mall',
                    'sort_by' => 'in:updated_date,created_at,campaign_name,campaign_type,location,campaign_dates,pages_views,views,clicks,daily,estimated_total,spending,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.campaignreportgeneral_sortby'),
                )
            );

            Event::fire('orbit.campaignreport.getcampaignreport.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.campaignreport.getcampaignreport.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.coupon.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.coupon.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $now = date('Y-m-d H:i:s');
            $tablePrefix = DB::getTablePrefix();

            //get total cost news
            $news = DB::table('news')->selectraw(DB::raw("{$tablePrefix}news.news_id AS campaign_id, news_name AS campaign_name, {$tablePrefix}news.object_type AS campaign_type, COUNT({$tablePrefix}news_merchant.news_merchant_id) AS total_tenant, merchants2.name AS mall_name, {$tablePrefix}news.begin_date, {$tablePrefix}news.end_date, {$tablePrefix}campaign_price.base_price ,COUNT({$tablePrefix}news_merchant.news_merchant_id) * {$tablePrefix}campaign_price.base_price * (DATEDIFF( {$tablePrefix}news.end_date, {$tablePrefix}news.begin_date) + 1) AS estimated_total, {$tablePrefix}news.status"))
                        ->join('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->join('campaign_price', 'campaign_price.campaign_id', '=', 'news.news_id')
                        ->join('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->join('merchants as merchants2', 'news.mall_id', '=', DB::raw('merchants2.merchant_id'))
                        ->where('merchants.status', '=', 'active')
                        ->where('news.mall_id', '=', $current_mall)
                        ->where(function ($q) use ($start_date, $end_date, $tablePrefix) {
                            $q->whereRaw("{$tablePrefix}news.begin_date between ? and ?", [$start_date, $end_date])
                            ->orWhereRaw("{$tablePrefix}news.end_date between ? and ?", [$start_date, $end_date]);
                        })
                        ->where('campaign_price.campaign_type', '=', 'news')
                        ->where('news.object_type', '=', 'news')
                        ->groupBy('news.news_id')
                        ;

            $promotions = DB::table('news')->selectraw(DB::raw("{$tablePrefix}news.news_id AS campaign_id, news_name AS campaign_name, {$tablePrefix}news.object_type AS campaign_type, COUNT({$tablePrefix}news_merchant.news_merchant_id) AS total_tenant, merchants2.name AS mall_name, {$tablePrefix}news.begin_date, {$tablePrefix}news.end_date, {$tablePrefix}campaign_price.base_price ,COUNT({$tablePrefix}news_merchant.news_merchant_id) * {$tablePrefix}campaign_price.base_price * (DATEDIFF({$tablePrefix}news.end_date, {$tablePrefix}news.begin_date) + 1) AS estimated_total, {$tablePrefix}news.status"))
                        ->join('news_merchant', 'news_merchant.news_id', '=', 'news.news_id')
                        ->join('campaign_price', 'campaign_price.campaign_id', '=', 'news.news_id')
                        ->join('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                        ->join('merchants as merchants2', 'news.mall_id', '=', DB::raw('merchants2.merchant_id'))
                        ->where('merchants.status', '=', 'active')
                        ->where('news.mall_id', '=', $current_mall)
                        ->where(function ($q) use ($start_date, $end_date, $tablePrefix) {
                            $q->whereRaw("{$tablePrefix}news.begin_date between ? and ?", [$start_date, $end_date])
                            ->orWhereRaw("{$tablePrefix}news.end_date between ? and ?", [$start_date, $end_date]);
                        })
                        ->where('campaign_price.campaign_type', '=', 'promotion')
                        ->where('news.object_type', '=', 'promotion')
                        ->groupBy('news.news_id')
                        ;

            $coupons = DB::table('promotions')->selectraw(DB::raw("{$tablePrefix}promotions.promotion_id AS campaign_id, promotion_name AS campaign_name, IF(1=1,'coupon', '') AS campaign_type, COUNT({$tablePrefix}promotion_retailer.promotion_retailer_id) AS total_tenant, merchants2.name AS mall_name, {$tablePrefix}promotions.begin_date, {$tablePrefix}promotions.end_date, {$tablePrefix}campaign_price.base_price ,COUNT({$tablePrefix}promotion_retailer.promotion_retailer_id) * {$tablePrefix}campaign_price.base_price * (DATEDIFF({$tablePrefix}promotions.end_date, {$tablePrefix}promotions.begin_date) + 1) AS estimated_total, {$tablePrefix}promotions.status"))
                        ->join('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                        ->join('campaign_price', 'campaign_price.campaign_id', '=', 'promotions.promotion_id')
                        ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                        ->join('merchants as merchants2', 'promotions.merchant_id', '=', DB::raw('merchants2.merchant_id'))
                        ->where('merchants.status', '=', 'active')
                        ->where('promotions.merchant_id', '=', $current_mall)
                        ->where(function ($q) use ($start_date, $end_date, $tablePrefix) {
                            $q->whereRaw("{$tablePrefix}promotions.begin_date between ? and ?", [$start_date, $end_date])
                            ->orWhereRaw("{$tablePrefix}promotions.end_date between ? and ?", [$start_date, $end_date]);
                        })
                        ->where('campaign_price.campaign_type', '=', 'coupon')
                        ->groupBy('promotions.promotion_id')
                        ;

            $dataCampaign = $news->unionAll($promotions)->unionAll($coupons);

            $sql = $dataCampaign->toSql();
            foreach($dataCampaign->getBindings() as $binding)
            {
              $value = is_numeric($binding) ? $binding : "'" . $binding . "'";
              $sql = preg_replace('/\?/', $value, $sql, 1);
            }

            // Make union result subquery
            $dataCampaign = DB::table(DB::raw('(' . $sql . ') as a'));


            // Filter by campaign name
            OrbitInput::get('campaign_name', function($campaign_name) use ($dataCampaign) {
                $dataCampaign->where('campaign_name', 'like', "%$campaign_name%");
            });

            // Filter by campaign type
            OrbitInput::get('campaign_type', function($campaign_type) use ($dataCampaign) {
                $dataCampaign->where('campaign_type', $campaign_type);
            });

            // Filter by tenant
            // OrbitInput::get('tenant', function($tenant) use ($dataCampaign) {
                // $dataCampaign->where('campaign_name', 'like', "%$campaign_name%");
            // });

            // Filter by mall
            OrbitInput::get('mall_name', function($mall_name) use ($dataCampaign) {
                $dataCampaign->where('mall_name', $mall_name);
            });

            // Filter by campaign status
            OrbitInput::get('status', function($status) use ($dataCampaign) {
                $status = (array)$status;
                $dataCampaign->whereIn('status', $status);
            });

            dd($dataCampaign->get());

            // Clone the query builder which still does not include the take,
            $_coupons = clone $coupons;

            $_couponsCountReddem = clone $coupons;
            $_couponsCountIssued = clone $coupons;

            $_coupons->select('promotions.promotion_id');

            $_couponsCountReddem = $_couponsCountReddem->get();
            $_couponsCountIssued = $_couponsCountIssued->groupBy('promotions.promotion_id')->get();

            // Get total reddem
            $totalRedeemed = 0 ;
            if (isset($_couponsCountReddem) && count($_couponsCountReddem) > 0){
                foreach ($_couponsCountReddem as $key => $valReddem) {
                    $totalRedeemed = $totalRedeemed + $valReddem->total_redeemed;
                }
            }

            // Get total issued coupon
            $totalIssued = 0;
            if (isset($_couponsCountIssued) && count($_couponsCountIssued) > 0){
                foreach ($_couponsCountIssued as $key => $valIssued) {
                    $totalIssued = $totalIssued + $valIssued->total_issued;
                }
            }

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
            $coupons->take($take);

            // skip, and order by
            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $coupons)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $coupons->skip($skip);

            // Default sort by
            $sortBy = 'promotions.status';

            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'campaign_name' => 'promotions.promotion_id',
                    'campaign_type' => 'mall_id',
                    'tenant'        => 'is_coupon',
                    'mall_bname'    => 'promotions.promotion_name',
                    'begin_date'    => 'promotions.begin_date',
                    'end_date'      => 'promotions.end_date',
                    'page_views'    => 'is_auto_issue_on_signup',

                    'total_issued'              => 'total_issued',
                    'redeem_retailer_id'        => 'redeem_retailer_id',
                    'retailer_name'             => 'retailer_name',
                    'total_redeemed'            => 'total_redeemed',
                    'coupon_status'             => 'coupon_status',
                    'status'                    => 'status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            // sort by status first
            if ($sortBy !== 'promotions.status') {
                $coupons->orderBy('promotions.status', 'asc');
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            $coupons->orderBy($sortBy, $sortMode);

            // also to sort tenant name
            if ($sortBy !== 'retailer_name') {
                $coupons->orderBy('retailer_name', 'asc');
            }

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return ['builder' => $coupons, 'count' => RecordCounter::create($_coupons)->count()];
            }

            $totalCoupons = RecordCounter::create($_coupons)->count();
            $listOfCoupons = $coupons->get();

            $data = new stdclass();
            $data->total_records = $totalCoupons;
            $data->returned_records = count($listOfCoupons);
            $data->records = $listOfCoupons;
            $data->total_redeemed = $totalRedeemed;
            $data->total_issued = $totalIssued;

            if ($totalCoupons === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.campaignreport.getcampaignreport.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.campaignreport.getcampaignreport.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.campaignreport.getcampaignreport.query.error', array($this, $e));

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
            Event::fire('orbit.campaignreport.getcampaignreport.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.campaignreport.getcampaignreport.before.render', array($this, &$output));

        return $output;
    }


    public function setReturnBuilder($bool)
    {
        $this->returnBuilder = $bool;

        return $this;
    }

    protected function registerCustomValidation()
    {
        $user = $this->api->user;
        // Check the existance of mall id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) use ($user){
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
