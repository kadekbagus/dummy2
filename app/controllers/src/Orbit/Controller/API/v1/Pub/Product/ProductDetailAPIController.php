<?php namespace Orbit\Controller\API\v1\Pub\Product;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use OrbitShop\API\v1\PubControllerAPI;

use Product;
use Lang;
use DB;
use Validator;
use Language;
use Config;

class ProductDetailAPIController extends PubControllerAPI
{
    protected $validLanguage = NULL;
    protected $allowedRoles = ['product manager'];

    /**
     * GET Detail Product
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function getDetailProduct()
    {
        $httpCode = 200;
        $user = NULL;

        try {
            $user = $this->getUser();

            $productId = OrbitInput::get('product_id');
            $language = OrbitInput::get('language', 'id');

            $prefix = DB::getTablePrefix();

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'product_id' => $productId,
                    'language' => $language,
                ),
                array(
                    'product_id' => 'required',
                    'language' => 'orbit.empty.language_default',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();
            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as cdn_url";
            if ($usingCdn) {
                $image = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as cdn_url";
            }

            $validLanguage = $this->validLanguage;
            $product = Product::with([
                    'media' => function ($q) use ($image) {
                                        $q->select(
                                                DB::raw("{$image}"),
                                                'media.media_id',
                                                'media.media_name_id',
                                                'media.media_name_long',
                                                'media.object_id',
                                                'media.object_name',
                                                'media.file_name',
                                                'media.file_extension',
                                                'media.file_size',
                                                'media.mime_type',
                                                'media.path',
                                                'media.cdn_bucket_name',
                                                'media.metadata'
                                            );
                    },
                    'marketplaces' => function ($q) use ($image) {
                        $q->with(['media' => function ($q) use ($image) {
                                        $q->select(
                                                DB::raw("{$image}"),
                                                'media.media_id',
                                                'media.media_name_id',
                                                'media.media_name_long',
                                                'media.object_id',
                                                'media.object_name',
                                                'media.file_name',
                                                'media.file_extension',
                                                'media.file_size',
                                                'media.mime_type',
                                                'media.path',
                                                'media.cdn_bucket_name',
                                                'media.metadata'
                                            );
                                  }]);
                        $q->where('marketplaces.status', 'active');
                    },
                    'country',
                    'categories' => function ($q) use ($validLanguage, $prefix) {
                        $q->select(
                                DB::Raw("
                                        CASE WHEN (
                                                    SELECT ct.category_name
                                                    FROM {$prefix}category_translations ct
                                                        WHERE ct.status = 'active'
                                                            and ct.merchant_language_id = {$this->quote($validLanguage->language_id)}
                                                            and ct.category_id = {$prefix}categories.category_id
                                                    ) != ''
                                            THEN (
                                                    SELECT ct.category_name
                                                    FROM {$prefix}category_translations ct
                                                    WHERE ct.status = 'active'
                                                        and ct.merchant_language_id = {$this->quote($validLanguage->language_id)}
                                                        and category_id = {$prefix}categories.category_id
                                                    )
                                            ELSE {$prefix}categories.category_name
                                        END AS category_name
                                    ")
                            )
                            ->groupBy('categories.category_id')
                            ->orderBy('category_name');
                    }
                ])
                ->where('product_id', $productId)
                ->firstOrFail();

            $this->response->data = $product;
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
        }

        $output = $this->render($httpCode);

        return $output;
    }

    protected function registerCustomValidation() {
        // Check language is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->validLanguage = $language;
            return TRUE;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}