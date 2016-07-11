<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;

class QuestionerAPIController extends ControllerAPI
{

    protected $validRoles = ['consumer', 'guest'];


    /**
     * GET - check if user inside mall area
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string latitude
     * @param string longitude
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getMallFence()
    {
        $httpCode = 200;
        try {

            $lat = OrbitInput::get('latitude', null);
            $long = OrbitInput::get('longitude', null);

            $usingDemo = Config::get('orbit.is_demo', FALSE);

            $malls = Mall::select('merchants.*')->includeLatLong()->InsideArea($lat, $long);

            if ($usingDemo) {
                $malls->excludeDeleted();
            } else {
                // Production
                $malls->active();
            }

            // Filter by mall_id
            OrbitInput::get('mall_id', function ($mallid) use ($malls) {
                $malls->where('merchants.merchant_id', $mallid);
            });

            $_malls = clone $malls;

            $take = PaginationNumber::parseTakeFromGet('geo_location');
            $malls->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $malls->skip($skip);

            $listmalls = $malls->get();
            $count = RecordCounter::create($_malls)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listmalls);
            $this->response->data->records = $listmalls;
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

        $output = $this->render($httpCode);

        return $output;
    }


    /**
     * POST - User answer the questioner
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string userid
     * @param string quenstion_id
     * @param string answer_id
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUserAnswer()
    {

        $httpCode = 200;

        try {

            Event::fire('orbit.questioner.postuseranswer.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.questioner.postuseranswer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            Event::fire('orbit.questioner.postuseranswer.before.authz', array($this, $user));

            $role = $user->role;
            $validRoles = $this->validRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.questioner.postuseranswer.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $user_id = $this->api->user->user_id;
            $quenstion_id = OrbitInput::post('quenstion_id');
            $answer_id = OrbitInput::post('answer_id');

            $validator = Validator::make(
                array(
                    'user_id'        => $user_id,
                    'quenstion_id'   => $quenstion_id,
                    'answer_id'      => $answer_id,
                ),
                array(
                    'user_id'          => 'required|orbit.exists.user_id',
                    'quenstion_id'     => 'required|orbit.exists.quenstion_id',
                    'answer_id'        => 'required|orbit.exists.answer_id:' . $user_id . ',' . $quenstion_id . ',' .$answer_id,
                )
            );

            Event::fire('orbit.questioner.postuseranswer.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.questioner.postuseranswer.after.validation', array($this, $validator));

            $newuseranswer = new UserAnswer();
            $newuseranswer->user_id = $user_id;
            $newuseranswer->quenstion_id = $quenstion_id;
            $newuseranswer->answer_id = $answer_id;

            Event::fire('orbit.questioner.postuseranswer.before.save', array($this, $newuseranswer));

            $newuseranswer->save();

            Event::fire('orbit.questioner.postuseranswer.after.save', array($this, $newuseranswer));

            $this->response->data = $newuseranswer;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('User Answer: %s', $newuseranswer->answer_id);
            $activity->setUser($user)
                    ->setActivityName('user_answer')
                    ->setActivityNameLong('User Answer OK')
                    ->setObject($newuseranswer)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.questioner.postuseranswer.after.commit', array($this, $newuseranswer));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.questioner.postuseranswer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('user_answer')
                    ->setActivityNameLong('User Answer Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.questioner.postuseranswer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('user_answer')
                    ->setActivityNameLong('User Answer Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.questioner.postuseranswer.query.error', array($this, $e));

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

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('user_answer')
                    ->setActivityNameLong('User Answer Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.questioner.postuseranswer.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('user_answer')
                    ->setActivityNameLong('User Answer Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }



    protected function registerCustomValidation()
    {
        // Check the exist use_id
        Validator::extend('orbit.exists.user_id', function ($attribute, $value, $parameters) {
            $userId = UserAnswer::where('user_id', '=', $value)
                                    ->first();

            if (empty($userId)) {
                return false;
            }

            return true;
        });

        // Check exist quenstion
        Validator::extend('orbit.exists.quenstion_id', function ($attribute, $value, $parameters) {
            $quenstion = Question::where('question_id', '=', $value)
                                    ->first();

            if (empty($quenstion)) {
                return false;
            }

            return true;
        });


        // Check exist user answer
        Validator::extend('orbit.exists.answer_id', function ($attribute, $value, $parameters) {
            $user_id = $parameters[0];
            $question_id = $parameters[1];
            $answer_id = $parameters[2];

            $existUserAnswer = UserAnswer::where('user_id', '=', $user_id)
                                    ->where('question_id', '=', $question_id)
                                    ->where('answer_id', '=', $answer_id)
                                    ->first();

            if (empty($existUserAnswer)) {
                return false;
            }

            return true;
        });

    }


}