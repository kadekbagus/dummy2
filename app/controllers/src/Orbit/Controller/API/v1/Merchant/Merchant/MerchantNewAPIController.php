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
use BaseMerchantKeyword;
use Config;
use Language;
use Keyword;
use Event;
use Category;
use Orbit\Controller\API\v1\Merchant\Merchant\MerchantHelper;

class MerchantNewAPIController extends ControllerAPI
{
    protected $merchantViewRoles = ['super admin', 'merchant database admin'];

    /**
     * Create new merchant on merchant database manager.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function postNewMerchant()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.basemerchant.postnewbasemerchant.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.basemerchant.postnewbasemerchant.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.basemerchant.postnewbasemerchant.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->merchantViewRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.basemerchant.postnewbasemerchant.after.authz', array($this, $user));

            $merchantHelper = MerchantHelper::create();
            $merchantHelper->merchantCustomValidator();

            $merchantName = OrbitInput::post('merchant_name');
            $websiteUrl = OrbitInput::post('website_url');
            $facebookUrl = OrbitInput::post('facebook_url');
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
                    'merchantName'  => $merchantName
                ),
                array(
                    'merchantName'  => 'required|orbit.exist.merchant_name'
                ),
                array(
                    'orbit.exist.merchant_name' => 'Merchant name already exist'
               )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // validate category_ids
            if (isset($categoryIds) && count($categoryIds) > 0) {
                foreach ($categoryIds as $category_id_check) {
                    $validator = Validator::make(
                        array(
                            'category_id'   => $category_id_check,
                        ),
                        array(
                            'category_id'   => 'orbit.empty.category',
                        )
                    );

                    Event::fire('orbit.basemerchant.postnewbasemerchant.before.categoryvalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.basemerchant.postnewbasemerchant.after.categoryvalidation', array($this, $validator));
                }
            }

            Event::fire('orbit.basemerchant.postnewbasemerchant.after.validation', array($this, $validator));

            // Get english language_id
            $idLanguageEnglish = Language::select('language_id')
                                ->where('name', '=', $language)
                                ->first();

            $newBaseMerchant = new BaseMerchant;
            $newBaseMerchant->name = $merchantName;
            $newBaseMerchant->facebook_url = $facebookUrl;
            $newBaseMerchant->url = $websiteUrl;
            $newBaseMerchant->status = 'active';

            if (! empty($translations) ) {
                $dataTranslations = @json_decode($translations);
                if (json_last_error() != JSON_ERROR_NONE) {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
                }

                if (! is_null($dataTranslations)) {
                    // Get english tenant description for saving to default language
                    foreach ($dataTranslations as $key => $val) {
                        // Validation language id from translation
                        $language = Language::where('language_id', '=', $key)->first();
                        if (empty($language)) {
                            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
                        }

                        if ($key === $idLanguageEnglish->language_id) {
                            $newBaseMerchant->description = $val->description;
                        }
                    }
                }
            }

            Event::fire('orbit.basemerchant.postnewbasemerchant.before.save', array($this, $newBaseMerchant));

            $newBaseMerchant->save();

            // save translations
            OrbitInput::post('translations', function($translation_json_string) use ($newBaseMerchant, $merchantHelper) {
                $merchantHelper->validateAndSaveTranslations($newBaseMerchant, $translation_json_string, $scenario = 'create');
            });

            // save base merchant categories
            $baseMerchantCategorys = array();
            foreach ($categoryIds as $category_id) {
                $BaseMerchantCategory = new BaseMerchantCategory();
                $BaseMerchantCategory->base_merchant_id = $newBaseMerchant->base_merchant_id;
                $BaseMerchantCategory->category_id = $category_id;
                $BaseMerchantCategory->save();
                $baseMerchantCategorys[] = $BaseMerchantCategory;
            }
            $newBaseMerchant->categories = $baseMerchantCategorys;

            // save Keyword
            $tenantKeywords = array();
            foreach ($keywords as $keyword) {
                $keyword_id = null;

                $existKeyword = Keyword::excludeDeleted()
                    ->where('keyword', '=', $keyword)
                    ->first();

                if (empty($existKeyword)) {
                    $newKeyword = new Keyword();
                    $newKeyword->merchant_id = '0';
                    $newKeyword->keyword = $keyword;
                    $newKeyword->status = 'active';
                    $newKeyword->created_by = $user->user_id;
                    $newKeyword->modified_by = $user->user_id;
                    $newKeyword->save();

                    $keyword_id = $newKeyword->keyword_id;
                    $tenantKeywords[] = $newKeyword;
                } else {
                    $keyword_id = $existKeyword->keyword_id;
                    $tenantKeywords[] = $existKeyword;
                }

                $newKeywordObject = new BaseMerchantKeyword();
                $newKeywordObject->base_merchant_id = $newBaseMerchant->base_merchant_id;
                $newKeywordObject->keyword_id = $keyword_id;
                $newKeywordObject->save();

            }
            $newBaseMerchant->keywords = $tenantKeywords;

            Event::fire('orbit.basemerchant.postnewbasemerchant.after.save', array($this, $newBaseMerchant));

            $this->response->data = $newBaseMerchant;

            // Commit the changes
            $this->commit();

          Event::fire('orbit.basemerchant.postnewbasemerchant.after.commit', array($this, $newBaseMerchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.basemerchant.postnewbasemerchant.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.basemerchant.postnewbasemerchant.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.basemerchant.postnewbasemerchant.query.error', array($this, $e));

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
            Event::fire('orbit.basemerchant.postnewbasemerchant.general.exception', array($this, $e));

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
