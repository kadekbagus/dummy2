<?php namespace Orbit\Controller\API\v1\Pub\Product;

/**
 * @author kadek <kadek@dominopos.com>
 * @desc Controller for product list in brand detail page
 */

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \URL;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Orbit\Controller\API\v1\Pub\Product\ProductHelper;
use Mall;
use Partner;
use \Orbit\Helper\Exception\OrbitCustomException;
use Product;
use stdclass;
use BaseStore;

class ProductListAPIController extends PubControllerAPI
{
     public function getSearchProduct()
    {
        $httpCode = 200;
        $user = NULL;

        try{
            $user = $this->getUser();

            $storeId = OrbitInput::get('store_id', null);
            $sort_by = OrbitInput::get('sortby', 'updated_at');
            $sort_mode = OrbitInput::get('sortmode','asc');

            $productHelper = ProductHelper::create();
            $productHelper->productCustomValidator();
            $validator = Validator::make(
                array(
                    'store_id' => $storeId,
                ),
                array(
                    'store_id' => 'required|orbit.exist.store_id',
                ),
                array(
                    'required' => 'Store ID is required',
                    'orbit.exist.store_id' => 'Store not found',
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

            $baseStore = BaseStore::select('base_merchant_id')->where('base_store_id', '=', $storeId)->first();
            $baseMerchantId = $baseStore->base_merchant_id;

            $product = Product::select(DB::raw("
                                    {$prefix}products.product_id,
                                    {$prefix}products.name,
                                    {$prefix}products.status,
                                    {$prefix}products.updated_at"
                                ))
                                ->join('product_link_to_object', function($q) use ($baseMerchantId){
                                             $q->on('product_link_to_object.product_id', '=',  'products.product_id')
                                               ->where('product_link_to_object.object_type', '=', 'brand')
                                               ->where('product_link_to_object.object_id', '=', $baseMerchantId);
                                    })
                                ->with(['media' => function ($q) use ($image) {
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
                                  }])
                                ->where('products.status', '=', 'active');

            OrbitInput::get('product_id', function($product_id) use ($product)
            {
                $product->where('product_id', $product_id);
            });

            OrbitInput::get('name_like', function($name) use ($product)
            {
                $product->where('name', 'like', "%$name%");
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_product = clone $product;

            $take = PaginationNumber::parseTakeFromGet('merchant');
            $product->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $product->skip($skip);

            // Default sort by
            $sortBy = 'updated_at';
            // Default sort mode
            $sortMode = 'desc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    //'name' => 'products.name',
                    'status' => 'products.status',
                    'updated_at' => 'products.updated_at',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $product->orderBy($sortBy, $sortMode);

            $totalItems = RecordCounter::create($_product)->count();
            $listOfItems = $product->get();

            $data = new stdclass();
            $data->total_records = $totalItems;
            $data->returned_records = count($listOfItems);
            $data->records = $listOfItems;

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            //$this->response->message = $message;

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

        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getCustomData();
            if ($this->response->code === 4040) {
                $httpCode = 404;
            } else {
                $httpCode = 500;
            }

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;

        }

        return $this->render($httpCode);
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
