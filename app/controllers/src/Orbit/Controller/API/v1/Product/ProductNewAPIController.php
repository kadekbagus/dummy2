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


class ProductNewAPIController extends ControllerAPI
{
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

            Event::fire('orbit.product.postnewproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postnewproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postnewproduct.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->productRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.product.postnewproduct.after.authz', array($this, $user));

            $productHelper = ProducteHelper::create();
            $productHelper->articleCustomValidator();

            $name = OrbitInput::post('name');
            $shortDescription = OrbitInput::post('short_description');
            $status = OrbitInput::post('status');
            $countryId = OrbitInput::post('country_id');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'name'             => $name,
                    'short_description'=> $shortDescription,
                    'status'           => $status,
                    'country_id'       => $countryId,
                ),
                array(
                    'name'             => 'required',
                    'status'           => 'required|in:active,inactive',
                    'country_id'       => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }


            Event::fire('orbit.product.postnewproduct.after.validation', array($this, $validator));

            $newProduct = new Product;
            $newProduct->name = $title;
            $newProduct->short_description = $shortDescription;
            $newProduct->status = $status;
            $newProduct->country_id = $countryId;

            Event::fire('orbit.product.postnewproduct.before.save', array($this, $newProduct));

            $newProduct->save();


            Event::fire('orbit.article.postnewproduct.after.save', array($this, $newProduct));

            $this->response->data = $newProduct;

            // Commit the changes
            $this->commit();

          Event::fire('orbit.article.postnewproduct.after.commit', array($this, $newProduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.article.postnewproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.article.postnewproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.article.postnewproduct.query.error', array($this, $e));

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
            Event::fire('orbit.article.postnewproduct.general.exception', array($this, $e));

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
