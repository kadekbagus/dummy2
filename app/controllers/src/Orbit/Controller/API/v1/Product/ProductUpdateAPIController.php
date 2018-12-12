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
            $validRoles = $this->articleRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.newproduct.postupdateproduct.after.authz', array($this, $user));

            $productHelper = ProductHelper::create();
            $productHelper->productCustomValidator();

            $productId = OrbitInput::post('product_id');
            $status = OrbitInput::post('status');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'product_id'       => $productId,
                    'status'           => $status,
                ),
                array(
                    'product_id'       => 'required',
                    'status'           => 'in:active,inactive',
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

            $updatedProduct->modified_by = $user->user_id;
            $updatedProduct->touch();

            $updatedProduct->save();

            OrbitInput::post('categories', function($categories) use ($updatedProduct, $articleId) {
                $deletedOldData = ProductLinkToObject::where('product_id', '=', $articleId)
                                                     ->where('object_type', '=', 'category')
                                                     ->delete();

                $category = array();
                foreach ($categories as $categoryId) {
                    $saveObjectCategories = new ProductLinkToObject();
                    $saveObjectCategories->product_id = $articleId;
                    $saveObjectCategories->object_id = $categoryId;
                    $saveObjectCategories->object_type = 'category';
                    $saveObjectCategories->save();
                    $category[] = $saveObjectCategories;
                }
                $updatedProduct->category = $category;
            });

            OrbitInput::post('marketplaces', function($marketplaces) use ($updatedProduct, $articleId) {
                $deletedOldData = ProductLinkToObject::where('product_id', '=', $articleId)
                                                     ->where('object_type', '=', 'marketplace')
                                                     ->delete();

                $marketplace = array();
                foreach ($marketplaces as $marketplaceId) {
                    $saveObjectCategories = new ProductLinkToObject();
                    $saveObjectCategories->product_id = $articleId;
                    $saveObjectCategories->object_id = $marketplaceId;
                    $saveObjectCategories->object_type = 'marketplace';
                    $saveObjectCategories->save();
                    $marketplace[] = $saveObjectCategories;
                }
                $updatedProduct->marketplace = $marketplace;
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
        } catch (Exception $e) {
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