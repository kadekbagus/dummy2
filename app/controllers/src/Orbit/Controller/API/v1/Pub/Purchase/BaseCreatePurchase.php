<?php

namespace Orbit\Controller\API\v1\Pub\Purchase;

use DB;
use App;
use Log;
use Mall;
use Country;
use Request;
use Discount;
use PaymentMidtrans;
use PaymentTransaction;
use PaymentTransactionDetail;
use PaymentTransactionDetailNormalPaypro;
use Orbit\Helper\Util\CampaignSourceParser;
use Orbit\Controller\API\v1\Pub\Purchase\Activities\PurchaseStartingActivity;

/**
 * Base Create Purchase class.
 *
 * @author Budi <budi@gotomalls.com>
 */
class BaseCreatePurchase
{
    protected $objectType = 'digital_product';

    protected $user = null;

    protected $item = null;

    protected $request = null;

    protected $purchase = null;

    protected $purchaseDetail = null;

    protected $campaignData = null;

    protected $countryId = null;

    protected $mallTimeZone = null;

    protected $beforeCommitHooksFn = null;

    protected $afterCommitHooksFn = null;

    protected $withTransaction = true;

    protected function init($request)
    {
        $this->request = $request;

        $this->initUser();

        $this->initItem();

        $this->buildCampaignData();

        $this->getCountryId();

        $this->getMallTimezone();
    }

    protected function initUser()
    {
        $this->user = App::make('currentUser');
    }

    protected function initItem()
    {
        $this->item = App::make('digitalProduct');
    }

