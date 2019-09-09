<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts;

interface ResponseRendererInterface
{
    public function renderResponse($view, $code, $status, $msg, $data, $httpCode);
    public function renderSuccess($ctrl, $data);

    public function renderForbidden($ctrl, $e);

    public function renderInvalidArgs($ctrl, $e);

    public function renderQueryExcept($ctrl, $e);

    public function renderExcept($ctrl, $e);
}
