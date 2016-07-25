<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * API Controller for generic activity (mobileci group) that doesn't have API
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use IntermediateBaseController;
use OrbitShop\API\v1\OrbitShopAPI;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\CommonAPIControllerTrait;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use OrbitShop\API\v1\ResponseProvider;
use Illuminate\Database\QueryException;
use Orbit\Helper\Net\GuestUserGenerator;
use Config;
use stdClass;
use \Activity;
use Validator;
use User;
use Lang;
use Mall;
use Orbit\Helper\Session\UserGetter;

class GenericActivityAPIController extends IntermediateBaseController
{
    public function postNewGenericActivity()
    {
        $this->response = new ResponseProvider();
        $user = NULL;
        $httpCode = 200;

        $genericActivityConfig = Config::get('orbit.generic_activity');

        if (empty($genericActivityConfig)) {
            $this->response->code = 1;
            $this->response->status = 'error';
            $this->response->message = 'Activity config is not configured correctly.';
            $this->response->data = null;
            return $this->render($this->response);
        }

        $activityParamName = $genericActivityConfig['parameter_name'];
        $activityNumber = OrbitInput::post($activityParamName, NULL);

        if (empty($activityNumber)) {
            $this->response->code = 1;
            $this->response->status = 'error';
            $this->response->message = 'Activity identifier is required.';
            $this->response->data = null;
            return $this->render($this->response);
        }

        if (! array_key_exists($activityNumber, $genericActivityConfig['activity_list'])) {
            $this->response->code = 1;
            $this->response->status = 'error';
            $this->response->message = 'Activity identifier is not found.';
            $this->response->data = null;
            return $this->render($this->response);
        }

        if (! isset($genericActivityConfig['activity_list'])
            || ! isset($genericActivityConfig['activity_list'][$activityNumber])
            || ! isset($genericActivityConfig['activity_list'][$activityNumber]['name'])
            || empty($genericActivityConfig['activity_list'][$activityNumber]['name'])
        ) {
            $this->response->code = 1;
            $this->response->status = 'error';
            $this->response->message = 'Activity config is not configured correctly.';
            $this->response->data = null;
            return $this->render($this->response);
        }

        $activityName = $genericActivityConfig['activity_list'][$activityNumber]['name'];
        $activityNameLong = $genericActivityConfig['activity_list'][$activityNumber]['name_long'];
        $activityModuleName = $genericActivityConfig['activity_list'][$activityNumber]['module_name'];
        $activityType = $genericActivityConfig['activity_list'][$activityNumber]['type'];

        $activity = Activity::mobileci()->setActivityType($activityType);
        try {
            $this->session->start([], 'no-session-creation');

            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            $activity->setUser($user)
                ->setActivityName($activityName)
                ->setActivityNameLong($activityNameLong)
                ->setObject(null)
                ->setModuleName($activityModuleName)
                ->responseOK()
                ->save();

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
            $this->response->data = $activity->activity_id;

        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            $activity->setUser($user)
                ->setActivityName($activityName)
                ->setActivityNameLong($activityNameLong)
                ->setObject(null)
                ->setModuleName($activityModuleName)
                ->setNotes('Failed to save activity: ' . $activityNameLong . '. Error: ' . $e->getMessage())
                ->responseFailed()
                ->save();
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            $activity->setUser($user)
                ->setActivityName($activityName)
                ->setActivityNameLong($activityNameLong)
                ->setObject(null)
                ->setModuleName($activityModuleName)
                ->setNotes('Failed to save activity: ' . $activityNameLong . '. Error: ' . $e->getMessage())
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

            $activity->setUser($user)
                ->setActivityName($activityName)
                ->setActivityNameLong($activityNameLong)
                ->setObject(null)
                ->setModuleName($activityModuleName)
                ->setNotes('Failed to save activity: ' . $activityNameLong . '. Error: ' . $e->getMessage())
                ->responseFailed()
                ->save();
        } catch (\Exception $e) {

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

            $activity->setUser($user)
                ->setActivityName($activityName)
                ->setActivityNameLong($activityNameLong)
                ->setObject(null)
                ->setModuleName($activityModuleName)
                ->setNotes('Failed to save activity: ' . $activityNameLong . '. Error: ' . $e->getMessage())
                ->responseFailed()
                ->save();
        }

        return $this->render($this->response);
    }
}
