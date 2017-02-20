<?php
/**
 * An API controller for managing Promotion.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class PromotionAPIController extends ControllerAPI
{
    /**
     * POST - Create New Promotion
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`           (required) - Merchant ID
     * @param string     `promotion_name`        (required) - Promotion name
     * @param string     `promotion_type`        (required) - Promotion type. Valid value: product, cart.
     * @param string     `status`                (required) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`           (optional) - Description
     * @param datetime   `begin_date`            (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime   `end_date`              (optional) - End date. Example: 2014-12-31 23:59:59
     * @param string     `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param file       `images`                (optional) - Promotion image
     * @param string     `rule_type`             (optional) - Rule type. Valid value: cart_discount_by_value, cart_discount_by_percentage, new_product_price, product_discount_by_value, product_discount_by_percentage.
     * @param decimal    `rule_value`            (optional) - Rule value
     * @param string     `discount_object_type`  (optional) - Discount object type. Valid value: product, family.
     * @param integer    `discount_object_id1`   (optional) - Discount object ID1 (product_id or category_id1).
     * @param integer    `discount_object_id2`   (optional) - Discount object ID2 (category_id2).
     * @param integer    `discount_object_id3`   (optional) - Discount object ID3 (category_id3).
     * @param integer    `discount_object_id4`   (optional) - Discount object ID4 (category_id4).
     * @param integer    `discount_object_id5`   (optional) - Discount object ID5 (category_id5).
     * @param decimal    `discount_value`        (optional) - Discount value
     * @param array      `retailer_ids`          (optional) - Retailer IDs
     * @param integer    `id_language_default`      (required) - ID language default
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewPromotion()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newpromotion = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.promotion.postnewpromotion.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.promotion.postnewpromotion.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promotion.postnewpromotion.before.authz', array($this, $user));
/*
            if (! ACL::create($user)->isAllowed('create_promotion')) {
                Event::fire('orbit.promotion.postnewpromotion.authz.notallowed', array($this, $user));
                $createPromotionLang = Lang::get('validation.orbit.actionlist.new_promotion');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createPromotionLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.promotion.postnewpromotion.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('current_mall');
            $promotion_name = OrbitInput::post('promotion_name');
            $promotion_type = OrbitInput::post('promotion_type');
            $status = OrbitInput::post('status');
            $description = OrbitInput::post('description');
            $begin_date = OrbitInput::post('begin_date');
            $end_date = OrbitInput::post('end_date');
            $is_permanent = OrbitInput::post('is_permanent');
            $image = OrbitInput::post('image');
            $rule_type = OrbitInput::post('rule_type');
            $rule_value = OrbitInput::post('rule_value');
            $discount_object_type = OrbitInput::post('discount_object_type');
            $discount_object_id1 = OrbitInput::post('discount_object_id1');
            $discount_object_id2 = OrbitInput::post('discount_object_id2');
            $discount_object_id3 = OrbitInput::post('discount_object_id3');
            $discount_object_id4 = OrbitInput::post('discount_object_id4');
            $discount_object_id5 = OrbitInput::post('discount_object_id5');
            $discount_value = OrbitInput::post('discount_value');
            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;
            $id_language_default = OrbitInput::post('id_language_default');
            $is_exclusive = OrbitInput::post('is_exclusive', 'N');

            $validator = Validator::make(
                array(
                    'merchant_id'          => $merchant_id,
                    'promotion_name'       => $promotion_name,
                    'promotion_type'       => $promotion_type,
                    'status'               => $status,
                    'rule_type'            => $rule_type,
                    'discount_object_type' => $discount_object_type,
                    'discount_object_id1'  => $discount_object_id1,
                    'discount_object_id2'  => $discount_object_id2,
                    'discount_object_id3'  => $discount_object_id3,
                    'discount_object_id4'  => $discount_object_id4,
                    'discount_object_id5'  => $discount_object_id5,
                    'id_language_default'   => $id_language_default,
                    'partner_exclusive'    => $is_exclusive,
                ),
                array(
                    'merchant_id'          => 'required|orbit.empty.merchant',
                    'promotion_name'       => 'required|max:100|orbit.exists.promotion_name',
                    'promotion_type'       => 'required|orbit.empty.promotion_type',
                    'status'               => 'required|orbit.empty.promotion_status',
                    'rule_type'            => 'orbit.empty.rule_type',
                    'discount_object_type' => 'orbit.empty.discount_object_type',
                    'discount_object_id1'  => 'orbit.empty.discount_object_id1',
                    'discount_object_id2'  => 'orbit.empty.discount_object_id2',
                    'discount_object_id3'  => 'orbit.empty.discount_object_id3',
                    'discount_object_id4'  => 'orbit.empty.discount_object_id4',
                    'discount_object_id5'  => 'orbit.empty.discount_object_id5',
                    'id_language_default'   => 'required|numeric',
                    'partner_exclusive'    => 'in:Y,N|orbit.empty.exclusive_partner',
                ),
                array(
                    'orbit.empty.exclusive_partner'  => 'Partner is not exclusive',
                )
            );

            Event::fire('orbit.promotion.postnewpromotion.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            foreach ($retailer_ids as $retailer_id_check) {
                $validator = Validator::make(
                    array(
                        'retailer_id'   => $retailer_id_check,

                    ),
                    array(
                        'retailer_id'   => 'orbit.empty.tenant',
                    )
                );

                Event::fire('orbit.promotion.postnewpromotion.before.retailervalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.promotion.postnewpromotion.after.retailervalidation', array($this, $validator));
            }

            Event::fire('orbit.promotion.postnewpromotion.after.validation', array($this, $validator));

            // save Promotion.
            $newpromotion = new Promotion();
            $newpromotion->merchant_id = $merchant_id;
            $newpromotion->promotion_name = $promotion_name;
            $newpromotion->promotion_type = $promotion_type;
            $newpromotion->status = $status;
            $newpromotion->description = $description;
            $newpromotion->begin_date = $begin_date;
            $newpromotion->end_date = $end_date;
            $newpromotion->is_permanent = $is_permanent;
            $newpromotion->image = $image;
            $newpromotion->created_by = $this->api->user->user_id;
            $newpromotion->is_exclusive = $is_exclusive;

            Event::fire('orbit.promotion.postnewpromotion.before.save', array($this, $newpromotion));

            $newpromotion->save();

            // save PromotionRule.
            $promotionrule = new PromotionRule();
            $promotionrule->rule_type = $rule_type;
            $promotionrule->rule_value = $rule_value;
            $promotionrule->discount_object_type = $discount_object_type;

            // discount_object_id1
            if (trim($discount_object_id1) === '') {
                $promotionrule->discount_object_id1 = NULL;
            } else {
                $promotionrule->discount_object_id1 = $discount_object_id1;
            }

            // discount_object_id2
            if (trim($discount_object_id2) === '') {
                $promotionrule->discount_object_id2 = NULL;
            } else {
                $promotionrule->discount_object_id2 = $discount_object_id2;
            }

            // discount_object_id3
            if (trim($discount_object_id3) === '') {
                $promotionrule->discount_object_id3 = NULL;
            } else {
                $promotionrule->discount_object_id3 = $discount_object_id3;
            }

            // discount_object_id4
            if (trim($discount_object_id4) === '') {
                $promotionrule->discount_object_id4 = NULL;
            } else {
                $promotionrule->discount_object_id4 = $discount_object_id4;
            }

            // discount_object_id5
            if (trim($discount_object_id5) === '') {
                $promotionrule->discount_object_id5 = NULL;
            } else {
                $promotionrule->discount_object_id5 = $discount_object_id5;
            }

            $promotionrule->discount_value = $discount_value;
            $promotionrule = $newpromotion->promotionrule()->save($promotionrule);
            $newpromotion->promotionrule = $promotionrule;

            // save PromotionRetailer.
            $promotionretailers = array();
            foreach ($retailer_ids as $retailer_id) {
                $promotionretailer = new PromotionRetailer();
                $promotionretailer->retailer_id = $retailer_id;
                $promotionretailer->promotion_id = $newpromotion->promotion_id;
                $promotionretailer->save();
                $promotionretailers[] = $promotionretailer;
            }
            $newpromotion->retailers = $promotionretailers;

            Event::fire('orbit.promotion.postnewpromotion.after.save', array($this, $newpromotion));

            // @author Irianto Pratama <irianto@dominopos.com>
            $default_translation = [
                $id_language_default => [
                    'promotion_name' => $newpromotion->promotion_name,
                    'description' => $newpromotion->description
                ]
            ];
            $this->validateAndSaveTranslations($newpromotion, json_encode($default_translation), 'create');

            // Save Promotion Translation
            OrbitInput::post('translations', function($translation_json_string) use ($newpromotion) {
                $this->validateAndSaveTranslations($newpromotion, $translation_json_string, 'create');
            });

            $this->response->data = $newpromotion;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Promotion Created: %s', $newpromotion->promotion_name);
            $activity->setUser($user)
                    ->setActivityName('create_promotion')
                    ->setActivityNameLong('Create Promotion OK')
                    ->setObject($newpromotion)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.promotion.postnewpromotion.after.commit', array($this, $newpromotion));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.promotion.postnewpromotion.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_promotion')
                    ->setActivityNameLong('Create Promotion Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promotion.postnewpromotion.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_promotion')
                    ->setActivityNameLong('Create Promotion Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.promotion.postnewpromotion.query.error', array($this, $e));

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
                    ->setActivityName('create_promotion')
                    ->setActivityNameLong('Create Promotion Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.promotion.postnewpromotion.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_promotion')
                    ->setActivityNameLong('Create Promotion Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update Promotion
     *
     * @author <Tian> <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`          (required) - Promotion ID
     * @param integer    `merchant_id`           (optional) - Merchant ID
     * @param string     `promotion_name`        (optional) - Promotion name
     * @param string     `promotion_type`        (optional) - Promotion type. Valid value: product, cart.
     * @param string     `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`           (optional) - Description
     * @param datetime   `begin_date`            (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime   `end_date`              (optional) - End date. Example: 2014-12-31 23:59:59
     * @param string     `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param file       `images`                (optional) - Promotion image
     * @param string     `rule_type`             (optional) - Rule type. Valid value: cart_discount_by_value, cart_discount_by_percentage, new_product_price, product_discount_by_value, product_discount_by_percentage.
     * @param decimal    `rule_value`            (optional) - Rule value
     * @param string     `discount_object_type`  (optional) - Discount object type. Valid value: product, family.
     * @param integer    `discount_object_id1`   (optional) - Discount object ID1 (product_id or category_id1).
     * @param integer    `discount_object_id2`   (optional) - Discount object ID2 (category_id2).
     * @param integer    `discount_object_id3`   (optional) - Discount object ID3 (category_id3).
     * @param integer    `discount_object_id4`   (optional) - Discount object ID4 (category_id4).
     * @param integer    `discount_object_id5`   (optional) - Discount object ID5 (category_id5).
     * @param decimal    `discount_value`        (optional) - Discount value
     * @param string     `no_retailer`           (optional) - Flag to delete all ORID links. Valid value: Y.
     * @param array      `retailer_ids`          (optional) - Retailer IDs
     * @param integer    `id_language_default`   (required) - ID language default
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdatePromotion()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedpromotion = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.promotion.postupdatepromotion.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.promotion.postupdatepromotion.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promotion.postupdatepromotion.before.authz', array($this, $user));

/*            if (! ACL::create($user)->isAllowed('update_promotion')) {
                Event::fire('orbit.promotion.postupdatepromotion.authz.notallowed', array($this, $user));
                $updatePromotionLang = Lang::get('validation.orbit.actionlist.update_promotion');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updatePromotionLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.promotion.postupdatepromotion.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $promotion_id = OrbitInput::post('promotion_id');
            $merchant_id = OrbitInput::post('current_mall');
            $promotion_type = OrbitInput::post('promotion_type');
            $status = OrbitInput::post('status');
            $rule_type = OrbitInput::post('rule_type');
            $discount_object_type = OrbitInput::post('discount_object_type');
            $discount_object_id1 = OrbitInput::post('discount_object_id1');
            $discount_object_id2 = OrbitInput::post('discount_object_id2');
            $discount_object_id3 = OrbitInput::post('discount_object_id3');
            $discount_object_id4 = OrbitInput::post('discount_object_id4');
            $discount_object_id5 = OrbitInput::post('discount_object_id5');
            $id_language_default = OrbitInput::post('id_language_default');
            $is_exclusive = OrbitInput::post('is_exclusive');

            $data = array(
                'promotion_id'         => $promotion_id,
                'merchant_id'          => $merchant_id,
                'promotion_type'       => $promotion_type,
                'status'               => $status,
                'rule_type'            => $rule_type,
                'discount_object_type' => $discount_object_type,
                'discount_object_id1'  => $discount_object_id1,
                'discount_object_id2'  => $discount_object_id2,
                'discount_object_id3'  => $discount_object_id3,
                'discount_object_id4'  => $discount_object_id4,
                'discount_object_id5'  => $discount_object_id5,
                'id_language_default' => $id_language_default,
                'partner_exclusive'    => $is_exclusive,
            );

            // Validate promotion_name only if exists in POST.
            OrbitInput::post('promotion_name', function($promotion_name) use (&$data) {
                $data['promotion_name'] = $promotion_name;
            });

            $validator = Validator::make(
                $data,
                array(
                    'promotion_id'         => 'required|orbit.empty.promotion',
                    'merchant_id'          => 'orbit.empty.merchant',
                    'promotion_name'       => 'sometimes|required|min:5|max:100|promotion_name_exists_but_me',
                    'promotion_type'       => 'orbit.empty.promotion_type',
                    'status'               => 'orbit.empty.promotion_status',
                    'rule_type'            => 'orbit.empty.rule_type',
                    'discount_object_type' => 'orbit.empty.discount_object_type',
                    'discount_object_id1'  => 'orbit.empty.discount_object_id1',
                    'discount_object_id2'  => 'orbit.empty.discount_object_id2',
                    'discount_object_id3'  => 'orbit.empty.discount_object_id3',
                    'discount_object_id4'  => 'orbit.empty.discount_object_id4',
                    'discount_object_id5'  => 'orbit.empty.discount_object_id5',
                    'id_language_default' => 'required|numeric',
                    'partner_exclusive'    => 'in:Y,N|orbit.empty.exclusive_partner',
                ),
                array(
                   'promotion_name_exists_but_me'   => Lang::get('validation.orbit.exists.promotion_name'),
                   'orbit.empty.exclusive_partner'  => 'Partner is not exclusive',
                )
            );

            Event::fire('orbit.promotion.postupdatepromotion.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.promotion.postupdatepromotion.after.validation', array($this, $validator));

            $updatedpromotion = Promotion::with('promotionrule', 'retailers')->excludeDeleted()->allowedForUser($user)->where('promotion_id', $promotion_id)->first();

            // save Promotion.
            OrbitInput::post('merchant_id', function($merchant_id) use ($updatedpromotion) {
                $updatedpromotion->merchant_id = $merchant_id;
            });

            OrbitInput::post('promotion_name', function($promotion_name) use ($updatedpromotion) {
                $updatedpromotion->promotion_name = $promotion_name;
            });

            OrbitInput::post('promotion_type', function($promotion_type) use ($updatedpromotion) {
                $updatedpromotion->promotion_type = $promotion_type;
            });

            OrbitInput::post('status', function($status) use ($updatedpromotion) {
                $updatedpromotion->status = $status;
            });

            OrbitInput::post('description', function($description) use ($updatedpromotion) {
                $updatedpromotion->description = $description;
            });

            OrbitInput::post('begin_date', function($begin_date) use ($updatedpromotion) {
                $updatedpromotion->begin_date = $begin_date;
            });

            OrbitInput::post('end_date', function($end_date) use ($updatedpromotion) {
                $updatedpromotion->end_date = $end_date;
            });

            OrbitInput::post('is_permanent', function($is_permanent) use ($updatedpromotion) {
                $updatedpromotion->is_permanent = $is_permanent;
            });

            OrbitInput::post('image', function($image) use ($updatedpromotion) {
                $updatedpromotion->image = $image;
            });

            OrbitInput::post('is_exclusive', function($is_exclusive) use ($updatedpromotion) {
                $updatedpromotion->is_exclusive = $is_exclusive;
            });

            $updatedpromotion->modified_by = $this->api->user->user_id;

            Event::fire('orbit.promotion.postupdatepromotion.before.save', array($this, $updatedpromotion));

            $updatedpromotion->save();


            // save PromotionRule.
            $promotionrule = PromotionRule::where('promotion_id', '=', $promotion_id)->first();
            OrbitInput::post('rule_type', function($rule_type) use ($promotionrule) {
                if (trim($rule_type) === '') {
                    $rule_type = NULL;
                }
                $promotionrule->rule_type = $rule_type;
            });

            OrbitInput::post('rule_value', function($rule_value) use ($promotionrule) {
                $promotionrule->rule_value = $rule_value;
            });

            OrbitInput::post('discount_object_type', function($discount_object_type) use ($promotionrule) {
                if (trim($discount_object_type) === '') {
                    $discount_object_type = NULL;
                }
                $promotionrule->discount_object_type = $discount_object_type;
            });

            OrbitInput::post('discount_object_id1', function($discount_object_id1) use ($promotionrule) {
                if (trim($discount_object_id1) === '') {
                    $discount_object_id1 = NULL;
                }
                $promotionrule->discount_object_id1 = $discount_object_id1;
            });

            OrbitInput::post('discount_object_id2', function($discount_object_id2) use ($promotionrule) {
                if (trim($discount_object_id2) === '') {
                    $discount_object_id2 = NULL;
                }
                $promotionrule->discount_object_id2 = $discount_object_id2;
            });

            OrbitInput::post('discount_object_id3', function($discount_object_id3) use ($promotionrule) {
                if (trim($discount_object_id3) === '') {
                    $discount_object_id3 = NULL;
                }
                $promotionrule->discount_object_id3 = $discount_object_id3;
            });

            OrbitInput::post('discount_object_id4', function($discount_object_id4) use ($promotionrule) {
                if (trim($discount_object_id4) === '') {
                    $discount_object_id4 = NULL;
                }
                $promotionrule->discount_object_id4 = $discount_object_id4;
            });

            OrbitInput::post('discount_object_id5', function($discount_object_id5) use ($promotionrule) {
                if (trim($discount_object_id5) === '') {
                    $discount_object_id5 = NULL;
                }
                $promotionrule->discount_object_id5 = $discount_object_id5;
            });

            OrbitInput::post('discount_value', function($discount_value) use ($promotionrule) {
                $promotionrule->discount_value = $discount_value;
            });
            $promotionrule->save();
            $updatedpromotion->setRelation('promotionrule', $promotionrule);
            $updatedpromotion->promotionrule = $promotionrule;


            // save PromotionRetailer.
            OrbitInput::post('no_retailer', function($no_retailer) use ($updatedpromotion) {
                if ($no_retailer == 'Y') {
                    $deleted_retailer_ids = PromotionRetailer::where('promotion_id', $updatedpromotion->promotion_id)->get(array('retailer_id'))->toArray();
                    $updatedpromotion->retailers()->detach($deleted_retailer_ids);
                    $updatedpromotion->load('retailers');
                }
            });

            OrbitInput::post('retailer_ids', function($retailer_ids) use ($updatedpromotion) {
                // validate retailer_ids
                $retailer_ids = (array) $retailer_ids;
                foreach ($retailer_ids as $retailer_id_check) {
                    $validator = Validator::make(
                        array(
                            'retailer_id'   => $retailer_id_check,
                        ),
                        array(
                            'retailer_id'   => 'orbit.empty.tenant',
                        )
                    );

                    Event::fire('orbit.promotion.postupdatepromotion.before.retailervalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.promotion.postupdatepromotion.after.retailervalidation', array($this, $validator));
                }
                // sync new set of retailer ids
                $updatedpromotion->retailers()->sync($retailer_ids);

                // reload retailers relation
                $updatedpromotion->load('retailers');
            });


            Event::fire('orbit.promotion.postupdatepromotion.after.save', array($this, $updatedpromotion));
            $this->response->data = $updatedpromotion;

            // @author Irianto Pratama <irianto@dominopos.com>
            $default_translation = [
                $id_language_default => [
                    'promotion_name' => $updatedpromotion->promotion_name,
                    'description' => $updatedpromotion->description
                ]
            ];
            $this->validateAndSaveTranslations($updatedpromotion, json_encode($default_translation), 'update');

            // save PromotionTranslation
            OrbitInput::post('translations', function($translation_json_string) use ($updatedpromotion) {
                $this->validateAndSaveTranslations($updatedpromotion, $translation_json_string, 'update');
            });

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Promotion updated: %s', $updatedpromotion->promotion_name);
            $activity->setUser($user)
                    ->setActivityName('update_promotion')
                    ->setActivityNameLong('Update Promotion OK')
                    ->setObject($updatedpromotion)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.promotion.postupdatepromotion.after.commit', array($this, $updatedpromotion));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.promotion.postupdatepromotion.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_promotion')
                    ->setActivityNameLong('Update Promotion Failed')
                    ->setObject($updatedpromotion)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promotion.postupdatepromotion.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_promotion')
                    ->setActivityNameLong('Update Promotion Failed')
                    ->setObject($updatedpromotion)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.promotion.postupdatepromotion.query.error', array($this, $e));

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
                    ->setActivityName('update_promotion')
                    ->setActivityNameLong('Update Promotion Failed')
                    ->setObject($updatedpromotion)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.promotion.postupdatepromotion.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_promotion')
                    ->setActivityNameLong('Update Promotion Failed')
                    ->setObject($updatedpromotion)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    /**
     * POST - Delete Promotion
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                  (required) - ID of the promotion
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeletePromotion()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deletepromotion = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.promotion.postdeletepromotion.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.promotion.postdeletepromotion.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promotion.postdeletepromotion.before.authz', array($this, $user));
/*
            if (! ACL::create($user)->isAllowed('delete_promotion')) {
                Event::fire('orbit.promotion.postdeletepromotion.authz.notallowed', array($this, $user));
                $deletePromotionLang = Lang::get('validation.orbit.actionlist.delete_promotion');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deletePromotionLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.promotion.postdeletepromotion.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $promotion_id = OrbitInput::post('promotion_id');

            $validator = Validator::make(
                array(
                    'promotion_id' => $promotion_id,
                ),
                array(
                    'promotion_id' => 'required|orbit.empty.promotion',
                )
            );

            Event::fire('orbit.promotion.postdeletepromotion.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.promotion.postdeletepromotion.after.validation', array($this, $validator));

            $deletepromotion = Promotion::excludeDeleted()->allowedForUser($user)->where('promotion_id', $promotion_id)->first();
            $deletepromotion->status = 'deleted';
            $deletepromotion->modified_by = $this->api->user->user_id;

            Event::fire('orbit.promotion.postdeletepromotion.before.save', array($this, $deletepromotion));

            // hard delete promotion-retailer.
            $deletepromotionretailers = PromotionRetailer::where('promotion_id', $deletepromotion->promotion_id)->get();
            foreach ($deletepromotionretailers as $deletepromotionretailer) {
                $deletepromotionretailer->delete();
            }

            foreach ($deletepromotion->translations as $translation) {
                $translation->modified_by = $this->api->user->user_id;
                $translation->delete();
            }

            $deletepromotion->save();

            Event::fire('orbit.promotion.postdeletepromotion.after.save', array($this, $deletepromotion));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.promotion');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Promotion Deleted: %s', $deletepromotion->promotion_name);
            $activity->setUser($user)
                    ->setActivityName('delete_promotion')
                    ->setActivityNameLong('Delete Promotion OK')
                    ->setObject($deletepromotion)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.promotion.postdeletepromotion.after.commit', array($this, $deletepromotion));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.promotion.postdeletepromotion.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_promotion')
                    ->setActivityNameLong('Delete Promotion Failed')
                    ->setObject($deletepromotion)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promotion.postdeletepromotion.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_promotion')
                    ->setActivityNameLong('Delete Promotion Failed')
                    ->setObject($deletepromotion)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.promotion.postdeletepromotion.query.error', array($this, $e));

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
                    ->setActivityName('delete_promotion')
                    ->setActivityNameLong('Delete Promotion Failed')
                    ->setObject($deletepromotion)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.promotion.postdeletepromotion.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_promotion')
                    ->setActivityNameLong('Delete Promotion Failed')
                    ->setObject($deletepromotion)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);

        // Save the activity
        $activity->save();

        return $output;
    }

    /**
     * GET - Search Promotion
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
     * @param integer  `promotion_id`          (optional) - Promotion ID
     * @param integer  `merchant_id`           (optional) - Merchant ID
     * @param string   `promotion_name`        (optional) - Promotion name
     * @param string   `promotion_name_like`   (optional) - Promotion name like
     * @param string   `promotion_type`        (optional) - Promotion type. Valid value: product, cart.
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param datetime `begin_date`            (optional) - Begin date. Example: 2014-12-30 00:00:00
     * @param datetime `end_date`              (optional) - End date. Example: 2014-12-31 23:59:59
     * @param string   `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `rule_type`             (optional) - Rule type. Valid value: cart_discount_by_value, cart_discount_by_percentage, new_product_price, product_discount_by_value, product_discount_by_percentage.
     * @param string   `discount_object_type`  (optional) - Discount object type. Valid value: product, family.
     * @param integer  `discount_object_id1`   (optional) - Discount object ID1 (product_id or category_id1).
     * @param integer  `discount_object_id2`   (optional) - Discount object ID2 (category_id2).
     * @param integer  `discount_object_id3`   (optional) - Discount object ID3 (category_id3).
     * @param integer  `discount_object_id4`   (optional) - Discount object ID4 (category_id4).
     * @param integer  `discount_object_id5`   (optional) - Discount object ID5 (category_id5).
     * @param integer  `retailer_id`           (optional) - Retailer IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchPromotion()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.promotion.getsearchpromotion.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.promotion.getsearchpromotion.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promotion.getsearchpromotion.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_promotion')) {
                Event::fire('orbit.promotion.getsearchpromotion.authz.notallowed', array($this, $user));
                $viewPromotionLang = Lang::get('validation.orbit.actionlist.view_promotion');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewPromotionLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.promotion.getsearchpromotion.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,promotion_name,promotion_type,description,begin_date,end_date,updated_at,is_permanent,status,rule_type,display_discount_value',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.promotion_sortby'),
                )
            );

            Event::fire('orbit.promotion.getsearchpromotion.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.promotion.getsearchpromotion.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.promotion.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.promotion.per_page');
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
            $promotions = Promotion::with('promotionrule')
                ->excludeDeleted()
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

            // Filter promotion by Ids
            OrbitInput::get('promotion_id', function($promotionIds) use ($promotions)
            {
                $promotions->whereIn('promotions.promotion_id', $promotionIds);
            });

            // Filter promotion by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($promotions) {
                $promotions->whereIn('promotions.merchant_id', $merchantIds);
            });

            // Filter promotion by promotion name
            OrbitInput::get('promotion_name', function($promotionname) use ($promotions)
            {
                $promotions->whereIn('promotions.promotion_name', $promotionname);
            });

            // Filter promotion by matching promotion name pattern
            OrbitInput::get('promotion_name_like', function($promotionname) use ($promotions)
            {
                $promotions->where('promotions.promotion_name', 'like', "%$promotionname%");
            });

            // Filter promotion by promotion type
            OrbitInput::get('promotion_type', function($promotionTypes) use ($promotions)
            {
                $promotions->whereIn('promotions.promotion_type', $promotionTypes);
            });

            // Filter promotion by description
            OrbitInput::get('description', function($description) use ($promotions)
            {
                $promotions->whereIn('promotions.description', $description);
            });

            // Filter promotion by matching description pattern
            OrbitInput::get('description_like', function($description) use ($promotions)
            {
                $promotions->where('promotions.description', 'like', "%$description%");
            });

            // Filter promotion by begin date
            OrbitInput::get('begin_date', function($begindate) use ($promotions)
            {
                $promotions->where('promotions.begin_date', '<=', $begindate);
            });

            // Filter promotion by end date
            OrbitInput::get('end_date', function($enddate) use ($promotions)
            {
                $promotions->where('promotions.end_date', '>=', $enddate);
            });

            // Filter promotion by is permanent
            OrbitInput::get('is_permanent', function ($ispermanent) use ($promotions) {
                $promotions->whereIn('promotions.is_permanent', $ispermanent);
            });

            // Filter promotion by status
            OrbitInput::get('status', function ($statuses) use ($promotions) {
                $promotions->whereIn('promotions.status', $statuses);
            });

            // Filter promotion rule by rule type
            OrbitInput::get('rule_type', function ($ruleTypes) use ($promotions) {
                $promotions->whereHas('promotionrule', function($q) use ($ruleTypes) {
                    $q->whereIn('rule_type', $ruleTypes);
                });
            });

            // Filter promotion rule by discount object type
            OrbitInput::get('discount_object_type', function ($discountObjectTypes) use ($promotions) {
                $promotions->whereHas('promotionrule', function($q) use ($discountObjectTypes) {
                    $q->whereIn('discount_object_type', $discountObjectTypes);
                });
            });

            // Filter promotion rule by discount object id1
            OrbitInput::get('discount_object_id1', function ($discountObjectId1s) use ($promotions) {
                $promotions->whereHas('promotionrule', function($q) use ($discountObjectId1s) {
                    $q->whereIn('discount_object_id1', $discountObjectId1s);
                });
            });

            // Filter promotion rule by discount object id2
            OrbitInput::get('discount_object_id2', function ($discountObjectId2s) use ($promotions) {
                $promotions->whereHas('promotionrule', function($q) use ($discountObjectId2s) {
                    $q->whereIn('discount_object_id2', $discountObjectId2s);
                });
            });

            // Filter promotion rule by discount object id3
            OrbitInput::get('discount_object_id3', function ($discountObjectId3s) use ($promotions) {
                $promotions->whereHas('promotionrule', function($q) use ($discountObjectId3s) {
                    $q->whereIn('discount_object_id3', $discountObjectId3s);
                });
            });

            // Filter promotion rule by discount object id4
            OrbitInput::get('discount_object_id4', function ($discountObjectId4s) use ($promotions) {
                $promotions->whereHas('promotionrule', function($q) use ($discountObjectId4s) {
                    $q->whereIn('discount_object_id4', $discountObjectId4s);
                });
            });

            // Filter promotion rule by discount object id5
            OrbitInput::get('discount_object_id5', function ($discountObjectId5s) use ($promotions) {
                $promotions->whereHas('promotionrule', function($q) use ($discountObjectId5s) {
                    $q->whereIn('discount_object_id5', $discountObjectId5s);
                });
            });

            // Filter promotion retailer by retailer id
            OrbitInput::get('retailer_id', function ($retailerIds) use ($promotions) {
                $promotions->whereHas('retailers', function($q) use ($retailerIds) {
                    $q->whereIn('retailer_id', $retailerIds);
                });
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($promotions) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'retailers') {
                        $promotions->with('retailers');
                    } elseif ($relation === 'product') {
                        $promotions->with('promotionrule.discountproduct');
                    } elseif ($relation === 'family') {
                        $promotions->with('promotionrule.discountcategory1', 'promotionrule.discountcategory2', 'promotionrule.discountcategory3', 'promotionrule.discountcategory4', 'promotionrule.discountcategory5');
                    } elseif ($relation === 'translations') {
                        $promotions->with('translations');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_promotions = clone $promotions;

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
            $promotions->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $promotions)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $promotions->skip($skip);
            }

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
                    'updated_at'               => 'promotions.updated_at',
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
                $promotions->orderBy('display_discount_type', $sortMode);
                $promotions->orderBy('display_discount_value', $sortMode);
            } else {
                $promotions->orderBy($sortBy, $sortMode);
            }

            $totalPromotions = RecordCounter::create($_promotions)->count();
            $listOfPromotions = $promotions->get();

            $data = new stdclass();
            $data->total_records = $totalPromotions;
            $data->returned_records = count($listOfPromotions);
            $data->records = $listOfPromotions;

            if ($totalPromotions === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.promotion');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.promotion.getsearchpromotion.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promotion.getsearchpromotion.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.promotion.getsearchpromotion.query.error', array($this, $e));

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
            Event::fire('orbit.promotion.getsearchpromotion.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.promotion.getsearchpromotion.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - Search Promotion - List By Retailer
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`                (optional) - column order by. Valid value: retailer_name, registered_date, promotion_name, promotion_type, description, begin_date, end_date, is_permanent, status.
     * @param string   `sortmode`              (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `promotion_id`          (optional) - Promotion ID
     * @param integer  `merchant_id`           (optional) - Merchant ID
     * @param string   `promotion_name`        (optional) - Promotion name
     * @param string   `promotion_name_like`   (optional) - Promotion name like
     * @param string   `promotion_type`        (optional) - Promotion type. Valid value: product, cart.
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param datetime `begin_date`            (optional) - Begin date. Example: 2015-2-4 00:00:00
     * @param datetime `end_date`              (optional) - End date. Example: 2015-2-4 23:59:59
     * @param string   `is_permanent`          (optional) - Is permanent. Valid value: Y, N.
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `city`                  (optional) - City name
     * @param string   `city_like`             (optional) - City name like
     * @param integer  `retailer_id`           (optional) - Retailer IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchPromotionByRetailer()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.promotion.getsearchpromotionbyretailer.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.promotion.getsearchpromotionbyretailer.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.promotion.getsearchpromotionbyretailer.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_promotion')) {
                Event::fire('orbit.promotion.getsearchpromotionbyretailer.authz.notallowed', array($this, $user));
                $viewPromotionLang = Lang::get('validation.orbit.actionlist.view_promotion');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewPromotionLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.promotion.getsearchpromotionbyretailer.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:retailer_name,registered_date,promotion_name,promotion_type,description,begin_date,end_date,updated_at,is_permanent,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.promotion_by_retailer_sortby'),
                )
            );

            Event::fire('orbit.promotion.getsearchpromotionbyretailer.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.promotion.getsearchpromotionbyretailer.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int)Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            // Builder object
            $promotions = DB::table('promotions')
                ->join('promotion_retailer', 'promotions.promotion_id', '=', 'promotion_retailer.promotion_id')
                ->join('merchants', 'promotion_retailer.retailer_id', '=', 'merchants.merchant_id')
                ->select('promotion_retailer.retailer_id', 'merchants.name AS retailer_name', 'promotions.*')
                ->where('promotions.is_coupon', '=', 'N')
                ->where('promotions.status', '!=', 'deleted');

            // Filter promotion by Ids
            OrbitInput::get('promotion_id', function($promotionIds) use ($promotions)
            {
                $promotions->whereIn('promotions.promotion_id', $promotionIds);
            });

            // Filter promotion by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($promotions) {
                $promotions->whereIn('promotions.merchant_id', $merchantIds);
            });

            // Filter promotion by promotion name
            OrbitInput::get('promotion_name', function($promotionname) use ($promotions)
            {
                $promotions->whereIn('promotions.promotion_name', $promotionname);
            });

            // Filter promotion by matching promotion name pattern
            OrbitInput::get('promotion_name_like', function($promotionname) use ($promotions)
            {
                $promotions->where('promotions.promotion_name', 'like', "%$promotionname%");
            });

            // Filter promotion by promotion type
            OrbitInput::get('promotion_type', function($promotionTypes) use ($promotions)
            {
                $promotions->whereIn('promotions.promotion_type', $promotionTypes);
            });

            // Filter promotion by description
            OrbitInput::get('description', function($description) use ($promotions)
            {
                $promotions->whereIn('promotions.description', $description);
            });

            // Filter promotion by matching description pattern
            OrbitInput::get('description_like', function($description) use ($promotions)
            {
                $promotions->where('promotions.description', 'like', "%$description%");
            });

            // Filter promotion by begin date
            OrbitInput::get('begin_date', function($begindate) use ($promotions)
            {
                $promotions->where('promotions.begin_date', '<=', $begindate);
            });

            // Filter promotion by end date
            OrbitInput::get('end_date', function($enddate) use ($promotions)
            {
                $promotions->where('promotions.end_date', '>=', $enddate);
            });

            // Filter promotion by is permanent
            OrbitInput::get('is_permanent', function ($ispermanent) use ($promotions) {
                $promotions->whereIn('promotions.is_permanent', $ispermanent);
            });

            // Filter promotion by status
            OrbitInput::get('status', function ($statuses) use ($promotions) {
                $promotions->whereIn('promotions.status', $statuses);
            });

            // Filter promotion by city
            OrbitInput::get('city', function($city) use ($promotions)
            {
                $promotions->whereIn('merchants.city', $city);
            });

            // Filter promotion by matching city pattern
            OrbitInput::get('city_like', function($city) use ($promotions)
            {
                $promotions->where('merchants.city', 'like', "%$city%");
            });

            // Filter promotion by retailer Ids
            OrbitInput::get('retailer_id', function ($retailerIds) use ($promotions) {
                $promotions->whereIn('promotion_retailer.retailer_id', $retailerIds);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_promotions = clone $promotions;

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
                $promotions->take($take);
            }

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $promotions)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            if (($take > 0) && ($skip > 0)) {
                $promotions->skip($skip);
            }

            // Default sort by
            $sortBy = 'retailer_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'retailer_name'     => 'retailer_name',
                    'registered_date'   => 'promotions.created_at',
                    'promotion_name'    => 'promotions.promotion_name',
                    'promotion_type'    => 'promotions.promotion_type',
                    'description'       => 'promotions.description',
                    'begin_date'        => 'promotions.begin_date',
                    'end_date'          => 'promotions.end_date',
                    'updated_at'        => 'promotions.updated_at',
                    'is_permanent'      => 'promotions.is_permanent',
                    'status'            => 'promotions.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $promotions->orderBy($sortBy, $sortMode);

            $totalPromotions = $_promotions->count();
            $listOfPromotions = $promotions->get();

            $data = new stdclass();
            $data->total_records = $totalPromotions;
            $data->returned_records = count($listOfPromotions);
            $data->records = $listOfPromotions;

            if ($totalPromotions === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.promotion');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.promotion.getsearchpromotionbyretailer.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.promotion.getsearchpromotionbyretailer.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.promotion.getsearchpromotionbyretailer.query.error', array($this, $e));

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
            Event::fire('orbit.promotion.getsearchpromotionbyretailer.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.promotion.getsearchpromotionbyretailer.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of promotion id
        Validator::extend('orbit.empty.promotion', function ($attribute, $value, $parameters) {
            $promotion = Promotion::excludeDeleted()
                        ->where('promotion_id', $value)
                        ->first();

            if (empty($promotion)) {
                return FALSE;
            }

            App::instance('orbit.empty.promotion', $promotion);

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

        // Check promotion name, it should not exists
        Validator::extend('orbit.exists.promotion_name', function ($attribute, $value, $parameters) {
            $merchant_id = OrbitInput::post('current_mall');

            $promotionName = Promotion::excludeDeleted()
                        ->where('promotion_name', $value)
                        ->where('merchant_id', $merchant_id)
                        ->first();

            if (! empty($promotionName)) {
                return FALSE;
            }

            App::instance('orbit.validation.promotion_name', $promotionName);

            return TRUE;
        });

        // Check promotion name, it should not exists (for update)
        Validator::extend('promotion_name_exists_but_me', function ($attribute, $value, $parameters) {
            $promotion_id = trim(OrbitInput::post('promotion_id'));
            $merchant_id = OrbitInput::post('current_mall');

            $promotion = Promotion::excludeDeleted()
                        ->where('promotion_name', $value)
                        ->where('promotion_id', '!=', $promotion_id)
                        ->where('merchant_id', $merchant_id)
                        ->first();

            if (! empty($promotion)) {
                return FALSE;
            }

            App::instance('orbit.validation.promotion_name', $promotion);

            return TRUE;
        });

        // Check the existence of the promotion status
        Validator::extend('orbit.empty.promotion_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the promotion type
        Validator::extend('orbit.empty.promotion_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $promotypes = array('product', 'cart');
            foreach ($promotypes as $promotype) {
                if($value === $promotype) $valid = $valid || TRUE;
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

        // Check the existence of the discount object type
        Validator::extend('orbit.empty.discount_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $discountobjecttypes = array('product', 'family');
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

        // Check the existance of tenant id
        Validator::extend('orbit.empty.tenant', function ($attribute, $value, $parameters) {
            $tenant = Tenant::excludeDeleted()->allowedForUser($this->api->user)
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($tenant)) {
                return FALSE;
            }

            App::instance('orbit.empty.tenant', $tenant);

            return TRUE;
        });

        // check the partner exclusive or not if the is_exclusive is set to 'Y'
        Validator::extend('orbit.empty.exclusive_partner', function ($attribute, $value, $parameters) {
            $flag_exclusive = false;
            $is_exclusive = OrbitInput::post('is_exclusive');
            $partner_ids = OrbitInput::post('partner_ids');
            $partner_ids = (array) $partner_ids;

            $partner_exclusive = Partner::select('is_exclusive')
                           ->whereIn('partner_id', $partner_ids)
                           ->get();

            foreach ($partner_exclusive as $exclusive) {
                if($exclusive->is_exclusive == 'Y'){
                    $flag_exclusive = true;
                }
            }

            $valid = true;

            if ($is_exclusive == 'Y') {
                if ($flag_exclusive) {
                    $valid = true;
                }
                else {
                    $valid = false;
                }
            }

            return $valid;
        });
    }

    /**
     * @param Promotion $promotion
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveTranslations($promotion, $translations_json_string, $scenario = 'create')
    {
        /*
         * JSON structure: object with keys = merchant_language_id and values = ProductTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where PromotionTranslation object is object with keys:
         *   promotion_name, description
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['promotion_name', 'description'];
        $user = $this->api->user;
        $operations = [];

        $data = @json_decode($translations_json_string);
        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
        }
        foreach ($data as $merchant_language_id => $translations) {
            $language = MerchantLanguage::excludeDeleted()
                ->where('merchant_language_id', '=', $merchant_language_id)
                ->first();
            if (empty($language)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
            }
            $existing_translation = PromotionTranslation::excludeDeleted()
                ->where('promotion_id', '=', $promotion->promotion_id)
                ->where('merchant_language_id', '=', $merchant_language_id)
                ->first();
            if ($translations === null) {
                // deleting, verify exists
                if (empty($existing_translation)) {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
                }
                $operations[] = ['delete', $existing_translation];
            } else {
                foreach ($translations as $field => $value) {
                    if (!in_array($field, $valid_fields, TRUE)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.key'));
                    }
                    if ($value !== null && !is_string($value)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.value'));
                    }
                }
                if (empty($existing_translation)) {
                    $operations[] = ['create', $merchant_language_id, $translations];
                } else {
                    $operations[] = ['update', $existing_translation, $translations];
                }
            }
        }

        foreach ($operations as $operation) {
            $op = $operation[0];
            if ($op === 'create') {
                $new_translation = new PromotionTranslation();
                $new_translation->promotion_id = $promotion->promotion_id;
                $new_translation->merchant_language_id = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $new_translation->{$field} = $value;
                }
                $new_translation->created_by = $this->api->user->user_id;
                $new_translation->modified_by = $this->api->user->user_id;
                $new_translation->save();

                // Fire an promotion which listen on orbit.promotion.after.translation.save
                // @param ControllerAPI $this
                // @param PromotionTranslation $new_transalation
                Event::fire('orbit.promotion.after.translation.save', array($this, $new_translation));

                $promotion->setRelation('translation_'. $new_translation->merchant_language_id, $new_translation);
            }
            elseif ($op === 'update') {
                /** @var PromotionTranslation $existing_translation */
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->status = $promotion->status;
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->save();

                // Fire an promotion which listen on orbit.promotion.after.translation.save
                // @param ControllerAPI $this
                // @param PromotionTranslation $existing_transalation
                Event::fire('orbit.promotion.after.translation.save', array($this, $existing_translation));

                $promotion->setRelation('translation_'. $existing_translation->merchant_language_id, $existing_translation);

            }
            elseif ($op === 'delete') {
                /** @var PromotionTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->delete();
            }
        }
    }

}