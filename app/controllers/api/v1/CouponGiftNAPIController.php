<?php
/**
 * An API controller for managing Coupon GiftN.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Carbon\Carbon as Carbon;
use \Orbit\Helper\Exception\OrbitCustomException;


class CouponGiftNAPIController extends ControllerAPI
{
     /**
     * Flag to return the query builder.
     *
     * @var Builder
     */
    protected $returnBuilder = FALSE;

    protected $couponViewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    protected $couponModifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];
    protected $couponModifiyRolesWithConsumer = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign admin', 'consumer'];

    protected $pmpAccountDefaultLanguage = NULL;


    public function postNewGiftNCoupon()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newcoupon = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.coupon.postnewcoupon.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.coupon.postnewcoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.coupon.postnewcoupon.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'You have to log in to continue';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.coupon.postnewcoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('current_mall');
            $promotion_name = OrbitInput::post('promotion_name');
            $promotion_type = OrbitInput::post('promotion_type', 'gift_n_coupon');
            $campaignStatus = OrbitInput::post('campaign_status');
            $description = OrbitInput::post('description');
            $long_description = OrbitInput::post('long_description');
            $begin_date = OrbitInput::post('begin_date');
            $end_date = OrbitInput::post('end_date');
            $is_permanent = OrbitInput::post('is_permanent','f');
            $is_all_retailer = OrbitInput::post('is_all_retailer', 'N');
            $is_all_employee = OrbitInput::post('is_all_employee', 'N');
            $maximum_issued_coupon_type = OrbitInput::post('maximum_issued_coupon_type');
            $maximum_issued_coupon = OrbitInput::post('maximum_issued_coupon');
            $coupon_validity_in_date = OrbitInput::post('coupon_validity_in_date');
            $coupon_notification = OrbitInput::post('coupon_notification');
            $rule_type = OrbitInput::post('rule_type');
            $rule_value = OrbitInput::post('rule_value', 0);
            $rule_object_type = OrbitInput::post('rule_object_type');
            $rule_object_id1 = OrbitInput::post('rule_object_id1');
            $rule_object_id2 = OrbitInput::post('rule_object_id2');
            $rule_object_id3 = OrbitInput::post('rule_object_id3');
            $rule_object_id4 = OrbitInput::post('rule_object_id4');
            $rule_object_id5 = OrbitInput::post('rule_object_id5');
            $discount_object_type = OrbitInput::post('discount_object_type');
            $discount_object_id1 = OrbitInput::post('discount_object_id1');
            $discount_object_id2 = OrbitInput::post('discount_object_id2');
            $discount_object_id3 = OrbitInput::post('discount_object_id3');
            $discount_object_id4 = OrbitInput::post('discount_object_id4');
            $discount_object_id5 = OrbitInput::post('discount_object_id5');
            $discount_value = OrbitInput::post('discount_value', 0);
            $is_cumulative_with_coupons = OrbitInput::post('is_cumulative_with_coupons');
            $is_cumulative_with_promotions = OrbitInput::post('is_cumulative_with_promotions');
            $coupon_redeem_rule_value = OrbitInput::post('coupon_redeem_rule_value',0);
            $id_language_default = OrbitInput::post('id_language_default');
            $is_popup = OrbitInput::post('is_popup', 'N');
            $rule_begin_date = OrbitInput::post('rule_begin_date');
            $rule_end_date = OrbitInput::post('rule_end_date');
            $keywords = OrbitInput::post('keywords');
            $translations = OrbitInput::post('translations');
            $keywords = (array) $keywords;
            $productTags = OrbitInput::post('product_tags');
            $productTags = (array) $productTags;
            $linkToTenantIds = OrbitInput::post('link_to_tenant_ids', []);
            $linkToTenantIds = (array) $linkToTenantIds;

            $partner_ids = OrbitInput::post('partner_ids');
            $partner_ids = (array) $partner_ids;
            $is_exclusive = OrbitInput::post('is_exclusive', 'N');
            $is_sponsored = OrbitInput::post('is_sponsored', 'N');
            $sponsor_ids = OrbitInput::post('sponsor_ids');
            $gender = OrbitInput::post('gender', 'Y');

            $is3rdPartyPromotion = OrbitInput::post('is_3rd_party_promotion', 'N');
            $promotionValue = OrbitInput::post('promotion_value', NULL);
            $currency = OrbitInput::post('currency', NULL);
            $offerType = OrbitInput::post('offer_type', NULL);
            $offerValue = OrbitInput::post('offer_value', NULL);
            $originalPrice = OrbitInput::post('original_price', NULL);
            $redemptionVerificationCode = OrbitInput::post('redemption_verification_code', NULL);
            $shortDescription = OrbitInput::post('short_description', NULL);
            $isVisible = OrbitInput::post('is_hidden', 'N') === 'Y' ? 'N' : 'Y';
            $thirdPartyName = OrbitInput::post('third_party_name', NULL);
            $payByNormal = OrbitInput::post('pay_by_normal', 'N');
            $payByWallet = OrbitInput::post('pay_by_wallet', 'N');
            $paymentProviders = OrbitInput::post('payment_provider_ids', null);
            $amountCommission = OrbitInput::post('amount_commission', 0);
            $fixedAmountCommission = OrbitInput::post('fixed_amount_commission', 0);

            $external_id = OrbitInput::post('external_id');
            $price_from_sepulsa = OrbitInput::post('price_from_sepulsa');
            $price_value = OrbitInput::post('price_value');
            $price_selling = OrbitInput::post('price_selling');
            $coupon_image_url = OrbitInput::post('coupon_image_url');
            $how_to_buy_and_redeem = OrbitInput::post('how_to_buy_and_redeem');
            $terms_and_conditions = OrbitInput::post('terms_and_conditions');
            $voucher_benefit = OrbitInput::post('voucher_benefit');
            $token = OrbitInput::post('token');
            $maxQuantityPerPurchase = OrbitInput::post('max_quantity_per_purchase', NULL);
            $maxQuantityPerUser = OrbitInput::post('max_quantity_per_user', NULL);
            $shortlinks = OrbitInput::post('shortlinks');
            $price_to_gtm = OrbitInput::post('price_to_gtm');
            $status = OrbitInput::post('status');

            if ($status === 'active') {
                $campaignStatus = 'ongoing';
            }
            else {
                $campaignStatus = 'not started';
            }

            $validator_value = [
                'promotion_name'          => $promotion_name,
                'promotion_type'          => $promotion_type,
                'begin_date'              => $begin_date,
                'end_date'                => $end_date,
                'rule_type'               => $rule_type,
                'status'                  => $status,
                'coupon_validity_in_date' => $coupon_validity_in_date,
                'rule_value'              => $rule_value,
                'discount_value'          => $discount_value,
                'is_all_retailer'         => $is_all_retailer,
                'is_all_employee'         => $is_all_employee,
                'id_language_default'     => $id_language_default,
                'is_popup'                => $is_popup,
                'is_visible'              => $isVisible,
                'is_3rd_party_promotion'  => $is3rdPartyPromotion,
                'maximum_issued_coupon'   => $maximum_issued_coupon,
                'price_value'             => $price_value,
                'price_selling'           => $price_selling,
                'how_to_buy_and_redeem'   => $how_to_buy_and_redeem,
                'max_quantity_per_purchase' => $maxQuantityPerPurchase,
                'shortlinks'              => $shortlinks,
                'price_to_gtm'            => $price_to_gtm,
                'link_to_tenant_ids'      => $linkToTenantIds,
            ];
            $validator_validation = [
                'promotion_name'          => 'required|max:255',
                'promotion_type'          => 'required|in:gift_n_coupon',
                'begin_date'              => 'required|date_format:Y-m-d H:i:s',
                'end_date'                => 'required|date_format:Y-m-d H:i:s',
                'rule_type'               => 'orbit.empty.coupon_rule_type',
                'status'                  => 'required|orbit.empty.coupon_status',
                'coupon_validity_in_date' => 'date_format:Y-m-d H:i:s',
                'rule_value'              => 'required|numeric|min:0',
                'discount_value'          => 'required|numeric|min:0',
                'is_all_retailer'         => 'orbit.empty.status_link_to',
                'is_all_employee'         => 'orbit.empty.status_link_to',
                'id_language_default'     => 'required|orbit.empty.language_default',
                'is_popup'                => 'in:Y,N',
                'is_visible'              => 'required|in:Y,N',
                'is_3rd_party_promotion'  => 'required|in:Y,N',
                'maximum_issued_coupon'   => '',
                'price_value'             => 'required',
                'price_selling'           => 'required',
                'max_quantity_per_purchase' => 'required|numeric',
                'shortlinks'              => 'required',
                'price_to_gtm'            => 'required',
                'link_to_tenant_ids'      => 'required',
            ];
            $validator_message = [
                'rule_value.required'     => 'The amount to obtain is required',
                'rule_value.numeric'      => 'The amount to obtain must be a number',
                'rule_value.min'          => 'The amount to obtain must be greater than zero',
                'discount_value.required' => 'The coupon value is required',
                'discount_value.numeric'  => 'The coupon value must be a number',
                'discount_value.min'      => 'The coupon value must be greater than zero',
                'is_popup.in'             => 'is popup must Y or N',
                'promotion_name.required' => 'Coupon name is required',
                'begin_date.required'     => 'Start Date is required',
                'end_date.required'       => 'End Date is required',
                'price_to_gtm.required'   => 'GIFT-N Price to GTM is required',
                'price_value.required'    => 'Coupon Facial Value is required',
                'price_selling.required'  => 'Selling Price to User is required',
                'shortlinks.required'     => 'Shortlink is required',
                'coupon_validity_in_date.required'     => 'Validity Redeem Date is required',
            ];

            if (! empty($is_exclusive) && ! empty($partner_ids)) {
                $validator_value['partner_exclusive']               = $is_exclusive;
                $validator_validation['partner_exclusive']          = 'in:Y,N|orbit.empty.exclusive_partner';
                $validator_message['orbit.empty.exclusive_partner'] = 'Partner is not exclusive / inactive';
            }

            $validator = Validator::make(
                $validator_value,
                $validator_validation,
                $validator_message
            );

            Event::fire('orbit.coupon.postnewcoupon.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Check the end date should be less than validity in date
            $int_before_end_date = strtotime(date('Y-m-d', strtotime($end_date)));
            $int_validity_date = strtotime($coupon_validity_in_date);


            $arrayShortlinks = [];
            // validate coupon codes
            if (! empty($shortlinks)) {
                $dupes = array();
                // trim and explode coupon codes to array
                $arrayShortlinks = array_map('trim', explode("\n", $shortlinks));
                // delete empty array and reorder it
                $arrayShortlinks = array_values(array_filter($arrayShortlinks));
                // find the dupes
                foreach(array_count_values($arrayShortlinks) as $val => $frequency) {
                    if ($frequency > 1) $dupes[] = $val;
                }

                if (! empty($dupes)) {
                    $stringDupes = implode(',', $dupes);
                    $errorMessage = 'The coupon codes you supplied have duplicates: %s';
                    OrbitShopAPI::throwInvalidArgument(sprintf($errorMessage, $stringDupes));
                }
            }

            // maximum redeem validation
            if (! empty($maximumRedeem)) {
                if ($maximumRedeem > count($arrayShortlinks)) {
                    $errorMessage = 'The total maximum redeemed coupon can not be more than amount of coupon code';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                if ($maximumRedeem < 1) {
                    $errorMessage = 'Minimum amount of maximum redeemed coupon is 1';
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            } else {
                $maximumRedeem = count($arrayShortlinks);
            }

            Event::fire('orbit.coupon.postnewcoupon.after.validation', array($this, $validator));

            // A means all gender
            if ($gender === 'A') {
                $gender = 'Y';
            }

            // save Coupon.
            $idStatus = CampaignStatus::select('campaign_status_id','campaign_status_name')->where('campaign_status_name', $campaignStatus)->first();
            $maximum_issued_coupon = count($arrayShortlinks);

            $newcoupon = new Coupon();
            $newcoupon->merchant_id = $merchant_id;
            $newcoupon->promotion_name = $promotion_name;
            $newcoupon->description = $description;
            $newcoupon->promotion_type = $promotion_type;
            $newcoupon->status = $status;
            $newcoupon->campaign_status_id = $idStatus->campaign_status_id;
            $newcoupon->long_description = $long_description;
            $newcoupon->begin_date = $begin_date;
            $newcoupon->end_date = $end_date;
            $newcoupon->is_permanent = $is_permanent;
            $newcoupon->is_all_retailer = $is_all_retailer;
            $newcoupon->is_all_employee = $is_all_employee;
            $newcoupon->maximum_issued_coupon_type = $maximum_issued_coupon_type;
            $newcoupon->maximum_issued_coupon = $maximum_issued_coupon;
            $newcoupon->coupon_validity_in_date = $coupon_validity_in_date;
            $newcoupon->coupon_notification = $coupon_notification;
            $newcoupon->created_by = $this->api->user->user_id;
            $newcoupon->is_all_age = 'Y';
            $newcoupon->is_all_gender = $gender;
            $newcoupon->is_popup = $is_popup;
            $newcoupon->is_exclusive = $is_exclusive;
            $newcoupon->is_visible = $isVisible;
            $newcoupon->maximum_redeem = $maximum_issued_coupon;
            $newcoupon->available = $maximum_issued_coupon;
            $newcoupon->is_payable_by_wallet = $payByWallet;
            $newcoupon->is_payable_by_normal = $payByNormal;
            $newcoupon->transaction_amount_commission = $amountCommission;
            $newcoupon->fixed_amount_commission = $fixedAmountCommission;
            $newcoupon->is_sponsored = $is_sponsored;
            $newcoupon->price_selling = $price_selling;
            $newcoupon->price_old = $price_value;
            $newcoupon->max_quantity_per_purchase = $maxQuantityPerPurchase;
            $newcoupon->max_quantity_per_user = $maxQuantityPerUser;
            $newcoupon->price_to_gtm = $price_to_gtm;
            $newcoupon->how_to_buy_and_redeem = $how_to_buy_and_redeem;

            $newcoupon->is_unique_redeem = 'N';
            if ($rule_type === 'unique_coupon_per_user') {
                $newcoupon->is_unique_redeem = 'Y';
                $newcoupon->max_quantity_per_purchase = 1;
                $newcoupon->max_quantity_per_user = 1;
            }

            Event::fire('orbit.coupon.postnewcoupon.before.save', array($this, $newcoupon));

            $newcoupon->save();

            // Return campaign_status_name
            $newcoupon->campaign_status = $idStatus->campaign_status_name;


            // save CouponRule.
            $couponrule = new CouponRule();
            $couponrule->rule_type = $rule_type;
            $couponrule->rule_value = $rule_value;
            $couponrule->rule_object_type = $rule_object_type;

            // rule_object_id1
            if (trim($rule_object_id1) === '') {
                $couponrule->rule_object_id1 = NULL;
            } else {
                $couponrule->rule_object_id1 = $rule_object_id1;
            }

            // rule_object_id2
            if (trim($rule_object_id2) === '') {
                $couponrule->rule_object_id2 = NULL;
            } else {
                $couponrule->rule_object_id2 = $rule_object_id2;
            }

            // rule_object_id3
            if (trim($rule_object_id3) === '') {
                $couponrule->rule_object_id3 = NULL;
            } else {
                $couponrule->rule_object_id3 = $rule_object_id3;
            }

            // rule_object_id4
            if (trim($rule_object_id4) === '') {
                $couponrule->rule_object_id4 = NULL;
            } else {
                $couponrule->rule_object_id4 = $rule_object_id4;
            }

            // rule_object_id5
            if (trim($rule_object_id5) === '') {
                $couponrule->rule_object_id5 = NULL;
            } else {
                $couponrule->rule_object_id5 = $rule_object_id5;
            }

            $couponrule->discount_object_type = $discount_object_type;

            // discount_object_id1
            if (trim($discount_object_id1) === '') {
                $couponrule->discount_object_id1 = NULL;
            } else {
                $couponrule->discount_object_id1 = $discount_object_id1;
            }

            // discount_object_id2
            if (trim($discount_object_id2) === '') {
                $couponrule->discount_object_id2 = NULL;
            } else {
                $couponrule->discount_object_id2 = $discount_object_id2;
            }

            // discount_object_id3
            if (trim($discount_object_id3) === '') {
                $couponrule->discount_object_id3 = NULL;
            } else {
                $couponrule->discount_object_id3 = $discount_object_id3;
            }

            // discount_object_id4
            if (trim($discount_object_id4) === '') {
                $couponrule->discount_object_id4 = NULL;
            } else {
                $couponrule->discount_object_id4 = $discount_object_id4;
            }

            // discount_object_id5
            if (trim($discount_object_id5) === '') {
                $couponrule->discount_object_id5 = NULL;
            } else {
                $couponrule->discount_object_id5 = $discount_object_id5;
            }

            $couponrule->discount_value = $discount_value;
            $couponrule->is_cumulative_with_coupons = $is_cumulative_with_coupons;
            $couponrule->is_cumulative_with_promotions = $is_cumulative_with_promotions;
            $couponrule->coupon_redeem_rule_value = $coupon_redeem_rule_value;
            $couponrule->rule_begin_date = $begin_date;
            $couponrule->rule_end_date = $end_date;
            $couponrule = $newcoupon->couponRule()->save($couponrule);
            $newcoupon->coupon_rule = $couponrule;

            // save CouponRetailer
            $retailers = array();
            $isMall = 'tenant';
            $mallid = array();

            foreach ($linkToTenantIds as $retailer_id) {
                $data = @json_decode($retailer_id);
                $tenant_id = $data->tenant_id;
                $mall_id = $data->mall_id;

                if(! in_array($mall_id, $mallid)) {
                    $mallid[] = $mall_id;
                }

                if ($tenant_id === $mall_id) {
                    $isMall = 'mall';
                } else {
                    $isMall = 'tenant';
                }

                $couponretailer = new CouponRetailer();
                $couponretailer->retailer_id = $tenant_id;
                $couponretailer->promotion_id = $newcoupon->promotion_id;
                $couponretailer->object_type = $isMall;
                $couponretailer->save();
                $couponretailers[] = $couponretailer;
            }

            $newcoupon->link_to_tenants = $couponretailers;

            //save to user campaign
            $usercampaign = new UserCampaign();
            $usercampaign->user_id = $user->user_id;
            $usercampaign->campaign_id = $newcoupon->promotion_id;
            $usercampaign->campaign_type = 'coupon';
            $usercampaign->save();

            // save Keyword
            $couponKeywords = array();
            foreach ($keywords as $keyword) {
                $keyword_id = null;

                $existKeyword = Keyword::excludeDeleted()
                ->where('keyword', '=', $keyword)
                ->where('merchant_id', '=', 0)
                ->first();

                if (empty($existKeyword)) {
                    $newKeyword = new Keyword();
                    $newKeyword->merchant_id = 0;
                    $newKeyword->keyword = $keyword;
                    $newKeyword->status = 'active';
                    $newKeyword->created_by = $this->api->user->user_id;
                    $newKeyword->modified_by = $this->api->user->user_id;
                    $newKeyword->save();

                    $keyword_id = $newKeyword->keyword_id;
                    $couponKeywords[] = $newKeyword;
                } else {
                    $keyword_id = $existKeyword->keyword_id;
                    $couponKeywords[] = $existKeyword;
                }

                $newKeywordObject = new KeywordObject();
                $newKeywordObject->keyword_id = $keyword_id;
                $newKeywordObject->object_id = $newcoupon->promotion_id;
                $newKeywordObject->object_type = 'coupon';
                $newKeywordObject->save();
            }
            $newcoupon->keywords = $couponKeywords;

            // Save product tags
            $couponProductTags = array();
            foreach ($productTags as $productTag) {
                $product_tag_id = null;

                $existProductTag = ProductTag::excludeDeleted()
                    ->where('product_tag', '=', $productTag)
                    ->where('merchant_id', '=', 0)
                    ->first();

                if (empty($existProductTag)) {
                    $newProductTag = new ProductTag();
                    $newProductTag->merchant_id = 0;
                    $newProductTag->product_tag = $productTag;
                    $newProductTag->status = 'active';
                    $newProductTag->created_by = $this->api->user->user_id;
                    $newProductTag->modified_by = $this->api->user->user_id;
                    $newProductTag->save();

                    $product_tag_id = $newProductTag->product_tag_id;
                    $couponProductTags[] = $newProductTag;
                } else {
                    $product_tag_id = $existProductTag->product_tag_id;
                    $couponProductTags[] = $existProductTag;
                }

                $newProductTagObject = new ProductTagObject();
                $newProductTagObject->product_tag_id = $product_tag_id;
                $newProductTagObject->object_id = $newcoupon->promotion_id;
                $newProductTagObject->object_type = 'coupon';
                $newProductTagObject->save();
            }
            $newcoupon->product_tags = $couponProductTags;

            // save ObjectPartner (link to partner)
            $objectPartners = array();
            foreach ($partner_ids as $partner_id) {
                $objectPartner = new ObjectPartner();
                $objectPartner->object_id = $newcoupon->promotion_id;
                $objectPartner->object_type = 'coupon';
                $objectPartner->partner_id = $partner_id;
                $objectPartner->save();
                $objectPartners[] = $objectPartner;
            }
            $newcoupon->partners = $objectPartners;


            Event::fire('orbit.coupon.postnewcoupon.after.save', array($this, $newcoupon));

            OrbitInput::post('translations', function($translation_json_string) use ($newcoupon, $mallid, $is3rdPartyPromotion) {
                $isThirdParty = $is3rdPartyPromotion === 'Y' ? TRUE : FALSE;
                $this->validateAndSaveTranslations($newcoupon, $translation_json_string, 'create', $isThirdParty);
            });

            // Default language for pmp_account is required
            $malls = implode("','", $mallid);
            $prefix = DB::getTablePrefix();
            $isAvailable = CouponTranslation::where('promotion_id', '=', $newcoupon->promotion_id)
                                        ->whereRaw("
                                            {$prefix}coupon_translations.merchant_language_id = (
                                                SELECT language_id
                                                FROM {$prefix}languages
                                                WHERE name = (SELECT mobile_default_language FROM {$prefix}campaign_account WHERE user_id = {$this->quote($this->api->user->user_id)})
                                            )
                                        ")
                                        ->where(function($query) {
                                            $query->where('promotion_name', '=', '')
                                                  ->orWhere('description', '=', '')
                                                  ->orWhereNull('promotion_name')
                                                  ->orWhereNull('description');
                                          })
                                        ->first();

            $required_name = false;
            $required_desc = false;

            if (is_object($isAvailable)) {
                if ($isAvailable->promotion_name === '' || empty($isAvailable->promotion_name)) {
                    $required_name = true;
                }
                if ($isAvailable->description === '' || empty($isAvailable->description)) {
                    $required_desc = true;
                }
            }

            if ($required_name === true && $required_desc === true) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_both', ['type' => 'coupon']);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } elseif ($required_name) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_name', ['type' => 'coupon']);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } elseif ($required_desc) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_desc', ['type' => 'coupon']);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $this->response->data = $newcoupon;
            // $this->response->data->translation_default = $coupon_translation_default;

            // issue coupon if coupon code is supplied
            if (! empty($arrayShortlinks)) {
                IssuedCoupon::bulkIssueGiftN($arrayShortlinks, $newcoupon->promotion_id, $newcoupon->coupon_validity_in_date, $user);
            }

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Coupon Created: %s', $newcoupon->promotion_name);
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon OK')
                    ->setObject($newcoupon)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.coupon.postnewgiftncoupon.after.commit', array($this, $newcoupon));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.postnewcoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.postnewcoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.postnewcoupon.query.error', array($this, $e));

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

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            Event::fire('orbit.coupon.postnewcoupon.custom.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getCustomData();
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();

        } catch (Exception $e) {
            Event::fire('orbit.coupon.postnewcoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage() . $e->getFile() . $e->getLine();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    public function postUpdateGiftNCoupon()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedcoupon = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.coupon.postupdatecoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.coupon.postupdatecoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.coupon.postupdatecoupon.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponModifiyRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.coupon.postupdatecoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $promotion_id = OrbitInput::post('promotion_id');
            $merchant_id = OrbitInput::post('current_mall');
            $campaignStatus = OrbitInput::post('campaign_status');
            $rule_type = OrbitInput::post('rule_type');
            $rule_object_type = OrbitInput::post('rule_object_type');
            $discount_object_type = OrbitInput::post('discount_object_type');
            $begin_date = OrbitInput::post('begin_date');
            $end_date = OrbitInput::post('end_date');
            $is_permanent = OrbitInput::post('is_permanent');
            $is_all_retailer = OrbitInput::post('is_all_retailer', 'N');
            $is_all_employee = OrbitInput::post('is_all_employee', 'N');
            $maximum_issued_coupon_type = OrbitInput::post('maximum_issued_coupon_type');
            $coupon_validity_in_date = OrbitInput::post('coupon_validity_in_date');
            $discount_value = OrbitInput::post('discount_value', 0);
            $rule_value = OrbitInput::post('rule_value', 0);
            $id_language_default = OrbitInput::post('id_language_default');
            $translations = OrbitInput::post('translations');

            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;
            $linkToTenantIds = OrbitInput::post('link_to_tenant_ids');
            $linkToTenantIds = (array) $linkToTenantIds;

            $partner_ids = OrbitInput::post('partner_ids');
            $partner_ids = (array) $partner_ids;
            $is_exclusive = OrbitInput::post('is_exclusive', 'N');
            $is_visible = OrbitInput::post('is_hidden', 'N') === 'Y' ? 'N' : 'Y';

            $is_3rd_party_promotion = OrbitInput::post('is_3rd_party_promotion', 'N');
            $promotion_value = OrbitInput::post('promotion_value', NULL);
            $currency = OrbitInput::post('currency', NULL);
            $offer_type = OrbitInput::post('offer_type', NULL);
            $offer_value = OrbitInput::post('offer_value', NULL);
            $original_price = OrbitInput::post('original_price', NULL);
            $redemption_verification_code = OrbitInput::post('redemption_verification_code', NULL);
            $short_description = OrbitInput::post('short_description', NULL);
            $third_party_name = OrbitInput::post('third_party_name', NULL) === '' ? 'grab' : OrbitInput::post('third_party_name');
            $payByWallet = OrbitInput::post('pay_by_wallet', 'N');
            $payByNormal = OrbitInput::post('pay_by_normal', 'N');
            $amountCommission = OrbitInput::post('amount_commission', 0);
            $fixedAmountCommission = OrbitInput::post('fixed_amount_commission', null);
            $sponsor_ids = OrbitInput::post('sponsor_ids');

            $promotion_id = OrbitInput::post('promotion_id');
            $external_id = OrbitInput::post('external_id');
            $price_from_sepulsa = OrbitInput::post('price_from_sepulsa');
            $price_value = OrbitInput::post('price_value');
            $price_selling = OrbitInput::post('price_selling');
            $coupon_image_url = OrbitInput::post('coupon_image_url');
            $how_to_buy_and_redeem = OrbitInput::post('how_to_buy_and_redeem');
            $terms_and_conditions = OrbitInput::post('terms_and_conditions');
            $status = OrbitInput::post('status');

            if ($status === 'active') {
                $campaignStatus = 'ongoing';
            } else {
                $campaignStatus = 'not started';
            }

            $idStatus = CampaignStatus::select('campaign_status_id')->where('campaign_status_name', $campaignStatus)->first();
            // $status = 'inactive';
            // if ($campaignStatus === 'ongoing') {
            //     $status = 'active';
            // }

            $data = array(
                'promotion_id'            => $promotion_id,
                'status'                  => $status,
                'begin_date'              => $begin_date,
                'end_date'                => $end_date,
                'rule_type'               => $rule_type,
                'rule_value'              => $rule_value,
                'discount_value'          => $discount_value,
                'is_all_retailer'         => $is_all_retailer,
                'is_all_employee'         => $is_all_employee,
                'id_language_default'     => $id_language_default,
                'is_visible'              => $is_visible,
                'is_3rd_party_promotion'  => $is_3rd_party_promotion,
                'campaign_status'         => $campaignStatus,
            );

            // Validate promotion_name only if exists in POST.
            OrbitInput::post('promotion_name', function($promotion_name) use (&$data) {
                $data['promotion_name'] = $promotion_name;
            });

            $validator = Validator::make(
                $data,
                array(
                    'promotion_id'            => 'required|orbit.update.coupon',
                    'promotion_name'          => 'sometimes|required|max:255',
                    'status'                  => 'orbit.empty.coupon_status',
                    'begin_date'              => 'date_format:Y-m-d H:i:s',
                    'end_date'                => 'date_format:Y-m-d H:i:s',
                    'rule_type'               => 'orbit.empty.coupon_rule_type',
                    'rule_value'              => 'numeric|min:0',
                    'discount_value'          => 'numeric|min:0',
                    'is_all_retailer'         => 'orbit.empty.status_link_to',
                    'is_all_employee'         => 'orbit.empty.status_link_to',
                    'id_language_default'     => 'required|orbit.empty.language_default',
                    'is_visible'              => 'in:Y,N',
                    'is_3rd_party_promotion'  => 'in:Y,N',
                    'campaign_status'         => 'orbit.check.issued_coupon',
                ),
                array(
                    'rule_value.required'       => 'The amount to obtain is required',
                    'rule_value.numeric'        => 'The amount to obtain must be a number',
                    'rule_value.min'            => 'The amount to obtain must be greater than zero',
                    'discount_value.required'   => 'The coupon value is required',
                    'discount_value.numeric'    => 'The coupon value must be a number',
                    'discount_value.min'        => 'The coupon value must be greater than zero',
                    'orbit.update.coupon'       => 'Cannot update campaign with status ' . $campaignStatus,
                    'orbit.empty.exclusive_partner' => 'Partner is not exclusive / inactive',
                    'orbit.check.issued_coupon' => 'There is one or more coupon unredeemed',
                    'promotion_id.required'     => 'GTM Coupon ID is required',
                )
            );

            Event::fire('orbit.coupon.postupdatecoupon.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Remove all key in Redis when campaign is stopped
            if ($status == 'inactive') {
                if (Config::get('orbit.cache.ng_redis_enabled', FALSE)) {
                    $redis = Cache::getRedis();
                    $keyName = array('coupon','home');
                    foreach ($keyName as $value) {
                        $keys = $redis->keys("*$value*");
                        if (! empty($keys)) {
                            foreach ($keys as $key) {
                                $redis->del($key);
                            }
                        }
                    }
                }
            }

            Event::fire('orbit.coupon.postupdatecoupon.after.validation', array($this, $validator));

            // Redeem to tenant
            $linktotenantnew = array();
            $mallid = array();
            foreach ($linkToTenantIds as $linktotenant) {
                $data = @json_decode($linktotenant);
                $tenant_id = $data->tenant_id;
                $mall_id = $data->mall_id;

                if(! in_array($mall_id, $mallid)) {
                    $mallid[] = $mall_id;
                }

                $linktotenantnew[] = $tenant_id;
            }

            $updatedcoupon = Coupon::where('promotion_id', $promotion_id)->first();

            $prefix = DB::getTablePrefix();
            // this is for send email to marketing, before and after list
            $beforeUpdatedCoupon = Coupon::with([
                                            'translations.language',
                                            'translations.media',
                                            'ages.ageRange',
                                            'keywords',
                                            'product_tags',
                                            'campaign_status',
                                            'tenants' => function($q) use($prefix) {
                                                $q->addSelect(DB::raw("CONCAT ({$prefix}merchants.name, ' at ', malls.name) as name"));
                                                $q->join(DB::raw("{$prefix}merchants malls"), DB::raw("malls.merchant_id"), '=', 'merchants.parent_id');
                                            },
                                            'employee',
                                            'couponRule' => function($q) use($prefix) {
                                                $q->select('promotion_rule_id', 'promotion_id', DB::raw("DATE_FORMAT({$prefix}promotion_rules.rule_end_date, '%d/%m/%Y %H:%i') as rule_end_date"));
                                            }
                                        ])
                                        ->selectRaw("{$prefix}promotions.*,
                                            DATE_FORMAT({$prefix}promotions.end_date, '%d/%m/%Y %H:%i') as end_date,
                                            DATE_FORMAT({$prefix}promotions.coupon_validity_in_date, '%d/%m/%Y %H:%i') as coupon_validity_in_date,
                                            IF({$prefix}promotions.maximum_issued_coupon = 0, 'Unlimited', {$prefix}promotions.maximum_issued_coupon) as maximum_issued_coupon
                                        ")
                                        ->excludeDeleted()
                                        ->where('promotion_id', $promotion_id)
                                        ->first();

            $statusdb = $updatedcoupon->status;
            $enddatedb = $updatedcoupon->end_date;

            // save Coupon
            OrbitInput::post('merchant_id', function($merchant_id) use ($updatedcoupon) {
                $updatedcoupon->merchant_id = $merchant_id;
            });

            OrbitInput::post('campaign_status', function($campaignStatus) use ($updatedcoupon, $idStatus, $status) {
                $updatedcoupon->status = $status;
                $updatedcoupon->campaign_status_id = $idStatus->campaign_status_id;
            });

            OrbitInput::post('status', function($campaignStatus) use ($updatedcoupon, $idStatus, $status) {
                $updatedcoupon->status = $status;
                $updatedcoupon->campaign_status_id = $idStatus->campaign_status_id;
            });

            OrbitInput::post('description', function($description) use ($updatedcoupon) {
                $updatedcoupon->description = $description;
            });

            OrbitInput::post('long_description', function($long_description) use ($updatedcoupon) {
                $updatedcoupon->long_description = $long_description;
            });

            OrbitInput::post('begin_date', function($begin_date) use ($updatedcoupon) {
                $updatedcoupon->begin_date = $begin_date;
            });

            OrbitInput::post('end_date', function($end_date) use ($updatedcoupon) {
                $updatedcoupon->end_date = $end_date;
            });

            OrbitInput::post('price_selling', function($price_selling) use ($updatedcoupon) {
                $updatedcoupon->price_selling = $price_selling;
            });

            OrbitInput::post('amount_commission', function($amount_commission) use ($updatedcoupon) {
                $updatedcoupon->amount_commission = $amount_commission;
            });

            OrbitInput::post('is_all_retailer', function($is_all_retailer) use ($updatedcoupon) {
                $updatedcoupon->is_all_retailer = $is_all_retailer;
                if ($is_all_retailer == 'Y') {
                    $deleted_retailer_ids = CouponRetailerRedeem::where('promotion_id', $updatedcoupon->promotion_id)->get(array('retailer_id'))->toArray();
                    $updatedcoupon->tenants()->detach($deleted_retailer_ids);
                    $updatedcoupon->load('tenants');
                }
            });

            OrbitInput::post('is_all_employee', function($is_all_employee) use ($updatedcoupon) {
                $updatedcoupon->is_all_employee = $is_all_employee;
            });

            OrbitInput::post('maximum_issued_coupon_type', function($maximum_issued_coupon_type) use ($updatedcoupon) {
                $updatedcoupon->maximum_issued_coupon_type = $maximum_issued_coupon_type;
            });

            OrbitInput::post('coupon_validity_in_date', function($coupon_validity_in_date) use ($updatedcoupon, $end_date) {
                // Check the end date should be less than validity in date
                $int_before_end_date = strtotime(date('Y-m-d', strtotime($end_date)));
                $int_validity_date = strtotime($coupon_validity_in_date);
                // if ($int_validity_date <= $int_before_end_date) {
                //     $errorMessage = 'The validity redeem date should be greater than the end date.';
                //     OrbitShopAPI::throwInvalidArgument($errorMessage);
                // }

                $updatedcoupon->coupon_validity_in_date = $coupon_validity_in_date;
            });

            OrbitInput::post('gender', function($gender) use ($updatedcoupon) {
                if ($gender === 'A') {
                    $gender = 'Y';
                }

                $updatedcoupon->is_all_gender = $gender;
            });

            if ($rule_type === 'unique_coupon_per_user') {
                $updatedcoupon->is_unique_redeem = 'Y';
            }

            $updatedcoupon->modified_by = $this->api->user->user_id;

            Event::fire('orbit.coupon.postupdatecoupon.before.save', array($this, $updatedcoupon));

            $updatedcoupon->setUpdatedAt($updatedcoupon->freshTimestamp());
            $updatedcoupon->save();

            // save CouponRule.
            $couponrule = CouponRule::where('promotion_id', '=', $promotion_id)->first();
            OrbitInput::post('rule_type', function($rule_type) use ($couponrule) {
                if (trim($rule_type) === '') {
                    $rule_type = NULL;
                }
                $couponrule->rule_type = $rule_type;
            });

            OrbitInput::post('rule_value', function($rule_value) use ($couponrule) {
                $couponrule->rule_value = $rule_value;
            });

            OrbitInput::post('begin_date', function($begin_date) use ($couponrule) {
                $couponrule->rule_begin_date = $begin_date;
            });

            OrbitInput::post('end_date', function($end_date) use ($couponrule) {
                $couponrule->rule_end_date = $end_date;
            });

            $couponrule->save();
            $updatedcoupon->setRelation('couponRule', $couponrule);
            $updatedcoupon->coupon_rule = $couponrule;

            // save CouponRetailerRedeem
            OrbitInput::post('no_retailer', function($no_retailer) use ($updatedcoupon) {
                if ($no_retailer == 'Y') {
                    $deleted_retailer_ids = CouponRetailerRedeem::where('promotion_id', $updatedcoupon->promotion_id)->get(array('retailer_id'))->toArray();
                    $updatedcoupon->tenants()->detach($deleted_retailer_ids);
                    $updatedcoupon->load('tenants');
                }
            });

            OrbitInput::post('no_employee', function($no_employee) use ($updatedcoupon) {
                if ($no_employee == 'Y') {
                    $deleted_employee = CouponEmployee::where('promotion_id', $updatedcoupon->promotion_id)->get(array('user_id'))->toArray();
                    $updatedcoupon->employee()->detach($deleted_employee);
                    $updatedcoupon->load('employee');
                }
            });

            // save CouponRetailer
            OrbitInput::post('no_link_to_tenant', function($no_retailer) use ($updatedcoupon) {
                if ($no_retailer == 'Y') {
                    $deleted_retailer_ids = CouponRetailer::where('promotion_id', $updatedcoupon->promotion_id)->get(array('retailer_id'))->toArray();
                    $updatedcoupon->linkToTenants()->detach($deleted_retailer_ids);
                    $updatedcoupon->load('linkToTenants');
                }
            });

            OrbitInput::post('link_to_tenant_ids', function($retailer_ids) use ($promotion_id, $payByWallet) {
                // validating retailer_ids.
                foreach ($retailer_ids as $retailer_id_json) {
                    $data = @json_decode($retailer_id_json);
                    $tenant_id = $data->tenant_id;
                    $mall_id = $data->mall_id;

                    $validator = Validator::make(
                        array(
                            'retailer_id'   => $tenant_id,

                        ),
                        array(
                            'retailer_id'   => 'orbit.empty.tenant',
                        )
                    );

                    Event::fire('orbit.coupon.postupdatecoupon.before.retailervalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.coupon.postupdatecoupon.after.retailervalidation', array($this, $validator));
                }

                $existRetailer = CouponRetailerRedeem::where('promotion_id', '=', $promotion_id)->lists('promotion_retailer_redeem_id');
                if (! empty($existRetailer)) {
                    $delete_provider = CouponPaymentProvider::whereIn('promotion_retailer_redeem_id', $existRetailer)->delete();
                }

                // Delete old data
                $delete_retailer = CouponRetailerRedeem::where('promotion_id', '=', $promotion_id);
                $delete_retailer->delete();

                // Insert new data
                $retailers = array();
                $isMall = 'tenant';
                $mallid = array();
                foreach ($retailer_ids as $retailer_id) {
                    $data = @json_decode($retailer_id);
                    $tenant_id = $data->tenant_id;
                    $mall_id = $data->mall_id;

                    if(! in_array($mall_id, $mallid)) {
                        $mallid[] = $mall_id;
                    }

                    if ($tenant_id === $mall_id) {
                        $isMall = 'mall';
                    } else {
                        $isMall = 'tenant';
                    }


                    $retailer = new CouponRetailerRedeem();
                    $retailer->promotion_id = $promotion_id;
                    $retailer->retailer_id = $tenant_id;
                    $retailer->object_type = $isMall;
                    $retailer->save();

                    $retailerRedeemId = $retailer->promotion_retailer_redeem_id;
                }

                // Insert to promotion retailer, with delete first old data
                $delete_coupon_retailer = CouponRetailer::where('promotion_id', '=', $promotion_id);
                $delete_coupon_retailer->delete();

                foreach ($retailer_ids as $retailer_id) {
                    $data = @json_decode($retailer_id);
                    $tenant_id = $data->tenant_id;
                    $mall_id = $data->mall_id;

                    if(! in_array($mall_id, $mallid)) {
                        $mallid[] = $mall_id;
                    }

                    if ($tenant_id === $mall_id) {
                        $isMall = 'mall';
                    } else {
                        $isMall = 'tenant';
                    }
                    // Insert new coupon retailer
                    $couponretailer = new CouponRetailer();
                    $couponretailer->retailer_id = $tenant_id;
                    $couponretailer->promotion_id = $promotion_id;
                    $couponretailer->object_type = $isMall;
                    $couponretailer->save();
                }
            });

            OrbitInput::post('partner_ids', function($partner_ids) use ($updatedcoupon, $promotion_id) {
                // validate retailer_ids
                $partner_ids = (array) $partner_ids;

                // Delete old data
                $delete_object_partner = ObjectPartner::where('object_id', '=', $promotion_id);
                $delete_object_partner->delete();

                $objectPartners = array();
                // Insert new data
                if(array_filter($partner_ids)) {
                    foreach ($partner_ids as $partner_id) {
                        $objectPartner = new ObjectPartner();
                        $objectPartner->object_id = $promotion_id;
                        $objectPartner->object_type = 'coupon';
                        $objectPartner->partner_id = $partner_id;
                        $objectPartner->save();
                        $objectPartners[] = $objectPartner;
                    }
                }
                $updatedcoupon->partners = $objectPartners;
            });

            // Delete old data
            $deleted_keyword_object = KeywordObject::where('object_id', '=', $promotion_id)
                                                    ->where('object_type', '=', 'coupon');
            $deleted_keyword_object->delete();

            OrbitInput::post('keywords', function($keywords) use ($updatedcoupon, $user, $promotion_id) {
                // Insert new data
                $couponKeywords = array();
                foreach ($keywords as $keyword) {
                    $keyword_id = null;

                    $existKeyword = Keyword::excludeDeleted()
                        ->where('keyword', '=', $keyword)
                        ->where('merchant_id', '=', 0)
                        ->first();

                    if (empty($existKeyword)) {
                        $newKeyword = new Keyword();
                        $newKeyword->merchant_id = 0;
                        $newKeyword->keyword = $keyword;
                        $newKeyword->status = 'active';
                        $newKeyword->created_by = $user->user_id;
                        $newKeyword->modified_by = $user->user_id;
                        $newKeyword->save();

                        $keyword_id = $newKeyword->keyword_id;
                        $couponKeywords[] = $newKeyword;
                    } else {
                        $keyword_id = $existKeyword->keyword_id;
                        $couponKeywords[] = $existKeyword;
                    }


                    $newKeywordObject = new KeywordObject();
                    $newKeywordObject->keyword_id = $keyword_id;
                    $newKeywordObject->object_id = $promotion_id;
                    $newKeywordObject->object_type = 'coupon';
                    $newKeywordObject->save();
                }
                $updatedcoupon->keywords = $couponKeywords;
            });

            // Update product tags
            $deleted_product_tags_object = ProductTagObject::where('object_id', '=', $promotion_id)
                                                    ->where('object_type', '=', 'coupon');
            $deleted_product_tags_object->delete();

            OrbitInput::post('product_tags', function($productTags) use ($updatedcoupon, $user, $promotion_id) {
                // Insert new data
                $couponProductTags = array();
                foreach ($productTags as $productTag) {
                    $product_tag_id = null;

                    $existProductTag = ProductTag::excludeDeleted()
                        ->where('product_tag', '=', $productTag)
                        ->where('merchant_id', '=', 0)
                        ->first();

                    if (empty($existProductTag)) {
                        $newProductTag = new ProductTag();
                        $newProductTag->merchant_id = 0;
                        $newProductTag->product_tag = $productTag;
                        $newProductTag->status = 'active';
                        $newProductTag->created_by = $user->user_id;
                        $newProductTag->modified_by = $user->user_id;
                        $newProductTag->save();

                        $product_tag_id = $newProductTag->product_tag_id;
                        $couponProductTags[] = $newProductTag;
                    } else {
                        $product_tag_id = $existProductTag->product_tag_id;
                        $couponProductTags[] = $existProductTag;
                    }

                    $newProductTagObject = new ProductTagObject();
                    $newProductTagObject->product_tag_id = $product_tag_id;
                    $newProductTagObject->object_id = $promotion_id;
                    $newProductTagObject->object_type = 'coupon';
                    $newProductTagObject->save();
                }
                $updatedcoupon->product_tags = $couponProductTags;
            });

            $tempContent = new TemporaryContent();
            $tempContent->contents = serialize($beforeUpdatedCoupon);
            $tempContent->save();

            Event::fire('orbit.coupon.postupdatecoupon.after.save', array($this, $updatedcoupon));
            Event::fire('orbit.coupon.postupdatecoupon-mallnotification.after.save', array($this, $updatedcoupon));

            OrbitInput::post('translations', function($translation_json_string) use ($updatedcoupon, $mallid, $is_3rd_party_promotion) {
                $is_third_party =  FALSE;
                $this->validateAndSaveTranslations($updatedcoupon, $translation_json_string, 'create', $is_third_party);
            });

            // Default language for pmp_account is required
            $malls = implode("','", $mallid);
            $prefix = DB::getTablePrefix();
                        $isAvailable = CouponTranslation::where('promotion_id', '=', $promotion_id)
                                        ->whereRaw("
                                            {$prefix}coupon_translations.merchant_language_id = (
                                                SELECT language_id
                                                FROM {$prefix}languages
                                                WHERE name = (SELECT mobile_default_language FROM {$prefix}campaign_account WHERE user_id = {$this->quote($this->api->user->user_id)})
                                            )
                                        ")
                                        ->where(function($query) {
                                            $query->where('promotion_name', '=', '')
                                                  ->orWhere('description', '=', '')
                                                  ->orWhereNull('promotion_name')
                                                  ->orWhereNull('description');
                                          })
                                        ->first();

            $required_name = false;
            $required_desc = false;

            if (is_object($isAvailable)) {
                if ($val->promotion_name === '' || empty($val->promotion_name)) {
                    $required_name = true;
                }
                if ($val->description === '' || empty($val->description)) {
                    $required_desc = true;
                }
            }

            if ($required_name === true && $required_desc === true) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_both', ['type' => 'coupon']);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } elseif ($required_name) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_name', ['type' => 'coupon']);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } elseif ($required_desc) {
                $errorMessage = Lang::get('validation.orbit.empty.default_language_desc', ['type' => 'coupon']);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // update coupon advert
            if (! empty($campaignStatus) || $campaignStatus !== '') {
                $couponAdverts = Advert::excludeDeleted()
                                    ->where('link_object_id', $updatedcoupon->promotion_id)
                                    ->update(['status'     => $updatedcoupon->status]);
            }

            if (! empty($end_date) || $end_date !== '') {
                $couponAdverts = Advert::excludeDeleted()
                                    ->where('link_object_id', $updatedcoupon->promotion_id)
                                    ->where('end_date', '>', $updatedcoupon->end_date)
                                    ->update(['end_date' => $updatedcoupon->end_date]);
            }

            $this->response->data = $updatedcoupon;

            // Commit the changes
            $this->commit();

            // Push notification
            Event::fire('orbit.coupon.postupdatecoupon-storenotificationupdate.after.commit', array($this, $updatedcoupon));

            // Successfull Update
            $activityNotes = sprintf('Coupon updated: %s', $updatedcoupon->promotion_name);
            $activity->setUser($user)
                    ->setActivityName('update_coupon')
                    ->setActivityNameLong('Update Coupon OK')
                    ->setObject($updatedcoupon)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.coupon.postupdatecoupon.after.commit', array($this, $updatedcoupon, $tempContent->temporary_content_id));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.postupdatecoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_coupon')
                    ->setActivityNameLong('Update Coupon Failed')
                    ->setObject($updatedcoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.postupdatecoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_coupon')
                    ->setActivityNameLong('Update Coupon Failed')
                    ->setObject($updatedcoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.postupdatecoupon.query.error', array($this, $e));

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

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_coupon')
                    ->setActivityNameLong('Update Coupon Failed')
                    ->setObject($updatedcoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            Event::fire('orbit.coupon.postupdatecoupon.custom.exception', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getCustomData();
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();

        } catch (Exception $e) {
            Event::fire('orbit.coupon.postupdatecoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = [$e->getMessage(), $e->getFile(), $e->getLine()];

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_coupon')
                    ->setActivityNameLong('Update Coupon Failed')
                    ->setObject($updatedcoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    public function getSearchGiftNCoupon()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.coupon.getsearchcoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.coupon.getsearchcoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.coupon.getsearchcoupon.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.coupon.getsearchcoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $keywords = OrbitInput::get('keywords');
            $currentmall = OrbitInput::get('current_mall');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                    'keywords' => $keywords,
                    'current_mall' => $currentmall
                ),
                array(
                    'sort_by' => 'in:registered_date,promotion_name,promotion_type,total_location,description,begin_date,end_date,status,is_permanent,rule_type,tenant_name,is_auto_issuance,display_discount_value,updated_at,coupon_status',
                    'keywords' => 'min:3',
                    'current_mall' => 'orbit.empty.merchant'
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.coupon_sortby'),
                )
            );

            Event::fire('orbit.coupon.getsearchcoupon.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.coupon.getsearchcoupon.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.coupon.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.coupon.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $table_prefix = DB::getTablePrefix();

            $filterName = OrbitInput::get('promotion_name_like', '');

            $mediaJoin = "";
            $mediaOptimize = " AND (object_name = 'coupon_translation') ";
            $mediaObjectIds = (array) OrbitInput::get('promotion_id', []);
            if (! empty ($mediaObjectIds)) {
                $mediaObjectIds = "'" . implode("', '", $mediaObjectIds) . "'";
                $mediaJoin = " LEFT JOIN {$table_prefix}coupon_translations mont ON mont.coupon_translation_id = {$table_prefix}media.object_id ";
                $mediaOptimize = " AND object_name = 'coupon_translation' AND mont.promotion_id IN ({$mediaObjectIds}) ";
            }

            // Builder object
            // Addition select case and join for sorting by discount_value.
            $coupons = Coupon::allowedForPMPUser($user, 'coupon')
                //->with('couponRule')
                ->select(
                    DB::raw("{$table_prefix}promotions.promotion_id,
                             {$table_prefix}coupon_translations.promotion_name AS promotion_name,
                             {$table_prefix}promotions.promotion_type,
                             {$table_prefix}promotions.description,
                             {$table_prefix}promotions.begin_date,
                             {$table_prefix}promotions.end_date,
                             media.path as image_path,
                    {$table_prefix}promotions.promotion_id as campaign_id, 'coupon' as campaign_type, {$table_prefix}coupon_translations.promotion_name AS display_name,
                    CASE WHEN {$table_prefix}campaign_status.campaign_status_name = 'expired' THEN {$table_prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$table_prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                                FROM {$table_prefix}merchants om
                                                                                LEFT JOIN {$table_prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                                WHERE om.merchant_id = {$table_prefix}promotions.merchant_id)
                    THEN 'expired' ELSE {$table_prefix}campaign_status.campaign_status_name END) END AS campaign_status,

                    CASE WHEN {$table_prefix}campaign_status.campaign_status_name = 'expired' THEN {$table_prefix}campaign_status.order ELSE (CASE WHEN {$table_prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                                FROM {$table_prefix}merchants om
                                                                                LEFT JOIN {$table_prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                                WHERE om.merchant_id = {$table_prefix}promotions.merchant_id)
                    THEN 5 ELSE {$table_prefix}campaign_status.order END) END AS campaign_status_order,
                    {$table_prefix}campaign_status.order
                    "),
                    // DB::raw("(select GROUP_CONCAT(IF({$table_prefix}merchants.object_type = 'tenant', CONCAT({$table_prefix}merchants.name,' at ', pm.name), CONCAT('Mall at ',{$table_prefix}merchants.name)) separator ', ') from {$table_prefix}promotion_retailer
                    //                 inner join {$table_prefix}merchants on {$table_prefix}merchants.merchant_id = {$table_prefix}promotion_retailer.retailer_id
                    //                 inner join {$table_prefix}merchants pm on {$table_prefix}merchants.parent_id = pm.merchant_id
                    //                 where {$table_prefix}promotion_retailer.promotion_id = {$table_prefix}promotions.promotion_id) as campaign_location_names"),
                    //DB::raw("CASE {$table_prefix}promotion_rules.rule_type WHEN 'auto_issue_on_signup' THEN 'Y' ELSE 'N' END as 'is_auto_issue_on_signup'"),
                    DB::raw("CASE WHEN {$table_prefix}promotions.end_date IS NOT NULL THEN
                        CASE WHEN
                            DATE_FORMAT({$table_prefix}promotions.end_date, '%Y-%m-%d %H:%i:%s') = '0000-00-00 00:00:00' THEN {$table_prefix}promotions.status
                        WHEN
                            {$table_prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name)
                                                                    FROM {$table_prefix}merchants om
                                                                    LEFT JOIN {$table_prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                    WHERE om.merchant_id = {$table_prefix}promotions.merchant_id)
                        THEN 'expired'
                        ELSE
                            {$table_prefix}promotions.status
                        END
                    ELSE
                        {$table_prefix}promotions.status
                    END as 'status'"),
                    // DB::raw("COUNT(DISTINCT {$table_prefix}promotion_retailer.promotion_retailer_id) as total_location"),
                    // DB::raw("(SELECT GROUP_CONCAT(url separator '\n')
                    //     FROM {$table_prefix}issued_coupons ic
                    //     WHERE ic.promotion_id = {$table_prefix}promotions.promotion_id
                    //         ) as shortlinks"),
                    DB::raw("IF({$table_prefix}promotions.is_all_gender = 'Y', 'A', {$table_prefix}promotions.is_all_gender) as gender"),
                    DB::raw("{$table_prefix}promotions.max_quantity_per_purchase as max_qty_per_purchase"),
                    DB::raw("{$table_prefix}promotions.max_quantity_per_user as max_qty_per_user")
                )
                ->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                ->leftJoin('promotion_retailer', 'promotion_retailer.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                ->leftJoin('languages', 'languages.language_id', '=', 'coupon_translations.merchant_language_id')
                ->leftJoin(DB::raw("(
                        SELECT {$table_prefix}media.* FROM {$table_prefix}media
                        {$mediaJoin}
                        WHERE media_name_long = 'coupon_translation_image_resized_default'
                        {$mediaOptimize} ) as media
                    "), DB::raw('media.object_id'), '=', 'coupon_translations.coupon_translation_id')
                ->groupBy('promotions.promotion_id')
                ->where('promotion_type', 'gift_n_coupon');


            if($filterName === '') {
                // handle role campaign admin cause not join with campaign account
                if ($role->role_name === 'Campaign Admin' ) {
                    $coupons->where('languages.name', '=', DB::raw("(select ca.mobile_default_language from {$table_prefix}campaign_account ca where ca.user_id = {$this->quote($user->user_id)})"));
                } else {
                    $coupons->where('languages.name', '=', DB::raw("ca.mobile_default_language"));
                }
            }

            if (strtolower($user->role->role_name) === 'mall customer service') {
                $now = date('Y-m-d H:i:s');
                if (! empty($currentmall)) {
                    $mall = App::make('orbit.empty.merchant');
                    $now = Carbon::now($mall->timezone->timezone_name);
                }
                $prefix = DB::getTablePrefix();
                $coupons->whereRaw("('$now' >= {$prefix}promotions.begin_date and '$now' <= {$prefix}promotions.end_date)");
                $coupons->whereRaw("(((select count({$prefix}issued_coupons.promotion_id) from {$prefix}issued_coupons
                                        where {$prefix}issued_coupons.promotion_id={$prefix}promotions.promotion_id
                                        and status!='deleted') < {$prefix}promotions.maximum_issued_coupon) or
                                    ({$prefix}promotions.maximum_issued_coupon = 0 or {$prefix}promotions.maximum_issued_coupon is null))");
                $coupons->active('promotions');
            } else {
                $coupons->excludeDeleted('promotions');
            }

            // Filter coupon by Ids
            OrbitInput::get('promotion_id', function($promotionIds) use ($coupons)
            {
                $coupons->whereIn('promotions.promotion_id', (array)$promotionIds);
            });

            // Filter coupon by promotion name
            OrbitInput::get('promotion_name', function($promotionName) use ($coupons)
            {
                $coupons->where('promotions.promotion_name', '=', $promotionName);
            });

            // Filter coupon by matching promotion name pattern
            OrbitInput::get('promotion_name_like', function($promotionName) use ($coupons)
            {
                $coupons->where('coupon_translations.promotion_name', 'like', "%$promotionName%");
            });

            // Filter coupon by keywords for advert link to
            OrbitInput::get('keywords', function($keywords) use ($coupons)
            {
                $coupons->where('coupon_translations.promotion_name', 'like', "$keywords%");
            });

            // Filter coupon by description
            OrbitInput::get('description', function($description) use ($coupons)
            {
                $coupons->whereIn('promotions.description', $description);
            });

            // Filter coupon by matching description pattern
            OrbitInput::get('description_like', function($description) use ($coupons)
            {
                $coupons->where('promotions.description', 'like', "%$description%");
            });

            // Filter coupon by long description
            OrbitInput::get('long_description', function($long_description) use ($coupons)
            {
                $coupons->whereIn('promotions.long_description', $long_description);
            });

            // Filter coupon by matching long_description pattern
            OrbitInput::get('long_description_like', function($long_description) use ($coupons)
            {
                $coupons->where('promotions.long_description', 'like', "%$long_description%");
            });

            // Filter coupon by date
            OrbitInput::get('end_date', function($endDate) use ($coupons)
            {
                $coupons->where('promotions.begin_date', '<=', $endDate);
            });

            // Filter coupon by date
            OrbitInput::get('begin_date', function($begindate) use ($coupons)
            {
                $coupons->where('promotions.end_date', '>=', $begindate);
            });

            // Filter coupon by is permanent
            OrbitInput::get('is_permanent', function ($isPermanent) use ($coupons) {
                $coupons->whereIn('promotions.is_permanent', $isPermanent);
            });

            // Filter coupon by coupon notification
            OrbitInput::get('coupon_notification', function ($couponNotification) use ($coupons) {
                $coupons->whereIn('promotions.coupon_notification', $couponNotification);
            });

            // Filter coupons by campaign status
            OrbitInput::get('campaign_status', function ($statuses) use ($coupons, $table_prefix) {
                $coupons->whereIn(DB::raw("CASE WHEN {$table_prefix}campaign_status.campaign_status_name = 'expired' THEN {$table_prefix}campaign_status.campaign_status_name ELSE (CASE WHEN {$table_prefix}promotions.end_date < (SELECT CONVERT_TZ(UTC_TIMESTAMP(),'+00:00', ot.timezone_name) FROM {$table_prefix}merchants om
                                                                LEFT JOIN {$table_prefix}timezones ot on ot.timezone_id = om.timezone_id
                                                                WHERE om.merchant_id = {$table_prefix}promotions.merchant_id)
                    THEN 'expired' ELSE {$table_prefix}campaign_status.campaign_status_name END) END"), $statuses);
            });

            // Filter coupons by status
            OrbitInput::get('status', function ($statuses) use ($coupons, $table_prefix) {
                $arrayStatus = implode("','", (array)$statuses);
                $coupons->havingRaw("(status in('{$arrayStatus}'))");
            });

            // Filter coupon by retailer id
            OrbitInput::get('retailer_id', function ($retailerIds) use ($coupons) {
                $coupons->whereHas('tenants', function($q) use ($retailerIds) {
                    $q->whereIn('retailer_id', $retailerIds);
                });
            });

            // Filter
            OrbitInput::get('retailer_name', function ($name) use ($coupons) {
                $coupons->where('merchants.merchant_name', 'like', "%$name%");
            });

            // Filter coupon by rule begin date
            OrbitInput::get('rule_begin_date', function ($beginDate) use ($coupons)
            {
                $coupons->whereHas('couponrule', function ($q) use ($beginDate) {
                    $q->where('rule_begin_date', '<=', $beginDate);
                });
            });

            // Filter coupon by end date
            OrbitInput::get('rule_end_date', function ($endDate) use ($coupons)
            {
                $coupons->whereHas('couponrule', function ($q) use ($endDate) {
                    $q->where('rule_end_date', '>=', $endDate);
                });
            });

            $from_cs = OrbitInput::get('from_cs', 'no');

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($coupons, $from_cs) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'mall') {
                        $coupons->with('mall');
                    } elseif ($relation === 'tenants') {
                        if ($from_cs === 'yes') {
                            $coupons->with(array('tenants' => function($q) {
                                $q->where('merchants.status', 'active');
                            }));
                        } else {
                            $coupons->with('tenants');
                        }
                    } elseif ($relation === 'tenants.mall') {
                        if ($from_cs === 'yes') {
                            $coupons->with(array('tenants' => function($q) {
                                $q->where('merchants.status', 'active');
                                $q->with('mall');
                            }));
                        } else {
                            $coupons->with('tenants.mall');
                        }
                    } elseif ($relation === 'translations') {
                        $coupons->with('translations');
                    } elseif ($relation === 'translations.media') {
                        $coupons->with('translations.media');
                    } elseif ($relation === 'employee') {
                        $coupons->with('employee.employee.retailers');
                    } elseif ($relation === 'link_to_tenants') {
                        $coupons->with('linkToTenants');
                    } elseif ($relation === 'link_to_tenants.mall') {
                        if ($from_cs === 'yes') {
                            $coupons->with(array('linkToTenants' => function($q) {
                                $q->where('merchants.status', 'active');
                                $q->with('mall');
                            }));
                        } else {
                            $coupons->with('linkToTenants.mall');
                        }
                    } elseif ($relation === 'campaignLocations') {
                        $coupons->with('campaignLocations');
                    } elseif ($relation === 'campaignLocations.mall') {
                        $coupons->with('campaignLocations.mall');
                    } elseif ($relation === 'keywords') {
                        $coupons->with('keywords');
                    } elseif ($relation === 'product_tags') {
                        $coupons->with('product_tags');
                    } elseif ($relation === 'campaignObjectPartners') {
                        $coupons->with('campaignObjectPartners');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_coupons = clone $coupons;

            if (! $this->returnBuilder) {
                // Get the take args
                $take = $perPage;
                OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;

                    if ((int)$take <= 0) {
                        $take = $maxRecord;
                    }
                });
                $coupons->take($take);

                $skip = 0;
                OrbitInput::get('skip', function($_skip) use (&$skip, $coupons)
                {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                });
                $coupons->skip($skip);
            }

            // Default sort by
            $sortBy = 'coupon_translations.promotion_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'        => 'promotions.created_at',
                    'promotion_name'         => 'coupon_translations.promotion_name',
                    'promotion_type'         => 'promotions.promotion_type',
                    'description'            => 'promotions.description',
                    'begin_date'             => 'promotions.begin_date',
                    'end_date'               => 'promotions.end_date',
                    'updated_at'             => 'promotions.updated_at',
                    'is_permanent'           => 'promotions.is_permanent',
                    'status'                 => 'campaign_status_order',
                    'rule_type'              => 'rule_type',
                    'total_location'         => 'total_location',
                    'tenant_name'            => 'tenant_name',
                    'is_auto_issuance'       => 'is_auto_issue_on_signup',
                    'display_discount_value' => 'display_discount_value',
                    'coupon_status'          => 'coupon_status',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            $coupons->orderBy($sortBy, $sortMode);

            //with name
            if ($sortBy !== 'coupon_translations.promotion_name') {
                if ($sortBy === 'campaign_status_order') {
                    $coupons->orderBy('promotions.updated_at', 'desc');
                }
                else {
                    $coupons->orderBy('coupon_translations.promotion_name', 'asc');
                }
            }

            $totalCoupons = RecordCounter::create($_coupons)->count();
            $listOfCoupons = $coupons->get();
            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return ['builder' => $coupons, 'count' => $totalCoupons];
            }

            $data = new stdclass();
            $data->total_records = $totalCoupons;
            $data->returned_records = count($listOfCoupons);
            $data->records = $listOfCoupons;

            if ($totalCoupons === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.getsearchcoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.getsearchcoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.getsearchcoupon.query.error', array($this, $e));

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
            Event::fire('orbit.coupon.getsearchcoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.coupon.getsearchcoupon.before.render', array($this, &$output));

        return $output;
    }

    public function getDetailGiftNCoupon()
    {
        $user = NULL;
        try {
            $httpCode = 200;

            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponViewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $promotion_id = OrbitInput::get('promotion_id');

            $validator = Validator::make(
                array(
                    'promotion_id' => $promotion_id,
                ),
                array(
                    'promotion_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $table_prefix = DB::getTablePrefix();
            $coupon = Coupon::allowedForPMPUser($user, 'coupon')
                                ->select('promotions.promotion_id',
                                         'promotions.promotion_name',
                                         'promotions.description',
                                         'promotions.how_to_buy_and_redeem',
                                         'promotions.begin_date',
                                         'promotions.end_date',
                                         'promotions.price_to_gtm',
                                         'promotions.price_old as price_value',
                                         'promotions.price_selling',
                                         'promotions.maximum_issued_coupon',
                                         'promotions.max_quantity_per_purchase',
                                         'promotions.max_quantity_per_user',
                                         'promotions.is_exclusive',
                                         DB::raw("(SELECT GROUP_CONCAT(IF({$table_prefix}merchants.object_type = 'tenant', CONCAT({$table_prefix}merchants.name,' at ', pm.name), CONCAT('Mall at ',{$table_prefix}merchants.name)) separator ', ') from {$table_prefix}promotion_retailer
                                                    inner join {$table_prefix}merchants on {$table_prefix}merchants.merchant_id = {$table_prefix}promotion_retailer.retailer_id
                                                    inner join {$table_prefix}merchants pm on {$table_prefix}merchants.parent_id = pm.merchant_id
                                                    where {$table_prefix}promotion_retailer.promotion_id = {$table_prefix}promotions.promotion_id) as campaign_location_names"),
                                         DB::raw("(SELECT COUNT(*)
                                                    FROM {$table_prefix}issued_coupons ic
                                                    WHERE ic.promotion_id = {$table_prefix}promotions.promotion_id
                                                        ) as total_shortlinks"),
                                         DB::raw("CASE WHEN
                                                        (SELECT COUNT(*) FROM {$table_prefix}issued_coupons ic
                                                         WHERE ic.promotion_id = {$table_prefix}promotions.promotion_id) > 1
                                                   THEN
                                                         (SELECT CONCAT((SELECT url FROM {$table_prefix}issued_coupons WHERE {$table_prefix}issued_coupons.promotion_id = {$table_prefix}promotions.promotion_id ORDER BY issued_coupon_id ASC LIMIT 1), '\n',
                                                                        (SELECT url FROM {$table_prefix}issued_coupons WHERE {$table_prefix}issued_coupons.promotion_id = {$table_prefix}promotions.promotion_id ORDER BY issued_coupon_id DESC LIMIT 1)))
                                                   ELSE
                                                         (SELECT url FROM {$table_prefix}issued_coupons
                                                         WHERE {$table_prefix}issued_coupons.promotion_id = {$table_prefix}promotions.promotion_id)
                                                   END as shortlinks"),
                                         DB::raw("IF({$table_prefix}promotions.is_all_gender = 'Y', 'A', {$table_prefix}promotions.is_all_gender) as gender"),
                                         'promotions.coupon_validity_in_date',
                                         'promotions.status'
                                  )
                                ->with('translations.media', 'keywords', 'product_tags', 'campaignObjectPartners')
                                ->leftJoin('coupon_translations', 'coupon_translations.promotion_id', '=', 'promotions.promotion_id')
                                ->where('promotions.promotion_id', $promotion_id)
                                ->where('promotions.promotion_type', Coupon::TYPE_GIFTNCOUPON)
                                ->firstOrFail();

            $this->response->data = $coupon;

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
            $this->response->data = $e->getLine();
        }

        return $this->render($httpCode);
    }


    protected function registerCustomValidation()
    {
        // Check maximal of total issued coupons
        Validator::extend('orbit.max.total_issued_coupons', function ($attribute, $value, $parameters) {
            $promotion_id = $parameters[0];

            $total_issued_coupons = IssuedCoupon::where('promotion_id', '=', $promotion_id)
                                                ->count();

            if (! empty($value) && $value < $total_issued_coupons) {
                return FALSE;
            }

            App::instance('orbit.max.total_issued_coupons', $total_issued_coupons);

            return TRUE;
        });

        // Check the existance of id_language_default
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $news = Language::where('language_id', '=', $value)
                             ->first();

            if (empty($news)) {
                return FALSE;
            }

            App::instance('orbit.empty.language_default', $news);

            return TRUE;
        });

        // Mall deletion master password
        Validator::extend('orbit.masterpassword.delete', function ($attribute, $value, $parameters) {
            // Current Mall ID
            $currentMall = $parameters[0];

            // Get the master password from settings table
            $masterPassword = Setting::getMasterPasswordFor($currentMall);

            if (! is_object($masterPassword)) {
                // @Todo replace with language
                $message = 'The master password is not set.';
                ACL::throwAccessForbidden($message);
            }

            if (! Hash::check($value, $masterPassword->setting_value)) {
                $message = 'The master password is incorrect.';
                ACL::throwAccessForbidden($message);
            }

            return TRUE;
        });

        // Check the existance of coupon id
        Validator::extend('orbit.empty.coupon', function ($attribute, $value, $parameters) {
            $coupon = Coupon::excludeStoppedOrExpired('promotions')
                        ->where('promotion_id', $value)
                        ->first();

            if (empty($coupon)) {
                return FALSE;
            }

            App::instance('orbit.empty.coupon', $coupon);

            return TRUE;
        });

        // Check the existance of coupon id for update with permission check
        Validator::extend('orbit.update.coupon', function ($attribute, $value, $parameters) {
            $user = $this->api->user;

            $coupon = Coupon::allowedForPMPUser($user, 'coupon')->excludeStoppedOrExpired('promotions')
                        ->where('promotion_id', $value)
                        ->first();

            if (empty($coupon)) {
                return FALSE;
            }

            App::instance('orbit.update.coupon', $coupon);

            return TRUE;
        });

        // Check the existance of issued coupon id
        $user = $this->api->user;
        Validator::extend('orbit.empty.issuedcoupon', function ($attribute, $value, $parameters) use ($user) {
            $now = date('Y-m-d H:i:s');
            $number = OrbitInput::post('merchant_verification_number');
            $mall_id = OrbitInput::post('current_mall');
            $storeId = OrbitInput::post('store_id');

            $prefix = DB::getTablePrefix();

            $issuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed', 'available'])
                        ->where('issued_coupons.issued_coupon_id', $value)
                        ->where('issued_coupons.user_id', $user->user_id)
                        // ->whereRaw("({$prefix}issued_coupons.expired_date >= ? or {$prefix}issued_coupons.expired_date is null)", [$now])
                        ->with('coupon')
                        ->whereHas('coupon', function($q) use($now) {
                            $q->where('promotions.status', 'active');
                            $q->where('promotions.coupon_validity_in_date', '>=', $now);
                        })
                        ->first();

            if (empty($issuedCoupon)) {
                $errorMessage = sprintf('Issued coupon ID %s is not found.', htmlentities($value));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            //Checking verification number in cs and tenant verification number
            //Checking in tenant verification number first
            if ($issuedCoupon->coupon->is_all_retailer === 'Y') {
                $checkIssuedCoupon = Tenant::where('merchant_id','=', $storeId)
                            ->where('status', 'active')
                            ->where('masterbox_number', $number)
                            ->first();
            } elseif ($issuedCoupon->coupon->is_all_retailer === 'N') {
                $checkIssuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed', 'available'])
                            ->join('promotion_retailer_redeem', 'promotion_retailer_redeem.promotion_id', '=', 'issued_coupons.promotion_id')
                            ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer_redeem.retailer_id')
                            ->where('issued_coupons.issued_coupon_id', $value)
                            ->where('issued_coupons.user_id', $user->user_id)
                            // ->whereRaw("({$prefix}issued_coupons.expired_date >= ? or {$prefix}issued_coupons.expired_date is null)", [$now])
                            ->whereHas('coupon', function($q) use($now) {
                                $q->where('promotions.status', 'active');
                                $q->where('promotions.coupon_validity_in_date', '>=', $now);
                            })
                            ->where('merchants.merchant_id', $storeId)
                            ->where('merchants.masterbox_number', $number)
                            ->first();
            }

            // Continue checking to tenant verification number
            if (empty($checkIssuedCoupon)) {
                // Checking cs verification number
                if ($issuedCoupon->coupon->is_all_employee === 'Y') {
                    $checkIssuedCoupon = UserVerificationNumber::
                                join('users', 'users.user_id', '=', 'user_verification_numbers.user_id')
                                ->where('status', 'active')
                                ->where('merchant_id', $mall_id)
                                ->where('verification_number', $number)
                                ->first();
                } elseif ($issuedCoupon->coupon->is_all_employee === 'N') {
                    $checkIssuedCoupon = IssuedCoupon::whereNotIn('issued_coupons.status', ['deleted', 'redeemed', 'available'])
                                ->join('promotion_employee', 'promotion_employee.promotion_id', '=', 'issued_coupons.promotion_id')
                                ->join('user_verification_numbers', 'user_verification_numbers.user_id', '=', 'promotion_employee.user_id')
                                ->join('employees', 'employees.user_id', '=', 'user_verification_numbers.user_id')
                                ->where('employees.status', 'active')
                                ->where('issued_coupons.issued_coupon_id', $value)
                                ->where('issued_coupons.user_id', $user->user_id)
                                // ->whereRaw("({$prefix}issued_coupons.expired_date >= ? or {$prefix}issued_coupons.expired_date is null)", [$now])
                                ->whereHas('coupon', function($q) use($now) {
                                    $q->where('promotions.status', 'active');
                                    $q->where('promotions.coupon_validity_in_date', '>=', $now);
                                })
                                ->where('user_verification_numbers.verification_number', $number)
                                ->first();
                }
            }

            if (! isset($checkIssuedCoupon) || empty($checkIssuedCoupon)) {
                $errorMessage = Lang::get('mobileci.coupon.wrong_verification_number');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (! empty($checkIssuedCoupon)) {
                App::instance('orbit.empty.issuedcoupon', $issuedCoupon);
            }

            return TRUE;
        });

        // Check the existance of merchant id
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) {
            $merchant = Mall::with('timezone')->excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Check the existence of the coupon status
        Validator::extend('orbit.empty.coupon_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the coupon rule type
        Validator::extend('orbit.empty.coupon_rule_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array(
                            'auto_issue_on_signup',
                            'auto_issue_on_first_signin',
                            'auto_issue_on_every_signin',
                            'blast_via_sms',
                            'unique_coupon_per_user'
                        );
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the link status
        Validator::extend('orbit.empty.status_link_to', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('Y', 'N');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the coupon type
        Validator::extend('orbit.empty.coupon_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $couponTypes = array('mall', 'tenant');
            foreach ($couponTypes as $couponType) {
                if($value === $couponType) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the rule type
        Validator::extend('orbit.empty.rule_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $ruletypes = array('cart_discount_by_value', 'cart_discount_by_percentage', 'new_product_price', 'product_discount_by_value', 'product_discount_by_percentage');
            foreach ($ruletypes as $ruletype) {
                if($value === $ruletype) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the rule object type
        Validator::extend('orbit.empty.rule_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $ruleobjecttypes = array('product', 'family');
            foreach ($ruleobjecttypes as $ruleobjecttype) {
                if($value === $ruleobjecttype) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existance of rule_object_id1
        Validator::extend('orbit.empty.rule_object_id1', function ($attribute, $value, $parameters) {
            $ruleobjecttype = trim(OrbitInput::post('rule_object_type'));
            if ($ruleobjecttype === 'product') {
                $rule_object_id1 = Product::excludeDeleted()
                        ->where('product_id', $value)
                        ->first();
            } elseif ($ruleobjecttype === 'family') {
                $rule_object_id1 = Category::excludeDeleted()
                        ->where('category_id', $value)
                        ->first();
            }

            if (empty($rule_object_id1)) {
                return FALSE;
            }

            App::instance('orbit.empty.rule_object_id1', $rule_object_id1);

            return TRUE;
        });

        // Check the existance of rule_object_id2
        Validator::extend('orbit.empty.rule_object_id2', function ($attribute, $value, $parameters) {
            $rule_object_id2 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($rule_object_id2)) {
                return FALSE;
            }

            App::instance('orbit.empty.rule_object_id2', $rule_object_id2);

            return TRUE;
        });

        // Check the existance of rule_object_id3
        Validator::extend('orbit.empty.rule_object_id3', function ($attribute, $value, $parameters) {
            $rule_object_id3 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($rule_object_id3)) {
                return FALSE;
            }

            App::instance('orbit.empty.rule_object_id3', $rule_object_id3);

            return TRUE;
        });

        // Check the existance of rule_object_id4
        Validator::extend('orbit.empty.rule_object_id4', function ($attribute, $value, $parameters) {
            $rule_object_id4 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($rule_object_id4)) {
                return FALSE;
            }

            App::instance('orbit.empty.rule_object_id4', $rule_object_id4);

            return TRUE;
        });

        // Check the existance of rule_object_id5
        Validator::extend('orbit.empty.rule_object_id5', function ($attribute, $value, $parameters) {
            $rule_object_id5 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($rule_object_id5)) {
                return FALSE;
            }

            App::instance('orbit.empty.rule_object_id5', $rule_object_id5);

            return TRUE;
        });

        // Check the existence of the discount object type
        Validator::extend('orbit.empty.discount_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $discountobjecttypes = array('product', 'family', 'cash_rebate');
            foreach ($discountobjecttypes as $discountobjecttype) {
                if($value === $discountobjecttype) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existance of discount_object_id1
        Validator::extend('orbit.empty.discount_object_id1', function ($attribute, $value, $parameters) {
            $discountobjecttype = trim(OrbitInput::post('discount_object_type'));
            if ($discountobjecttype === 'product') {
                $discount_object_id1 = Product::excludeDeleted()
                        ->where('product_id', $value)
                        ->first();
            } elseif ($discountobjecttype === 'family') {
                $discount_object_id1 = Category::excludeDeleted()
                        ->where('category_id', $value)
                        ->first();
            }

            if (empty($discount_object_id1)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id1', $discount_object_id1);

            return TRUE;
        });

        // Check the existance of discount_object_id2
        Validator::extend('orbit.empty.discount_object_id2', function ($attribute, $value, $parameters) {
            $discount_object_id2 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id2)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id2', $discount_object_id2);

            return TRUE;
        });

        // Check the existance of discount_object_id3
        Validator::extend('orbit.empty.discount_object_id3', function ($attribute, $value, $parameters) {
            $discount_object_id3 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id3)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id3', $discount_object_id3);

            return TRUE;
        });

        // Check the existance of discount_object_id4
        Validator::extend('orbit.empty.discount_object_id4', function ($attribute, $value, $parameters) {
            $discount_object_id4 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id4)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id4', $discount_object_id4);

            return TRUE;
        });

        // Check the existance of discount_object_id5
        Validator::extend('orbit.empty.discount_object_id5', function ($attribute, $value, $parameters) {
            $discount_object_id5 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id5)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id5', $discount_object_id5);

            return TRUE;
        });

        // Check the existance of tenant id
        Validator::extend('orbit.empty.tenant', function ($attribute, $value, $parameters) {
            $tenant = Tenant::excludeDeleted()
                                ->where('merchant_id', $value)
                                ->first();

            if (empty($tenant)) {
                return FALSE;
            }

            App::instance('orbit.empty.tenant', $tenant);

            return TRUE;
        });

        // Check the existance of employee id
        Validator::extend('orbit.empty.employee', function ($attribute, $value, $parameters) {
            $employee = Employee::excludeDeleted()
                                ->where('user_id', $value)
                                ->first();

            if (empty($employee)) {
                return FALSE;
            }

            App::instance('orbit.empty.employee', $employee);

            return TRUE;
        });

        // Check the existance of retailer id
        Validator::extend('orbit.issuedcoupon.exists', function ($attribute, $value, $parameters) {
            $coupon = IssuedCoupon::active()->where('promotion_id', $value)->count();

            if ($coupon > 0) {
                $message = sprintf('Can not delete coupon since there is still %s issued coupon which not redeemed yet.', $coupon);
                ACL::throwAccessForbidden($message);
            }

            return TRUE;
        });

        Validator::extend('orbit.empty.is_all_age', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('Y', 'N');

            if (in_array($value, $statuses)) {
                $valid = true;
            }

            return $valid;
        });

        Validator::extend('orbit.empty.is_all_gender', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('Y', 'N');

            if (in_array($value, $statuses)) {
                $valid = true;
            }

            return $valid;
        });

        Validator::extend('orbit.empty.gender', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('M', 'F', 'U');

            if (in_array($value, $statuses)) {
                $valid = true;
            }

            return $valid;
        });

        Validator::extend('orbit.empty.age', function ($attribute, $value, $parameters) {
            $exist = AgeRange::excludeDeleted()
                        ->where('age_range_id', $value)
                        ->first();

            if (empty($exist)) {
                return false;
            }

            App::instance('orbit.empty.age', $exist);

            return true;
        });

        // check the partner exclusive or not if the is_exclusive is set to 'Y'
        Validator::extend('orbit.empty.exclusive_partner', function ($attribute, $value, $parameters) {
            $flag_exclusive = false;
            $is_exclusive = OrbitInput::post('is_exclusive');
            $partner_ids = OrbitInput::post('partner_ids');
            $partner_ids = (array) $partner_ids;

            $partner_exclusive = Partner::select('is_exclusive', 'status')
                           ->whereIn('partner_id', $partner_ids)
                           ->get();

            foreach ($partner_exclusive as $exclusive) {
                if ($exclusive->is_exclusive == 'Y' && $exclusive->status == 'active') {
                    $flag_exclusive = true;
                }
            }

            $valid = true;

            if ($is_exclusive == 'Y') {
                if ($flag_exclusive) {
                    $valid = true;
                } else {
                    $valid = false;
                }
            }

            return $valid;
        });

        Validator::extend('orbit.check.issued_coupon', function ($attribute, $value, $parameters) {
            $valid = true;
            if (strtolower($value) === 'stopped') {
                $couponId = OrbitInput::post('promotion_id');
                $couponIssued = IssuedCoupon::where('promotion_id', '=', $couponId)->where('status', '=', 'issued')->first();
                $valid = ($couponIssued) ? false : true;
            }

            return $valid;
        });

        Validator::extend('orbit.unique.token', function ($attribute, $value, $parameters) {
            $valid = true;
            $production = Config::get('orbit.partners_api.sepulsa.unique_token', TRUE);
            if ($production) {
                $couponSepulsa = CouponSepulsa::select('promotions.promotion_id',
                                                       'promotions.promotion_name',
                                                       'promotions.promotion_type',
                                                       'campaign_status.campaign_status_name',
                                                       'coupon_sepulsa.token')
                                               ->join('promotions', 'promotions.promotion_id', '=', 'coupon_sepulsa.promotion_id')
                                               ->join('campaign_status', 'promotions.campaign_status_id', '=', 'campaign_status.campaign_status_id')
                                               ->where('coupon_sepulsa.token', '=', $value)
                                               ->whereNotIn('campaign_status.campaign_status_name', ['stopped'])
                                               ->get();

                $valid = count($couponSepulsa) == 0 ? true : false;
            }

            return $valid;
        });
    }

    /**
     * @param Coupon $coupon
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveTranslations($coupon, $translations_json_string, $scenario = 'create', $isThirdParty = FALSE)
    {
        /*
         * JSON structure: object with keys = merchant_language_id and values = ProductTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where Coupon object is object with keys:
         *   promotion_name, description, long_description, short_description
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['promotion_name', 'description', 'long_description', 'short_description', 'how_to_buy_and_redeem', 'terms_and_conditions'];
        $user = $this->api->user;
        $operations = [];

        $data = @json_decode($translations_json_string);

        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
        }

        $pmpAccountDefaultLanguage = Language::where('name', '=', $this->pmpAccountDefaultLanguage)
                ->first();


        foreach ($data as $merchant_language_id => $translations) {
            $language = Language::where('language_id', '=', $merchant_language_id)
                ->first();
            if (empty($language)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
            }
            $existing_translation = CouponTranslation::excludeDeleted()
                ->where('promotion_id', '=', $coupon->promotion_id)
                ->where('merchant_language_id', '=', $merchant_language_id)
                ->first();

            if ($translations === null) {
                // deleting, verify exists
                if (empty($existing_translation)) {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
                }
                $operations[] = ['delete', $existing_translation];
            } else {
                foreach ($translations as $field => $value) {
                    if (!in_array($field, $valid_fields, TRUE)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.key'));
                    }
                    if ($value !== null && !is_string($value)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.value'));
                    }
                }
                if (empty($existing_translation)) {
                    $operations[] = ['create', $merchant_language_id, $translations];
                } else {
                    $operations[] = ['update', $existing_translation, $translations];
                }
            }
        }

        foreach ($operations as $operation) {
            $op = $operation[0];
            if ($op === 'create') {
                $new_translation = new CouponTranslation();
                $new_translation->promotion_id = $coupon->promotion_id;
                $new_translation->merchant_language_id = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $new_translation->{$field} = $value;
                }
                $new_translation->created_by = $this->api->user->user_id;
                $new_translation->modified_by = $this->api->user->user_id;
                $new_translation->save();

                // Fire an event which listen on orbit.coupon.after.translation.save
                // @param ControllerAPI $this
                // @param EventTranslation $new_transalation
                Event::fire('orbit.coupon.after.translation.save', array($this, $new_translation));

                if ($isThirdParty) {
                    // validate header & image 1 if the coupon translation language = pmp account default language
                    if ($new_translation->merchant_language_id === $pmpAccountDefaultLanguage->language_id) {
                        $header_files = OrbitInput::files('header_image_translation_' . $new_translation->merchant_language_id);
                        if (! $header_files && $isThirdParty) {
                            $errorMessage = 'Header image is required for ' . $pmpAccountDefaultLanguage->name_long;
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }
                        $image1_files = OrbitInput::files('image1_translation_' . $new_translation->merchant_language_id);
                        if (! $image1_files && $isThirdParty) {
                            $errorMessage = 'Image 1 is required for ' . $pmpAccountDefaultLanguage->name_long;
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }
                    }
                    Event::fire('orbit.coupon.after.header.translation.save', array($this, $new_translation));
                    Event::fire('orbit.coupon.after.image1.translation.save', array($this, $new_translation));
                }

                $coupon->setRelation('translation_' . $new_translation->merchant_language_id, $new_translation);
            }
            elseif ($op === 'update') {
                /** @var CouponTranslation $existing_translation */
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->status = $coupon->status;
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->save();

                // Fire an event which listen on orbit.coupon.after.translation.save
                // @param ControllerAPI $this
                // @param EventTranslation $new_transalation
                Event::fire('orbit.coupon.after.translation.save', array($this, $existing_translation));

                if ($isThirdParty) {
                    // validate header & image 1 if the coupon translation language = pmp account default language
                    if ($existing_translation->merchant_language_id === $pmpAccountDefaultLanguage->language_id) {

                        //check media header and image1
                        $header = Media::where('object_id', $existing_translation->coupon_translation_id)
                                        ->where('media_name_id', 'coupon_header_grab_translation')->first();
                        $image1 = Media::where('object_id', $existing_translation->coupon_translation_id)
                                        ->where('media_name_id', 'coupon_image1_grab_translation')->first();

                        $header_files = OrbitInput::files('header_image_translation_' . $existing_translation->merchant_language_id);
                        if (! $header_files && $isThirdParty && empty($header)) {
                            $errorMessage = 'Header image is required for ' . $pmpAccountDefaultLanguage->name_long;
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }
                        $image1_files = OrbitInput::files('image1_translation_' . $existing_translation->merchant_language_id);
                        if (! $image1_files && $isThirdParty && empty($image1)) {
                            $errorMessage = 'Image 1 is required for ' . $pmpAccountDefaultLanguage->name_long;
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }
                    }
                    Event::fire('orbit.coupon.after.header.translation.save', array($this, $existing_translation));
                    Event::fire('orbit.coupon.after.image1.translation.save', array($this, $existing_translation));
                }

                // return respones if any upload image or no
                $existing_translation->load('media');

                $coupon->setRelation('translation_' . $existing_translation->merchant_language_id, $existing_translation);
            }
            elseif ($op === 'delete') {
                /** @var CouponTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->delete();
            }
        }
    }

    protected function getTimezone($current_mall)
    {
        $timezone = Mall::leftJoin('timezones','timezones.timezone_id','=','merchants.timezone_id')
            ->where('merchants.merchant_id','=', $current_mall)
            ->first();

        return $timezone->timezone_name;
    }

    protected function getTimezoneOffset($timezone)
    {
        $dt = new DateTime('now', new DateTimeZone($timezone));

        return $dt->format('P');
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    public function setReturnBuilder($bool)
    {
        $this->returnBuilder = $bool;

        return $this;
    }

}
