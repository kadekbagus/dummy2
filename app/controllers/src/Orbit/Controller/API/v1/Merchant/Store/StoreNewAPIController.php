<?php namespace Orbit\Controller\API\v1\Merchant\Store;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use DB;
use Validator;
use Lang;
use \Exception;
use \Event;
use Orbit\Controller\API\v1\Merchant\Store\StoreHelper;
use BaseStore;
use ObjectBank;
use ObjectContact;
use ObjectFinancialDetail;
use MerchantStorePaymentProvider;
use ProductTag;
use BaseStoreProductTag;

class StoreNewAPIController extends ControllerAPI
{
    protected $newStoreRoles = ['merchant database admin'];
    /**
     * POST - post new store
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string base_merchant_id - The id of base merchant
     * @param string mall_id - The id of mall
     * @param string floor_id - The id of floor on the mall
     * @param string unit - The unit on the mall
     * @param string phone - The store phone
     * @param string status - The store status ('active' or 'inactive')
     * @param string verification_number - The verification number
     * @param file pictures - The store images (array)
     * @param file maps - The store map
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewStore()
    {
        $newstore = NULL;
        $user = NULL;
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->newStoreRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $base_merchant_id = OrbitInput::post('base_merchant_id');
            $mall_id = OrbitInput::post('mall_id');
            $floor_id = OrbitInput::post('floor_id');
            $unit = OrbitInput::post('unit');
            $phone = OrbitInput::post('phone');
            $status = OrbitInput::post('status', 'active');
            $verification_number = OrbitInput::post('verification_number');
            //images and map
            $images = OrbitInput::files('pictures');
            $map = OrbitInput::files('maps');
            $grab_images = OrbitInput::files('grab_pictures');

            // Payment_acquire
            $paymentAcquire = OrbitInput::post('payment_acquire', 'N'); // Y or N
            $contactName = OrbitInput::post('contact_name');
            $position = OrbitInput::post('position');
            $phoneNumber = OrbitInput::post('phone_number');
            $emailFinancial = OrbitInput::post('email_financial');
            $storeContactContactName = OrbitInput::post('store_contact_contact_name');
            $storeContactPosition = OrbitInput::post('store_contact_position');
            $storeContactPhoneNumber = OrbitInput::post('store_contact_phone_number');
            $storeContactEmail = OrbitInput::post('store_contact_email');
            $paymentProviderIds = OrbitInput::post('payment_provider_ids',[]);
            $phoneNumberForSms = OrbitInput::post('phone_number_for_sms',[]);
            $mdr = OrbitInput::post('mdr',[]);
            $bankIds = OrbitInput::post('bank_ids',[]);
            $accountNames = OrbitInput::post('account_names',[]);
            $accountNumbers = OrbitInput::post('account_numbers',[]);
            $bankAddress = OrbitInput::post('bank_address',[]);
            $swiftCodes = OrbitInput::post('swift_codes',[]);
            $productTags = OrbitInput::post('product_tags');
            $productTags = (array) $productTags;
            $url = OrbitInput::post('url');
            $facebook_url = OrbitInput::post('facebook_url');
            $instagram_url = OrbitInput::post('instagram_url');
            $twitter_url = OrbitInput::post('twitter_url');
            $youtube_url = OrbitInput::post('youtube_url');
            $line_url = OrbitInput::post('line_url');

            $storeHelper = StoreHelper::create();
            $storeHelper->storeCustomValidator();

            // generate array validation image
            $images_validation = $storeHelper->generate_validation_image('store_image', $images, 'orbit.upload.retailer.picture', 3);
            $map_validation = $storeHelper->generate_validation_image('store_map', $map, 'orbit.upload.retailer.map');
            $images_validation = $storeHelper->generate_validation_image('store_image_3rd_party_coupon', $grab_images, 'orbit.upload.base_store.grab_picture', 3);

            $validation_data = [
                'base_merchant_id'    => $base_merchant_id,
                'mall_id'             => $mall_id,
                'floor_id'            => $floor_id,
                'status'              => $status,
                'verification_number' => $verification_number,
            ];

            $validation_error = [
                'base_merchant_id'    => 'required|orbit.empty.base_merchant',
                'mall_id'             => 'required|orbit.empty.mall|orbit.mall.country:' . $base_merchant_id,
                'floor_id'            => 'orbit.empty.floor:' . $mall_id,
                'status'              => 'in:active,inactive',
                'verification_number' => 'alpha_num',
            ];

            $validation_error_message = [
                'orbit.mall.country' => 'Mall does not exist in your selected country'
            ];

            // unit make floor_id is required
            if (! empty($unit)) {
                $validation_error['floor_id'] = 'required|orbit.empty.floor:' . $mall_id;
            }

            // add validation images
            if (! empty($images_validation)) {
                $validation_data += $images_validation['data'];
                $validation_error += $images_validation['error'];
                $validation_error_message += $images_validation['error_message'];
            }
            // add validation map
            if (! empty($map_validation)) {
                $validation_data += $map_validation['data'];
                $validation_error += $map_validation['error'];
                $validation_error_message += $map_validation['error_message'];
            }

            $validator = Validator::make($validation_data, $validation_error, $validation_error_message);

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $newstore = new BaseStore();
            $newstore->base_merchant_id = $base_merchant_id;
            $newstore->merchant_id = $mall_id;
            $newstore->floor_id = $floor_id;
            $newstore->unit = $unit;
            $newstore->phone = $phone;
            $newstore->status = $status;
            $newstore->verification_number = $verification_number;
            $newstore->is_payment_acquire = $paymentAcquire;
            $newstore->url = $url;
            $newstore->facebook_url = $facebook_url;
            $newstore->instagram_url = $instagram_url;
            $newstore->twitter_url = $twitter_url;
            $newstore->youtube_url = $youtube_url;
            $newstore->line_url = $line_url;
            $newstore->save();

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

                $newKeywordObject = new BaseStoreProductTag();
                $newKeywordObject->base_store_id = $newstore->base_store_id;
                $newKeywordObject->product_tag_id = $product_tag_id;
                $newKeywordObject->save();

            }
            $newstore->product_tags = $tenantProductTags;


            // Validate the payment acquire, only chech if payment acquire = Y
            if ($paymentAcquire === 'Y') {
                $objectId = $newstore->base_store_id;
                $objectType = 'base_store';

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

                // Save store contact person
                $validator = Validator::make(
                    array(
                        'store_contact_contact_name' => $storeContactContactName,
                        'store_contact_position' => $storeContactPosition,
                        'store_contact_phone_number' => $storeContactPhoneNumber,
                        'store_contact_email' => $storeContactEmail,
                    ),
                    array(
                        'store_contact_contact_name' => 'required',
                        'store_contact_position' => 'required',
                        'store_contact_phone_number' => 'required',
                        'store_contact_email' => 'required',
                    )
                );
                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $newStoreCotactPerson = new ObjectContact;
                $newStoreCotactPerson->object_id = $objectId;
                $newStoreCotactPerson->object_type = $objectType;
                $newStoreCotactPerson->contact_name = $storeContactContactName;
                $newStoreCotactPerson->position = $storeContactPosition;
                $newStoreCotactPerson->phone_number = $storeContactPhoneNumber;
                $newStoreCotactPerson->email = $storeContactEmail;
                $newStoreCotactPerson->Save();
                $objectContact[] = $newStoreCotactPerson;

                // Save object contact
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
                            'phone_number_for_sms' => isset($phoneNumberForSms[$paymentProviderKey]) ? $phoneNumberForSms[$paymentProviderKey] : null,
                            'mdr' => $mdr[$paymentProviderKey],
                        ),
                        array(
                            'payment_provider_id'  => 'required',
                            'phone_number_for_sms' => 'required',
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
                    $newMerchantStorePaymentProvider->phone_number_for_sms = $phoneNumberForSms[$paymentProviderKey];
                    $newMerchantStorePaymentProvider->mdr = $mdr[$paymentProviderKey];
                    $newMerchantStorePaymentProvider->save();
                    $merchantStorePaymentProvider[$paymentProviderKey] = $newMerchantStorePaymentProvider;
                }

                // Add responses for payment acquire
                if (! empty($objectFinancialDetail)) {
                    $newstore->object_financial_detail = $objectFinancialDetail;
                }
                if (! empty($objectContact)) {
                    $newstore->object_contact = $objectContact;
                }
                if (! empty($objectBank)) {
                    $newstore->object_bank = $objectBank;
                }
                if (! empty($merchantStorePaymentProvider)) {
                    $newstore->merchant_store_payment_provider = $merchantStorePaymentProvider;
                }
            }

            // cause not required
            $newstore->floor = '';
            if (! empty($floor_id) || $floor_id !== '') {
                $newstore->floor = $storeHelper->getValidFloor()->object_name;
            }

            $newstore->mall_id = $mall_id;
            $newstore->location = $storeHelper->getValidMall()->name;

            Event::fire('orbit.basestore.postnewstore.after.save', array($this, $newstore));
            $this->response->data = $newstore;

            // Commit the changes
            $this->commit();
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $httpCode = 403;
        } catch (QueryException $e) {
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
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }
}
