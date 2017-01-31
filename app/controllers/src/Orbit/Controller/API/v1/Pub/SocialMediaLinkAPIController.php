<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing pages with multilanguage.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use Validator;

class SocialMediaLinkAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;

    /**
     * GET - get social media
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string language
     *
     * @return Illuminate\Support\Facades\Response
     */

    public function getSocialMediaLink()
    {
        $httpCode = 200;
        $user = null;

        try {
            $user = $this->getUser();
            $country = OrbitInput::get('country');

            $validator = Validator::make(
                array(
                    'country' => $country,
                ),
                array(
                    'country' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $socielMediaLink = Config::get('orbit.social_media_account.country.' . $country);

            $count = count($socielMediaLink);

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = $count;
            $this->response->data->records = $socielMediaLink;
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

}
