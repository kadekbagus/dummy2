<?php namespace Orbit\Controller\API\v1\Pub\Promotion;

/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for promotion list and search in landing page
 */

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\ResponseProvider;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \URL;
use News;
use NewsMerchant;
use Language;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Session\UserGetter;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Controller\API\v1\Pub\Promotion\PromotionHelper;
use Mall;

class PromotionLocationAPIController extends ControllerAPI
{
    public function getPromotionLocations()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $promotionId = OrbitInput::get('promotion_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');


            $validator = Validator::make(
                array(
                    'promotion_id' => $promotionId,
                ),
                array(
                    'promotion_id' => 'required',
                ),
                array(
                    'required' => 'Promotion ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $promotionLocation = NewsMerchant::select(
                                        DB::raw("{$prefix}merchants.merchant_id as merchant_id"),
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN CONCAT({$prefix}merchants.name, ' at ', oms.name) ELSE {$prefix}merchants.name END as name"),
                                        DB::raw("{$prefix}merchants.object_type as location_type"),
                                        DB::raw("CONCAT(IF({$prefix}merchants.object_type = 'tenant', oms.ci_domain, {$prefix}merchants.ci_domain), '/customer/mallpromotion?id=', {$prefix}news_merchant.news_id) as url"),
                                        'news.begin_date as begin_date',
                                        'news.end_date as end_date',
                                        DB::raw("( SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                    FROM {$prefix}merchants om
                                                    LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                    WHERE om.merchant_id = (CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.merchant_id ELSE {$prefix}merchants.merchant_id END)
                                                ) as tz"),
                                            DB::Raw("img.path as location_logo"),
                                            DB::Raw("{$prefix}merchants.phone as phone")
                                    )
                                    ->leftJoin('news', 'news_merchant.news_id', '=', 'news.news_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->leftJoin(DB::raw("{$prefix}media as img"), function($q) use ($prefix){
                                        $q->on(DB::raw('img.object_id'), '=', DB::Raw("
                                                        (select CASE WHEN t.object_type = 'tenant'
                                                                    THEN m.merchant_id
                                                                    ELSE t.merchant_id
                                                                END as mall_id
                                                        from orb_merchants t
                                                        join orb_merchants m
                                                            on m.merchant_id = t.parent_id
                                                        where t.merchant_id = {$prefix}merchants.merchant_id)
                                            "))
                                            ->on(DB::raw('img.media_name_long'), 'IN', DB::raw("('mall_logo_orig', 'retailer_logo_orig')"));
                                    })
                                    ->where('news_merchant.news_id', '=', $promotionId)
                                    ->groupBy('merchant_id')
                                    ->havingRaw('tz <= end_date AND tz >= begin_date');

            $_promotionLocation = clone($promotionLocation);

            $take = PaginationNumber::parseTakeFromGet('news');
            $promotionLocation->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $promotionLocation->skip($skip);

            $promotionLocation->orderBy($sort_by, $sort_mode);

            $listOfRec = $promotionLocation->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($_promotionLocation)->count();
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
}