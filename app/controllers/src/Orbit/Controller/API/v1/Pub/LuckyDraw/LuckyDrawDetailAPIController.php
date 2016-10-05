<?php namespace Orbit\Controller\API\v1\Pub\LuckyDraw;
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
use Activity;
use Validator;
use Lang;
use Language;
use Config;
use LuckyDraw;
use stdclass;
use DB;
use URL;
use Orbit\Controller\API\v1\Pub\LuckyDraw\LuckyDrawHelper;
use Carbon\Carbon;

class LuckyDrawDetailAPIController extends IntermediateBaseController
{
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

            $luckyDrawHelper = LuckyDrawHelper::create();
            $luckyDrawHelper->luckyDrawCustomValidator();
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

            $valid_language = $luckyDrawHelper->getValidLanguage();
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
                    'timezones.timezone_name',
                    'merchants.merchant_id'
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
                ->where('lucky_draws.object_type', 'auto');

            OrbitInput::get('mall_id', function($mallId) use (&$luckyDraw) {
                $luckyDraw->where('lucky_draws.mall_id', $mallId);
            });

            $luckyDraw = $luckyDraw->first();

            if (! is_object($luckyDraw)) {
                OrbitShopAPI::throwInvalidArgument('Lucky draw you specified is not found');
            }

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
            $this->response->data = [$e->getLine(), $e->getFile(), $e->getTraceAsString()];
            $httpCode = 500;

        }

        return $this->render($this->response);
    }
}
