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

class MarketplaceUpdateAPIController extends ControllerAPI
{
    protected $productRoles = ['product manager'];

    /**
     * Update product on product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function postUpdateMarketPlace()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.marketplace.postupdatemarketplace.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.marketplace.postupdatemarketplace.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.marketplace.postupdatemarketplace.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->productRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.marketplace.postupdatemarketplace.after.authz', array($this, $user));

            $productHelper = ProductHelper::create();
            $productHelper->productCustomValidator();

            $marketplaceId = OrbitInput::post('marketplace_id');
            $name = OrbitInput::post('name');
            $countryId = OrbitInput::post('country_id');
            $websiteUrl = OrbitInput::post('website_url');
            $status = OrbitInput::post('status', 'inactive');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'marketplace_id'   => $marketplaceId,
                    'name'             => $name,
                    'status'           => $status,
                    'country_id'       => $countryId,
                    'website_url'      => $websiteUrl,
                ),
                array(
                    'marketplace_id'   => 'required',
                    'name'             => 'required',
                    'status'           => 'in:active,inactive',
                    'country_id'       => 'required',
                    'website_url'      => 'url',
                ),
                array(
                    'name.required'             => 'Affiliates Name field is required',
                    'country_id.required'       => 'Affiliates Country field is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.marketplace.postupdatemarketplace.after.validation', array($this, $validator));

            $updatedMarketplace = Marketplace::where('marketplace_id', $marketplaceId)->first();

            OrbitInput::post('name', function($name) use ($updatedMarketplace) {
                $updatedMarketplace->name = $name;
            });

            OrbitInput::post('short_description', function($short_description) use ($updatedMarketplace) {
                $updatedMarketplace->short_description = $short_description;
            });

            OrbitInput::post('status', function($status) use ($updatedMarketplace) {
                $updatedMarketplace->status = $status;
            });

            OrbitInput::post('country_id', function($country_id) use ($updatedMarketplace) {
                $updatedMarketplace->country_id = $country_id;
            });

            OrbitInput::post('website_url', function($website_url) use ($updatedMarketplace) {
                $updatedMarketplace->website_url = $website_url;
            });

            Event::fire('orbit.marketplace.postupdatemarketplace.before.save', array($this, $updatedMarketplace));

            $updatedMarketplace->touch();
            $updatedMarketplace->save();

            Event::fire('orbit.marketplace.postupdatemarketplace.after.save', array($this, $updatedMarketplace));

            $this->response->data = $updatedMarketplace;

            // Commit the changes
            $this->commit();

          Event::fire('orbit.marketplace.postupdatemarketplace.after.commit', array($this, $updatedMarketplace));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.marketplace.postupdatemarketplace.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.marketplace.postupdatemarketplace.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.marketplace.postupdatemarketplace.query.error', array($this, $e));

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
            Event::fire('orbit.marketplace.postupdatemarketplace.general.exception', array($this, $e));

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
