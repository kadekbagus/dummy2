<?php
/**
 * An API controller for shutting down or rebooting the Box.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\OS\Shutdown;

class ShutdownAPIController extends ControllerAPI
{
    /**
     * POST - Shutdown box
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postShutdownBox()
    {
        $activity = Activity::portal()
                            ->setActivityType('box_control')
                            ->setModuleName('Application');

        $userObject = NULL;
        $activityName = 'box_shutdown';
        $activityNameLong = 'Shutdown Box';

        try {
            $httpCode = 200;

            Event::fire('orbit.widget.postshutdownbox.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.widget.postshutdownbox.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            $userObject = $user;

            Event::fire('orbit.widget.postshutdownbox.before.authz', array($this, $user));

            // @Todo, remove this hardcode to permission list
            $allowedRoles = ['super admin', 'merchant owner', 'retailer owner'];
            $userRole = strtolower($user->role->role_name);

            if (! in_array($userRole, $allowedRoles)) {
                Event::fire('orbit.widget.postshutdownbox.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.shutdown_box');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.widget.postshutdownbox.after.authz', array($this, $user));

            $shutdown = Shutdown::create()->poweroff();

            if ($shutdown['status'] === FALSE) {
                OrbitShopAPI::throwInvalidArgument($shutdown['message']);
            }

            // Successfull execution
            $activity->setUser($user)
                     ->setActivityName($activityName)
                     ->setActivityNameLong($activityNameLong)
                     ->setModuleName('Application')
                     ->setNotes($shutdown['message'])
                     ->responseOK();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($userObject)
                     ->setActivityName($activityName)
                     ->setActivityNameLong($activityNameLong)
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($userObject)
                     ->setActivityName($activityName)
                     ->setActivityNameLong($activityNameLong)
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($userObject)
                     ->setActivityName($activityName)
                     ->setActivityNameLong($activityNameLong)
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Shutdown box
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postRebootBox()
    {
        $activity = Activity::portal()
                            ->setActivityType('box_control')
                            ->setModuleName('Application');

        $userObject = NULL;
        $activityName = 'box_reboot';
        $activityNameLong = 'Reboot Box';

        try {
            $httpCode = 200;

            Event::fire('orbit.widget.postrebootbox.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.widget.postrebootbox.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            $userObject = $user;

            Event::fire('orbit.widget.postrebootbox.before.authz', array($this, $user));

            // @Todo, remove this hardcode to permission list
            $allowedRoles = ['super admin', 'merchant owner', 'retailer owner'];
            $userRole = strtolower($user->role->role_name);

            if (! in_array($userRole, $allowedRoles)) {
                Event::fire('orbit.widget.postrebootbox.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.shutdown_box');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.widget.postrebootbox.after.authz', array($this, $user));

            $shutdown = Shutdown::create()->reboot();

            if ($shutdown['status'] === FALSE) {
                OrbitShopAPI::throwInvalidArgument($shutdown['message']);
            }

            // Successfull execution
            $activity->setUser($user)
                     ->setActivityName($activityName)
                     ->setActivityNameLong($activityNameLong)
                     ->setModuleName('Application')
                     ->setNotes($shutdown['message'])
                     ->responseOK();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($userObject)
                     ->setActivityName($activityName)
                     ->setActivityNameLong($activityNameLong)
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($userObject)
                     ->setActivityName($activityName)
                     ->setActivityNameLong($activityNameLong)
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $activity->setUser($userObject)
                     ->setActivityName($activityName)
                     ->setActivityNameLong($activityNameLong)
                     ->setNotes($e->getMessage())
                     ->responseFailed();
        }

        $activity->save();

        return $this->render($httpCode);
    }
}
