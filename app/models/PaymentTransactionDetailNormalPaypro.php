<?php

/**
 * Payment Transcations Detail Model.
 *
 * @author Budi <budi@dominopos.com>
 */
class PaymentTransactionDetailNormalPaypro extends Eloquent
{
    use ModelStatusTrait;

    protected $primaryKey = 'payment_normal_paypro_detail_id';

    protected $table = 'payment_normal_paypro_details';

}
