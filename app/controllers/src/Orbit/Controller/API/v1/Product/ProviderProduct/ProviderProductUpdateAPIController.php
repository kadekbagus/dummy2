<?php namespace Orbit\Controller\API\v1\Product\ProviderProduct;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Validator;
use Orbit\Controller\API\v1\Product\ProviderProduct\ProviderProductHelper;

use Lang;
use Config;
use Event;
use ProviderProduct;

class ProviderProductUpdateAPIController extends ControllerAPI
{
    protected $productRoles = ['product manager'];

    /**
     * Update provider product on product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function postUpdateProviderProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.updateproviderproduct.postupdateproviderproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.updateproviderproduct.postupdateproviderproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.updateproviderproduct.postupdateproviderproduct.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->productRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.updateproviderproduct.postupdateproviderproduct.after.authz', array($this, $user));

            $productProviderHelper = ProviderProductHelper::create();
            $productProviderHelper->providerProductCustomValidator();

            $provider_product_id = OrbitInput::post('provider_product_id');
            $code = OrbitInput::post('code');
            $status = OrbitInput::post('status');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'provider_product_id'   => $provider_product_id,
                    'status'                => $status,
                    'code'                  => $code,
                ),
                array(
                    'provider_product_id'   => 'required',
                    'status'                => 'in:active,inactive',
                    'code'                  => 'orbit.exist.code_but_me:' . $provider_product_id,
                ),
                array(
                    'provider_product_id.required' => 'Provider product id is required',
                    'code.orbit.exist.code_but_me' => 'Code already used',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.updateproviderproduct.postupdateproviderproduct.after.validation', array($this, $validator));

            $updatedProviderProduct = ProviderProduct::where('provider_product_id', $provider_product_id)->first();

            OrbitInput::post('provider_product_name', function($provider_product_name) use ($updatedProviderProduct) {
                $updatedProviderProduct->provider_product_name = $provider_product_name;
            });

            OrbitInput::post('provider_name', function($provider_name) use ($updatedProviderProduct) {
                $updatedProviderProduct->provider_name = $provider_name;
            });

            OrbitInput::post('product_type', function($product_type) use ($updatedProviderProduct) {
                $updatedProviderProduct->product_type = $product_type;
            });

            OrbitInput::post('code', function($code) use ($updatedProviderProduct) {
                $updatedProviderProduct->code = $code;
            });

            OrbitInput::post('price', function($price) use ($updatedProviderProduct) {
                $updatedProviderProduct->price = $price;
            });

            OrbitInput::post('description', function($description) use ($updatedProviderProduct) {
                $updatedProviderProduct->description = $description;
            });

            OrbitInput::post('faq', function($faq) use ($updatedProviderProduct) {
                $updatedProviderProduct->faq = $faq;
            });

            OrbitInput::post('commission_type', function($commission_type) use ($updatedProviderProduct) {
                $updatedProviderProduct->commission_type = $commission_type;
            });

            OrbitInput::post('commission_value', function($commission_value) use ($updatedProviderProduct) {
                $updatedProviderProduct->commission_value = $commission_value;
            });

            OrbitInput::post('status', function($status) use ($updatedProviderProduct) {
                $updatedProviderProduct->status = $status;
            });

            Event::fire('orbit.updateproviderproduct.postupdateproviderproduct.before.save', array($this, $updatedProviderProduct));

            $updatedProviderProduct->touch();
            $updatedProviderProduct->save();

            Event::fire('orbit.updateproviderproduct.postupdateproviderproduct.after.save', array($this, $updatedProviderProduct));

            $this->response->data = $updatedProviderProduct;

            // Commit the changes
            $this->commit();

          Event::fire('orbit.updateproviderproduct.postupdateproviderproduct.after.commit', array($this, $updatedProviderProduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.updateproviderproduct.postupdateproviderproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.updateproviderproduct.postupdateproviderproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.updateproviderproduct.postupdateproviderproduct.query.error', array($this, $e));

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
            Event::fire('orbit.updateproviderproduct.postupdateproviderproduct.general.exception', array($this, $e));

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
