<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * API for getting seo text
 * @author kadek <kadek@dominopos.com>
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\OrbitShopAPI;
use Validator;
use stdClass;
use Page;
use Mall;
use DB;

class SeoTextAPIController extends PubControllerAPI
{
    /**
     * GET - SEO Text
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `object_type`          (required) - object type of the seo text
     * @param string    `language`             (required) - language
     * @param string    `mall_id`              (optional) - mall_id for object type seo_mall_homepage
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSeoText()
    {
    	$httpCode = 200;
    	try {
    		$user = $this->getUser();
            $object_type = OrbitInput::get('object_type');
            $language = OrbitInput::get('language', 'id');
            $mall_id = OrbitInput::get('mall_id');

            $validator = Validator::make(
                array(
                    'object_type'   => $object_type
                ),
                array(
                    'object_type'   => 'required|in:seo_promotion_list,seo_coupon_list,seo_event_list,seo_store_list,seo_mall_list,seo_homepage,seo_mall_homepage'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            switch ($object_type) {
            	case 'seo_mall_homepage':
            		$seo_text = Mall::select(DB::raw("CASE WHEN ({$prefix}merchant_translations.description = '' or
            													 {$prefix}merchant_translations.description is null)
            										  THEN {$prefix}merchants.description
								                      ELSE {$prefix}merchant_translations.description
							                          END as seo_text"),
					                        'languages.name as language')
									->leftJoin('merchant_translations', 'merchant_translations.merchant_id', '=', 'merchants.merchant_id')
									->leftJoin('languages', 'languages.language_id', '=', 'merchant_translations.merchant_language_id')
									->where('merchants.merchant_id', '=', $mall_id);
            			break;

            	default:
            		$seo_text = Page::select('content as seo_text', 'language')
            						->where('object_type', '=', $object_type)
            						->where('status', '=', 'active');
            			break;
            }

            OrbitInput::get('language', function($language) use ($seo_text, $object_type) {
            	if ($object_type === 'seo_mall_homepage') {
            		$seo_text->where('languages.name', '=', $language);
            	} else {
                	$seo_text->where('pages.language', '=', $language);
            	}
            });

            $seo = $seo_text->get();

            $this->response->data = new stdClass();
            $this->response->data = $seo;
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