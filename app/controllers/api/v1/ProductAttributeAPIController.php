<?php
/**
 * API for managing product attributes and its values.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class ProductAttributeAPIController extends ControllerAPI
{
    /**
     * GET - List of Product Attributes.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param array         `id`                    (optional) - List of Ids
     * @param string        `sort_by`               (optional) - column order by
     * @param string        `sort_mode`             (optional) - asc or desc
     * @param string        `attribute_name`        (optional) - attribute name
     * @param string        `attribute_name_like`   (optional) - attribute name like
     * @param array         `with`                  (optional) - relationship included, e.g: 'values', 'merchant'
     * @param array|string  `merchant_id`           (optional) - Id of the merchant, could be array or string with comma separated value
     * @param integer       `take`                  (optional) - limit
     * @param integer       `skip`                  (optional) - limit offset
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchAttribute()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.getattribute.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.getattribute.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.getattribute.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product_attribute')) {
                Event::fire('orbit.product.getattribute.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.view_user');
                $message = Lang::get('validation.orbit.access.view_product_attribute', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.getattribute.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:id,name,created',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.attribute_sortby'),
                )
            );

            Event::fire('orbit.product.getattribute.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.getattribute.after.validation', array($this, $validator));

             // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.product_attribute.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.product_attribute.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Include other relationship
            $with = ['values'];
            OrbitInput::get('with', function($_with) use (&$with) {
                $with = array_merge($_with, $with);
            });

            // Builder object
            $attributes = ProductAttribute::with($with)->excludeDeleted();

            // Filter by ids
            OrbitInput::get('id', function($productIds) use ($attributes) {
                $attributes->whereIn('product_attributes.product_attribute_id', $productIds);
            });

            // Filter by merchant ids
            OrbitInput::get('merchant_id', function($merchantIds) use ($attributes) {
                $attributes->whereIn('product_attributes.merchant_id', $merchantIds);
            });

            // Filter by attribute name
            OrbitInput::get('attribute_name', function ($attributeName) use ($attributes) {
                $attributes->whereIn('product_attributes.product_attribute_name', $attributeName);
            });

            // Filter like attribute name
            OrbitInput::get('attribute_name_like', function ($attributeName) use ($attributes) {
                $attributes->whereIn('product_attributes.product_attribute_name', $attributeName);
            });

            // Filter user by their status
            OrbitInput::get('status', function ($status) use ($attributes) {
                $$attributes->whereIn('product_attributes.status', $status);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_attributes = clone $attributes;

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
            $attributes->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $attributes) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $attributes->skip($skip);

            // Default sort by
            $sortBy = 'product_attributes.product_attribute_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'id'            => 'product_attributes.product_attribute_id',
                    'name'          => 'product_attributes.product_attribute_name',
                    'created'       => 'product_attributes.created_at',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $attributes->orderBy($sortBy, $sortMode);

            $totalAttributes = RecordCounter::create($_attributes)->count();
            $listOfAttributes = $attributes->get();

            $data = new stdclass();
            $data->total_records = $totalAttributes;
            $data->returned_records = count($listOfAttributes);
            $data->records = $listOfAttributes;

            if ($listOfAttributes === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.attribute');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.getattribute.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.getattribute.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.product.getattribute.query.error', array($this, $e));

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
            Event::fire('orbit.product.getattribute.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.product.getattribute.before.render', array($this, &$output));

        return $output;
    }

     /**
     * POST - Add new product attribute
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `merchant_id`           (required) - ID of the merchant
     * @param string    `attribute_name         (required) - Name of the attribute
     * @param array     'attribute_value`       (optional) - The value of attribute
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewAttribute()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $attribute = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postnewattribute.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postnewattribute.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postnewattribute.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_product_attribute')) {
                Event::fire('orbit.product.postnewattribute.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.new_product_attribute');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postnewattribute.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchantId = OrbitInput::post('merchant_id');
            $attributeName = OrbitInput::post('attribute_name');
            $attributeValue = OrbitInput::post('attribute_value');

            $messageAttributeUnique = Lang::get('validation.orbit.exists.product.attribute.unique', array(
                'attrname' => $attributeName
            ));

            $validator = Validator::make(
                array(
                    'merchant_id'           => $merchantId,
                    'attribute_name'        => $attributeName,
                    'attribute_value'       => $attributeValue
                ),
                array(
                    'merchant_id'       => 'required|numeric|orbit.empty.merchant',
                    'attribute_name'    => 'required|orbit.attribute.unique',
                ),
                array(
                    'orbit.attribute.unique'    => $messageAttributeUnique
                )
            );

            Event::fire('orbit.product.postnewattribute.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postnewattribute.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $attribute = new ProductAttribute();
            $attribute->merchant_id = $merchantId;
            $attribute->product_attribute_name = $attributeName;
            $attribute->created_by = $user->user_id;

            Event::fire('orbit.product.postnewattribute.before.save', array($this, $attribute));

            $attribute->save();

            $values = array();

            // Insert attribute values if specified by the caller
            OrbitInput::post('attribute_value', function($attributeValue) use ($attribute, &$values, $user)
            {
                // Parse JSON
                $attributeValue = $this->JSONValidate($attributeValue);

                foreach ($attributeValue as $value) {
                    $attrValue = new ProductAttributeValue();
                    $attrValue->product_attribute_id = $attribute->product_attribute_id;
                    $attrValue->value = $value->attribute_value;
                    $attrValue->status = 'active';
                    $attrValue->created_by = $user->user_id;
                    $attrValue->save();

                    $values[] = $attrValue;
                }
            });

            $attribute->setRelation('values', $values);
            $attribute->values = $values;

            Event::fire('orbit.product.postnewattribute.after.save', array($this, $attribute));
            $this->response->data = $attribute;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Attribute Created: %s', $attribute->product_attribute_name);
            $activity->setUser($user)
                    ->setActivityName('create_attribute')
                    ->setActivityNameLong('Create Attribute OK')
                    ->setObject($attribute)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.product.postnewattribute.after.commit', array($this, $attribute));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postnewattribute.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_attribute')
                    ->setActivityNameLong('Create Attribute Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postnewattribute.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_attribute')
                    ->setActivityNameLong('Create Attribute Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postnewattribute.query.error', array($this, $e));

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
                    ->setActivityName('create_attribute')
                    ->setActivityNameLong('Create Attribute Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.product.postnewattribute.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_attribute')
                    ->setActivityNameLong('Create Attribute Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

     /**
     * POST - Update product attribute
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `product_attribute_id`      (required) - ID of the product attribute
     * @param integer   `merchant_id`               (required) - ID of the merchant
     * @param string    `attribute_name             (required) - Name of the attribute
     * @param array     `attribute_value_new`       (optional) - The value of attribute (new)
     * @param array     `attribute_value_update`    (optional) - The value of attribute (update)
     * @param array     `attribute_value_delete`    (optional) - The value of attribute (delete)
     * @param integer   `is_validation`             (optional) - Valid value: Y. Flag to validate only when deleting attribute value.
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateAttribute()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $attribute = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postupdateattribute.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postupdateattribute.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postupdateattribute.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_product_attribute')) {
                Event::fire('orbit.product.postupdateattribute.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.update_product_attribute');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postupdateattribute.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $attributeId = OrbitInput::post('product_attribute_id');
            $merchantId = OrbitInput::post('merchant_id');
            $attributeName = OrbitInput::post('attribute_name');
            $is_validation = OrbitInput::post('is_validation');

            $messageAttributeUnique = Lang::get('validation.orbit.exists.product.attribute.unique', array(
                'attrname' => $attributeName
            ));

            $validator = Validator::make(
                array(
                    'product_attribute_id'  => $attributeId,
                    'merchant_id'           => $merchantId,
                    'attribute_name'        => $attributeName,
                ),
                array(
                    'product_attribute_id'      => 'required|numeric|orbit.empty.attribute',
                    'merchant_id'               => 'numeric|orbit.empty.merchant',
                    'attribute_name'            => 'orbit.exists.product_attribute_name_have_transaction:'.$attributeId.'|orbit.exists.product_attribute_name_have_product:'.$attributeId.'|orbit.attribute.unique.butme',
                    'attribute_value_deleted'   => 'array',
                ),
                array(
                    'orbit.attribute.unique.butme'                          => $messageAttributeUnique,
                    'orbit.exists.product_attribute_name_have_transaction'  => Lang::get('validation.orbit.exists.product_attribute_have_transaction'),
                    'orbit.exists.product_attribute_name_have_product'      => Lang::get('validation.orbit.exists.product_attribute_have_product')
                )
            );

            Event::fire('orbit.product.postupdateattribute.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postupdateattribute.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $attribute = App::make('orbit.empty.attribute');

            // Check if product attribute name has been changed
            OrbitInput::post('attribute_name', function($name) use ($attribute)
            {
                $attribute->product_attribute_name = $name;
            });

            Event::fire('orbit.product.postupdateattribute.before.save', array($this, $attribute));

            $attribute->save();

            // Hold the attribute values both old and new which has been saved
            $values = array();
            $newValues = array();
            $updatedValues = array();
            $deletedValues = array();

            // Delete attribute value
            OrbitInput::post('attribute_value_delete', function($attributeValueDelete) use ($attribute, &$deletedValues, $user, $is_validation)
            {
                foreach ($attributeValueDelete as $valueId) {
                    // validate attribute value
                    $validator = Validator::make(
                        array(
                            'product_attribute_value_id' => $valueId,
                        ),
                        array(
                            'product_attribute_value_id' => 'orbit.exists.product_attribute_value_have_transaction|orbit.exists.product_attribute_value_have_product_variant',
                        )
                    );

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    // the deletion request is only for validation
                    if (! ($is_validation === 'Y')) {
                        $attrValue = ProductAttributeValue::excludeDeleted()->find($valueId);

                        if (empty($attrValue)) {
                            continue;   // Skip deleting
                        }

                        $attrValue->status = 'deleted';
                        $attrValue->modified_by = $user->user_id;
                        $attrValue->save();

                        $deletedValues[] = $attrValue->product_attribute_value_id;
                    }
                }
            });

            // Update attribute value
            OrbitInput::post('attribute_value_update', function($attributeValueOld) use ($attribute, &$updatedValues, $user)
            {
                $existence = array();

                // Parse JSON
                $attributeValueOld = $this->JSONValidate($attributeValueOld);

                // List of new value in array format
                $oldValueArray = array();
                foreach ($attributeValueOld as $newValue) {
                    $oldValueArray[$newValue->value_id] = $newValue->attribute_value;
                }

                foreach ($oldValueArray as $valueId=>$newValue) {
                    // validate attribute value
                    $validator = Validator::make(
                        array(
                            'product_attribute_value_id' => $valueId,
                        ),
                        array(
                            'product_attribute_value_id' => 'orbit.exists.product_attribute_value_have_transaction|orbit.exists.product_attribute_value_have_product_variant',
                        )
                    );

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    $otherAttributeValue = ProductAttributeValue::where('value', $newValue)
                                                                ->where('product_attribute_id', $attribute->product_attribute_id)
                                                                ->where('product_attribute_value_id', '!=', $valueId)
                                                                ->excludeDeleted()
                                                                ->first();

                    if (! empty($otherAttributeValue)) {
                        // Throw an error since the value are same
                        $errorMessage = Lang::get('validation.orbit.exists.product.attribute.value.unique', ['value' => $otherAttributeValue->value]);
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    // Make sure the product exists
                    $updatedAttributeValue = ProductAttributeValue::where('product_attribute_value_id', $valueId)
                                                                ->where('product_attribute_id', $attribute->product_attribute_id)
                                                                ->excludeDeleted()
                                                                ->first();

                    if (empty($updatedAttributeValue)) {
                        // Throw an error since the value are same
                        $errorMessage = Lang::get('validation.orbit.empty.product_attr.attribute.value', ['id' => htmlentities($valueId)]);
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    $updatedAttributeValue->value = $newValue;
                    $updatedAttributeValue->modified_by = $user->user_id;
                    $updatedAttributeValue->save();

                    $updatedValues[] = $updatedAttributeValue;
                }
            });

            // Insert new attribute value
            OrbitInput::post('attribute_value_new', function($attributeValueNew) use ($attribute, &$newValues, $user)
            {
                $existence = array();

                // Parse JSON
                $attributeValueNew = $this->JSONValidate($attributeValueNew);

                // List of new value in array format
                $newValueArray = array();
                foreach ($attributeValueNew as $newValue) {
                    $newValueArray[] = $newValue->attribute_value;
                }

                // Does the new attribute value already exists
                foreach ($attribute->values as $attrValue) {
                    foreach ($newValueArray as $newValue) {
                        $_newValue = trim(strtolower($newValue));
                        $oldValue = trim(strtolower($attrValue->value));

                        if ($oldValue === $_newValue) {
                            // Throw an error since the value are same
                            $errorMessage = Lang::get('validation.orbit.exists.product.attribute.value.unique', ['value' => htmlentities($attrValue->value)]);
                            OrbitShopAPI::throwInvalidArgument($errorMessage);

                            $existence[] = $newValue;
                        }
                    }
                }

                // Calculate the difference of new value from the existings one
                $attributeDifference = array_diff($newValueArray, $existence);

                foreach ($attributeDifference as $value) {
                    $attrValue = new ProductAttributeValue();
                    $attrValue->product_attribute_id = $attribute->product_attribute_id;
                    $attrValue->value = $value;
                    $attrValue->status = 'active';
                    $attrValue->created_by = $user->user_id;
                    $attrValue->save();

                    $newValues[] = $attrValue;
                }
            });

            $values = array_merge($newValues + $updatedValues);
            if (! empty($values)) {
                $attribute->setRelation('values', $values);
                $attribute->values = $values;
            }

            // Unset the attribute value which has been deleted
            foreach ($deletedValues as $valueId) {
                foreach ($attribute->values as $key=>$origValue) {
                    $origId = (string)$origValue->product_attribute_value_id;
                    if ($origId === (string)$valueId) {
                        $attribute->values->forget($key);
                    }
                }
            }

            Event::fire('orbit.product.postupdateattribute.after.save', array($this, $attribute));
            $this->response->data = $attribute;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Attribute updated: %s', $attribute->product_attribute_name);
            $activity->setUser($user)
                    ->setActivityName('update_attribute')
                    ->setActivityNameLong('Update Attribute OK')
                    ->setObject($attribute)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.product.postupdateattribute.after.commit', array($this, $attribute));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postupdateattribute.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_attribute')
                    ->setActivityNameLong('Update Attribute Failed')
                    ->setObject($attribute)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postupdateattribute.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_attribute')
                    ->setActivityNameLong('Update Attribute Failed')
                    ->setObject($attribute)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postupdateattribute.query.error', array($this, $e));

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
                    ->setActivityName('update_attribute')
                    ->setActivityNameLong('Update Attribute Failed')
                    ->setObject($attribute)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.product.postupdateattribute.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_attribute')
                    ->setActivityNameLong('Update Attribute Failed')
                    ->setObject($attribute)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);
    }

     /**
     * POST - Delete product attribute
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `product_attribute_id`      (required) - ID of the product attribute
     * @param integer   `is_validation`             (optional) - Valid value: Y. Flag to validate only when deleting attribute.
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteAttribute()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $attribute = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postdeleteattribute.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postdeleteattribute.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postdeleteattribute.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_product_attribute')) {
                Event::fire('orbit.product.postdeleteattribute.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.delete_product_attribute');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postdeleteattribute.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $attributeId = OrbitInput::post('product_attribute_id');
            $is_validation = OrbitInput::post('is_validation');

            $validator = Validator::make(
                array(
                    'product_attribute_id'  => $attributeId,
                ),
                array(
                    'product_attribute_id'  => 'required|numeric|orbit.empty.attribute|orbit.exists.product_attribute_have_transaction|orbit.exists.product_attribute_have_product',
                )
            );

            Event::fire('orbit.product.postdeleteattribute.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } elseif ($is_validation === 'Y') { // the deletion request is only for validation
                $this->response->code = 0;
                $this->response->status = 'success';
                $this->response->message = 'Request OK';
                $this->response->data = NULL;

                return $this->render($httpCode);
            }

            Event::fire('orbit.product.postdeleteattribute.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $attribute = App::make('orbit.empty.attribute');

            Event::fire('orbit.product.postdeleteattribute.before.save', array($this, $attribute));

            // Change the status to deleted
            $attribute->status = 'deleted';
            $attribute->modified_by = $user->user_id;
            $attribute->save();

            // soft delete attribute value, by updating a set of models
            $affectedRows = ProductAttributeValue::excludeDeleted()
                                                ->where('product_attribute_id', $attribute->product_attribute_id)
                                                ->update(array('status' => 'deleted', 'modified_by' => $user->user_id));

            Event::fire('orbit.product.postdeleteattribute.after.save', array($this, $attribute));
            $this->response->data = $attribute;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Attribute Deleted: %s', $attribute->product_attribute_name);
            $activity->setUser($user)
                    ->setActivityName('delete_attribute')
                    ->setActivityNameLong('Delete Attribute OK')
                    ->setObject($attribute)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.product.postdeleteattribute.after.commit', array($this, $attribute));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postdeleteattribute.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_attribute')
                    ->setActivityNameLong('Delete Attribute Failed')
                    ->setObject($attribute)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postdeleteattribute.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_attribute')
                    ->setActivityNameLong('Delete Attribute Failed')
                    ->setObject($attribute)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postdeleteattribute.query.error', array($this, $e));

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
                    ->setActivityName('delete_attribute')
                    ->setActivityNameLong('Delete Attribute Failed')
                    ->setObject($attribute)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.product.postdeleteattribute.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_attribute')
                    ->setActivityNameLong('Delete Attribute Failed')
                    ->setObject($attribute)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check the existance of merchant id
        $user = $this->api->user;
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) use ($user){
            $merchant = Merchant::excludeDeleted()
                        ->allowedForUser($user)
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Make sure product attribute exists
        Validator::extend('orbit.empty.attribute', function ($attribute, $value, $parameters) {
            $attribute = ProductAttribute::excludeDeleted()
                                         ->with('values')
                                         ->find($value);

            if (empty($attribute)) {
                return FALSE;
            }

            App::instance('orbit.empty.attribute', $attribute);

            return TRUE;
        });

        // Make sure the name of the attribute is unique on this merchant only
        Validator::extend('orbit.attribute.unique', function ($attribute, $value, $parameters) {
            // Use the value of attribute if post merchant_id is null
            $merchantId = OrbitInput::post('merchant_id', NULL);
            if (empty($merchantId)) {
                $merchantId = $attribute->merchant_id;
            }

            $attribute = ProductAttribute::excludeDeleted()
                                         ->with('values')
                                         ->where('product_attribute_name', $value)
                                         ->where('merchant_id', $merchantId)
                                         ->first();

            if (! empty($attribute)) {
                return FALSE;
            }

            App::instance('orbit.attribute.unique', $attribute);

            return TRUE;
        });

        // Make sure the name of the attribute is unique on this merchant only
        Validator::extend('orbit.attribute.unique.butme', function ($attribute, $value, $parameters) {
            // Get the instance of ProductAttribute object
            $attribute = App::make('orbit.empty.attribute');

            // Use the value of attribute if post merchant_id is null
            $merchantId = OrbitInput::post('merchant_id', NULL);
            if (empty($merchantId)) {
                $merchantId = $attribute->merchant_id;
            }

            $attribute = ProductAttribute::excludeDeleted()
                                         ->with('values')
                                         ->where('product_attribute_name', $value)
                                         ->where('merchant_id', $merchantId)
                                         ->where('product_attribute_id', '!=', $attribute->product_attribute_id)
                                         ->first();

            if (! empty($attribute)) {
                return FALSE;
            }

            App::instance('orbit.attribute.unique.butme', $attribute);

            return TRUE;
        });

        // Check if product attribute have transaction.
        Validator::extend('orbit.exists.product_attribute_have_transaction', function ($attribute, $value, $parameters) {
            // Check inside transaction details to see if this product attribute has a transaction
            $transactionDetail = TransactionDetail::transactionJoin()
                                                ->excludeDeleted('transactions')
                                                ->where(function ($query) use ($value) {
                                                    $query->where('attribute_id1', $value)
                                                        ->orWhere('attribute_id2', $value)
                                                        ->orWhere('attribute_id3', $value)
                                                        ->orWhere('attribute_id4', $value)
                                                        ->orWhere('attribute_id5', $value);
                                                })
                                                ->first();
            if (! empty($transactionDetail)) {
                return FALSE;
            }

            App::instance('orbit.exists.product_attribute_have_transaction', $transactionDetail);

            return TRUE;
        });

        // Check if updating product attribute name, the attribute must not have transaction
        Validator::extend('orbit.exists.product_attribute_name_have_transaction', function ($attribute, $value, $parameters) {
            $product_attribute_id = trim($parameters[0]);
            $attribute = ProductAttribute::excludeDeleted()
                                         ->where('product_attribute_id', $product_attribute_id)
                                         ->where('product_attribute_name', $value)
                                         ->first();

            // if empty, attribute name is being change.
            if (empty($attribute)) {
                // Check inside transaction details to see if this product attribute has a transaction
                $transactionDetail = TransactionDetail::transactionJoin()
                                                    ->excludeDeleted('transactions')
                                                    ->where(function ($query) use ($product_attribute_id) {
                                                        $query->where('attribute_id1', $product_attribute_id)
                                                            ->orWhere('attribute_id2', $product_attribute_id)
                                                            ->orWhere('attribute_id3', $product_attribute_id)
                                                            ->orWhere('attribute_id4', $product_attribute_id)
                                                            ->orWhere('attribute_id5', $product_attribute_id);
                                                    })
                                                    ->first();
                if (! empty($transactionDetail)) {
                    return FALSE;
                }

                App::instance('orbit.exists.product_attribute_name_have_transaction', $transactionDetail);
            }

            return TRUE;
        });

        // Check if product attribute value have transaction.
        Validator::extend('orbit.exists.product_attribute_value_have_transaction', function ($attribute, $value, $parameters) {
            // Check inside transaction details to see if this product attribute value has a transaction
            $transactionDetail = TransactionDetail::transactionJoin()
                                                ->excludeDeleted('transactions')
                                                ->where(function ($query) use ($value) {
                                                    $query->where('product_attribute_value_id1', $value)
                                                        ->orWhere('product_attribute_value_id2', $value)
                                                        ->orWhere('product_attribute_value_id3', $value)
                                                        ->orWhere('product_attribute_value_id4', $value)
                                                        ->orWhere('product_attribute_value_id5', $value);
                                                })
                                                ->first();
            if (! empty($transactionDetail)) {
                return FALSE;
            }

            App::instance('orbit.exists.product_attribute_value_have_transaction', $transactionDetail);

            return TRUE;
        });

        // Check if product attribute have product.
        Validator::extend('orbit.exists.product_attribute_have_product', function ($attribute, $value, $parameters) {
            $product = Product::excludeDeleted()
                            ->where(function ($query) use ($value) {
                                $query->where('attribute_id1', $value)
                                    ->orWhere('attribute_id2', $value)
                                    ->orWhere('attribute_id3', $value)
                                    ->orWhere('attribute_id4', $value)
                                    ->orWhere('attribute_id5', $value);
                            })
                            ->first();
            if (! empty($product)) {
                return FALSE;
            }

            App::instance('orbit.exists.product_attribute_have_product', $product);

            return TRUE;
        });

        // Check if updating product attribute name, the attribute must not have product
        Validator::extend('orbit.exists.product_attribute_name_have_product', function ($attribute, $value, $parameters) {
            $product_attribute_id = trim($parameters[0]);
            $attribute = ProductAttribute::excludeDeleted()
                                         ->where('product_attribute_id', $product_attribute_id)
                                         ->where('product_attribute_name', $value)
                                         ->first();

            // if empty, attribute name is being change.
            if (empty($attribute)) {
                // check products table
                $product = Product::excludeDeleted()
                                ->where(function ($query) use ($product_attribute_id) {
                                    $query->where('attribute_id1', $product_attribute_id)
                                        ->orWhere('attribute_id2', $product_attribute_id)
                                        ->orWhere('attribute_id3', $product_attribute_id)
                                        ->orWhere('attribute_id4', $product_attribute_id)
                                        ->orWhere('attribute_id5', $product_attribute_id);
                                })
                                ->first();
                if (! empty($product)) {
                    return FALSE;
                }

                App::instance('orbit.exists.product_attribute_name_have_product', $product);
            }

            return TRUE;
        });

        // Check if product attribute value have product variant
        Validator::extend('orbit.exists.product_attribute_value_have_product_variant', function ($attribute, $value, $parameters) {
            $productVariant = ProductVariant::excludeDeleted()
                                            ->where(function ($query) use ($value) {
                                                $query->where('product_attribute_value_id1', $value)
                                                    ->orWhere('product_attribute_value_id2', $value)
                                                    ->orWhere('product_attribute_value_id3', $value)
                                                    ->orWhere('product_attribute_value_id4', $value)
                                                    ->orWhere('product_attribute_value_id5', $value);
                                            })
                                            ->first();
            if (! empty($productVariant)) {
                return FALSE;
            }

            App::instance('orbit.exists.product_attribute_value_have_product_variant', $productVariant);

            return TRUE;
        });
    }

    /**
     * Validate a JSON.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $string - JSON string to parse.
     * @return mixed
     */
    protected function JSONValidate($string) {
        $errorMessage = Lang::get('validation.orbit.jsonerror.format');

        if (! is_string($string)) {
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        $result = @json_decode($string);
        if (json_last_error() !== JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        $errorMessage = Lang::get('validation.orbit.jsonerror.array');
        if (! is_array($result)) {
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        return $result;
    }
}
