<?php namespace Orbit\Controller\API\v1\Article;

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
use ObjectSupportedLanguage;
use BaseObjectPartner;
use Config;
use Language;
use Keyword;
use Event;
use Category;
use ObjectBank;
use ObjectFinancialDetail;
use MerchantStorePaymentProvider;
use ProductTag;
use ProductTagObject;
use BaseMerchantProductTag;

use Orbit\Controller\API\v1\Merchant\Merchant\MerchantHelper;

class ArticleUpdateAPIController extends ControllerAPI
{
    protected $articleRoles = ['article writer', 'article publisher'];


    /**
     * Update merchant on merchant database manager.
     *
     * @author firmansyah <firmansyah@dominopos.com>
     */
    public function postUpdateArticle()
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
            $validRoles = $this->articleRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.basemerchant.postupdatebasemerchant.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchantHelper = MerchantHelper::create();
            $merchantHelper->merchantCustomValidator();

            $baseMerchantId = OrbitInput::post('base_merchant_id');
            $merchantName = OrbitInput::post('merchant_name');
            $translations = OrbitInput::post('translations');
            $language = OrbitInput::get('language', 'en');
            $countryId = OrbitInput::post('country_id');
            $keywords = OrbitInput::post('keywords', []);
            $keywords = (array) $keywords;
            $languages = OrbitInput::post('languages', []);
            $mobile_default_language = OrbitInput::post('mobile_default_language');
            $phone = OrbitInput::post('phone');
            $email = OrbitInput::post('email');
            $productTags = OrbitInput::post('product_tags', []);
            $gender = OrbitInput::post('gender', 'A');

