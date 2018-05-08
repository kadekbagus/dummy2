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
     * @param string    `object_type`   (required) - object type of the seo text
     * @param string    `language`      (required) - language
     * @param string    `mall_id`       (optional) - mall_id for object type seo_mall_homepage
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSeoText()
    {
        $httpCode = 200;
        try {
            $user = $this->getUser();

            $default_language = 'en';
            $object_type = OrbitInput::get('object_type');
            $language = OrbitInput::get('language', $default_language);
            $mall_id = OrbitInput::get('mall_id');
            $categoryId = OrbitInput::get('category_id', null);

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
                    $seo_text = Mall::select('description as seo_text')
                                    ->where('merchants.merchant_id', '=', $mall_id)
                                    ->first();
                    break;

                default:
                    $seo_text = Page::select('language', 'title', 'content as seo_text', 'status')
                                    ->where('content', '<>', '')
                                    ->where('status', '=', 'active')
                                    ->where('object_type', '=', $object_type)
                                    ->where('pages.language', '=', $language)
                                    ->where('pages.category_id', $categoryId)
                                    ->first();

                    if (empty($seo_text)) {
                        $seo_text = Page::select('language', 'title', 'content as seo_text', 'status')
                                        ->where('object_type', '=', $object_type)
                                        ->where('status', '=', 'active')
                                        ->where('content', '<>', '')
                                        ->where('pages.language', '=', $default_language)
                                        ->where('pages.category_id', $categoryId)
                                        ->first();

                        if (empty($seo_text) && ! empty($categoryId)) {
                            $seo_text = Page::select('language', 'title', 'content as seo_text', 'status')
                                            ->where('object_type', '=', $object_type)
                                            ->where('status', '=', 'active')
                                            ->where('content', '<>', '')
                                            ->where('pages.language', '=', $language)
                                            ->whereNull('pages.category_id')
                                            ->first();

                            if (empty($seo_text)) {
                                $seo_text = Page::select('language', 'title', 'content as seo_text', 'status')
                                                ->where('object_type', '=', $object_type)
                                                ->where('status', '=', 'active')
                                                ->where('content', '<>', '')
                                                ->where('pages.language', '=', $default_language)
                                                ->whereNull('pages.category_id')
                                                ->first();
                            }
                        }
                    }

                    break;
            }

            $this->response->data = new stdClass();
            $this->response->data = $seo_text;
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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
