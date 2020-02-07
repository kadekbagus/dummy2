<?php namespace Orbit\Controller\API\v1\Pub\Purchase\DigitalProduct;

use App;
use Country;
use DB;
use Discount;
use Log;
use Mall;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseStartingActivity;
use Orbit\Helper\Util\CampaignSourceParser;
use PaymentMidtrans;
use PaymentTransaction;
use PaymentTransactionDetail;
use PaymentTransactionDetailNormalPaypro;
use Request;

/**
 * Digital Product Purchase
 */
class CreatePurchase
{
    public static function create($request)
    {
        $currentUser = App::make('currentUser');
        $digitalProduct = App::make('digitalProduct');
        $providerProduct = App::make('providerProduct');

        // Resolve country_id
        $countryId = $request->country_id;
        if (! empty($countryId) && ($countryId !== '0' || $countryId !== 0)) {
            $country = Country::where('name', $countryId)->first();
            if (! empty($country)) {
                $countryId = $country->country_id;
            }
        }

        // Get mall timezone
        $mallId = $request->mall_id;
        $mallTimeZone = 'Asia/Jakarta';
        $mall = null;
        if ($mallId !== 'gtm') {
            $mall = Mall::where('merchant_id', $mallId)->first();
            if (!empty($mall)) {
                $mallTimeZone = $mall->getTimezone($mallId);
                $countryId = $mall->country_id;
            }
        }

        // Get utm
        $referer = NULL;
        if (isset($_SERVER['HTTP_REFERER']) && ! empty($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
        }
        $current_url = Request::fullUrl();
        $urlForTracking = [$referer, $current_url];
        $campaignData = CampaignSourceParser::create()
                            ->setUrls($urlForTracking)
                            ->getCampaignSource();

        DB::beginTransaction();

        $purchase = new PaymentTransaction;
        $purchase->user_email = $request->email;
        $purchase->user_name = trim($request->first_name . ' ' . $request->last_name);
        $purchase->user_id = $currentUser->user_id;
        $purchase->phone = $request->phone;
        $purchase->country_id = $countryId;
        $purchase->payment_method = $request->payment_method;
        $purchase->amount = $digitalProduct->selling_price;
        $purchase->currency = $request->currency;
        $purchase->status = PaymentTransaction::STATUS_STARTING;
        $purchase->timezone_name = $mallTimeZone;
        $purchase->post_data = $request->post_data ? serialize($request->post_data) : null;
        $purchase->extra_data = $request->game_id;

        $purchase->utm_source = $campaignData['campaign_source'];
        $purchase->utm_medium = $campaignData['campaign_medium'];
        $purchase->utm_term = $campaignData['campaign_term'];
        $purchase->utm_content = $campaignData['campaign_content'];
        $purchase->utm_campaign = $campaignData['campaign_name'];

        $purchase->save();

        // Insert detail information
        $paymentDetail = new PaymentTransactionDetail;
        $paymentDetail->payment_transaction_id = $purchase->payment_transaction_id;
        $paymentDetail->currency = $request->currency;
        $paymentDetail->price = $digitalProduct->selling_price;
        $paymentDetail->quantity = $request->quantity;
        $paymentDetail->vendor_price = $providerProduct->price;

        $paymentDetail->object_id = $request->object_id;
        $paymentDetail->object_type = $request->object_type;
        $paymentDetail->object_name = $request->object_name;
        $paymentDetail->provider_product_id = $providerProduct->provider_product_id;
        $paymentDetail->commission_type = $providerProduct->commission_type;
        $paymentDetail->commission_value = $providerProduct->commission_value;

        $paymentDetail->save();

        // Insert normal/paypro details
        $paymentDetailNormalPaypro = new PaymentTransactionDetailNormalPaypro;
        $paymentDetail->normal_paypro_detail()->save($paymentDetailNormalPaypro);

        // Insert midtrans info
        $paymentMidtransDetail = new PaymentMidtrans;
        $purchase->midtrans()->save($paymentMidtransDetail);

        // process discount if any.
        $promoCode = $request->promo_code;
        if (! empty($promoCode)) {
            Log::info("Trying to make pulsa purchase with promo code {$promoCode}...");

            // TODO: Move to PromoReservation helper?
            $reservedPromoCodes = $user->discountCodes()->with(['discount'])
                ->where('discount_code', $promoCode)
                ->whereNull('payment_transaction_id')
                ->reserved()
                ->get();

            $discount = $reservedPromoCodes->count() > 0
                ? $reservedPromoCodes->first()->discount
                : Discount::findOrFail($reservedPromoCodes->first()->discount_id);

            $discountRecord = new PaymentTransactionDetail;
            $discountRecord->payment_transaction_id = $purchase->payment_transaction_id;
            $discountRecord->currency = $purchase->currency;
            $discountRecord->price = $discount->value_in_percent / 100 * $purchase->amount * -1.00;
            $discountRecord->quantity = 1;
            $discountRecord->object_id = $discount->discount_id;
            $discountRecord->object_type = 'discount';
            $discountRecord->object_name = $discount->discount_title;
            $discountRecord->save();

            // $reservedPromoCode->status = 'ready_to_issue';
            foreach($reservedPromoCodes as $reservedPromoCode) {
                $reservedPromoCode->payment_transaction_id = $purchase->payment_transaction_id;
                $reservedPromoCode->save();

                Log::info(sprintf("Promo Code %s (discountCodeId: %s) added to purchase %s",
                    $reservedPromoCode->discount_code,
                    $reservedPromoCode->discount_code_id,
                    $purchase->payment_transaction_id
                ));
            }

            $purchase->amount = $purchase->amount + $discountRecord->price;
            $purchase->save();

            // Add additional property to indicate that this purchase is free,
            // so frontend can bypass payment steps.
            if ($discount->value_in_percent === 100) {
                $purchase->bypass_payment = true;
            }

            $purchase->promo_code = $reservedPromoCode->discount_code;
        }

        DB::commit();

        // Record activity
        $objectType = ucwords(str_replace('_', ' ', $digitalProduct->product_type));
        $currentUser->activity(new PurchaseStartingActivity($purchase, $objectType));

        return $purchase;
    }
}
