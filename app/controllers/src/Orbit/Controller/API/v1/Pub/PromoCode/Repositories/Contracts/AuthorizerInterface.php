<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts;

/**
 * interface for aby class having capability to set authorizer
 *
 * @author Zamroni <zamroni@dominopos.com>
 */
interface AuthorizerInterface
{
    public function authorizer($authorizer);
}
