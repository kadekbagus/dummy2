<?php
class LuckyDrawReceipt extends Eloquent
{
    /**
     * LuckyDrawReceipt Model
     *
     * @author Tian <tian@dominopos.com>
     */

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;

    protected $table = 'lucky_draw_receipts';

    protected $primaryKey = 'lucky_draw_receipt_id';

    public function mall()
    {
        return $this->belongsTo('Retailer', 'mall_id', 'merchant_id')->isMall();
    }

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id');
    }

    public function receiptRetailer()
    {
        return $this->belongsTo('Retailer', 'receipt_retailer_id', 'merchant_id')->isMall('no');
    }

    public function numbers()
    {
        return $this->belongsToMany('LuckyDrawNumber', 'lucky_draw_number_receipt', 'lucky_draw_receipt_id', 'lucky_draw_number_id');
    }

    /**
     * Relation of table promotions (coupon) with the receipt.
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function issuedCoupons()
    {
        return $this->belongsToMany('IssuedCoupon', 'lucky_draw_number_receipt', 'lucky_draw_receipt_id', 'issued_coupon_id');
    }

    public function creator()
    {
        return $this->belongsTo('User', 'created_by', 'user_id');
    }

    public function modifier()
    {
        return $this->belongsTo('User', 'modified_by', 'user_id');
    }

    /**
     * Generate receipt group
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string Identifier
     */
    public static function genReceiptGroup($identifier)
    {
        $group = sha1($identifier . microtime());

        return $group;
    }

    /**
     * Save receipt from array of object. This should coming from decoded JSON.
     * Assuming data already validated before.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param int $retailerId Mall/Retailer ID
     * @param User $user User who's submit this action
     * @param array $receipts Array of objects
     * @return string Receipt group
     */
    public static function saveFromArrayObject($retailerId, $user, array $receipts)
    {
        $identifier = mt_rand(0, 100000) . serialize($receipts);
        $group = static::genReceiptGroup($identifier);
        $returned = [];

        foreach ($receipts as $receipt) {
            $numberReceipt = new static();
            $numberReceipt->mall_id = $retailerId;
            $numberReceipt->user_id = $receipt->user_id;
            $numberReceipt->receipt_retailer_id = $receipt->receipt_retailer_id;
            $numberReceipt->receipt_number = $receipt->receipt_number;
            $numberReceipt->receipt_date = $receipt->receipt_date;
            $numberReceipt->receipt_payment_type = $receipt->receipt_payment_type;
            $numberReceipt->receipt_card_number = $receipt->receipt_card_number;
            $numberReceipt->receipt_amount = $receipt->receipt_amount;
            $numberReceipt->receipt_group = $group;
            $numberReceipt->external_receipt_id = $receipt->external_receipt_id;
            $numberReceipt->status = 'active';
            $numberReceipt->object_type = 'lucky_draw';
            $numberReceipt->created_by = $user->user_id;
            $numberReceipt->save();

            $returned[] = $numberReceipt;
        }

        return $returned;
    }
}
