<?php
/**
 * Saving object bank for payment wallet in every merchant and store
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */
class ObjectBank extends Eloquent
{
    protected $primaryKey = 'object_bank_id';

    protected $table = 'object_banks';
}