<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use \Exception;
use \QueryException;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ResponseRendererInterface;
use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\RepositoryExecutorInterface;

class RepositoryExecutor implements RepositoryExecutorInterface
{
    private $responseRenderer;

    public function __construct(ResponseRendererInterface $resp)
    {
        $this->responseRenderer = $resp;
    }

    /**
     * execute callback and return response
     *
     * @param Controller $controller
     * @param callable $callback
     * @return Illuminate\Support\Facades\Response
     */
    public function execute($ctrl, $callback)
    {
        $resp = $this->responseRenderer;
        try {
            return $resp->renderSuccess($ctrl, $callback($ctrl));
        } catch (ACLForbiddenException $e) {
            return $resp->renderForbidden($ctrl, $e);
        } catch (InvalidArgsException $e) {
            return $resp->renderInvalidArgs($ctrl, $e);
        } catch (QueryException $e) {
            return $resp->renderQueryExcept($ctrl, $e);
        } catch (Exception $e) {
            return $resp->renderExcept($ctrl, $e);
        }
    }
}
