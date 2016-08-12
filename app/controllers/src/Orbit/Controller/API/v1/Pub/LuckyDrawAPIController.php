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
use Config;
use LuckyDraw;
use stdclass;
use DB;
use URL;

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

            $asiaJakartaTime = Carbon::now('Asia/Jakarta');

            // add type also
            $luckydraws = LuckyDraw::select(
                    'lucky_draw_id',
                    'lucky_draw_name',
                    DB::raw("name as mall_name"),
                    'city',
                    'country',
                    'ci_domain',
                    DB::raw("(CONCAT(ci_domain, '" . $ciLuckyDrawPath . "?id=', lucky_draw_id)) as ci_path")
                )
                ->leftJoin('merchants', 'lucky_draws.mall_id', '=', 'merchants.merchant_id')
                ->active('lucky_draws')
                ->where('lucky_draws.start_date', '<=', $asiaJakartaTime)
                ->where('lucky_draws.grace_period_date', '>=', $asiaJakartaTime);

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

            if (empty($skip)) {
                $activityNotes = sprintf('Page viewed: Landing Page Lucky Draw List Page');
                $activity->setUser($user)
                    ->setActivityName('view_landing_page_lucky_draw_list')
                    ->setActivityNameLong('View GoToMalls Lucky Draw List')
                    ->setObject(null)
                    ->setModuleName('LuckyDraw')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
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

            $activityNotes = sprintf('Failed to view Page: Landing Page Lucky Draw List. Err: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_landing_page_lucky_draw_list')
                ->setActivityNameLong('View GoToMalls Lucky Draw List')
                ->setObject(null)
                ->setModuleName('LuckyDraw')
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            $activityNotes = sprintf('Failed to view Page: Landing Page Lucky Draw List. Err: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_landing_page_lucky_draw_list')
                ->setActivityNameLong('View GoToMalls Lucky Draw List')
                ->setObject(null)
                ->setModuleName('LuckyDraw')
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
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

            $activityNotes = sprintf('Failed to view Page: Landing Page Lucky Draw List. Err: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_landing_page_lucky_draw_list')
                ->setActivityNameLong('View GoToMalls Lucky Draw List')
                ->setObject(null)
                ->setModuleName('LuckyDraw')
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
        } catch (\Exception $e) {

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

            $activityNotes = sprintf('Failed to view Page: Landing Page Lucky Draw List. Err: %s', $e->getMessage());
            $activity->setUser($user)
                ->setActivityName('view_landing_page_lucky_draw_list')
                ->setActivityNameLong('View GoToMalls Lucky Draw List')
                ->setObject(null)
                ->setModuleName('LuckyDraw')
                ->setNotes($activityNotes)
                ->responseFailed()
                ->save();
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
