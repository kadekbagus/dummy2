<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * API Controller for Lucky draw list for public usage
 *
 */
use IntermediateBaseController;
use OrbitShop\API\v1\ResponseProvider;
use Orbit\Helper\Session\UserGetter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use OrbitShop\API\v1\OrbitShopAPI;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Net\SessionPreparer;
use Carbon\Carbon;
use Activity;
use Validator;
use User;
use Lang;
use Mall;
use Language;
use Config;
use LuckyDraw;
use stdclass;
use DB;
use URL;
use LuckyDrawNumber;
use Inbox;

class LuckyDrawAPIController extends IntermediateBaseController
{
    protected $valid_language = NULL;

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
        $this->response = new ResponseProvider();
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $httpCode = 200;

        try {
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

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

            $ciLuckyDrawPath = URL::route('ci-luckydraw-detail', []);
            $ciLuckyDrawPath = $this->getRelPathWithoutParam($ciLuckyDrawPath, 'orbit_session');

            $sort_by = OrbitInput::get('sortby', 'lucky_draw_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');

            $this->registerCustomValidation();
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

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();

            // add type also
            $luckydraws = LuckyDraw::select(
                    'lucky_draws.lucky_draw_id',
                    DB::raw("
                        CASE WHEN {$prefix}lucky_draw_translations.lucky_draw_name = '' THEN {$prefix}lucky_draws.lucky_draw_name ELSE {$prefix}lucky_draw_translations.lucky_draw_name END as lucky_draw_name,
                        CASE WHEN {$prefix}lucky_draw_translations.description = '' THEN {$prefix}lucky_draws.description ELSE {$prefix}lucky_draw_translations.description END as description,
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
                    DB::raw("(CONCAT(ci_domain, '" . $ciLuckyDrawPath . "?id=', {$prefix}lucky_draws.lucky_draw_id)) as ci_path"),
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
                ->leftJoin('lucky_draw_translations', 'lucky_draw_translations.lucky_draw_id', '=', 'lucky_draws.lucky_draw_id')
                ->leftJoin('media', function($q) {
                    $q->on('media.object_id', '=', 'lucky_draw_translations.lucky_draw_translation_id');
                    $q->on('media.media_name_long', '=', DB::raw("'lucky_draw_translation_image_orig'"));
                })
                ->active('lucky_draws')
                ->where('lucky_draw_translations.merchant_language_id', '=', $valid_language->language_id)
                ->havingRaw("campaign_status = 'ongoing'")
                ->groupBy('lucky_draws.lucky_draw_id')
                ->orderBy($sort_by, $sort_mode);

            OrbitInput::get('object_type', function($objType) use($luckydraws) {
                $luckydraws->where('lucky_draws.object_type', $objType);
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

        return $this->render($this->response);
    }

    /**
     * GET - get lucky draw detail
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string lucky_draw_id
     * @param string language
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getLuckyDrawItem()
    {
        $this->response = new ResponseProvider();
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $httpCode = 200;

        try {
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            $language = OrbitInput::get('language', 'id');
            $luckyDrawId = OrbitInput::get('lucky_draw_id');

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'lucky_draw_id' => $luckyDrawId,
                    'language' => $language
                ),
                array(
                    'lucky_draw_id' => 'required|orbit.empty.lucky_draw',
                    'language' => 'required|orbit.empty.language_default'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->valid_language;
            $prefix = DB::getTablePrefix();

            $luckyDraw = LuckyDraw::select(
                    'lucky_draws.lucky_draw_id',
                    DB::raw("
                        CASE WHEN {$prefix}lucky_draw_translations.lucky_draw_name = ''
                            THEN {$prefix}lucky_draws.lucky_draw_name
                            ELSE {$prefix}lucky_draw_translations.lucky_draw_name
                        END as lucky_draw_name,
                        CASE WHEN {$prefix}lucky_draw_translations.description = ''
                            THEN {$prefix}lucky_draws.description
                            ELSE {$prefix}lucky_draw_translations.description
                        END as description,
                        CASE WHEN {$prefix}media.path is null
                            THEN (
                                select m.path
                                from {$prefix}lucky_draw_translations ldt
                                join {$prefix}media m
                                    on m.object_id = ldt.lucky_draw_translation_id
                                    and m.media_name_long = 'lucky_draw_translation_image_orig'
                                where ldt.lucky_draw_id = {$prefix}lucky_draws.lucky_draw_id
                                group by ldt.lucky_draw_id
                            )
                            ELSE {$prefix}media.path
                        END as image_url,
                        name as mall_name
                    "),
                    DB::raw("
                        mall_media.path as mall_logo_url
                    "),
                    'city',
                    'country',
                    'start_date',
                    'end_date',
                    'draw_date',
                    DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                            THEN {$prefix}campaign_status.campaign_status_name
                            ELSE (
                                CASE WHEN {$prefix}lucky_draws.draw_date < (
                                        SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                        FROM {$prefix}merchants om
                                        LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                        WHERE om.merchant_id = {$prefix}lucky_draws.mall_id)
                                    THEN 'expired'
                                    ELSE {$prefix}campaign_status.campaign_status_name
                                END)
                            END AS campaign_status"),
                    'timezones.timezone_name'
                )
                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'lucky_draws.campaign_status_id')
                ->leftJoin('merchants', 'lucky_draws.mall_id', '=', 'merchants.merchant_id')
                ->leftJoin('lucky_draw_translations', 'lucky_draw_translations.lucky_draw_id', '=', 'lucky_draws.lucky_draw_id')
                ->leftJoin('media', function($q) {
                    $q->on('media.object_id', '=', 'lucky_draw_translations.lucky_draw_translation_id');
                    $q->on('media.media_name_long', '=', DB::raw("'lucky_draw_translation_image_orig'"));
                })
                ->leftJoin(DB::raw("{$prefix}media mall_media"), function($q) {
                    $q->on(DB::raw('mall_media.object_id'), '=', 'merchants.merchant_id');
                    $q->on(DB::raw('mall_media.media_name_long'), 'IN', DB::raw("('mall_logo_orig', 'retailer_logo_orig')"));
                })
                ->leftJoin('timezones', 'merchants.timezone_id', '=', 'timezones.timezone_id')
                ->active('lucky_draws')
                ->where('lucky_draw_translations.merchant_language_id', '=', $valid_language->language_id)
                ->where('lucky_draws.lucky_draw_id', $luckyDrawId)
                ->where('lucky_draws.object_type', 'auto')
                ->first();

            $csrf_token = csrf_token();
            $this->session->write('orbit_csrf_token', $csrf_token);

            $luckyDraw->current_mall_time = Carbon::now($luckyDraw->timezone_name)->format('Y-m-d H:i:s');
            $luckyDraw->token = $csrf_token;

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
            $this->response->data = $luckyDraw;

            $activityNotes = sprintf('Page viewed: Landing Page Lucky Draw Detail Page');
            $activity->setUser($user)
                ->setActivityName('view_landing_page_lucky_draw_detail')
                ->setActivityNameLong('View GoToMalls Lucky Draw Detail')
                ->setObject($luckyDraw)
                ->setModuleName('LuckyDraw')
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();

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

        return $this->render($this->response);
    }

    /**
     * POST - post auto issue lucky draw (fake upload issue lucky draw number)
     *
     * @param string lucky_draw_id
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function postLuckyDrawAutoIssue()
    {
        $this->response = new ResponseProvider();
        $activity = Activity::mobileci()->setActivityType('click');
        $user = NULL;
        $httpCode = 200;
        $luckyDraw = null;
        try {
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You must login to access this.';
                ACL::throwAccessForbidden($message);
            }

            $lucky_draw_id = OrbitInput::post('lucky_draw_id');
            $token = OrbitInput::post('_token');

            DB::connection()->beginTransaction();

            // check csrf token
            if ($this->session->read('orbit_csrf_token') !== OrbitInput::post('_token')) {
                throw new \Illuminate\Session\TokenMismatchException;
            }

            $luckyDraw = LuckyDraw::active()
                ->where('lucky_draw_id', $lucky_draw_id)
                ->where('lucky_draws.object_type', 'auto')
                ->first();

            // check lucky draw existance
            if (! is_object($luckyDraw)) {
                $errorMessage = sprintf('Lucky draw ID is not found.');
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            $mall = Mall::with('timezone')
                ->excludeDeleted()
                ->where('merchant_id', $luckyDraw->mall_id)
                ->first();

            // check mall
            if (! is_object($mall)) {
                $errorMessage = sprintf('Mall is not found.');
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            $now = strtotime(Carbon::now($mall->timezone->timezone_name));
            $luckyDrawDate = strtotime($luckyDraw->end_date);

            // check lucky draw validity date (now against end_date)
            if ($now > $luckyDrawDate) {
                $errorMessage = sprintf('The lucky draw already expired on %s.', date('d/m/Y', strtotime($luckyDraw->end_date)));
                OrbitShopAPI::throwInvalidArgument(htmlentities($errorMessage));
            }

            $checkMaxIssuance = DB::table('lucky_draws')
                ->where('status', 'active')
                ->where('start_date', '<=', Carbon::now($mall->timezone->timezone_name))
                ->where('end_date', '>=', Carbon::now($mall->timezone->timezone_name))
                ->where('lucky_draw_id', $lucky_draw_id)
                ->where('lucky_draws.object_type', 'auto')
                ->lockForUpdate()
                ->first();

            if (! is_object($checkMaxIssuance)) {
                DB::connection()->rollBack();
                $errorMessage = Lang::get('validation.orbit.empty.lucky_draw');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // check if lucky draw already reach max_number
            if ((((int) $checkMaxIssuance->max_number - (int) $checkMaxIssuance->min_number + 1) <= (int) $checkMaxIssuance->generated_numbers) && ((int) $checkMaxIssuance->free_number_batch === 0)) {
                DB::connection()->rollBack();
                $errorMessage = Lang::get('validation.orbit.exceed.lucky_draw.max_issuance', ['max_number' => $checkMaxIssuance->max_number]);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $activeluckydraw = DB::table('lucky_draws')
                ->where('status', 'active')
                ->where('start_date', '<=', Carbon::now($mall->timezone->timezone_name))
                ->where('end_date', '>=', Carbon::now($mall->timezone->timezone_name))
                ->where('lucky_draw_id', $lucky_draw_id)
                ->where('lucky_draws.object_type', 'auto')
                ->lockForUpdate()
                ->first();

            // check lucky draw for update
            if (! is_object($activeluckydraw)) {
                DB::connection()->rollBack();
                $errorMessage = Lang::get('validation.orbit.empty.lucky_draw');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // determine the starting number
            $starting_number_code = DB::table('lucky_draw_numbers')
                ->where('lucky_draw_id', $lucky_draw_id)
                ->max(DB::raw('CAST(lucky_draw_number_code AS UNSIGNED)'));

            if (empty ($starting_number_code)) {
                $starting_number_code = $activeluckydraw->min_number;
            } else {
                $starting_number_code = $starting_number_code + 1;
            }

            // do the issuance
            $lucky_draw_number = new LuckyDrawNumber;
            $lucky_draw_number->lucky_draw_id = $lucky_draw_id;
            $lucky_draw_number->lucky_draw_number_code = $starting_number_code;
            $lucky_draw_number->user_id = $user->user_id;
            $lucky_draw_number->issued_date = Carbon::now();
            $lucky_draw_number->status = 'active';
            $lucky_draw_number->created_by = $user->user_id;
            $lucky_draw_number->modified_by = $user->user_id;
            $lucky_draw_number->save();

            // update free_number_batch and generated_numbers
            $updated_luckydraw = LuckyDraw::where('lucky_draw_id', $lucky_draw_id)
                ->where('lucky_draws.object_type', 'auto')
                ->first();
            $updated_luckydraw->free_number_batch = 0;
            $updated_luckydraw->generated_numbers = $updated_luckydraw->generated_numbers + 1;
            $updated_luckydraw->save();

            // // refresh csrf_token
            $csrf_token = csrf_token();
            $this->session->write('orbit_csrf_token', $csrf_token);

            $data = new stdclass();
            $data->total_records = 1;
            $data->returned_records = 1;
            $data->expected_issued_numbers = 1;
            $data->records = null;
            $data->lucky_draw_number_code = (array) $lucky_draw_number->lucky_draw_number_code;
            $data->lucky_draw_name = $luckyDraw->lucky_draw_name;

            // Insert to alert system
            $inbox = new Inbox();
            $inbox->addToInbox($user->user_id, $data, $mall->merchant_id, 'lucky_draw_issuance');

            DB::connection()->commit();

            $total_lucky_draw_number = LuckyDrawNumber::where('lucky_draw_id', $lucky_draw_id)
                ->where('user_id', $user->user_id)
                ->get()->count();

            // Successfull Creation
            $activity->setUser($user)
                ->setActivityName('issue_lucky_draw')
                ->setActivityNameLong('Lucky Draw Number Auto Issuance')
                ->setNotes('Generated number: ' . $lucky_draw_number->lucky_draw_number_code)
                ->setObject($luckyDraw)
                ->responseOK();

            $response = new stdclass();
            $response->lucky_draw_number_code = $lucky_draw_number->lucky_draw_number_code;
            $response->token = $csrf_token;
            $response->total_number = $total_lucky_draw_number;

            $this->response->data = $response;

        } catch (ACLForbiddenException $e) {
            DB::connection()->rollBack();
            $token = $this->refreshCSRFToken($this->session);
            $data = new stdclass();
            $data->token = $token;

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $data;
            $httpCode = 403;

            // Creation failed Activity log
            $activity->setUser($user)
                ->setModuleName('LuckyDraw')
                ->setActivityName('issue_lucky_draw')
                ->setActivityNameLong('Lucky Draw Number Auto Issuance Failed')
                ->setObject($luckyDraw)
                ->setNotes($e->getMessage())
                ->responseFailed();

        } catch (InvalidArgsException $e) {
            DB::connection()->rollBack();
            $token = $this->refreshCSRFToken($this->session);
            $data = new stdclass();
            $data->token = $token;

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $data;
            $httpCode = 403;

            // Creation failed Activity log
            $activity->setUser($user)
                ->setModuleName('LuckyDraw')
                ->setActivityName('issue_lucky_draw')
                ->setActivityNameLong('Lucky Draw Number Auto Issuance Failed')
                ->setObject($luckyDraw)
                ->setNotes($e->getMessage())
                ->responseFailed();

        } catch (QueryException $e) {
            DB::connection()->rollBack();
            $token = $this->refreshCSRFToken($this->session);
            $data = new stdclass();
            $data->token = $token;

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = $data;
            $httpCode = 500;

            // Creation failed Activity log
            $activity->setUser($user)
                ->setModuleName('LuckyDraw')
                ->setActivityName('issue_lucky_draw')
                ->setActivityNameLong('Lucky Draw Number Auto Issuance Failed')
                ->setObject($luckyDraw)
                ->setNotes($e->getMessage())
                ->responseFailed();

        } catch (\Illuminate\Session\TokenMismatchException $e) {
            DB::connection()->rollBack();
            $token = $this->refreshCSRFToken($this->session);
            $data = new stdclass();
            $data->token = $token;

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = 'Token mismatch';
            $this->response->data = $data;
            $httpCode = 500;

            // Creation failed Activity log
            $activity->setUser($user)
                ->setModuleName('LuckyDraw')
                ->setActivityName('issue_lucky_draw')
                ->setActivityNameLong('Lucky Draw Number Auto Issuance Failed')
                ->setObject($luckyDraw)
                ->setNotes('Token mismatch exception')
                ->responseFailed();

        } catch (\Exception $e) {
            DB::connection()->rollBack();
            $token = $this->refreshCSRFToken($this->session);
            $data = new stdclass();
            $data->token = $token;

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $data;
            $httpCode = 500;

            // Creation failed Activity log
            $activity->setUser($user)
                ->setModuleName('LuckyDraw')
                ->setActivityName('issue_lucky_draw')
                ->setActivityNameLong('Lucky Draw Number Auto Issuance Failed')
                ->setObject($luckyDraw)
                ->setNotes($e->getMessage())
                ->responseFailed();

        }

        // Save the activity
        $activity->save();

        return $this->render($this->response);
    }

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
        $this->response = new ResponseProvider();
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $httpCode = 200;

        try {
            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

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

            $ciLuckyDrawPath = URL::route('ci-luckydraw-detail', []);
            $ciLuckyDrawPath = $this->getRelPathWithoutParam($ciLuckyDrawPath, 'orbit_session');

            $sort_by = OrbitInput::get('sortby', 'lucky_draw_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');

            $this->registerCustomValidation();
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

            $valid_language = $this->valid_language;

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
                        ->leftJoin('users', 'users.user_id', '=', 'lucky_draw_numbers.user_id')
                        ->whereRaw("
                                ld.draw_date <= (
                                         SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                         FROM {$prefix}merchants om
                                         LEFT JOIN {$prefix}timezones ot on ot.timezone_id = om.timezone_id
                                         WHERE om.merchant_id = ld.mall_id
                                    )
                            ");
                    }]);
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
                        CASE WHEN {$prefix}lucky_draw_translations.lucky_draw_name = '' THEN {$prefix}lucky_draws.lucky_draw_name ELSE {$prefix}lucky_draw_translations.lucky_draw_name END as lucky_draw_name,
                        CASE WHEN {$prefix}lucky_draw_translations.description = '' THEN {$prefix}lucky_draws.description ELSE {$prefix}lucky_draw_translations.description END as description,
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
                    DB::raw("(CONCAT(ci_domain, '" . $ciLuckyDrawPath . "?id=', {$prefix}lucky_draws.lucky_draw_id)) as ci_path"),
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
                ->leftJoin('lucky_draw_translations', 'lucky_draw_translations.lucky_draw_id', '=', 'lucky_draws.lucky_draw_id')
                ->leftJoin('media', function($q) {
                    $q->on('media.object_id', '=', 'lucky_draw_translations.lucky_draw_translation_id');
                    $q->on('media.media_name_long', '=', DB::raw("'lucky_draw_translation_image_orig'"));
                })
                ->leftJoin(DB::raw("{$prefix}media mall_media"), function($q) {
                    $q->on(DB::raw('mall_media.object_id'), '=', 'merchants.merchant_id');
                    $q->on(DB::raw('mall_media.media_name_long'), 'IN', DB::raw("('mall_logo_orig', 'retailer_logo_orig')"));
                })
                ->active('lucky_draws')
                ->where('lucky_draw_numbers.user_id', $user->user_id)
                ->where('lucky_draw_translations.merchant_language_id', '=', $valid_language->language_id)
                ->havingRaw("campaign_status = 'ongoing'")
                ->groupBy('lucky_draws.lucky_draw_id')
                ->orderBy($sort_by, $sort_mode);

            OrbitInput::get('object_type', function($objType) use($luckydraws) {
                $luckydraws->where('lucky_draws.object_type', $objType);
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

        return $this->render($this->response);
    }

    /**
     * Get relative path from url
     */
    protected function getRelPathWithoutParam($url, $key)
    {
        $parsed_url = parse_url((string)$url);

        return $parsed_url['path'];
    }

    /**
     * Refresh csrf_token
     */
    protected function refreshCSRFToken($session) {
        $csrfToken = csrf_token();
        $session->write('orbit_csrf_token', $csrfToken);

        return $csrfToken;
    }

    protected function registerCustomValidation() {
        // Check language is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->valid_language = $language;
            return TRUE;
        });

        // Check the existance of lucky_draw id
        Validator::extend('orbit.empty.lucky_draw', function ($attribute, $value, $parameters) {
            $lucky_draw = LuckyDraw::excludeDeleted()
                                   ->where('lucky_draw_id', $value)
                                   ->first();

            if (empty($lucky_draw)) {
                return FALSE;
            }

            return TRUE;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
