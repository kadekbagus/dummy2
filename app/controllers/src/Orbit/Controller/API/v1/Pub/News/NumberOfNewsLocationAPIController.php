<?php namespace Orbit\Controller\API\v1\Pub\News;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use NewsMerchant;
use Validator;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use Illuminate\Database\QueryException;
use stdClass;

class NumberOfNewsLocationAPIController extends PubControllerAPI
{
    /**
     * GET - number of news/event location
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string mall_id
     * @param string news_id
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getNumberOfNewsLocation()
    {
        $httpCode = 200;
        try{
            $newsId = OrbitInput::get('news_id', null);
            $mallId = OrbitInput::get('mall_id', null);
            $skipMall = OrbitInput::get('skip_mall', 'N');

            $validator = Validator::make(
                array(
                    'news_id' => $newsId,
                    'skip_mall' => $skipMall,
                ),
                array(
                    'news_id' => 'required',
                    'skip_mall' => 'in:Y,N',
                ),
                array(
                    'required' => 'News ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();
            $newsLocations = NewsMerchant::select(
                                            "merchants.merchant_id",
                                            DB::raw("{$prefix}merchants.name as name"),
                                            "merchants.object_type",
                                            DB::raw("oms.merchant_id as parent_id"),
                                            DB::raw("oms.object_type as parent_type"),
                                            DB::raw("oms.name as parent_name")
                                        )
                                    ->join('news', function ($q) {
                                        $q->on('news_merchant.news_id', '=', 'news.news_id')
                                          ->on('news.object_type', '=', DB::raw("'news'"));
                                    })
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->where('news_merchant.news_id', '=', $newsId);

            OrbitInput::get('cities', function($cities) use ($newsLocations, $prefix) {
                foreach ($cities as $key => $value) {
                    if (empty($value)) {
                       unset($cities[$key]);
                    }
                }
                if (! empty($cities)) {
                    $newsLocations->whereIn(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'mall' THEN {$prefix}merchants.city ELSE oms.city END)"), $cities);
                }
            });

            OrbitInput::get('country', function($country) use ($newsLocations, $prefix) {
                if (! empty($country)) {
                    $newsLocations->where(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'mall' THEN {$prefix}merchants.country ELSE oms.country END)"), $country);
                }
            });

            if ($skipMall === 'Y') {
                // filter news skip by mall id
                OrbitInput::get('mall_id', function($mallid) use ($newsLocations, &$group_by) {
                    $newsLocations->where(DB::raw('oms.merchant_id'), '!=', $mallid);
                });
            } else {
                // filter news by mall id
                OrbitInput::get('mall_id', function($mallid) use ($newsLocations, &$group_by) {
                    $newsLocations->where('merchants.parent_id', $mallid)
                                ->where('merchants.object_type', 'tenant');
                });
            }

            // get all record with mall id
            $numberOfMall = 0;
            $numberOfStore = 0;
            $numberOfStoreRelatedMall = 0;

            // get number of store and number of mall
            $newsLocations = $newsLocations->groupBy('merchants.name');

            $numberOfLocationSql = $newsLocations->toSql();
            $newsLocations = DB::table(DB::Raw("({$numberOfLocationSql}) as sub_query"))->mergeBindings($newsLocations->getQuery())
                            ->select(
                                    DB::raw("object_type, count(merchant_id) as total")
                                )
                            ->groupBy(DB::Raw("sub_query.parent_id"))
                            ->get();

            foreach ($newsLocations as $_data) {
                if ($_data->object_type === 'tenant') {
                    $numberOfStore += $_data->total;
                    $numberOfStoreRelatedMall++;
                } else {
                    $numberOfMall += $_data->total;
                }
            }

            $data = new \stdclass();
            $data->numberOfMall = $numberOfMall;
            $data->numberOfStore = $numberOfStore;
            $data->numberOfStoreRelatedMall = $numberOfStoreRelatedMall;

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