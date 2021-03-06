<?php
/**
 * Model for representing the account types table.
 *
 * @author Irianto <irianto@dominopos.com>
 */
class AccountType extends Eloquent
{
    protected $primaryKey = 'account_type_id';
    protected $table = 'account_types';

    /**
     * Import trait ModelStatusTrait so we can use some common scope dealing
     * with `status` field.
     */
    use ModelStatusTrait;
}
