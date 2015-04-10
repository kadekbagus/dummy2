<?php

/**
 * An API controller for managing products.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Arrays\Util\DuplicateChecker as ArrayChecker;
use Helper\EloquentRecordCounter as RecordCounter;

class ProductAPIController extends ControllerAPI
{

    /**
     * POST - Update Product
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Kadek <kadek@dominopos.com>
     * @author Tian <tian@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `product_id`                    (required) - ID of the product
     * @param integer   `merchant_id`                   (optional) - ID of the merchant
     * @param string    `product_code`                  (optional) - Product code
     * @param string    `upc_code`                      (optional) - Product UPC code
     * @param string    `product_name`                  (optional) - Product name
     * @param string    `image`                         (optional) - Product image
     * @param string    `short_description`             (optional) - Product short description
     * @param string    `long_description`              (optional) - Product long description
     * @param string    `is_featured`                   (optional) - is featured
     * @param string    `new_from`                      (optional) - new from
     * @param string    `new_until`                     (optional) - new until
     * @param string    `in_store_localization`         (optional) - in store localization
     * @param string    `post_sales_url`                (optional) - post sales url
     * @param decimal   `price`                         (optional) - Price of the product
     * @param string    `merchant_tax_id1`              (optional) - Tax 1
     * @param string    `merchant_tax_id2`              (optional) - Tax 2
     * @param string    `status`                        (optional) - Status
     * @param integer   `created_by`                    (optional) - ID of the creator
     * @param integer   `modified_by`                   (optional) - Modify by
     * @param array     `retailer_ids`                  (optional) - ORID links
     * @param array     `no_retailer`                   (optional) - Flag to delete all ORID links
     * @param images    `images`                        (optional) - Product image
     * @param integer   `category_id1`                  (optional) - Category ID1.
     * @param integer   `category_id2`                  (optional) - Category ID2.
     * @param integer   `category_id3`                  (optional) - Category ID3.
     * @param integer   `category_id4`                  (optional) - Category ID4.
     * @param integer   `category_id5`                  (optional) - Category ID5.
     * @param string    `product_variants`              (optional) - JSON String for new product combination
     * @param string    `product_variants_update`       (optional) - JSON String for updated product combination
     * @param array     `product_variants_delete`       (optional) - Array of variant id
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateProduct()
    {
        $activityProduct = Activity::portal()
                                   ->setActivityType('update');

        $user = NULL;
        $updatedproduct = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.product.postupdateproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postupdateproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postupdateproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('update_product')) {
                Event::fire('orbit.product.postupdateproduct.authz.notallowed', array($this, $user));
                $updateProductLang = Lang::get('validation.orbit.actionlist.update_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateProductLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postupdateproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $product_id = OrbitInput::post('product_id');
            $merchant_id = OrbitInput::post('merchant_id');

            // product_code is the same as SKU
            $product_code = OrbitInput::post('product_code');

            $upc_code = OrbitInput::post('upc_code');
            $category_id1 = OrbitInput::post('category_id1');
            $category_id2 = OrbitInput::post('category_id2');
            $category_id3 = OrbitInput::post('category_id3');
            $category_id4 = OrbitInput::post('category_id4');
            $category_id5 = OrbitInput::post('category_id5');

            // Product Variants Delete
            $product_combinations_delete = OrbitInput::post('product_variants_delete');

            $validator = Validator::make(
                array(
                    'product_id'        => $product_id,
                    'merchant_id'       => $merchant_id,
                    'product_code'      => $product_code,
                    'upc_code'          => $upc_code,
                    'category_id1'      => $category_id1,
                    'category_id2'      => $category_id2,
                    'category_id3'      => $category_id3,
                    'category_id4'      => $category_id4,
                    'category_id5'      => $category_id5,
                    'product_variants_delete'    => $product_combinations_delete
                ),
                array(
                    'product_id'        => 'required|numeric|orbit.empty.product',
                    'upc_code'          => 'orbit.exists.product.upc_code_but_me',
                    'product_code'      => 'orbit.exists.product.sku_code_but_me',
                    'merchant_id'       => 'numeric|orbit.empty.merchant',
                    'category_id1'      => 'numeric|orbit.empty.category_id1',
                    'category_id2'      => 'numeric|orbit.empty.category_id2',
                    'category_id3'      => 'numeric|orbit.empty.category_id3',
                    'category_id4'      => 'numeric|orbit.empty.category_id4',
                    'category_id5'      => 'numeric|orbit.empty.category_id5',
                    'product_variants_delete'   => 'array|orbit.empty.product_variant_array'
                ),
                array(
                    'orbit.empty.product_variant_array'     => Lang::get('validation.orbit.empty.product_attr.attribute.variant'),
                    'orbit.exists.product.upc_code_but_me'  => Lang::get('validation.orbit.exists.product.upc_code', [
                        'upc' => $upc_code
                    ]),
                    'orbit.exists.product.sku_code_but_me'  => Lang::get('validation.orbit.exists.product.sku_code', [
                        'sku' => $product_code
                    ])
                )
            );

            Event::fire('orbit.product.postupdateproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postupdateproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updatedproduct = App::make('orbit.empty.product');
            App::instance('memory:current.updated.product', $updatedproduct);

            // Flag for product which already had a transaction
            $productHasTransaction = FALSE;

            // Check inside transaction details to see if this product has
            // a transaction
            $transactionDetailProduct = TransactionDetail::transactionJoin()
                                                         ->where('product_id', $updatedproduct->product_id)
                                                         ->excludeDeleted('transactions')
                                                         ->first();
            if (is_object($transactionDetailProduct)) {
                $productHasTransaction = TRUE;
            }

            OrbitInput::post('product_name', function($product_name) use ($updatedproduct, $productHasTransaction) {
                // This is sucks, why we need check it? The frontend should not send it to us!
                if ((string)$updatedproduct->product_name !== (string)$product_name) {
                    if ($productHasTransaction) {
                        $errorMessage = Lang::get('validation.orbit.exists.product.transaction', ['name' => $updatedproduct->product_name]);
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }
                }
                $updatedproduct->product_name = $product_name;
            });

            OrbitInput::post('image', function($image) use ($updatedproduct) {
                $updatedproduct->image = $image;
            });

            OrbitInput::post('product_code', function($code) use ($updatedproduct, $productHasTransaction) {
                // This is sucks, why we need check it? The frontend should not send it to us!
                if ((string)$updatedproduct->product_code !== (string)$code) {
                    if ($productHasTransaction) {
                        $errorMessage = Lang::get('validation.orbit.exists.product.transaction', ['name' => $updatedproduct->product_name]);
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }
                }
                $updatedproduct->product_code = $code;
            });

            OrbitInput::post('upc_code', function($code) use ($updatedproduct, $productHasTransaction) {
                // This is sucks, why we need check it? The frontend should not send it to us!
                if ((string)$updatedproduct->upc_code !== (string)$code) {
                    if ($productHasTransaction) {
                        $errorMessage = Lang::get('validation.orbit.exists.product.transaction', ['name' => $updatedproduct->product_name]);
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }
                }
                $updatedproduct->upc_code = $code;
            });

            OrbitInput::post('short_description', function($short_description) use ($updatedproduct) {
                $updatedproduct->short_description = $short_description;
            });

            OrbitInput::post('long_description', function($long_description) use ($updatedproduct) {
                $updatedproduct->long_description = $long_description;
            });

            OrbitInput::post('is_featured', function($is_featured) use ($updatedproduct) {
                $updatedproduct->is_featured = $is_featured;
            });

            OrbitInput::post('new_from', function($new_from) use ($updatedproduct) {
                $updatedproduct->new_from = $new_from;
            });

            OrbitInput::post('new_until', function($new_until) use ($updatedproduct) {
                $updatedproduct->new_until = $new_until;
            });

            OrbitInput::post('in_store_localization', function($in_store_localization) use ($updatedproduct) {
                $updatedproduct->in_store_localization = $in_store_localization;
            });

            OrbitInput::post('post_sales_url', function($post_sales_url) use ($updatedproduct) {
                $updatedproduct->post_sales_url = $post_sales_url;
            });

            OrbitInput::post('price', function($price) use ($updatedproduct) {
                $updatedproduct->price = $price;
            });

            OrbitInput::post('merchant_tax_id1', function($merchant_tax_id1) use ($updatedproduct, $productHasTransaction) {
                // This is sucks, why we need check it? The frontend should not send it to us!
                if ((string)$merchant_tax_id1 !== (string)$updatedproduct->merchant_tax_id1) {
                    if ($productHasTransaction) {
                        $errorMessage = Lang::get('validation.orbit.exists.product.transaction', ['name' => $updatedproduct->product_name]);
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }
                }
                $updatedproduct->merchant_tax_id1 = $merchant_tax_id1;
            });

            OrbitInput::post('merchant_tax_id2', function($merchant_tax_id2) use ($updatedproduct, $productHasTransaction) {
                $updatedproduct->merchant_tax_id2 = $merchant_tax_id2;
            });

            OrbitInput::post('status', function($status) use ($updatedproduct) {
                $updatedproduct->status = $status;
            });

            OrbitInput::post('created_by', function($created_by) use ($updatedproduct) {
                $updatedproduct->created_by = $created_by;
            });

            OrbitInput::post('category_id1', function($category_id1) use ($updatedproduct) {
                if (trim($category_id1) === '') {
                    $category_id1 = NULL;
                }
                $updatedproduct->category_id1 = (int) $category_id1;
                $updatedproduct->load('category1');
            });

            OrbitInput::post('category_id2', function($category_id2) use ($updatedproduct) {
                if (trim($category_id2) === '') {
                    $category_id2 = NULL;
                }
                $updatedproduct->category_id2 = (int) $category_id2;
                $updatedproduct->load('category2');
            });

            OrbitInput::post('category_id3', function($category_id3) use ($updatedproduct) {
                if (trim($category_id3) === '') {
                    $category_id3 = NULL;
                }
                $updatedproduct->category_id3 = (int) $category_id3;
                $updatedproduct->load('category3');
            });

            OrbitInput::post('category_id4', function($category_id4) use ($updatedproduct) {
                if (trim($category_id4) === '') {
                    $category_id4 = NULL;
                }
                $updatedproduct->category_id4 = (int) $category_id4;
                $updatedproduct->load('category4');
            });

            OrbitInput::post('category_id5', function($category_id5) use ($updatedproduct) {
                if (trim($category_id5) === '') {
                    $category_id5 = NULL;
                }
                $updatedproduct->category_id5 = (int) $category_id5;
                $updatedproduct->load('category5');
            });

            OrbitInput::post('no_retailer', function($no_retailer) use ($updatedproduct) {
                if ($no_retailer == 'y') {
                    $deleted_retailer_ids = ProductRetailer::where('product_id', $updatedproduct->product_id)->get(array('retailer_id'))->toArray();
                    $updatedproduct->retailers()->detach($deleted_retailer_ids);
                    $updatedproduct->load('retailers');
                }
            });

            OrbitInput::post('retailer_ids', function($retailer_ids) use ($updatedproduct) {
                // validate retailer_ids
                $retailer_ids = (array) $retailer_ids;
                foreach ($retailer_ids as $retailer_id_check) {
                    $validator = Validator::make(
                        array(
                            'retailer_id'   => $retailer_id_check,

                        ),
                        array(
                            'retailer_id'   => 'numeric|orbit.empty.retailer',
                        )
                    );

                    Event::fire('orbit.product.postupdateproduct.before.retailervalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.product.postupdateproduct.after.retailervalidation', array($this, $validator));
                }
                // sync new set of retailer ids
                $updatedproduct->retailers()->sync($retailer_ids);

                // reload retailers relation
                $updatedproduct->load('retailers');
            });

            $lastAttributeIndexNumber = $updatedproduct->getLastAttributeIndexNumber();

            // Save new product variants (combination)
            $variants = array();
            OrbitInput::post('product_variants', function($product_combinations)
            use ($user, $updatedproduct, &$variants, $lastAttributeIndexNumber)
            {
                $variant_decode = $this->JSONValidate($product_combinations);
                $attribute_values = $this->checkVariant($variant_decode);
                $merchant_id = $updatedproduct->merchant_id;

                foreach ($variant_decode as $variant_index=>$variant) {
                    // Return the default price if the variant price is empty
                    $vprice = function() use ($variant, $updatedproduct) {
                        if (empty($variant->price)) {
                            return $updatedproduct->price;
                        }

                        return $variant->price;
                    };

                    // Return the default sku if the variant sku is empty
                    $vsku = function() use ($variant, $updatedproduct) {
                        if (empty($variant->sku)) {
                            return $updatedproduct->product_code;
                        }

                        return $variant->sku;
                    };

                    // Return the default upc if the variant upc is empty
                    $vupc = function() use ($variant, $updatedproduct) {
                        if (empty($variant->upc)) {
                            return $updatedproduct->upc_code;
                        }

                        return $variant->upc;
                    };

                    $product_variant = new ProductVariant();
                    $product_variant->product_id = $updatedproduct->product_id;
                    $product_variant->price = $vprice();
                    $product_variant->sku = $vsku();
                    $product_variant->upc = $vupc();
                    $product_variant->merchant_id = $merchant_id;
                    $product_variant->created_by = $user->user_id;
                    $product_variant->status = 'active';
                    $product_variant->default_variant = 'no';

                    // Check the validity of each attribute value sent
                    foreach ($variant->attribute_values as $i=>$value_id) {
                        $attributeIndex = $i + 1;

                        $field_value_id = 'product_attribute_value_id' . $attributeIndex;
                        $product_variant->{$field_value_id} = $value_id;

                        // We check the value of the old attribute_id on the products table
                        // then compare it with attribute_id of this new variant.
                        // If it is not same then we should not process it
                        $old_product_id = (string)$updatedproduct->{'attribute_id' . $attributeIndex};
                        if (empty($old_product_id)) {
                            continue;
                        }

                        if (empty($value_id)) {
                            continue;
                        }

                        // This ProductAttributeValue object is to get the product attribute_id
                        // of the new variant
                        $_attribute_value = ProductAttributeValue::where('product_attribute_value_id', $value_id)->first();
                        if (empty($_attribute_value)) {
                            $errorMessage = Lang::get('validation.orbit.empty.product_attr.attribute.value', array('id' => $value_id));
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        // Compare it
                        if ((string)$_attribute_value->product_attribute_id !== $old_product_id) {
                            $old_product_attribute_name = $old_product_id;
                            $new_product_attribute_name = $_attribute_value->product_attribute_id;

                            $old_product_attribute_name_obj = ProductAttribute::find($old_product_id);
                            $new_product_attribute_name_obj = ProductAttribute::find($_attribute_value->product_attribute_id);

                            if (is_object($old_product_attribute_name_obj)) {
                                // Get the attribute name
                                $old_product_attribute_name = $old_product_attribute_name_obj->product_attribute_name;
                            }

                            if (is_object($new_product_attribute_name_obj)) {
                                // Get the attribute name
                                $new_product_attribute_name = $new_product_attribute_name_obj->product_attribute_name;
                            }

                            $errorMessage = Lang::get('validation.orbit.formaterror.product_attr.attribute.value.order', [
                                                    'expect' => $old_product_attribute_name,
                                                    'got' => $new_product_attribute_name
                            ]);
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }
                    }
                    $product_variant->save();
                    $product_variant->has_transaction = 'no';

                    $variants[] = $product_variant;
                }

                $this->keepProductColumnUpToDate($updatedproduct);
            });

            // Save existing product variants (combination)
            OrbitInput::post('product_variants_update', function($product_combinations_update)
            use ($user, $updatedproduct, &$variants, $lastAttributeIndexNumber)
            {
                $variant_decode = $this->JSONValidate($product_combinations_update);
                $attribute_values = $this->checkVariant($variant_decode, 'update');
                $merchant_id = $updatedproduct->merchant_id;

                // Get current variant id
                $current_variant = function($_variant_id) use ($updatedproduct) {
                    foreach ($updatedproduct->variants as $tmp_variant) {
                        if ((string)$tmp_variant->product_variant_id !== (string)$_variant_id) {
                            continue;
                        }

                        return $tmp_variant;
                    }
                };

                foreach ($variant_decode as $variant_index=>$variant) {
                    // Flag for particular product variant which should be edited
                    $has_transaction = FALSE;

                    $product_variant = $current_variant($variant->variant_id);
                    $product_variant->modified_by = $user->user_id;

                    $transaction_detail = TransactionDetail::TransactionJoin()
                                                          ->ExcludeDeletedTransaction()
                                                          ->where('transaction_details.product_variant_id', $product_variant->product_variant_id)
                                                          ->first();

                    if (is_object($transaction_detail)) {
                        $has_transaction = TRUE;
                    }

                    // Return the default price if the variant price is empty
                    $price = function() use ($variant, $has_transaction, $product_variant) {
                        if (empty($variant->price)) {
                            return $product_variant->price;
                        }

                        return $variant->price;
                    };

                    // Return the default sku if the variant sku is empty
                    $sku = function() use ($variant, $has_transaction, $product_variant) {
                        if (empty($variant->sku)) {
                            return $product_variant->sku;
                        }

                        if ($has_transaction) {
                            // Reject the saving if user change the SKU which has
                            // already transaction
                            if ((string)$variant->sku !== (string)$product_variant->sku) {
                                $errorMessage = Lang::get('validation.orbit.exists.product.variant.transaction',
                                    ['id' => $variant->variant_id]
                                );
                                OrbitShopAPI::throwInvalidArgument($errorMessage);
                            }
                        }

                        return $variant->sku;
                    };

                    // Return the default upc if the variant upc is empty
                    $upc = function() use ($variant, $has_transaction, $product_variant) {
                        if (empty($variant->upc)) {
                            return $product_variant->upc;
                        }

                        if ($has_transaction) {
                            // Reject the saving if user change the SKU which has
                            // already transaction
                            if ((string)$variant->upc !== (string)$product_variant->upc) {
                                $errorMessage = Lang::get('validation.orbit.exists.product.variant.transaction',
                                    ['id' => $variant->variant_id]
                                );
                                OrbitShopAPI::throwInvalidArgument($errorMessage);
                            }
                        }

                        return $variant->upc;
                    };

                    $product_variant->price = $price();
                    $product_variant->sku = $sku();
                    $product_variant->upc = $upc();

                    // Check the validity of each attribute value sent
                    foreach ($variant->attribute_values as $i=>$value_id) {
                        $attributeIndex = $i + 1;

                        $field_value_id = 'product_attribute_value_id' . $attributeIndex;
                        $product_variant->{$field_value_id} = $value_id;

                        // We check the value of the old attribute_id on the products table
                        // then compare it with attribute_id of this new variant.
                        // If it is not same then we should not process it
                        $old_product_id = (string)$updatedproduct->{'attribute_id' . $attributeIndex};
                        if (empty($old_product_id)) {
                            continue;
                        }

                        if (empty($value_id)) {
                            continue;
                        }

                        // This ProductAttributeValue object is to get the product attribute_id
                        // of the new variant
                        $_attribute_value = ProductAttributeValue::where('product_attribute_value_id', $value_id)->first();
                        if (empty($_attribute_value)) {
                            $errorMessage = Lang::get('validation.orbit.empty.product_attr.attribute.value', array('id' => $value_id));
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }

                        // Compare it
                        if ((string)$_attribute_value->product_attribute_id !== $old_product_id) {
                            $errorMessage = Lang::get('validation.orbit.formaterror.product_attr.attribute.value.order', [
                                                    'expect' => $old_product_id,
                                                    'got' => $_attribute_value->product_attribute_id
                            ]);
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }
                    }
                    $product_variant->save();
                    $product_variant->has_transaction = 'no';

                    if ($has_transaction) {
                        $product_variant->has_transaction = 'yes';
                    }

                    $variants[] = $product_variant;
                }

                $this->keepProductColumnUpToDate($updatedproduct);
            });

            // Delete product variant
            OrbitInput::post('product_variants_delete', function($product_combinations_delete) use ($updatedproduct, $user, &$variants)
            {
                $_variants = App::make('memory:deleted.variants');
                foreach ($_variants as $variant) {
                    $transaction_detail_variant = TransactionDetail::TransactionJoin()
                                                          ->ExcludeDeletedTransaction()
                                                          ->where('transaction_details.product_variant_id', $variant->product_variant_id)
                                                          ->first();

                    if (is_object($transaction_detail_variant)) {
                        $errorMessage = Lang::get('validation.orbit.exists.product.variant.transaction', array('id' => $variant->product_variant_id));
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    // Unset variant which has been deleted
                    // So it does not returned on the result
                    foreach ($variants as $i=>$v) {
                        if ((string)$v->product_variant_id === (string)$variant->product_variant_id) {
                            unset($variants[$i]);
                        }
                    }

                    $variant->modified_by = $user->user_id;
                    $variant->delete();
                }

                $this->keepProductColumnUpToDate($updatedproduct);
            });

            $updatedproduct->modified_by = $this->api->user->user_id;

            Event::fire('orbit.product.postupdateproduct.before.save', array($this, $updatedproduct));

            $updatedproduct->save();

            $updatedproduct->setRelation('variants', $variants);
            $updatedproduct->variants = $variants;

            $updatedproduct->load('category1');
            $updatedproduct->load('category2');
            $updatedproduct->load('category3');
            $updatedproduct->load('category4');
            $updatedproduct->load('category5');

            // Create default variant for this product
            ProductVariant::createDefaultVariant($updatedproduct);

            Event::fire('orbit.product.postupdateproduct.after.save', array($this, $updatedproduct));
            $this->response->data = $updatedproduct;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityProductNotes = sprintf('Product updated: %s', $updatedproduct->product_name);
            $activityProduct->setUser($user)
                            ->setActivityName('update_product')
                            ->setActivityNameLong('Update Product OK')
                            ->setObject($updatedproduct)
                            ->setNotes($activityProductNotes)
                            ->responseOK();

            Event::fire('orbit.product.postupdateproduct.after.commit', array($this, $updatedproduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postupdateproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activityProduct->setUser($user)
                            ->setActivityName('update_product')
                            ->setActivityNameLong('Update Product Failed')
                            ->setObject($updatedproduct)
                            ->setNotes($e->getMessage())
                            ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postupdateproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activityProduct->setUser($user)
                            ->setActivityName('update_product')
                            ->setActivityNameLong('Update Product Failed')
                            ->setObject($updatedproduct)
                            ->setNotes($e->getMessage())
                            ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postupdateproduct.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activityProduct->setUser($user)
                            ->setActivityName('update_product')
                            ->setActivityNameLong('Update Product Failed')
                            ->setObject($updatedproduct)
                            ->setNotes($e->getMessage())
                            ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.product.postupdateproduct.general.exception', array($this, $e));

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
            $activityProduct->setUser($user)
                            ->setActivityName('update_product')
                            ->setActivityNameLong('Update Product Failed')
                            ->setObject($updatedproduct)
                            ->setNotes($e->getMessage())
                            ->responseFailed();
        }

        // Save activity
        $activityProduct->save();

        return $this->render($httpCode);
    }

    /**
     * GET - Search Product
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `with`                     (optional) - Valid value: family.
     * @param array      `product_id`               (optional) - ID of the product
     * @param string     `product_code`             (optional)
     * @param string     `product_name`             (optional)
     * @param string     `short_description`        (optional)
     * @param string     `long_description`         (optional)
     * @param string     `product_name_like`        (optional)
     * @param string     `short_description_like`   (optional)
     * @param string     `long_description_like`    (optional)
     * @param integer    `merchant_id`              (optional)
     * @param integer    `status`                   (optional)
     * @param array      `merchant_tax_id1`         (optional)
     * @param array      `merchant_tax_id2`         (optional)
     * @param string     `is_current_retailer_only` (optional) - To show current retailer product only. Valid value: Y
     * @param array      `retailer_ids`             (optional) - IDs of the retailer
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchProduct()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.product.getsearchproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.getsearchproduct.after.auth', array($this));

            // Try to check access control list, does this product allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.getsearchproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_product')) {
                Event::fire('orbit.product.getsearchproduct.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.getsearchproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                    'product_id' => OrbitInput::get('product_id'),
                ),
                array(
                    'sort_by'       => 'in:registered_date,product_id,product_name,product_sku,product_code,product_upc,product_price,product_short_description,product_long_description,product_is_new,product_new_until,product_merchant_id,product_status',
                    'product_id'    => 'array|min:1'
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.product_sortby'),
                )
            );

            Event::fire('orbit.product.getsearchproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.product.getsearchproduct.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.product.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.product.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $now = date('Y-m-d H:i:s');
            $products = Product::with('retailers')
                                ->excludeDeleted()
                                ->allowedForUser($user)
                                ->select('products.*', DB::raw('CASE WHEN (new_from <= "'.$now.'" AND new_from != "0000-00-00 00:00:00") AND (new_until >= "'.$now.'" OR new_until = "0000-00-00 00:00:00") THEN "Yes" ELSE "No" END AS is_new'));

            // Check the value of `with_params` argument
            OrbitInput::get('with_params', function ($withParams) use ($products) {
                if (isset($withParams['variant.exclude_default'])) {
                    if ($withParams['variant.exclude_default'] === 'yes') {
                        Config::set('model:product.variant.exclude_default', 'yes');
                    }
                }

                if (isset($withParams['variant.include_transaction_status'])) {
                    if ($withParams['variant.include_transaction_status'] === 'yes') {
                        Config::set('model:product.variant.include_transaction_status', 'yes');
                    }
                }
            });

            // Filter product by Ids
            OrbitInput::get('product_id', function ($productIds) use ($products) {
                $products->whereIn('products.product_id', $productIds);
            });

            // Filter product by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($products) {
                $products->whereIn('products.merchant_id', $merchantIds);
            });

            // Filter product by product code
            OrbitInput::get('product_code', function ($product_code) use ($products) {
                $products->whereIn('products.product_code', $product_code);
            });

            // Filter product by name
            OrbitInput::get('product_name', function ($name) use ($products) {
                $products->whereIn('products.product_name', $name);
            });

            // Filter product by name pattern
            OrbitInput::get('product_name_like', function ($name) use ($products) {
                $products->where('products.product_name', 'like', "%$name%");
            });

            // Filter product by short description
            OrbitInput::get('short_description', function ($short_description) use ($products) {
                $products->whereIn('products.short_description', $short_description);
            });

            // Filter product by short description pattern
            OrbitInput::get('short_description_like', function ($short_description) use ($products) {
                $products->where('products.short_description', 'like', "%$short_description%");
            });

            // Filter product by long description
            OrbitInput::get('long_description', function ($long_description) use ($products) {
                $products->whereIn('products.long_description', $long_description);
            });

            // Filter product by long description pattern
            OrbitInput::get('long_description_like', function ($long_description) use ($products) {
                $products->where('products.long_description', 'like', "%$long_description%");
            });

            // Filter product by status
            OrbitInput::get('status', function ($status) use ($products) {
                $products->whereIn('products.status', $status);
            });

            // Filter product by merchant_tax_id1
            OrbitInput::get('merchant_tax_id1', function ($merchant_tax_id1) use ($products) {
                $products->whereIn('products.merchant_tax_id1', $merchant_tax_id1);
            });

            // Filter product by merchant_tax_id2
            OrbitInput::get('merchant_tax_id2', function ($merchant_tax_id2) use ($products) {
                $products->whereIn('products.merchant_tax_id2', $merchant_tax_id2);
            });

            // Filter product by retailer_ids
            OrbitInput::get('retailer_ids', function($retailerIds) use ($products) {
                $products->whereHas('retailers', function($q) use ($retailerIds) {
                    $q->whereIn('product_retailer.retailer_id', $retailerIds);
                });
            });

            // Filter product by current retailer
            OrbitInput::get('is_current_retailer_only', function ($is_current_retailer_only) use ($products) {
                if ($is_current_retailer_only === 'Y') {
                    $retailer_id = Setting::where('setting_name', 'current_retailer')->first();
                    if (! empty($retailer_id)) {
                        $products->whereHas('retailers', function($q) use ($retailer_id) {
                                    $q->where('product_retailer.retailer_id', $retailer_id->setting_value);
                                });
                    }
                }
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($products) {
                $with = (array) $with;
                foreach ($with as $relation) {
                    if ($relation === 'family') {
                        $with = array_merge($with, array('category1', 'category2', 'category3', 'category4', 'category5'));
                        break;
                    }
                }
                $products->with($with);
            });

            $_products = clone $products;

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
            $products->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $products) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $products->skip($skip);

            // Default sort by
            $sortBy = 'products.product_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'           => 'products.created_at',
                    'product_id'                => 'products.product_id',
                    'product_name'              => 'products.product_name',
                    'product_sku'               => 'products.product_code',
                    'product_code'              => 'products.product_code',
                    'product_upc'               => 'products.upc_code',
                    'product_price'             => 'products.price',
                    'product_short_description' => 'products.short_description',
                    'product_long_description'  => 'products.long_description',
                    'product_is_new'            => 'is_new',
                    'product_new_until'         => 'products.new_until',
                    'product_merchant_id'       => 'products.merchant_id',
                    'product_status'            => 'products.status',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $products->orderBy($sortBy, $sortMode);

            $totalRec = RecordCounter::create($_products)->count();
            $listOfRec = $products->get();

            $data = new stdclass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if ($totalRec === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.product');
            }

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.getsearchproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.getsearchproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.product.getsearchproduct.query.error', array($this, $e));

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
            Event::fire('orbit.product.getsearchproduct.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        $output = $this->render($httpCode);
        Event::fire('orbit.product.getsearchproduct.before.render', array($this, &$output));

        return $output;
    }

    /**
     * POST - Add new product
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Tian <tian@dominopos.com>
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer   `merchant_id`               (required) - ID of the merchant
     * @param string    `product_code`              (optional) - Product code
     * @param string    `upc_code`                  (optional) - Merchant description
     * @param string    `product_name`              (required) - Product name
     * @param string    `image`                     (optional) - Product image
     * @param string    `short_description`         (optional) - Product short description
     * @param string    `long_description`          (optional) - Product long description
     * @param string    `is_featured`               (optional) - is featured
     * @param string    `new_from`                  (optional) - new from
     * @param string    `new_until`                 (optional) - new until
     * @param string    `in_store_localization`     (optional) - in store localization
     * @param string    `post_sales_url`            (optional) - post sales url
     * @param decimal   `price`                     (optional) - Price of the product
     * @param string    `merchant_tax_id1`          (optional) - Tax 1
     * @param string    `merchant_tax_id2`          (optional) - Tax 2
     * @param string    `status`                    (required) - Status
     * @param integer   `created_by`                (optional) - ID of the creator
     * @param integer   `modified_by`               (optional) - Modify by
     * @param file      `images`                    (optional) - Product Image
     * @param array     `retailer_ids`              (optional) - ORID links
     * @param integer   `category_id1`              (optional) - Category ID1.
     * @param integer   `category_id2`              (optional) - Category ID2.
     * @param integer   `category_id3`              (optional) - Category ID3.
     * @param integer   `category_id4`              (optional) - Category ID4.
     * @param integer   `category_id5`              (optional) - Category ID5.
     * @param integer   `product_variants`          (optional) - JSON String of Product Combination
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewProduct()
    {
        $activityProduct = Activity::portal()
                                   ->setActivityType('create');

        $user = NULL;
        $newproduct = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.product.postnewproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postnewproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postnewproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('create_product')) {
                Event::fire('orbit.merchant.postnewproduct.authz.notallowed', array($this, $user));
                $createProductLang = Lang::get('validation.orbit.actionlist.new_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createProductLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postnewproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('merchant_id');

            // This product_code is the same as SKU
            $product_code = OrbitInput::post('product_code');

            $upc_code = OrbitInput::post('upc_code');
            $product_name = OrbitInput::post('product_name');
            $image = OrbitInput::post('image');
            $short_description = OrbitInput::post('short_description');
            $long_description = OrbitInput::post('long_description');
            $is_featured = OrbitInput::post('is_featured');
            $new_from = OrbitInput::post('new_from');
            $new_until = OrbitInput::post('new_until');
            $in_store_localization = OrbitInput::post('in_store_localization');
            $post_sales_url = OrbitInput::post('post_sales_url');
            $price = OrbitInput::post('price');
            $merchant_tax_id1 = OrbitInput::post('merchant_tax_id1');
            $merchant_tax_id2 = OrbitInput::post('merchant_tax_id2');
            $status = OrbitInput::post('status');
            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;
            $category_id1 = OrbitInput::post('category_id1');
            $category_id2 = OrbitInput::post('category_id2');
            $category_id3 = OrbitInput::post('category_id3');
            $category_id4 = OrbitInput::post('category_id4');
            $category_id5 = OrbitInput::post('category_id5');

            $validator = Validator::make(
                array(
                    'merchant_id'       => $merchant_id,
                    'product_name'      => $product_name,
                    'upc_code'          => $upc_code,
                    'product_code'      => $product_code,
                    'status'            => $status,
                    'category_id1'      => $category_id1,
                    'category_id2'      => $category_id2,
                    'category_id3'      => $category_id3,
                    'category_id4'      => $category_id4,
                    'category_id5'      => $category_id5,
                ),
                array(
                    'merchant_id'           => 'required|numeric|orbit.empty.merchant',
                    'product_name'          => 'required',
                    'status'                => 'required|orbit.empty.product_status',
                    'upc_code'              => 'orbit.exists.product.upc_code',
                    'product_code'          => 'orbit.exists.product.sku_code',
                    'category_id1'          => 'numeric|orbit.empty.category_id1',
                    'category_id2'          => 'numeric|orbit.empty.category_id2',
                    'category_id3'          => 'numeric|orbit.empty.category_id3',
                    'category_id4'          => 'numeric|orbit.empty.category_id4',
                    'category_id5'          => 'numeric|orbit.empty.category_id5',
                ),
                array(
                    // Duplicate UPC error message
                    'orbit.exists.product.upc_code' => Lang::get('validation.orbit.exists.product.upc_code', [
                        'upc' => $upc_code
                    ]),

                    // Duplicate SKU error message
                    'orbit.exists.product.sku_code' => Lang::get('validation.orbit.exists.product.sku_code', [
                        'sku' => $product_code
                    ])
                )
            );

            Event::fire('orbit.product.postnewproduct.before.validation', array($this, $validator));

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
                        'retailer_id'   => 'numeric|orbit.empty.retailer',
                    )
                );

                Event::fire('orbit.product.postnewproduct.before.retailervalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.product.postnewproduct.after.retailervalidation', array($this, $validator));
            }

            Event::fire('orbit.product.postnewproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $newproduct = new Product();
            $newproduct->merchant_id = $merchant_id;
            $newproduct->product_code = $product_code;
            $newproduct->upc_code = $upc_code;
            $newproduct->product_name = $product_name;
            $newproduct->image = $image;
            $newproduct->short_description = $short_description;
            $newproduct->long_description = $long_description;
            $newproduct->is_featured = $is_featured;
            $newproduct->new_from = $new_from;
            $newproduct->new_until = $new_until;
            $newproduct->in_store_localization = $in_store_localization;
            $newproduct->post_sales_url = $post_sales_url;
            $newproduct->price = $price;
            $newproduct->merchant_tax_id1 = $merchant_tax_id1;
            $newproduct->merchant_tax_id2 = $merchant_tax_id2;
            $newproduct->status = $status;
            $newproduct->created_by = $this->api->user->user_id;
            $newproduct->modified_by = $this->api->user->user_id;
            $newproduct->category_id1 = $category_id1;
            $newproduct->category_id2 = $category_id2;
            $newproduct->category_id3 = $category_id3;
            $newproduct->category_id4 = $category_id4;
            $newproduct->category_id5 = $category_id5;

            Event::fire('orbit.product.postnewproduct.before.save', array($this, $newproduct));

            $newproduct->save();

            // Register the saved product to the IoC named 'orbit.empty.product'
            // which used on checkVariants() and various places
            App::instance('orbit.empty.product', $newproduct);

            $productretailers = array();

            foreach ($retailer_ids as $retailer_id) {
                $productretailer = new ProductRetailer();
                $productretailer->retailer_id = $retailer_id;
                $productretailer->product_id = $newproduct->product_id;
                $productretailer->save();
                $productretailers[] = $productretailer;
            }

            // Save product variants (combination)
            $variants = array();
            OrbitInput::post('product_variants', function($product_combinations)
            use ($price, $upc_code, $merchant_id, $user, $newproduct, $product_code, &$variants)
            {
                $variant_decode = $this->JSONValidate($product_combinations);
                $index = 1;
                $attribute_values = $this->checkVariant($variant_decode);

                foreach ($variant_decode as $variant_index=>$variant) {
                    // Return the default price if the variant price is empty
                    $vprice = function() use ($variant, $price) {
                        if (empty($variant->price)) {
                            return $price;
                        }

                        return $variant->price;
                    };

                    // Return the default sku if the variant sku is empty
                    $vsku = function() use ($variant, $product_code) {
                        if (empty($variant->sku)) {
                            return $product_code;
                        }

                        return $variant->sku;
                    };

                    // Return the default upc if the variant upc is empty
                    $vupc = function() use ($variant, $upc_code) {
                        if (empty($variant->upc)) {
                            return $upc_code;
                        }

                        return $variant->upc;
                    };

                    $product_variant = new ProductVariant();
                    $product_variant->product_id = $newproduct->product_id;
                    $product_variant->price = $vprice();
                    $product_variant->sku = $vsku();
                    $product_variant->upc = $vupc();
                    $product_variant->merchant_id = $merchant_id;
                    $product_variant->created_by = $user->user_id;
                    $product_variant->status = 'active';

                    // Save the 5 attributes value id
                    foreach ($variant->attribute_values as $i=>$value_id) {
                        $field_value_id = 'product_attribute_value_id' . ($i + 1);
                        $product_variant->{$field_value_id} = $value_id;
                    }
                    $product_variant->save();

                    $variants[] = $product_variant;
                }

                // Save the product attribute id to the product table
                foreach ($attribute_values as $index_attr=>$value) {
                    $field_attribute_id = 'attribute_id' . ($index_attr + 1);
                    $newproduct->$field_attribute_id = $value->product_attribute_id;
                }
                $newproduct->save();

                $this->keepProductColumnUpToDate($newproduct);
            });

            $newproduct->setRelation('retailers', $productretailers);
            $newproduct->retailers = $productretailers;

            $newproduct->setRelation('variants', $variants);
            $newproduct->variants = $variants;

            // Create default variant for this product
            ProductVariant::createDefaultVariant($newproduct);

            $newproduct->load('category1');
            $newproduct->load('category2');
            $newproduct->load('category3');
            $newproduct->load('category4');
            $newproduct->load('category5');

            Event::fire('orbit.product.postnewproduct.after.save', array($this, $newproduct));
            $this->response->data = $newproduct;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityProductNotes = sprintf('Product Created: %s', $newproduct->product_name);
            $activityProduct->setUser($user)
                            ->setActivityName('create_product')
                            ->setActivityNameLong('Create Product OK')
                            ->setObject($newproduct)
                            ->setNotes($activityProductNotes)
                            ->responseOK();

            Event::fire('orbit.product.postnewproduct.after.commit', array($this, $newproduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postnewproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activityProduct->setUser($user)
                            ->setActivityName('create_product')
                            ->setActivityNameLong('Create Product Failed')
                            ->setNotes($e->getMessage())
                            ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postnewproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activityProduct->setUser($user)
                            ->setActivityName('create_product')
                            ->setActivityNameLong('Create Product Failed')
                            ->setNotes($e->getMessage())
                            ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postnewproduct.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activityProduct->setUser($user)
                            ->setActivityName('create_product')
                            ->setActivityNameLong('Create Product Failed')
                            ->setNotes($e->getMessage())
                            ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.product.postnewproduct.general.exception', array($this, $e));

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

            // Creation failed Activity log
            $activityProduct->setUser($user)
                            ->setActivityName('create_product')
                            ->setActivityNameLong('Create Product Failed')
                            ->setNotes($e->getMessage())
                            ->responseFailed();
        }

        // Save the activity
        $activityProduct->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Delete Product
     *
     * @author Kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `product_id`                  (required) - ID of the product
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteProduct()
    {
        $activityProduct = Activity::portal()
                                   ->setActivityType('delete');

        $user = NULL;
        $deleteproduct = NULL;

        try {
            $httpCode = 200;

            Event::fire('orbit.product.postdeleteproduct.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.product.postdeleteproduct.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.product.postdeleteproduct.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('delete_product')) {
                Event::fire('orbit.product.postdeleteproduct.authz.notallowed', array($this, $user));
                $deleteProductLang = Lang::get('validation.orbit.actionlist.delete_product');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteProductLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.product.postdeleteproduct.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $product_id = OrbitInput::post('product_id');

            $validator = Validator::make(
                array(
                    'product_id' => $product_id,
                ),
                array(
                    'product_id' => 'required|numeric|orbit.empty.product|orbit.exists.product_have_transaction',
                )
            );

            Event::fire('orbit.product.postdeleteproduct.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.product.postdeleteproduct.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $deleteproduct = Product::excludeDeleted()->allowedForUser($user)->where('product_id', $product_id)->first();
            $deleteproduct->status = 'deleted';
            $deleteproduct->modified_by = $this->api->user->user_id;

            Event::fire('orbit.product.postdeleteproduct.before.save', array($this, $deleteproduct));

            // get product-retailer for the product
            $deleteproductretailers = ProductRetailer::where('product_id', $deleteproduct->product_id)->get();

            foreach ($deleteproductretailers as $deleteproductretailer) {
                $deleteproductretailer->delete();
            }

            $deleteproduct->save();

            Event::fire('orbit.product.postdeleteproduct.after.save', array($this, $deleteproduct));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.product');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityProductNotes = sprintf('Product Deleted: %s', $deleteproduct->product_name);
            $activityProduct->setUser($user)
                            ->setActivityName('delete_product')
                            ->setActivityNameLong('Delete Product OK')
                            ->setObject($deleteproduct)
                            ->setNotes($activityProductNotes)
                            ->responseOK();

            Event::fire('orbit.product.postdeleteproduct.after.commit', array($this, $deleteproduct));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.product.postdeleteproduct.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activityProduct->setUser($user)
                            ->setActivityName('delete_product')
                            ->setActivityNameLong('Delete Product Failed')
                            ->setObject($deleteproduct)
                            ->setNotes($e->getMessage())
                            ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.product.postdeleteproduct.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activityProduct->setUser($user)
                            ->setActivityName('delete_product')
                            ->setActivityNameLong('Delete Product Failed')
                            ->setObject($deleteproduct)
                            ->setNotes($e->getMessage())
                            ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.product.postdeleteproduct.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activityProduct->setUser($user)
                            ->setActivityName('delete_product')
                            ->setActivityNameLong('Delete Product Failed')
                            ->setObject($deleteproduct)
                            ->setNotes($e->getMessage())
                            ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.product.postdeleteproduct.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activityProduct->setUser($user)
                            ->setActivityName('delete_product')
                            ->setActivityNameLong('Delete Product Failed')
                            ->setObject($deleteproduct)
                            ->setNotes($e->getMessage())
                            ->responseFailed();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.product.postdeleteproduct.before.render', array($this, $output));

        // Save the activity
        $activityProduct->save();

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of product id
        $user = $this->api->user;
        Validator::extend('orbit.empty.product', function ($attribute, $value, $parameters) use ($user) {
            $product = Product::with('retailers', 'category1', 'category2', 'category3', 'category4', 'category5', 'variants')
                                     ->excludeDeleted()
                                     ->allowedForUser($user)
                                     ->where('product_id', $value)
                                     ->first();

            if (empty($product)) {
                return FALSE;
            }

            App::instance('orbit.empty.product', $product);

            return TRUE;
        });

        // Check the existance of merchant id
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) {
            $merchant = Merchant::excludeDeleted()
                        ->allowedForUser($this->api->user)
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Check the existance of retailer id
        Validator::extend('orbit.empty.retailer', function ($attribute, $value, $parameters) {
            $merchant = App::make('orbit.empty.merchant');
            $retailer = Retailer::excludeDeleted()->allowedForUser($this->api->user)
                        ->where('merchant_id', $value)
                        ->where('parent_id', $merchant->merchant_id)
                        ->first();

            if (empty($retailer)) {
                return FALSE;
            }

            App::instance('orbit.empty.retailer', $retailer);

            return TRUE;
        });

        // Check upc_code, it should not exists
        Validator::extend('orbit.exists.product.upc_code', function ($attribute, $value, $parameters) {
            $merchant = App::make('orbit.empty.merchant');

            // Check also the UPC on product variant
            $productVariant = ProductVariant::with([])
                                            ->excludeDeleted()
                                            ->where('merchant_id', $merchant->merchant_id)
                                            ->where('upc', $value)
                                            ->first();

            if (! empty($productVariant)) {
                return FALSE;
            }

            $product = Product::excludeDeleted()
                        ->where('upc_code', $value)
                        ->where('merchant_id', $merchant->merchant_id)
                        ->first();

            if (! empty($product)) {
                return FALSE;
            }

            App::instance('orbit.exists.product.upc_code', $product);

            return TRUE;
        });

        // Check upc_code, it should not exists
        Validator::extend('orbit.exists.product.upc_code_but_me', function ($attribute, $value, $parameters) {
            $product = App::make('orbit.empty.product');

            // Check also the UPC on product variant
            $productVariant = ProductVariant::with([])
                                            ->excludeDeleted()
                                            ->where('product_id', '!=', $product->product_id)
                                            ->where('merchant_id', $product->merchant_id)
                                            ->where('upc', $value)
                                            ->first();

            if (! empty($productVariant)) {
                return FALSE;
            }

            $product = Product::excludeDeleted()
                        ->where('upc_code', $value)
                        ->where('merchant_id', $product->merchant_id)
                        ->where('product_id', '!=', $product->product_id)
                        ->first();

            if (! empty($product)) {
                return FALSE;
            }

            App::instance('orbit.exists.product.upc_code', $product);

            return TRUE;
        });

        // Check product_code (SKU), it should not exists
        Validator::extend('orbit.exists.product.sku_code', function ($attribute, $value, $parameters) {
            $merchant = App::make('orbit.empty.merchant');

            // Check also the UPC on product variant
            $productVariant = ProductVariant::with([])
                                            ->excludeDeleted()
                                            ->where('merchant_id', $merchant->merchant_id)
                                            ->where('sku', $value)
                                            ->first();

            if (! empty($productVariant)) {
                return FALSE;
            }

            $product = Product::excludeDeleted()
                        ->where('product_code', $value)
                        ->where('merchant_id', $merchant->merchant_id)
                        ->first();

            if (! empty($product)) {
                return FALSE;
            }

            App::instance('orbit.validation.product_code', $product);

            return TRUE;
        });

        // Check product_code (SKU) for update, it should not exists
        Validator::extend('orbit.exists.product.sku_code_but_me', function ($attribute, $value, $parameters) {
            $product = App::make('orbit.empty.product');

            // Check also the UPC on product variant
            $productVariant = ProductVariant::with([])
                                            ->excludeDeleted()
                                            ->where('product_id', '!=', $product->product_id)
                                            ->where('merchant_id', $product->merchant_id)
                                            ->where('sku', $value)
                                            ->first();

            if (! empty($productVariant)) {
                return FALSE;
            }

            $product = Product::excludeDeleted()
                        ->where('product_code', $value)
                        ->where('merchant_id', $product->merchant_id)
                        ->where('product_id', '!=', $product->product_id)
                        ->first();

            if (! empty($product)) {
                return FALSE;
            }

            App::instance('orbit.validation.product_code', $product);

            return TRUE;
        });

        // Check changed product_code
        Validator::extend('product_code_exists_but_me', function ($attribute, $value, $parameters) {
            $product_id = OrbitInput::post('product_id');
            $product = Product::excludeDeleted()
                        ->where('product_code', $value)
                        ->where('product_id', '!=', $product_id)
                        ->first();

            if (! empty($product)) {
                return FALSE;
            }

            App::instance('orbit.validation.product', $product);

            return TRUE;
        });

        // Check upc_code, it should not exists
        Validator::extend('orbit.exists.upc_code', function ($attribute, $value, $parameters) {
            $product = Product::excludeDeleted()
                        ->where('upc_code', $value)
                        ->first();

            if (! empty($product)) {
                return FALSE;
            }

            App::instance('orbit.validation.upc_code', $product);

            return TRUE;
        });

        // Check changed upc_code
        Validator::extend('upc_code_exists_but_me', function ($attribute, $value, $parameters) {
            $product_id = OrbitInput::post('product_id');
            $product = Product::excludeDeleted()
                        ->where('upc_code', $value)
                        ->where('product_id', '!=', $product_id)
                        ->first();

            if (! empty($product)) {
                return FALSE;
            }

            App::instance('orbit.validation.product', $product);

            return TRUE;
        });

        // Check the existance of category_id1
        Validator::extend('orbit.empty.category_id1', function ($attribute, $value, $parameters) {
            $category_id1 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($category_id1)) {
                return FALSE;
            }

            App::instance('orbit.empty.category_id1', $category_id1);

            return TRUE;
        });

        // Check the existance of category_id2
        Validator::extend('orbit.empty.category_id2', function ($attribute, $value, $parameters) {
            $category_id2 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($category_id2)) {
                return FALSE;
            }

            App::instance('orbit.empty.category_id2', $category_id2);

            return TRUE;
        });

        // Check the existance of category_id3
        Validator::extend('orbit.empty.category_id3', function ($attribute, $value, $parameters) {
            $category_id3 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($category_id3)) {
                return FALSE;
            }

            App::instance('orbit.empty.category_id3', $category_id3);

            return TRUE;
        });

        // Check the existance of category_id4
        Validator::extend('orbit.empty.category_id4', function ($attribute, $value, $parameters) {
            $category_id4 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($category_id4)) {
                return FALSE;
            }

            App::instance('orbit.empty.category_id4', $category_id4);

            return TRUE;
        });

        // Check the existance of category_id5
        Validator::extend('orbit.empty.category_id5', function ($attribute, $value, $parameters) {
            $category_id5 = Category::excludeDeleted()
                    ->where('category_id', $value)
                    ->first();

            if (empty($category_id5)) {
                return FALSE;
            }

            App::instance('orbit.empty.category_id5', $category_id5);

            return TRUE;
        });

        // Check the existence of the product status
        Validator::extend('orbit.empty.product_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of each variant ID
        Validator::extend('orbit.empty.product_variant_array', function ($attribute, $value, $parameters) {
            $variants = App::make('orbit.empty.product')->variants;

            $variant_ids = [];
            $variant_objects = [];
            foreach ($variants as $i=>$variant) {
                $variant_ids[$i] = $variant->product_variant_id;
            }

            $valid_deleted = [];

            foreach ($value as $variant_id) {
                if (! in_array($variant_id, $variant_ids)) {
                    return FALSE;
                }

                foreach ($variants as $variant) {
                    if ((string)$variant->product_variant_id === (string)$variant_id) {
                        $variant_objects[] = $variant;
                    }
                }
            }

            App::instance('memory:deleted.variants', $variant_objects);

            return TRUE;
        });

        // Check if product have transaction.
        Validator::extend('orbit.exists.product_have_transaction', function ($attribute, $value, $parameters) {

            // Check inside transaction details to see if this product has a transaction
            $transactionDetailProduct = TransactionDetail::transactionJoin()
                                                         ->where('product_id', $value)
                                                         ->excludeDeleted('transactions')
                                                         ->first();
            if (! empty($transactionDetailProduct)) {
                return FALSE;
            }

            App::instance('orbit.exists.product_have_transaction', $transactionDetailProduct);

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
        $errorMessage = Lang::get('validation.orbit.jsonerror.field.format');

        if (! is_string($string)) {
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        $result = @json_decode($string);
        if (json_last_error() !== JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        $errorMessage = Lang::get('validation.orbit.jsonerror.field.array');
        if (! is_array($result)) {
            OrbitShopAPI::throwInvalidArgument($errorMessage);
        }

        return $result;
    }

    /**
     * Check the validity of variant.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param object $variant
     * @return array - ProductAttributeValue
     */
    protected function checkVariant($variants, $mode='normal')
    {
        $values = array();

        // Flag to determine how many vlaue are filled for each variant submitted
        $lastNumber = -1;

        foreach ($variants as $i=>$variant) {
            $neededProperties = array('attribute_values', 'price', 'sku', 'upc');

            if ($mode === 'update') {
                // Check the existence of 'variant_id'
                $neededProperties[] = 'variant_id';
            }

            foreach ($neededProperties as $property) {
                // It should have property specified
                if (! property_exists($variant, $property)) {
                    $errorMessage = Lang::get('validation.orbit.empty.product_attr.attribute.json_property',
                        array('property' => $property)
                    );
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }

            if (! is_array($variant->attribute_values)) {
                $errorMessage = Lang::get('validation.orbit.jsonerror.field', array('field' => 'attribute_values'));
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (count($variant->attribute_values) !== 5) {
                $errorMessage = Lang::get('validation.orbit.formaterror.product_attr.attribute.value.count');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Check the price validity
            if (! empty($variant->price)) {
                if (! preg_match('/^[+-]?((\d+(\.\d*)?)|(\.\d+))$/', $variant->price)) {
                    $errorMessage = Lang::get('validation.orbit.formaterror.product_attr.attribute.value.price');
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }

            // Check the existence of the product variant id
            $product = App::make('orbit.empty.product');

            $empty = 0;
            foreach ($variant->attribute_values as $value_id) {
                if (empty($value_id)) {
                    $empty++;
                }
            }
            if ($empty >= 5) {
                $errorMessage = Lang::get('validation.orbit.formaterror.product_attr.attribute.value.allnull');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if ($lastNumber !== -1) {
                // This is the first time no need to check
                if ($lastNumber !== (5 - $empty)) {
                    // The last number should be 5 - $empty, so if it does not the same
                    // the number of values should not be the same also
                    $errorMessage = Lang::get('validation.orbit.formaterror.product_attr.attribute.value.notsame');
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }
            $lastNumber = 5 - $empty;

            // Make sure there is no duplicate value, but make sure we remove the empty value first
            if (ArrayChecker::create(array_filter($variant->attribute_values))->hasDuplicate()) {
                $errorMessage = Lang::get('validation.orbit.formaterror.product_attr.attribute.value.duplicate');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Check for null in front of the value
            if ($this->isPrependedByNull($variant->attribute_values)) {
                $errorMessage = Lang::get('validation.orbit.formaterror.product_attr.attribute.value.nullprepend');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Make sure the combinations does not exist yet
            $productVariantCombination = ProductVariant::with(array())
                                                       ->excludeDeleted()
                                                       ->excludeDefault()
                                                       ->where('product_id', $product->product_id);

            // If this one are update process then make sure not select our current one
            if ($mode === 'update') {
                // Check the existence of productVariant
                $productVariantExists = ProductVariant::where('product_variant_id', $variant->variant_id)
                                                      ->where('product_id', $product->product_id)
                                                      ->first();

                if (empty($productVariantExists)) {
                    $errorMessage = Lang::get('validation.orbit.empty.product_attr.attribute.variant');
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $productVariantCombination->where('product_variant_id', '!=', $variant->variant_id);
            }

            // Check each of these product attribute value existence
            $merchantId = $this->getMerchantId();
            foreach ($variant->attribute_values as $index=>$value_id) {
                if (empty($value_id)) {
                    continue;
                }

                $variantAttributeValueId = 'product_attribute_value_id' . ($index + 1);
                $productVariantCombination->where($variantAttributeValueId, $value_id);

                $productAttributeValue = ProductAttributeValue::excludeDeleted('product_attribute_values')
                                                              ->merchantIds(array($merchantId))
                                                              ->where('product_attribute_value_id', $value_id)
                                                              ->first();

                if (empty($productAttributeValue)) {
                    $errorMessage = Lang::get('validation.orbit.empty.product_attr.attribute.value', array('id' => $value_id));
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                // This code are executed inside a loop so, we only need the first
                // index to get the object of productAttributeValue to determine
                // which product_attribute_id this group belonging to
                if ($i === 0) {
                    $values[] = $productAttributeValue;
                }
            }

            // Try to get the records of the ProductVariant, if it exists then
            // reject the request
            $variantProduct = $productVariantCombination;
            if (! empty($variantProduct->first())) {
                $errorMessage = Lang::get('validation.orbit.formaterror.product_attr.attribute.value.exists');
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            if (! empty($variant->upc)) {
                // Make sure there is no duplicate on UPC code:
                // 1) Search product which has the same UPC but exclude the parent
                $productUPC = Product::with([])
                                     ->excludeDeleted()
                                     ->where('product_id', '!=', $product->product_id)
                                     ->where('upc_code', $variant->upc)
                                     ->where('merchant_id', $product->merchant_id)
                                     ->first();
                if (! empty($productUPC)) {
                    $errorMessage = Lang::get('validation.orbit.exists.product.upc_code', [
                        'upc' => $productUPC->upc_code
                    ]);
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                // 2) Search product variant which has the same upc but exclude one
                //    which has the same parent
                $productVariantUPC = ProductVariant::with([])
                                                   ->excludeDeleted()
                                                   ->where('product_id', '!=', $product->product_id)
                                                   ->where('upc', $variant->upc)
                                                   ->where('merchant_id', $product->merchant_id);

                if ($mode === 'update') {
                    $productVariantUPC->where('product_variant_id', '!=', $productVariantExists->product_variant_id);
                }

                // If this is not empty, then there are other variant on different
                // product which has the same UPC
                $productVariantUPC = $productVariantUPC->first();
                if (! empty($productVariantUPC)) {
                    $errorMessage = Lang::get('validation.orbit.exists.product.upc_code', [
                        'upc' => $productVariantUPC->upc
                    ]);
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }

            if (! empty($variant->sku)) {
                // Make sure there is no duplicate on SKU code:
                // 1) Search product which has the same SKU but exclude the parent
                $productSKU = Product::with([])
                                     ->excludeDeleted()
                                     ->where('product_id', '!=', $product->product_id)
                                     ->where('product_code', $variant->sku)
                                     ->where('merchant_id', $product->merchant_id)
                                     ->first();
                if (! empty($productSKU)) {
                    $errorMessage = Lang::get('validation.orbit.exists.product.sku_code', [
                        'sku' => $productSKU->product_code
                    ]);
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                // 2) Search product variant which has the same sku but exclude one
                //    which has the same parent
                $productVariantSKU = ProductVariant::with([])
                                                   ->excludeDeleted()
                                                   ->where('product_id', '!=', $product->product_id)
                                                   ->where('sku', $variant->sku)
                                                   ->where('merchant_id', $product->merchant_id);

                if ($mode === 'update') {
                    $productVariantSKU->where('product_variant_id', '!=', $productVariantExists->product_variant_id);
                }

                // If this is not empty, then there are other variant on different
                // product which has the same UPC
                $productVariantSKU = $productVariantSKU->first();
                if (! empty($productVariantSKU)) {
                    $errorMessage = Lang::get('validation.orbit.exists.product.sku_code', [
                        'sku' => $productVariantSKU->sku
                    ]);
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }
            }
        }

        return $values;
    }

    /**
     * Helper for checking current merchant id.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return int
     */
    protected function getMerchantId()
    {
        $currentProduct = NULL;

        $merchantId = OrbitInput::post('merchant_id', function($_merchantId) {
            return $_merchantId;
        });

        if (! $merchantId) {
            if (App::bound('memory:current.updated.product')) {
                $currentProduct = App::make('memory:current.updated.product');
            }

            if (is_object($currentProduct)) {
                $merchantId = $currentProduct->merchant_id;
            }
        }

        return $merchantId;
    }

    /**
     * Update product column attribute_id{1-5} to reflect the most up to date
     * changes.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Product $updatedproduct
     * @return void
     */
    protected function keepProductColumnUpToDate(&$updatedproduct)
    {
        // Get the most complete variant with all the product attribute
        // values which has been set up

        // @Todo
        // This is slow, it should be rewritten
        $with = array(
            'attributeValue1',
            'attributeValue2',
            'attributeValue3',
            'attributeValue4',
            'attributeValue5',
        );
        $complete_variant = ProductVariant::excludeDeleted()
                                          ->excludeDefault()
                                          ->mostCompleteValue()
                                          ->where('product_id', $updatedproduct->product_id)
                                          ->with($with)
                                          ->first();

        // Flag to determine if the updated product has been changes
        $updated_product_changes = FALSE;

        if (empty($complete_variant)) {
            // This product does not have any variant anymore
            for ($i=5; $i>=1; $i--) {
                $updatedproduct->{'attribute_id' . $i} = NULL;
            }

            $updated_product_changes = TRUE;
        } else {
            // Update the product attribute id{1-5}
            for ($i=5; $i>=1; $i--) {
                if (is_null($complete_variant->{'attributeValue' . $i})) {
                    continue;
                }

                // If we goes here then particular attribute value is not empty
                // and also has attributeValue object
                $updatedproduct->{'attribute_id' . $i} = $complete_variant->{'attributeValue' . $i}->product_attribute_id;

                // Update the flag
                $updated_product_changes = TRUE;
            }
        }

        // Save the updated product
        if ($updated_product_changes) {
            $updatedproduct->save();
        }
    }

    /**
     * Method to check whether a NULL value prepends the attribute value. I.e:
     *
     * [NULL, 1, 2, 3, 4] OR
     * [1, 2, NULL, 4, 5] OR
     * [NULL, NULL, NULL, NULL, 5]
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $values - Array of values
     * @return boolean
     */
    protected function isPrependedByNull($values)
    {
        $nullNumber = -1;
        $valueNumber = -1;
        foreach ($values as $i=>$value) {
            if (is_null($value)) {
                if ($nullNumber === -1) {
                    // First time, only record the very first NULL
                    $nullNumber = $i;
                }
            } else {
                $valueNumber = $i;
            }
        }

        if ($valueNumber === -1) {
            // There is no real value supplied
            return TRUE;
        }

        if ($nullNumber < $valueNumber && $nullNumber !== -1) {
            return TRUE;
        }

        return FALSE;
    }
}
