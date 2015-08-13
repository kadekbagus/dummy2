<?php

class LuckyDrawNumberReceipt extends Eloquent
{
    /**
     * LuckyDrawNumberReceipt Model
     *
     * @author Tian <tian@dominopos.com>
     */

    protected $table = 'lucky_draw_number_receipt';

    protected $primaryKey = 'lucky_draw_number_receipt_id';

    public function number()
    {
        return $this->belongsTo('LuckyDrawNumber', 'lucky_draw_number_id', 'lucky_draw_number_id');
    }

    public function receipt()
    {
        return $this->belongsTo('LuckyDrawReceipt', 'lucky_draw_receipt_id', 'lucky_draw_receipt_id');
    }

    /**
     * Insert the records using select method.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $receiptId - The receipt id
     * @param string $hash - The lucky draw number hash
     * @return number of record inserted
     */
    public static function syncUsingHashNumber($receiptId, $hash)
    {
        $pdo = DB::connection()->getPdo();
        $prefix = DB::getTablePrefix();

        // Get the hash count
        $hashCount = DB::table('lucky_draw_numbers')->where('hash', $hash)->count();

        $records = $pdo->query("insert into {$prefix}lucky_draw_number_receipt (lucky_draw_number_id, lucky_draw_receipt_id)
                                select lucky_draw_number_id, $receiptId
                                from {$prefix}lucky_draw_numbers where hash='$hash'");

        $receiptIdCount = DB::table('lucky_draw_number_receipt')->where('lucky_draw_receipt_id', $receiptId)->count();

        if ($receiptIdCount !== $hashCount) {
            throw new Exception (sprintf('Number of record on lucky_draw_number_receipt(%s) does not match with lucky_draw_numbers (%s).', $receiptIdCount, $hashCount));
        }

        return $hashCount;
    }
}
