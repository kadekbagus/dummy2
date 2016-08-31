<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * API Controller for Lucky draw list for public usage
 *
 */
use IntermediateBaseController;
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Net\SessionPreparer;
use Carbon\Carbon;
use Validator;
use Lang;
use Mall;
use Config;
use LuckyDraw;
use stdclass;
use DB;
use URL;
use Language;

class LuckyDrawAPIController extends IntermediateBaseController
{
    /**
     * GET - get lucky draw list in all mall
     * the time used here is Asia/Jakarta already confirmed by PO
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
        $httpCode = 200;

        try {
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

            // Get language_if of english
            $languageEnId = null;
            $language = Language::where('name', 'en')->first();

            if (! empty($language)) {
                $languageEnId = $language->language_id;
            }

            $prefix = DB::getTablePrefix();

            // add type also
            $luckydraws = LuckyDraw::select(
                    'lucky_draws.lucky_draw_id',
                    'lucky_draw_translations.lucky_draw_name',
                    DB::raw("name as mall_name"),
                    'city',
                    'country',
                    'ci_domain',
                    'lucky_draw_translations.description',
                    DB::raw("(CONCAT(ci_domain, '" . $ciLuckyDrawPath . "?id=', {$prefix}lucky_draws.lucky_draw_id)) as ci_path"),
                    DB::raw('media.path as image_url'),
                    DB::raw("CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                             THEN {$prefix}campaign_status.campaign_status_name ELSE (
                                 CASE WHEN {$prefix}lucky_draws.end_date < (
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
                ->leftJoin(DB::raw("( SELECT * FROM {$prefix}media WHERE media_name_long = 'lucky_draw_translation_image_orig' ) as media"), 'lucky_draw_translations.lucky_draw_translation_id', '=', DB::raw('media.object_id'))
                ->active('lucky_draws')
                ->where('lucky_draw_translations.merchant_language_id', '=', $languageEnId)
                ->where('lucky_draw_translations.lucky_draw_name', '!=', '')
                ->havingRaw("campaign_status = 'ongoing'");

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

            $luckydraws->orderBy('lucky_draw_name', 'asc');

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
     * Get relative path from url
     */
    protected function getRelPathWithoutParam($url, $key)
    {
        $parsed_url = parse_url((string)$url);

        return $parsed_url['path'];
    }
}
