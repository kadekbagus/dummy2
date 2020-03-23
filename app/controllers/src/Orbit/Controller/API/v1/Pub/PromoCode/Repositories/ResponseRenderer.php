<?php namespace Orbit\Controller\API\v1\Pub\PromoCode\Repositories;

use Orbit\Controller\API\v1\Pub\PromoCode\Repositories\Contracts\ResponseRendererInterface;
use Config;
use Lang;

class ResponseRenderer implements ResponseRendererInterface
{
    public function renderResponse($ctrl, $code, $status, $msg, $data, $httpCode)
    {
        $ctrl->response->data = $data;
        $ctrl->response->code = $code;
        $ctrl->response->status = $status;
        $ctrl->response->message = $msg;
        return $ctrl->render($httpCode);
    }

    public function renderSuccess($ctrl, $data)
    {
        return $this->renderResponse($ctrl, 0, 'success', 'OK', $data, 200);
    }

    public function renderForbidden($ctrl, $e)
    {
        return $this->renderResponse($ctrl, $e->getCode(), 'error', $e->getMessage(), null, 403);
    }

    public function renderInvalidArgs($ctrl, $e)
    {
        $result['total_records'] = 0;
        $result['returned_records'] = 0;
        $result['records'] = null;
        return $this->renderResponse($ctrl, $e->getCode(), 'error', $e->getMessage(), $result, 403);
    }

    public function renderQueryExcept($ctrl, $e)
    {
        // Only shows full query error when we are in debug mode
        if (Config::get('app.debug')) {
            $msg = $e->getMessage();
        } else {
            $msg = Lang::get('validation.orbit.queryerror');
        }
        return $this->renderResponse($ctrl, $e->getCode(), 'error', $msg, null, 500);
    }

    public function renderExcept($ctrl, $e)
    {
        return $this->renderResponse($ctrl, $ctrl->getNonZeroCode($e->getCode()), 'error', $e->getMessage(), null, 500);
    }

}
