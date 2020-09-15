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

class ProviderProductNewAPIController extends ControllerAPI
{
    protected $productRoles = ['product manager'];

    /**
     * Create new game on product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function postNewProviderProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.newproviderproduct.postnewproviderproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.newproviderproduct.postnewproviderproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.newproviderproduct.postnewproviderproduct.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->productRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.newproviderproduct.postnewproviderproduct.after.authz', array($this, $user));

            $productProviderHelper = ProviderProductHelper::create();
            $productProviderHelper->providerProductCustomValidator();

            $provider_product_name = OrbitInput::post('provider_product_name');
            $product_type = OrbitInput::post('product_type');
            $provider_name = OrbitInput::post('provider_name');
            $code = OrbitInput::post('code');
            $status = OrbitInput::post('status', 'inactive');
            $description = OrbitInput::post('description');
            $faq = OrbitInput::post('faq');
            $price = OrbitInput::post('price');
            $commission_type = OrbitInput::post('commission_type');
            $commission_value = OrbitInput::post('commission_value');
            $extra_field_metadata = OrbitInput::post('extra_field_metadata');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'provider_product_name' => $provider_product_name,
                    'product_type'          => $product_type,
                    'provider_name'         => $provider_name,
                    'code'                  => $code,
                    'status'                => $status,
                    'price'                 => $price,
                    'commission_type'       => $commission_type,
                    'commission_value'      => $commission_value,
                ),
                array(
                    'provider_product_name' => 'required',
                    'product_type'          => 'required|in:game_voucher,electricity',
                    'provider_name'         => 'required',
                    'code'                  => 'required|orbit.exist.code',
                    'status'                => 'in:active,inactive',
                    'price'                 => 'required',
                    'commission_type'       => 'required',
                    'commission_value'      => 'required',
                ),
                array(
                    'provider_product_name.required'    => 'Product Name field is required',
                    'code.required'                     => 'Product Code field is required',
                    'product_type.required'             => 'Provider Type field is required',
                    'provider_name.required'            => 'Select Provider field is required',
                    'price.required'                    => 'Product Price field is required',
                    'commission_type.required'          => 'Commission Type field is required',
                    'commission_value.required'         => 'Commission Value field is required',
                    'code.orbit.exist.code'             => 'Code already used',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.newproviderproduct.postnewproviderproduct.after.validation', array($this, $validator));

            $newProviderProduct = new ProviderProduct;
            $newProviderProduct->provider_name = $provider_name;
            $newProviderProduct->product_type = $product_type;
            $newProviderProduct->provider_product_name = $provider_product_name;
            $newProviderProduct->code = $code;
            $newProviderProduct->price = $price;
            $newProviderProduct->description = $description;
            $newProviderProduct->faq = $faq;
            $newProviderProduct->commission_type = $commission_type;
            $newProviderProduct->commission_value = $commission_value;
            $newProviderProduct->status = $status;
            $newProviderProduct->extra_field_metadata = $extra_field_metadata;

            Event::fire('orbit.newproviderproduct.postnewproviderproduct.before.save', array($this, $newProviderProduct));

            $newProviderProduct->save();

            Event::fire('orbit.newproviderproduct.postnewproviderproduct.after.save', array($this, $newProviderProduct));

            $this->response->data = $newProviderProduct;

            // Commit the changes
            $this->commit();

          Event::fire('orbit.newproviderproduct.postnewproviderproduct.after.commit', array($this, $newProviderProduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.newproviderproduct.postnewproviderproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.newproviderproduct.postnewproviderproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.newproviderproduct.postnewproviderproduct.query.error', array($this, $e));

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
            Event::fire('orbit.newproviderproduct.postnewproviderproduct.general.exception', array($this, $e));

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