            // Payment_acquire
            $paymentAcquire = OrbitInput::post('payment_acquire', 'N'); // Y or N
            $contactName = OrbitInput::post('contact_name');
            $position = OrbitInput::post('position');
            $phoneNumber = OrbitInput::post('phone_number');
            $emailFinancial = OrbitInput::post('email_financial');
            $paymentProviderIds = OrbitInput::post('payment_provider_ids',[]);
            $mdr = OrbitInput::post('mdr',[]);
            $bankIds = OrbitInput::post('bank_ids',[]);
            $accountNames = OrbitInput::post('account_names',[]);
            $accountNumbers = OrbitInput::post('account_numbers',[]);
            $bankAddress = OrbitInput::post('bank_address',[]);
            $swiftCodes = OrbitInput::post('swift_codes',[]);

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'baseMerchantId'          => $baseMerchantId,
                    'translations'            => $translations,
                    'merchantName'            => $merchantName,
                    'country'                 => $countryId,
                    'languages'               => $languages,
                    'mobile_default_language' => $mobile_default_language
                ),
                array(
                    'baseMerchantId'          => 'required|orbit.exist.base_merchant_id',
                    'translations'            => 'required',
                    'merchantName'            => 'required|orbit.exist.merchant_name_not_me:' . $baseMerchantId . ',' . $countryId,
                    'country'                 => 'required|orbit.store.country:' . $baseMerchantId . ',' . $countryId,
                    'languages'               => 'required|array',
                    'mobile_default_language' => 'required|size:2|orbit.supported.language|orbit.store.language:' . $baseMerchantId . ',' . $mobile_default_language
                ),
                array(
                    'orbit.exist.base_merchant_id'     => 'Base Merchant ID is invalid',
                    'orbit.exist.merchant_name_not_me' => 'Merchant is already exist',
                    'orbit.store.country'              => 'You have stores linked to the previous country',
                    'orbit.supported.language'         => 'Default language is not supported',
                    'orbit.store.language'             => 'You have stores linked to the previous default language'
               )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.basemerchant.postupdatebasemerchant.after.validation', array($this, $validator));

            $updatedArticle = BaseMerchant::where('base_merchant_id', $baseMerchantId)->first();

            OrbitInput::post('website_url', function($website_url) use ($updatedArticle) {
                $updatedArticle->url = $website_url;
            });

            OrbitInput::post('country_id', function($countryId) use ($updatedArticle) {
                $updatedArticle->country_id = $countryId;
            });

            OrbitInput::post('facebook_url', function($facebook_url) use ($updatedArticle) {
                $updatedArticle->facebook_url = $facebook_url;
            });

            OrbitInput::post('phone', function($phone) use ($updatedArticle) {
                $updatedArticle->phone = $phone;
            });

            OrbitInput::post('email', function($email) use ($updatedArticle) {
                $updatedArticle->email = $email;
            });

            OrbitInput::post('payment_acquire', function($paymentAcquire) use ($updatedArticle) {
                $updatedArticle->is_payment_acquire = $paymentAcquire;
            });

            OrbitInput::post('gender', function($gender) use ($updatedArticle) {
                $updatedArticle->gender = $gender;
            });

            // Translations
            $idLanguageEnglish = Language::select('language_id')->where('name', '=', 'en')->first();

            // Check for english content
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
                        $updatedArticle->description = $val->description;
                        $updatedArticle->custom_title = isset($val->custom_title) ? $val->custom_title : null;
                    }
                }
            }

            OrbitInput::post('translations', function($translation_json_string) use ($updatedArticle, $merchantHelper) {
                $merchantHelper->validateAndSaveTranslations($updatedArticle, $translation_json_string, $scenario = 'update');
            });

            Event::fire('orbit.basemerchant.postupdatebasemerchant.before.save', array($this, $updatedArticle));

            OrbitInput::post('mobile_default_language', function($mobile_default_language) use ($updatedArticle, $languages) {
                // check mobile default language must in supported language
                if (in_array($mobile_default_language, $languages)) {
                    $updatedArticle->mobile_default_language = $mobile_default_language;
                } else {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.mobile_default_lang'));
                }
            });

            $updatedArticle->save();

            OrbitInput::post('languages', function($languages) use ($updatedArticle, $baseMerchantId) {
                if (count($languages) > 0) {
                    // Delete old data
                    $deletedBaseMechantLanguage = ObjectSupportedLanguage::where('object_type', '=', 'base_merchant')
                                                    ->where('object_id', '=', $baseMerchantId)
                                                    ->delete();

                    foreach ($languages as $language_name) {
                        $validator = Validator::make(
                            array(
                                'language'  => $language_name
                            ),
                            array(
                                'language'  => 'required|size:2|orbit.supported.language'
                            ),
                            array(
                                'orbit.supported.language'  => 'Language is not supported'
                            )
                        );

                        // Run the validation
                        if ($validator->fails()) {
                            $errorMessage = $validator->messages()->first();
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        $baseMerchantLanguage = new ObjectSupportedLanguage();
                        $baseMerchantLanguage->object_id = $baseMerchantId;
                        $baseMerchantLanguage->object_type = 'base_merchant';
                        $baseMerchantLanguage->status = 'active';
                        $baseMerchantLanguage->language_id = Language::where('name', '=', $language_name)->first()->language_id;
                        $baseMerchantLanguage->save();
                    }
                }
            });

            OrbitInput::post('category_ids', function($categoryIds) use ($updatedArticle, $baseMerchantId) {
                // Delete old data
                $deleted_base_category = BaseMerchantCategory::where('base_merchant_id', '=', $baseMerchantId)->delete();

                // save base merchant categories
                $baseMerchantCategorys = array();
                foreach ($categoryIds as $category_id) {
                    $BaseMerchantCategory = new BaseMerchantCategory();
                    $BaseMerchantCategory->base_merchant_id = $baseMerchantId;
                    $BaseMerchantCategory->category_id = $category_id;
                    $BaseMerchantCategory->save();
                    $baseMerchantCategorys[] = $BaseMerchantCategory;
                }

                $updatedArticle->categories = $baseMerchantCategorys;
            });

            OrbitInput::post('keywords', function($keywords) use ($updatedArticle, $user, $baseMerchantId) {
                // Delete old data
                $deleted_keyword_object = BaseMerchantKeyword::where('base_merchant_id', '=', $baseMerchantId)->delete();

                // save Keyword
                $merchantKeywords = array();
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
                        $merchantKeywords[] = $newKeyword;
                    } else {
                        $keyword_id = $existKeyword->keyword_id;
                        $merchantKeywords[] = $existKeyword;
                    }

                    $newKeywordObject = new BaseMerchantKeyword();
                    $newKeywordObject->base_merchant_id = $baseMerchantId;
                    $newKeywordObject->keyword_id = $keyword_id;
                    $newKeywordObject->save();
                }

                $updatedArticle->keywords = $merchantKeywords;
            });


            OrbitInput::post('product_tags', function($productTags) use ($updatedArticle, $user, $baseMerchantId) {
                // Delete old data
                $deleted_product_tag_object = BaseMerchantProductTag::where('base_merchant_id', '=', $baseMerchantId)->delete();

                // save Keyword
                $merchantProductTags = array();
                foreach ($productTags as $product_tag) {
                    $product_tag_id = null;

                    $existProductTag = ProductTag::excludeDeleted()
                                        ->where('product_tag', '=', $product_tag)
                                        ->first();

                    if (empty($existProductTag)) {
                        $newProductTag = new ProductTag();
                        $newProductTag->merchant_id = '0';
                        $newProductTag->product_tag = $product_tag;
                        $newProductTag->status = 'active';
                        $newProductTag->created_by = $user->user_id;
                        $newProductTag->modified_by = $user->user_id;
                        $newProductTag->save();

                        $product_tag_id = $newProductTag->product_tag_id;
                        $merchantProductTags[] = $newProductTag;
                    } else {
                        $product_tag_id = $existProductTag->product_tag_id;
                        $merchantProductTags[] = $existProductTag;
                    }

                    $newProductTagObject = new BaseMerchantProductTag();
                    $newProductTagObject->base_merchant_id = $baseMerchantId;
                    $newProductTagObject->product_tag_id = $product_tag_id;
                    $newProductTagObject->save();
                }

                $updatedArticle->productTags = $merchantProductTags;
            });

            // update link to partner - base opject partner table
            OrbitInput::post('partner_ids', function($partnerIds) use ($baseMerchantId) {
                // Delete old data
                $delete_partner = BaseObjectPartner::where('object_id', '=', $baseMerchantId)->where('object_type', 'tenant');
                $delete_partner->delete(true);

                if (! empty($partnerIds)) {
                  // Insert new data
                  foreach ($partnerIds as $partnerId) {
                    if ($partnerId != "") {
                      $object_partner = new BaseObjectPartner();
                      $object_partner->object_id = $baseMerchantId;
                      $object_partner->object_type = 'tenant';
                      $object_partner->partner_id = $partnerId;
                      $object_partner->save();
                    }
                  }
                }
            });

            // delete product_tag when empty array send
            if (empty($productTags)) {
                $deleted_product_tag_object = BaseMerchantProductTag::where('base_merchant_id', '=', $baseMerchantId)->delete();
                $updatedArticle->productTags = [];
            }

            // delete keyword when empty array send
            if (empty($keywords)) {
                $deleted_keyword_object = BaseMerchantKeyword::where('base_merchant_id', '=', $baseMerchantId)->delete();
                $updatedArticle->keywords = [];
            }

            // Payment Acquire
            $objectType = 'base_merchant';

            $deleteObjectFinancialDetail = ObjectFinancialDetail::where('object_id', '=', $baseMerchantId)->where('object_type', '=', $objectType)->delete(true);
            $deleteObjectBank = ObjectBank::where('object_id', '=', $baseMerchantId)->where('object_type', '=', $objectType)->delete(true);
            $deleteMerchantStorePaymentProvider = MerchantStorePaymentProvider::where('object_id', '=', $baseMerchantId)->where('object_type', '=', $objectType)->delete(true);

            if ($paymentAcquire === 'Y') {

                // Save object financial detail
                $validator = Validator::make(
                    array(
                        'object_id'  => $baseMerchantId,
                        'object_type'  => $objectType,
                        'contact_name'  => $contactName,
                        'position'  => $position,
                        'phone_number'  => $phoneNumber,
                        'email_financial'  => $emailFinancial,
                    ),
                    array(
                        'object_id'  => 'required',
                        'object_type'  => 'required',
                        'contact_name'  => 'required',
                        'position'  => 'required',
                        'phone_number'  => 'required',
                        'email_financial'  => 'required',
                    )
                );

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $newObjectFinancialDetail = new ObjectFinancialDetail;
                $newObjectFinancialDetail->object_id = $baseMerchantId;
                $newObjectFinancialDetail->object_type = $objectType;
                $newObjectFinancialDetail->contact_name = $contactName;
                $newObjectFinancialDetail->position = $position;
                $newObjectFinancialDetail->phone_number = $phoneNumber;
                $newObjectFinancialDetail->email = $emailFinancial;
                $newObjectFinancialDetail->Save();
                $objectFinancialDetail[] = $newObjectFinancialDetail;

                if(count($bankIds) > 0){
                    foreach ($bankIds as $objectBankKey => $bankId) {
                        $validator = Validator::make(
                            array(
                                'bank_id'  => $bankId,
                                'account_name'  => $accountNames[$objectBankKey],
                                'account_number'  => $accountNumbers[$objectBankKey],
                                'bank_address'  => $bankAddress[$objectBankKey],
                            ),
                            array(
                                'bank_id'  => 'required',
                                'account_name'  => 'required',
                                'account_number'  => 'required',
                                'bank_address'  => 'required',
                            )
                        );
                        // Run the validation
                        if ($validator->fails()) {
                            $errorMessage = $validator->messages()->first();
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }
                        $newObjectBank = new ObjectBank;
                        $newObjectBank->object_id = $baseMerchantId;
                        $newObjectBank->object_type = $objectType;
                        $newObjectBank->bank_id = $bankId;
                        $newObjectBank->account_name = $accountNames[$objectBankKey];
                        $newObjectBank->account_number = $accountNumbers[$objectBankKey];
                        $newObjectBank->bank_address = $bankAddress[$objectBankKey];
                        $newObjectBank->swift_code = isset($swiftCodes[$objectBankKey]) ? $swiftCodes[$objectBankKey] : null;
                        $newObjectBank->save();
                        $objectBank[$objectBankKey] = $newObjectBank;
                    }

                    $updatedArticle->object_bank = $objectBank;
                }

                if (count($paymentProviderIds) > 0) {
                    foreach ($paymentProviderIds as $paymentProviderKey => $paymentProviderId) {
                        $validator = Validator::make(
                            array(
                                'payment_provider_id'  => $paymentProviderId,
                                'mdr' => $mdr,
                            ),
                            array(
                                'payment_provider_id'  => 'required',
                                'mdr' => 'required',
                            )
                        );
                        // Run the validation
                        if ($validator->fails()) {
                            $errorMessage = $validator->messages()->first();
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }
                        $newMerchantStorePaymentProvider = new MerchantStorePaymentProvider;
                        $newMerchantStorePaymentProvider->payment_provider_id = $paymentProviderId;
                        $newMerchantStorePaymentProvider->object_id = $baseMerchantId;
                        $newMerchantStorePaymentProvider->object_type = $objectType;
                        $newMerchantStorePaymentProvider->phone_number_for_sms = '';
                        $newMerchantStorePaymentProvider->mdr = $mdr[$paymentProviderKey];
                        $newMerchantStorePaymentProvider->save();
                        $merchantStorePaymentProvider[$paymentProviderKey] = $newMerchantStorePaymentProvider;
                    }
                    $updatedArticle->merchant_store_payment_provider = $merchantStorePaymentProvider;

                }
            }

            Event::fire('orbit.basemerchant.postupdatebasemerchant.after.save', array($this, $updatedArticle));

            $this->response->data = $updatedArticle;

            // Commit the changes
            $this->commit();

          Event::fire('orbit.basemerchant.postupdatebasemerchant.after.commit', array($this, $updatedArticle));
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
        Validator::extend('orbit.exist.merchant_name_not_me', function ($attribute, $value, $parameters) {
            $baseMerchantId = $parameters[0];
            $country = $parameters[1];

            $merchant = BaseMerchant::where('name', '=', $value)
                            ->where('country_id', $country)
                            ->whereNotIn('base_merchant_id', array($baseMerchantId))
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

}