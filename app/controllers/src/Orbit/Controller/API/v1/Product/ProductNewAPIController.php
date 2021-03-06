<?php namespace Orbit\Controller\API\v1\Product;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Validator;
use Orbit\Controller\API\v1\Product\ProductHelper;

use Lang;
use Config;
use Category;
use Event;
use Tenant;
use BaseMerchant;
use Product;
use ProductLinkToObject;
use ProductVideo;
use ProductTag;
use ProductTagObject;
use App;

class ProductNewAPIController extends ControllerAPI
{
    protected $calledFrom = '';

    protected $productRoles = ['product manager'];

    /**
     * Create new product on product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function postNewProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.newproduct.postnewproduct.before.auth', array($this));

            if (! $this->callingFrom('massupload')) {

                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.newproduct.postnewproduct.after.auth', array($this));

                // Try to check access control list, does this user allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.newproduct.postnewproduct.before.authz', array($this, $user));

                // @Todo: Use ACL authentication instead
                $role = $user->role;
                $validRoles = $this->productRoles;
                if (! in_array(strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.newproduct.postnewproduct.after.authz', array($this, $user));
            } else {
                $user = App::make('orbit.product.user');
            }

            $productHelper = ProductHelper::create();
            $productHelper->productCustomValidator();

            $name = OrbitInput::post('name');
            $shortDescription = OrbitInput::post('short_description');
            $status = OrbitInput::post('status', 'inactive');
            $countryId = OrbitInput::post('country_id');
            $categories = OrbitInput::post('categories', []);
            $marketplaces = OrbitInput::post('marketplaces', []);
            $brandIds = OrbitInput::post('brand_ids', []);
            $youtubeIds = OrbitInput::post('youtube_ids', []);
            $images = $_FILES['images'];
            $productTags = OrbitInput::post('product_tags');
            $productTags = (array) $productTags;

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'name'              => $name,
                    'status'            => $status,
                    'country_id'        => $countryId,
                    'images'            => $images,
                    'short_description' => $shortDescription,
                    'categories'        => $categories,
                    'brand_ids'         => $brandIds,
                    'marketplaces'      => $marketplaces,
                ),
                array(
                    'name'              => 'required',
                    'status'            => 'in:active,inactive',
                    'country_id'        => 'required',
                    'images'            => 'required',
                    'short_description' => 'required',
                    'categories'        => 'required|array',
                    'brand_ids'         => 'array',
                    'marketplaces'      => 'required|orbit.empty.marketplaces',
                ),
                array(
                    'name.required'                 => 'Product Title field is required',
                    'country_id.required'           => 'Country field is required',
                    'images.required'               => 'Product Main Image is required',
                    'short_description.required'    => 'Product Description is required',
                    'categories.required'           => 'Product Category is required',
                    'orbit.empty.marketplaces'      => 'Link to Affiliates is required',
                    'brand_ids.required'            => 'Link to Brand is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.newproduct.postnewproduct.after.validation', array($this, $validator));

            $newProduct = new Product;
            $newProduct->name = $name;
            $newProduct->short_description = $shortDescription;
            $newProduct->status = $status;
            $newProduct->country_id = $countryId;

            Event::fire('orbit.newproduct.postnewproduct.before.save', array($this, $newProduct));

            $newProduct->save();

            $category = array();
            foreach ($categories as $categoryId) {
                $saveObjectCategories = new ProductLinkToObject();
                $saveObjectCategories->product_id = $newProduct->product_id;
                $saveObjectCategories->object_id = $categoryId;
                $saveObjectCategories->object_type = 'category';
                $saveObjectCategories->save();
                $category[] = $saveObjectCategories;
            }
            $newProduct->category = $category;

            $brands = array();
            foreach ($brandIds as $brandId) {
                $saveObjectCategories = new ProductLinkToObject();
                $saveObjectCategories->product_id = $newProduct->product_id;
                $saveObjectCategories->object_id = $brandId;
                $saveObjectCategories->object_type = 'brand';
                $saveObjectCategories->save();
                $brands[] = $saveObjectCategories;
            }
            $newProduct->brands = $brands;

            $videos = array();
            foreach ($youtubeIds as $youtubeId) {
                $productVideos = new ProductVideo();
                $productVideos->product_id = $newProduct->product_id;
                $productVideos->youtube_id = $youtubeId;
                $productVideos->save();
                $videos[] = $productVideos;
            }
            $newProduct->product_videos = $videos;

            $tags = array();
            foreach ($productTags as $productTag) {
                $product_tag_id = null;

                $existProductTag = ProductTag::excludeDeleted()
                    ->where('product_tag', '=', $productTag)
                    ->where('merchant_id', '=', 0)
                    ->first();

                if (empty($existProductTag)) {
                    $newProductTag = new ProductTag();
                    $newProductTag->merchant_id = 0;
                    $newProductTag->product_tag = $productTag;
                    $newProductTag->status = 'active';
                    $newProductTag->created_by = $this->api->user->user_id;
                    $newProductTag->modified_by = $this->api->user->user_id;
                    $newProductTag->save();

                    $product_tag_id = $newProductTag->product_tag_id;
                    $tags[] = $newProductTag;
                } else {
                    $product_tag_id = $existProductTag->product_tag_id;
                    $tags[] = $existProductTag;
                }

                $newProductTagObject = new ProductTagObject();
                $newProductTagObject->product_tag_id = $product_tag_id;
                $newProductTagObject->object_id = $newProduct->product_id;
                $newProductTagObject->object_type = 'product';
                $newProductTagObject->save();
            }
            $newProduct->product_tags = $tags;

            // save translations
            OrbitInput::post('marketplaces', function($marketplace_json_string) use ($newProduct, $productHelper) {
                $productHelper->validateAndSaveMarketplaces($newProduct, $marketplace_json_string, $scenario = 'create');
            });

            Event::fire('orbit.newproduct.postnewproduct.after.save', array($user, $newProduct));

            $this->response->data = $newProduct;

            // Commit the changes
            $this->commit();

          Event::fire('orbit.newproduct.postnewproduct.after.commit', array($this, $newProduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.newproduct.postnewproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.newproduct.postnewproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.newproduct.postnewproduct.query.error', array($this, $e));

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

            // Rollback the changes
            $this->rollBack();
        } catch (\Exception $e) {
            Event::fire('orbit.newproduct.postnewproduct.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    public function callingFrom($list)
    {
        if (! is_array($list))
        {
            $list = explode(',', (string)$list);
            $list = array_map('trim', $list);
        }

        return in_array($this->calledFrom, $list);
    }

    public function setCalledFrom($from)
    {
        $this->calledFrom = $from;

        return $this;
    }
}
