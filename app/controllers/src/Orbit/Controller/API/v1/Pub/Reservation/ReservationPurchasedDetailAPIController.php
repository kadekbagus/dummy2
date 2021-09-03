<?php 

namespace Orbit\Controller\API\v1\Pub\Reservation;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Lang;
use \Exception;
use BrandProductReservation;
use Helper\EloquentRecordCounter as RecordCounter;

class ReservationPurchasedDetailAPIController extends PubControllerAPI
{
    public function getReservationPurchasedDetail()
    {
        $httpCode = 200;

        try {
            $user = $this->getUser();

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $brandProductReservationId = OrbitInput::get('brand_product_reservation_id');

            $validator = Validator::make(
                array(
                    'reservation_id'      => $brandProductReservationId,
                ),
                array(
                    'reservation_id'      => 'required',
                ),
                array(
                    'reservation_id.required' => 'Reservation ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)";
            if ($usingCdn) {
                $image = "CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
            }

            $reservation = BrandProductReservation::select('brand_product_reservations.brand_product_reservation_id',
                                                            'brand_product_reservations.created_at',
                                                            'brand_product_reservations.expired_at',
                                                            'brand_product_variants.brand_product_id',
                                                            'brand_product_reservations.user_id',
                                                            'brand_product_reservation_details.product_name',
                                                            'brand_product_reservation_details.selling_price',
                                                            'brand_product_reservation_details.quantity',
                                                            'brand_product_reservation_details.sku',
                                                            'brand_product_reservation_details.product_code',
                                                            'brand_product_reservation_details.image_url',
                                                            'brand_product_reservation_details.image_cdn as image',
                                                            'brand_product_reservations.status',
                                                    DB::raw("CONCAT(m1.name,' ', m2.name, ', ', m2.city) as store_name"))
                                                    ->with([
                                                        'users' => function($q) {
                                                            $q->select('users.user_id', 'user_firstname', 'user_lastname');
                                                        },
                                                        'details.variant_details' => function($q) {
                                                            $q->where('option_type', 'variant_option');
                                                        }
                                                    ])
                                                    ->join(
                                                        DB::raw("{$prefix}merchants as m1"),
                                                        'brand_product_reservations.merchant_id',
                                                        '=',
                                                        DB::raw('m1.merchant_id')
                                                    )
                                                    ->join(
                                                        DB::raw("{$prefix}merchants as m2"),
                                                        DB::raw('m1.parent_id'),
                                                        '=',
                                                        DB::raw('m2.merchant_id')
                                                    )
                                                    ->join(
                                                        'brand_product_reservation_details',
                                                        'brand_product_reservations.brand_product_reservation_id',
                                                        '=',
                                                        'brand_product_reservation_details.brand_product_reservation_id'
                                                    )
                                                    ->where('brand_product_reservations.user_id', '=', $user->user_id)
                                                    ->where('brand_product_reservations.brand_product_reservation_id', $brandProductReservationId)
                                                    ->first();

            if (empty($reservation)) {
                $errorMessage = 'Reservation not found';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $this->response->data = $reservation;
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
            $this->response->data = null;
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
        } catch (\Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        return $this->render($httpCode);
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}
