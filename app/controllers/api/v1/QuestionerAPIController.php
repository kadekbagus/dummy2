<?php
/**
 * An API controller for managing News.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;


class QuestionerAPIController extends ControllerAPI
{
    /**
     * Flag to return the query builder.
     *
     * @var Builder
     */

    protected $validRoles = ['consumer', 'guest'];


    /**
     * GET - question and answer selection
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer id
     * @param array ids
     * @param string status
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getQuestion()
    {
        $httpCode = 200;
        try {

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.activity.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.activity.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $questions = Question::with('answers');

             // Filter by id
            OrbitInput::get('id', function($questionId) use ($questions) {
                $questions->where('questions.question_id', $questionId);
            });

            // Filter by ids
            OrbitInput::get('ids', function($questionId) use ($questions) {
                $questions->whereIn('questions.question_id', $questionId);
            });

            // Filter by status
            OrbitInput::get('status', function($status) use ($questions) {
                $questions->where('questions.status', $status);
            });

            $_questions = clone $questions;

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $questions->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $questions) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $questions->skip($skip);

            // Default sort by
            $sortBy = 'questions.question_id';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'id'              => 'questions.question_id',
                    'status'          => 'questions.status',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'desc') {
                    $sortMode = 'asc';
                }
            });
            $questions->orderBy($sortBy, $sortMode);

            $listQuestions = $questions->get();
            $count = RecordCounter::create($_questions)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listQuestions);
            $this->response->data->records = $listQuestions;
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
     * @param string question_id
     * @param string answer_id
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUserAnswer()
    {

        $httpCode = 200;

        try {

            Event::fire('orbit.questioner.postuseranswer.before.auth', array($this));

            // $this->checkAuth();

            Event::fire('orbit.questioner.postuseranswer.after.auth', array($this));

            $this->registerCustomValidation();

            // $user_id = $this->api->user->user_id;
            $user_id = OrbitInput::post('user_id');
            $question_id = OrbitInput::post('question_id');
            $answer_id = OrbitInput::post('answer_id');

            $validator = Validator::make(
                array(
                    'user_id'     => $user_id,
                    'question_id' => $question_id,
                    'answer_id'   => $answer_id,
                ),
                array(
                    'user_id'     => 'required|orbit.empty.user',
                    'question_id' => 'required|orbit.empty.question_id',
                    'answer_id'   => 'required|orbit.empty.answer_id|orbit.exists.answer_id:' . $user_id . ',' . $question_id . ',' .$answer_id,
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
            $newuseranswer->question_id = $question_id;
            $newuseranswer->answer_id = $answer_id;

            Event::fire('orbit.questioner.postuseranswer.before.save', array($this, $newuseranswer));

            $newuseranswer->save();

            Event::fire('orbit.questioner.postuseranswer.after.save', array($this, $newuseranswer));

            $this->response->data = $newuseranswer;

            // Commit the changes
            $this->commit();

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

        } catch (InvalidArgsException $e) {
            Event::fire('orbit.questioner.postuseranswer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

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

        } catch (Exception $e) {
            Event::fire('orbit.questioner.postuseranswer.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            $this->rollBack();

        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check the exist use_id
        Validator::extend('orbit.empty.user', function ($attribute, $value, $parameters) {

            $userId = User::where('user_id', '=', $value)
                                    ->first();

            if (empty($userId)) {
                return false;
            }

            return true;
        });

        // Check exist quenstion
        Validator::extend('orbit.empty.question_id', function ($attribute, $value, $parameters) {
            $quenstion = Question::where('question_id', '=', $value)
                                    ->first();

            if (empty($quenstion)) {
                return false;
            }

            return true;
        });

        // Check exist answer
        Validator::extend('orbit.empty.answer_id', function ($attribute, $value, $parameters) {
            $quenstion = Answer::where('answer_id', '=', $value)
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
                return true;
            }

            return false;
        });
    }


}
