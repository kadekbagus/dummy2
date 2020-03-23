<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts;

/**
 * interface for any class having capability to execute a callback and
 * output response
 *
 * @author Zamroni <zamroni@dominopos.com>
 */
interface RepositoryExecutorInterface
{
    /**
     * execute callback and return response
     *
     * @param Controller $controller
     * @param callable $callback
     * @return Illuminate\Support\Facades\Response
     */
    public function execute($ctrl, $callback);
}
