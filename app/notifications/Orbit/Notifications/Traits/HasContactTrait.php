<?php namespace Orbit\Notifications\Traits;

use Illuminate\Support\Facades\Config;

/**
 * A trait that indicate that the using object/model has a contact info config.
 *
 * @author Budi <budi@dominopos.com>
 */
trait HasContactTrait 
{
    /**
     * Get the transaction data.
     *
     * @todo  return transaction as object instead of array. (need to adjust the view/email templates)
     * 
     * @return [type] [description]
     */
    protected function getContactData()
    {
        return [
            'phone' => Config::get('orbit.contact_information.customer_service.phone'),
            'email' => Config::get('orbit.contact_information.customer_service.email'),
        ];
    }

}
