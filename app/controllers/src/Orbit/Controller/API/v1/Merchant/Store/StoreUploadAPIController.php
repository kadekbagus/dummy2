<?php namespace Orbit\Controller\API\v1\Merchant\Store;

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

use Config;
use stdClass;
use DB;
use Validator;
use Lang;
use \Exception;
use \Event;
use \Media;
use \Str;
use \App;
use Orbit\Controller\API\v1\Merchant\Store\StoreHelper;
use BaseStore;

class StoreUploadAPIController extends ControllerAPI
{
    /**
     * From what part of the code this API are called from.
     *
     * @var string
     */
    protected $calledFrom = 'default';

    protected $deleteStoreImageRoles = ['merchant database admin'];
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
     * Upload images for Base Store.
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `base_store_id`               (required) - ID of the base store
     * @param file|array `pictures`                    (required) - Pictures of the Image
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadBaseStoreImage()
    {
        try {
            $httpCode = 200;
            $user = App::make('orbit.upload.user');

            // Load the orbit configuration for base store upload image
            $uploadImageConfig = Config::get('orbit.upload.base_store.picture');
            $elementName = $uploadImageConfig['name'];

            // Register custom validation
            $storeHelper = StoreHelper::create();
            $storeHelper->storeCustomValidator();

            // Application input
            $base_store_id = OrbitInput::post('base_store_id');
            $images = OrbitInput::files($elementName);

            $messages = array(
                'nomore.than' => Lang::get('validation.max.array', array(
                    'max' => 3
                ))
            );

            $validator = Validator::make(
                array(
                    'base_store_id' => $base_store_id,
                    $elementName    => $images,
                ),
                array(
                    'base_store_id' => 'required|orbit.empty.base_store',
                    $elementName    => 'required|array|nomore.than:3',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadstoreimage.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadstoreimage.after.validation', array($this, $validator));

            // We already had validation base store
            // get it from there no need to re-query the database
            $base_store = $storeHelper->getValidBaseStore();

            // Callback to rename the file, we will format it as follow
            // [BASE_STORE_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($base_store)
            {
                $base_store_id = $base_store->base_store_id;
                $slug = Str::slug($base_store->name);
                $file['new']->name = sprintf('%s-%s-%s', $base_store_id, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadImageConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadstoreimage.before.save', array($this, $base_store, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Delete old base_store image
            $pastMedia = Media::where('object_id', $base_store->base_store_id)
                              ->where('object_name', 'base_store')
                              ->where('media_name_id', 'base_store_image');

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
                'id'            => $base_store->base_store_id,
                'name'          => 'base_store',
                'media_name_id' => 'base_store_image',
                'modified_by'   => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            if (isset($uploaded[0])) {
                $base_store->save();
            }

            Event::fire('orbit.upload.postuploadstoreimage.after.save', array($this, $base_store, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = 'Base Store Image has been successfully uploaded.';

            // Commit the changes
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadstoreimage.after.commit', array($this, $base_store, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadstoreimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadstoreimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadstoreimage.query.error', array($this, $e));

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
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadstoreimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadstoreimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete images for a base store.
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `base_store_id`                  (required) - ID of the merchant/retailer
     * @param integer    `picture_index`                (required) - Index of the picture
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteBaseStoreImage()
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
            $validRoles = $this->deleteStoreImageRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            // Register custom validation
            $storeHelper = StoreHelper::create();
            $storeHelper->storeCustomValidator();

            // Application input
            $base_store_id = OrbitInput::post('base_store_id');
            $picture_index = OrbitInput::post('picture_index');

            $validator = Validator::make(
                array(
                    'base_store_id' => $base_store_id,
                    'picture_index' => $picture_index,
                ),
                array(
                    'base_store_id' => 'required|orbit.empty.base_store',
                    'picture_index' => 'array',
                )
            );

            Event::fire('orbit.upload.postdeletebasestoreimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('basestore.new,basestore.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletebasestoreimage.after.validation', array($this, $validator));

            // We already had validation base store
            // get it from there no need to re-query the database
            $base_store = $storeHelper->getValidBaseStore();

            // Delete old base_store image
            $pastMedia = Media::where('object_id', $base_store->base_store_id)
                              ->where('object_name', 'base_store')
                              ->where('media_name_id', 'base_store_image');

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

            Event::fire('orbit.upload.postdeletebasestoreimage.before.save', array($this, $base_store));

            // Update the `image` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per base_store
            $base_store->save();

            Event::fire('orbit.upload.postdeletebasestoreimage.after.save', array($this, $base_store));

            $this->response->data = $base_store;
            $this->response->message = Lang::get('statuses.orbit.uploaded.retailer.delete_image');

            if (! $this->calledFrom('basestore.new,basestore.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletebasestoreimage.after.commit', array($this, $base_store));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletebasestoreimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('basestore.new,basestore.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletebasestoreimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('basestore.new,basestore.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletebasestoreimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('basestore.new,basestore.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletebasestoreimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('basestore.new, basestore.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletebasestoreimage.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload map for Base Store.
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `base_store_id`               (required) - ID of the base store
     * @param file|array `maps`                        (required) - Images of the map
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadBaseStoreMap()
    {
        try {
            $httpCode = 200;
            $user = App::make('orbit.upload.user');

            // Load the orbit configuration for merchant upload logo
            $uploadMapConfig = Config::get('orbit.upload.base_store.map');
            $elementName = $uploadMapConfig['name'];

            // Register custom validation
            $storeHelper = StoreHelper::create();
            $storeHelper->storeCustomValidator();

            // Application input
            $base_store_id = OrbitInput::post('base_store_id');
            $images = OrbitInput::files($elementName);

            $messages = array(
                'nomore.than' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'base_store_id' => $base_store_id,
                    $elementName  => $images,
                ),
                array(
                    'base_store_id'   => 'required|orbit.empty.base_store',
                    $elementName    => 'required|nomore.than:1',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadstoremap.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadstoremap.after.validation', array($this, $validator));

            // We already had validation base store
            // get it from there no need to re-query the database
            $base_store = $storeHelper->getValidBaseStore();

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($base_store)
            {
                $base_store_id = $base_store->base_store_id;
                $slug = Str::slug($base_store->name);
                $file['new']->name = sprintf('%s-%s-%s', $base_store_id, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadMapConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadstoremap.before.save', array($this, $base_store, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Delete old base_store logo
            $pastMedia = Media::where('object_id', $base_store->base_store_id)
                              ->where('object_name', 'base_store')
                              ->where('media_name_id', 'base_store_map');

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
                'id'            => $base_store->base_store_id,
                'name'          => 'base_store',
                'media_name_id' => 'base_store_map',
                'modified_by'   => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            if (isset($uploaded[0])) {
                $base_store->save();
            }

            Event::fire('orbit.upload.postuploadstoremap.after.save', array($this, $base_store, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.retailer.map');

            // Commit the changes
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadstoremap.after.commit', array($this, $base_store, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadstoremap.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadstoremap.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadstoremap.query.error', array($this, $e));

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
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadstoremap.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadstoremap.before.render', array($this, $output));

        return $output;
    }

    /**
     * Delete map for a base store.
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `base_store_id`                  (required) - ID of the merchant
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteBaseStoreMap()
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
            $validRoles = $this->deleteStoreImageRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            // Register custom validation
            $storeHelper = StoreHelper::create();
            $storeHelper->storeCustomValidator();

            // Application input
            $base_store_id = OrbitInput::post('base_store_id');

            $validator = Validator::make(
                array(
                    'base_store_id'   => $base_store_id,
                ),
                array(
                    'base_store_id'   => 'required|orbit.empty.base_store',
                )
            );

            Event::fire('orbit.upload.postdeletebasestoremap.before.validation', array($this, $validator));

            if (! $this->calledFrom('basestore.new,basestore.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletebasestoremap.after.validation', array($this, $validator));

            // We already had validation base store
            // get it from there no need to re-query the database
            $base_store = $storeHelper->getValidBaseStore();

            // Delete old base_store map
            $pastMedia = Media::where('object_id', $base_store->base_store_id)
                              ->where('object_name', 'base_store')
                              ->where('media_name_id', 'base_store_map');

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

            Event::fire('orbit.upload.postdeletebasestoremap.before.save', array($this, $base_store));

            // Update the `map` field which store the original path of the map
            // This is temporary since right now the business rules actually
            // only allows one map per base_store
            $base_store->save();

            Event::fire('orbit.upload.postdeletebasestoremap.after.save', array($this, $base_store));

            $this->response->data = $base_store;
            $this->response->message = Lang::get('statuses.orbit.uploaded.retailer.delete_map');

            if (! $this->calledFrom('basestore.new,basestore.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletebasestoremap.after.commit', array($this, $base_store));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletebasestoremap.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('basestore.new,basestore.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletebasestoremap.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('basestore.new,basestore.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletebasestoremap.query.error', array($this, $e));

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

            if (! $this->calledFrom('basestore.new,basestore.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletebasestoremap.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('basestore.new, basestore.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletebasestoremap.before.render', array($this, $output));

        return $output;
    }

    /**
     * Upload images grab for Base Store.
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `base_store_id`               (required) - ID of the base store
     * @param file|array `grab_pictures`               (required) - Pictures of the Image
     * @return Illuminate\Support\Facades\Response
     */
    public function postUploadBaseStoreImageGrab()
    {
        try {
            $httpCode = 200;
            $user = App::make('orbit.upload.user');

            // Load the orbit configuration for base store upload image
            $uploadImageConfig = Config::get('orbit.upload.base_store.grab_picture');
            $elementName = $uploadImageConfig['name'];

            // Register custom validation
            $storeHelper = StoreHelper::create();
            $storeHelper->storeCustomValidator();

            // Application input
            $base_store_id = OrbitInput::post('base_store_id');
            $images = OrbitInput::files($elementName);

            $messages = array(
                'nomore.than' => Lang::get('validation.max.array', array(
                    'max' => 3
                ))
            );

            $validator = Validator::make(
                array(
                    'base_store_id' => $base_store_id,
                    $elementName    => $images,
                ),
                array(
                    'base_store_id' => 'required|orbit.empty.base_store',
                    $elementName    => 'required|array|nomore.than:3',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadstoreimage.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadstoreimage.after.validation', array($this, $validator));

            // We already had validation base store
            // get it from there no need to re-query the database
            $base_store = $storeHelper->getValidBaseStore();

            // Callback to rename the file, we will format it as follow
            // [BASE_STORE_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($base_store)
            {
                $base_store_id = $base_store->base_store_id;
                $slug = Str::slug($base_store->name);
                $file['new']->name = sprintf('%s-%s-%s', $base_store_id, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadImageConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadstoreimage.before.save', array($this, $base_store, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            // Delete old base_store image
            $pastMedia = Media::where('object_id', $base_store->base_store_id)
                              ->where('object_name', 'base_store')
                              ->where('media_name_id', 'base_store_image_grab');

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
                'id'            => $base_store->base_store_id,
                'name'          => 'base_store',
                'media_name_id' => 'base_store_image_grab',
                'modified_by'   => $user->user_id
            );
            $mediaList = $this->saveMetadata($object, $uploaded);

            // Update the `image` field which store the original path of the image
            // This is temporary since right know the business rules actually
            // only allows one image per product
            if (isset($uploaded[0])) {
                $base_store->save();
            }

            Event::fire('orbit.upload.postuploadstoreimage.after.save', array($this, $base_store, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = 'Base Store Image Grab has been successfully uploaded.';

            // Commit the changes
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadstoreimage.after.commit', array($this, $base_store, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadstoreimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadstoreimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadstoreimage.query.error', array($this, $e));

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
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadstoreimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            if (! $this->calledFrom('basestore.new, basestore.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadstoreimage.before.render', array($this, $output));

        return $output;
    }


    /**
     * Delete images grab for a base store.
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `base_store_id`                (required) - ID of the merchant/retailer
     * @param integer    `picture_index`                (required) - Index of the picture
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteBaseStoreImageGrab()
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
            $validRoles = $this->deleteStoreImageRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            // Register custom validation
            $storeHelper = StoreHelper::create();
            $storeHelper->storeCustomValidator();

            // Application input
            $base_store_id = OrbitInput::post('base_store_id');
            $picture_index = OrbitInput::post('picture_index');

            $validator = Validator::make(
                array(
                    'base_store_id' => $base_store_id,
                    'picture_index' => $picture_index,
                ),
                array(
                    'base_store_id' => 'required|orbit.empty.base_store',
                    'picture_index' => 'array',
                )
            );

            Event::fire('orbit.upload.postdeletebasestoreimage.before.validation', array($this, $validator));

            if (! $this->calledFrom('basestore.new,basestore.update')) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postdeletebasestoreimage.after.validation', array($this, $validator));

            // We already had validation base store
            // get it from there no need to re-query the database
            $base_store = $storeHelper->getValidBaseStore();

            // Delete old base_store image
            $pastMedia = Media::where('object_id', $base_store->base_store_id)
                              ->where('object_name', 'base_store')
                              ->where('media_name_id', 'base_store_image_grab');

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

            Event::fire('orbit.upload.postdeletebasestoreimage.before.save', array($this, $base_store));

            // Update the `image` field which store the original path of the image
            // This is temporary since right now the business rules actually
            // only allows one image per base_store
            $base_store->save();

            Event::fire('orbit.upload.postdeletebasestoreimage.after.save', array($this, $base_store));

            $this->response->data = $base_store;
            $this->response->message = Lang::get('statuses.orbit.uploaded.retailer.delete_image');

            if (! $this->calledFrom('basestore.new,basestore.update')) {
                // Commit the changes
                $this->commit();
            }

            Event::fire('orbit.upload.postdeletebasestoreimage.after.commit', array($this, $base_store));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postdeletebasestoreimage.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('basestore.new,basestore.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postdeletebasestoreimage.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            if (! $this->calledFrom('basestore.new,basestore.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postdeletebasestoreimage.query.error', array($this, $e));

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

            if (! $this->calledFrom('basestore.new,basestore.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postdeletebasestoreimage.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = NULL;

            if (! $this->calledFrom('basestore.new, basestore.update')) {
                // Rollback the changes
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postdeletebasestoreimage.before.render', array($this, $output));

        return $output;
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
     * @author Irianto <irianto@dominopos.com>
     * @param string $from The source of the caller
     * @return StoreUploadAPIController
     */
    public function setCalledFrom($from)
    {
        $this->calledFrom = $from;

        return $this;
    }
}
