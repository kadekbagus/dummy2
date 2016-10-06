<?php namespace Orbit\Controller\API\v1\Pub\LuckyDraw;

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
use Lang;
use Config;
use LuckyDraw;
use stdclass;
use DB;
use Mall;
use Carbon\Carbon;
use LuckyDrawNumber;
use Inbox;
use \Orbit\Helper\Exception\OrbitCustomException;

class LuckyDrawAutoIssueAPIController extends IntermediateBaseController
{
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
                throw new OrbitCustomException($errorMessage, LuckyDraw::LUCKY_DRAW_EXPIRED_ERROR_CODE, NULL);
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
                $customData = new stdclass();
                $customData->max_number = $checkMaxIssuance->max_number;
                throw new OrbitCustomException($errorMessage, LuckyDraw::LUCKY_DRAW_MAX_NUMBER_REACHED_ERROR_CODE, $customData);
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

        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            DB::connection()->rollBack();
            $token = $this->refreshCSRFToken($this->session);
            $data = new stdclass();
            $data->token = $token;
            if ($e->getCode() === LuckyDraw::LUCKY_DRAW_MAX_NUMBER_REACHED_ERROR_CODE) {
                $data->custom_data = $e->getCustomData();
            }

            $this->response->code = $e->getCode();
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
     * Refresh csrf_token
     */
    protected function refreshCSRFToken($session) {
        $csrfToken = csrf_token();
        $session->write('orbit_csrf_token', $csrfToken);

        return $csrfToken;
    }
}
