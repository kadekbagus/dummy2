<?php
/**
 * An API controller for managing Coupon.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class CouponAPIController extends ControllerAPI
{

    /**
     * POST - Create New Coupon
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                       (required) - Merchant ID
     * @param string     `promotion_name`                    (required) - Coupon name
     * @param string     `promotion_type`                    (required) - Coupon type. Valid value: product, cart.
     * @param string     `status`                            (required) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`                       (optional) - Description
     * @param datetime   `begin_date`                        (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime   `end_date`                          (optional) - End date. Example: 2014-12-31 23:59:59
     * @param string     `is_permanent`                      (optional) - Is permanent. Valid value: Y, N.
     * @param file       `images`                            (optional) - Coupon image
     * @param integer    `maximum_issued_coupon`             (optional) - Maximum issued coupon
     * @param integer    `coupon_validity_in_days`           (optional) - Coupon validity in days
     * @param string     `coupon_notification`               (optional) - Coupon notification. Valid value: Y, N.
     * @param string     `rule_type`                         (optional) - Rule type. Valid value: cart_discount_by_value, cart_discount_by_percentage, new_product_price, product_discount_by_value, product_discount_by_percentage.
     * @param decimal    `rule_value`                        (optional) - Rule value
     * @param string     `rule_object_type`                  (optional) - Rule object type. Valid value: product, family.
     * @param integer    `rule_object_id1`                   (optional) - Rule object ID1 (product_id or category_id1).
     * @param integer    `rule_object_id2`                   (optional) - Rule object ID2 (category_id2).
     * @param integer    `rule_object_id3`                   (optional) - Rule object ID3 (category_id3).
     * @param integer    `rule_object_id4`                   (optional) - Rule object ID4 (category_id4).
     * @param integer    `rule_object_id5`                   (optional) - Rule object ID5 (category_id5).
     * @param string     `discount_object_type`              (optional) - Discount object type. Valid value: product, family, cash_rebate.
     * @param integer    `discount_object_id1`               (optional) - Discount object ID1 (product_id or category_id1).
     * @param integer    `discount_object_id2`               (optional) - Discount object ID2 (category_id2).
     * @param integer    `discount_object_id3`               (optional) - Discount object ID3 (category_id3).
     * @param integer    `discount_object_id4`               (optional) - Discount object ID4 (category_id4).
     * @param integer    `discount_object_id5`               (optional) - Discount object ID5 (category_id5).
     * @param decimal    `discount_value`                    (optional) - Discount value
     * @param string     `is_cumulative_with_coupons`        (optional) - Cumulative with other coupons. Valid value: Y, N.
     * @param string     `is_cumulative_with_promotions`     (optional) - Cumulative with other promotions. Valid value: Y, N.
     * @param decimal    `coupon_redeem_rule_value`          (optional) - Coupon redeem rule value
     * @param array      `issue_retailer_ids`                (optional) - Issue Retailer IDs
     * @param array      `redeem_retailer_ids`               (optional) - Redeem Retailer IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewCoupon()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newcoupon = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.coupon.postnewcoupon.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.coupon.postnewcoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.coupon.postnewcoupon.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_coupon')) {
                Event::fire('orbit.coupon.postnewcoupon.authz.notallowed', array($this, $user));
                $createCouponLang = Lang::get('validation.orbit.actionlist.new_coupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.coupon.postnewcoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('merchant_id');
            $promotion_name = OrbitInput::post('promotion_name');
            $promotion_type = OrbitInput::post('promotion_type');
            $status = OrbitInput::post('status');
            $description = OrbitInput::post('description');
            $begin_date = OrbitInput::post('begin_date');
            $end_date = OrbitInput::post('end_date');
            $is_permanent = OrbitInput::post('is_permanent');
            $maximum_issued_coupon = OrbitInput::post('maximum_issued_coupon');
            $coupon_validity_in_days = OrbitInput::post('coupon_validity_in_days');
            $coupon_notification = OrbitInput::post('coupon_notification');
            $rule_type = OrbitInput::post('rule_type');
            $rule_value = OrbitInput::post('rule_value');
            $rule_object_type = OrbitInput::post('rule_object_type');
            $rule_object_id1 = OrbitInput::post('rule_object_id1');
            $rule_object_id2 = OrbitInput::post('rule_object_id2');
            $rule_object_id3 = OrbitInput::post('rule_object_id3');
            $rule_object_id4 = OrbitInput::post('rule_object_id4');
            $rule_object_id5 = OrbitInput::post('rule_object_id5');
            $discount_object_type = OrbitInput::post('discount_object_type');
            $discount_object_id1 = OrbitInput::post('discount_object_id1');
            $discount_object_id2 = OrbitInput::post('discount_object_id2');
            $discount_object_id3 = OrbitInput::post('discount_object_id3');
            $discount_object_id4 = OrbitInput::post('discount_object_id4');
            $discount_object_id5 = OrbitInput::post('discount_object_id5');
            $discount_value = OrbitInput::post('discount_value');
            $is_cumulative_with_coupons = OrbitInput::post('is_cumulative_with_coupons');
            $is_cumulative_with_promotions = OrbitInput::post('is_cumulative_with_promotions');
            $coupon_redeem_rule_value = OrbitInput::post('coupon_redeem_rule_value');
            $issue_retailer_ids = OrbitInput::post('issue_retailer_ids');
            $issue_retailer_ids = (array) $issue_retailer_ids;
            $redeem_retailer_ids = OrbitInput::post('redeem_retailer_ids');
            $redeem_retailer_ids = (array) $redeem_retailer_ids;

            $validator = Validator::make(
                array(
                    'merchant_id'          => $merchant_id,
                    'promotion_name'       => $promotion_name,
                    'promotion_type'       => $promotion_type,
                    'status'               => $status,
                    'rule_type'            => $rule_type,
                    'rule_object_type'     => $rule_object_type,
                    'rule_object_id1'      => $rule_object_id1,
                    'rule_object_id2'      => $rule_object_id2,
                    'rule_object_id3'      => $rule_object_id3,
                    'rule_object_id4'      => $rule_object_id4,
                    'rule_object_id5'      => $rule_object_id5,
                    'discount_object_type' => $discount_object_type,
                    'discount_object_id1'  => $discount_object_id1,
                    'discount_object_id2'  => $discount_object_id2,
                    'discount_object_id3'  => $discount_object_id3,
                    'discount_object_id4'  => $discount_object_id4,
                    'discount_object_id5'  => $discount_object_id5,
                ),
                array(
                    'merchant_id'          => 'required|numeric|orbit.empty.merchant',
                    'promotion_name'       => 'required|max:100|orbit.exists.coupon_name',
                    'promotion_type'       => 'required|orbit.empty.coupon_type',
                    'status'               => 'required|orbit.empty.coupon_status',
                    'rule_type'            => 'orbit.empty.rule_type',
                    'rule_object_type'     => 'orbit.empty.rule_object_type',
                    'rule_object_id1'      => 'numeric|orbit.empty.rule_object_id1',
                    'rule_object_id2'      => 'numeric|orbit.empty.rule_object_id2',
                    'rule_object_id3'      => 'numeric|orbit.empty.rule_object_id3',
                    'rule_object_id4'      => 'numeric|orbit.empty.rule_object_id4',
                    'rule_object_id5'      => 'numeric|orbit.empty.rule_object_id5',
                    'discount_object_type' => 'orbit.empty.discount_object_type',
                    'discount_object_id1'  => 'numeric|orbit.empty.discount_object_id1',
                    'discount_object_id2'  => 'numeric|orbit.empty.discount_object_id2',
                    'discount_object_id3'  => 'numeric|orbit.empty.discount_object_id3',
                    'discount_object_id4'  => 'numeric|orbit.empty.discount_object_id4',
                    'discount_object_id5'  => 'numeric|orbit.empty.discount_object_id5',
                )
            );

            Event::fire('orbit.coupon.postnewcoupon.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // validating issue_retailer_ids.
            foreach ($issue_retailer_ids as $retailer_id_check) {
                $validator = Validator::make(
                    array(
                        'retailer_id'   => $retailer_id_check,

                    ),
                    array(
                        'retailer_id'   => 'numeric|orbit.empty.retailer',
                    )
                );

                Event::fire('orbit.coupon.postnewcoupon.before.issueretailervalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.coupon.postnewcoupon.after.issueretailervalidation', array($this, $validator));
            }

            // validating redeem_retailer_ids.
            foreach ($redeem_retailer_ids as $retailer_id_check) {
                $validator = Validator::make(
                    array(
                        'retailer_id'   => $retailer_id_check,

                    ),
                    array(
                        'retailer_id'   => 'numeric|orbit.empty.retailer',
                    )
                );

                Event::fire('orbit.coupon.postnewcoupon.before.redeemretailervalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.coupon.postnewcoupon.after.redeemretailervalidation', array($this, $validator));
            }

            Event::fire('orbit.coupon.postnewcoupon.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // save Coupon.
            $newcoupon = new Coupon();
            $newcoupon->merchant_id = $merchant_id;
            $newcoupon->promotion_name = $promotion_name;
            $newcoupon->promotion_type = $promotion_type;
            $newcoupon->status = $status;
            $newcoupon->description = $description;
            $newcoupon->begin_date = $begin_date;
            $newcoupon->end_date = $end_date;
            $newcoupon->is_permanent = $is_permanent;
            $newcoupon->maximum_issued_coupon = $maximum_issued_coupon;
            $newcoupon->coupon_validity_in_days = $coupon_validity_in_days;
            $newcoupon->coupon_notification = $coupon_notification;
            $newcoupon->created_by = $this->api->user->user_id;

            Event::fire('orbit.coupon.postnewcoupon.before.save', array($this, $newcoupon));

            $newcoupon->save();

            // save CouponRule.
            $couponrule = new CouponRule();
            $couponrule->rule_type = $rule_type;
            $couponrule->rule_value = $rule_value;
            $couponrule->rule_object_type = $rule_object_type;

            // rule_object_id1
            if (trim($rule_object_id1) === '') {
                $couponrule->rule_object_id1 = NULL;
            } else {
                $couponrule->rule_object_id1 = $rule_object_id1;
            }

            // rule_object_id2
            if (trim($rule_object_id2) === '') {
                $couponrule->rule_object_id2 = NULL;
            } else {
                $couponrule->rule_object_id2 = $rule_object_id2;
            }

            // rule_object_id3
            if (trim($rule_object_id3) === '') {
                $couponrule->rule_object_id3 = NULL;
            } else {
                $couponrule->rule_object_id3 = $rule_object_id3;
            }

            // rule_object_id4
            if (trim($rule_object_id4) === '') {
                $couponrule->rule_object_id4 = NULL;
            } else {
                $couponrule->rule_object_id4 = $rule_object_id4;
            }

            // rule_object_id5
            if (trim($rule_object_id5) === '') {
                $couponrule->rule_object_id5 = NULL;
            } else {
                $couponrule->rule_object_id5 = $rule_object_id5;
            }

            $couponrule->discount_object_type = $discount_object_type;

            // discount_object_id1
            if (trim($discount_object_id1) === '') {
                $couponrule->discount_object_id1 = NULL;
            } else {
                $couponrule->discount_object_id1 = $discount_object_id1;
            }

            // discount_object_id2
            if (trim($discount_object_id2) === '') {
                $couponrule->discount_object_id2 = NULL;
            } else {
                $couponrule->discount_object_id2 = $discount_object_id2;
            }

            // discount_object_id3
            if (trim($discount_object_id3) === '') {
                $couponrule->discount_object_id3 = NULL;
            } else {
                $couponrule->discount_object_id3 = $discount_object_id3;
            }

            // discount_object_id4
            if (trim($discount_object_id4) === '') {
                $couponrule->discount_object_id4 = NULL;
            } else {
                $couponrule->discount_object_id4 = $discount_object_id4;
            }

            // discount_object_id5
            if (trim($discount_object_id5) === '') {
                $couponrule->discount_object_id5 = NULL;
            } else {
                $couponrule->discount_object_id5 = $discount_object_id5;
            }

            $couponrule->discount_value = $discount_value;
            $couponrule->is_cumulative_with_coupons = $is_cumulative_with_coupons;
            $couponrule->is_cumulative_with_promotions = $is_cumulative_with_promotions;
            $couponrule->coupon_redeem_rule_value = $coupon_redeem_rule_value;
            $couponrule = $newcoupon->couponrule()->save($couponrule);
            $newcoupon->couponrule = $couponrule;

            // save CouponRetailer (issue retailers).
            $issueretailers = array();
            foreach ($issue_retailer_ids as $retailer_id) {
                $issueretailer = new CouponRetailer();
                $issueretailer->retailer_id = $retailer_id;
                $issueretailer->promotion_id = $newcoupon->promotion_id;
                $issueretailer->save();
                $issueretailers[] = $issueretailer;
            }
            $newcoupon->issueretailers = $issueretailers;

            // save CouponRetailerRedeem (redeem retailers).
            $redeemretailers = array();
            foreach ($redeem_retailer_ids as $retailer_id) {
                $redeemretailer = new CouponRetailerRedeem();
                $redeemretailer->retailer_id = $retailer_id;
                $redeemretailer->promotion_id = $newcoupon->promotion_id;
                $redeemretailer->save();
                $redeemretailers[] = $redeemretailer;
            }
            $newcoupon->redeemretailers = $redeemretailers;

            Event::fire('orbit.coupon.postnewcoupon.after.save', array($this, $newcoupon));
            $this->response->data = $newcoupon;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Coupon Created: %s', $newcoupon->promotion_name);
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon OK')
                    ->setObject($newcoupon)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.coupon.postnewcoupon.after.commit', array($this, $newcoupon));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.postnewcoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.postnewcoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.postnewcoupon.query.error', array($this, $e));

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

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.coupon.postnewcoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_coupon')
                    ->setActivityNameLong('Create Coupon Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update Coupon
     *
     * @author <Tian> <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                      (required) - Coupon ID
     * @param integer    `merchant_id`                       (optional) - Merchant ID
     * @param string     `promotion_name`                    (optional) - Coupon name
     * @param string     `promotion_type`                    (optional) - Coupon type. Valid value: product, cart.
     * @param string     `status`                            (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`                       (optional) - Description
     * @param datetime   `begin_date`                        (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime   `end_date`                          (optional) - End date. Example: 2014-12-31 23:59:59
     * @param string     `is_permanent`                      (optional) - Is permanent. Valid value: Y, N.
     * @param file       `images`                            (optional) - Coupon image
     * @param integer    `maximum_issued_coupon`             (optional) - Maximum issued coupon
     * @param integer    `coupon_validity_in_days`           (optional) - Coupon validity in days
     * @param string     `coupon_notification`               (optional) - Coupon notification. Valid value: Y, N.
     * @param string     `rule_type`                         (optional) - Rule type. Valid value: cart_discount_by_value, cart_discount_by_percentage, new_product_price, product_discount_by_value, product_discount_by_percentage.
     * @param decimal    `rule_value`                        (optional) - Rule value
     * @param string     `rule_object_type`                  (optional) - Rule object type. Valid value: product, family.
     * @param integer    `rule_object_id1`                   (optional) - Rule object ID1 (product_id or category_id1).
     * @param integer    `rule_object_id2`                   (optional) - Rule object ID2 (category_id2).
     * @param integer    `rule_object_id3`                   (optional) - Rule object ID3 (category_id3).
     * @param integer    `rule_object_id4`                   (optional) - Rule object ID4 (category_id4).
     * @param integer    `rule_object_id5`                   (optional) - Rule object ID5 (category_id5).
     * @param string     `discount_object_type`              (optional) - Discount object type. Valid value: product, family.
     * @param integer    `discount_object_id1`               (optional) - Discount object ID1 (product_id or category_id1).
     * @param integer    `discount_object_id2`               (optional) - Discount object ID2 (category_id2).
     * @param integer    `discount_object_id3`               (optional) - Discount object ID3 (category_id3).
     * @param integer    `discount_object_id4`               (optional) - Discount object ID4 (category_id4).
     * @param integer    `discount_object_id5`               (optional) - Discount object ID5 (category_id5).
     * @param decimal    `discount_value`                    (optional) - Discount value
     * @param string     `is_cumulative_with_coupons`        (optional) - Cumulative with other coupons. Valid value: Y, N.
     * @param string     `is_cumulative_with_promotions`     (optional) - Cumulative with other promotions. Valid value: Y, N.
     * @param decimal    `coupon_redeem_rule_value`          (optional) - Coupon redeem rule value
     * @param array      `issue_retailer_ids`                (optional) - Issue Retailer IDs
     * @param array      `redeem_retailer_ids`               (optional) - Redeem Retailer IDs
     * @param string     `no_issue_retailer`                 (optional) - Flag to delete all issue retailer links. Valid value: Y.
     * @param string     `no_redeem_retailer`                (optional) - Flag to delete all redeem retailer links. Valid value: Y.
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateCoupon()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedcoupon = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.coupon.postupdatecoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.coupon.postupdatecoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.coupon.postupdatecoupon.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_coupon')) {
                Event::fire('orbit.coupon.postupdatecoupon.authz.notallowed', array($this, $user));
                $updateCouponLang = Lang::get('validation.orbit.actionlist.update_coupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.coupon.postupdatecoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $promotion_id = OrbitInput::post('promotion_id');
            $merchant_id = OrbitInput::post('merchant_id');
            $promotion_type = OrbitInput::post('promotion_type');
            $status = OrbitInput::post('status');
            $rule_type = OrbitInput::post('rule_type');
            $rule_object_type = OrbitInput::post('rule_object_type');
            $rule_object_id1 = OrbitInput::post('rule_object_id1');
            $rule_object_id2 = OrbitInput::post('rule_object_id2');
            $rule_object_id3 = OrbitInput::post('rule_object_id3');
            $rule_object_id4 = OrbitInput::post('rule_object_id4');
            $rule_object_id5 = OrbitInput::post('rule_object_id5');
            $discount_object_type = OrbitInput::post('discount_object_type');
            $discount_object_id1 = OrbitInput::post('discount_object_id1');
            $discount_object_id2 = OrbitInput::post('discount_object_id2');
            $discount_object_id3 = OrbitInput::post('discount_object_id3');
            $discount_object_id4 = OrbitInput::post('discount_object_id4');
            $discount_object_id5 = OrbitInput::post('discount_object_id5');

            $data = array(
                'promotion_id'         => $promotion_id,
                'merchant_id'          => $merchant_id,
                'promotion_type'       => $promotion_type,
                'status'               => $status,
                'rule_type'            => $rule_type,
                'rule_object_type'     => $rule_object_type,
                'rule_object_id1'      => $rule_object_id1,
                'rule_object_id2'      => $rule_object_id2,
                'rule_object_id3'      => $rule_object_id3,
                'rule_object_id4'      => $rule_object_id4,
                'rule_object_id5'      => $rule_object_id5,
                'discount_object_type' => $discount_object_type,
                'discount_object_id1'  => $discount_object_id1,
                'discount_object_id2'  => $discount_object_id2,
                'discount_object_id3'  => $discount_object_id3,
                'discount_object_id4'  => $discount_object_id4,
                'discount_object_id5'  => $discount_object_id5,
            );

            // Validate promotion_name only if exists in POST.
            OrbitInput::post('promotion_name', function($promotion_name) use (&$data) {
                $data['promotion_name'] = $promotion_name;
            });

            $validator = Validator::make(
                $data,
                array(
                    'promotion_id'         => 'required|numeric|orbit.empty.coupon',
                    'merchant_id'          => 'numeric|orbit.empty.merchant',
                    'promotion_name'       => 'sometimes|required|min:5|max:100|coupon_name_exists_but_me',
                    'promotion_type'       => 'orbit.empty.coupon_type',
                    'status'               => 'orbit.empty.coupon_status',
                    'rule_type'            => 'orbit.empty.rule_type',
                    'rule_object_type'     => 'orbit.empty.rule_object_type',
                    'rule_object_id1'      => 'numeric|orbit.empty.rule_object_id1',
                    'rule_object_id2'      => 'numeric|orbit.empty.rule_object_id2',
                    'rule_object_id3'      => 'numeric|orbit.empty.rule_object_id3',
                    'rule_object_id4'      => 'numeric|orbit.empty.rule_object_id4',
                    'rule_object_id5'      => 'numeric|orbit.empty.rule_object_id5',
                    'discount_object_type' => 'orbit.empty.discount_object_type',
                    'discount_object_id1'  => 'numeric|orbit.empty.discount_object_id1',
                    'discount_object_id2'  => 'numeric|orbit.empty.discount_object_id2',
                    'discount_object_id3'  => 'numeric|orbit.empty.discount_object_id3',
                    'discount_object_id4'  => 'numeric|orbit.empty.discount_object_id4',
                    'discount_object_id5'  => 'numeric|orbit.empty.discount_object_id5',
                ),
                array(
                   'coupon_name_exists_but_me' => Lang::get('validation.orbit.exists.coupon_name'),
                )
            );

            Event::fire('orbit.coupon.postupdatecoupon.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.coupon.postupdatecoupon.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updatedcoupon = Coupon::with('couponrule', 'issueretailers', 'redeemretailers')->excludeDeleted()->allowedForUser($user)->where('promotion_id', $promotion_id)->first();

            // save Coupon.
            OrbitInput::post('merchant_id', function($merchant_id) use ($updatedcoupon) {
                $updatedcoupon->merchant_id = $merchant_id;
            });

            OrbitInput::post('promotion_name', function($promotion_name) use ($updatedcoupon) {
                $updatedcoupon->promotion_name = $promotion_name;
            });

            OrbitInput::post('promotion_type', function($promotion_type) use ($updatedcoupon) {
                $updatedcoupon->promotion_type = $promotion_type;
            });

            OrbitInput::post('status', function($status) use ($updatedcoupon) {
                $updatedcoupon->status = $status;
            });

            OrbitInput::post('description', function($description) use ($updatedcoupon) {
                $updatedcoupon->description = $description;
            });

            OrbitInput::post('begin_date', function($begin_date) use ($updatedcoupon) {
                $updatedcoupon->begin_date = $begin_date;
            });

            OrbitInput::post('end_date', function($end_date) use ($updatedcoupon) {
                $updatedcoupon->end_date = $end_date;
            });

            OrbitInput::post('is_permanent', function($is_permanent) use ($updatedcoupon) {
                $updatedcoupon->is_permanent = $is_permanent;
            });

            OrbitInput::post('maximum_issued_coupon', function($maximum_issued_coupon) use ($updatedcoupon) {
                $updatedcoupon->maximum_issued_coupon = $maximum_issued_coupon;
            });

            OrbitInput::post('coupon_validity_in_days', function($coupon_validity_in_days) use ($updatedcoupon) {
                $updatedcoupon->coupon_validity_in_days = $coupon_validity_in_days;
            });

            OrbitInput::post('coupon_notification', function($coupon_notification) use ($updatedcoupon) {
                $updatedcoupon->coupon_notification = $coupon_notification;
            });

            $updatedcoupon->modified_by = $this->api->user->user_id;

            Event::fire('orbit.coupon.postupdatecoupon.before.save', array($this, $updatedcoupon));

            $updatedcoupon->save();


            // save CouponRule.
            $couponrule = CouponRule::where('promotion_id', '=', $promotion_id)->first();
            OrbitInput::post('rule_type', function($rule_type) use ($couponrule) {
                if (trim($rule_type) === '') {
                    $rule_type = NULL;
                }
                $couponrule->rule_type = $rule_type;
            });

            OrbitInput::post('rule_value', function($rule_value) use ($couponrule) {
                $couponrule->rule_value = $rule_value;
            });

            OrbitInput::post('rule_object_type', function($rule_object_type) use ($couponrule) {
                if (trim($rule_object_type) === '') {
                    $rule_object_type = NULL;
                }
                $couponrule->rule_object_type = $rule_object_type;
            });

            OrbitInput::post('rule_object_id1', function($rule_object_id1) use ($couponrule) {
                if (trim($rule_object_id1) === '') {
                    $rule_object_id1 = NULL;
                }
                $couponrule->rule_object_id1 = $rule_object_id1;
            });

            OrbitInput::post('rule_object_id2', function($rule_object_id2) use ($couponrule) {
                if (trim($rule_object_id2) === '') {
                    $rule_object_id2 = NULL;
                }
                $couponrule->rule_object_id2 = $rule_object_id2;
            });

            OrbitInput::post('rule_object_id3', function($rule_object_id3) use ($couponrule) {
                if (trim($rule_object_id3) === '') {
                    $rule_object_id3 = NULL;
                }
                $couponrule->rule_object_id3 = $rule_object_id3;
            });

            OrbitInput::post('rule_object_id4', function($rule_object_id4) use ($couponrule) {
                if (trim($rule_object_id4) === '') {
                    $rule_object_id4 = NULL;
                }
                $couponrule->rule_object_id4 = $rule_object_id4;
            });

            OrbitInput::post('rule_object_id5', function($rule_object_id5) use ($couponrule) {
                if (trim($rule_object_id5) === '') {
                    $rule_object_id5 = NULL;
                }
                $couponrule->rule_object_id5 = $rule_object_id5;
            });

            OrbitInput::post('discount_object_type', function($discount_object_type) use ($couponrule) {
                if (trim($discount_object_type) === '') {
                    $discount_object_type = NULL;
                }
                $couponrule->discount_object_type = $discount_object_type;
            });

            OrbitInput::post('discount_object_id1', function($discount_object_id1) use ($couponrule) {
                if (trim($discount_object_id1) === '') {
                    $discount_object_id1 = NULL;
                }
                $couponrule->discount_object_id1 = $discount_object_id1;
            });

            OrbitInput::post('discount_object_id2', function($discount_object_id2) use ($couponrule) {
                if (trim($discount_object_id2) === '') {
                    $discount_object_id2 = NULL;
                }
                $couponrule->discount_object_id2 = $discount_object_id2;
            });

            OrbitInput::post('discount_object_id3', function($discount_object_id3) use ($couponrule) {
                if (trim($discount_object_id3) === '') {
                    $discount_object_id3 = NULL;
                }
                $couponrule->discount_object_id3 = $discount_object_id3;
            });

            OrbitInput::post('discount_object_id4', function($discount_object_id4) use ($couponrule) {
                if (trim($discount_object_id4) === '') {
                    $discount_object_id4 = NULL;
                }
                $couponrule->discount_object_id4 = $discount_object_id4;
            });

            OrbitInput::post('discount_object_id5', function($discount_object_id5) use ($couponrule) {
                if (trim($discount_object_id5) === '') {
                    $discount_object_id5 = NULL;
                }
                $couponrule->discount_object_id5 = $discount_object_id5;
            });

            OrbitInput::post('discount_value', function($discount_value) use ($couponrule) {
                $couponrule->discount_value = $discount_value;
            });

            OrbitInput::post('is_cumulative_with_coupons', function($is_cumulative_with_coupons) use ($couponrule) {
                $couponrule->is_cumulative_with_coupons = $is_cumulative_with_coupons;
            });

            OrbitInput::post('is_cumulative_with_promotions', function($is_cumulative_with_promotions) use ($couponrule) {
                $couponrule->is_cumulative_with_promotions = $is_cumulative_with_promotions;
            });

            OrbitInput::post('coupon_redeem_rule_value', function($coupon_redeem_rule_value) use ($couponrule) {
                $couponrule->coupon_redeem_rule_value = $coupon_redeem_rule_value;
            });

            $couponrule->save();
            $updatedcoupon->setRelation('couponrule', $couponrule);
            $updatedcoupon->couponrule = $couponrule;


            // save CouponRetailer (issue retailer) and CouponRetailerRedeem (redeem retailer).
            OrbitInput::post('no_issue_retailer', function($no_issue_retailer) use ($updatedcoupon) {
                if ($no_issue_retailer == 'Y') {
                    $deleted_issue_retailer_ids = CouponRetailer::where('promotion_id', $updatedcoupon->promotion_id)->get(array('retailer_id'))->toArray();
                    $updatedcoupon->issueretailers()->detach($deleted_issue_retailer_ids);
                    $updatedcoupon->load('issueretailers');
                }
            });

            OrbitInput::post('no_redeem_retailer', function($no_redeem_retailer) use ($updatedcoupon) {
                if ($no_redeem_retailer == 'Y') {
                    $deleted_redeem_retailer_ids = CouponRetailerRedeem::where('promotion_id', $updatedcoupon->promotion_id)->get(array('retailer_id'))->toArray();
                    $updatedcoupon->redeemretailers()->detach($deleted_redeem_retailer_ids);
                    $updatedcoupon->load('redeemretailers');
                }
            });

            OrbitInput::post('issue_retailer_ids', function($issue_retailer_ids) use ($updatedcoupon) {
                // validate issue_retailer_ids
                $issue_retailer_ids = (array) $issue_retailer_ids;
                foreach ($issue_retailer_ids as $retailer_id_check) {
                    $validator = Validator::make(
                        array(
                            'retailer_id'   => $retailer_id_check,
                        ),
                        array(
                            'retailer_id'   => 'orbit.empty.retailer',
                        )
                    );

                    Event::fire('orbit.coupon.postupdatecoupon.before.issueretailervalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.coupon.postupdatecoupon.after.issueretailervalidation', array($this, $validator));
                }
                // sync new set of retailer ids
                $updatedcoupon->issueretailers()->sync($issue_retailer_ids);

                // reload issueretailers relation
                $updatedcoupon->load('issueretailers');
            });

            OrbitInput::post('redeem_retailer_ids', function($redeem_retailer_ids) use ($updatedcoupon) {
                // validate redeem_retailer_ids
                $redeem_retailer_ids = (array) $redeem_retailer_ids;
                foreach ($redeem_retailer_ids as $retailer_id_check) {
                    $validator = Validator::make(
                        array(
                            'retailer_id'   => $retailer_id_check,
                        ),
                        array(
                            'retailer_id'   => 'orbit.empty.retailer',
                        )
                    );

                    Event::fire('orbit.coupon.postupdatecoupon.before.redeemretailervalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.coupon.postupdatecoupon.after.redeemretailervalidation', array($this, $validator));
                }
                // sync new set of retailer ids
                $updatedcoupon->redeemretailers()->sync($redeem_retailer_ids);

                // reload redeemretailers relation
                $updatedcoupon->load('redeemretailers');
            });

            Event::fire('orbit.coupon.postupdatecoupon.after.save', array($this, $updatedcoupon));
            $this->response->data = $updatedcoupon;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Coupon updated: %s', $updatedcoupon->promotion_name);
            $activity->setUser($user)
                    ->setActivityName('update_coupon')
                    ->setActivityNameLong('Update Coupon OK')
                    ->setObject($updatedcoupon)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.coupon.postupdatecoupon.after.commit', array($this, $updatedcoupon));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.postupdatecoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_coupon')
                    ->setActivityNameLong('Update Coupon Failed')
                    ->setObject($updatedcoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.postupdatecoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_coupon')
                    ->setActivityNameLong('Update Coupon Failed')
                    ->setObject($updatedcoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.postupdatecoupon.query.error', array($this, $e));

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

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_coupon')
                    ->setActivityNameLong('Update Coupon Failed')
                    ->setObject($updatedcoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.coupon.postupdatecoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_coupon')
                    ->setActivityNameLong('Update Coupon Failed')
                    ->setObject($updatedcoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    /**
     * POST - Delete Coupon
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                  (required) - ID of the coupon
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteCoupon()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deletecoupon = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.coupon.postdeletecoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.coupon.postdeletecoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.coupon.postdeletecoupon.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_coupon')) {
                Event::fire('orbit.coupon.postdeletecoupon.authz.notallowed', array($this, $user));
                $deleteCouponLang = Lang::get('validation.orbit.actionlist.delete_coupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.coupon.postdeletecoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $promotion_id = OrbitInput::post('promotion_id');

            $validator = Validator::make(
                array(
                    'promotion_id' => $promotion_id,
                ),
                array(
                    'promotion_id' => 'required|numeric|orbit.empty.coupon',
                )
            );

            Event::fire('orbit.coupon.postdeletecoupon.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.coupon.postdeletecoupon.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $deletecoupon = Coupon::excludeDeleted()->allowedForUser($user)->where('promotion_id', $promotion_id)->first();
            $deletecoupon->status = 'deleted';
            $deletecoupon->modified_by = $this->api->user->user_id;

            Event::fire('orbit.coupon.postdeletecoupon.before.save', array($this, $deletecoupon));

            // hard delete issueretailer.
            $deleteissueretailers = CouponRetailer::where('promotion_id', $deletecoupon->promotion_id)->get();
            foreach ($deleteissueretailers as $deleteissueretailer) {
                $deleteissueretailer->delete();
            }

            // hard delete redeemretailer.
            $deleteredeemretailers = CouponRetailerRedeem::where('promotion_id', $deletecoupon->promotion_id)->get();
            foreach ($deleteredeemretailers as $deleteredeemretailer) {
                $deleteredeemretailer->delete();
            }

            $deletecoupon->save();

            Event::fire('orbit.coupon.postdeletecoupon.after.save', array($this, $deletecoupon));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.coupon');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Coupon Deleted: %s', $deletecoupon->promotion_name);
            $activity->setUser($user)
                    ->setActivityName('delete_coupon')
                    ->setActivityNameLong('Delete Coupon OK')
                    ->setObject($deletecoupon)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.coupon.postdeletecoupon.after.commit', array($this, $deletecoupon));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.postdeletecoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_coupon')
                    ->setActivityNameLong('Delete Coupon Failed')
                    ->setObject($deletecoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.postdeletecoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_coupon')
                    ->setActivityNameLong('Delete Coupon Failed')
                    ->setObject($deletecoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.postdeletecoupon.query.error', array($this, $e));

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

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_coupon')
                    ->setActivityNameLong('Delete Coupon Failed')
                    ->setObject($deletecoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.coupon.postdeletecoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_coupon')
                    ->setActivityNameLong('Delete Coupon Failed')
                    ->setObject($deletecoupon)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);

        // Save the activity
        $activity->save();

        return $output;
    }

    /**
     * GET - Search Coupon
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                  (optional) - Valid value: retailers, product, family.
     * @param string   `sortby`                (optional) - Column order by. Valid value: registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status, rule_type, display_discount_value.
     * @param string   `sortmode`              (optional) - ASC or DESC
     * @param integer  `take`                  (optional) - Limit
     * @param integer  `skip`                  (optional) - Limit offset
     * @param integer  `promotion_id`          (optional) - Coupon ID
     * @param integer  `merchant_id`           (optional) - Merchant ID
     * @param string   `promotion_name`        (optional) - Coupon name
     * @param string   `promotion_name_like`   (optional) - Coupon name like
     * @param string   `promotion_type`        (optional) - Coupon type. Valid value: product, cart.
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param datetime `begin_date`            (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime `end_date`              (optional) - End date. Example: 2014-12-31 23:59:59
     * @param string   `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param string   `coupon_notification`   (optional) - Coupon notification. Valid value: Y, N.
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `rule_type`             (optional) - Rule type. Valid value: cart_discount_by_value, cart_discount_by_percentage, new_product_price, product_discount_by_value, product_discount_by_percentage.
     * @param string   `rule_object_type`      (optional) - Rule object type. Valid value: product, family.
     * @param integer  `rule_object_id1`       (optional) - Rule object ID1 (product_id or category_id1).
     * @param integer  `rule_object_id2`       (optional) - Rule object ID2 (category_id2).
     * @param integer  `rule_object_id3`       (optional) - Rule object ID3 (category_id3).
     * @param integer  `rule_object_id4`       (optional) - Rule object ID4 (category_id4).
     * @param integer  `rule_object_id5`       (optional) - Rule object ID5 (category_id5).
     * @param string   `discount_object_type`  (optional) - Discount object type. Valid value: product, family, cash_rebate.
     * @param integer  `discount_object_id1`   (optional) - Discount object ID1 (product_id or category_id1).
     * @param integer  `discount_object_id2`   (optional) - Discount object ID2 (category_id2).
     * @param integer  `discount_object_id3`   (optional) - Discount object ID3 (category_id3).
     * @param integer  `discount_object_id4`   (optional) - Discount object ID4 (category_id4).
     * @param integer  `discount_object_id5`   (optional) - Discount object ID5 (category_id5).
     * @param integer  `issue_retailer_id`     (optional) - Issue Retailer IDs
     * @param integer  `redeem_retailer_id`    (optional) - Redeem Retailer IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchCoupon()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.coupon.getsearchcoupon.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.coupon.getsearchcoupon.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.coupon.getsearchcoupon.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_coupon')) {
                Event::fire('orbit.coupon.getsearchcoupon.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_coupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.coupon.getsearchcoupon.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,promotion_name,promotion_type,description,begin_date,end_date,is_permanent,status,rule_type,display_discount_value',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.coupon_sortby'),
                )
            );

            Event::fire('orbit.coupon.getsearchcoupon.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.coupon.getsearchcoupon.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.coupon.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.coupon.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $table_prefix = DB::getTablePrefix();
            // Builder object
            // Addition select case and join for sorting by discount_value.
            $coupons = Coupon::with('couponrule')
                ->excludeDeleted()
                ->allowedForViewOnly($user)
                ->select(DB::raw($table_prefix . "promotions.*,
                    CASE rule_type
                        WHEN 'cart_discount_by_percentage' THEN 'percentage'
                        WHEN 'product_discount_by_percentage' THEN 'percentage'
                        WHEN 'cart_discount_by_value' THEN 'value'
                        WHEN 'product_discount_by_value' THEN 'value'
                        ELSE NULL
                    END AS 'display_discount_type',
                    CASE rule_type
                        WHEN 'cart_discount_by_percentage' THEN discount_value * 100
                        WHEN 'product_discount_by_percentage' THEN discount_value * 100
                        ELSE discount_value
                    END AS 'display_discount_value'
                    ")
                )
                ->join('promotion_rules', 'promotions.promotion_id', '=', 'promotion_rules.promotion_id');

            // Filter coupon by Ids
            OrbitInput::get('promotion_id', function($promotionIds) use ($coupons)
            {
                $coupons->whereIn('promotions.promotion_id', $promotionIds);
            });

            // Filter coupon by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($coupons) {
                $coupons->whereIn('promotions.merchant_id', $merchantIds);
            });

            // Filter coupon by promotion name
            OrbitInput::get('promotion_name', function($promotionName) use ($coupons)
            {
                $coupons->whereIn('promotions.promotion_name', $promotionName);
            });

            // Filter coupon by matching promotion name pattern
            OrbitInput::get('promotion_name_like', function($promotionName) use ($coupons)
            {
                $coupons->where('promotions.promotion_name', 'like', "%$promotionName%");
            });

            // Filter coupon by promotion type
            OrbitInput::get('promotion_type', function($promotionTypes) use ($coupons)
            {
                $coupons->whereIn('promotions.promotion_type', $promotionTypes);
            });

            // Filter coupon by description
            OrbitInput::get('description', function($description) use ($coupons)
            {
                $coupons->whereIn('promotions.description', $description);
            });

            // Filter coupon by matching description pattern
            OrbitInput::get('description_like', function($description) use ($coupons)
            {
                $coupons->where('promotions.description', 'like', "%$description%");
            });

            // Filter coupon by begin date
            OrbitInput::get('begin_date', function($beginDate) use ($coupons)
            {
                $coupons->where('promotions.begin_date', '<=', $beginDate);
            });

            // Filter coupon by end date
            OrbitInput::get('end_date', function($endDate) use ($coupons)
            {
                $coupons->where('promotions.end_date', '>=', $endDate);
            });

            // Filter coupon by is permanent
            OrbitInput::get('is_permanent', function ($isPermanent) use ($coupons) {
                $coupons->whereIn('promotions.is_permanent', $isPermanent);
            });

            // Filter coupon by coupon notification
            OrbitInput::get('coupon_notification', function ($couponNotification) use ($coupons) {
                $coupons->whereIn('promotions.coupon_notification', $couponNotification);
            });

            // Filter coupon by status
            OrbitInput::get('status', function ($statuses) use ($coupons) {
                $coupons->whereIn('promotions.status', $statuses);
            });

            // Filter coupon rule by rule type
            OrbitInput::get('rule_type', function ($ruleTypes) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleTypes) {
                    $q->whereIn('rule_type', $ruleTypes);
                });
            });

             // Filter coupon rule by rule object type
            OrbitInput::get('rule_object_type', function ($ruleObjectTypes) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectTypes) {
                    $q->whereIn('rule_object_type', $ruleObjectTypes);
                });
            });

            // Filter coupon rule by rule object id1
            OrbitInput::get('rule_object_id1', function ($ruleObjectId1s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectId1s) {
                    $q->whereIn('rule_object_id1', $ruleObjectId1s);
                });
            });

            // Filter coupon rule by rule object id2
            OrbitInput::get('rule_object_id2', function ($ruleObjectId2s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectId2s) {
                    $q->whereIn('rule_object_id2', $ruleObjectId2s);
                });
            });

            // Filter coupon rule by rule object id3
            OrbitInput::get('rule_object_id3', function ($ruleObjectId3s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectId3s) {
                    $q->whereIn('rule_object_id3', $ruleObjectId3s);
                });
            });

            // Filter coupon rule by rule object id4
            OrbitInput::get('rule_object_id4', function ($ruleObjectId4s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectId4s) {
                    $q->whereIn('rule_object_id4', $ruleObjectId4s);
                });
            });

            // Filter coupon rule by rule object id5
            OrbitInput::get('rule_object_id5', function ($ruleObjectId5s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($ruleObjectId5s) {
                    $q->whereIn('rule_object_id5', $ruleObjectId5s);
                });
            });

            // Filter coupon rule by discount object type
            OrbitInput::get('discount_object_type', function ($discountObjectTypes) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectTypes) {
                    $q->whereIn('discount_object_type', $discountObjectTypes);
                });
            });

            // Filter coupon rule by discount object id1
            OrbitInput::get('discount_object_id1', function ($discountObjectId1s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectId1s) {
                    $q->whereIn('discount_object_id1', $discountObjectId1s);
                });
            });

            // Filter coupon rule by discount object id2
            OrbitInput::get('discount_object_id2', function ($discountObjectId2s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectId2s) {
                    $q->whereIn('discount_object_id2', $discountObjectId2s);
                });
            });

            // Filter coupon rule by discount object id3
            OrbitInput::get('discount_object_id3', function ($discountObjectId3s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectId3s) {
                    $q->whereIn('discount_object_id3', $discountObjectId3s);
                });
            });

            // Filter coupon rule by discount object id4
            OrbitInput::get('discount_object_id4', function ($discountObjectId4s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectId4s) {
                    $q->whereIn('discount_object_id4', $discountObjectId4s);
                });
            });

            // Filter coupon rule by discount object id5
            OrbitInput::get('discount_object_id5', function ($discountObjectId5s) use ($coupons) {
                $coupons->whereHas('couponrule', function($q) use ($discountObjectId5s) {
                    $q->whereIn('discount_object_id5', $discountObjectId5s);
                });
            });

            // Filter coupon by issue retailer id
            OrbitInput::get('issue_retailer_id', function ($issueRetailerIds) use ($coupons) {
                $coupons->whereHas('issueretailers', function($q) use ($issueRetailerIds) {
                    $q->whereIn('retailer_id', $issueRetailerIds);
                });
            });

            // Filter coupon by redeem retailer id
            OrbitInput::get('redeem_retailer_id', function ($redeemRetailerIds) use ($coupons) {
                $coupons->whereHas('redeemretailers', function($q) use ($redeemRetailerIds) {
                    $q->whereIn('retailer_id', $redeemRetailerIds);
                });
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($coupons) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'retailers') {
                        $coupons->with('issueretailers', 'redeemretailers');
                    } elseif ($relation === 'product') {
                        $coupons->with('couponrule.ruleproduct', 'couponrule.discountproduct');
                    } elseif ($relation === 'family') {
                        $coupons->with('couponrule.rulecategory1', 'couponrule.rulecategory2', 'couponrule.rulecategory3', 'couponrule.rulecategory4', 'couponrule.rulecategory5', 'couponrule.discountcategory1', 'couponrule.discountcategory2', 'couponrule.discountcategory3', 'couponrule.discountcategory4', 'couponrule.discountcategory5');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_coupons = clone $coupons;

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
            $coupons->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $coupons)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $coupons->skip($skip);

            // Default sort by
            $sortBy = 'promotions.promotion_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'          => 'promotions.created_at',
                    'promotion_name'           => 'promotions.promotion_name',
                    'promotion_type'           => 'promotions.promotion_type',
                    'description'              => 'promotions.description',
                    'begin_date'               => 'promotions.begin_date',
                    'end_date'                 => 'promotions.end_date',
                    'is_permanent'             => 'promotions.is_permanent',
                    'status'                   => 'promotions.status',
                    'rule_type'                => 'rule_type',
                    'display_discount_value'   => 'display_discount_value' // only to avoid error 'Undefined index'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });

            if (trim(OrbitInput::get('sortby')) === 'display_discount_value') {
                $coupons->orderBy('display_discount_type', $sortMode);
                $coupons->orderBy('display_discount_value', $sortMode);
            } else {
                $coupons->orderBy($sortBy, $sortMode);
            }

            $totalCoupons = RecordCounter::create($_coupons)->count();
            $listOfCoupons = $coupons->get();

            $data = new stdclass();
            $data->total_records = $totalCoupons;
            $data->returned_records = count($listOfCoupons);
            $data->records = $listOfCoupons;

            if ($totalCoupons === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.getsearchcoupon.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.getsearchcoupon.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.getsearchcoupon.query.error', array($this, $e));

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
            Event::fire('orbit.coupon.getsearchcoupon.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.coupon.getsearchcoupon.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Search Coupon - List By Issue Retailer
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`                (optional) - column order by. Valid value: issue_retailer_name, registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status.
     * @param string   `sortmode`              (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `promotion_id`          (optional) - Coupon ID
     * @param integer  `merchant_id`           (optional) - Merchant ID
     * @param string   `promotion_name`        (optional) - Coupon name
     * @param string   `promotion_name_like`   (optional) - Coupon name like
     * @param string   `promotion_type`        (optional) - Coupon type. Valid value: product, cart.
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param datetime `begin_date`            (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime `end_date`              (optional) - End date. Example: 2014-12-30 23:59:59
     * @param string   `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param string   `coupon_notification`   (optional) - Coupon notification. Valid value: Y, N.
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `city`                  (optional) - City name
     * @param string   `city_like`             (optional) - City name like
     * @param integer  `issue_retailer_id`     (optional) - Issue retailer ID
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchCouponByIssueRetailer()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_coupon')) {
                Event::fire('orbit.coupon.getsearchcouponbyissueretailer.authz.notallowed', array($this, $user));
                $viewCouponLang = Lang::get('validation.orbit.actionlist.view_coupon');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCouponLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:issue_retailer_name,registered_date,promotion_name,promotion_type,description,begin_date,end_date,is_permanent,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.coupon_by_issue_retailer_sortby'),
                )
            );

            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int)Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            // Builder object
            $coupons = DB::table('promotions')
                ->join('promotion_retailer', 'promotions.promotion_id', '=', 'promotion_retailer.promotion_id')
                ->join('merchants', 'promotion_retailer.retailer_id', '=', 'merchants.merchant_id')
                ->select('promotion_retailer.retailer_id', 'merchants.name AS issue_retailer_name', 'promotions.*')
                ->where('promotions.is_coupon', '=', 'Y')
                ->where('promotions.status', '!=', 'deleted');

            // Filter coupon by Ids
            OrbitInput::get('promotion_id', function($promotionIds) use ($coupons)
            {
                $coupons->whereIn('promotions.promotion_id', $promotionIds);
            });

            // Filter coupon by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($coupons) {
                $coupons->whereIn('promotions.merchant_id', $merchantIds);
            });

            // Filter coupon by promotion name
            OrbitInput::get('promotion_name', function($promotionName) use ($coupons)
            {
                $coupons->whereIn('promotions.promotion_name', $promotionName);
            });

            // Filter coupon by matching promotion name pattern
            OrbitInput::get('promotion_name_like', function($promotionName) use ($coupons)
            {
                $coupons->where('promotions.promotion_name', 'like', "%$promotionName%");
            });

            // Filter coupon by promotion type
            OrbitInput::get('promotion_type', function($promotionTypes) use ($coupons)
            {
                $coupons->whereIn('promotions.promotion_type', $promotionTypes);
            });

            // Filter coupon by description
            OrbitInput::get('description', function($description) use ($coupons)
            {
                $coupons->whereIn('promotions.description', $description);
            });

            // Filter coupon by matching description pattern
            OrbitInput::get('description_like', function($description) use ($coupons)
            {
                $coupons->where('promotions.description', 'like', "%$description%");
            });

            // Filter coupon by begin date
            OrbitInput::get('begin_date', function($beginDate) use ($coupons)
            {
                $coupons->where('promotions.begin_date', '<=', $beginDate);
            });

            // Filter coupon by end date
            OrbitInput::get('end_date', function($endDate) use ($coupons)
            {
                $coupons->where('promotions.end_date', '>=', $endDate);
            });

            // Filter coupon by is permanent
            OrbitInput::get('is_permanent', function ($isPermanent) use ($coupons) {
                $coupons->whereIn('promotions.is_permanent', $isPermanent);
            });

            // Filter coupon by coupon notification
            OrbitInput::get('coupon_notification', function ($couponNotification) use ($coupons) {
                $coupons->whereIn('promotions.coupon_notification', $couponNotification);
            });

            // Filter coupon by status
            OrbitInput::get('status', function ($statuses) use ($coupons) {
                $coupons->whereIn('promotions.status', $statuses);
            });

            // Filter coupon by city
            OrbitInput::get('city', function($city) use ($coupons)
            {
                $coupons->whereIn('merchants.city', $city);
            });

            // Filter coupon by matching city pattern
            OrbitInput::get('city_like', function($city) use ($coupons)
            {
                $coupons->where('merchants.city', 'like', "%$city%");
            });

            // Filter coupon by issue retailer Ids
            OrbitInput::get('issue_retailer_id', function ($issueRetailerIds) use ($coupons) {
                $coupons->whereIn('promotion_retailer.retailer_id', $issueRetailerIds);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_coupons = clone $coupons;

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
                $coupons->take($take);
            }

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $coupons)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $coupons->skip($skip);
            }

            // Default sort by
            $sortBy = 'issue_retailer_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'issue_retailer_name'    => 'issue_retailer_name',
                    'registered_date'        => 'promotions.created_at',
                    'promotion_name'         => 'promotions.promotion_name',
                    'promotion_type'         => 'promotions.promotion_type',
                    'description'            => 'promotions.description',
                    'begin_date'             => 'promotions.begin_date',
                    'end_date'               => 'promotions.end_date',
                    'is_permanent'           => 'promotions.is_permanent',
                    'status'                 => 'promotions.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $coupons->orderBy($sortBy, $sortMode);

            $totalCoupons = $_coupons->count();
            $listOfCoupons = $coupons->get();

            $data = new stdclass();
            $data->total_records = $totalCoupons;
            $data->returned_records = count($listOfCoupons);
            $data->records = $listOfCoupons;

            if ($totalCoupons === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.coupon');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.query.error', array($this, $e));

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
            Event::fire('orbit.coupon.getsearchcouponbyissueretailer.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.coupon.getsearchcouponbyissueretailer.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
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

        // Check the existance of merchant id
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) {
            $merchant = Merchant::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Check coupon name, it should not exists
        Validator::extend('orbit.exists.coupon_name', function ($attribute, $value, $parameters) {
            $couponName = Coupon::excludeDeleted()
                        ->where('promotion_name', $value)
                        ->first();

            if (! empty($couponName)) {
                return FALSE;
            }

            App::instance('orbit.validation.coupon_name', $couponName);

            return TRUE;
        });

        // Check coupon name, it should not exists (for update)
        Validator::extend('coupon_name_exists_but_me', function ($attribute, $value, $parameters) {
            $promotion_id = trim(OrbitInput::post('promotion_id'));
            $coupon = Coupon::excludeDeleted()
                        ->where('promotion_name', $value)
                        ->where('promotion_id', '!=', $promotion_id)
                        ->first();

            if (! empty($coupon)) {
                return FALSE;
            }

            App::instance('orbit.validation.coupon_name', $coupon);

            return TRUE;
        });

        // Check the existence of the coupon status
        Validator::extend('orbit.empty.coupon_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the coupon type
        Validator::extend('orbit.empty.coupon_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $couponTypes = array('product', 'cart');
            foreach ($couponTypes as $couponType) {
                if($value === $couponType) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the rule type
        Validator::extend('orbit.empty.rule_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $ruletypes = array('cart_discount_by_value', 'cart_discount_by_percentage', 'new_product_price', 'product_discount_by_value', 'product_discount_by_percentage');
            foreach ($ruletypes as $ruletype) {
                if($value === $ruletype) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the rule object type
        Validator::extend('orbit.empty.rule_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $ruleobjecttypes = array('product', 'family');
            foreach ($ruleobjecttypes as $ruleobjecttype) {
                if($value === $ruleobjecttype) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existance of rule_object_id1
        Validator::extend('orbit.empty.rule_object_id1', function ($attribute, $value, $parameters) {
            $ruleobjecttype = trim(OrbitInput::post('rule_object_type'));
            if ($ruleobjecttype === 'product') {
                $rule_object_id1 = Product::excludeDeleted()
                        ->where('product_id', $value)
                        ->first();
            } elseif ($ruleobjecttype === 'family') {
                $rule_object_id1 = Category::excludeDeleted()
                        ->where('category_id', $value)
                        ->first();
            }

            if (empty($rule_object_id1)) {
                return FALSE;
            }

            App::instance('orbit.empty.rule_object_id1', $rule_object_id1);

            return TRUE;
        });

        // Check the existance of rule_object_id2
        Validator::extend('orbit.empty.rule_object_id2', function ($attribute, $value, $parameters) {
            $rule_object_id2 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($rule_object_id2)) {
                return FALSE;
            }

            App::instance('orbit.empty.rule_object_id2', $rule_object_id2);

            return TRUE;
        });

        // Check the existance of rule_object_id3
        Validator::extend('orbit.empty.rule_object_id3', function ($attribute, $value, $parameters) {
            $rule_object_id3 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($rule_object_id3)) {
                return FALSE;
            }

            App::instance('orbit.empty.rule_object_id3', $rule_object_id3);

            return TRUE;
        });

        // Check the existance of rule_object_id4
        Validator::extend('orbit.empty.rule_object_id4', function ($attribute, $value, $parameters) {
            $rule_object_id4 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($rule_object_id4)) {
                return FALSE;
            }

            App::instance('orbit.empty.rule_object_id4', $rule_object_id4);

            return TRUE;
        });

        // Check the existance of rule_object_id5
        Validator::extend('orbit.empty.rule_object_id5', function ($attribute, $value, $parameters) {
            $rule_object_id5 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($rule_object_id5)) {
                return FALSE;
            }

            App::instance('orbit.empty.rule_object_id5', $rule_object_id5);

            return TRUE;
        });

        // Check the existence of the discount object type
        Validator::extend('orbit.empty.discount_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $discountobjecttypes = array('product', 'family', 'cash_rebate');
            foreach ($discountobjecttypes as $discountobjecttype) {
                if($value === $discountobjecttype) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existance of discount_object_id1
        Validator::extend('orbit.empty.discount_object_id1', function ($attribute, $value, $parameters) {
            $discountobjecttype = trim(OrbitInput::post('discount_object_type'));
            if ($discountobjecttype === 'product') {
                $discount_object_id1 = Product::excludeDeleted()
                        ->where('product_id', $value)
                        ->first();
            } elseif ($discountobjecttype === 'family') {
                $discount_object_id1 = Category::excludeDeleted()
                        ->where('category_id', $value)
                        ->first();
            }

            if (empty($discount_object_id1)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id1', $discount_object_id1);

            return TRUE;
        });

        // Check the existance of discount_object_id2
        Validator::extend('orbit.empty.discount_object_id2', function ($attribute, $value, $parameters) {
            $discount_object_id2 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id2)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id2', $discount_object_id2);

            return TRUE;
        });

        // Check the existance of discount_object_id3
        Validator::extend('orbit.empty.discount_object_id3', function ($attribute, $value, $parameters) {
            $discount_object_id3 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id3)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id3', $discount_object_id3);

            return TRUE;
        });

        // Check the existance of discount_object_id4
        Validator::extend('orbit.empty.discount_object_id4', function ($attribute, $value, $parameters) {
            $discount_object_id4 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id4)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id4', $discount_object_id4);

            return TRUE;
        });

        // Check the existance of discount_object_id5
        Validator::extend('orbit.empty.discount_object_id5', function ($attribute, $value, $parameters) {
            $discount_object_id5 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($discount_object_id5)) {
                return FALSE;
            }

            App::instance('orbit.empty.discount_object_id5', $discount_object_id5);

            return TRUE;
        });

        // Check the existance of retailer id
        Validator::extend('orbit.empty.retailer', function ($attribute, $value, $parameters) {
            $retailer = Retailer::excludeDeleted()->allowedForUser($this->api->user)
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($retailer)) {
                return FALSE;
            }

            App::instance('orbit.empty.retailer', $retailer);

            return TRUE;
        });

    }
}