    protected function buildCampaignData()
    {
        // Get utm
        $referer = NULL;
        if (isset($_SERVER['HTTP_REFERER']) && ! empty($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
        }
        $current_url = Request::fullUrl();
        $urlForTracking = [$referer, $current_url];
        $this->campaignData = CampaignSourceParser::create()
                            ->setUrls($urlForTracking)
                            ->getCampaignSource();
    }

    protected function getCountryId()
    {
        // Resolve country_id
        $this->countryId = $this->request->country_id;
        if (! empty($this->countryId) && ($this->countryId !== '0' || $this->countryId !== 0)) {
            $country = Country::where('name', $this->countryId)->first();
            if (! empty($country)) {
                $this->countryId = $country->country_id;
            }
        }
    }

    protected function getMallTimezone()
    {
        // Get mall timezone
        $mallId = $this->request->mall_id;
        $this->mallTimeZone = 'Asia/Jakarta';
        $mall = null;
        if ($mallId !== 'gtm') {
            $mall = Mall::where('merchant_id', $mallId)->first();
            if (!empty($mall)) {
                $this->mallTimeZone = $mall->getTimezone($mallId);
                $this->countryId = $mall->country_id;
            }
        }
    }

    protected function createPaymentTransaction()
    {
        $this->purchase = PaymentTransaction::create(
            $this->buildPurchaseData()
        );
    }

    protected function buildPurchaseData()
    {
        return [
            'user_email' => $this->request->email,
            'user_name' => trim($this->request->first_name . ' ' . $this->request->last_name),
            'user_id' => $this->user->user_id,
            'phone' => $this->request->phone,
            'country_id' => $this->countryId,
            'payment_method' => $this->request->payment_method,
            'amount' => $this->getTotalAmount(),
            'currency' => $this->request->currency,
            'status' => PaymentTransaction::STATUS_STARTING,
            'timezone_name' => $this->mallTimeZone,
            'post_data' => $this->request->post_data ? serialize($this->request->post_data) : null,
            'extra_data' => $this->getExtraData($this->request),
            'utm_source' => $this->campaignData['campaign_source'],
            'utm_medium' => $this->campaignData['campaign_medium'],
            'utm_term' => $this->campaignData['campaign_term'],
            'utm_content' => $this->campaignData['campaign_content'],
            'utm_campaign' => $this->campaignData['campaign_name'],
        ];
    }

    protected function createPaymentTransactionDetail()
    {
        $this->purchaseDetail = PaymentTransactionDetail::create(
            $this->buildPurchaseDetailData()
        );
    }

    protected function buildPurchaseDetailData()
    {
        return [
            'payment_transaction_id' => $this->purchase->payment_transaction_id,
            'currency' => $this->request->currency
                ? $this->request->currency
                : 'IDR',
            'price' => $this->getItemPrice(),
            'quantity' => 1,
            'vendor_price' => $this->getVendorPrice(),
            'object_id' => $this->request->object_id,
            'object_type' => $this->request->object_type,
            'object_name' => $this->request->object_name,
            'provider_product_id' => isset($this->item->selected_provider_product_id)
                ? $this->item->selected_provider_product_id
                : null,
        ];
    }

    protected function insertAdditionalDetails()
    {
        // Insert normal/paypro details
        $paymentDetailNormalPaypro = new PaymentTransactionDetailNormalPaypro;
        $this->purchaseDetail->normal_paypro_detail()->save($paymentDetailNormalPaypro);

        // Insert midtrans info
        $paymentMidtransDetail = new PaymentMidtrans;
        $this->purchase->midtrans()->save($paymentMidtransDetail);
    }

    protected function applyPromoCode()
    {
        // process discount if any.
        $promoCode = $this->request->promo_code;
        if (! empty($promoCode)) {
            Log::info("Trying to make pulsa purchase with promo code {$promoCode}...");

            // TODO: Move to PromoReservation helper?
            $reservedPromoCodes = $this->user->discountCodes()->with(['discount'])
                ->where('discount_code', $promoCode)
                ->whereNull('payment_transaction_id')
                ->reserved()
                ->get();

            $discount = $reservedPromoCodes->count() > 0
                ? $reservedPromoCodes->first()->discount
                : Discount::findOrFail($reservedPromoCodes->first()->discount_id);

            $discountRecord = new PaymentTransactionDetail;
            $discountRecord->payment_transaction_id = $purchase->payment_transaction_id;
            $discountRecord->currency = $this->purchase->currency;
            $discountRecord->price = $discount->value_in_percent / 100 * $this->purchase->amount * -1.00;
            $discountRecord->quantity = 1;
            $discountRecord->object_id = $discount->discount_id;
            $discountRecord->object_type = 'discount';
            $discountRecord->object_name = $discount->discount_title;
            $discountRecord->save();

            // $reservedPromoCode->status = 'ready_to_issue';
            foreach($reservedPromoCodes as $reservedPromoCode) {
                $reservedPromoCode->payment_transaction_id = $this->purchase->payment_transaction_id;
                $reservedPromoCode->save();

                Log::info(sprintf("Promo Code %s (discountCodeId: %s) added to purchase %s",
                    $reservedPromoCode->discount_code,
                    $reservedPromoCode->discount_code_id,
                    $this->purchase->payment_transaction_id
                ));
            }

            $this->purchase->amount = $this->purchase->amount + $discountRecord->price;
            $this->purchase->save();

            // Add additional property to indicate that this purchase is free,
            // so frontend can bypass payment steps.
            if ($discount->value_in_percent === 100) {
                $this->purchase->bypass_payment = true;
            }

            $this->purchase->promo_code = $reservedPromoCode->discount_code;
        }
    }

    protected function runBeforeCommitHooks()
    {
        if (is_callable($this->beforeCommitHooksFn)) {
            call_user_func_array(
                $this->beforeCommitHooksFn,
                [$this->purchase, $this->request]
            );
        }
        else {
            $this->beforeCommitHooks();
        }
    }

    protected function beforeCommitHooks()
    {
        //
    }

    protected function runAfterCommitHooks()
    {
        if (is_callable($this->afterCommitHooksFn)) {
            call_user_func_array(
                $this->afterCommitHooksFn,
                [$this->purchase, $this->request]
            );
        }
        else {
            $this->afterCommitHooks();
        }
    }

    protected function afterCommitHooks()
    {
        $this->recordActivity();
    }

    protected function getTotalAmount()
    {
        return $this->item->total_amount;
    }

    protected function getItemPrice()
    {
        return $this->item->total_amount;
    }

    protected function getVendorPrice()
    {
        return $this->item->total_amount;
    }

    /**
     * Get the right extra_data value for the purchase.
     *
     * @param  ValidateRequestInterface $request request object
     * @return string|null extra data
     */
    protected function getExtraData($request)
    {
        return null;
    }

    protected function getObjectTypeForActivity()
    {
        return ucwords(str_replace('_', ' ', $this->objectType));
    }

    protected function recordActivity()
    {
        // Record activity
        $this->user->activity(new PurchaseStartingActivity(
            $this->purchase, $this->getObjectTypeForActivity()
        ));
    }

    public function onBeforeCommit($hook)
    {
        $this->beforeCommitHooksFn = $hook;

        return $this;
    }

    public function onAfterCommit($hook)
    {
        $this->afterCommitHooksFn = $hook;

        return $this;
    }

    public function create($request)
    {
        if ($this->withTransaction) {
            DB::beginTransaction();
        }

        $this->init($request);

        $this->createPaymentTransaction();

        $this->createPaymentTransactionDetail();

        $this->insertAdditionalDetails();

        $this->applyPromoCode();

        $this->runBeforeCommitHooks();

        if ($this->withTransaction) {
            DB::commit();
        }

        $this->runAfterCommitHooks();

        return $this->purchase;
    }
}
