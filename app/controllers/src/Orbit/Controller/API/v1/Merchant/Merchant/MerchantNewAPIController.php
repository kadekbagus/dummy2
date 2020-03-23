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
use ProductTag;
use ProductTagObject;
use BaseMerchantProductTag;

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
            $partnerIds = OrbitInput::post('partner_ids', []);
            $categoryIds = OrbitInput::post('category_ids');
            $categoryIds = (array) $categoryIds;
            $translations = OrbitInput::post('translations');
            $language = OrbitInput::get('language', 'en');
            $keywords = OrbitInput::post('keywords');
            $countryId = OrbitInput::post('country_id');
            $keywords = (array) $keywords;
            $languages = OrbitInput::post('languages', []);
            $mobile_default_language = OrbitInput::post('mobile_default_language');
            $phone = OrbitInput::post('phone');
            $email = OrbitInput::post('email');
            $gender = OrbitInput::post('gender', 'A');
            $productTags = OrbitInput::post('product_tags');
            $productTags = (array) $productTags;
            $disable_ads = OrbitInput::post('disable_ads','n');
            $disable_ymal = OrbitInput::post('disable_ymal','n');
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

            $instagramUrl = OrbitInput::post('instagram_url');
            $twitterUrl = OrbitInput::post('twitter_url');
            $youtubeUrl = OrbitInput::post('youtube_url');
            $lineUrl = OrbitInput::post('line_url');
            $otherPhotoSectionTitle = OrbitInput::post('other_photo_section_title');
            $videoId1 = OrbitInput::post('video_id_1');
            $videoId2 = OrbitInput::post('video_id_2');
            $videoId3 = OrbitInput::post('video_id_3');
            $videoId4 = OrbitInput::post('video_id_4');
            $videoId5 = OrbitInput::post('video_id_5');
            $videoId6 = OrbitInput::post('video_id_6');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'merchantName'            => $merchantName,
                    'country'                 => $countryId,
                    'languages'               => $languages,
                    'mobile_default_language' => $mobile_default_language
                ),
                array(
                    'merchantName'            => 'required|orbit.exist.merchant_name:' . $countryId,
                    'country'                 => 'required',
                    'languages'               => 'required|array',
                    'disable_ads'             => 'in:n,y',
                    'disable_ymal'            => 'in:n,y',
                    'mobile_default_language' => 'required|size:2|orbit.supported.language'
                ),
                array(
                    'orbit.exist.merchant_name' => 'Merchant is already exist',
                    'orbit.supported.language'  => 'Default language is not supported'
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
            $newBaseMerchant->country_id = $countryId;
            $newBaseMerchant->facebook_url = $facebookUrl;
            $newBaseMerchant->url = $websiteUrl;
            $newBaseMerchant->phone = $phone;
            $newBaseMerchant->email = $email;
            $newBaseMerchant->is_payment_acquire = $paymentAcquire;
            $newBaseMerchant->gender = $gender;
            $newBaseMerchant->status = 'active';
            $newBaseMerchant->instagram_url = $instagramUrl;
            $newBaseMerchant->twitter_url = $twitterUrl;
            $newBaseMerchant->youtube_url = $youtubeUrl;
            $newBaseMerchant->line_url = $lineUrl;
            $newBaseMerchant->other_photo_section_title = $otherPhotoSectionTitle;
            $newBaseMerchant->video_id_1 = $videoId1;
            $newBaseMerchant->video_id_2 = $videoId2;
            $newBaseMerchant->video_id_3 = $videoId3;
            $newBaseMerchant->video_id_4 = $videoId4;
            $newBaseMerchant->video_id_5 = $videoId5;
            $newBaseMerchant->video_id_6 = $videoId6;
            $newBaseMerchant->disable_ads = $disable_ads;
            $newBaseMerchant->disable_ymal = $disable_ymal;

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
                            $newBaseMerchant->custom_title = $val->custom_title;
                        }
                    }
                }
            }

            Event::fire('orbit.basemerchant.postnewbasemerchant.before.save', array($this, $newBaseMerchant));

            // check mobile default language must in supported language
            if (in_array($mobile_default_language, $languages)) {
                $newBaseMerchant->mobile_default_language = $mobile_default_language;
            } else {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.mobile_default_lang'));
            }

            $newBaseMerchant->save();

            // Validate the payment acquire, only chech if payment acquire = Y
            if ($paymentAcquire === 'Y') {

                $objectId = $newBaseMerchant->base_merchant_id;
                $objectType = 'base_merchant';

                // Save object financial detail
                $validator = Validator::make(
                    array(
                        'object_id' => $objectId,
                        'object_type' => $objectType,
                        'contact_name' => $contactName,
                        'position' => $position,
                        'phone_number' => $phoneNumber,
                        'email_financial' => $emailFinancial,
                    ),
                    array(
                        'object_id' => 'required',
                        'object_type' => 'required',
                        'contact_name' => 'required',
                        'position' => 'required',
                        'phone_number' => 'required',
                        'email_financial' => 'required',
                    )
                );

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $newObjectFinancialDetail = new ObjectFinancialDetail;
                $newObjectFinancialDetail->object_id = $objectId;
                $newObjectFinancialDetail->object_type = $objectType;
                $newObjectFinancialDetail->contact_name = $contactName;
                $newObjectFinancialDetail->position = $position;
                $newObjectFinancialDetail->phone_number = $phoneNumber;
                $newObjectFinancialDetail->email = $emailFinancial;
                $newObjectFinancialDetail->Save();
                $objectFinancialDetail[] = $newObjectFinancialDetail;

                // Save object bank
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
                    $newObjectBank->object_id = $objectId;
                    $newObjectBank->object_type = $objectType;
                    $newObjectBank->bank_id = $bankId;
                    $newObjectBank->account_name = $accountNames[$objectBankKey];
                    $newObjectBank->account_number = $accountNumbers[$objectBankKey];
                    $newObjectBank->bank_address = $bankAddress[$objectBankKey];
                    $newObjectBank->swift_code = isset($swiftCodes[$objectBankKey]) ? $swiftCodes[$objectBankKey] : null;
                    $newObjectBank->save();
                    $objectBank[$objectBankKey] = $newObjectBank;
                }

                // Save merchant store payment provider
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
                    $newMerchantStorePaymentProvider->object_id = $objectId;
                    $newMerchantStorePaymentProvider->object_type = $objectType;
                    $newMerchantStorePaymentProvider->phone_number_for_sms = '';
                    $newMerchantStorePaymentProvider->mdr = $mdr[$paymentProviderKey];
                    $newMerchantStorePaymentProvider->save();
                    $merchantStorePaymentProvider[$paymentProviderKey] = $newMerchantStorePaymentProvider;
                }

                // Add responses for payment acquire
                if (! empty($objectFinancialDetail)) {
                    $newBaseMerchant->object_financial_detail = $objectFinancialDetail;
                }

                if (! empty($objectBank)) {
                    $newBaseMerchant->object_bank = $objectBank;
                }

                if (! empty($merchantStorePaymentProvider)) {
                    $newBaseMerchant->merchant_store_payment_provider = $merchantStorePaymentProvider;
                }
            }

            // languages
            if (count($languages) > 0) {
                foreach ($languages as $language_name) {
                    $validator = Validator::make(
                        array(
                            'language'  => $language_name
                        ),
                        array(
                            'language'  => 'required|size:2|orbit.supported.language'
                        ),
                        array(
                            'orbit.supported.language'  => 'Default language is not supported'
                        )
                    );

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    $baseMerchantLanguage = new ObjectSupportedLanguage();
                    $baseMerchantLanguage->object_id = $newBaseMerchant->base_merchant_id;
                    $baseMerchantLanguage->object_type = 'base_merchant';
                    $baseMerchantLanguage->status = 'active';
                    $baseMerchantLanguage->language_id = Language::where('name', '=', $language_name)->first()->language_id;
                    $baseMerchantLanguage->save();
                }
            }

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


            // save product tag
            $tenantProductTags = array();
            foreach ($productTags as $productTag) {
                $product_tag_id = null;

                $existProductTag = ProductTag::excludeDeleted()
                    ->where('product_tag', '=', $productTag)
                    ->first();

                if (empty($existProductTag)) {
                    $newProductTag = new ProductTag();
                    $newProductTag->merchant_id = '0';
                    $newProductTag->product_tag = $productTag;
                    $newProductTag->status = 'active';
                    $newProductTag->created_by = $user->user_id;
                    $newProductTag->modified_by = $user->user_id;
                    $newProductTag->save();

                    $product_tag_id = $newProductTag->product_tag_id;
                    $tenantProductTags[] = $newProductTag;
                } else {
                    $product_tag_id = $existProductTag->product_tag_id;
                    $tenantProductTags[] = $existProductTag;
                }

                $newKeywordObject = new BaseMerchantProductTag();
                $newKeywordObject->base_merchant_id = $newBaseMerchant->base_merchant_id;
                $newKeywordObject->product_tag_id = $product_tag_id;
                $newKeywordObject->save();

            }
            $newBaseMerchant->product_tags = $tenantProductTags;

            //save to base object partner
            if (! empty($partnerIds)) {
              foreach ($partnerIds as $partnerId) {
                if ($partnerId != "") {
                  $baseObjectPartner = new BaseObjectPartner();
                  $baseObjectPartner->object_id = $newBaseMerchant->base_merchant_id;
                  $baseObjectPartner->object_type = 'tenant';
                  $baseObjectPartner->partner_id = $partnerId;
                  $baseObjectPartner->save();
                }
              }
            }

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
