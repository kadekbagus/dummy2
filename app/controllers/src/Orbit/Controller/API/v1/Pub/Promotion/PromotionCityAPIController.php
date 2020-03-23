<?php namespace Orbit\Controller\API\v1\Pub\Promotion;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
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
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Mall;

class PromotionCityAPIController extends PubControllerAPI
{

    /**
     * GET - Get list of city for each promotion
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string promotion_id
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     *
     * @return Illuminate\Support\Facades\Response
     */

    public function getPromotionCity()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;

        try{
            $user = $this->getUser();

            $promotionId = OrbitInput::get('promotion_id', null);
            $sort_by = OrbitInput::get('sortby', 'city');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $mall = null;
            $storeName = OrbitInput::get('store_name');
            $mallId = OrbitInput::get('mall_id');
            $skipMall = OrbitInput::get('skip_mall', 'N');

            $validator = Validator::make(
                array(
                    'promotion_id' => $promotionId,
                    'skip_mall' => $skipMall,
                ),
                array(
                    'promotion_id' => 'required',
                    'skip_mall' => 'in:Y,N',
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

            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            $prefix = DB::getTablePrefix();

            $promotionLocation = NewsMerchant::select(
                                        DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN oms.city ELSE {$prefix}merchants.city END as city")
                                    )
                                    ->leftJoin('news', 'news_merchant.news_id', '=', 'news.news_id')
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->leftJoin(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                                    ->where('news_merchant.news_id', '=', $promotionId)
                                    ->where('merchants.status', '=', 'active');

            // filter by store name
            OrbitInput::get('store_name', function($storeName) use ($promotionLocation) {
                $promotionLocation->where('merchants.name', $storeName);
            });

            if ($skipMall === 'Y') {
                OrbitInput::get('mall_id', function($mallId) use ($promotionLocation) {
                    $promotionLocation->where(function($q) use ($mallId) {
                                        $q->where('merchants.parent_id', '!=', $mallId)
                                          ->where('merchants.merchant_id', '!=', $mallId);
                                    });
                    });
            } else {
                OrbitInput::get('mall_id', function($mallId) use ($promotionLocation) {
                    $promotionLocation->where(function($q) use ($mallId) {
                                        $q->where('merchants.parent_id', '=', $mallId)
                                          ->orWhere('merchants.merchant_id', '=', $mallId);
                                    });
                    });
            }

            $promotionLocation = $promotionLocation->groupBy('city');

            $_promotionLocation = clone($promotionLocation);

            $take = PaginationNumber::parseTakeFromGet('city_location');
            $promotionLocation->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $promotionLocation->skip($skip);

            $promotionLocation->orderBy($sort_by, $sort_mode);

            $listOfRec = $promotionLocation->get();

            // moved from generic activity number 36
            if (empty($skip) && OrbitInput::get('is_detail', 'n') === 'y'  ) {
                $promotion = News::excludeDeleted()
                    ->where('news_id', $promotionId)
                    ->first();

                $activityNotes = sprintf('Page viewed: Promotion city list');
                $activity->setUser($user)
                    ->setActivityName('view_promotion_city')
                    ->setActivityNameLong('View Promotion City Page')
                    ->setObject($promotion)
                    ->setLocation($mall)
                    ->setModuleName('Promotion')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

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