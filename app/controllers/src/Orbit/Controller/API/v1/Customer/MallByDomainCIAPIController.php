<?php namespace Orbit\Controller\API\v1\Customer;

/**
 * @author Ahmad <ahmad@dominopos.com>
 * @desc Controller for Getting Mall Object by Domain
 */
use Orbit\Controller\API\v1\Customer\BaseAPIController;
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Setting;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \Carbon\Carbon as Carbon;
use \Validator;
use Tenant;
use Mall;
use MerchantLanguage;
use App;
use Lang;

class MallByDomainCIAPIController extends BaseAPIController
{
    public function getMallIdByDomain()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $subDom = OrbitInput::get('sub_domain', NULL);
            $validator = Validator::make(
                array(
                    'sub_domain' => $subDom,
                ),
                array(
                    'sub_domain' => 'required',
                )
            );
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $dom = $subDom . '.' . Config::get('orbit.shop.main_domain');

            $data = NULL;

            $mall = Setting::getMallByDomain($dom);
            $membership_card = Setting::where('setting_name','enable_membership_card')->where('object_id',$mall->merchant_id)->first();

            $enable_membership='false';
            if (is_object($membership_card)){
                $enable_membership=$membership_card->setting_value;
            }

            if (is_object($mall)) {
                $mall = $mall->load('mediaLogoOrig');
                $mall = $mall->load('merchantSocialMedia.socialMedia');

                $facebook_like_url = '';
                foreach ($mall->merchantSocialMedia as $merchantSocialMedia) {
                    if (is_object($merchantSocialMedia->socialMedia)) {
                        if ($merchantSocialMedia->socialMedia->social_media_code === 'facebook') {
                            $facebook_like_url = $merchantSocialMedia->social_media_uri;
                        }
                    }
                }
                $mallLogo = '';
                if (isset($mall->mediaLogoOrig[0])) {
                    $mallLogo = $mall->mediaLogoOrig[0]->path;
                }

                $mallLanguages = $this->getListLanguages($mall);

                $data = new \stdclass();
                $data->merchant_id = $mall->merchant_id;
                $data->name = $mall->name;
                $data->mobile_default_language = $mall->mobile_default_language;
                $data->logo = $mallLogo;
                $data->facebook_like_url = $facebook_like_url;
                $data->supported_languages = $mallLanguages;
                $data->auth_pages = Config::get('orbit.blocked_routes', []);
                $data->pop_up_delay = Config::get('orbit.shop.event_delay', 2.5);
                $data->enable_membership = $enable_membership;
                $data->cookie_domain = Config::get('orbit.session.session_origin.cookie.domain');
                $data->after_logout_url = Config::get('orbit.shop.after_logout_url');
            }

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
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

        return $this->render($httpCode);
    }

    /**
    * Get list language from current merchant or mall
    *
    * @param mall     `mall`    mall object
    *
    * @author Firmansyah <firmansyah@dominopos.com>
    * @author Irianto Pratama <irianto@dominopos.com>
    *
    * @return array or collection
    */
    protected function getListLanguages($mall)
    {
        $languages = MerchantLanguage::select(
                'languages.language_id',
                'languages.name',
                'languages.name_long'
            )
            ->join('languages', 'languages.language_id', '=','merchant_languages.language_id')
            ->where('merchant_languages.status', '!=', 'deleted')
            ->where('merchant_id', $mall->merchant_id)
            ->where('languages.status', 'active')
            ->orderBy('languages.name_long', 'ASC')
            ->get();

        return $languages;
    }
}
