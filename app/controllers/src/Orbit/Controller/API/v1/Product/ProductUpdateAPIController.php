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

class ProductUpdateAPIController extends ControllerAPI
{
    protected $productRoles = ['product manager'];

    /**
     * Update product on product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function postUpdateProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.newproduct.postupdateproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.newproduct.postupdateproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.newproduct.postupdateproduct.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->productRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.newproduct.postupdateproduct.after.authz', array($this, $user));

            $productHelper = ProductHelper::create();
            $productHelper->productCustomValidator();

            $productId = OrbitInput::post('product_id');
            $name = OrbitInput::post('name');
            $shortDescription = OrbitInput::post('short_description');
            $status = OrbitInput::post('status');
            $countryId = OrbitInput::post('country_id');
            $categories = OrbitInput::post('categories');
            $marketplaces = OrbitInput::post('marketplaces');
            $brandIds = OrbitInput::post('brand_ids');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'product_id'        => $productId,
                    'name'              => $name,
                    'status'            => $status,
                    'country_id'        => $countryId,
                    'short_description' => $shortDescription,
                    'categories'        => $categories,
                    'brand_ids'         => $brandIds,
                    'marketplaces'      => $marketplaces,
                ),
                array(
                    'product_id'        => 'required',
                    'name'              => 'required',
                    'status'            => 'in:active,inactive',
                    'country_id'        => 'required',
                    'short_description' => 'required',
                    'categories'        => 'required|array',
                    'brand_ids'         => 'array',
                    'marketplaces'      => 'required|orbit.empty.marketplaces',
                ),
                array(
                    'name.required'                 => 'Product Title field is required',
                    'country_id.required'           => 'Country field is required',
                    'short_description.required'    => 'Product Description is required',
                    'categories.required'           => 'Product Category is required',
                    'orbit.empty.marketplaces'      => 'Link to Affiliates is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.newproduct.postupdateproduct.after.validation', array($this, $validator));

            $updatedProduct = Product::where('product_id', $productId)->first();

            OrbitInput::post('name', function($name) use ($updatedProduct) {
                $updatedProduct->name = $name;
            });

            OrbitInput::post('short_description', function($short_description) use ($updatedProduct) {
                $updatedProduct->short_description = $short_description;
            });

            OrbitInput::post('status', function($status) use ($updatedProduct) {
                $updatedProduct->status = $status;
            });

            OrbitInput::post('country_id', function($country_id) use ($updatedProduct) {
                $updatedProduct->country_id = $country_id;
            });

            Event::fire('orbit.newproduct.postupdateproduct.before.save', array($this, $updatedProduct));

            $updatedProduct->touch();
            $updatedProduct->save();

            // update category
            OrbitInput::post('categories', function($categories) use ($updatedProduct, $productId) {
                $deletedOldData = ProductLinkToObject::where('product_id', '=', $productId)
                                                     ->where('object_type', '=', 'category')
                                                     ->delete();

                $category = array();
                foreach ($categories as $categoryId) {
                    $saveObjectCategories = new ProductLinkToObject();
                    $saveObjectCategories->product_id = $productId;
                    $saveObjectCategories->object_id = $categoryId;
                    $saveObjectCategories->object_type = 'category';
                    $saveObjectCategories->save();
                    $category[] = $saveObjectCategories;
                }
                $updatedProduct->category = $category;
            });

            // update brands
            OrbitInput::post('brand_ids', function($brandIds) use ($updatedProduct, $productId) {
                $deletedOldData = ProductLinkToObject::where('product_id', '=', $productId)
                                                     ->where('object_type', '=', 'brand')
                                                     ->delete();

                $brands = array();
                foreach ($brandIds as $brandId) {
                    $saveObjectCategories = new ProductLinkToObject();
                    $saveObjectCategories->product_id = $productId;
                    $saveObjectCategories->object_id = $brandId;
                    $saveObjectCategories->object_type = 'brand';
                    $saveObjectCategories->save();
                    $brands[] = $saveObjectCategories;
                }
                $updatedProduct->brands = $brands;
            });

            // update product_videos
            OrbitInput::post('youtube_ids', function($youtubeIds) use ($updatedProduct, $productId) {
                $deletedOldData = ProductVideo::where('product_id', '=', $productId)->delete();

                $videos = array();
                foreach ($youtubeIds as $youtubeId) {
                    $productVideos = new ProductVideo();
                    $productVideos->product_id = $productId;
                    $productVideos->youtube_id = $youtubeId;
                    $productVideos->save();
                    $videos[] = $productVideos;
                }
                $updatedProduct->product_videos = $videos;
            });

            // update marketplaces
            OrbitInput::post('marketplaces', function($marketplace_json_string) use ($updatedProduct, $productHelper) {
                $productHelper->validateAndSaveMarketplaces($updatedProduct, $marketplace_json_string, $scenario = 'update');
            });

            Event::fire('orbit.newproduct.postupdateproduct.after.save', array($this, $updatedProduct));

            $this->response->data = $updatedProduct;

            // Commit the changes
            $this->commit();

          Event::fire('orbit.newproduct.postupdateproduct.after.commit', array($this, $updatedProduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.newproduct.postupdateproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.newproduct.postupdateproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.newproduct.postupdateproduct.query.error', array($this, $e));

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
            Event::fire('orbit.newproduct.postupdateproduct.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }
}
