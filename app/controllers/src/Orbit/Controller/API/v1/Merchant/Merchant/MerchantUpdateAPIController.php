<?php namespace Orbit\Controller\API\v1\Merchant\Merchant;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Validator;
use Lang;
use BaseMerchant;
use BaseMerchantCategory;
use BaseMerchantTranslation;
use Config;
use Language;
use Keyword;
use KeywordObject;
use Event;

class MerchantUpdateAPIController extends ControllerAPI
{
    protected $merchantViewRoles = ['super admin', 'merchant database admin'];

    /**
     * Update merchant on merchant database manager.
     *
     * @author firmansyah <firmansyah@dominopos.com>
     */
    public function postUpdateMerchant()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.basemerchant.postupdatebasemerchant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.basemerchant.postupdatebasemerchant.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.basemerchant.postupdatebasemerchant.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->merchantViewRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.basemerchant.postupdatebasemerchant.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $baseMerchantId = OrbitInput::post('base_merchant_id');
            $websiteUrl = OrbitInput::post('website_url');
            // $facebookUrl = OrbitInput::post('facebook_url');
            $categoryIds = OrbitInput::post('category_ids');
            $categoryIds = (array) $categoryIds;
            $translations = OrbitInput::post('translations');
            $language = OrbitInput::get('language', 'en');
            $keywords = OrbitInput::post('keywords');
            $keywords = (array) $keywords;

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'baseMerchantId' => $baseMerchantId,
                    'websiteUrl'     => $websiteUrl,
                    'translations'   => $translations,
                ),
                array(
                    'baseMerchantId' => 'required|orbit.exist.base_merchant_id',
                    'websiteUrl'     => 'orbit.formaterror.url.web',
                    'translations'   => 'required',
                ),
                array(
                    'orbit.exist.base_merchant_id' => 'Base Merchant ID is invalid',
                    'orbit.formaterror.url.web' => 'Website URL is not valid',
               )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }


            // validate category_ids
            if (isset($category_ids) && count($category_ids) > 0) {
                foreach ($category_ids as $category_id_check) {
                    $validator = Validator::make(
                        array(
                            'category_id'   => $category_id_check,
                        ),
                        array(
                            'category_id'   => 'orbit.empty.category',
                        )
                    );

                    Event::fire('orbit.basemerchant.postupdatebasemerchant.before.categoryvalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.basemerchant.postupdatebasemerchant.after.categoryvalidation', array($this, $validator));
                }
            }

            Event::fire('orbit.basemerchant.postupdatebasemerchant.after.validation', array($this, $validator));

            $updatedBaseMerchant = BaseMerchant::where('base_merchant_id', $baseMerchantId)->first();

            OrbitInput::post('website_url', function($website_url) use ($updatedBaseMerchant) {
                $updatedBaseMerchant->url = $website_url;
            });

            OrbitInput::post('facebook_url', function($facebook_url) use ($updatedBaseMerchant) {
                $updatedBaseMerchant->facebook_url = $facebook_url;
            });

            Event::fire('orbit.basemerchant.postupdatebasemerchant.before.save', array($this, $updatedBaseMerchant));

            $updatedBaseMerchant->save();

            Event::fire('orbit.basemerchant.postupdatebasemerchant.after.save', array($this, $updatedBaseMerchant));

            $this->response->data = $updatedBaseMerchant;

            // Commit the changes
            $this->commit();

          Event::fire('orbit.basemerchant.postupdatebasemerchant.after.commit', array($this, $updatedBaseMerchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.basemerchant.postupdatebasemerchant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.basemerchant.postupdatebasemerchant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.basemerchant.postupdatebasemerchant.query.error', array($this, $e));

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
            Event::fire('orbit.basemerchant.postupdatebasemerchant.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check existing merchant name
        Validator::extend('orbit.exist.merchant_name', function ($attribute, $value, $parameters) {
            $merchant = BaseMerchant::where('name', '=', $value)
                            ->first();

            if (! empty($merchant)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the validity of URL
        Validator::extend('orbit.formaterror.url.web', function ($attribute, $value, $parameters) {
            $url = 'http://' . $value;

            $pattern = '@^((http:\/\/www\.)|(www\.)|(http:\/\/))[a-zA-Z0-9._-]+\.[a-zA-Z.]{2,5}$@';

            if (! preg_match($pattern, $url)) {
                return FALSE;
            }
            return TRUE;
        });

        // Check the validity of base merchant id
        Validator::extend('orbit.exist.base_merchant_id', function ($attribute, $value, $parameters) {
            $baseMerchant = BaseMerchant::where('base_merchant_id', $value)->first();

            if (empty($baseMerchant)) {
                return FALSE;
            }
            return TRUE;
        });
    }

    /**
     * @param object $baseMerchant
     * @param string $translations_json_string
     * @throws InvalidArgsException
     */
    private function validateAndSaveTranslations($updatedBaseMerchant, $translations_json_string)
    {
        $data = @json_decode($translations_json_string);
        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
        }
        foreach ($data as $language_id => $translations) {
            $updatedBaseMerchantTranslation = new BaseMerchantTranslation();
            $updatedBaseMerchantTranslation->base_merchant_id = $updatedBaseMerchant->base_merchant_id;
            $updatedBaseMerchantTranslation->language_id = $language_id;
            $updatedBaseMerchantTranslation->description = $translations->description;
            $updatedBaseMerchantTranslation->save();
            $baseMerchantTranslations[] = $updatedBaseMerchantTranslation;
        }
        $updatedBaseMerchant->translations = $baseMerchantTranslations;
    }
}
