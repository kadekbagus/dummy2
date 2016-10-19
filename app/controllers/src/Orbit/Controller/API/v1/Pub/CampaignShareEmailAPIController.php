<?php namespace Orbit\Controller\API\v1\Pub;

/**
 * An API controller for getting generic activity.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Config;
use stdClass;
use OrbitShop\API\v1\ResponseProvider;
use Orbit\Helper\Net\SessionPreparer;
use Orbit\Helper\Session\UserGetter;
use Validator;
use News;
use Coupon;
use \Queue;

class CampaignShareEmailAPIController extends ControllerAPI
{
    /**
     * post - share gotomalls campaign via email
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string email
     * @param string campaign_id
     * @param string campaign_type
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postCampaignShareEmail()
    {

        $httpCode = 200;
        $this->response = new ResponseProvider();
    	try {

            $this->session = SessionPreparer::prepareSession();
            $user = UserGetter::getLoggedInUserOrGuest($this->session);

            $email = OrbitInput::post('email');
            $campaign_id = OrbitInput::post('campaign_id');
            $campaign_type = OrbitInput::post('campaign_type');
            $language = OrbitInput::get('language', 'id');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'email'         => $email,
                    'campaign_id'   => $campaign_id,
                    'campaign_type' => $campaign_type,
                ),
                array(
                    'email'         => 'required|email',
                    'campaign_id'   => 'required|orbit.empty.campaign',
                    'campaign_type' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // send the email via queue
            Queue::push('Orbit\\Queue\\CampaignShareMail', [
                'email'              => $email,
                'campaignId'         => $campaign_id,
                'campaignType'       => $campaign_type,
                'userId'             => $user->user_id,
                'languageId'         => $language,
            ]);

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
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

        } catch (Exception $e) {

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {

        // Check the existance of the campaign (promotion,news,coupon)
        Validator::extend('orbit.empty.campaign', function ($attribute, $value, $parameters) {

            $campaign_type = OrbitInput::post('campaign_type');

            switch($campaign_type) {
                case 'promotion' :
                        $campaign = News::where('news_id', '=', $value)
                                          ->where('object_type', '=', 'promotion')
                                          ->first();
                        break;
                case 'news' :
                        $campaign = News::where('news_id', '=', $value)
                                          ->where('object_type', '=', 'news')
                                          ->first();
                        break;
                case 'coupon' :
                        $campaign = Coupon::where('promotion_id', '=', $value)
                                          ->first();
                        break;
                default :
                        $campaign = null;
            }

            if (empty($campaign)) {
                return FALSE;
            }

            return TRUE;
        });
    }
}