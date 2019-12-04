<?php namespace Orbit\Controller\API\v1\Pub\Coupon\Detail\Repository;

use DB;

/**
 * class that add payment information of current coupon detail page
 * We move to separate query to improve performance
 *
 * @author Zamroni <amroni@dominopos.com>
 */
class PaymentRepository
{
    private function getPaymentInfo($couponId, $userId)
    {
        return DB::table('payment_transactions')
            ->select(
                'payment_transactions.payment_transaction_id',
                'payment_transactions.status',
                'payment_midtrans.payment_midtrans_info'
            )->join(
                'payment_transaction_details',
                'payment_transaction_details.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id'
            )
            ->leftJoin(
                'payment_midtrans',
                'payment_midtrans.payment_transaction_id', '=', 'payment_transactions.payment_transaction_id'
            )->where('payment_transactions.user_id', $userId)
            ->where('payment_transaction_details.object_id', $couponId)
            ->where('payment_transaction_details.object_type', 'coupon')
            ->orderBy('payment_transactions.created_at', 'desc')
            ->first();
    }

    public function addPaymentInfo($coupon, $user)
    {
        $paymentInfo = $this->getPaymentInfo($coupon->promotion_id, $user->user_id);
        if (empty($paymentInfo)) {
            $coupon->transaction_id = null;
            $coupon->payment_status = null;
            $coupon->payment_midtrans_info = null;
        } else {
            $coupon->transaction_id = $paymentInfo->payment_transaction_id;
            $coupon->payment_status = $paymentInfo->status;
            $coupon->payment_midtrans_info = $paymentInfo->payment_midtrans_info;
        }
        return $coupon;
    }
}
