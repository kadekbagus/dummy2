<?php

namespace Orbit\Helper\AutoIssueCoupon;

/**
 * Has Rewards for payment transaction.
 */
trait HasRewards
{
    /**
     * Get rewards information from current transaction.
     *
     * @return void
     */
    public function getRewards()
    {
        $rewards = [];

        if ($this->issued_coupon && $this->issued_coupon->is_auto_issued) {
            $rewards[] = (object) [
                'object_type' => 'coupon',
                'object_id' => $this->issued_coupon->promotion_id,
                'object_name' => $this->issued_coupon->coupon->promotion_name,
            ];

            unset($this->issued_coupon);
        }

        // other type of rewards should go here...

        if (! empty($rewards)) {
            $this->rewards = $rewards;
        }
    }
}
