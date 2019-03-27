<?php
/**
 * An API controller for managing file uploads.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitUploader\UploaderConfig;
use DominoPOS\OrbitUploader\UploaderMessage;
use DominoPOS\OrbitUploader\Uploader;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Orbit\Helper\MongoDB\Client as MongoClient;

class UploadAPIController extends ControllerAPI
{
    /**
     * From what part of the code this API are called from.
     *
     * @var string
     */
    protected $calledFrom = 'default';

    /**
     * param: type: string
     */
    public function getMaximumFileSize()
    {
        $httpCode = 200;
        try {
            $type = OrbitInput::get('type', null);
            if ($type === null) {
                OrbitShopAPI::throwInvalidArgument('Type required');
            }

            $keys = (array)$type;
            $data = [];

            foreach($keys as $key) {
                $type = (string)$key;
                if (!preg_match('/^[a-z._]+$/', $type)) {
                    OrbitShopAPI::throwInvalidArgument('Type must be alphabetic separated by dots');
                }
                $config = Config::get('orbit.upload.' . $type, null);
                if (!is_array($config)) {
                    OrbitShopAPI::throwInvalidArgument('Type unknown');
                }

                if (!isset($config['file_size'])) {
                    OrbitShopAPI::throwInvalidArgument('Type does not set file size');
                }

                $data['file_size'][$key] = $config['file_size'];
            }
            $this->response->data = $data;
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (Exception $e) {
            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;
        }
        return $this->render($httpCode);
    }

    /**
     * Generic method for saving the uploaded metadata to the Media table on
     * the database.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     */
    public function saveMetadata($object, $metadata)
    {
        $result = array();

        foreach ($metadata as $i=>$file) {
            // Save original file meta data into Media table
            $media = new Media();
            $media->object_id = $object['id'];
            $media->object_name = $object['name'];
            $media->media_name_id = $object['media_name_id'];
            $media->media_name_long = sprintf('%s_orig', $object['media_name_id']);
            $media->file_name = $file['file_name'];
            $media->file_extension = $file['file_ext'];
            $media->file_size = $file['file_size'];
            $media->mime_type = $file['mime_type'];
            $media->path = $file['path'];
            $media->realpath = $file['realpath'];
            $media->metadata = 'order-' . $i;
            $media->modified_by = $object['modified_by'];
            $media->save();
            $result[] = $media;

            // Save the cropped, resized and scaled if any
            foreach (array('resized', 'cropped', 'scaled') as $variant) {
                // Save each profile
                foreach ($file[$variant] as $profile=>$finfo) {
                    $media = new Media();
                    $media->object_id = $object['id'];
                    $media->object_name = $object['name'];
                    $media->media_name_id = $object['media_name_id'];
                    $media->media_name_long = sprintf('%s_%s_%s', $object['media_name_id'], $variant, $profile);
                    $media->file_name = $finfo['file_name'];
                    $media->file_extension = $file['file_ext'];
                    $media->file_size = $finfo['file_size'];
                    $media->mime_type = $file['mime_type'];
                    $media->path = $finfo['path'];
                    $media->realpath = $finfo['realpath'];
                    $media->metadata = 'order-' . $i;
                    $media->modified_by = $object['modified_by'];
                    $media->save();
                    $result[] = $media;
                }
            }
        }

        return $result;
    }

    public function saveMetaDataCreditCard($object, $metadata)
    {
        $result = array();
        foreach ($metadata as $i=>$file) {
            // Save original file meta data into Media table
            if (isset($object['id'][$i]))
            {
                $media = new Media();
                $media->object_id = $object['id'][$i];
                $media->object_name = $object['name'];
                $media->media_name_id = $object['media_name_id'];
                $media->media_name_long = sprintf('%s_orig', $object['media_name_id']);
                $media->file_name = $file['file_name'];
                $media->file_extension = $file['file_ext'];
                $media->file_size = $file['file_size'];
                $media->mime_type = $file['mime_type'];
                $media->path = $file['path'];
                $media->realpath = $file['realpath'];
                $media->metadata = 'order-' . $i;
                $media->modified_by = $object['modified_by'];
                $media->save();
                $result[] = $media;

                // Save the cropped, resized and scaled if any
                foreach (array('resized', 'cropped', 'scaled') as $variant) {
                    // Save each profile
                    foreach ($file[$variant] as $profile=>$finfo) {
                        $media = new Media();
                        $media->object_id = $object['id'][$i];
                        $media->object_name = $object['name'];
                        $media->media_name_id = $object['media_name_id'];
                        $media->media_name_long = sprintf('%s_%s_%s', $object['media_name_id'], $variant, $profile);
                        $media->file_name = $finfo['file_name'];
                        $media->file_extension = $file['file_ext'];
                        $media->file_size = $finfo['file_size'];
                        $media->mime_type = $file['mime_type'];
                        $media->path = $finfo['path'];
                        $media->realpath = $finfo['realpath'];
                        $media->metadata = 'order-' . $i;
                        $media->modified_by = $object['modified_by'];
                        $media->save();
                        $result[] = $media;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Upload logo for Merchant.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Firmansyah <firmansyah@myorbit.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - ID of the merchant
     * @param file|array `images`                      (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadMerchantLogo()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadmerchantlogo.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadmerchantlogo.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadmerchantlogo.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('edit_merchant')) {
                    Event::fire('orbit.upload.postuploadmerchantlogo.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_merchant');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadmerchantlogo.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');
            $images = OrbitInput::files('images');
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    'images'      => $images,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.merchant',
                    'images'        => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadmerchantlogo.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadmerchantlogo.after.validation', array($this, $validator));

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.merchant');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($merchant)
            {
                $merchant_id = $merchant->merchant_id;
                $slug = Str::slug($merchant->name);
                $file['new']->name = sprintf('%s-%s-%s', $merchant_id, $slug, time());
            };

            // Load the orbit configuration for merchant upload logo
            $uploadLogoConfig = Config::get('orbit.upload.merchant.logo');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadLogoConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadmerchantlogo.before.save', array($this, $merchant, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'merchant')
                              ->where('media_name_id', 'merchant_logo');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $merchant->merchant_id,
                'name'          => 'merchant',
                'media_name_id' => 'merchant_logo',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            if (isset($uploaded[0])) {
                $merchant->logo = $uploaded[0]['path'];
                $merchant->save();
            }

            Event::fire('orbit.upload.postuploadmerchantlogo.after.save', array($this, $merchant, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.merchant.logo');

            // Commit the changes
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadmerchantlogo.after.commit', array($this, $merchant, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadmerchantlogo.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadmerchantlogo.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadmerchantlogo.query.error', array($this, $e));

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
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadmerchantlogo.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadmerchantlogo.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete logo for a merchant.
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                  (required) - ID of the merchant
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteMerchantLogo()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletemerchantlogo.before.auth', array($this));

            if (! $this->calledFrom('merchant.new, merchant.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletemerchantlogo.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletemerchantlogo.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_mall')) {
                    Event::fire('orbit.upload.postdeletemerchantlogo.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_merchant');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postdeletemerchantlogo.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');

            $validator = Validator::make(
                array(
                    'merchant_id'   => $merchant_id,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.merchant',
                )
            );

            Event::fire('orbit.upload.postdeletemerchantlogo.before.validation', array($this, $validator));

            if (! $this->calledFrom('merchant.new,merchant.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletemerchantlogo.after.validation', array($this, $validator));

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.merchant');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'merchant')
                              ->where('media_name_id', 'merchant_logo');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletemerchantlogo.before.save', array($this, $merchant));

            // Update the `logo` field which store the original path of the logo
            // This is temporary since right now the business rules actually
            // only allows one logo per merchant
            $merchant->logo = NULL;
            $merchant->save();

            Event::fire('orbit.upload.postdeletemerchantlogo.after.save', array($this, $merchant));

            $this->response->data = $merchant;
            $this->response->message = Lang::get('statuses.orbit.uploaded.merchant.delete_logo');

            if (! $this->calledFrom('merchant.new,merchant.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletemerchantlogo.after.commit', array($this, $merchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletemerchantlogo.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('merchant.new,merchant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletemerchantlogo.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('merchant.new,merchant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletemerchantlogo.query.error', array($this, $e));

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

            if (! $this->calledFrom('merchant.new,merchant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletemerchantlogo.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('merchant.new, merchant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletemerchantlogo.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload photo for a product.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `product_id`                  (required) - ID of the product
     * @param file|array `images`                      (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadProductImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadproductimage.before.auth', array($this));

            if (! $this->calledFrom('product.new, product.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadproductimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadproductimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('edit_product')) {
                    Event::fire('orbit.upload.postuploadproductimage.authz.notallowed', array($this, $user));
                    $editProductLang = Lang::get('validation.orbit.actionlist.update_product');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editProductLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadproductimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $product_id = OrbitInput::post('product_id');
            $images = OrbitInput::files('images');
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'product_id'    => $product_id,
                    'images'        => $images,
                ),
                array(
                    'product_id'   => 'required|orbit.empty.product',
                    'images'       => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadproductimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('product.new,product.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadproductimage.after.validation', array($this, $validator));

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $product = App::make('orbit.empty.product');

            // Callback to rename the file, we will format it as follow
            // [PRODUCT_ID]-[PRODUCT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($product)
            {
                $product_id = $product->product_id;
                $slug = Str::slug($product->product_name);
                $file['new']->name = sprintf('%s-%s-%s', $product_id, $slug, time());
            };

            // Load the orbit configuration for product upload
            $uploadProductConfig = Config::get('orbit.upload.product.main');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadProductConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadproductimage.before.save', array($this, $product, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $product->product_id)
                              ->where('object_name', 'product')
                              ->where('media_name_id', 'product_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $product->product_id,
                'name'          => 'product',
                'media_name_id' => 'product_image',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            if (isset($uploaded[0])) {
                $product->image = $uploaded[0]['path'];
                $product->save();
            }

            Event::fire('orbit.upload.postuploadproductimage.after.save', array($this, $product, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.product.main');

            if (! $this->calledFrom('product.new,product.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadproductimage.after.commit', array($this, $product, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadproductimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('product.new,product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadproductimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('product.new,product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadproductimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('product.new,product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadproductimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('product.new, product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadproductimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete photo for a product.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `product_id`                  (required) - ID of the product
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteProductImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeleteproductimage.before.auth', array($this));

            if (! $this->calledFrom('product.new, product.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeleteproductimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeleteproductimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_product')) {
                    Event::fire('orbit.upload.postdeleteproductimage.authz.notallowed', array($this, $user));
                    $editProductLang = Lang::get('validation.orbit.actionlist.update_product');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editProductLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postdeleteproductimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $product_id = OrbitInput::post('product_id');

            $validator = Validator::make(
                array(
                    'product_id'    => $product_id,
                ),
                array(
                    'product_id'   => 'required|orbit.empty.product',
                )
            );

            Event::fire('orbit.upload.postdeleteproductimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('product.new,product.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeleteproductimage.after.validation', array($this, $validator));

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $product = App::make('orbit.empty.product');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $product->product_id)
                              ->where('object_name', 'product')
                              ->where('media_name_id', 'product_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeleteproductimage.before.save', array($this, $product));

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            $product->image = NULL;
            $product->save();

            Event::fire('orbit.upload.postdeleteproductimage.after.save', array($this, $product));

            $this->response->data = $product;
            $this->response->message = Lang::get('statuses.orbit.uploaded.product.delete_image');

            if (! $this->calledFrom('product.new,product.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeleteproductimage.after.commit', array($this, $product));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeleteproductimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('product.new,product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeleteproductimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('product.new,product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeleteproductimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('product.new,product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeleteproductimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('product.new, product.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeleteproductimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload photo for a promotion.
     *
     * @author Tian <tian@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                (required) - ID of the promotion
     * @param file|array `images`                      (required) - Promotion images
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadPromotionImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadpromotionimage.before.auth', array($this));

            if (! $this->calledFrom('promotion.new, promotion.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadpromotionimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadpromotionimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_promotion')) {
                    Event::fire('orbit.upload.postuploadpromotionimage.authz.notallowed', array($this, $user));
                    $editPromotionLang = Lang::get('validation.orbit.actionlist.update_promotion');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editPromotionLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadpromotionimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $promotion_id = OrbitInput::post('promotion_id');
            $images = OrbitInput::files('images');
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'promotion_id'  => $promotion_id,
                    'images'        => $images,
                ),
                array(
                    'promotion_id'  => 'required|orbit.empty.promotion',
                    'images'        => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadpromotionimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadpromotionimage.after.validation', array($this, $validator));

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $promotion = App::make('orbit.empty.promotion');

            // Callback to rename the file, we will format it as follow
            // [PRODUCT_ID]-[PRODUCT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($promotion)
            {
                $promotion_id = $promotion->promotion_id;
                $slug = Str::slug($promotion->promotion_name);
                $file['new']->name = sprintf('%s-%s-%s', $promotion_id, $slug, time());
            };

            // Load the orbit configuration for promotion upload
            $uploadPromotionConfig = Config::get('orbit.upload.promotion.main');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadPromotionConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadpromotionimage.before.save', array($this, $promotion, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $promotion->promotion_id)
                              ->where('object_name', 'promotion')
                              ->where('media_name_id', 'promotion_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $promotion->promotion_id,
                'name'          => 'promotion',
                'media_name_id' => 'promotion_image',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per promotion
            if (isset($uploaded[0])) {
                $promotion->image = $uploaded[0]['path'];
                $promotion->save();
            }

            Event::fire('orbit.upload.postuploadpromotionimage.after.save', array($this, $promotion, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.promotion.main');

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadpromotionimage.after.commit', array($this, $promotion, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadpromotionimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadpromotionimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadpromotionimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadpromotionimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('promotion.new, promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadpromotionimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete photo for a promotion.
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                  (required) - ID of the promotion
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeletePromotionImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletepromotionimage.before.auth', array($this));

            if (! $this->calledFrom('promotion.new, promotion.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletepromotionimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletepromotionimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_promotion')) {
                    Event::fire('orbit.upload.postdeletepromotionimage.authz.notallowed', array($this, $user));
                    $editPromotionLang = Lang::get('validation.orbit.actionlist.update_promotion');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editPromotionLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postdeletepromotionimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $promotion_id = OrbitInput::post('promotion_id');

            $validator = Validator::make(
                array(
                    'promotion_id'    => $promotion_id,
                ),
                array(
                    'promotion_id'   => 'required|orbit.empty.promotion',
                )
            );

            Event::fire('orbit.upload.postdeletepromotionimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletepromotionimage.after.validation', array($this, $validator));

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $promotion = App::make('orbit.empty.promotion');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $promotion->promotion_id)
                              ->where('object_name', 'promotion')
                              ->where('media_name_id', 'promotion_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletepromotionimage.before.save', array($this, $promotion));

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per promotion
            $promotion->image = NULL;
            $promotion->save();

            Event::fire('orbit.upload.postdeletepromotionimage.after.save', array($this, $promotion));

            $this->response->data = $promotion;
            $this->response->message = Lang::get('statuses.orbit.uploaded.promotion.delete_image');

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletepromotionimage.after.commit', array($this, $promotion));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletepromotionimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletepromotionimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletepromotionimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletepromotionimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('promotion.new, promotion.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletepromotionimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload image for a promotion tranlation (selected language).
     *
     * @author Irianto Pratama <irianto@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                                         (required) - ID of the promotion
     * @param integer    `promotion_translation_id`                             (required) - ID of the promotion tranlation
     * @param integer    `merchant_language_id`                                 (required) - ID of the merchan language
     * @param file|array `image_translation_<merchant_language_id>`             (required) - Event translation images
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadPromotionTranslationImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadpromotiontranslationimage.before.auth', array($this));

            if (! $this->calledFrom('promotion.translations'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadpromotiontranslationimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadpromotiontranslationimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_promotion')) {
                    Event::fire('orbit.upload.postuploadpromotiontranslationimage.authz.notallowed', array($this, $user));
                    $editPromotionLang = Lang::get('validation.orbit.actionlist.update_promotion');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editPromotionLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadpromotiontranslationimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $promotion_translation_id = OrbitInput::post('promotion_translation_id');
            $promotion_id = OrbitInput::post('promotion_id');
            $merchant_language_id = OrbitInput::post('merchant_language_id');
            $image_translation = OrbitInput::files('image_translation_' . $merchant_language_id);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'promotion_translation_id'      => $promotion_translation_id,
                    'promotion_id'                  => $promotion_id,
                    'merchant_language_id'          => $merchant_language_id,
                    'image_translation'             => $image_translation,
                ),
                array(
                    'promotion_translation_id'      => 'required|orbit.empty.promotion_translation',
                    'promotion_id'                  => 'required|orbit.empty.promotion',
                    'merchant_language_id'          => 'required|orbit.empty.merchant_language',
                    'image_translation'             => 'required|nomore.than.one',
                ),
                $messages
            );
            Event::fire('orbit.upload.postuploadpromotiontranslationimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('promotion.translations')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadpromotiontranslationimage.after.validation', array($this, $validator));

            // We already had Promotion Translation instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $promotion_translations = App::make('orbit.empty.promotion_translation');

            // Callback to rename the file, we will format it as follow
            // [PROMOTION_ID]-[PROMOTION_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($promotion_translations)
            {
                $promotion_translation_id = $promotion_translations->promotion_translation_id;
                $slug = Str::slug($promotion_translations->promotion_name);
                $file['new']->name = sprintf('%s-%s-%s', $promotion_translation_id, $slug, time());
            };

            // Load the orbit configuration for promotion upload
            $uploadPromotionConfig = Config::get('orbit.upload.promotion.translation');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadPromotionConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadpromotiontranslationimage.before.save', array($this, $promotion_translations, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($image_translation);

            // Delete old promotion translation image
            $pastMedia = Media::where('object_id', $promotion_translations->promotion_translation_id)
                              ->where('object_name', 'promotion_translation')
                              ->where('media_name_id', 'promotion_translation_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $promotion_translations->promotion_translation_id,
                'name'          => 'promotion_translation',
                'media_name_id' => 'promotion_translation_image',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image_translation` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per promotion
            if (isset($uploaded[0])) {
                $promotion_translations->save();
            }

            Event::fire('orbit.upload.postuploadpromotiontranslationimage.after.save', array($this, $promotion_translations, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.promotion_translation.main');

            if (! $this->calledFrom('promotion.translations')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadpromotiontranslationimage.after.commit', array($this, $promotion_translations, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadpromotiontranslationimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('promotion.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadpromotiontranslationimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('promotion.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadpromotiontranslationimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('promotion.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadpromotiontranslationimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('promotion.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadpromotiontranslationimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload profile picure (avatar) for User.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `user_id`                      (required) - ID of the merchant
     * @param file|array `images`                       (required) - Images of the user photo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadUserImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploaduserimage.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('user.new, user.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploaduserimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                $user_id = OrbitInput::post('user_id');
                Event::fire('orbit.upload.postuploaduserimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_user')) {
                    // Skip ACL check the the user itself
                    if ((string)$user->user_id !== $user_id) {
                        Event::fire('orbit.upload.postuploaduserimage.authz.notallowed', array($this, $user));

                        $editUserLang = Lang::get('validation.orbit.actionlist.update_user');
                        $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editUserLang));
                        ACL::throwAccessForbidden($message);
                    }
                }
                Event::fire('orbit.upload.postuploaduserimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $user_id = OrbitInput::post('user_id');
            $images = OrbitInput::files('images');
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'user_id'   => $user_id,
                    'images'    => $images,
                ),
                array(
                    'user_id'   => 'required|orbit.empty.user',
                    'images'    => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploaduserimage.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('user.new, user.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploaduserimage.after.validation', array($this, $validator));

            // We already had User instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $user = App::make('orbit.empty.user');

            // Callback to rename the file, we will format it as follow
            // [USER_ID]-[USER_EMAIL]
            $renameFile = function($uploader, &$file, $dir) use ($user)
            {
                $user_id = $user->user_id;
                $slug = str_replace('@', '_at_', $user->user_email);
                $slug = Str::slug($slug);
                $file['new']->name = sprintf('%s-%s-%s', $user_id, $slug, time());
            };

            // Load the orbit configuration for user profile picture
            $uploadImageConfig = Config::get('orbit.upload.user.profile_picture');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadImageConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploaduserimage.before.save', array($this, $user, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Delete old user picture
            $pastMedia = Media::where('object_id', $user->user_id)
                              ->where('object_name', 'user')
                              ->where('media_name_id', 'user_profile_picture');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $user->user_id,
                'name'          => 'user',
                'media_name_id' => 'user_profile_picture',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per user
            if (isset($uploaded[0])) {
                $user->userdetail->photo = $uploaded[0]['path'];
                $user->userdetail->save();
            }

            Event::fire('orbit.upload.postuploaduserimage.after.save', array($this, $user, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'user_profile_picture';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.user.profile_picture');

            // Commit the changes
            if (! $this->calledFrom('user.new, user.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploaduserimage.after.commit', array($this, $user, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploaduserimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('user.new, user.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploaduserimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('user.new, user.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploaduserimage.query.error', array($this, $e));

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
            if (! $this->calledFrom('user.new, user.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploaduserimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('user.new, user.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploaduserimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete photo for a user.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `user_id`                  (required) - ID of the user
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteUserImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeleteuserimage.before.auth', array($this));

            if (! $this->calledFrom('user.new, user.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeleteuserimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                $user_id = OrbitInput::post('user_id');
                Event::fire('orbit.upload.postdeleteuserimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_product')) {
                    if ((string)$user_id !== $user->user_id) {
                        Event::fire('orbit.upload.postdeleteuserimage.authz.notallowed', array($this, $user));
                        $editUserLang = Lang::get('validation.orbit.actionlist.update_user');

                        $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editUserLang));
                        ACL::throwAccessForbidden($message);
                    }
                }
                Event::fire('orbit.upload.postdeleteuserimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $user_id = OrbitInput::post('user_id');

            $validator = Validator::make(
                array(
                    'user_id'    => $user_id,
                ),
                array(
                    'user_id'   => 'required|orbit.empty.user',
                )
            );

            Event::fire('orbit.upload.postdeleteuserimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('user.new,user.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeleteuserimage.after.validation', array($this, $validator));

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $user = App::make('orbit.empty.user');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $user->user_id)
                              ->where('object_name', 'user')
                              ->where('media_name_id', 'user_profile_picture');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeleteuserimage.before.save', array($this, $user));

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            $user->userdetail->photo = NULL;
            $user->userdetail->save();

            Event::fire('orbit.upload.postdeleteuserimage.after.save', array($this, $user));

            $this->response->data = $user;
            $this->response->message = Lang::get('statuses.orbit.uploaded.user.profile_picture_deleted');

            if (! $this->calledFrom('user.new,user.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeleteuserimage.after.commit', array($this, $user));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeleteuserimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('user.new,user.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeleteuserimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('user.new,user.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeleteuserimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('user.new,user.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeleteuserimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('user.new, user.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeleteuserimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload photo for a coupon.
     *
     * @author Tian <tian@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                (required) - ID of the coupon
     * @param file|array `images`                      (required) - Coupon images
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadCouponImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadcouponimage.before.auth', array($this));

            if (! $this->calledFrom('coupon.new, coupon.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadcouponimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadcouponimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_coupon')) {
                    Event::fire('orbit.upload.postuploadcouponimage.authz.notallowed', array($this, $user));
                    $editCouponLang = Lang::get('validation.orbit.actionlist.update_coupon');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editCouponLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadcouponimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $promotion_id = OrbitInput::post('promotion_id');
            $images = OrbitInput::files('images');
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'promotion_id'  => $promotion_id,
                    'images'        => $images,
                ),
                array(
                    'promotion_id'  => 'required|orbit.empty.coupon',
                    'images'        => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadcouponimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('coupon.new,coupon.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadcouponimage.after.validation', array($this, $validator));

            // We already had Coupon instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $coupon = App::make('orbit.empty.coupon');

            // Callback to rename the file, we will format it as follow
            // [PROMOTION_ID]-[PROMOTION_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($coupon)
            {
                $promotion_id = $coupon->promotion_id;
                $slug = Str::slug($coupon->promotion_name);
                $file['new']->name = sprintf('%s-%s-%s', $promotion_id, $slug, time());
            };

            // Load the orbit configuration for coupon upload
            $uploadCouponConfig = Config::get('orbit.upload.coupon.main');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadCouponConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadcouponimage.before.save', array($this, $coupon, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Delete old coupon image
            $pastMedia = Media::where('object_id', $coupon->promotion_id)
                              ->where('object_name', 'coupon')
                              ->where('media_name_id', 'coupon_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $coupon->promotion_id,
                'name'          => 'coupon',
                'media_name_id' => 'coupon_image',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per coupon
            if (isset($uploaded[0])) {
                $coupon->image = $uploaded[0]['path'];
                $coupon->save();
            }

            Event::fire('orbit.upload.postuploadcouponimage.after.save', array($this, $coupon, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'coupon_image';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.coupon.main');

            if (! $this->calledFrom('coupon.new,coupon.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadcouponimage.after.commit', array($this, $coupon, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadcouponimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('coupon.new,coupon.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadcouponimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('coupon.new,coupon.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadcouponimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('coupon.new,coupon.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadcouponimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('coupon.new, coupon.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadcouponimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete photo for a coupon.
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                  (required) - ID of the coupon
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteCouponImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletecouponimage.before.auth', array($this));

            if (! $this->calledFrom('coupon.new, coupon.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletecouponimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletecouponimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_coupon')) {
                    Event::fire('orbit.upload.postdeletecouponimage.authz.notallowed', array($this, $user));
                    $editCouponLang = Lang::get('validation.orbit.actionlist.update_coupon');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editCouponLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postdeletecouponimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $promotion_id = OrbitInput::post('promotion_id');

            $validator = Validator::make(
                array(
                    'promotion_id'    => $promotion_id,
                ),
                array(
                    'promotion_id'   => 'required|orbit.empty.coupon',
                )
            );

            Event::fire('orbit.upload.postdeletecouponimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('coupon.new,coupon.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletecouponimage.after.validation', array($this, $validator));

            // We already had Coupon instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $coupon = App::make('orbit.empty.coupon');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $coupon->promotion_id)
                              ->where('object_name', 'coupon')
                              ->where('media_name_id', 'coupon_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletecouponimage.before.save', array($this, $coupon));

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per coupon
            $coupon->image = NULL;
            $coupon->save();

            Event::fire('orbit.upload.postdeletecouponimage.after.save', array($this, $coupon));

            $this->response->data = $coupon;
            $this->response->message = Lang::get('statuses.orbit.uploaded.coupon.delete_image');

            if (! $this->calledFrom('coupon.new,coupon.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletecouponimage.after.commit', array($this, $coupon));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletecouponimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('coupon.new,coupon.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletecouponimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('coupon.new,coupon.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletecouponimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('coupon.new,coupon.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletecouponimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('coupon.new, coupon.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletecouponimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload widget images.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Firmansyah <firmansayh@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `widget_id`                    (required) - ID of the widget
     * @param file|array `images`                       (required) - Images of the user photo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadWidgetImage($widgetType, $widgetOrder = 0)
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadwidgetimage.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('widget.new, widget.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadwidgetimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadwidgetimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_widget')) {
                    Event::fire('orbit.upload.postuploadwidgetimage.authz.notallowed', array($this, $user));

                    $editUserLang = Lang::get('validation.orbit.actionlist.update_widget');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editUserLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadwidgetimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $widget_id = OrbitInput::post('widget_id');
            $images = OrbitInput::files('image_'.$widgetType);

            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'widget_id' => $widget_id,
                    'images'    => $images,
                ),
                array(
                    'widget_id' => 'required|orbit.empty.widget',
                    'images'    => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadwidgetimage.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('widget.new, widget.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadwidgetimage.after.validation', array($this, $validator));

            // We already had User instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $widget = App::make('orbit.empty.widget');

            // Callback to rename the file, we will format it as follow
            // [WIDGET_ID]-[WIDGET_SLOGAN]
            $renameFile = function($uploader, &$file, $dir) use ($widget)
            {
                $widget_id = $widget->widget_id;
                $slug = Str::slug($widget->widget_slogan);
                $file['new']->name = sprintf('%s-%s-%s', $widget_id, $slug, time());
            };

            // Load the orbit configuration for user profile picture
            $uploadImageConfig = Config::get('orbit.upload.widget.main');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadImageConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadwidgetimage.before.save', array($this, $widget, $uploader));
            // Begin uploading the files
            if ($widgetOrder !== 0) {
                $uploaded = $uploader->uploadWidget($images, $widgetOrder);
            } else {
                $uploaded = $uploader->upload($images);
            }

            // Delete old user picture
            $pastMedia = Media::where('object_id', $widget->widget_id)
                              ->where('object_name', 'widget')
                              ->where('media_name_id', 'home_widget');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $widget->widget_id,
                'name'          => 'widget',
                'media_name_id' => 'home_widget',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            Event::fire('orbit.upload.postuploadwidgetimage.after.save', array($this, $widget, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.widget.main');

            // Commit the changes
            if (! $this->calledFrom('widget.new, widget.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadwidgetimage.after.commit', array($this, $widget, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadwidgetimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('widget.new, widget.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadwidgetimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('widget.new, widget.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadwidgetimage.query.error', array($this, $e));

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
            if (! $this->calledFrom('widget.new, widget.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadwidgetimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('widget.new, widget.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadwidgetimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload photo for a event.
     *
     * @author Tian <tian@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `event_id`                (required) - ID of the event
     * @param file|array `images`                  (required) - Event images
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadEventImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadeventimage.before.auth', array($this));

            if (! $this->calledFrom('event.new, event.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadeventimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadeventimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_event')) {
                    Event::fire('orbit.upload.postuploadeventimage.authz.notallowed', array($this, $user));
                    $editEventLang = Lang::get('validation.orbit.actionlist.update_event');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editEventLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadeventimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $event_id = OrbitInput::post('event_id');
            $images = OrbitInput::files('images');
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'event_id'      => $event_id,
                    'images'        => $images,
                ),
                array(
                    'event_id'      => 'required|orbit.empty.event',
                    'images'        => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadeventimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('event.new,event.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadeventimage.after.validation', array($this, $validator));

            // We already had Event instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $event = App::make('orbit.empty.event');

            // Callback to rename the file, we will format it as follow
            // [PROMOTION_ID]-[PROMOTION_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($event)
            {
                $event_id = $event->event_id;
                $slug = Str::slug($event->event_name);
                $file['new']->name = sprintf('%s-%s-%s', $event_id, $slug, time());
            };

            // Load the orbit configuration for event upload
            $uploadEventConfig = Config::get('orbit.upload.event.main');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadEventConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadeventimage.before.save', array($this, $event, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Delete old event image
            $pastMedia = Media::where('object_id', $event->event_id)
                              ->where('object_name', 'event')
                              ->where('media_name_id', 'event_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $event->event_id,
                'name'          => 'event',
                'media_name_id' => 'event_image',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per event
            if (isset($uploaded[0])) {
                $event->image = $uploaded[0]['path'];
                $event->save();
            }

            Event::fire('orbit.upload.postuploadeventimage.after.save', array($this, $event, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.event.main');

            if (! $this->calledFrom('event.new,event.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadeventimage.after.commit', array($this, $event, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadeventimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('event.new,event.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadeventimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('event.new,event.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadeventimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('event.new,event.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadeventimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('event.new,event.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadeventimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete photo for a event.
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `event_id`                  (required) - ID of the event
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteEventImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeleteeventimage.before.auth', array($this));

            if (! $this->calledFrom('event.new, event.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeleteeventimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeleteeventimage.before.authz', array($this, $user));

/*
                if (! ACL::create($user)->isAllowed('update_event')) {
                    Event::fire('orbit.upload.postdeleteeventimage.authz.notallowed', array($this, $user));
                    $editEventLang = Lang::get('validation.orbit.actionlist.update_event');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editEventLang));
                    ACL::throwAccessForbidden($message);
                }
*/
                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.upload.postdeleteeventimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $event_id = OrbitInput::post('event_id');

            $validator = Validator::make(
                array(
                    'event_id'      => $event_id,
                ),
                array(
                    'event_id'      => 'required|orbit.empty.event',
                )
            );

            Event::fire('orbit.upload.postdeleteeventimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('event.new,event.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeleteeventimage.after.validation', array($this, $validator));

            // We already had Event instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $event = App::make('orbit.empty.event');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $event->event_id)
                              ->where('object_name', 'event')
                              ->where('media_name_id', 'event_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeleteeventimage.before.save', array($this, $event));

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per event
            $event->image = NULL;
            $event->save();

            Event::fire('orbit.upload.postdeleteeventimage.after.save', array($this, $event));

            $this->response->data = $event;
            $this->response->message = Lang::get('statuses.orbit.uploaded.event.delete_image');

            if (! $this->calledFrom('event.new,event.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeleteeventimage.after.commit', array($this, $event));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeleteeventimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('event.new,event.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeleteeventimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('event.new,event.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeleteeventimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('event.new,event.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeleteeventimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('event.new, event.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeleteeventimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload image for a event tranlation (selected language).
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     * @author Irianto Pratama <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `event_id`                     (required) - ID of the event
     * @param integer    `event_translation_id`         (required) - ID of the event tranlation
     * @param integer    `merchant_language_id`         (required) - ID of the merchan language
     * @param file|array `image_translation`            (required) - Event translation images
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadEventTranslationImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadeventtranslationimage.before.auth', array($this));

            if (! $this->calledFrom('event.translations'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadeventtranslationimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadeventtranslationimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_event')) {
                    Event::fire('orbit.upload.postuploadeventtranslationimage.authz.notallowed', array($this, $user));
                    $editEventLang = Lang::get('validation.orbit.actionlist.update_event');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editEventLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadeventtranslationimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $event_translation_id = OrbitInput::post('event_translation_id');
            $event_id = OrbitInput::post('event_id');
            $merchant_language_id = OrbitInput::post('merchant_language_id');
            $image_translation = OrbitInput::files('image_translation_' . $merchant_language_id);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'event_translation_id'      => $event_translation_id,
                    'event_id'                  => $event_id,
                    'merchant_language_id'      => $merchant_language_id,
                    'image_translation'         => $image_translation,
                ),
                array(
                    'event_translation_id'      => 'required|orbit.empty.event_translation',
                    'event_id'                  => 'required|orbit.empty.event',
                    'merchant_language_id'      => 'required|orbit.empty.merchant_language',
                    'image_translation'         => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadeventtranslationimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('event.translations')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadeventtranslationimage.after.validation', array($this, $validator));

            // We already had Event Translation instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $event_translations = App::make('orbit.empty.event_translation');

            // Callback to rename the file, we will format it as follow
            // [PROMOTION_ID]-[PROMOTION_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($event_translations)
            {
                $event_translation_id = $event_translations->event_translation_id;
                $slug = Str::slug($event_translations->event_name);
                $file['new']->name = sprintf('%s-%s-%s', $event_translation_id, $slug, time());
            };

            // Load the orbit configuration for event upload
            $uploadEventConfig = Config::get('orbit.upload.event.translation');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadEventConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadeventtranslationimage.before.save', array($this, $event_translations, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($image_translation);

            // Delete old event translation image
            $pastMedia = Media::where('object_id', $event_translations->event_translation_id)
                              ->where('object_name', 'event_translation')
                              ->where('media_name_id', 'event_translation_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $event_translations->event_translation_id,
                'name'          => 'event_translation',
                'media_name_id' => 'event_translation_image',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image_translation` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per event
            if (isset($uploaded[0])) {
                $event_translations->save();
            }

            Event::fire('orbit.upload.postuploadeventtranslationimage.after.save', array($this, $event_translations, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.event_translation.main');

            if (! $this->calledFrom('event.translations')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadeventtranslationimage.after.commit', array($this, $event_translations, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadeventtranslationimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('event.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadeventtranslationimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('event.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadeventtranslationimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('event.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadeventtranslationimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('event.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadeventtranslationimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload image for a coupon translation (selected language).
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                 (required) - ID of the coupon
     * @param integer    `coupon_translation_id`        (required) - ID of the coupon tranlation
     * @param integer    `merchant_language_id`         (required) - ID of the merchant language
     * @param file|array `image_translation`            (required) - Translation images
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadCouponTranslationImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadcoupontranslationimage.before.auth', array($this));

            if (! $this->calledFrom('coupon.translations'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadcoupontranslationimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadcoupontranslationimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_coupon')) {
                    Event::fire('orbit.upload.postuploadcoupontranslationimage.authz.notallowed', array($this, $user));
                    $editCouponLang = Lang::get('validation.orbit.actionlist.update_coupon');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editCouponLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadcoupontranslationimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $coupon_translation_id = OrbitInput::post('coupon_translation_id');
            $promotion_id = OrbitInput::post('promotion_id');
            $merchant_language_id = OrbitInput::post('merchant_language_id');
            $image_translation = OrbitInput::files('image_translation_' . $merchant_language_id);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'coupon_translation_id'      => $coupon_translation_id,
                    'promotion_id'               => $promotion_id,
                    'merchant_language_id'       => $merchant_language_id,
                    'image_translation'          => $image_translation,
                ),
                array(
                    'coupon_translation_id'      => 'required|orbit.empty.coupon_translation',
                    'promotion_id'               => 'required|orbit.empty.coupon',
                    'merchant_language_id'       => 'required|orbit.empty.merchant_language',
                    'image_translation'          => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadcoupontranslationimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('coupon.translations')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadcoupontranslationimage.after.validation', array($this, $validator));

            // We already had Coupon Translation instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $coupon_translations = App::make('orbit.empty.coupon_translation');

            // Callback to rename the file, we will format it as follow
            // [PROMOTION_ID]-[PROMOTION_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($coupon_translations)
            {
                $coupon_translation_id = $coupon_translations->coupon_translation_id;
                $slug = Str::slug($coupon_translations->event_name);
                $file['new']->name = sprintf('%s-%s-%s', $coupon_translation_id, $slug, time());
            };

            // Load the orbit configuration for event upload
            $uploadCouponConfig = Config::get('orbit.upload.coupon.translation');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadCouponConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadcoupontranslationimage.before.save', array($this, $coupon_translations, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($image_translation);

            // Delete old coupon translation image
            $pastMedia = Media::where('object_id', $coupon_translations->coupon_translation_id)
                              ->where('object_name', 'coupon_translation')
                              ->where('media_name_id', 'coupon_translation_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $coupon_translations->coupon_translation_id,
                'name'          => 'coupon_translation',
                'media_name_id' => 'coupon_translation_image',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image_translation` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per event
            if (isset($uploaded[0])) {
                $coupon_translations->save();
            }

            Event::fire('orbit.upload.postuploadcoupontranslationimage.after.save', array($this, $coupon_translations, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'coupon_translation_image';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.coupon_translation.main');

            if (! $this->calledFrom('coupon.translations')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadcoupontranslationimage.after.commit', array($this, $coupon_translations, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadcoupontranslationimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadcoupontranslationimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadcoupontranslationimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadcoupontranslationimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadcoupontranslationimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload image for a coupon 3rd party header translation (selected language).
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                 (required) - ID of the coupon
     * @param integer    `coupon_translation_id`        (required) - ID of the coupon tranlation
     * @param integer    `merchant_language_id`         (required) - ID of the merchant language
     * @param file|array `header_image_translation`     (required) - Translation images
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadCouponHeaderTranslationImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadcouponheadertranslationimage.before.auth', array($this));

            if (! $this->calledFrom('coupon.translations'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadcouponheadertranslationimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadcouponheadertranslationimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_coupon')) {
                    Event::fire('orbit.upload.postuploadcouponheadertranslationimage.authz.notallowed', array($this, $user));
                    $editCouponLang = Lang::get('validation.orbit.actionlist.update_coupon');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editCouponLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadcouponheadertranslationimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $coupon_translation_id = OrbitInput::post('coupon_translation_id');
            $promotion_id = OrbitInput::post('promotion_id');
            $merchant_language_id = OrbitInput::post('merchant_language_id');
            $image_translation = OrbitInput::files('header_image_translation_' . $merchant_language_id);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'coupon_translation_id'      => $coupon_translation_id,
                    'promotion_id'               => $promotion_id,
                    'merchant_language_id'       => $merchant_language_id,
                    'header_image_translation'   => $image_translation,
                ),
                array(
                    'coupon_translation_id'      => 'required|orbit.empty.coupon_translation',
                    'promotion_id'               => 'required|orbit.empty.coupon',
                    'merchant_language_id'       => 'required|orbit.empty.merchant_language',
                    'header_image_translation'   => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadcouponheadertranslationimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('coupon.translations')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.upload.postuploadcouponheadertranslationimage.after.validation', array($this, $validator));

            // We already had Coupon Translation instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $coupon_translations = App::make('orbit.empty.coupon_translation');

            // Callback to rename the file, we will format it as follow
            // [TYPE]-[PROMOTION_ID]-[PROMOTION_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($coupon_translations)
            {
                $coupon_translation_id = $coupon_translations->coupon_translation_id;
                $slug = Str::slug($coupon_translations->promotion_name);
                $file['new']->name = sprintf('%s-%s-%s-%s', 'header', $coupon_translation_id, $slug, time());
            };

            // Load the orbit configuration for event upload
            $uploadCouponConfig = Config::get('orbit.upload.coupon.third_party_header');

            $message = new UploaderMessage(array(
                'errors' => array(
                    'unknown_error'         => 'Unknown upload error.',
                    'no_file_uploaded'      => 'No file being uploaded.',
                    'path_not_found'        => 'Unable to find the upload directory.',
                    'no_write_access'       => 'Unable to write to the upload directory.',
                    'file_too_big'          => 'Header image size is too big, maximum size allowed is :size :unit.',
                    'file_type_not_allowed' => 'File extension ":extension" is not allowed.',
                    'mime_type_not_allowed' => 'File with mime type of ":mime" is not allowed.',
                    'dimension_not_allowed' => 'Maximum dimension allowed is :maxdimension, your image dimension is :yoursdimension',
                    'unable_to_upload'      => 'Unable to move the uploaded file.'
                ),
            ));
            $config = new UploaderConfig($uploadCouponConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadcouponheadertranslationimage.before.save', array($this, $coupon_translations, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($image_translation);

            // Delete old coupon translation image
            $pastMedia = Media::where('object_id', $coupon_translations->coupon_translation_id)
                              ->where('object_name', 'coupon_translation')
                              ->where('media_name_id', 'coupon_header_grab_translation');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $coupon_translations->coupon_translation_id,
                'name'          => 'coupon_translation',
                'media_name_id' => 'coupon_header_grab_translation',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image_translation` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per event
            if (isset($uploaded[0])) {
                $coupon_translations->save();
            }

            Event::fire('orbit.upload.postuploadcouponheadertranslationimage.after.save', array($this, $coupon_translations, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'coupon_header_grab_translation';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.coupon_translation.main');

            if (! $this->calledFrom('coupon.translations')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadcouponheadertranslationimage.after.commit', array($this, $coupon_translations, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadcouponheadertranslationimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadcouponheadertranslationimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadcouponheadertranslationimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadcouponheadertranslationimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadcouponheadertranslationimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload image for a coupon 3rd party image1 translation (selected language).
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `promotion_id`                 (required) - ID of the coupon
     * @param integer    `coupon_translation_id`        (required) - ID of the coupon tranlation
     * @param integer    `merchant_language_id`         (required) - ID of the merchant language
     * @param file|array `header_image_translation`     (required) - Translation images
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadCouponImage1TranslationImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadcouponimage1translationimage.before.auth', array($this));

            if (! $this->calledFrom('coupon.translations'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadcouponimage1translationimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadcouponimage1translationimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_coupon')) {
                    Event::fire('orbit.upload.postuploadcouponimage1translationimage.authz.notallowed', array($this, $user));
                    $editCouponLang = Lang::get('validation.orbit.actionlist.update_coupon');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editCouponLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadcouponimage1translationimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $coupon_translation_id = OrbitInput::post('coupon_translation_id');
            $promotion_id = OrbitInput::post('promotion_id');
            $merchant_language_id = OrbitInput::post('merchant_language_id');
            $image_translation = OrbitInput::files('image1_translation_' . $merchant_language_id);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'coupon_translation_id'      => $coupon_translation_id,
                    'promotion_id'               => $promotion_id,
                    'merchant_language_id'       => $merchant_language_id,
                    'image1_translation'         => $image_translation,
                ),
                array(
                    'coupon_translation_id'      => 'required|orbit.empty.coupon_translation',
                    'promotion_id'               => 'required|orbit.empty.coupon',
                    'merchant_language_id'       => 'required|orbit.empty.merchant_language',
                    'image1_translation'         => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadcouponimage1translationimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('coupon.translations')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.upload.postuploadcouponimage1translationimage.after.validation', array($this, $validator));

            // We already had Coupon Translation instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $coupon_translations = App::make('orbit.empty.coupon_translation');

            // Callback to rename the file, we will format it as follow
            // [TYPE]-[PROMOTION_ID]-[PROMOTION_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($coupon_translations)
            {
                $coupon_translation_id = $coupon_translations->coupon_translation_id;
                $slug = Str::slug($coupon_translations->promotion_name);
                $file['new']->name = sprintf('%s-%s-%s-%s', 'image1', $coupon_translation_id, $slug, time());
            };

            // Load the orbit configuration for event upload
            $uploadCouponConfig = Config::get('orbit.upload.coupon.third_party_image');

            $message = new UploaderMessage(array(
                'errors' => array(
                    'unknown_error'         => 'Unknown upload error.',
                    'no_file_uploaded'      => 'No file being uploaded.',
                    'path_not_found'        => 'Unable to find the upload directory.',
                    'no_write_access'       => 'Unable to write to the upload directory.',
                    'file_too_big'          => 'Image 1 size is too big, maximum size allowed is :size :unit.',
                    'file_type_not_allowed' => 'File extension ":extension" is not allowed.',
                    'mime_type_not_allowed' => 'File with mime type of ":mime" is not allowed.',
                    'dimension_not_allowed' => 'Maximum dimension allowed is :maxdimension, your image dimension is :yoursdimension',
                    'unable_to_upload'      => 'Unable to move the uploaded file.'
                ),
            ));
            $config = new UploaderConfig($uploadCouponConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadcouponimage1translationimage.before.save', array($this, $coupon_translations, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($image_translation);

            // Delete old coupon translation image
            $pastMedia = Media::where('object_id', $coupon_translations->coupon_translation_id)
                              ->where('object_name', 'coupon_translation')
                              ->where('media_name_id', 'coupon_image1_grab_translation');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $coupon_translations->coupon_translation_id,
                'name'          => 'coupon_translation',
                'media_name_id' => 'coupon_image1_grab_translation',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image_translation` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per event
            if (isset($uploaded[0])) {
                $coupon_translations->save();
            }

            Event::fire('orbit.upload.postuploadcouponimage1translationimage.after.save', array($this, $coupon_translations, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'coupon_image1_grab_translation';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.coupon_translation.main');

            if (! $this->calledFrom('coupon.translations')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadcouponimage1translationimage.after.commit', array($this, $coupon_translations, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadcouponimage1translationimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadcouponimage1translationimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadcouponimage1translationimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadcouponimage1translationimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadcouponimage1translationimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload logo for Tenant.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - ID of the merchant
     * @param file|array `images`                      (required) - Images of the logo
     * @param string     `object_type`                 (required) - Object type of tenant : tenant or service
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadTenantLogo()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadtenantlogo.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadtenantlogo.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadtenantlogo.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_retailer')) {
                    Event::fire('orbit.upload.postuploadtenantlogo.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_retailer');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadtenantlogo.after.authz', array($this, $user));
            } else {
                $user = App::make('orbit.upload.user');
            }

            // Load the orbit configuration for merchant upload logo
            $uploadLogoConfig = Config::get('orbit.upload.retailer.logo');
            $elementName = $uploadLogoConfig['name'];

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');
            $object_type = OrbitInput::post('object_type');
            $images = OrbitInput::files($elementName);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    $elementName  => $images,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.tenantstoreandservice',
                    $elementName    => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadtenantlogo.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadtenantlogo.after.validation', array($this, $validator));

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.tenantstoreandservice');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($merchant)
            {
                $merchant_id = $merchant->merchant_id;
                $slug = Str::slug($merchant->name);
                $file['new']->name = sprintf('%s-%s-%s', $merchant_id, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadLogoConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadtenantlogo.before.save', array($this, $merchant, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            $object_name = '';
            $media_name_id = '';

            // Set object_name and media name id as each object type (tenant or sevice)
            if ($object_type === 'tenant') {
                $object_name = 'retailer';
                $media_name_id = 'retailer_logo';
            } elseif ($object_type === 'service') {
                $object_name = 'service';
                $media_name_id = 'service_logo';
            }

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', $object_name)
                              ->where('media_name_id', $media_name_id);

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $merchant->merchant_id,
                'name'          => $object_name,
                'media_name_id' => $media_name_id,
                'modified_by'   => $user->user_id
            );

            $mediaList = $this->saveMetadata($object, $uploaded);

            Event::fire('orbit.upload.postuploadtenantlogo.after.save', array($this, $merchant, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.retailer.logo');

            // Commit the changes
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadtenantlogo.after.commit', array($this, $merchant, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadtenantlogo.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadtenantlogo.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadtenantlogo.query.error', array($this, $e));

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
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadtenantlogo.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadtenantlogo.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete logo for a merchant.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                  (required) - ID of the merchant
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteTenantLogo()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletetenantlogo.before.auth', array($this));

            if (! $this->calledFrom('tenant.new, tenant.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletetenantlogo.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletetenantlogo.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_mall')) {
                    Event::fire('orbit.upload.postdeletetenantlogo.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_retailer');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postdeletetenantlogo.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');

            $validator = Validator::make(
                array(
                    'merchant_id'   => $merchant_id,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.tenant',
                )
            );

            Event::fire('orbit.upload.postdeletetenantlogo.before.validation', array($this, $validator));

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletetenantlogo.after.validation', array($this, $validator));

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.tenant');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'retailer')
                              ->where('media_name_id', 'retailer_logo');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletetenantlogo.before.save', array($this, $merchant));

            // Update the `logo` field which store the original path of the logo
            // This is temporary since right now the business rules actually
            // only allows one logo per merchant
            $merchant->logo = NULL;
            $merchant->save();

            Event::fire('orbit.upload.postdeletetenantlogo.after.save', array($this, $merchant));

            $this->response->data = $merchant;
            $this->response->message = Lang::get('statuses.orbit.uploaded.retailer.delete_logo');

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletetenantlogo.after.commit', array($this, $merchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletetenantlogo.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletetenantlogo.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletetenantlogo.query.error', array($this, $e));

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

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletetenantlogo.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('tenant.new, tenant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletetenantlogo.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload images for Tenant.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - ID of the merchant
     * @param file|array `pictures`                    (required) - Images of the logo
     * @param string     `object_type`                 (required) - Object type of tenant : tenant or service
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadTenantImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadtenantimage.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadtenantimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadtenantimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_retailer')) {
                    Event::fire('orbit.upload.postuploadtenantimage.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_retailer');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadtenantimage.after.authz', array($this, $user));
            } else {
                // Comes from event
                $user = App::make('orbit.upload.user');
            }

            // Load the orbit configuration for merchant upload logo
            $uploadImageConfig = Config::get('orbit.upload.retailer.picture');
            $elementName = $uploadImageConfig['name'];

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');
            $object_type = OrbitInput::post('object_type');

            $images = OrbitInput::files($elementName);
            $messages = array(
                'nomore.than.three' => Lang::get('validation.max.array', array(
                    'max' => 3
                ))
            );

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    $elementName  => $images,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.tenantstoreandservice',
                    $elementName    => 'required|array|nomore.than.three',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadtenantimage.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadtenantimage.after.validation', array($this, $validator));

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.tenantstoreandservice');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($merchant)
            {
                $merchant_id = $merchant->merchant_id;
                $slug = Str::slug($merchant->name);
                $file['new']->name = sprintf('%s-%s-%s', $merchant_id, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadImageConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadtenantimage.before.save', array($this, $merchant, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            $object_name = '';
            $media_name_id = '';

            // Set object_name and media name id as each object type (tenant or sevice)
            if ($object_type === 'tenant') {
                $object_name = 'retailer';
                $media_name_id = 'retailer_image';
            } elseif ($object_type === 'service') {
                $object_name = 'service';
                $media_name_id = 'service_image';
            }

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', $object_name)
                              ->where('media_name_id', $media_name_id);

            // Get the index of the image to delete the right one
            $increment = 0;
            $imgOrder = array_keys($images['name']);

            $pastMedia->where(function($q) use ($increment, $imgOrder) {
                foreach ($imgOrder as $indexOrder) {
                    $q->orWhere('metadata', 'order-' . $indexOrder);
                }
            });

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $merchant->merchant_id,
                'name'          => $object_name,
                'media_name_id' => $media_name_id,
                'modified_by'   => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            if (isset($uploaded[0])) {
                $merchant->logo = $uploaded[0]['path'];
                $merchant->save();
            }

            Event::fire('orbit.upload.postuploadtenantimage.after.save', array($this, $merchant, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = 'Tenant picture has been successfully uploaded.';

            // Commit the changes
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadtenantimage.after.commit', array($this, $merchant, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadtenantimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadtenantimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadtenantimage.query.error', array($this, $e));

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
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadtenantimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadtenantimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete images for a merchant.
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                  (required) - ID of the merchant/retailer
     * @param integer    `picture_index`                (required) - Index of the picture
     * @param integer    `object_type`                  (required) - Object type of tenant : tenant or service
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteTenantImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletetenantimage.before.auth', array($this));

            if (! $this->calledFrom('tenant.new, tenant.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletetenantimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletetenantimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_mall')) {
                    Event::fire('orbit.upload.postdeletetenantimage.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_retailer');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postdeletetenantimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');
            $picture_index = OrbitInput::post('picture_index');
            $object_type = OrbitInput::post('object_type');

            $validator = Validator::make(
                array(
                    'merchant_id'   => $merchant_id,
                    'picture_index' => $picture_index,
                    'object_type'   => $object_type,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.tenantstoreandservice',
                    'picture_index' => 'array',
                    'object_type'   => 'orbit.empty.tenant_type',
                )
            );

            Event::fire('orbit.upload.postdeletetenantimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletetenantimage.after.validation', array($this, $validator));

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.tenantstoreandservice');

            $object_name = '';
            $media_name_id = '';

            // Set object_name and media name id as each object type (tenant or sevice)
            if ($object_type === 'tenant') {
                $object_name = 'retailer';
                $media_name_id = 'retailer_image';
            } elseif ($object_type === 'service') {
                $object_name = 'service';
                $media_name_id = 'service_image';
            }


            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', $object_name)
                              ->where('media_name_id', $media_name_id);

            if (! empty($picture_index)) {
                $pastMedia->where(function($q) use ($picture_index) {
                    foreach ($picture_index as $indexOrder) {
                        $q->orWhere('metadata', 'order-' . $indexOrder);
                    }
                });
            }

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletetenantimage.before.save', array($this, $merchant));

            // Update the `logo` field which store the original path of the logo
            // This is temporary since right now the business rules actually
            // only allows one logo per merchant
            $merchant->logo = NULL;
            $merchant->save();

            Event::fire('orbit.upload.postdeletetenantimage.after.save', array($this, $merchant));

            $this->response->data = $merchant;
            $this->response->message = Lang::get('statuses.orbit.uploaded.retailer.delete_image');

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletetenantimage.after.commit', array($this, $merchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletetenantimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletetenantimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletetenantimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletetenantimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('tenant.new, tenant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletetenantimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload map for Tenant.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - ID of the merchant
     * @param file|array `maps`                        (required) - Images of the logo
     * @param string     `object_type`                 (required) - Object type of tenant : tenant or service
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadTenantMap()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadtenantmap.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->checkAuth();

                Event::fire('orbit.uploadpostuploadtenantmap.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.uploadpostuploadtenantmap.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_retailer')) {
                    Event::fire('orbit.uploadpostuploadtenantmap.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_retailer');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.uploadpostuploadtenantmap.after.authz', array($this, $user));
            } else {
                // Comes from the events trigger
                $user = App::make('orbit.upload.user');
            }

            // Load the orbit configuration for merchant upload logo
            $uploadLogoConfig = Config::get('orbit.upload.retailer.map');
            $elementName = $uploadLogoConfig['name'];

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');
            $object_type = OrbitInput::post('object_type');
            $images = OrbitInput::files($elementName);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    $elementName  => $images,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.tenantstoreandservice',
                    $elementName    => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.uploadpostuploadtenantmap.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.uploadpostuploadtenantmap.after.validation', array($this, $validator));

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.tenantstoreandservice');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($merchant)
            {
                $merchant_id = $merchant->merchant_id;
                $slug = Str::slug($merchant->name);
                $file['new']->name = sprintf('%s-%s-%s', $merchant_id, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadLogoConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.uploadpostuploadtenantmap.before.save', array($this, $merchant, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            $object_name = '';
            $media_name_id = '';

            // Set object_name and media name id as each object type (tenant or sevice)
            if ($object_type === 'tenant') {
                $object_name = 'retailer';
                $media_name_id = 'retailer_map';
            } elseif ($object_type === 'service') {
                $object_name = 'service';
                $media_name_id = 'service_map';
            }

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', $object_name)
                              ->where('media_name_id', $media_name_id);

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $merchant->merchant_id,
                'name'          => $object_name,
                'media_name_id' => $media_name_id,
                'modified_by'   => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            if (isset($uploaded[0])) {
                $merchant->logo = $uploaded[0]['path'];
                $merchant->save();
            }

            Event::fire('orbit.uploadpostuploadtenantmap.after.save', array($this, $merchant, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.retailer.map');

            // Commit the changes
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->commit();
            }

            Event::fire('orbit.uploadpostuploadtenantmap.after.commit', array($this, $merchant, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.uploadpostuploadtenantmap.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.uploadpostuploadtenantmap.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.uploadpostuploadtenantmap.query.error', array($this, $e));

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
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.uploadpostuploadtenantmap.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.uploadpostuploadtenantmap.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete logo for a merchant.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                  (required) - ID of the merchant
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteTenantMap()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletetenantmap.before.auth', array($this));

            if (! $this->calledFrom('tenant.new, tenant.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletetenantmap.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletetenantmap.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_mall')) {
                    Event::fire('orbit.upload.postdeletetenantmap.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_retailer');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postdeletetenantmap.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');

            $validator = Validator::make(
                array(
                    'merchant_id'   => $merchant_id,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.tenant',
                )
            );

            Event::fire('orbit.upload.postdeletetenantmap.before.validation', array($this, $validator));

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletetenantmap.after.validation', array($this, $validator));

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.tenant');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'retailer')
                              ->where('media_name_id', 'retailer_map');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletetenantmap.before.save', array($this, $merchant));

            // Update the `logo` field which store the original path of the logo
            // This is temporary since right now the business rules actually
            // only allows one logo per merchant
            $merchant->logo = NULL;
            $merchant->save();

            Event::fire('orbit.upload.postdeletetenantmap.after.save', array($this, $merchant));

            $this->response->data = $merchant;
            $this->response->message = Lang::get('statuses.orbit.uploaded.retailer.delete_map');

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletetenantmap.after.commit', array($this, $merchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletetenantmap.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletetenantmap.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletetenantmap.query.error', array($this, $e));

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

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletetenantmap.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('tenant.new, tenant.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletetenantmap.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload background mobile page for Mall.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @author Firmansyah <firmansyah@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - ID of the merchant
     * @param file|array `backgrounds`                 (required) - Images of the background
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadMallBackground()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadmallbackground.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadmallbackground.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadmallbackground.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_retailer')) {
                    Event::fire('orbit.upload.postuploadmallbackground.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_retailer');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadmallbackground.after.authz', array($this, $user));
            } else {
                // Comes from the events trigger
                $user = App::make('orbit.upload.user');
            }

            // Load the orbit configuration for merchant upload logo
            $uploadLogoConfig = Config::get('orbit.upload.retailer.background');
            $elementName = $uploadLogoConfig['name'];

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');
            $images = OrbitInput::files($elementName);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    $elementName  => $images,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.mall',
                    $elementName    => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadmallbackground.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadmallbackground.after.validation', array($this, $validator));

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.mall');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($merchant)
            {
                $merchant_id = $merchant->merchant_id;
                $slug = Str::slug($merchant->name);
                $file['new']->name = sprintf('%s-%s-%s', $merchant_id, $slug, time());
            };

            $custom_message = array(
                    'errors' => array(
                        'file_too_big'          => 'Login Page Background Image size is too big, maximum size allowed is :size :unit.',
                    ),
                );

            $message = new UploaderMessage($custom_message);
            $config = new UploaderConfig($uploadLogoConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadmallbackground.before.save', array($this, $merchant, $uploader));

            // Begin uploading the files, if upload failed
            $uploaded = $uploader->upload($images);

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'mall')
                              ->where('media_name_id', 'retailer_background');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $merchant->merchant_id,
                'name'          => 'mall',
                'media_name_id' => 'retailer_background',
                'modified_by'   => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            Event::fire('orbit.upload.postuploadmallbackground.after.save', array($this, $merchant, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.mall.background');

            // Commit the changes
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadmallbackground.after.commit', array($this, $merchant, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadmallbackground.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadmallbackground.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadmallbackground.query.error', array($this, $e));

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
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadmallbackground.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadmallbackground.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete background image for Mall.
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                  (required) - ID of the merchant
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteMallBackground()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletemallbackground.before.auth', array($this));

            if (! $this->calledFrom('mall.new, mall.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletemallbackground.after.auth', array($this));

                // Try to check access control list, does this news allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletemallbackground.before.authz', array($this, $user));

/*
                if (! ACL::create($user)->isAllowed('update_news')) {
                    Event::fire('orbit.upload.postdeletemallbackground.authz.notallowed', array($this, $user));
                    $editNewsLang = Lang::get('validation.orbit.actionlist.update_news');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editNewsLang));
                    ACL::throwAccessForbidden($message);
                }
*/
                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.upload.postdeletemallbackground.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');

            $validator = Validator::make(
                array(
                    'merchant_id'   => $merchant_id,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.mall',
                )
            );

            Event::fire('orbit.upload.postdeletemallbackground.before.validation', array($this, $validator));

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletemallbackground.after.validation', array($this, $validator));

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.mall');

            // Delete old merchant image
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'mall')
                              ->where('media_name_id', 'retailer_background');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletemallbackground.before.save', array($this, $merchant));

            // Update the `image` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per merchant
            // $merchant->logo = NULL;
            // $merchant->save();

            // On table settings, update background_image to null
            $updatedsetting = Setting::active()
                         ->where('object_id', $merchant->merchant_id)
                         ->where('object_type', 'merchant')
                         ->where('setting_name', 'background_image')
                         ->first();
            if (! empty($updatedsetting)) {
                $updatedsetting->setting_value = null;
                $updatedsetting->save();
            }

            Event::fire('orbit.upload.postdeletemallbackground.after.save', array($this, $merchant));

            $this->response->data = $merchant;
            $this->response->message = Lang::get('statuses.orbit.uploaded.mall.delete_background');

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletemallbackground.after.commit', array($this, $merchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletemallbackground.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletemallbackground.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletemallbackground.query.error', array($this, $e));

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

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletemallbackground.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletemallbackground.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload logo for Mall.
     *
     * @author Firmansyah <firmansyah@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - ID of the merchant
     * @param file|array `images`                      (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadMallLogo()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadmalllogo.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadmalllogo.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadmalllogo.before.authz', array($this, $user));

/*
                if (! ACL::create($user)->isAllowed('update_retailer')) {
                    Event::fire('orbit.upload.postuploadmalllogo.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_retailer');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
*/
                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.upload.postuploadmalllogo.after.authz', array($this, $user));
            } else {
                $user = App::make('orbit.upload.user');
            }

            // Load the orbit configuration for merchant upload logo
            $uploadLogoConfig = Config::get('orbit.upload.mall.logo');
            $elementName = $uploadLogoConfig['name'];

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');
            $images = OrbitInput::files($elementName);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    $elementName  => $images,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.mall',
                    $elementName    => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadmalllogo.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadmalllogo.after.validation', array($this, $validator));

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.mall');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($merchant)
            {
                $merchant_id = $merchant->merchant_id;
                $slug = Str::slug($merchant->name);
                $file['new']->name = sprintf('%s-%s-%s', $merchant_id, $slug, time());
            };

            $custom_message = array(
                    'errors' => array(
                        'file_too_big'          => 'Mall Logo size is too big, maximum size allowed is :size :unit.',
                    ),
                );

            $message = new UploaderMessage($custom_message);
            $config = new UploaderConfig($uploadLogoConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadmalllogo.before.save', array($this, $merchant, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'mall')
                              ->where('media_name_id', 'mall_logo');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $merchant->merchant_id,
                'name'          => 'mall',
                'media_name_id' => 'mall_logo',
                'modified_by'   => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            if (isset($uploaded[0])) {
                $merchant->logo = $uploaded[0]['path'];
                $merchant->save();
            }

            Event::fire('orbit.upload.postuploadmalllogo.after.save', array($this, $merchant, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'mall_logo';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.mall.logo');

            // Commit the changes
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadmalllogo.after.commit', array($this, $merchant, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadmalllogo.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadmalllogo.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadmalllogo.query.error', array($this, $e));

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
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadmalllogo.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadmalllogo.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete logo for a mall.
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                  (required) - ID of the merchant
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteMallLogo()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletemalllogo.before.auth', array($this));

            if (! $this->calledFrom('mall.new, mall.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletemalllogo.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletemalllogo.before.authz', array($this, $user));
/*
                if (! ACL::create($user)->isAllowed('update_mall')) {
                    Event::fire('orbit.upload.postdeletemalllogo.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_retailer');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
*/
                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.upload.postdeletemalllogo.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');

            $validator = Validator::make(
                array(
                    'merchant_id'   => $merchant_id,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.mall',
                )
            );

            Event::fire('orbit.upload.postdeletemalllogo.before.validation', array($this, $validator));

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletemalllogo.after.validation', array($this, $validator));

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.mall');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'mall')
                              ->where('media_name_id', 'mall_logo');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletemalllogo.before.save', array($this, $merchant));

            // Update the `logo` field which store the original path of the logo
            // This is temporary since right now the business rules actually
            // only allows one logo per merchant
            $merchant->logo = NULL;
            $merchant->save();

            Event::fire('orbit.upload.postdeletemalllogo.after.save', array($this, $merchant));

            $extras = new \stdClass();
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'mall_logo';
            $merchant['extras'] = $extras;

            $this->response->data = $merchant;
            $this->response->message = Lang::get('statuses.orbit.uploaded.mall.delete_logo');

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletemalllogo.after.commit', array($this, $merchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletemalllogo.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletemalllogo.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletemalllogo.query.error', array($this, $e));

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

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletemalllogo.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletemalllogo.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload images for News.
     *
     * @author Tian <tian@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `news_id`                     (required) - ID of the news
     * @param file|array `images`                      (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadNewsImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadnewsimage.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('news.new, news.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadnewsimage.after.auth', array($this));

                // Try to check access control list, does this news allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadnewsimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_news')) {
                    Event::fire('orbit.upload.postuploadnewsimage.authz.notallowed', array($this, $user));
                    $editNewsLang = Lang::get('validation.orbit.actionlist.update_news');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editNewsLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadnewsimage.after.authz', array($this, $user));
            } else {
                // Comes from event
                $user = App::make('orbit.upload.user');
            }

            // Load the orbit configuration for news upload image
            $uploadImageConfig = Config::get('orbit.upload.news.main');
            $elementName = $uploadImageConfig['name'];

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $news_id = OrbitInput::post('news_id');
            $images = OrbitInput::files($elementName);
            $messages = array(
                'nomore.than.three' => Lang::get('validation.max.array', array(
                    'max' => 3
                ))
            );

            $validator = Validator::make(
                array(
                    'news_id'       => $news_id,
                    $elementName    => $images,
                ),
                array(
                    'news_id'       => 'required|orbit.empty.news',
                    $elementName    => 'required|array|nomore.than.three',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadnewsimage.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('news.new, news.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadnewsimage.after.validation', array($this, $validator));

            // We already had News instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $news = App::make('orbit.empty.news');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($news)
            {
                $news_id = $news->news_id;
                $slug = Str::slug($news->news_name);
                $file['new']->name = sprintf('%s-%s-%s', $news_id, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadImageConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadnewsimage.before.save', array($this, $news, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Delete old news image
            $pastMedia = Media::where('object_id', $news->news_id)
                              ->where('object_name', 'news')
                              ->where('media_name_id', 'news_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $news->news_id,
                'name'          => 'news',
                'media_name_id' => 'news_image',
                'modified_by'   => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            if (isset($uploaded[0])) {
                $news->image = $uploaded[0]['path'];
                $news->save();
            }

            Event::fire('orbit.upload.postuploadnewsimage.after.save', array($this, $news, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'news_image';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.news.main');

            // Commit the changes
            if (! $this->calledFrom('news.new, news.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadnewsimage.after.commit', array($this, $news, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadnewsimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('news.new, news.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadnewsimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('news.new, news.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadnewsimage.query.error', array($this, $e));

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
            if (! $this->calledFrom('news.new, news.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadnewsimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('news.new, news.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadnewsimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete images for news.
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `news_id`                  (required) - ID of the news
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteNewsImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletenewsimage.before.auth', array($this));

            if (! $this->calledFrom('news.new, news.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletenewsimage.after.auth', array($this));

                // Try to check access control list, does this news allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletenewsimage.before.authz', array($this, $user));

/*
                if (! ACL::create($user)->isAllowed('update_news')) {
                    Event::fire('orbit.upload.postdeletenewsimage.authz.notallowed', array($this, $user));
                    $editNewsLang = Lang::get('validation.orbit.actionlist.update_news');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editNewsLang));
                    ACL::throwAccessForbidden($message);
                }
*/
                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.upload.postdeletenewsimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $news_id = OrbitInput::post('news_id');

            $validator = Validator::make(
                array(
                    'news_id'   => $news_id,
                ),
                array(
                    'news_id'   => 'required|orbit.empty.news',
                )
            );

            Event::fire('orbit.upload.postdeletenewsimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('news.new, news.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletenewsimage.after.validation', array($this, $validator));

            // We already had News instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $news = App::make('orbit.empty.news');

            // Delete old news image
            $pastMedia = Media::where('object_id', $news->news_id)
                              ->where('object_name', 'news')
                              ->where('media_name_id', 'news_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletenewsimage.before.save', array($this, $news));

            // Update the `image` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per news
            $news->image = NULL;
            $news->save();

            Event::fire('orbit.upload.postdeletenewsimage.after.save', array($this, $news));

            $this->response->data = $news;
            $this->response->message = Lang::get('statuses.orbit.uploaded.news.delete_image');

            if (! $this->calledFrom('news.new, news.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletenewsimage.after.commit', array($this, $news));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletenewsimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('news.new, news.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletenewsimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('news.new, news.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletenewsimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('news.new, news.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletenewsimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('news.new, news.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletenewsimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload image for a news tranlation (selected language).
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `news_id`                     (required) - ID of the news
     * @param integer    `news_translation_id`         (required) - ID of the news tranlation
     * @param integer    `merchant_language_id`         (required) - ID of the merchan language
     * @param file|array `image_translation`            (required) - News translation images
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadNewsTranslationImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadnewstranslationimage.before.auth', array($this));

            if (! $this->calledFrom('news.translations'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadnewstranslationimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadnewstranslationimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_news')) {
                    Event::fire('orbit.upload.postuploadnewstranslationimage.authz.notallowed', array($this, $user));
                    $editNewsLang = Lang::get('validation.orbit.actionlist.update_news');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editNewsLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadnewstranslationimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $news_translation_id = OrbitInput::post('news_translation_id');
            $news_id = OrbitInput::post('news_id');
            $merchant_language_id = OrbitInput::post('merchant_language_id');
            $image_translation = OrbitInput::files('image_translation_' . $merchant_language_id);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'news_translation_id'  => $news_translation_id,
                    'news_id'              => $news_id,
                    'merchant_language_id' => $merchant_language_id,
                    'image_translation'    => $image_translation,
                ),
                array(
                    'news_translation_id'  => 'required|orbit.empty.news_translation',
                    'news_id'              => 'required|orbit.empty.news',
                    'merchant_language_id' => 'required|orbit.empty.merchant_language',
                    'image_translation'    => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadnewstranslationimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('news.translations')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadnewstranslationimage.after.validation', array($this, $validator));

            // We already had Event Translation instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $news_translations = App::make('orbit.empty.news_translation');

            // Callback to rename the file, we will format it as follow
            // [PROMOTION_ID]-[PROMOTION_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($news_translations)
            {
                $news_translation_id = $news_translations->news_translation_id;
                $slug = Str::slug($news_translations->news_name);
                $file['new']->name = sprintf('%s-%s-%s', $news_translation_id, $slug, time());
            };

            // Load the orbit configuration for news upload
            $uploadNewsConfig = Config::get('orbit.upload.news.translation');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadNewsConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadnewstranslationimage.before.save', array($this, $news_translations, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($image_translation);

            // Delete old news translation image
            $pastMedia = Media::where('object_id', $news_translations->news_translation_id)
                              ->where('object_name', 'news_translation')
                              ->where('media_name_id', 'news_translation_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }


            // Save the files metadata
            $object = array(
                'id'            => $news_translations->news_translation_id,
                'name'          => 'news_translation',
                'media_name_id' => 'news_translation_image',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image_translation` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per news
            if (isset($uploaded[0])) {
                $news_translations->save();
            }

            Event::fire('orbit.upload.postuploadnewstranslationimage.after.save', array($this, $news_translations, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'news_translation_image';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.news_translation.main');

            if (! $this->calledFrom('news.translations')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadnewstranslationimage.after.commit', array($this, $news_translations, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadnewstranslationimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('news.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadnewstranslationimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('news.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadnewstranslationimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('news.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadnewstranslationimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('news.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadnewstranslationimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload image for a sign up desktop (selected language).
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `reward_id`                         (required) - ID of the news
     * @param integer    `reward_detail_translation_id`      (required) - ID of the news tranlation
     * @param integer    `language_id`                       (required) - ID of the merchan language
     * @param file|array `reward_signup_bg_desktop`          (required) - News translation images
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadSignUpDesktopBackground()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadsignupdesktopbackground.before.auth', array($this));

            if (! $this->calledFrom('reward.translations'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadsignupdesktopbackground.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadsignupdesktopbackground.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_news')) {
                    Event::fire('orbit.upload.postuploadsignupdesktopbackground.authz.notallowed', array($this, $user));
                    $editNewsLang = Lang::get('validation.orbit.actionlist.update_news');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editNewsLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadsignupdesktopbackground.after.authz', array($this, $user));
            }

            $upload_image_config = Config::get('orbit.upload.reward_detail.reward_signup_bg_desktop');
            $element_name = $upload_image_config['name'];

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $reward_detail_translation_id = OrbitInput::post('reward_detail_translation_id');
            $reward_detail_id = OrbitInput::post('reward_detail_id');
            $language_id = OrbitInput::post('language_id');
            $image_translation = OrbitInput::files($element_name . '_' . $language_id);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'reward_detail_translation_id' => $reward_detail_translation_id,
                    'reward_detail_id'             => $reward_detail_id,
                    'language_id'                  => $language_id,
                    $element_name                  => $image_translation,
                ),
                array(
                    'reward_detail_translation_id' => 'required|orbit.empty.reward_detail_translation',
                    'reward_detail_id'             => 'required|orbit.empty.reward_detail',
                    'language_id'                  => 'required|orbit.empty.merchant_language',
                    $element_name                  => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadsignupdesktopbackground.before.validation', array($this, $validator));

            if (! $this->calledFrom('reward.translations')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadsignupdesktopbackground.after.validation', array($this, $validator));

            // We already had Event Translation instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $reward_detail_translations = App::make('orbit.empty.reward_detail_translation');

            // Callback to rename the file, we will format it as follow
            // [PROMOTION_ID]-[PROMOTION_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($reward_detail_translations)
            {
                $reward_detail_translation_id = $reward_detail_translations->reward_detail_translation_id;
                $slug = Str::slug('signup_desktop');
                $file['new']->name = sprintf('%s-%s-%s', $reward_detail_translation_id, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($upload_image_config);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadsignupdesktopbackground.before.save', array($this, $reward_detail_translations, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($image_translation);

            // Delete old news translation image
            $pastMedia = Media::where('object_id', $reward_detail_translations->reward_detail_translation_id)
                              ->where('object_name', 'reward_detail')
                              ->where('media_name_id', $element_name);

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }


            // Save the files metadata
            $object = array(
                'id'            => $reward_detail_translations->reward_detail_translation_id,
                'name'          => 'reward_detail',
                'media_name_id' => $element_name,
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image_translation` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per news
            if (isset($uploaded[0])) {
                $reward_detail_translations->save();
            }

            Event::fire('orbit.upload.postuploadsignupdesktopbackground.after.save', array($this, $reward_detail_translations, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = $element_name;
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.news_translation.main');

            if (! $this->calledFrom('reward.translations')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadsignupdesktopbackground.after.commit', array($this, $reward_detail_translations, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadsignupdesktopbackground.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('reward.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadsignupdesktopbackground.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('reward.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadsignupdesktopbackground.query.error', array($this, $e));

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

            if (! $this->calledFrom('reward.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadsignupdesktopbackground.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('reward.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadsignupdesktopbackground.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload image for a sign up mobile (selected language).
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `reward_id`                         (required) - ID of the news
     * @param integer    `reward_detail_translation_id`      (required) - ID of the news tranlation
     * @param integer    `language_id`                       (required) - ID of the merchan language
     * @param file|array `reward_signup_bg_mobile` (required) - News translation images
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadSignUpMobileBackground()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadsignupmobilebackground.before.auth', array($this));

            if (! $this->calledFrom('reward.translations'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadsignupmobilebackground.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadsignupmobilebackground.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_news')) {
                    Event::fire('orbit.upload.postuploadsignupmobilebackground.authz.notallowed', array($this, $user));
                    $editNewsLang = Lang::get('validation.orbit.actionlist.update_news');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editNewsLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadsignupmobilebackground.after.authz', array($this, $user));
            }

            $upload_image_config = Config::get('orbit.upload.reward_detail.reward_signup_bg_mobile');
            $element_name = $upload_image_config['name'];

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $reward_detail_translation_id = OrbitInput::post('reward_detail_translation_id');
            $reward_detail_id = OrbitInput::post('reward_detail_id');
            $language_id = OrbitInput::post('language_id');
            $image_translation = OrbitInput::files($element_name . '_' . $language_id);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'reward_detail_translation_id' => $reward_detail_translation_id,
                    'reward_detail_id'             => $reward_detail_id,
                    'language_id'                  => $language_id,
                    $element_name                  => $image_translation,
                ),
                array(
                    'reward_detail_translation_id' => 'required|orbit.empty.reward_detail_translation',
                    'reward_detail_id'             => 'required|orbit.empty.reward_detail',
                    'language_id'                  => 'required|orbit.empty.merchant_language',
                    $element_name                  => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadsignupmobilebackground.before.validation', array($this, $validator));

            if (! $this->calledFrom('reward.translations')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadsignupmobilebackground.after.validation', array($this, $validator));

            // We already had Event Translation instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $reward_detail_translations = App::make('orbit.empty.reward_detail_translation');

            // Callback to rename the file, we will format it as follow
            // [PROMOTION_ID]-[PROMOTION_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($reward_detail_translations)
            {
                $reward_detail_translation_id = $reward_detail_translations->reward_detail_translation_id;
                $slug = Str::slug('signup_mobile');
                $file['new']->name = sprintf('%s-%s-%s', $reward_detail_translation_id, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($upload_image_config);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadsignupmobilebackground.before.save', array($this, $reward_detail_translations, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($image_translation);

            // Delete old news translation image
            $pastMedia = Media::where('object_id', $reward_detail_translations->reward_detail_translation_id)
                              ->where('object_name', 'reward_detail')
                              ->where('media_name_id', $element_name);

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }


            // Save the files metadata
            $object = array(
                'id'            => $reward_detail_translations->reward_detail_translation_id,
                'name'          => 'reward_detail',
                'media_name_id' => $element_name,
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image_translation` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per news
            if (isset($uploaded[0])) {
                $reward_detail_translations->save();
            }

            Event::fire('orbit.upload.postuploadsignupmobilebackground.after.save', array($this, $reward_detail_translations, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = $element_name;
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.news_translation.main');

            if (! $this->calledFrom('reward.translations')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadsignupmobilebackground.after.commit', array($this, $reward_detail_translations, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadsignupmobilebackground.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('reward.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadsignupmobilebackground.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('reward.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadsignupmobilebackground.query.error', array($this, $e));

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

            if (! $this->calledFrom('reward.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadsignupmobilebackground.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('reward.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadsignupmobilebackground.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload images for Lucky Draw.
     *
     * @author Tian <tian@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `lucky_draw_id`               (required) - ID of the lucky draw
     * @param file|array `images`                      (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadLuckyDrawImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadluckydrawimage.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadluckydrawimage.after.auth', array($this));

                // Try to check access control list, does this lucky draw allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadluckydrawimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_lucky_draw')) {
                    Event::fire('orbit.upload.postuploadluckydrawimage.authz.notallowed', array($this, $user));
                    $editLuckyDrawLang = Lang::get('validation.orbit.actionlist.update_lucky_draw');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editLuckyDrawLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadluckydrawimage.after.authz', array($this, $user));
            } else {
                // Comes from event
                $user = App::make('orbit.upload.user');
            }

            // Load the orbit configuration for lucky draw upload image
            $uploadImageConfig = Config::get('orbit.upload.lucky_draw.main');
            $elementName = $uploadImageConfig['name'];

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $lucky_draw_id = OrbitInput::post('lucky_draw_id');
            $images = OrbitInput::files($elementName);
            $messages = array(
                'nomore.than.three' => Lang::get('validation.max.array', array(
                    'max' => 3
                ))
            );

            $validator = Validator::make(
                array(
                    'lucky_draw_id' => $lucky_draw_id,
                    $elementName    => $images,
                ),
                array(
                    'lucky_draw_id' => 'required|orbit.empty.lucky_draw',
                    $elementName    => 'required|array|nomore.than.three',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadluckydrawimage.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadluckydrawimage.after.validation', array($this, $validator));

            // We already had LuckyDraw instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $luckydraw = App::make('orbit.empty.lucky_draw');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($luckydraw)
            {
                $lucky_draw_id = $luckydraw->lucky_draw_id;
                $slug = Str::slug($luckydraw->lucky_draw_name);
                $file['new']->name = sprintf('%s-%s-%s', $lucky_draw_id, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadImageConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadluckydrawimage.before.save', array($this, $luckydraw, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Delete old lucky draw image
            $pastMedia = Media::where('object_id', $luckydraw->lucky_draw_id)
                              ->where('object_name', 'lucky_draw')
                              ->where('media_name_id', 'lucky_draw_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $luckydraw->lucky_draw_id,
                'name'          => 'lucky_draw',
                'media_name_id' => 'lucky_draw_image',
                'modified_by'   => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            if (isset($uploaded[0])) {
                $luckydraw->image = $uploaded[0]['path'];
                $luckydraw->save();
            }

            Event::fire('orbit.upload.postuploadluckydrawimage.after.save', array($this, $luckydraw, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.lucky_draw.main');

            // Commit the changes
            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadluckydrawimage.after.commit', array($this, $luckydraw, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadluckydrawimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadluckydrawimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadluckydrawimage.query.error', array($this, $e));

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
            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadluckydrawimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadluckydrawimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete images for Lucky Draw.
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `lucky_draw_id`                  (required) - ID of the lucky draw
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteLuckyDrawImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeleteluckydrawimage.before.auth', array($this));

            if (! $this->calledFrom('luckydraw.new, luckydraw.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeleteluckydrawimage.after.auth', array($this));

                // Try to check access control list, does this lucky draw allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeleteluckydrawimage.before.authz', array($this, $user));

/*
                if (! ACL::create($user)->isAllowed('update_lucky_draw')) {
                    Event::fire('orbit.upload.postdeleteluckydrawimage.authz.notallowed', array($this, $user));
                    $editLuckyDrawLang = Lang::get('validation.orbit.actionlist.update_lucky_draw');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editLuckyDrawLang));
                    ACL::throwAccessForbidden($message);
                }
*/
                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.upload.postdeleteluckydrawimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $lucky_draw_id = OrbitInput::post('lucky_draw_id');

            $validator = Validator::make(
                array(
                    'lucky_draw_id'   => $lucky_draw_id,
                ),
                array(
                    'lucky_draw_id'   => 'required|orbit.empty.lucky_draw',
                )
            );

            Event::fire('orbit.upload.postdeleteluckydrawimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeleteluckydrawimage.after.validation', array($this, $validator));

            // We already had LuckyDraw instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $luckydraw = App::make('orbit.empty.lucky_draw');

            // Delete old lucky draw image
            $pastMedia = Media::where('object_id', $luckydraw->lucky_draw_id)
                              ->where('object_name', 'lucky_draw')
                              ->where('media_name_id', 'lucky_draw_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeleteluckydrawimage.before.save', array($this, $luckydraw));

            // Update the `image` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per lucky draw
            $luckydraw->image = NULL;
            $luckydraw->save();

            Event::fire('orbit.upload.postdeleteluckydrawimage.after.save', array($this, $luckydraw));

            $this->response->data = $luckydraw;
            $this->response->message = Lang::get('statuses.orbit.uploaded.lucky_draw.delete_image');

            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeleteluckydrawimage.after.commit', array($this, $luckydraw));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeleteluckydrawimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeleteluckydrawimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeleteluckydrawimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeleteluckydrawimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeleteluckydrawimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload image for a lucky draw translation (selected language).
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `lucky_draw_id`                (required) - ID of the lucky draw
     * @param integer    `lucky_draw_translation_id`    (required) - ID of the lucky draw translation
     * @param integer    `merchant_language_id`         (required) - ID of the merchant language
     * @param file|array `image_translation`            (required) - Translation images
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadLuckyDrawTranslationImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadluckydrawtranslationimage.before.auth', array($this));

            if (! $this->calledFrom('luckydraw.translations'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadluckydrawtranslationimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadluckydrawtranslationimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_lucky_draw')) {
                    Event::fire('orbit.upload.postuploadluckydrawtranslationimage.authz.notallowed', array($this, $user));
                    $editLuckyDrawLang = Lang::get('validation.orbit.actionlist.update_lucky_draw');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editLuckyDrawLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadluckydrawtranslationimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $lucky_draw_translation_id = OrbitInput::post('lucky_draw_translation_id');
            $lucky_draw_id = OrbitInput::post('lucky_draw_id');
            $merchant_language_id = OrbitInput::post('merchant_language_id');
            $image_translation = OrbitInput::files('image_translation_' . $merchant_language_id);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'lucky_draw_translation_id'  => $lucky_draw_translation_id,
                    'lucky_draw_id'              => $lucky_draw_id,
                    'merchant_language_id'       => $merchant_language_id,
                    'image_translation'          => $image_translation,
                ),
                array(
                    'lucky_draw_translation_id'  => 'required|orbit.empty.lucky_draw_translation',
                    'lucky_draw_id'              => 'required|orbit.empty.lucky_draw',
                    'merchant_language_id'       => 'required|orbit.empty.merchant_language_lucky_draw',
                    'image_translation'          => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadluckydrawtranslationimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('luckydraw.translations')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadluckydrawtranslationimage.after.validation', array($this, $validator));

            // We already had Coupon Translation instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $lucky_draw_translations = App::make('orbit.empty.lucky_draw_translation');

            // Delete old coupon translation image
            $pastMedia = Media::where('object_id', $lucky_draw_translations->lucky_draw_translation_id)
                              ->where('object_name', 'lucky_draw_translation')
                              ->where('media_name_id', 'lucky_draw_translation_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Callback to rename the file, we will format it as follow
            // [LUCKY_DRAW_ID]-[LUCKY_DRAW_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($lucky_draw_translations)
            {
                $lucky_draw_translation_id = $lucky_draw_translations->lucky_draw_translation_id;
                $slug = Str::slug($lucky_draw_translations->lucky_draw_name);
                $file['new']->name = sprintf('%s-%s-%s', $lucky_draw_translation_id, $slug, time());
            };

            // Load the orbit configuration for event upload
            $uploadLuckyDrawConfig = Config::get('orbit.upload.lucky_draw.translation');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadLuckyDrawConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadluckydrawtranslationimage.before.save', array($this, $lucky_draw_translations, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($image_translation);

            // Save the files metadata
            $object = array(
                'id'            => $lucky_draw_translations->lucky_draw_translation_id,
                'name'          => 'lucky_draw_translation',
                'media_name_id' => 'lucky_draw_translation_image',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image_translation` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per event
            if (isset($uploaded[0])) {
                $lucky_draw_translations->save();
            }

            Event::fire('orbit.upload.postuploadluckydrawtranslationimage.after.save', array($this, $lucky_draw_translations, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.lucky_draw_translation.main');

            if (! $this->calledFrom('luckydraw.translations')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadluckydrawtranslationimage.after.commit', array($this, $lucky_draw_translations, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadluckydrawtranslationimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadluckydrawtranslationimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadluckydrawtranslationimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadluckydrawtranslationimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadluckydrawtranslationimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload images for Lucky Draw Announcement.
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `lucky_draw_announcement_id`               (required) - ID of the lucky draw announcement
     * @param file|array `images`                      (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadLuckyDrawAnnouncementImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadluckydrawannouncementimage.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('luckydrawannouncement.new, luckydrawannouncement.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadluckydrawannouncementimage.after.auth', array($this));

                // Try to check access control list, does this lucky draw allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadluckydrawannouncementimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_lucky_draw')) {
                    Event::fire('orbit.upload.postuploadluckydrawannouncementimage.authz.notallowed', array($this, $user));
                    $editLuckyDrawLang = Lang::get('validation.orbit.actionlist.update_lucky_draw');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editLuckyDrawLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadluckydrawannouncementimage.after.authz', array($this, $user));
            } else {
                // Comes from event
                $user = App::make('orbit.upload.user');
            }

            // Load the orbit configuration for lucky draw upload image
            $uploadImageConfig = Config::get('orbit.upload.lucky_draw.main');
            $elementName = $uploadImageConfig['name'];

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $lucky_draw_announcement_id = OrbitInput::post('lucky_draw_announcement_id');
            $images = OrbitInput::files($elementName);
            $messages = array(
                'nomore.than.three' => Lang::get('validation.max.array', array(
                    'max' => 3
                ))
            );

            $validator = Validator::make(
                array(
                    'lucky_draw_announcement_id' => $lucky_draw_announcement_id,
                    $elementName                 => $images,
                ),
                array(
                    'lucky_draw_announcement_id' => 'required|orbit.empty.lucky_draw_announcement',
                    $elementName                 => 'required|array|nomore.than.three',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadluckydrawannouncementimage.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('luckydrawannouncement.new, luckydrawannouncement.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadluckydrawannouncementimage.after.validation', array($this, $validator));

            // We already had LuckyDraw instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $luckydrawannouncement = App::make('orbit.empty.lucky_draw_announcement');

            // Delete old lucky draw image
            $pastMedia = Media::where('object_id', $luckydrawannouncement->lucky_draw_announcement_id)
                              ->where('object_name', 'lucky_draw_announcement')
                              ->where('media_name_id', 'lucky_draw_announcement_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($luckydrawannouncement)
            {
                $lucky_draw_id = $luckydrawannouncement->lucky_draw_announcement_id;
                $slug = Str::slug($luckydrawannouncement->title);
                $file['new']->name = sprintf('%s-%s-%s', $lucky_draw_announcement_id, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadImageConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadluckydrawannouncementimage.before.save', array($this, $luckydrawannouncement, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Save the files metadata
            $object = array(
                'id'            => $luckydrawannouncement->lucky_draw_announcement_id,
                'name'          => 'lucky_draw_announcement',
                'media_name_id' => 'lucky_draw_announcement_image',
                'modified_by'   => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            // if (isset($uploaded[0])) {
            //     $luckydrawannouncement->save();
            // }

            Event::fire('orbit.upload.postuploadluckydrawannouncementimage.after.save', array($this, $luckydrawannouncement, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.lucky_draw.announcement');

            // Commit the changes
            if (! $this->calledFrom('luckydrawannouncement.new, luckydrawannouncement.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadluckydrawannouncementimage.after.commit', array($this, $luckydraw, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadluckydrawannouncementimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadluckydrawannouncementimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadluckydrawannouncementimage.query.error', array($this, $e));

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
            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadluckydrawannouncementimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadluckydrawannouncementimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload image for a lucky draw translation (selected language).
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `lucky_draw_id`                (required) - ID of the lucky draw
     * @param integer    `lucky_draw_translation_id`    (required) - ID of the lucky draw translation
     * @param integer    `merchant_language_id`         (required) - ID of the merchant language
     * @param file|array `image_translation`            (required) - Translation images
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadLuckyDrawAnnouncementTranslationImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadluckydrawannouncementtranslationimage.before.auth', array($this));

            if (! $this->calledFrom('luckydrawannouncement.translations'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadluckydrawannouncementtranslationimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadluckydrawannouncementtranslationimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_lucky_draw')) {
                    Event::fire('orbit.upload.postuploadluckydrawannouncementtranslationimage.authz.notallowed', array($this, $user));
                    $editLuckyDrawLang = Lang::get('validation.orbit.actionlist.update_lucky_draw');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editLuckyDrawLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadluckydrawannouncementtranslationimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $lucky_draw_announcement_translation_id = OrbitInput::post('lucky_draw_announcement_translation_id');
            $lucky_draw_announcement_id = OrbitInput::post('lucky_draw_announcement_id');
            $merchant_language_id = OrbitInput::post('merchant_language_id');
            $image_translation = OrbitInput::files('image_translation_' . $merchant_language_id);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'lucky_draw_announcement_translation_id'  => $lucky_draw_announcement_translation_id,
                    'lucky_draw_announcement_id'              => $lucky_draw_announcement_id,
                    'merchant_language_id'                    => $merchant_language_id,
                    'image_translation'                       => $image_translation,
                ),
                array(
                    'lucky_draw_announcement_translation_id'  => 'required|orbit.empty.lucky_draw_announcement_translation',
                    'lucky_draw_announcement_id'              => 'required|orbit.empty.lucky_draw_announcement',
                    'merchant_language_id'                    => 'required|orbit.empty.merchant_language_lucky_draw',
                    'image_translation'                       => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadluckydrawannouncementtranslationimage.before.validation', array($this, $validator));
            if (! $this->calledFrom('luckydrawannouncement.translations')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadluckydrawannouncementtranslationimage.after.validation', array($this, $validator));

            // We already had Coupon Translation instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $lucky_draw_announcement_translations = App::make('orbit.empty.lucky_draw_announcement_translation');

            // Delete old coupon translation image
            $pastMedia = Media::where('object_id', $lucky_draw_announcement_translations->lucky_draw_announcement_translation_id)
                              ->where('object_name', 'lucky_draw_announcement_translation')
                              ->where('media_name_id', 'lucky_draw_announcement_translation_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Callback to rename the file, we will format it as follow
            // [LUCKY_DRAW_ID]-[LUCKY_DRAW_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($lucky_draw_announcement_translations)
            {
                $lucky_draw_announcement_translation_id = $lucky_draw_announcement_translations->lucky_draw_announcement_translation_id;
                $slug = Str::slug($lucky_draw_announcement_translations->title);
                $file['new']->name = sprintf('%s-%s-%s', $lucky_draw_announcement_translation_id, $slug, time());
            };

            // Load the orbit configuration for event upload
            $uploadLuckyDrawConfig = Config::get('orbit.upload.lucky_draw.announcement_translation');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadLuckyDrawConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadluckydrawannouncementtranslationimage.before.save', array($this, $lucky_draw_announcement_translations, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($image_translation);

            // Save the files metadata
            $object = array(
                'id'            => $lucky_draw_announcement_translations->lucky_draw_announcement_translation_id,
                'name'          => 'lucky_draw_announcement_translation',
                'media_name_id' => 'lucky_draw_announcement_translation_image',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image_translation` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per event
            if (isset($uploaded[0])) {
                $lucky_draw_announcement_translations->save();
            }

            Event::fire('orbit.upload.postuploadluckydrawannouncementtranslationimage.after.save', array($this, $lucky_draw_announcement_translations, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.lucky_draw_translation.main');

            if (! $this->calledFrom('luckydrawannouncement.translations')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadluckydrawannouncementtranslationimage.after.commit', array($this, $lucky_draw_announcement_translations, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadluckydrawannouncementtranslationimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadluckydrawannouncementtranslationimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadluckydrawannouncementtranslationimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadluckydrawannouncementtranslationimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('coupon.translations')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadluckydrawannouncementtranslationimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload images for Membership.
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `membership_id`               (required) - Membership ID
     * @param file|array `images`                      (required) - Image files
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadMembershipImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadmembershipimage.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('membership.new, membership.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadmembershipimage.after.auth', array($this));

                // Try to check access control list, does this membership allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadmembershipimage.before.authz', array($this, $user));

/*
                if (! ACL::create($user)->isAllowed('update_membership')) {
                    Event::fire('orbit.upload.postuploadmembershipimage.authz.notallowed', array($this, $user));
                    $editMembershipLang = Lang::get('validation.orbit.actionlist.update_membership');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMembershipLang));
                    ACL::throwAccessForbidden($message);
                }
*/
                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.upload.postuploadmembershipimage.after.authz', array($this, $user));
            } else {
                // Comes from event
                $user = App::make('orbit.upload.user');
            }

            // Load the orbit configuration for membership upload image
            $uploadImageConfig = Config::get('orbit.upload.membership.main');
            $elementName = $uploadImageConfig['name'];

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $membership_id = OrbitInput::post('membership_id');
            $images = OrbitInput::files($elementName);

            $messages = array(
                'nomore.than.three' => Lang::get('validation.max.array', array(
                    'max' => 3
                ))
            );

            $validator = Validator::make(
                array(
                    'membership_id'        => $membership_id,
                    $elementName           => $images,
                ),
                array(
                    'membership_id'        => 'required|orbit.empty.membership',
                    $elementName           => 'required|array|nomore.than.three',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadmembershipimage.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('membership.new, membership.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadmembershipimage.after.validation', array($this, $validator));

            // We already had Membership instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $membership = App::make('orbit.empty.membership');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($membership)
            {
                $membership_id = $membership->membership_id;
                $slug = Str::slug($membership->membership_name);
                $file['new']->name = sprintf('%s-%s-%s', $membership_id, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadImageConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadmembershipimage.before.save', array($this, $membership, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Delete old membership image
            $pastMedia = Media::where('object_id', $membership->membership_id)
                              ->where('object_name', 'membership')
                              ->where('media_name_id', 'membership_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $membership->membership_id,
                'name'          => 'membership',
                'media_name_id' => 'membership_image',
                'modified_by'   => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            Event::fire('orbit.upload.postuploadmembershipimage.after.save', array($this, $membership, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.membership.main');

            // Commit the changes
            if (! $this->calledFrom('membership.new, membership.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadmembershipimage.after.commit', array($this, $membership, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadmembershipimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('membership.new, membership.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadmembershipimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('membership.new, membership.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadmembershipimage.query.error', array($this, $e));

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
            if (! $this->calledFrom('membership.new, membership.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadmembershipimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('membership.new, membership.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadmembershipimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete images for membership.
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `membership_id`                  (required) - ID of the membership
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteMembershipImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletemembershipimage.before.auth', array($this));

            if (! $this->calledFrom('membership.new, membership.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletemembershipimage.after.auth', array($this));

                // Try to check access control list, does this membership allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletemembershipimage.before.authz', array($this, $user));

/*
                if (! ACL::create($user)->isAllowed('update_membership')) {
                    Event::fire('orbit.upload.postdeletemembershipimage.authz.notallowed', array($this, $user));
                    $editMembershipLang = Lang::get('validation.orbit.actionlist.update_membership');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMembershipLang));
                    ACL::throwAccessForbidden($message);
                }
*/
                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.upload.postdeletemembershipimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $membership_id = OrbitInput::post('membership_id');

            $validator = Validator::make(
                array(
                    'membership_id'   => $membership_id,
                ),
                array(
                    'membership_id'   => 'required|orbit.empty.membership',
                )
            );

            Event::fire('orbit.upload.postdeletemembershipimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('membership.new, membership.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletemembershipimage.after.validation', array($this, $validator));

            // We already had Membership instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $membership = App::make('orbit.empty.membership');

            // Delete old membership image
            $pastMedia = Media::where('object_id', $membership->membership_id)
                              ->where('object_name', 'membership')
                              ->where('media_name_id', 'membership_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletemembershipimage.before.save', array($this, $membership));

            Event::fire('orbit.upload.postdeletemembershipimage.after.save', array($this, $membership));

            $membership->load('media');

            $this->response->data = $membership;
            $this->response->message = Lang::get('statuses.orbit.uploaded.membership.delete_image');

            if (! $this->calledFrom('membership.new, membership.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletemembershipimage.after.commit', array($this, $membership));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletemembershipimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('membership.new, membership.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletemembershipimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('membership.new, membership.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletemembershipimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('membership.new, membership.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletemembershipimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('membership.new, membership.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletemembershipimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload logo for Mall Group.
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param char    `merchant_id`                 (required) - ID of the merchant
     * @param file|array `images`                   (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadMallGroupLogo()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadmallgrouplogo.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('mallgroup.new, mallgroup.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadmalllogo.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadmallgrouplogo.before.authz', array($this, $user));

/*
                if (! ACL::create($user)->isAllowed('update_retailer')) {
                    Event::fire('orbit.upload.postuploadmalllogo.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_retailer');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
*/
                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.upload.postuploadmallgrouplogo.after.authz', array($this, $user));
            } else {
                $user = App::make('orbit.upload.user');
            }

            // Load the orbit configuration for merchant upload logo
            $uploadLogoConfig = Config::get('orbit.upload.mallgroup.logo');
            $elementName = $uploadLogoConfig['name'];

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');
            $images = OrbitInput::files($elementName);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    $elementName  => $images,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.mallgroup',
                    $elementName    => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadmallgrouplogo.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('mallgroup.new, mallgroup.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadmallgrouplogo.after.validation', array($this, $validator));

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.mallgroup');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'mallgroup')
                              ->where('media_name_id', 'mallgroup_logo');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($merchant)
            {
                $merchant_id = $merchant->merchant_id;
                $slug = Str::slug($merchant->name);
                $file['new']->name = sprintf('%s-%s-%s', $merchant_id, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadLogoConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadmallgrouplogo.before.save', array($this, $merchant, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Save the files metadata
            $object = array(
                'id'            => $merchant->merchant_id,
                'name'          => 'mallgroup',
                'media_name_id' => 'mallgroup_logo',
                'modified_by'   => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            if (isset($uploaded[0])) {
                $merchant->logo = $uploaded[0]['path'];
                $merchant->save();
            }

            Event::fire('orbit.upload.postuploadmallgrouplogo.after.save', array($this, $merchant, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.mallgroup.logo');

            // Commit the changes
            if (! $this->calledFrom('mallgroup.new, mallgroup.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadmallgrouplogo.after.commit', array($this, $merchant, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadmallgrouplogo.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('mallgroup.new, mallgroup.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadmallgrouplogo.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('mallgroup.new, mallgroup.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadmallgrouplogo.query.error', array($this, $e));

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
            if (! $this->calledFrom('mallgroup.new, mallgroup.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadmallgrouplogo.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('mallgroup.new, mallgroup.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadmallgrouplogo.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete logo for a mall group.
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param char    `merchant_id`                  (required) - ID of the mall group
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteMallGroupLogo()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletemallgrouplogo.before.auth', array($this));

            if (! $this->calledFrom('mallgroup.new, mallgroup.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletemallgrouplogo.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletemallgrouplogo.before.authz', array($this, $user));
/*
                if (! ACL::create($user)->isAllowed('update_mall')) {
                    Event::fire('orbit.upload.postdeletemalllogo.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_retailer');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
*/
                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.upload.postdeletemallgrouplogo.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');

            $validator = Validator::make(
                array(
                    'merchant_id'   => $merchant_id,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.mallgroup',
                )
            );

            Event::fire('orbit.upload.postdeletemallgrouplogo.before.validation', array($this, $validator));

            if (! $this->calledFrom('mallgroup.new, mallgroup.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletemallgrouplogo.after.validation', array($this, $validator));

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.mallgroup');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'mallgroup')
                              ->where('media_name_id', 'mallgroup_logo');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletemallgrouplogo.before.save', array($this, $merchant));

            // Update the `logo` field which store the original path of the logo
            // This is temporary since right now the business rules actually
            // only allows one logo per merchant
            $merchant->logo = NULL;
            $merchant->save();

            Event::fire('orbit.upload.postdeletemallgrouplogo.after.save', array($this, $merchant));

            $this->response->data = $merchant;
            $this->response->message = Lang::get('statuses.orbit.uploaded.mallgroup.delete_logo');

            if (! $this->calledFrom('mallgroup.new, mallgroup.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletemallgrouplogo.after.commit', array($this, $merchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletemallgrouplogo.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('mallgroup.new, mallgroup.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletemallgrouplogo.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('mallgroup.new, mallgroup.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletemallgrouplogo.query.error', array($this, $e));

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

            if (! $this->calledFrom('mallgroup.new, mallgroup.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletemallgrouplogo.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('mallgroup.new, mallgroup.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletemallgrouplogo.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload map for Mall.
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - ID of the merchant
     * @param file|array `pictures`                    (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadMallMap()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadmallmap.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadmallmap.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadmallmap.before.authz', array($this, $user));

/*
                if (! ACL::create($user)->isAllowed('update_retailer')) {
                    Event::fire('orbit.upload.postuploadmallmap.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_retailer');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
*/
                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.upload.postuploadmallmap.after.authz', array($this, $user));
            } else {
                // Comes from event
                $user = App::make('orbit.upload.user');
            }

            // Load the orbit configuration for merchant upload logo
            $uploadMapConfig = Config::get('orbit.upload.mall.map');
            $elementName = $uploadMapConfig['name'];

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');
            $images = OrbitInput::files($elementName);
            $messages = array(
                'nomore.than.three' => Lang::get('validation.max.array', array(
                    'max' => 3
                ))
            );

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    $elementName  => $images,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.mall',
                    $elementName    => 'required|array|nomore.than.three',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadmallmap.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadmallmap.after.validation', array($this, $validator));

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.mall');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($merchant)
            {
                $merchant_id = $merchant->merchant_id;
                $slug = Str::slug($merchant->name);
                $file['new']->name = sprintf('%s-%s-%s', $merchant_id, $slug, time());
            };

            $custom_message = array(
                    'errors' => array(
                        'file_too_big'          => 'Mall Map size is too big, maximum size allowed is :size :unit.',
                    ),
                );

            $message = new UploaderMessage($custom_message);
            $config = new UploaderConfig($uploadMapConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadmallmap.before.save', array($this, $merchant, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'mall')
                              ->where('media_name_id', 'mall_map');

            // Get the index of the image to delete the right one
            $increment = 0;
            $imgOrder = array_keys($images['name']);

            $pastMedia->where(function($q) use ($increment, $imgOrder) {
                foreach ($imgOrder as $indexOrder) {
                    $q->orWhere('metadata', 'order-' . $indexOrder);
                }
            });

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $merchant->merchant_id,
                'name'          => 'mall',
                'media_name_id' => 'mall_map',
                'modified_by'   => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            if (isset($uploaded[0])) {
                $merchant->logo = $uploaded[0]['path'];
                $merchant->save();
            }

            Event::fire('orbit.upload.postuploadmallmap.after.save', array($this, $merchant, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'mall_map';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.mall.map');

            // Commit the changes
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadmallmap.after.commit', array($this, $merchant, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadmallmap.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadmallmap.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadmallmap.query.error', array($this, $e));

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
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadmallmap.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadmallmap.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete map for a mall.
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                  (required) - ID of the merchant/retailer
     * @param integer    `picture_index`                (required) - Index of the picture
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteMallMap()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletemallmap.before.auth', array($this));

            if (! $this->calledFrom('mall.new, mall.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletemallmap.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletemallmap.before.authz', array($this, $user));

/*
                if (! ACL::create($user)->isAllowed('update_mall')) {
                    Event::fire('orbit.upload.postdeletemallmap.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_retailer');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
*/
                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.upload.postdeletemallmap.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');
            $delete_index = OrbitInput::post('delete_index');

            $validator = Validator::make(
                array(
                    'merchant_id'   => $merchant_id,
                    'delete_index' => $delete_index,
                ),
                array(
                    'merchant_id'   => 'required|orbit.empty.mall',
                    'delete_index' => 'array',
                )
            );

            Event::fire('orbit.upload.postdeletemallmap.before.validation', array($this, $validator));

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletemallmap.after.validation', array($this, $validator));

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.mall');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'mall')
                              ->where('media_name_id', 'mall_map');

            if (! empty($delete_index)) {
                $pastMedia->where(function($q) use ($delete_index) {
                    foreach ($delete_index as $indexOrder) {
                        $q->orWhere('metadata', 'order-' . $indexOrder);
                    }
                });
            }

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletemallmap.before.save', array($this, $merchant));

            // Update the `logo` field which store the original path of the logo
            // This is temporary since right now the business rules actually
            // only allows one logo per merchant
            $merchant->logo = NULL;
            $merchant->save();

            Event::fire('orbit.upload.postdeletemallmap.after.save', array($this, $merchant));

            // queue for data amazon s3
            $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);

            if ($usingCdn) {
                $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');
                $queueName = Config::get('orbit.cdn.queue_name', 'cdn_upload');

                Queue::push('Orbit\\Queue\\CdnUpload\\CdnUploadDeleteQueue', [
                    'object_id'     => $merchant_id,
                    'media_name_id' => 'mall_map',
                    'old_path'      => $oldPath,
                    'bucket_name'   => $bucketName
                ], $queueName);
            }

            $this->response->data = $merchant;
            $this->response->message = Lang::get('statuses.orbit.uploaded.retailer.delete_image');

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletemallmap.after.commit', array($this, $merchant));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletemallmap.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletemallmap.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletemallmap.query.error', array($this, $e));

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

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletemallmap.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletemallmap.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload images for Advert.
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `advert_id`                   (required) - ID of the advert
     * @param file|array `images`                      (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadAdvertImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadadvertimage.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('advert.new, advert.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadadvertimage.after.auth', array($this));

                // Try to check access control list, does this advert allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadadvertimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('update_advert')) {
                    Event::fire('orbit.upload.postuploadadvertimage.authz.notallowed', array($this, $user));
                    $editNewsLang = Lang::get('validation.orbit.actionlist.update_advert');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editNewsLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadadvertimage.after.authz', array($this, $user));
            } else {
                // Comes from event
                $user = App::make('orbit.upload.user');
            }

            // Load the orbit configuration for advert upload image
            $uploadImageConfig = Config::get('orbit.upload.advert.main');
            $elementName = $uploadImageConfig['name'];

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $advert_id = OrbitInput::post('advert_id');
            $images = OrbitInput::files($elementName);

            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'advert_id'  => $advert_id,
                    $elementName => $images,
                ),
                array(
                    'advert_id'  => 'required|orbit.empty.advert_id',
                    $elementName => 'required|array|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadadvertimage.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('advert.new, advert.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadadvertimage.after.validation', array($this, $validator));

            // We already had News instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $advert = App::make('orbit.empty.advert_id');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($advert)
            {
                $advert_id = $advert->advert_id;
                $slug = Str::slug($advert->advert_name);
                $file['new']->name = sprintf('%s-%s-%s', $advert_id, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadImageConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadadvertimage.before.save', array($this, $advert, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Delete old advert image
            $pastMedia = Media::where('object_id', $advert->advert_id)
                              ->where('object_name', 'advert')
                              ->where('media_name_id', 'advert_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $advert->advert_id,
                'name'          => 'advert',
                'media_name_id' => 'advert_image',
                'modified_by'   => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            Event::fire('orbit.upload.postuploadadvertimage.after.save', array($this, $advert, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'advert_image';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.advert.main');

            // Commit the changes
            if (! $this->calledFrom('advert.new, advert.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadadvertimage.after.commit', array($this, $advert, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadadvertimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('advert.new, advert.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadadvertimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('advert.new, advert.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadadvertimage.query.error', array($this, $e));

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
            if (! $this->calledFrom('advert.new, advert.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadadvertimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('advert.new, advert.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadadvertimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete images for Advert.
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `advert_id`                  (required) - ID of the advert
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteAdvertImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeleteadvertimage.before.auth', array($this));

            if (! $this->calledFrom('advert.new, advert.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeleteadvertimage.after.auth', array($this));

                // Try to check access control list, does this advert allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeleteadvertimage.before.authz', array($this, $user));

                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.upload.postdeleteadvertimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $advert_id = OrbitInput::post('advert_id');

            $validator = Validator::make(
                array(
                    'advert_id'   => $advert_id,
                ),
                array(
                    'advert_id'   => 'required|orbit.empty.advert_id',
                )
            );

            Event::fire('orbit.upload.postdeleteadvertimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('advert.new, advert.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeleteadvertimage.after.validation', array($this, $validator));

            // We already had News instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $advert = App::make('orbit.empty.advert_id');

            // Delete old advert image
            $pastMedia = Media::where('object_id', $advert->advert_id)
                              ->where('object_name', 'advert')
                              ->where('media_name_id', 'advert_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeleteadvertimage.before.save', array($this, $advert));

            // Update the `image` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per advert
            $advert->image = NULL;
            $advert->save();

            Event::fire('orbit.upload.postdeleteadvertimage.after.save', array($this, $advert));

            $this->response->data = $advert;
            $this->response->message = Lang::get('statuses.orbit.uploaded.advert.delete_image');

            if (! $this->calledFrom('advert.new, advert.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeleteadvertimage.after.commit', array($this, $advert));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeleteadvertimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('advert.new, advert.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeleteadvertimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('advert.new, advert.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeleteadvertimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('advert.new, advert.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeleteadvertimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('advert.new, advert.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeleteadvertimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload logo for Partner.
     *
     * @author kadek<kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `partner_id`                 (required) - ID of the partner
     * @param file|array `images`                      (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadPartnerLogo()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadpartnerlogo.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadpartnerlogo.after.auth', array($this));

                // Try to check access control list, does this parent allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadpartnerlogo.before.authz', array($this, $user));

                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadpartnerlogo.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $partner_id = OrbitInput::post('partner_id');
            $logo = OrbitInput::files('logo');
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'partner_id' => $partner_id,
                    'logo'       => $logo,
                ),
                array(
                    'partner_id' => 'required|orbit.empty.partner',
                    'logo'       => 'required|nomore.than.one',
                )
            );

            Event::fire('orbit.upload.postuploadpartnerlogo.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadpartnerlogo.after.validation', array($this, $validator));

            // We already had partner instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $partner = App::make('orbit.empty.partner_id');

            // Callback to rename the file, we will format it as follow
            // [PARTNER_ID]-[PARTNER_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($partner)
            {
                $partner_id = $partner->partner_id;
                $slug = Str::slug($partner->partner_name);
                $file['new']->name = sprintf('%s-%s-%s', $partner_id, $slug, time());
            };

            // Load the orbit configuration for partner upload logo
            $uploadLogoConfig = Config::get('orbit.upload.partner.logo');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadLogoConfig);
            $config->setConfig('before_saving', $renameFile);
            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadpartnerlogo.before.save', array($this, $partner, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($logo);

            // Delete old partner logo
            $pastMedia = Media::where('object_id', $partner->partner_id)
                              ->where('object_name', 'partner')
                              ->where('media_name_id', 'partner_logo');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $partner->partner_id,
                'name'          => 'partner',
                'media_name_id' => 'partner_logo',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            Event::fire('orbit.upload.postuploadpartnerlogo.after.save', array($this, $partner, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'partner_logo';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.partner.logo');

            // Commit the changes
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadpartnerlogo.after.commit', array($this, $partner, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadpartnerlogo.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadpartnerlogo.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadpartnerlogo.query.error', array($this, $e));

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
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadpartnerlogo.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = [$e->getMessage(), $e->getFile(), $e->getLine()];
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadpartnerlogo.before.render', array($this, $output));

        return $output;
    }


    /**
     * Upload image for Partner.
     *
     * @author kadek<kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `partner_id`   (required) - ID of the partner
     * @param file|array `images`       (required) - Image
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadPartnerImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadpartnerimage.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadpartnerimage.after.auth', array($this));

                // Try to check access control list, does this parent allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadpartnerimage.before.authz', array($this, $user));

                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadpartnerimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $partner_id = OrbitInput::post('partner_id');
            $image = OrbitInput::files('image');
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'partner_id' => $partner_id,
                    'image'      => $image,
                ),
                array(
                    'partner_id' => 'required|orbit.empty.partner',
                    'image'      => 'required|nomore.than.one',
                )
            );

            Event::fire('orbit.upload.postuploadpartnerimage.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadpartnerimage.after.validation', array($this, $validator));

            // We already had partner instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $partner = App::make('orbit.empty.partner_id');

            // Callback to rename the file, we will format it as follow
            // [PARTNER_ID]-[PARTNER_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($partner)
            {
                $partner_id = $partner->partner_id;
                $slug = Str::slug($partner->partner_name);
                $file['new']->name = sprintf('%s-%s-%s', $partner_id, $slug, time());
            };

            // Load the orbit configuration for partner upload image
            $uploadimageConfig = Config::get('orbit.upload.partner.image');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadimageConfig);
            $config->setConfig('before_saving', $renameFile);
            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadpartnerimage.before.save', array($this, $partner, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($image);

            // Delete old partner image
            $pastMedia = Media::where('object_id', $partner->partner_id)
                              ->where('object_name', 'partner')
                              ->where('media_name_id', 'partner_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $partner->partner_id,
                'name'          => 'partner',
                'media_name_id' => 'partner_image',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            Event::fire('orbit.upload.postuploadpartnerimage.after.save', array($this, $partner, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'partner_image';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.partner.main');

            // Commit the changes
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadpartnerimage.after.commit', array($this, $partner, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadpartnerimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadpartnerimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadpartnerimage.query.error', array($this, $e));

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
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadpartnerimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = [$e->getMessage(), $e->getFile(), $e->getLine()];
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadpartnerimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload banner for Partner.
     *
     * @author Budi <budi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `partner_banner_id`   (required) - ID of the partner
     * @param file|array `images`       (required) - Image
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadPartnerBanner()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadpartnerimage.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('partner.new, partner.update, partnerbanner.new')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadpartnerimage.after.auth', array($this));

                // Try to check access control list, does this parent allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadpartnerimage.before.authz', array($this, $user));

                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadpartnerimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $partner_banner_id = OrbitInput::post('partner_banner_id');
            $partnerName = OrbitInput::post('partner_name');
            $bannerIndex = OrbitInput::post('banner_index');
            $image = OrbitInput::files("banners_image_{$bannerIndex}");
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'partner_banner_id' => $partner_banner_id,
                    'banner'      => $image,
                ),
                array(
                    'partner_banner_id' => 'required|orbit.empty.partner_banner',
                    'banner'      => 'required',
                )
            );

            Event::fire('orbit.upload.postuploadpartnerimage.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('partner.new, partner.update, partnerbanner.new')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadpartnerimage.after.validation', array($this, $validator));

            // We already had partner instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $partnerBanner = App::make('orbit.empty.partner_banner_id');

            // Callback to rename the file, we will format it as follow
            // [PARTNER_banner_ID]-[PARTNER_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($partnerBanner, $partnerName)
            {
                $partner_banner_id = $partnerBanner->partner_banner_id;
                $slug = Str::slug($partnerName);
                $file['new']->name = sprintf('%s-%s-%s', $partner_banner_id, $slug, time());
            };

            // Load the orbit configuration for partner upload image
            $uploadimageConfig = Config::get('orbit.upload.partner.banner');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadimageConfig);
            $config->setConfig('before_saving', $renameFile);
            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadpartnerimage.before.save', array($this, $partnerBanner, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($image);

            // Delete old partner banner
            $pastMedia = Media::where('object_id', $partner_banner_id)
                              ->where('object_name', 'partner_banner')
                              ->where('media_name_id', 'partner_banner');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $partnerBanner->partner_banner_id,
                'name'          => 'partner_banner',
                'media_name_id' => 'partner_banner',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            Event::fire('orbit.upload.postuploadpartnerimage.after.save', array($this, $partnerBanner, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'partner_banner';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.partner.main');

            // Commit the changes
            if (! $this->calledFrom('partner.new, partner.update, partnerbanner.new')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadpartnerimage.after.commit', array($this, $partnerBanner, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadpartnerimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadpartnerimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadpartnerimage.query.error', array($this, $e));

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
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadpartnerimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = [$e->getMessage(), $e->getFile(), $e->getLine()];
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('partner.new, partner.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadpartnerimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete logo for partner.
     *
     * @author kadek<kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param char    `partner_id`      (required) - ID of the partner
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeletePartnerLogo()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletepartnerlogo.before.auth', array($this));

            if (! $this->calledFrom('partner.new, partner.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletepartnerlogo.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletepartnerlogo.before.authz', array($this, $user));

                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.upload.postdeletepartnerlogo.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $partner_id = OrbitInput::post('partner_id');

            $validator = Validator::make(
                array(
                    'partner_id'   => $partner_id,
                ),
                array(
                    'partner_id'   => 'required|orbit.empty.partner',
                )
            );

            Event::fire('orbit.upload.postdeletepartnerlogo.before.validation', array($this, $validator));

            if (! $this->calledFrom('partner.new, partner.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletepartnerlogo.after.validation', array($this, $validator));

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $partner = App::make('orbit.empty.partner_id');
            $partner->touch();

            // Delete old partner logo
            $pastMedia = Media::where('object_id', $partner->partner_id)
                              ->where('object_name', 'partner')
                              ->where('media_name_id', 'partner_logo');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletepartnerlogo.before.save', array($this, $partner));

            Event::fire('orbit.upload.postdeletepartnerlogo.after.save', array($this, $partner));

            $this->response->data = $partner;
            $this->response->message = Lang::get('statuses.orbit.uploaded.partner.delete_logo');

            if (! $this->calledFrom('partner.new, partner.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletepartnerlogo.after.commit', array($this, $partner));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletepartnerlogo.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('partner.new, partner.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletepartnerlogo.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('partner.new, partner.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletepartnerlogo.query.error', array($this, $e));

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

            if (! $this->calledFrom('partner.new, partner.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletepartnerlogo.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('partner.new, partner.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletepartnerlogo.before.render', array($this, $output));

        return $output;
    }


    /**
     * Delete image for partner.
     *
     * @author kadek<kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param char    `partner_id`      (required) - ID of the partner
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeletePartnerImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postdeletepartnerimage.before.auth', array($this));

            if (! $this->calledFrom('partner.new, partner.update'))
            {
                // Require authentication
                $this->checkAuth();

                Event::fire('orbit.upload.postdeletepartnerimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postdeletepartnerimage.before.authz', array($this, $user));

                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }

                Event::fire('orbit.upload.postdeletepartnerimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $partner_id = OrbitInput::post('partner_id');

            $validator = Validator::make(
                array(
                    'partner_id'   => $partner_id,
                ),
                array(
                    'partner_id'   => 'required|orbit.empty.partner',
                )
            );

            Event::fire('orbit.upload.postdeletepartnerimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('partner.new, partner.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletepartnerimage.after.validation', array($this, $validator));

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $partner = App::make('orbit.empty.partner_id');
            $partner->touch();

            // Delete old partner logo
            $pastMedia = Media::where('object_id', $partner->partner_id)
                              ->where('object_name', 'partner')
                              ->where('media_name_id', 'partner_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            Event::fire('orbit.upload.postdeletepartnerimage.before.save', array($this, $partner));

            Event::fire('orbit.upload.postdeletepartnerimage.after.save', array($this, $partner));

            $this->response->data = $partner;
            $this->response->message = Lang::get('statuses.orbit.uploaded.partner.delete_image');

            if (! $this->calledFrom('partner.new, partner.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletepartnerimage.after.commit', array($this, $partner));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletepartnerimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('partner.new, partner.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletepartnerimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('partner.new, partner.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletepartnerimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('partner.new, partner.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletepartnerimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('partner.new, partner.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletepartnerimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload logo for Wallet Operator.
     *
     * @author kadek <kadek@myorbit.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `payment_provider_id`       (required) - ID of the wallet operator
     * @param file|array `logo`                      (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadWalletOperatorLogo()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadwalletoperatorlogo.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('walletoperator.new, walletoperator.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadwalletoperatorlogo.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadwalletoperatorlogo.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('edit_merchant')) {
                    Event::fire('orbit.upload.postuploadwalletoperatorlogo.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_merchant');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadwalletoperatorlogo.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $payment_provider_id = OrbitInput::post('payment_provider_id');
            $logo = OrbitInput::files('logo');
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'payment_provider_id' => $payment_provider_id,
                    'logo'                => $logo,
                ),
                array(
                    'payment_provider_id' => 'required|orbit.empty.walletoperator',
                    'logo'                => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadwalletoperatorlogo.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('walletoperator.new, walletoperator.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadwalletoperatorlogo.after.validation', array($this, $validator));

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $walletOperator = App::make('orbit.empty.walletoperator');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($walletOperator)
            {
                $payment_provider_id = $walletOperator->payment_provider_id;
                $slug = Str::slug($walletOperator->payment_name);
                $file['new']->name = sprintf('%s-%s-%s', $payment_provider_id, $slug, time());
            };

            // Load the orbit configuration for merchant upload logo
            $uploadLogoConfig = Config::get('orbit.upload.wallet_operator.logo');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadLogoConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadwalletoperatorlogo.before.save', array($this, $walletOperator, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($logo);

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $walletOperator->payment_provider_id)
                              ->where('object_name', 'wallet_operator')
                              ->where('media_name_id', 'wallet_operator_logo');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $walletOperator->payment_provider_id,
                'name'          => 'wallet_operator',
                'media_name_id' => 'wallet_operator_logo',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            // if (isset($uploaded[0])) {
            //     $merchant->logo = $uploaded[0]['path'];
            //     $merchant->save();
            // }

            Event::fire('orbit.upload.postuploadwalletoperatorlogo.after.save', array($this, $walletOperator, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.merchant.logo');

            // Commit the changes
            if (! $this->calledFrom('walletoperator.new, walletoperator.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadwalletoperatorlogo.after.commit', array($this, $walletOperator, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadwalletoperatorlogo.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('walletoperator.new, walletoperator.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadwalletoperatorlogo.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('walletoperator.new, walletoperator.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadwalletoperatorlogo.query.error', array($this, $e));

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
            if (! $this->calledFrom('walletoperator.new, walletoperator.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadwalletoperatorlogo.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('walletoperator.new, walletoperator.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadwalletoperatorlogo.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload images for Notification.
     *
     * @author Shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `news_id`                     (required) - ID of the news
     * @param file|array `images`                      (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadNotificationImage()
    {
        $notificationId = OrbitInput::post('notification_id');
        $mongoConfig = Config::get('database.mongodb');
        $mongoClient = MongoClient::create($mongoConfig);

        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadnewsimage.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('notification.new')) {
                $this->checkAuth();

                // Try to check access control list, does this notification allowed to
                // perform this action
                $user = $this->api->user;

                if (! ACL::create($user)->isAllowed('update_news')) {
                    $editNewsLang = Lang::get('validation.orbit.actionlist.update_news');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editNewsLang));
                    ACL::throwAccessForbidden($message);
                }
            } else {
                // Comes from event
                $user = App::make('orbit.upload.user');
            }

            // Load the orbit configuration for notification upload image
            $uploadImageConfig = Config::get('orbit.upload.notification.main');
            $elementName = $uploadImageConfig['name'];

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $images = OrbitInput::files($elementName);
            $messages = array(
                'nomore.than.three' => Lang::get('validation.max.array', array(
                    'max' => 3
                ))
            );

            $validator = Validator::make(
                array(
                    $elementName    => $images,
                    'notification_id'    => $notificationId,
                ),
                array(
                    $elementName    => 'required|array|nomore.than.three',
                    'notification_id'    => 'required',
                ),
                $messages
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $notif = $mongoClient->setEndPoint("notifications/$notificationId")->request('GET');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($notif, $notificationId)
            {
                $slug = Str::slug($notif->data->title);
                $file['new']->name = sprintf('%s-%s-%s', $notificationId, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadImageConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // delete existing notification image
            $isUpdate = false;
            $oldPath = array();
            if (! empty($notif->data->attachment_realpath)) {
                $isUpdate = true;
                //get old path before delete
                $oldPath[0]['path'] = $notif->data->attachment_path;
                $oldPath[0]['cdn_url'] = $notif->data->cdn_url;
                $oldPath[0]['cdn_bucket_name'] = $notif->data->cdn_bucket_name;

                @unlink($notif->data->attachment_realpath);
            }

            // update mongodb
            $body = [
                '_id'                 => $notificationId,
                'attachment_path'     => $uploaded[0]['path'],
                'attachment_realpath' => $uploaded[0]['realpath'],
                'mime_type'           => $uploaded[0]['mime_type'],
            ];

            $responseUpdate = $mongoClient->setFormParam($body)
                                    ->setEndPoint('notifications') // express endpoint
                                    ->request('PUT');

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $uploaded['extras'] = $extras;

            $this->response->data = $uploaded;
            $this->response->message = Lang::get('statuses.orbit.uploaded.news.main');
        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $deleteNotif = $mongoClient->setEndPoint("notifications/$notificationId")->request('DELETE');
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadnewsimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $deleteNotif = $mongoClient->setEndPoint("notifications/$notificationId")->request('DELETE');
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadnewsimage.query.error', array($this, $e));

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
            $deleteNotif = $mongoClient->setEndPoint("notifications/$notificationId")->request('DELETE');
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadnewsimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $deleteNotif = $mongoClient->setEndPoint("notifications/$notificationId")->request('DELETE');
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadnewsimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload logo for Sponsor Provider.
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `payment_provider_id`       (required) - ID of the wallet operator
     * @param file|array `logo`                      (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadSponsorProviderLogo()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadsponsorproviderlogo.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('sponsorprovider.new, sponsorprovider.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadsponsorproviderlogo.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadsponsorproviderlogo.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('edit_merchant')) {
                    Event::fire('orbit.upload.postuploadsponsorproviderlogo.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_merchant');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadsponsorproviderlogo.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $sponsor_provider_id = OrbitInput::post('sponsor_provider_id');
            $logo = OrbitInput::files('logo');
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'sponsor_provider_id' => $sponsor_provider_id,
                    'logo'                => $logo,
                ),
                array(
                    'sponsor_provider_id' => 'required|orbit.empty.sponsorprovider',
                    'logo'                => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadsponsorproviderlogo.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('sponsorprovider.new, sponsorprovider.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadsponsorproviderlogo.after.validation', array($this, $validator));

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $sponsorProvider = App::make('orbit.empty.sponsorprovider');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($sponsorProvider)
            {
                $sponsor_provider_id = $sponsorProvider->sponsor_provider_id;
                $slug = Str::slug($sponsorProvider->name);
                $file['new']->name = sprintf('%s-%s-%s', $sponsor_provider_id, $slug, time());
            };

            // Load the orbit configuration for merchant upload logo
            $uploadLogoConfig = Config::get('orbit.upload.sponsor_provider.logo');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadLogoConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadsponsorproviderlogo.before.save', array($this, $sponsorProvider, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($logo);

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $sponsorProvider->sponsor_provider_id)
                              ->where('object_name', 'sponsor_provider')
                              ->where('media_name_id', 'sponsor_provider_logo');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $sponsorProvider->sponsor_provider_id,
                'name'          => 'sponsor_provider',
                'media_name_id' => 'sponsor_provider_logo',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            // if (isset($uploaded[0])) {
            //     $merchant->logo = $uploaded[0]['path'];
            //     $merchant->save();
            // }

            Event::fire('orbit.upload.postuploadsponsorproviderlogo.after.save', array($this, $sponsorProvider, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'sponsor_provider_logo';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.merchant.logo');

            // Commit the changes
            if (! $this->calledFrom('sponsorprovider.new, sponsorprovider.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadsponsorproviderlogo.after.commit', array($this, $sponsorProvider, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadsponsorproviderlogo.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('sponsorprovider.new, sponsorprovider.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadsponsorproviderlogo.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('sponsorprovider.new, sponsorprovider.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadsponsorproviderlogo.query.error', array($this, $e));

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
            if (! $this->calledFrom('sponsorprovider.new, sponsorprovider.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadsponsorproviderlogo.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('sponsorprovider.new, sponsorprovider.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadsponsorproviderlogo.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload image for Credit Card.
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `payment_provider_id`       (required) - ID of the wallet operator
     * @param file|array `logo`                      (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadCreditCardImage()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadcreditcardimage.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('sponsorprovider.new, sponsorprovider.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadcreditcardimage.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadcreditcardimage.before.authz', array($this, $user));

                if (! ACL::create($user)->isAllowed('edit_merchant')) {
                    Event::fire('orbit.upload.postuploadcreditcardimage.authz.notallowed', array($this, $user));
                    $editMerchantLang = Lang::get('validation.orbit.actionlist.update_merchant');
                    $message = Lang::get('validation.orbit.access.forbidden', array('action' => $editMerchantLang));
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadcreditcardimage.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $sponsor_provider_id = OrbitInput::post('sponsor_provider_id');
            $credit_card_image_ids = OrbitInput::post('credit_card_image_ids');
            $credit_card_image_ids = (array) $credit_card_image_ids;
            $add_credit_card = OrbitInput::post('add_credit_card');
            $add_credit_card = (array) $add_credit_card;
            $logo = OrbitInput::files('credit_card_image');
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );
            //print_r($logo); die();
            $validator = Validator::make(
                array(
                    'sponsor_provider_id' => $sponsor_provider_id,
                    'logo'                => $logo,
                ),
                array(
                    'sponsor_provider_id' => 'required|orbit.empty.sponsorprovider',
                    'logo'                => 'required',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadcreditcardimage.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('sponsorprovider.new, sponsorprovider.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadcreditcardimage.after.validation', array($this, $validator));

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $sponsorProvider = App::make('orbit.empty.sponsorprovider');

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($sponsorProvider)
            {
                $sponsor_provider_id = $sponsorProvider->sponsor_provider_id;
                $slug = Str::slug($sponsorProvider->name);
                $file['new']->name = sprintf('%s-%s-%s', $sponsor_provider_id, $slug, time());
            };

            // Load the orbit configuration for merchant upload logo
            $uploadLogoConfig = Config::get('orbit.upload.sponsor_credit_card.image');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadLogoConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadcreditcardimage.before.save', array($this, $sponsorProvider, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($logo);

            // Delete old merchant logo
            $pastMedia = Media::whereIn('object_id', $credit_card_image_ids)
                              ->where('object_name', 'sponsor_credit_card')
                              ->where('media_name_id', 'sponsor_credit_card_image');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;
                $oldPath[$oldMedia->media_id]['object_id'] = $oldMedia->object_id;
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }

            if ($this->calledFrom('sponsorprovider.new'))
            {
                // get the id of sponsor credit card
                $creditCards = SponsorCreditCard::select('sponsor_credit_card_id')
                                    ->where('sponsor_provider_id','=', $sponsorProvider->sponsor_provider_id)
                                    ->get();

                $arrCreditCardId = [];
                if (!empty($creditCards)) {
                    foreach ($creditCards as $key => $value) {
                        $arrCreditCardId[] = $creditCards[$key]['sponsor_credit_card_id'];
                    }
                }
            }

            if ($this->calledFrom('sponsorprovider.update'))
            {
                if (!empty($add_credit_card))
                {
                    foreach ($add_credit_card as $key => $value) {
                        $credit_card_image_ids[] = $value;
                    }
                    $credit_card_image_ids = array_diff($credit_card_image_ids, array(0));
                    $credit_card_image_ids = array_values($credit_card_image_ids);
                }
                $arrCreditCardId = $credit_card_image_ids;
            }

            // Save the files metadata
            $object = array(
                'id'            => $arrCreditCardId,
                'name'          => 'sponsor_credit_card',
                'media_name_id' => 'sponsor_credit_card_image',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetaDataCreditCard($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            // if (isset($uploaded[0])) {
            //     $merchant->logo = $uploaded[0]['path'];
            //     $merchant->save();
            // }

            Event::fire('orbit.upload.postuploadcreditcardimage.after.save', array($this, $sponsorProvider, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'sponsor_credit_card_image';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.merchant.logo');

            // Commit the changes
            if (! $this->calledFrom('sponsorprovider.new, sponsorprovider.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadcreditcardimage.after.commit', array($this, $sponsorProvider, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadcreditcardimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('sponsorprovider.new, sponsorprovider.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadcreditcardimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('sponsorprovider.new, sponsorprovider.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadcreditcardimage.query.error', array($this, $e));

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
            if (! $this->calledFrom('sponsorprovider.new, sponsorprovider.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadcreditcardimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('sponsorprovider.new, sponsorprovider.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadcreditcardimage.before.render', array($this, $output));

        return $output;
    }

        /**
     * Upload logo for Telco Operator.
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `telco_operator_id`           (required) - ID of the telco
     * @param file|array `images`                      (required) - Images of the logo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadTelcoLogo()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadtelcologo.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('telco.new, telco.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadtelcologo.after.auth', array($this));

                // Try to check access control list, does this parent allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadtelcologo.before.authz', array($this, $user));

                $role = $user->role;
                $validRoles = ['super admin', 'mall admin', 'mall owner'];
                if (! in_array( strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadtelcologo.after.authz', array($this, $user));
            }

            // Register custom validation
            $this->registerCustomValidation();

            // Application input
            $telco_operator_id = OrbitInput::post('telco_operator_id');
            $logo = OrbitInput::files('logo');
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'telco_operator_id' => $telco_operator_id,
                    'logo'       => $logo,
                ),
                array(
                    'telco_operator_id' => 'required|orbit.empty.telco',
                    'logo'       => 'required|nomore.than.one',
                )
            );

            Event::fire('orbit.upload.postuploadtelcologo.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('telco.new, telco.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadtelcologo.after.validation', array($this, $validator));

            // We already had telco instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $telco = App::make('orbit.empty.telco_operator_id');

            // Callback to rename the file, we will format it as follow
            // [telco_operator_id]-[telco]
            $renameFile = function($uploader, &$file, $dir) use ($telco)
            {
                $telco_operator_id = $telco->telco_operator_id;
                $slug = Str::slug($telco->name);
                $file['new']->name = sprintf('%s-%s-%s', $telco_operator_id, $slug, time());
            };

            // Load the orbit configuration for telco upload logo
            $uploadLogoConfig = Config::get('orbit.upload.telco.logo');

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadLogoConfig);
            $config->setConfig('before_saving', $renameFile);
            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadtelcologo.before.save', array($this, $telco, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($logo);

            // Delete old telco logo
            $pastMedia = Media::where('object_id', $telco->telco_operator_id)
                              ->where('object_name', 'telco_operator')
                              ->where('media_name_id', 'telco_operator_logo');

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            $oldPath = array();
            foreach ($oldMediaFiles as $oldMedia) {
                //get old path before delete
                $oldPath[$oldMedia->media_id]['path'] = $oldMedia->path;
                $oldPath[$oldMedia->media_id]['cdn_url'] = $oldMedia->cdn_url;
                $oldPath[$oldMedia->media_id]['cdn_bucket_name'] = $oldMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            $isUpdate = false;
            if (count($oldMediaFiles) > 0) {
                $isUpdate = true;
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $telco->telco_operator_id,
                'name'          => 'telco_operator',
                'media_name_id' => 'telco_operator_logo',
                'modified_by'   => 1
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            Event::fire('orbit.upload.postuploadtelcologo.after.save', array($this, $telco, $uploader));

            $extras = new \stdClass();
            $extras->isUpdate = $isUpdate;
            $extras->oldPath = $oldPath;
            $extras->mediaNameId = 'telco_operator_logo';
            $mediaList['extras'] = $extras;

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.telco.logo');

            // Commit the changes
            if (! $this->calledFrom('telco.new, telco.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadtelcologo.after.commit', array($this, $telco, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadtelcologo.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('telco.new, telco.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadtelcologo.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('telco.new, telco.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadtelcologo.query.error', array($this, $e));

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
            if (! $this->calledFrom('telco.new, telco.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadtelcologo.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = [$e->getMessage(), $e->getFile(), $e->getLine()];
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('telco.new, telco.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadtelcologo.before.render', array($this, $output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        if ($this->calledFrom('default')) {
            // Check the existance of merchant id
            $user = $this->api->user;
            Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) use ($user) {
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

            // @Todo: Refactor by adding allowedForUser for tenant
            Validator::extend('orbit.empty.tenant123', function ($attribute, $value, $parameters) use ($user) {
                $merchant = Tenant::excludeDeleted()
                            ->where('merchant_id', $value)
                            ->first();

                if (empty($merchant)) {
                    return FALSE;
                }

                App::instance('orbit.empty.tenant123', $merchant);

                return TRUE;
            });

            // Check existing tenant (with type tenant or service)
            Validator::extend('orbit.empty.tenantstoreandservice', function ($attribute, $value, $parameters){
                $merchant = TenantStoreAndService::excludeDeleted()
                            ->where(function($q) {
                                 $q->where('object_type', 'tenant')
                                   ->orWhere('object_type', 'service');
                            })
                            ->where('merchant_id', $value)
                            ->first();

                if (empty($merchant)) {
                    return FALSE;
                }

                App::instance('orbit.empty.tenantstoreandservice', $merchant);

                return TRUE;
            });


            // Check the existance of the tenant type
            Validator::extend('orbit.empty.tenant_type', function ($attribute, $value, $parameters) {
                $valid = false;
                $statuses = array('tenant', 'service');
                foreach ($statuses as $status) {
                    if($value === $status) $valid = $valid || TRUE;
                }

                return $valid;
            });

            // @Todo: Refactor by adding allowedForUser for mall
            Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) use ($user) {
                $merchant = Mall::excludeDeleted()
                            ->where('merchant_id', $value)
                            ->first();

                if (empty($merchant)) {
                    return FALSE;
                }

                App::instance('orbit.empty.mall', $merchant);

                return TRUE;
            });

            // @Todo: Refactor by adding allowedForUser for mall group
            Validator::extend('orbit.empty.mallgroup', function ($attribute, $value, $parameters) use ($user) {
                $merchant = MallGroup::excludeDeleted()
                            ->where('merchant_id', $value)
                            ->first();

                if (empty($merchant)) {
                    return FALSE;
                }

                App::instance('orbit.empty.mallgroup', $merchant);

                return TRUE;
            });

            Validator::extend('orbit.empty.news', function ($attribute, $value, $parameters) use ($user) {
                $news = News::excludeDeleted()
                            ->where('news_id', $value)
                            ->first();

                if (empty($news)) {
                    return FALSE;
                }

                App::instance('orbit.empty.news', $news);

                return TRUE;
            });

            Validator::extend('orbit.empty.membership', function ($attribute, $value, $parameters) use ($user) {
                $membership = Membership::excludeDeleted()
                                        ->with('media')
                                        ->where('membership_id', $value)
                                        ->first();

                if (empty($membership)) {
                    return FALSE;
                }

                App::instance('orbit.empty.membership', $membership);

                return TRUE;
            });

            Validator::extend('orbit.empty.lucky_draw', function ($attribute, $value, $parameters) use ($user) {
                $luckyDraw = LuckyDraw::excludeDeleted()
                            ->where('lucky_draw_id', $value)
                            ->first();

                if (empty($luckyDraw)) {
                    return FALSE;
                }

                App::instance('orbit.empty.lucky_draw', $luckyDraw);

                return TRUE;
            });
        }

        Validator::extend('orbit.empty.news', function ($attribute, $value, $parameters) {
            $news = News::excludeDeleted()
                        ->where('news_id', $value)
                        ->first();

            if (empty($news)) {
                return FALSE;
            }

            App::instance('orbit.empty.news', $news);

            return TRUE;
        });


        if ($this->calledFrom('default')) {
            // Check the existance of product id
            Validator::extend('orbit.empty.product', function ($attribute, $value, $parameters) {
                $product = Product::excludeDeleted()
                            ->where('product_id', $value)
                            ->first();

                if (empty($product)) {
                    return FALSE;
                }

                App::instance('orbit.empty.product', $product);

                return TRUE;
            });
        }

        if ($this->calledFrom('default')) {
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
        }

        Validator::extend('orbit.empty.advert_id', function ($attribute, $value, $parameters){
            $advert = Advert::excludeDeleted()
                        ->where('advert_id', $value)
                        ->first();

            if (empty($advert)) {
                return FALSE;
            }

            App::instance('orbit.empty.advert_id', $advert);

            return TRUE;
        });

        Validator::extend('orbit.empty.partner', function ($attribute, $value, $parameters){
            $partner = Partner::excludeDeleted()
                        ->where('partner_id', $value)
                        ->first();

            if (empty($partner)) {
                return FALSE;
            }

            App::instance('orbit.empty.partner_id', $partner);

            return TRUE;
        });

        Validator::extend('orbit.empty.telco', function ($attribute, $value, $parameters){
            $telco = TelcoOperator::where('status', 'active')
                        ->where('telco_operator_id', $value)
                        ->first();

            if (empty($telco)) {
                return FALSE;
            }

            App::instance('orbit.empty.telco_operator_id', $telco);

            return TRUE;
        });

        Validator::extend('orbit.empty.partner_banner', function ($attribute, $value, $parameters){
            $partner = PartnerBanner::where('partner_banner_id', $value)->first();

            if (empty($partner)) {
                return FALSE;
            }

            App::instance('orbit.empty.partner_banner_id', $partner);

            return TRUE;
        });

        Validator::extend('orbit.empty.promotion_translation', function ($attribute, $value, $parameters) {
            $promotion_translation = PromotionTranslation::excludeDeleted()
                        ->where('promotion_translation_id', $value)
                        ->first();

            if (empty($promotion_translation)) {
                return FALSE;
            }

            App::instance('orbit.empty.promotion_translation', $promotion_translation);

            return TRUE;
        });

        Validator::extend('orbit.empty.merchant_language', function ($attribute, $value, $parameters) {
            $merchant_language = Language::where('language_id', '=', $value)
                ->first();

            if (empty($merchant_language)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant_language', $merchant_language);

            return TRUE;
        });

        Validator::extend('orbit.empty.walletoperator', function ($attribute, $value, $parameters) {
            $walletOperator = PaymentProvider::excludeDeleted()
                                        ->where('payment_provider_id', $value)
                                        ->first();

            if (empty($walletOperator)) {
                return FALSE;
            }

            App::instance('orbit.empty.walletoperator', $walletOperator);

            return TRUE;
        });

        Validator::extend('orbit.empty.sponsorprovider', function ($attribute, $value, $parameters) {
            $sponsorProvider = SponsorProvider::excludeDeleted()
                                        ->where('sponsor_provider_id', $value)
                                        ->first();

            if (empty($sponsorProvider)) {
                return FALSE;
            }

            App::instance('orbit.empty.sponsorprovider', $sponsorProvider);

            return TRUE;
        });

        if ($this->calledFrom('default, user.update')) {
            // Check the existance of user id
            Validator::extend('orbit.empty.user', function ($attribute, $value, $parameters) {
                $user = User::excludeDeleted()
                            ->with('userdetail')
                            ->where('user_id', $value)
                            ->first();

                if (empty($user)) {
                    return FALSE;
                }

                App::instance('orbit.empty.user', $user);

                return TRUE;
            });
        }

        if ($this->calledFrom('default')) {
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
        }

        if ($this->calledFrom('default')) {
            Validator::extend('orbit.empty.widget', function ($attribute, $value, $parameters) use ($user) {
                $widget = Widget::excludeDeleted()
                            ->where('widget_id', $value)
                            ->first();

                if (empty($widget)) {
                    return FALSE;
                }

                App::instance('orbit.empty.widget', $widget);

                return TRUE;
            });
        }

        if ($this->calledFrom('default')) {
            // Check the existance of event id
            Validator::extend('orbit.empty.event', function ($attribute, $value, $parameters) {
                $event = EventModel::excludeDeleted()
                            ->where('event_id', $value)
                            ->first();

                if (empty($event)) {
                    return FALSE;
                }

                App::instance('orbit.empty.event', $event);

                return TRUE;
            });
        }

        if ($this->calledFrom('default')) {
            // Check the existance of event id
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
        }

        Validator::extend('orbit.empty.news_translation', function ($attribute, $value, $parameters) {
            $news_translation = NewsTranslation::excludeDeleted()
                        ->where('news_translation_id', $value)
                        ->first();

            if (empty($news_translation)) {
                return FALSE;
            }

            App::instance('orbit.empty.news_translation', $news_translation);

            return TRUE;
        });

        Validator::extend('orbit.empty.reward_detail_translation', function ($attribute, $value, $parameters) {
            $reward_detail_translation = RewardDetailTranslation::where('reward_detail_translation_id', $value)
                        ->first();

            if (empty($reward_detail_translation)) {
                return FALSE;
            }

            App::instance('orbit.empty.reward_detail_translation', $reward_detail_translation);

            return TRUE;
        });

        Validator::extend('orbit.empty.reward_detail', function ($attribute, $value, $parameters) {
            $reward_detail = RewardDetail::where('reward_detail_id', $value)
                        ->first();

            if (empty($reward_detail)) {
                return FALSE;
            }

            App::instance('orbit.empty.reward_detail', $reward_detail);

            return TRUE;
        });

        Validator::extend('orbit.empty.event_translation', function ($attribute, $value, $parameters) {
            $event_translation = EventTranslation::excludeDeleted()
                        ->where('event_translation_id', $value)
                        ->first();

            if (empty($event_translation)) {
                return FALSE;
            }

            App::instance('orbit.empty.event_translation', $event_translation);

            return TRUE;
        });

        Validator::extend('orbit.empty.coupon_translation', function ($attribute, $value, $parameters) {
            $coupon_translation = CouponTranslation::excludeDeleted()
                        ->where('coupon_translation_id', $value)
                        ->first();

            if (empty($coupon_translation)) {
                return FALSE;
            }

            App::instance('orbit.empty.coupon_translation', $coupon_translation);

            return TRUE;
        });

        Validator::extend('orbit.empty.lucky_draw_translation', function ($attribute, $value, $parameters) {
            $lucky_draw_translation = LuckyDrawTranslation::excludeDeleted()
                        ->where('lucky_draw_translation_id', $value)
                        ->first();

            if (empty($lucky_draw_translation)) {
                return FALSE;
            }

            App::instance('orbit.empty.lucky_draw_translation', $lucky_draw_translation);

            return TRUE;
        });

        Validator::extend('orbit.empty.lucky_draw_announcement', function ($attribute, $value, $parameters) {
            $luckyDrawAnnouncement = LuckyDrawAnnouncement::excludeDeleted()
                        ->where('lucky_draw_announcement_id', $value)
                        ->first();

            if (empty($luckyDrawAnnouncement)) {
                return FALSE;
            }

            App::instance('orbit.empty.lucky_draw_announcement', $luckyDrawAnnouncement);

            return TRUE;
        });

        Validator::extend('orbit.empty.lucky_draw_announcement_translation', function ($attribute, $value, $parameters) {
            $lucky_draw_announcement_translation = LuckyDrawAnnouncementTranslation::excludeDeleted()
                        ->where('lucky_draw_announcement_translation_id', $value)
                        ->first();

            if (empty($lucky_draw_announcement_translation)) {
                return FALSE;
            }

            App::instance('orbit.empty.lucky_draw_announcement_translation', $lucky_draw_announcement_translation);

            return TRUE;
        });

        Validator::extend('orbit.empty.merchant_language_lucky_draw', function ($attribute, $value, $parameters) {
            $merchant_language = MerchantLanguage::excludeDeleted()
                       ->where('language_id', $value)
                       ->first();

            if (empty($merchant_language)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant_language_lucky_draw', $merchant_language);

            return TRUE;
        });

        // Check the images, we are allowed array of images but not more that one
        Validator::extend('nomore.than.one', function ($attribute, $value, $parameters) {
            if (is_array($value['name']) && count($value['name']) > 1) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the images, we are allowed array of images but not more that three
        Validator::extend('nomore.than.three', function ($attribute, $value, $parameters) {
            if (is_array($value['name']) && count($value['name']) > 3) {
                return FALSE;
            }

            return TRUE;
        });
    }

    public function calledFrom($list)
    {
        if (! is_array($list))
        {
            $list = explode(',', (string)$list);
            $list = array_map('trim', $list);
        }

        return in_array($this->calledFrom, $list);
    }

    /**
     * Set the called from value.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $from The source of the caller
     * @return UploadAPIController
     */
    public function setCalledFrom($from)
    {
        $this->calledFrom = $from;

        return $this;
    }
}
