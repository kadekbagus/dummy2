<?php

namespace Orbit\Helper\AutoIssueCoupon;

use Orbit\Helper\Activity\PubActivity;

/**
 * Auto Issue Coupon Activity.
 *
 * @author Budi <budi@gotomalls.com>
 */
class AutoIssueCouponActivity extends PubActivity
{
    protected $payment = null;

    public function __construct($payment, $coupon, $additionalData = [])
    {
        if (! isset($payment->user)) {
            $payment->load(['user']);
        }

        $this->payment = $payment;

        parent::__construct($payment->user, $coupon, $additionalData);
    }

    /**
     * Optional method that return array of additional data
     * that will be merged before recording the activity.
     */
    protected function getAdditionalActivityData()
    {
        return [
            'notes' => $this->payment->payment_transaction_id,
            'activityType' => 'coupon_issuance',
            'activityName' => 'auto_issue_coupon',
            'activityNameLong' => 'Auto Issue Coupon',
            'moduleName' => 'Coupon',
        ];
    }
}
