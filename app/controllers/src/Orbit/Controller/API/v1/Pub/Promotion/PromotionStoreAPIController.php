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
use Language;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use Orbit\Controller\API\v1\Pub\Promotion\PromotionHelper;
use Mall;

class PromotionStoreAPIController extends PubControllerAPI
{
    /**
     * GET - get store list inside promotion detil
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string promotion_id
     * @param string mall_id
     * @param string is_detail
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getPromotionStore()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;

        try{
            $user = $this->getUser();

            $promotionId = OrbitInput::get('promotion_id', null);
            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $mallId = OrbitInput::get('mall_id', null);
            $is_detail = OrbitInput::get('is_detail', 'n');
            $mall = null;

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

            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            $prefix = DB::getTablePrefix();
            $promotionLocation = NewsMerchant::select(
                                        "merchants.merchant_id",
                                        DB::raw("{$prefix}merchants.name as name"),
                                        "merchants.object_type"
                                    )
                                    ->join('news', function ($q) {
                                        $q->on('news_merchant.news_id', '=', 'news.news_id')
                                          ->on('news.object_type', '=', DB::raw("'promotion'"));
                                    })
                                    ->leftJoin('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->where('news_merchant.news_id', '=', $promotionId)
                                    ->where('merchants.object_type', 'tenant')
                                    ->groupBy("name")
                                    ->orderBy($sort_by, $sort_mode);

            // filter news by mall id
            OrbitInput::get('mall_id', function($mallid) use ($is_detail, $promotionLocation, &$group_by) {
                if ($is_detail != 'y') {
                    $promotionLocation->where('merchants.parent_id', '=', $mallid);
                }
            });

            $_promotionLocation = clone($promotionLocation);

            $take = PaginationNumber::parseTakeFromGet('news');
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

                $activityNotes = sprintf('Page viewed: Promotion location list');
                $activity->setUser($user)
                    ->setActivityName('view_promotion_location')
                    ->setActivityNameLong('View Promotion Location Page')
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