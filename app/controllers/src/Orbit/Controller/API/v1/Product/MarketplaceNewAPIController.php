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
use Marketplace;

class MarketplaceNewAPIController extends ControllerAPI
{
    protected $productRoles = ['product manager'];

    /**
     * Create new product on product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function postNewMarketPlace()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.marketplace.postnewmarketplace.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.marketplace.postnewmarketplace.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.marketplace.postnewmarketplace.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->productRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.marketplace.postnewmarketplace.after.authz', array($this, $user));

            $productHelper = ProductHelper::create();
            $productHelper->productCustomValidator();

            $name = OrbitInput::post('name');
            $shortDescription = OrbitInput::post('short_description');
            $status = OrbitInput::post('status', 'inactive');
            $countryId = OrbitInput::post('country_id');
            $websiteUrl = OrbitInput::post('website_url');
            $images = \Input::file('images');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'name'             => $name,
                    'short_description'=> $shortDescription,
                    'status'           => $status,
                    'country_id'       => $countryId,
                    'website_url'      => $websiteUrl,
                    'images'           => $images,
                ),
                array(
                    'name'             => 'required',
                    'status'           => 'in:active,inactive',
                    'country_id'       => 'required',
                    'website_url'      => 'url',
                    'images'           => 'required|array',
                ),
                array(
                    'name.required'             => 'Affiliates Name field is required',
                    'country_id.required'       => 'Affiliates Country field is required',
                    'images.required'           => 'Affiliates Logo field is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.marketplace.postnewmarketplace.after.validation', array($this, $validator));

            $newMarketPlace = new Marketplace;
            $newMarketPlace->name = $name;
            $newMarketPlace->short_description = $shortDescription;
            $newMarketPlace->status = $status;
            $newMarketPlace->country_id = $countryId;
            $newMarketPlace->website_url = $websiteUrl;

            Event::fire('orbit.marketplace.postnewmarketplace.before.save', array($this, $newMarketPlace));

            $newMarketPlace->save();

            Event::fire('orbit.marketplace.postnewmarketplace.after.save', array($this, $newMarketPlace));

            $this->response->data = $newMarketPlace;

            // Commit the changes
            $this->commit();

          Event::fire('orbit.marketplace.postnewmarketplace.after.commit', array($this, $newMarketPlace));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.marketplace.postnewmarketplace.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.marketplace.postnewmarketplace.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.marketplace.postnewmarketplace.query.error', array($this, $e));

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
            Event::fire('orbit.marketplace.postnewmarketplace.general.exception', array($this, $e));

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
