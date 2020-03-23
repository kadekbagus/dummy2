<?php namespace Orbit\Controller\API\v1\Merchant\Merchant;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Validator;
use Lang;
use BaseMerchant;
use Config;

class MerchantDetailAPIController extends ControllerAPI
{
    protected $merchantViewRoles = ['super admin', 'merchant database admin'];

    /**
     * Get spesific base merchant by id.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function getMerchantDetail()
    {
        try {
            $httpCode = 200;

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

            $this->registerCustomValidation();

            $baseMerchantId = OrbitInput::get('base_merchant_id');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'baseMerchantId'  => $baseMerchantId,
                ),
                array(
                    'baseMerchantId'  => 'required|orbit.exist.base_merchant_id',
                ),
                array(
                    'orbit.exist.base_merchant_id' => 'Base merchant not found',
               )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $baseMerchant = BaseMerchant::with(
                                                    'baseMerchantCategory',
                                                    'baseMerchantTranslation',
                                                    'keywords',
                                                    'mediaLogo',
                                                    'mediaLogoGrab',
                                                    'mediaBanner',
                                                    'partners',
                                                    'country',
                                                    'supportedLanguage',
                                                    'bank',
                                                    'financialContactDetail',
                                                    'paymentProvider',
                                                    'productTags'
                                                )
                                    ->where('base_merchant_id', '=', $baseMerchantId)
                                    ->first();

            // Frontend request to make reformat financial contact detail
            $baseMerchant->contact_name = null;
            $baseMerchant->position = null;
            $baseMerchant->phone_number = null;
            $baseMerchant->email_financial = null;

            if (count($baseMerchant->financialContactDetail) > 0) {
                $baseMerchant->contact_name = $baseMerchant->financialContactDetail->contact_name;
                $baseMerchant->position = $baseMerchant->financialContactDetail->position;
                $baseMerchant->phone_number = $baseMerchant->financialContactDetail->phone_number;
                $baseMerchant->email_financial = $baseMerchant->financialContactDetail->email;
            }
            unset($baseMerchant->financialContactDetail);

            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
            $this->response->data = $baseMerchant;

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
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

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check existing base merchant id
        Validator::extend('orbit.exist.base_merchant_id', function ($attribute, $value, $parameters) {
            $merchant = BaseMerchant::where('base_merchant_id', '=', $value)
                            ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            return TRUE;
        });
    }
}