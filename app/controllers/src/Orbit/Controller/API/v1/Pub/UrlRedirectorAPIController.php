<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
 */
use IntermediateBaseController;
use Orbit\Helper\Util\CorsHeader;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use OrbitShop\API\v1\ResponseProvider;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Illuminate\Database\QueryException;
use Config;
use Mall;
use stdClass;
use Redirect;
use Orbit\Helper\Session\UserGetter;
use Orbit\Helper\Net\SessionPreparer;
use Activity;

class UrlRedirectorAPIController extends IntermediateBaseController
{
    /**
     * GET - Redirect to external URL
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     */
    public function getRedirectUrl()
    {
        $this->response = new ResponseProvider();
        $user = NULL;
        $activity = Activity::mobileci();
        $httpCode = 200;
        try {
            $this->session->start([], 'no-session-creation');
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            $redirectUrls = Config::get('orbit.redirect_url_list');

            $type = OrbitInput::get('type');

            if (! array_key_exists($type, $redirectUrls)) {
                OrbitShopAPI::throwInvalidArgument('Url type is not supported.');
            }

            if (! isset($redirectUrls[$type])
                || empty($redirectUrls[$type])
                || ! isset($redirectUrls[$type]['url'])
                || empty($redirectUrls[$type]['url'])
            ) {
                OrbitShopAPI::throwInvalidArgument('No url found for requested type.');
            }

            if (isset($redirectUrls[$type])
                && ! empty($redirectUrls[$type])
                && isset($redirectUrls[$type]['activity_name'])
                && ! empty($redirectUrls[$type]['activity_name'])
                && isset($redirectUrls[$type]['activity_name_long'])
                && ! empty($redirectUrls[$type]['activity_name_long'])
                && isset($redirectUrls[$type]['activity_module_name'])
                && ! empty($redirectUrls[$type]['activity_module_name'])
                && isset($redirectUrls[$type]['activity_module_name'])
                && ! empty($redirectUrls[$type]['activity_module_name'])
                && isset($redirectUrls[$type]['activity_type'])
                && ! empty($redirectUrls[$type]['activity_type'])
                && is_object($user)
            ) {
                $activity->setActivityType($redirectUrls[$type]['activity_type'])
                    ->setUser($user)
                    ->setActivityName($redirectUrls[$type]['activity_name'])
                    ->setActivityNameLong($redirectUrls[$type]['activity_name_long'])
                    ->setObject(null)
                    ->setModuleName($redirectUrls[$type]['activity_module_name'])
                    ->setNotes('Redirected to: ' . $redirectUrls[$type]['url'])
                    ->responseOK()
                    ->save();
            }

            $url = $redirectUrls[$type]['url'];
            $params = [];
            $req = \Symfony\Component\HttpFoundation\Request::create($url, 'GET', $params);

            return Redirect::away($req->getUri(), 302, $this->getCORSHeaders());

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

        } catch (\Exception $e) {

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

        }

        return $this->render($this->response);
    }
}
