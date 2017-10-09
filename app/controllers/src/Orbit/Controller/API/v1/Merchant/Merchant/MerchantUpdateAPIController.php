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

use Orbit\Controller\API\v1\Merchant\Merchant\MerchantHelper;

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

            $merchantHelper = MerchantHelper::create();
            $merchantHelper->merchantCustomValidator();

            $baseMerchantId = OrbitInput::post('base_merchant_id');
            $merchantName = OrbitInput::post('merchant_name');
            $translations = OrbitInput::post('translations');
            $language = OrbitInput::get('language', 'en');
            $countryId = OrbitInput::post('country_id');
            $keywords = OrbitInput::post('keywords');
            $keywords = (array) $keywords;
            $languages = OrbitInput::post('languages', []);
            $mobile_default_language = OrbitInput::post('mobile_default_language');
            $phone = OrbitInput::post('phone');
            $email = OrbitInput::post('email');

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

            $updatedBaseMerchant = BaseMerchant::where('base_merchant_id', $baseMerchantId)->first();

            OrbitInput::post('website_url', function($website_url) use ($updatedBaseMerchant) {
                $updatedBaseMerchant->url = $website_url;
            });

            OrbitInput::post('country_id', function($countryId) use ($updatedBaseMerchant) {
                $updatedBaseMerchant->country_id = $countryId;
            });

            OrbitInput::post('facebook_url', function($facebook_url) use ($updatedBaseMerchant) {
                $updatedBaseMerchant->facebook_url = $facebook_url;
            });

            OrbitInput::post('phone', function($phone) use ($updatedBaseMerchant) {
                $updatedBaseMerchant->phone = $phone;
            });

            OrbitInput::post('email', function($email) use ($updatedBaseMerchant) {
                $updatedBaseMerchant->email = $email;
            });

            OrbitInput::post('payment_acquire', function($paymentAcquire) use ($updatedBaseMerchant) {
                $updatedBaseMerchant->is_payment_acquire = $paymentAcquire;
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
                        $updatedBaseMerchant->description = $val->description;
                    }
                }
            }

            OrbitInput::post('translations', function($translation_json_string) use ($updatedBaseMerchant, $merchantHelper) {
                $merchantHelper->validateAndSaveTranslations($updatedBaseMerchant, $translation_json_string, $scenario = 'update');
            });

            Event::fire('orbit.basemerchant.postupdatebasemerchant.before.save', array($this, $updatedBaseMerchant));

            OrbitInput::post('mobile_default_language', function($mobile_default_language) use ($updatedBaseMerchant, $languages) {
                // check mobile default language must in supported language
                if (in_array($mobile_default_language, $languages)) {
                    $updatedBaseMerchant->mobile_default_language = $mobile_default_language;
                } else {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.mobile_default_lang'));
                }
            });

            $updatedBaseMerchant->save();

            OrbitInput::post('languages', function($languages) use ($updatedBaseMerchant, $baseMerchantId) {
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

            OrbitInput::post('category_ids', function($categoryIds) use ($updatedBaseMerchant, $baseMerchantId) {
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

                $updatedBaseMerchant->categories = $baseMerchantCategorys;
            });

            OrbitInput::post('keywords', function($keywords) use ($updatedBaseMerchant, $user, $baseMerchantId) {
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

                $updatedBaseMerchant->keywords = $merchantKeywords;
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


            // Payment Acquire
            $deleteObjectFinancialDetail = ObjectFinancialDetail::where('object_id', '=', $baseMerchantId)->where('object_type', '=', 'merchant')->delete();
            $deleteObjectFinancialDetail = ObjectBank::where('object_id', '=', $baseMerchantId)->where('object_type', '=', 'merchant')->delete();
            $deleteMerchantStorePaymentProvider = MerchantStorePaymentProvider::where('object_id', '=', $baseMerchantId)->where('object_type', '=', 'merchant')->delete();


            if ($paymentAcquire === 'Y') {

                $objectType = 'base_merchant';

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

                    $updatedBaseMerchant->object_bank = $objectBank;
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
                    $updatedBaseMerchant->merchant_store_payment_provider = $merchantStorePaymentProvider;

                }
            }

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