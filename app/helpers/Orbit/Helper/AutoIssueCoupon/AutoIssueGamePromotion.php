<?php

namespace Orbit\Helper\AutoIssueCoupon;

use Carbon\Carbon;
use Exception;
use GameVoucherPromotion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Orbit\Notifications\DigitalProduct\FreeGameVoucherPromotionNotification;

/**
 * Helper which handle free/auto-issued free game voucher
 * if given transaction meet certain criteria.
 *
 * @author Budi <budi@gotomalls.com>
 */
class AutoIssueGamePromotion
{
    /**
     * Auto issue a free game voucher promotion if given product-type/payment meet the criteria.
     *
     * @param PaymentTransaction $payment trx instance
     * @param ProviderProduct $providerProduct the providerProduct instance
     * @return void
     */
    public static function issue($payment, $providerProduct = null)
    {
        // Resolve provider product type from payment if needed.
        $providerProduct = ! empty($providerProduct)
            ? $providerProduct
            : $payment->getProviderProduct();

        if (empty($providerProduct)) {
            return;
        }

        $issuedVouchers = [];

        DB::transaction(function() use ($payment, $providerProduct, &$issuedVouchers) {
            $trxDateTime = $payment->created_at->format('Y-m-d H:i:s');

            $vouchers = GameVoucherPromotion::with(['available_voucher'])
                ->has('available_voucher')
                ->has('active_provider_product')
                ->where('provider_product_id', $providerProduct->provider_product_id)
                ->where('status', 'active')
                ->where('end_date', '>=', $trxDateTime)
                ->where('start_date', '<=', $trxDateTime)
                ->get();

            foreach($vouchers as $voucher) {
                $voucher->available_voucher->payment_transaction_id = $payment->payment_transaction_id;
                $voucher->available_voucher->save();

                // (new AutoIssueGameVoucherActivity($payment, $coupon))->record();

                $issuedVouchers[] = $voucher->available_voucher
                    ->game_voucher_promotion_detail_id;

                Log::info(sprintf(
                    'AutoIssueGamePromotion: voucher %s (%s) issued for trx %s',
                    $voucher->game_voucher_promotion_id,
                    $voucher->campaign_name,
                    $payment->payment_transaction_id
                ));
            }
        });

        // Notify customer with free voucher pin/SN information.
        if (! empty($issuedVouchers)) {
            (new FreeGameVoucherPromotionNotification($payment))->send();
        }
    }
}
