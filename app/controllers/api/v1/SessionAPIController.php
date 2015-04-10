<?php
/**
 * An API controller for session user.
 */
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

class SessionAPIController extends ControllerAPI
{
    /**
     * GET - Session Check
     *
     * @author Tian <tian@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCheck()
    {
        try {
            if (Auth::check()) {
                $this->response->code = Status::OK;
                $this->response->status = 'success';
                $this->response->message = Status::OK_MSG;
                $this->response->data = NULL;
            } else {
                ACL::throwAccessForbidden();
            }
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render();
    }
}