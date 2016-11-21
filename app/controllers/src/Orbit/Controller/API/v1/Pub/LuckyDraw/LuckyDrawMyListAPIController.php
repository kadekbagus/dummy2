<?php namespace Orbit\Controller\API\v1\Pub\LuckyDraw;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use OrbitShop\API\v1\OrbitShopAPI;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Helper\EloquentRecordCounter as RecordCounter;
use Activity;
use Mall;
use Validator;
use Lang;
use Config;
use LuckyDraw;
use stdclass;
use DB;
use URL;
use Orbit\Controller\API\v1\Pub\LuckyDraw\LuckyDrawHelper;

class LuckyDrawMyListAPIController extends PubControllerAPI
{
    /**
     * GET - get My lucky draw list in all mall
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer take
     * @param integer skip
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMyLuckyDrawList()
    {
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $mall = NULL;
        $httpCode = 200;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You must login to access this.';
                ACL::throwAccessForbidden($message);
            }

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

            $sort_by = OrbitInput::get('sortby', 'lucky_draw_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');

            $luckyDrawHelper = LuckyDrawHelper::create();
            $luckyDrawHelper->luckyDrawCustomValidator();
            $validator = Validator::make(
                array(
                    'language' => $language,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
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
            $luckydraws = LuckyDraw::with(['prizes' => function ($q) use ($prefix, $user) {
                    $q->select(
                            'lucky_draw_id',
                            'lucky_draw_prize_id',
                            'prize_name',
                            'winner_number'
                        )
                    ->with(['winners' => function ($qw) use ($prefix, $user) {
                            $qw->select(
                                    'lucky_draw_winners.lucky_draw_id',
                                    'lucky_draw_winner_id',
                                    'lucky_draw_prize_id',
                                    'lucky_draw_winner_code',
                                    'user_firstname',
                                    'user_lastname',
                                    DB::Raw("
                                            CASE WHEN {$prefix}users.user_id = {$this->quote($user->user_id)} THEN 'Y' ELSE 'N' END as my_number
                                        ")
                                )
                            ->leftJoin('lucky_draw_numbers', function ($qldn) use ($prefix) {
                                $qldn->on('lucky_draw_numbers.lucky_draw_id', '=', 'lucky_draw_winners.lucky_draw_id')
                                    ->on('lucky_draw_numbers.lucky_draw_number_code', '=', DB::Raw("{$prefix}lucky_draw_winners.lucky_draw_winner_code"));
                            })
                            ->leftJoin('lucky_draws as ld', DB::Raw('ld.lucky_draw_id'), '=', 'lucky_draw_winners.lucky_draw_id')
                            ->join('users', 'users.user_id', '=', 'lucky_draw_numbers.user_id')
                            ->whereRaw("
                                    ld.draw_date <= (
                                             SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                             FROM {$prefix}merchants om
                                             LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                             WHERE om.merchant_id = ld.mall_id
                                        )
                                ");
                        }])
                    ->orderBy('order');
                }, 'numbers' => function ($qn) use($user) {
                    $qn->select(
                            'lucky_draw_id',
                            'lucky_draw_number_code'
                        )
                    ->where('lucky_draw_numbers.user_id', $user->user_id);
                }])
                ->select(
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
                    DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                             THEN {$prefix}campaign_status.campaign_status_name ELSE (
                                 CASE WHEN {$prefix}lucky_draws.grace_period_date < (
                                     SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                     FROM {$prefix}merchants om
                                     LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                     WHERE om.merchant_id = {$prefix}lucky_draws.mall_id)
                                 THEN 'expired'
                             ELSE {$prefix}campaign_status.campaign_status_name END)
                             END AS campaign_status"),
                    'lucky_draws.start_date',
                    'lucky_draws.end_date',
                    'lucky_draws.draw_date',
                    DB::raw("
                        mall_media.path as mall_logo_url
                    ")
                )
                ->join('lucky_draw_numbers', 'lucky_draw_numbers.lucky_draw_id', '=', 'lucky_draws.lucky_draw_id')
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
                ->leftJoin(DB::raw("{$prefix}media mall_media"), function ($q) {
                    $q->on(DB::raw('mall_media.object_id'), '=', 'merchants.merchant_id');
                    $q->on(DB::raw('mall_media.media_name_long'), 'IN', DB::raw("('mall_logo_orig', 'retailer_logo_orig')"));
                })
                ->active('lucky_draws')
                ->where('lucky_draw_numbers.user_id', $user->user_id)
                ->havingRaw("campaign_status = 'ongoing'")
                ->groupBy('lucky_draws.lucky_draw_id')
                ->orderBy($sort_by, $sort_mode);

            OrbitInput::get('object_type', function($objType) use($luckydraws) {
                $luckydraws->where('lucky_draws.object_type', $objType);
            });

            OrbitInput::get('mall_id', function($mallId) use($luckydraws, &$mall) {
                $luckydraws->where('lucky_draws.mall_id', $mallId);
                $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $mallId)
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

            if (empty($skip)) {
                $activityNotes = sprintf('Page viewed: Landing Page My Lucky Number List');
                $activity->setUser($user)
                    ->setActivityName('view_landing_page_my_lucky_number_list')
                    ->setActivityNameLong('View GoToMalls My Lucky Number List')
                    ->setObject(null)
                    ->setLocation($mall)
                    ->setModuleName('LuckyDraw')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

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
