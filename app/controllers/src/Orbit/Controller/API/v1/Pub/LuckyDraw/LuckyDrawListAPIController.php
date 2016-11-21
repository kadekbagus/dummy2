<?php namespace Orbit\Controller\API\v1\Pub\LuckyDraw;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use OrbitShop\API\v1\OrbitShopAPI;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Helper\EloquentRecordCounter as RecordCounter;
use Validator;
use Lang;
use Config;
use LuckyDraw;
use stdclass;
use DB;
use URL;
use Activity;
use Mall;
use Orbit\Controller\API\v1\Pub\LuckyDraw\LuckyDrawHelper;

class LuckyDrawListAPIController extends PubControllerAPI
{
    /**
     * GET - get lucky draw list in all mall
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer take
     * @param integer skip
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchLuckyDraw()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $mall = NULL;
        $httpCode = 200;

        try {
            $this->checkAuth();
            $user = $this->api->user;

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.lucky_draw.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.lucky_draw.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');

            $luckyDrawHelper = LuckyDrawHelper::create();
            $luckyDrawHelper->luckyDrawCustomValidator();
            $validator = Validator::make(
                array(
                    'language' => $language,
                    'sortby'   => $sort_by,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:name,created_date',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $luckyDrawHelper->getValidLanguage();

            $prefix = DB::getTablePrefix();

            // add type also
            $luckydraws = LuckyDraw::select(
                    'lucky_draws.lucky_draw_id',
                    DB::raw("
                        CASE WHEN ({$prefix}lucky_draw_translations.lucky_draw_name = '' or {$prefix}lucky_draw_translations.lucky_draw_name is null) THEN {$prefix}lucky_draws.lucky_draw_name ELSE {$prefix}lucky_draw_translations.lucky_draw_name END as lucky_draw_name,
                        CASE WHEN ({$prefix}lucky_draw_translations.description = '' or {$prefix}lucky_draw_translations.description is null) THEN {$prefix}lucky_draws.description ELSE {$prefix}lucky_draw_translations.description END as description,
                        CASE WHEN {$prefix}media.path is null THEN (
                                select m.path
                                from {$prefix}lucky_draw_translations ldt
                                join {$prefix}media m
                                    on m.object_id = ldt.lucky_draw_translation_id
                                    and m.media_name_long = 'lucky_draw_translation_image_orig'
                                where ldt.lucky_draw_id = {$prefix}lucky_draws.lucky_draw_id
                                group by ldt.lucky_draw_id
                            ) ELSE {$prefix}media.path END as image_url,
                        name as mall_name
                    "),
                    'city',
                    'country',
                    'ci_domain',
                    DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                             THEN {$prefix}campaign_status.campaign_status_name ELSE (
                                 CASE WHEN {$prefix}lucky_draws.draw_date < (
                                     SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                     FROM {$prefix}merchants om
                                     LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                     WHERE om.merchant_id = {$prefix}lucky_draws.mall_id)
                                 THEN 'expired'
                             ELSE {$prefix}campaign_status.campaign_status_name END)
                             END AS campaign_status")
                )
                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'lucky_draws.campaign_status_id')
                ->leftJoin('merchants', 'lucky_draws.mall_id', '=', 'merchants.merchant_id')
                ->leftJoin('lucky_draw_translations', function ($q) use ($valid_language) {
                    $q->on('lucky_draw_translations.lucky_draw_id', '=', 'lucky_draws.lucky_draw_id')
                      ->on('lucky_draw_translations.merchant_language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                })
                ->leftJoin('media', function ($q) {
                    $q->on('media.object_id', '=', 'lucky_draw_translations.lucky_draw_translation_id');
                    $q->on('media.media_name_long', '=', DB::raw("'lucky_draw_translation_image_orig'"));
                })
                ->active('lucky_draws')
                ->havingRaw("campaign_status = 'ongoing'")
                ->groupBy('lucky_draws.lucky_draw_id');

            if ($sort_by !== 'location') {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'name'          => 'lucky_draw_name',
                    'created_date'  => 'lucky_draws.created_at'
                );

                $sort_by = $sortByMapping[$sort_by];
            }

            OrbitInput::get('sortmode', function($_sortMode) use (&$sort_mode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sort_mode = 'desc';
                }
            });
            $luckydraws = $luckydraws->orderBy($sort_by, $sort_mode);

            OrbitInput::get('object_type', function($objType) use($luckydraws) {
                $luckydraws->where('lucky_draws.object_type', $objType);
            });

            OrbitInput::get('mall_id', function($mallid) use($luckydraws, &$mall) {
                // indicates this API is accessed from mall ci
                $luckydraws->where('lucky_draws.mall_id', $mallid);
                $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $mallid)
                        ->first();
            });

            $_luckydraws = clone $luckydraws;

            // Get the take args
            $take = Config::get('orbit.pagination.per_page');
            OrbitInput::get(
                'take',
                function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                }
            );
            $luckydraws->take($take);

            $skip = 0;
            OrbitInput::get(
                'skip',
                function ($_skip) use (&$skip, $luckydraws) {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                }
            );
            $luckydraws->skip($skip);

            // save activity when accessing listing
            // omit save activity if accessed from mall ci campaign list 'from_mall_ci' !== 'y'
            // moved from generic activity number 17
            if (empty($skip) && OrbitInput::get('from_mall_ci', '') !== 'y') {
                if (is_object($mall)) {
                    $activityNotes = sprintf('Page viewed: View mall lucky draw list page');
                    $activity->setUser($user)
                        ->setActivityName('view_mall_lucky_draw_list')
                        ->setActivityNameLong('View mall lucky draw list')
                        ->setObject(null)
                        ->setLocation($mall)
                        ->setModuleName('LuckyDraw')
                        ->setNotes($activityNotes)
                        ->responseOK()
                        ->save();
                } else {
                    $activityNotes = sprintf('Page viewed: Lucky draw list');
                    $activity->setUser($user)
                        ->setActivityName('view_lucky_draws_main_page')
                        ->setActivityNameLong('View Lucky Draws Main Page')
                        ->setObject(null)
                        ->setLocation($mall)
                        ->setModuleName('LuckyDraw')
                        ->setNotes($activityNotes)
                        ->responseOK()
                        ->save();
                }
            }

            $totalRec = RecordCounter::create($_luckydraws)->count();
            $listOfRec = $luckydraws->get();

            if ($listOfRec->isEmpty()) {
                $data = new stdclass();
                $data->total_records = 0;
                $data->returned_records = 0;
                $data->records = null;
                $data->custom_message = Config::get('orbit.lucky_draw.custom_message', '');
            } else {
                $data = new stdclass();
                $data->total_records = $totalRec;
                $data->returned_records = sizeof($listOfRec);
                $data->records = $listOfRec;
            }

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
            $this->response->data = $data;

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

            $this->response->code = Status::UNKNOWN_ERROR;
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