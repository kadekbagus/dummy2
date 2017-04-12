<?php namespace Orbit\Controller\API\v1\Merchant;

use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;
use BaseMerchant;
use Validator;
use Lang;
use DB;
use Config;
use stdclass;
use Orbit\Controller\API\v1\Merchant\Merchant\MerchantHelper;
use App;
use \Queue;

class MerchantExportAPIController extends ControllerAPI
{
    protected $merchantViewRoles = ['super admin', 'merchant database admin'];

    /**
     * POST data - store synchronization
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     */
    public function postMerchantExport()
    {
        try {
            $httpCode = 200;
            $exportData = OrbitInput::post('ids', null);
            $exportType = OrbitInput::post('type', 'merchant');

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->merchantViewRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            if (empty($exportData)) {
                $message = 'Merchant is required';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            // queue for data synchronization
            Queue::push('Orbit\\Queue\\FileExport\\BaseMerchantExportQueue', [
                'export_data' => $exportData,
                'user' => $user->user_id
            ], 'gtm_export_csv');

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
