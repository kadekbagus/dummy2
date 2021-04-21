<?php
/**
 * An API controller for managing Issued Coupon.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Carbon\Carbon as Carbon;

class IssuedCouponAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    protected $modifiyRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee'];

    /**
     * POST - Create New Issued Coupon
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                      (required) - Coupon ID
     * @param string     `issued_coupon_code`                (required) - Issued coupon code
     * @param integer    `user_id`                           (required) - User ID
     * @param string     `status`                            (required) - Status. Valid value: active, inactive, pending, blocked, deleted, redeemed.
     * @param datetime   `expired_date`                      (optional) - Expired date. Example: 2014-12-30 23:59:59
     * @param datetime   `issued_date`                       (optional) - Issued date. Example: 2014-12-31 00:00:00
     * @param datetime   `redeemed_date`                     (optional) - Redeemed date. Example: 2014-12-31 00:00:00
     * @param integer    `issuer_retailer_id`                (optional) - Issuer Retailer ID
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewIssuedCoupon()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.issuedcoupon.postnewissuedcoupon.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.issuedcoupon.postnewissuedcoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.issuedcoupon.postnewissuedcoupon.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_issued_coupon')) {
                Event::fire('orbit.issuedcoupon.postnewissuedcoupon.authz.notallowed', array($this, $user));
                $createIssuedCouponLang = Lang::get('validation.orbit.actionlist.new_issuedcoupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createIssuedCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.issuedcoupon.postnewissuedcoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $promotion_id = OrbitInput::post('promotion_id');
            $issued_coupon_code = OrbitInput::post('issued_coupon_code');
            $user_id = OrbitInput::post('user_id');
            $status = OrbitInput::post('status');
            $expired_date = OrbitInput::post('expired_date');
            $issued_date = OrbitInput::post('issued_date');
            $redeemed_date = OrbitInput::post('redeemed_date');
            $issuer_retailer_id = OrbitInput::post('issuer_retailer_id');

            $validator = Validator::make(
                array(
                    'promotion_id'         => $promotion_id,
                    'issued_coupon_code'   => $issued_coupon_code,
                    'user_id'              => $user_id,
                    'status'               => $status,
                    'issuer_retailer_id'   => $issuer_retailer_id,
                ),
                array(
                    'promotion_id'         => 'required|orbit.empty.coupon',
                    'issued_coupon_code'   => 'required|orbit.exists.issued_coupon_code',
                    'user_id'              => 'required|orbit.empty.user',
                    'status'               => 'required|orbit.empty.issued_coupon_status',
                    'issuer_retailer_id'   => 'orbit.empty.retailer',
                )
            );

            Event::fire('orbit.issuedcoupon.postnewissuedcoupon.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.issuedcoupon.postnewissuedcoupon.after.validation', array($this, $validator));

            // save IssuedCoupon.
            $newissuedcoupon = new IssuedCoupon();
            $newissuedcoupon->promotion_id = $promotion_id;
            $newissuedcoupon->issued_coupon_code = $issued_coupon_code;
            $newissuedcoupon->user_id = $user_id;
            $newissuedcoupon->status = $status;
            $newissuedcoupon->expired_date = $expired_date;
            $newissuedcoupon->issued_date = $issued_date;
            $newissuedcoupon->redeemed_date = $redeemed_date;
            $newissuedcoupon->issuer_retailer_id = $issuer_retailer_id;

            Event::fire('orbit.issuedcoupon.postnewissuedcoupon.before.save', array($this, $newissuedcoupon));

            $newissuedcoupon->save();

            Event::fire('orbit.issuedcoupon.postnewissuedcoupon.after.save', array($this, $newissuedcoupon));
            $this->response->data = $newissuedcoupon;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.issuedcoupon.postnewissuedcoupon.after.commit', array($this, $newissuedcoupon));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.issuedcoupon.postnewissuedcoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.issuedcoupon.postnewissuedcoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.issuedcoupon.postnewissuedcoupon.query.error', array($this, $e));

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
            Event::fire('orbit.issuedcoupon.postnewissuedcoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * POST - Update Issued Coupon
     *
     * @author <Tian> <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `issued_coupon_id`                  (required) - Issued Coupon ID
     * @param integer    `promotion_id`                      (optional) - Coupon ID
     * @param string     `issued_coupon_code`                (optional) - Issued coupon code
     * @param string     `user_id`                           (optional) - User ID
     * @param string     `status`                            (optional) - Status. Valid value: active, inactive, pending, blocked, deleted, redeemed.
     * @param datetime   `expired_date`                      (optional) - Expired date. Example: 2014-12-30 23:59:59
     * @param datetime   `issued_date`                       (optional) - Issued date. Example: 2014-12-31 00:00:00
     * @param datetime   `redeemed_date`                     (optional) - Redeemed date. Example: 2014-12-31 00:00:00
     * @param integer    `issuer_retailer_id`                (optional) - Issuer Retailer ID
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateIssuedCoupon()
    {
        try {
            $httpCode=200;

            Event::fire('orbit.issuedcoupon.postupdateissuedcoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.issuedcoupon.postupdateissuedcoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.issuedcoupon.postupdateissuedcoupon.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_issued_coupon')) {
                Event::fire('orbit.issuedcoupon.postupdateissuedcoupon.authz.notallowed', array($this, $user));
                $updateIssuedCouponLang = Lang::get('validation.orbit.actionlist.update_issuedcoupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateIssuedCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.issuedcoupon.postupdateissuedcoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $issued_coupon_id = OrbitInput::post('issued_coupon_id');
            $promotion_id = OrbitInput::post('promotion_id');
            $issued_coupon_code = OrbitInput::post('issued_coupon_code');
            $user_id = OrbitInput::post('user_id');
            $status = OrbitInput::post('status');

            $validator = Validator::make(
                array(
                    'issued_coupon_id'     => $issued_coupon_id,
                    'promotion_id'         => $promotion_id,
                    'issued_coupon_code'   => $issued_coupon_code,
                    'user_id'              => $user_id,
                    'status'               => $status,
                ),
                array(
                    'issued_coupon_id'     => 'required|orbit.empty.issued_coupon',
                    'promotion_id'         => 'orbit.empty.coupon',
                    'issued_coupon_code'   => 'issued_coupon_code_exists_but_me',
                    'user_id'              => 'orbit.empty.user',
                    'status'               => 'orbit.empty.issued_coupon_status',
                ),
                array(
                   'issued_coupon_code_exists_but_me' => Lang::get('validation.orbit.exists.issued_coupon_code'),
                )
            );

            Event::fire('orbit.issuedcoupon.postupdateissuedcoupon.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.issuedcoupon.postupdateissuedcoupon.after.validation', array($this, $validator));

            $updatedissuedcoupon = IssuedCoupon::with('coupon', 'user', 'issuerretailer')->excludeDeleted()->where('issued_coupon_id', $issued_coupon_id)->first();

            // save Issued Coupon.
            OrbitInput::post('promotion_id', function($promotion_id) use ($updatedissuedcoupon) {
                $updatedissuedcoupon->promotion_id = $promotion_id;
                $updatedissuedcoupon->load('coupon');
            });

            OrbitInput::post('issued_coupon_code', function($issued_coupon_code) use ($updatedissuedcoupon) {
                $updatedissuedcoupon->issued_coupon_code = $issued_coupon_code;
            });

            OrbitInput::post('user_id', function($user_id) use ($updatedissuedcoupon) {
                $updatedissuedcoupon->user_id = $user_id;
                $updatedissuedcoupon->load('user');
            });

            OrbitInput::post('status', function($status) use ($updatedissuedcoupon) {
                $updatedissuedcoupon->status = $status;
            });

            OrbitInput::post('expired_date', function($expired_date) use ($updatedissuedcoupon) {
                $updatedissuedcoupon->expired_date = $expired_date;
            });

            OrbitInput::post('issued_date', function($issued_date) use ($updatedissuedcoupon) {
                $updatedissuedcoupon->issued_date = $issued_date;
            });

            OrbitInput::post('redeemed_date', function($redeemed_date) use ($updatedissuedcoupon) {
                $updatedissuedcoupon->redeemed_date = $redeemed_date;
            });

            OrbitInput::post('issuer_retailer_id', function($issuer_retailer_id) use ($updatedissuedcoupon) {
                $updatedissuedcoupon->issuer_retailer_id = $issuer_retailer_id;
                $updatedissuedcoupon->load('issuerretailer');
            });

            Event::fire('orbit.issuedcoupon.postupdateissuedcoupon.before.save', array($this, $updatedissuedcoupon));

            $updatedissuedcoupon->save();

            Event::fire('orbit.issuedcoupon.postupdateissuedcoupon.after.save', array($this, $updatedissuedcoupon));
            $this->response->data = $updatedissuedcoupon;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.issuedcoupon.postupdateissuedcoupon.after.commit', array($this, $updatedissuedcoupon));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.issuedcoupon.postupdateissuedcoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.issuedcoupon.postupdateissuedcoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.issuedcoupon.postupdateissuedcoupon.query.error', array($this, $e));

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
            Event::fire('orbit.issuedcoupon.postupdateissuedcoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);

    }

    /**
     * POST - Delete Issued Coupon
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `issued_coupon_id`                  (required) - ID of the Issued Coupon
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteIssuedCoupon()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.issuedcoupon.postdeleteissuedcoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.issuedcoupon.postdeleteissuedcoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.issuedcoupon.postdeleteissuedcoupon.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_issued_coupon')) {
                Event::fire('orbit.issuedcoupon.postdeleteissuedcoupon.authz.notallowed', array($this, $user));
                $deleteIssuedCouponLang = Lang::get('validation.orbit.actionlist.delete_issuedcoupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteIssuedCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.issuedcoupon.postdeleteissuedcoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $issued_coupon_id = OrbitInput::post('issued_coupon_id');

            $validator = Validator::make(
                array(
                    'issued_coupon_id' => $issued_coupon_id,
                ),
                array(
                    'issued_coupon_id' => 'required|orbit.empty.issued_coupon',
                )
            );

            Event::fire('orbit.issuedcoupon.postdeleteissuedcoupon.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.issuedcoupon.postdeleteissuedcoupon.after.validation', array($this, $validator));

            $deleteissuedcoupon = IssuedCoupon::excludeDeleted()->where('issued_coupon_id', $issued_coupon_id)->first();
            $deleteissuedcoupon->status = 'deleted';

            Event::fire('orbit.issuedcoupon.postdeleteissuedcoupon.before.save', array($this, $deleteissuedcoupon));

            $deleteissuedcoupon->save();

            Event::fire('orbit.issuedcoupon.postdeleteissuedcoupon.after.save', array($this, $deleteissuedcoupon));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.issued_coupon');

            // Commit the changes
            $this->commit();

            Event::fire('orbit.issuedcoupon.postdeleteissuedcoupon.after.commit', array($this, $deleteissuedcoupon));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.issuedcoupon.postdeleteissuedcoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.issuedcoupon.postdeleteissuedcoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.issuedcoupon.postdeleteissuedcoupon.query.error', array($this, $e));

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
            Event::fire('orbit.issuedcoupon.postdeleteissuedcoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        $output = $this->render($httpCode);
        return $output;
    }

    /**
     * GET - Search Issued Coupon
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                      (optional) - Valid value: coupon, user, retailer.
     * @param string   `sortby`                    (optional) - column order by
     * @param string   `sortmode`                  (optional) - asc or desc
     * @param integer  `take`                      (optional) - limit
     * @param integer  `skip`                      (optional) - limit offset
     * @param integer  `promotion_id`              (optional) - Coupon ID
     * @param string   `issued_coupon_code`        (optional) - Issued coupon code
     * @param string   `issued_coupon_code_like`   (optional) - Issued coupon code like
     * @param integer  `user_id`                   (optional) - User ID
     * @param string   `status`                    (optional) - Status. Valid value: active, inactive, pending, blocked, deleted, redeemed.
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchIssuedCoupon()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.issuedcoupon.getsearchissuedcoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.issuedcoupon.getsearchissuedcoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.issuedcoupon.getsearchissuedcoupon.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.issuedcoupon.getsearchissuedcoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $fields  = OrbitInput::get('fields', null);

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                    'fields'  => $fields,
                ),
                array(
                    'sort_by' => 'in:issued_coupon_id,registered_date,issued_coupon_code,expired_date,issued_date,redeemed_date,status',
                    'fields'  => 'array',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.issued_coupon_sortby'),
                )
            );

            Event::fire('orbit.issuedcoupon.getsearchissuedcoupon.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.issuedcoupon.getsearchissuedcoupon.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.issuedcoupon.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.issuedcoupon.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $issuedcoupons = IssuedCoupon::excludeDeleted();

            // Dynamic select filter
            OrbitInput::get('fields', function($fields) use ($issuedcoupons)
            {
                // Do not allow fields that are unknown by us
                $errorCount = 0;
                $validFields = ['issued_coupon_code'];
                foreach ($fields as $field) {
                    if (! in_array($field, $validFields)) {
                        $errorCount++;
                    }
                }

                if ($errorCount === 0) {
                    $issuedcoupons->addSelect($fields);
                }
            });

            // Filter coupon by Ids
            OrbitInput::get('promotion_id', function($promotionIds) use ($issuedcoupons)
            {
                $issuedcoupons->whereIn('issued_coupons.promotion_id', $promotionIds);
            });

            // Filter coupon by issued coupon code
            OrbitInput::get('issued_coupon_code', function($issuedCouponCode) use ($issuedcoupons)
            {
                $issuedcoupons->whereIn('issued_coupons.issued_coupon_code', $issuedCouponCode);
            });

            // Filter coupon by matching issued coupon code pattern
            OrbitInput::get('issued_coupon_code_like', function($issuedCouponCode) use ($issuedcoupons)
            {
                $issuedcoupons->where('issued_coupons.issued_coupon_code', 'like', "%$issuedCouponCode%");
            });

            // Filter coupon by user Ids
            if ($user->isRoleName('consumer')) {
                $issuedcoupons->whereIn('issued_coupons.user_id', [$user->user_id]);
            } else {
                OrbitInput::get('user_id', function ($userIds) use ($issuedcoupons) {
                    $issuedcoupons->whereIn('issued_coupons.user_id', $userIds);
                });
            }

            // Filter coupon by status
            OrbitInput::get('status', function ($statuses) use ($issuedcoupons) {
                $issuedcoupons->whereIn('issued_coupons.status', $statuses);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($issuedcoupons) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'retailer') {
                        $issuedcoupons->with('issuerretailer');
                    } elseif ($relation === 'user') {
                        $issuedcoupons->with('user');
                    } elseif ($relation === 'coupon') {
                        $issuedcoupons->with('coupon');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_issuedcoupons = clone $issuedcoupons;

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $issuedcoupons->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $issuedcoupons)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $issuedcoupons->skip($skip);

            // Default sort by
            $sortBy = 'issued_coupons.issued_coupon_code';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'issued_coupon_id'   => 'issued_coupons.issued_coupon_id',
                    'registered_date'    => 'issued_coupons.created_at',
                    'issued_coupon_code' => 'issued_coupons.issued_coupon_code',
                    'expired_date'       => 'issued_coupons.expired_date',
                    'issued_date'        => 'issued_coupons.issued_date',
                    'redeemed_date'      => 'issued_coupons.redeemed_date',
                    'status'             => 'issued_coupons.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $issuedcoupons->orderBy($sortBy, $sortMode);

            $totalIssuedCoupons = RecordCounter::create($_issuedcoupons)->count();
            $listOfIssuedCoupons = $issuedcoupons->get();

            $data = new stdclass();
            $data->total_records = $totalIssuedCoupons;
            $data->returned_records = count($listOfIssuedCoupons);
            $data->records = $listOfIssuedCoupons;

            if ($totalIssuedCoupons === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.issued_coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.issuedcoupon.getsearchissuedcoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.issuedcoupon.getsearchissuedcoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.issuedcoupon.getsearchissuedcoupon.query.error', array($this, $e));

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
            Event::fire('orbit.issuedcoupon.getsearchissuedcoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.issuedcoupon.getsearchissuedcoupon.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Search Issued Coupon - List By Redeem Retailer
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`                    (optional) - column order by. Valid value: redeem_retailer_name, registered_date, issued_coupon_code, expired_date, promotion_name, promotion_type, description
     * @param string   `sortmode`                  (optional) - asc or desc
     * @param integer  `take`                      (optional) - limit
     * @param integer  `skip`                      (optional) - limit offset
     * @param integer  `promotion_id`              (optional) - Coupon ID
     * @param string   `issued_coupon_code`        (optional) - Issued coupon code
     * @param string   `issued_coupon_code_like`   (optional) - Issued coupon code like
     * @param integer  `user_id`                   (optional) - User ID
     * @param string   `status`                    (optional) - Status. Valid value: active, inactive, pending, blocked, deleted, redeemed.
     * @param string   `city`                      (optional) - City name
     * @param string   `city_like`                 (optional) - City name like
     * @param integer  `redeem_retailer_id`        (optional) - Redeem retailer ID
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchIssuedCouponByRedeemRetailer()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.issuedcoupon.getsearchissuedcouponbyredeemretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.issuedcoupon.getsearchissuedcouponbyredeemretailer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.issuedcoupon.getsearchissuedcouponbyredeemretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_issued_coupon')) {
                Event::fire('orbit.issuedcoupon.getsearchissuedcouponbyredeemretailer.authz.notallowed', array($this, $user));
                $viewIssuedCouponLang = Lang::get('validation.orbit.actionlist.view_issuedcoupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewIssuedCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.issuedcoupon.getsearchissuedcouponbyredeemretailer.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:redeem_retailer_name,registered_date,issued_coupon_code,expired_date,promotion_name,promotion_type,description',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.issued_coupon_by_retailer_sortby'),
                )
            );

            Event::fire('orbit.issuedcoupon.getsearchissuedcouponbyredeemretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.issuedcoupon.getsearchissuedcouponbyredeemretailer.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int)Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            $prefix = DB::getTablePrefix();
            $nowUTC = Carbon::now();
            // Builder object
            $issuedcoupons = IssuedCoupon::join('promotions', 'issued_coupons.promotion_id', '=', 'promotions.promotion_id')
                ->join('merchants', 'promotions.merchant_id', '=', 'merchants.merchant_id')
                ->join('timezones', 'merchants.timezone_id', '=', 'timezones.timezone_id')
                ->select('merchants.name AS redeem_retailer_name', 'promotions.*', 'issued_coupons.*', 'timezones.timezone_name')
                ->where('promotions.promotion_type', 'mall')
                ->where('promotions.status', 'active')
                // ->where('issued_coupons.status', '!=', 'deleted');
                ->where('issued_coupons.status', '=', 'active')
                ->where('issued_coupons.expired_date', '>=', DB::raw("CONVERT_TZ('{$nowUTC}','UTC',{$prefix}timezones.timezone_name)"));


            // Filter coupon by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($issuedcoupons) {
                $issuedcoupons->whereIn('promotions.merchant_id', $merchantIds);
            });

            // Filter coupon by Ids
            OrbitInput::get('promotion_id', function($promotionIds) use ($issuedcoupons)
            {
                $issuedcoupons->whereIn('issued_coupons.promotion_id', $promotionIds);
            });

            // Filter coupon by issued coupon code
            OrbitInput::get('issued_coupon_code', function($issuedCouponCode) use ($issuedcoupons)
            {
                $issuedcoupons->whereIn('issued_coupons.issued_coupon_code', $issuedCouponCode);
            });

            // Filter coupon by matching issued coupon code pattern
            OrbitInput::get('issued_coupon_code_like', function($issuedCouponCode) use ($issuedcoupons)
            {
                $issuedcoupons->where('issued_coupons.issued_coupon_code', 'like', "%$issuedCouponCode%");
            });

            // Filter coupon by user Ids
            OrbitInput::get('user_id', function ($userIds) use ($issuedcoupons) {
                $issuedcoupons->whereIn('issued_coupons.user_id', $userIds);
            });

            // Filter coupon by status
            OrbitInput::get('status', function ($statuses) use ($issuedcoupons) {
                $issuedcoupons->whereIn('issued_coupons.status', $statuses);
            });

            // Filter coupon by city
            OrbitInput::get('city', function($city) use ($issuedcoupons)
            {
                $issuedcoupons->whereIn('merchants.city', $city);
            });

            // Filter coupon by matching city pattern
            OrbitInput::get('city_like', function($city) use ($issuedcoupons)
            {
                $issuedcoupons->where('merchants.city', 'like', "%$city%");
            });

            // Filter coupon by redeem retailer Ids
            OrbitInput::get('redeem_retailer_id', function ($redeemRetailerIds) use ($issuedcoupons) {
                $issuedcoupons->whereIn('promotion_retailer_redeem.retailer_id', $redeemRetailerIds);
            });

            OrbitInput::get('with', function ($with) use ($issuedcoupons) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'translations') {
                        $issuedcoupons->with('coupon.translations');
                    } elseif ($relation === 'translations.media') {
                        $issuedcoupons->with('coupon.translations.media');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_issuedcoupons = clone $issuedcoupons;

            // Get the take args
            if (trim(OrbitInput::get('take')) === '') {
                $take = $maxRecord;
            } else {
                OrbitInput::get('take', function($_take) use (&$take, $maxRecord)
                {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;
                });
            }
            if ($take > 0) {
                $issuedcoupons->take($take);
            }

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $issuedcoupons)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $issuedcoupons->skip($skip);
            }

            // Default sort by
            $sortBy = 'redeem_retailer_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'redeem_retailer_name'    => 'redeem_retailer_name',
                    'registered_date'         => 'issued_coupons.created_at',
                    'issued_coupon_code'      => 'issued_coupons.issued_coupon_code',
                    'expired_date'            => 'issued_coupons.expired_date',
                    'promotion_name'          => 'promotions.promotion_name',
                    'promotion_type'          => 'promotions.promotion_type',
                    'description'             => 'promotions.description'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $issuedcoupons->orderBy($sortBy, $sortMode);

            $totalIssuedCoupons = $_issuedcoupons->count();
            $listOfIssuedCoupons = $issuedcoupons->get();

            $data = new stdclass();
            $data->total_records = $totalIssuedCoupons;
            $data->returned_records = count($listOfIssuedCoupons);
            $data->records = $listOfIssuedCoupons;

            if ($totalIssuedCoupons === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.issued_coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.issuedcoupon.getsearchissuedcouponbyredeemretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.issuedcoupon.getsearchissuedcouponbyredeemretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.issuedcoupon.getsearchissuedcouponbyredeemretailer.query.error', array($this, $e));

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
            Event::fire('orbit.issuedcoupon.getsearchissuedcouponbyredeemretailer.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.issuedcoupon.getsearchissuedcouponbyredeemretailer.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of issued coupon id
        Validator::extend('orbit.empty.issued_coupon', function ($attribute, $value, $parameters) {
            $issuedcoupon = IssuedCoupon::excludeDeleted()
                        ->where('issued_coupon_id', $value)
                        ->first();

            if (empty($issuedcoupon)) {
                return FALSE;
            }

            App::instance('orbit.empty.issued_coupon', $issuedcoupon);

            return TRUE;
        });

        // Check the existance of coupon id
        Validator::extend('orbit.empty.coupon', function ($attribute, $value, $parameters) {
            $coupon = Coupon::excludeDeleted()
                        ->where('promotion_id', $value)
                        ->first();

            if (empty($coupon)) {
                return FALSE;
            }

            App::instance('orbit.empty.coupon', $coupon);

            return TRUE;
        });

        // Check the existance of user id
        Validator::extend('orbit.empty.user', function ($attribute, $value, $parameters) {
            $user = User::excludeDeleted()
                        ->where('user_id', $value)
                        ->first();

            if (empty($user)) {
                return FALSE;
            }

            App::instance('orbit.empty.user', $user);

            return TRUE;
        });

        // Check issued coupon code, it should not exists
        Validator::extend('orbit.exists.issued_coupon_code', function ($attribute, $value, $parameters) {
            $coupon = IssuedCoupon::excludeDeleted()
                        ->where('issued_coupon_code', $value)
                        ->first();

            if (! empty($coupon)) {
                return FALSE;
            }

            App::instance('orbit.validation.issued_coupon_code', $coupon);

            return TRUE;
        });

        // Check issued coupon code, it should not exists (for update)
        Validator::extend('issued_coupon_code_exists_but_me', function ($attribute, $value, $parameters) {
            $issued_coupon_id = trim(OrbitInput::post('issued_coupon_id'));
            $coupon = IssuedCoupon::excludeDeleted()
                        ->where('issued_coupon_code', $value)
                        ->where('issued_coupon_id', '!=', $issued_coupon_id)
                        ->first();

            if (! empty($coupon)) {
                return FALSE;
            }

            App::instance('orbit.validation.issued_coupon_code', $coupon);

            return TRUE;
        });

        // Check the existence of the issued coupon status
        Validator::extend('orbit.empty.issued_coupon_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted', 'redeemed');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existance of issuer retailer id
        Validator::extend('orbit.empty.retailer', function ($attribute, $value, $parameters) {
            $retailer = Mall::excludeDeleted()->allowedForUser($this->api->user)
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($retailer)) {
                return FALSE;
            }

            App::instance('orbit.empty.retailer', $retailer);

            return TRUE;
        });

    }

    public function getCouponCodesForCouponId()
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
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $promotionId = OrbitInput::get('promotion_id');
            $type = OrbitInput::get('code_type', 'code');

            $validator = Validator::make(
                array(
                    'promotion_id' => $promotionId,
                    'type' => $type,
                    'sort_by' => $sort_by,
                ),
                array(
                    'promotion_id' => 'required',
                    'type' => 'in:code,url',
                    'sort_by' => 'in:issued_coupon_id,registered_date,issued_coupon_code,expired_date,issued_date,redeemed_date,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.issued_coupon_sortby'),
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $col = $type === 'code' ? 'issued_coupon_code' : 'url';

            $prefix = DB::getTablePrefix();
            // Builder object
            $issuedcoupons = IssuedCoupon::select(DB::raw(`${prefix}${col} as items`))
                ->where('promotion_id', $promotionId)
                ->excludeDeleted();

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_issuedcoupons = clone $issuedcoupons;

            // Default sort by
            $sortBy = 'issued_coupons.issued_coupon_code';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'issued_coupon_id'   => 'issued_coupons.issued_coupon_id',
                    'registered_date'    => 'issued_coupons.created_at',
                    'issued_coupon_code' => 'issued_coupons.issued_coupon_code',
                    'expired_date'       => 'issued_coupons.expired_date',
                    'issued_date'        => 'issued_coupons.issued_date',
                    'redeemed_date'      => 'issued_coupons.redeemed_date',
                    'status'             => 'issued_coupons.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $issuedcoupons->orderBy($sortBy, $sortMode);

            $listOfIssuedCoupons = $issuedcoupons->get();

            $data = null;

            if (count($listOfIssuedCoupons) > 0) {
                foreach ($listOfIssuedCoupons as $issuedCoupon) {
                    $data = $data . $issuedCoupon->items . "\n";
                }
                $data = rtrim($data);
            } else {
                $this->response->message = Lang::get('statuses.orbit.nodata.issued_coupon');
            }

            $this->response->data = $data;
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
