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
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitUploader\UploaderConfig;
use DominoPOS\OrbitUploader\UploaderMessage;
use DominoPOS\OrbitUploader\Uploader;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use \Exception;

class UploadAPIController extends ControllerAPI
{
    /**
     * From what part of the code this API are called from.
     *
     * @var string
     */
    protected $calledFrom = 'default';

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

    /**
     * Upload logo for Merchant.
     *
     * @author Rio Astamal <me@rioastamal.net>
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
                    'merchant_id'   => 'required|numeric|orbit.empty.merchant',
                    'images'        => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadmerchantlogo.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadmerchantlogo.after.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->beginTransaction();
            }

            // We already had Merchant instance on the RegisterCustomValidation
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

                if (! ACL::create($user)->isAllowed('update_merchant')) {
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
                    'merchant_id'   => 'required|numeric|orbit.empty.merchant',
                )
            );

            Event::fire('orbit.upload.postdeletemerchantlogo.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletemerchantlogo.after.validation', array($this, $validator));

            if (! $this->calledFrom('merchant.new,merchant.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

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
                    'product_id'   => 'required|numeric|orbit.empty.product',
                    'images'       => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadproductimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadproductimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('product.new,product.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

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
                    'product_id'   => 'required|numeric|orbit.empty.product',
                )
            );

            Event::fire('orbit.upload.postdeleteproductimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeleteproductimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('product.new,product.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

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
                    'promotion_id'  => 'required|numeric|orbit.empty.promotion',
                    'images'        => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadpromotionimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadpromotionimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

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
                    'promotion_id'   => 'required|numeric|orbit.empty.promotion',
                )
            );

            Event::fire('orbit.upload.postdeletepromotionimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletepromotionimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('promotion.new,promotion.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

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
     * Upload profile picure (avatar) for User.
     *
     * @author Rio Astamal <me@rioastamal.net>
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
                    'user_id'   => 'required|numeric|orbit.empty.user',
                    'images'    => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploaduserimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploaduserimage.after.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('user.new, user.update')) {
                $this->beginTransaction();
            }

            // We already had User instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $user = App::make('orbit.empty.user');

            // Delete old user picture
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
                    'user_id'   => 'required|numeric|orbit.empty.user',
                )
            );

            Event::fire('orbit.upload.postdeleteuserimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeleteuserimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('user.new,user.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

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
                    'promotion_id'  => 'required|numeric|orbit.empty.coupon',
                    'images'        => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadcouponimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadcouponimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('coupon.new,coupon.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // We already had Coupon instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $coupon = App::make('orbit.empty.coupon');

            // Delete old coupon image
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
                    'promotion_id'   => 'required|numeric|orbit.empty.coupon',
                )
            );

            Event::fire('orbit.upload.postdeletecouponimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletecouponimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('coupon.new,coupon.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

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
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `widget_id`                    (required) - ID of the widget
     * @param file|array `images`                       (required) - Images of the user photo
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadWidgetImage()
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
            $images = OrbitInput::files('images');
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
                    'widget_id' => 'required|numeric|orbit.empty.widget',
                    'images'    => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadwidgetimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadwidgetimage.after.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('widget.new, widget.update')) {
                $this->beginTransaction();
            }

            // We already had User instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $widget = App::make('orbit.empty.widget');

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
            $uploaded = $uploader->upload($images);

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
                    'event_id'      => 'required|numeric|orbit.empty.event',
                    'images'        => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadeventimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadeventimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('event.new,event.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // We already had Event instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $event = App::make('orbit.empty.event');

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
                    'event_id'      => 'required|numeric|orbit.empty.event',
                )
            );

            Event::fire('orbit.upload.postdeleteeventimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeleteeventimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('event.new,event.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

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
     * Upload logo for Tenant.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - ID of the merchant
     * @param file|array `images`                      (required) - Images of the logo
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
                    'merchant_id'   => 'required|numeric|orbit.empty.tenant',
                    $elementName    => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadtenantlogo.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadtenantlogo.after.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->beginTransaction();
            }

            // We already had Merchant instance on the RegisterCustomValidation
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

            // Save the files metadata
            $object = array(
                'id'            => $merchant->merchant_id,
                'name'          => 'retailer',
                'media_name_id' => 'retailer_logo',
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

                if (! ACL::create($user)->isAllowed('update_merchant')) {
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
                    'merchant_id'   => 'required|numeric|orbit.empty.tenant',
                )
            );

            Event::fire('orbit.upload.postdeletetenantlogo.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletetenantlogo.after.validation', array($this, $validator));

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

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
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - ID of the merchant
     * @param file|array `pictures`                    (required) - Images of the logo
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
                    'merchant_id'   => 'required|numeric|orbit.empty.tenant',
                    $elementName    => 'required|array|nomore.than.three',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadtenantimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadtenantimage.after.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->beginTransaction();
            }

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.tenant');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'retailer')
                              ->where('media_name_id', 'retailer_image');

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

            // Save the files metadata
            $object = array(
                'id'            => $merchant->merchant_id,
                'name'          => 'retailer',
                'media_name_id' => 'retailer_image',
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

                if (! ACL::create($user)->isAllowed('update_merchant')) {
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

            $validator = Validator::make(
                array(
                    'merchant_id'   => $merchant_id,
                    'picture_index' => $picture_index,
                ),
                array(
                    'merchant_id'    => 'required|numeric|orbit.empty.tenant',
                    'picture_index'  => 'array',
                )
            );

            Event::fire('orbit.upload.postdeletetenantimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletetenantimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // We already had Product instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.tenant');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'retailer')
                              ->where('media_name_id', 'retailer_image');

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
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`                 (required) - ID of the merchant
     * @param file|array `maps`                        (required) - Images of the logo
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
                    'merchant_id'   => 'required|numeric|orbit.empty.tenant',
                    $elementName    => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.uploadpostuploadtenantmap.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.uploadpostuploadtenantmap.after.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('tenant.new, tenant.update')) {
                $this->beginTransaction();
            }

            // We already had Merchant instance on the RegisterCustomValidation
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

            // Save the files metadata
            $object = array(
                'id'            => $merchant->merchant_id,
                'name'          => 'retailer',
                'media_name_id' => 'retailer_map',
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

                if (! ACL::create($user)->isAllowed('update_merchant')) {
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
                    'merchant_id'   => 'required|numeric|orbit.empty.tenant',
                )
            );

            Event::fire('orbit.upload.postdeletetenantmap.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletetenantmap.after.validation', array($this, $validator));

            if (! $this->calledFrom('tenant.new,tenant.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

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
                    'merchant_id'   => 'required|numeric|orbit.empty.mall',
                    $elementName    => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadmallbackground.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadmallbackground.after.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('mall.new, mall.update')) {
                $this->beginTransaction();
            }

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.tenant');

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'retailer')
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

            Event::fire('orbit.upload.postuploadmallbackground.before.save', array($this, $merchant, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Save the files metadata
            $object = array(
                'id'            => $merchant->merchant_id,
                'name'          => 'retailer',
                'media_name_id' => 'retailer_background',
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
                    'merchant_id'   => 'required|numeric|orbit.empty.mall',
                )
            );

            Event::fire('orbit.upload.postdeletemallbackground.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletemallbackground.after.validation', array($this, $validator));

            if (! $this->calledFrom('mall.new, mall.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = App::make('orbit.empty.tenant');

            // Delete old merchant image
            $pastMedia = Media::where('object_id', $merchant->merchant_id)
                              ->where('object_name', 'retailer')
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
            $merchant->logo = NULL;
            $merchant->save();

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
     * Upload images for News.
     *
     * @author Tian <tian@dominopos.com>
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
                    'news_id'       => 'required|numeric|orbit.empty.news',
                    $elementName    => 'required|array|nomore.than.three',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadnewsimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadnewsimage.after.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('news.new, news.update')) {
                $this->beginTransaction();
            }

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
                    'news_id'   => 'required|numeric|orbit.empty.news',
                )
            );

            Event::fire('orbit.upload.postdeletenewsimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletenewsimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('news.new, news.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

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
     * Upload images for Lucky Draw.
     *
     * @author Tian <tian@dominopos.com>
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
                    'lucky_draw_id' => 'required|numeric|orbit.empty.lucky_draw',
                    $elementName    => 'required|array|nomore.than.three',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadluckydrawimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadluckydrawimage.after.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                $this->beginTransaction();
            }

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
                    'lucky_draw_id'   => 'required|numeric|orbit.empty.lucky_draw',
                )
            );

            Event::fire('orbit.upload.postdeleteluckydrawimage.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeleteluckydrawimage.after.validation', array($this, $validator));

            if (! $this->calledFrom('luckydraw.new, luckydraw.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

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
            Validator::extend('orbit.empty.tenant', function ($attribute, $value, $parameters) use ($user) {
                $merchant = Retailer::excludeDeleted()
                            ->isMall('no')
                            ->where('merchant_id', $value)
                            ->first();

                if (empty($merchant)) {
                    return FALSE;
                }

                App::instance('orbit.empty.tenant', $merchant);

                return TRUE;
            });

            // @Todo: Refactor by adding allowedForUser for mall
            Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) use ($user) {
                $merchant = Retailer::excludeDeleted()
                            ->isMall('yes')
                            ->where('merchant_id', $value)
                            ->first();

                if (empty($merchant)) {
                    return FALSE;
                }

                App::instance('orbit.empty.tenant', $merchant);

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

        if ($this->calledFrom('default')) {
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
