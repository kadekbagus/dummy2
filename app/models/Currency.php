<?php
/**
 * Saving information currency
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */
class Currency extends Eloquent
{
    protected $primaryKey = 'currency_id';

    protected $table = 'currencies';
}