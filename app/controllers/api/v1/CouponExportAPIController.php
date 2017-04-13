<?php
/**
 * An API controller for export coupon to csv (grab)
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use \Orbit\Helper\Exception\OrbitCustomException;
use \Queue as Queue;

class CouponExportAPIController extends ControllerAPI
{
    protected $returnBuilder = FALSE;

    protected $couponViewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];

    /**
     * POST data - coupon_ids
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function postCouponExport()
    {
        try {
            $httpCode = 200;
            $exportData = OrbitInput::post('coupon_ids', null);
            $exportType = OrbitInput::post('type', 'coupon');

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->couponViewRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            if (empty($exportData)) {
                $message = 'Coupon ids is required';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            // queue for data synchronization
            // Queue::push('Orbit\\Queue\\FileExport\\RewardExportQueue', [
            //     'export_data' => $exportData,
            //     'user' => $user->user_id
            // ], 'gtm_export_csv');

        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);

        return $output;
    }
}
