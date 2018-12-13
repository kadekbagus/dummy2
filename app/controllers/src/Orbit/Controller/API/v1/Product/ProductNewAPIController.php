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

            Event::fire('orbit.newproduct.postnewproduct.before.auth', array($this));

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

            $productHelper = ProductHelper::create();
            $productHelper->productCustomValidator();

            $name = OrbitInput::post('name');
            $shortDescription = OrbitInput::post('short_description');
            $status = OrbitInput::post('status');
            $countryId = OrbitInput::post('country_id');
            $categories = OrbitInput::post('categories', []);
            $marketplaces = OrbitInput::post('marketplaces', []);

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
                    'status'           => 'in:active,inactive',
                    'country_id'       => 'required',
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


            // save translations
            OrbitInput::post('marketplaces', function($marketplace_json_string) use ($newProduct, $productHelper) {
                $this->validateAndSaveMarketplaces($newProduct, $marketplace_json_string, $scenario = 'create');
            });

            Event::fire('orbit.newproduct.postnewproduct.after.save', array($this, $newProduct));

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
        } catch (Exception $e) {
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

    /**
     * @param object $baseMerchant
     * @param string $translations_json_string
     * @throws InvalidArgsException
     */
    public function validateAndSaveMarketplaces($newProduct, $marketplace_json_string, $scenario = 'create')
    {
        $valid_fields = ['website_url'];
        $operations = [];

        $data = @json_decode($marketplace_json_string);
        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'marketplace']));
        }

        foreach ($data as $marketplace_id => $marketplace) {
            print_r($marketplace);

            $existing_marketplace = ProductLinkToObject::where('product_id', '=', $newProduct->product_id)
                                                        ->where('object_id', '=', $marketplace_id)
                                                        ->where('object_type', '=', 'marketplace')
                                                        ->first();

            if ($marketplace === null) {
                // deleting, verify exists
                if (empty($existing_marketplace)) {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
                }
                $operations[] = ['delete', $existing_marketplace];
            } else {
                foreach ($marketplace as $field => $value) {
                    if (!in_array($field, $valid_fields, TRUE)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.key'));
                    }
                    if ($value !== null && !is_string($value)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.value'));
                    }
                }
                if (empty($existing_marketplace)) {
                    $operations[] = ['create', $marketplace_id, $marketplace->website_url];
                } else {
                    $operations[] = ['update', $existing_marketplace, $marketplace->website_url];
                }
            }
        }

        foreach ($operations as $operation) {
            $op = $operation[0];
            if ($op === 'create') {
                $saveObjectMarketPlaces = new ProductLinkToObject();
                $saveObjectMarketPlaces->product_id = $newProduct->product_id;
                $saveObjectMarketPlaces->object_id = $operation[1];
                $saveObjectMarketPlaces->object_type = 'marketplace';
                $saveObjectMarketPlaces->product_url = $operation[2];
                $saveObjectMarketPlaces->save();
                $marketplaceData[] = $saveObjectMarketPlaces;
                $newProduct->marketplaces = $marketplaceData;
            }
            elseif ($op === 'update') {
                /** @var MerchantTranslation $existing_translation */
                // $existing_translation = $operation[1];
                // $data = $operation[2];
                // foreach ($data as $field => $value) {
                //     $existing_translation->{$field} = $value;
                // }
                // $existing_translation->save();

                // $baseMerchant->setRelation('translation_'. $existing_translation->language_id, $existing_translation);
            }
            elseif ($op === 'delete') {
                /** @var MerchantTranslation $existing_translation */
                // $existing_translation = $operation[1];
                // $existing_translation->delete();
            }
        }

        // to prevent error on saving base merchant
        unset($newProduct->marketplaces);
    }
}
