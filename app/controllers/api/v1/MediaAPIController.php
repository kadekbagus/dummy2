<?php

use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Intervention\Image\ImageManagerStatic as Image;
use Intervention\Image\File as ImageFile;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Controller for Media image related task, all roles should be able to access this
 * controller rather than to use UploadAPIController that duplicates same processes
 * we should create one uniformed controller to handle media everywhere
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
class MediaAPIController extends ControllerAPI
{
    /** Allowed roles */
    protected $uploadRoles = ['merchant review admin', 'master review admin', 'consumer', 'merchant database admin', 'article writer', 'article publisher', 'product manager', 'super admin'];

    /**
     * Pass false if called from another Class (if caller Class already emit transaction)
     * false: disable transaction
     *
     * @var string */
    protected $enableTransaction = true;

    /**
     * Custom input file name. Useful for specific input with custom (maybe indexed) name.
     * e.g. banners_image_0, banners_image_1, etc.
     * @var string
     */
    protected $inputName = '';

    /**
     * for bypass role checking (used on bpp)
     *
     *
     * @var string */
    protected $skipRoleChecking = false;

    /**
     * This uploader receive multiple file input and will make 4 variant for each image
     * (original, desktop thumbnail, mobile thumbnail, and medium quality image)
     *
     * Variant: 'orig', 'desktop_thumb', 'mobile_thumb', 'desktop_medium', 'mobile_medium'
     *
     * Input parameters:
     * required string object_id
     * required string media_name_id
     * required array images - image files
     */
    public function upload()
    {
        $httpCode = 200;
        $user = null;

        try {
            // Authenticate
            if (! $this->enableTransaction) {
                // use passed user
                $user = App::make('orbit.upload.user');
            } else {
                $this->checkAuth();
                $user = $this->api->user;
            }

            if (! $this->skipRoleChecking) {
                $role = $user->role;
                if (! in_array(strtolower($role->role_name), $this->uploadRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }
            }

            // Check config for media image upload
            if (empty(Config::get('orbit.upload.media'))) {
                throw new Exception("Image media upload config is not set.", 1);
            }

            $objectId = OrbitInput::post('object_id');
            $mediaNameId = OrbitInput::post('media_name_id');

            if (empty($this->inputName)) {
                $images = Input::file(null);
            }
            else {
                $images = [$this->inputName => Input::file($this->inputName)];
            }

            $mediaNames = implode(',', array_keys(Config::get('orbit.upload.media.image.media_names')));

            $validator = Validator::make(
                array(
                    'media_name_id' => $mediaNameId,
                    'images' => $images,
                ),
                array(
                    'media_name_id' => 'required|in:' . $mediaNames,
                    'images' => 'required|array',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage, 1);
            }

            if ($this->enableTransaction) {
                // Begin database transaction
                $this->beginTransaction();
            }

            if (empty($this->inputName)) {
                $images = $images['images'];
            }

            // TODO: Should be moved somewhere to keep this api clean.
            // Try guessing object id from current User.
            $objectId = $this->getObjectId($objectId, $user);

            // get object name based on media_name_id
            $objectName = Config::get('orbit.upload.media.image.media_names.' . $mediaNameId);

            $filenameFormat = Config::get('orbit.upload.media.image.file_name_format');
            $filepathFormat = Config::get('orbit.upload.media.image.path_format');
            if (! empty($objectId)) {
                $fileDir = sprintf($filepathFormat, $objectName, $objectId, $mediaNameId);
            } else {
                $fileDir = sprintf($filepathFormat, $mediaNameId);
            }

            // returned image data
            $compiledImages = [];

            // create directory
            if (! file_exists($fileDir)) {
                // Try to create the directory
                if (! @mkdir($fileDir, 0777, TRUE)) {
                    throw new Exception('Error. No write access', 1);
                }
            } else {
                // Only check the original upload path value
                if (! is_writable($fileDir)) {
                    throw new Exception('Error. No write access', 1);
                }
            }

            $index = 0;
            $lastImage = Media::where('object_id', $objectId)
                ->where('object_name', $objectName)
                ->where('media_name_id', $mediaNameId)
                ->orderBy('media_id', 'desc')
                ->first();

            $lastImageOrder = 0;
            if (is_object($lastImage)) {
                $lastImageOrder = (int) substr($lastImage->metadata, 4);
            }

            foreach ($images as $image) {
                $returnedImage = new \stdclass();
                $returnedImage->index = $index;
                $origFileName = $image->getClientOriginalName();
                $fileExtension = strtolower(substr(strrchr($origFileName, '.'), 1));

                // Image information to be saved in Media
                $imageMetas = new \stdclass();

                $imageMetas->object_id = null;
                $imageMetas->object_name = null;
                if (! empty($objectId)) {
                    $imageMetas->object_id = $objectId;
                    $imageMetas->object_name = $objectName;
                }
                $imageMetas->media_name_id = $mediaNameId;
                $imageMetas->modified_by = $user->user_id;
                $imageMetas->last_image_order = $lastImageOrder;

                $_image = Image::make($image->getRealPath())->orientate();

                // Check allowed file mime
                if (! in_array($_image->mime(), Config::get('orbit.upload.media.image.mime_type'))) {
                    throw new Exception(sprintf("File type is not supported. (%s)", Config::get('orbit.upload.media.image.mime_type')), 1);
                }

                // Check maximum file size
                if ($_image->filesize() > Config::get('orbit.upload.media.image.file_size')) {
                    throw new Exception(sprintf("File size is too big. (%s kB)", Config::get('orbit.upload.media.image.file_size') / 1000) , 1);
                }

                // save original image
                $_imagePath = $fileDir . sprintf($filenameFormat, $objectId, 'orig', str_replace('.', '', microtime(true)), $fileExtension);
                $_image->save($_imagePath, Config::get('orbit.upload.media.image.qualities.orig'));

                // build original image metas
                $imageMetas->files = [];
                $imageMetas->files['orig'] = $_image;

                // Resize for thumbnails
                // clone original image
                $_imageDesktop = clone $_image;
                $_imageMobile = clone $_image;

                $desktopConfig = Config::get('orbit.upload.media.image.thumbnail_size.desktop');
                $mobileConfig = Config::get('orbit.upload.media.image.thumbnail_size.mobile');

                $desktopConstraint = null;
                // fit or resize?
                if ($desktopConfig['crop_fit']) {
                    $_imageDesktop->fit($desktopConfig['width'], $desktopConfig['height']);
                } else {
                    if ($desktopConfig['keep_aspect_ratio']) {
                        $desktopConstraint = function ($constraint) {
                            $constraint->aspectRatio();
                        };
                    }
                    $_imageDesktop->resize($desktopConfig['width'], null, $desktopConstraint);
                }
                // save image
                $_imageDesktopPath = $fileDir . sprintf($filenameFormat, $objectId, 'd-thumb', str_replace('.', '', microtime(true)), $fileExtension);
                $_imageDesktop->save($_imageDesktopPath, Config::get('orbit.upload.media.image.qualities.medium'));
                // add to image metas
                $imageMetas->files['desktop_thumb'] = $_imageDesktop;

                $mobileConstraint = null;
                // fit or resize?
                if ($mobileConfig['crop_fit']) {
                    $_imageMobile->fit($mobileConfig['width'], $mobileConfig['height']);
                } else {
                    if ($mobileConfig['keep_aspect_ratio']) {
                        $mobileConstraint = function ($constraint) {
                            $constraint->aspectRatio();
                        };
                    }
                    $_imageMobile->resize($mobileConfig['width'], null, $mobileConstraint);
                }
                // save image
                $_imageMobilePath = $fileDir . sprintf($filenameFormat, $objectId, 'm-thumb', str_replace('.', '', microtime(true)), $fileExtension);
                $_imageMobile->save($_imageMobilePath, Config::get('orbit.upload.media.image.qualities.medium'));
                // add to image metas
                $imageMetas->files['mobile_thumb'] = $_imageMobile;

                // save image with medium quality
                $_imageMediumQuality = clone $_image;
                $_imageMediumQualityPath = $fileDir . sprintf($filenameFormat, $objectId, 'd-med', str_replace('.', '', microtime(true)), $fileExtension);
                $_imageMediumQuality->save($_imageMediumQualityPath, Config::get('orbit.upload.media.image.qualities.medium'));
                // add to image metas
                $imageMetas->files['desktop_medium'] = $_imageMediumQuality;

                // save image with medium quality for mobile
                $_imageMediumQualityMobile = clone $_image;
                $_imageMediumQualityMobilePath = $fileDir . sprintf($filenameFormat, $objectId, 'm-med', str_replace('.', '', microtime(true)), $fileExtension);
                $_imageMediumQualityMobile->resize(Config::get('orbit.upload.media.image.mobile_size.width'), null, function ($constraint) {
                            $constraint->aspectRatio();
                        })
                    ->save($_imageMediumQualityMobilePath, Config::get('orbit.upload.media.image.qualities.medium'));
                // add to image metas
                $imageMetas->files['mobile_medium'] = $_imageMediumQualityMobile;

                // save image in Media model
                $savedData = $this->saveMetadata($imageMetas);
                $returnedImage->variants = $savedData;
                $compiledImages[] = $returnedImage;
                $index++;
                $lastImageOrder++;
            }

            if ($this->enableTransaction) {
                // Commit the changes
                $this->commit();
            }

            $this->response->data = $compiledImages;

        } catch (ACLForbiddenException $e) {
            $httpCode = 500;
            if ($this->enableTransaction) {
                $this->rollBack();
            }
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = Config::get('app.debug') ? [$e->getFile(), $e->getLine()] : null;
            $this->response->message = $e->getMessage();
        } catch (InvalidArgsException $e) {
            $httpCode = 500;
            if ($this->enableTransaction) {
                $this->rollBack();
            }
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = Config::get('app.debug') ? [$e->getFile(), $e->getLine()] : null;
            $this->response->message = $e->getMessage();
        } catch (QueryException $e) {
            $httpCode = 500;
            if ($this->enableTransaction) {
                $this->rollBack();
            }
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = Config::get('app.debug') ? [$e->getFile(), $e->getLine()] : null;
            $this->response->message = $e->getMessage();
        } catch (\Exception $e) {
            $httpCode = 500;
            if ($this->enableTransaction) {
                $this->rollBack();
            }
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = Config::get('app.debug') ? [$e->getFile(), $e->getLine()] : null;
            $this->response->message = $e->getMessage();
        }

        return $this->render($httpCode);
    }

    /**
     * Delete single image (and variants) and meta data by Original Media ID
     *
     * Input parameters:
     * required string media_id - one of the media image variant
     */
    public function delete()
    {
        $httpCode = 200;
        $user = null;

        try {
            // Authenticate
            if (! $this->enableTransaction) {
                // use passed user
                $user = App::make('orbit.upload.user');
            } else {
                $this->checkAuth();
                $user = $this->api->user;
            }
            $role = $user->role;
            if (! in_array( strtolower($role->role_name), $this->uploadRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            // Check config for media image upload
            if (empty(Config::get('orbit.upload.media'))) {
                throw new Exception("Image media upload config is not set.", 1);
            }

            $mediaId = OrbitInput::post('media_id');

            $validator = Validator::make(
                array(
                    'media_id' => $mediaId
                ),
                array(
                    'media_id' => 'required'
                )
            );


            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage, 1);
            }

            if ($this->enableTransaction) {
                // Begin database transaction
                $this->beginTransaction();
            }

            // get 1 media variant id
            $media = Media::where('media_id', $mediaId)->firstOrFail();

            $objectId = $media->object_id;
            $mediaNameId = $media->media_name_id;
            $metadata = $media->metadata;
            // get object name based on media_name_id
            $objectName = Config::get('orbit.upload.media.image.media_names.' . $mediaNameId);

            // get all variant from the given media ID
            $deletedMedias = Media::where('object_id', $objectId)
                ->where('object_name', $objectName)
                ->where('media_name_id', $mediaNameId)
                ->where('metadata', $metadata)
                ->get();

            foreach ($deletedMedias as $deletedMedia) {
                $oldPath = [];

                //get old path before delete
                $oldPath[$deletedMedia->media_id]['path'] = $deletedMedia->path;
                $oldPath[$deletedMedia->media_id]['cdn_url'] = $deletedMedia->cdn_url;
                $oldPath[$deletedMedia->media_id]['cdn_bucket_name'] = $deletedMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($deletedMedia->realpath);

                // queue for uploading image to amazon s3
                $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);

                // delete file from S3
                if ($usingCdn) {
                    $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');
                    $queueName = Config::get('orbit.cdn.queue_name', 'cdn_upload');

                    Queue::push('Orbit\\Queue\\CdnUpload\\CdnUploadDeleteQueue', [
                        'object_id'     => $objectId,
                        'media_name_id' => $mediaNameId,
                        'old_path'      => $oldPath,
                        'es_type'       => null,
                        'es_id'         => null,
                        'bucket_name'   => $bucketName
                    ], $queueName);
                }

                $deletedMedia->delete(true);
            }

            if ($this->enableTransaction) {
                // Commit the changes
                $this->commit();
            }

            $this->response->data = $objectId;

        } catch (ACLForbiddenException $e) {
            $httpCode = 500;
            if ($this->enableTransaction) {
                $this->rollBack();
            }
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = Config::get('app.debug') ? [$e->getFile(), $e->getLine()] : null;
            $this->response->message = $e->getMessage();
        } catch (InvalidArgsException $e) {
            $httpCode = 500;
            if ($this->enableTransaction) {
                $this->rollBack();
            }
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = Config::get('app.debug') ? [$e->getFile(), $e->getLine()] : null;
            $this->response->message = $e->getMessage();
        } catch (QueryException $e) {
            $httpCode = 500;
            if ($this->enableTransaction) {
                $this->rollBack();
            }
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = Config::get('app.debug') ? [$e->getFile(), $e->getLine()] : null;
            $this->response->message = $e->getMessage();
        } catch (\Exception $e) {
            $httpCode = 500;
            if ($this->enableTransaction) {
                $this->rollBack();
            }
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = Config::get('app.debug') ? [$e->getFile(), $e->getLine()] : null;
            $this->response->message = $e->getMessage();
        }

        return $this->render($httpCode);
    }

    /**
     * List image Media by object ID
     *
     * Input parameters:
     * required string object_id
     * required string media_name_id
     * optional string thumb_only ('y'/ else)
     *
     * @todo Find a way to group the result by image
     */
    public function get()
    {
        $httpCode = 200;
        $user = null;

        try {
            // Authenticate
            $this->checkAuth();
            $user = $this->api->user;
            $role = $user->role;
            if (! in_array( strtolower($role->role_name), $this->uploadRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            // Check config for media image upload
            if (empty(Config::get('orbit.upload.media'))) {
                throw new Exception("Image media upload config is not set.", 1);
            }

            $objectId = OrbitInput::get('object_id');
            $mediaNameId = OrbitInput::get('media_name_id');

            $mediaNames = implode(',', array_keys(Config::get('orbit.upload.media.image.media_names')));

            // to select only specific variant, none then return all
            $variant = OrbitInput::get('variant');

            $take = OrbitInput::get('take', 500);
            $skip = OrbitInput::get('skip', 0);

            $validator = Validator::make(
                array(
                    'media_name_id' => $mediaNameId,
                    'variant' => $variant,
                ),
                array(
                    'media_name_id' => 'in:' . $mediaNames,
                    'variant' => 'in:orig,desktop_thumb,mobile_thumb,desktop_medium,mobile_medium',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage, 1);
            }

            // get object name based on media_name_id
            $objectName = Config::get('orbit.upload.media.image.media_names.' . $mediaNameId);

            $medias = new Media();

            // TODO: Should be moved somewhere to keep this api clean.
            $objectId = $this->getObjectId($objectId, $user);

            if (! empty($objectId)) {
                $medias = $medias->where('object_id', $objectId);
            }

            if (! empty($objectName)) {
                $medias = $medias->where('object_name', $objectName);
            }

            if (! empty($mediaNameId)) {
                $medias = $medias->where('media_name_id', $mediaNameId);
            }

            if (! empty($mediaNameId) && ! empty($variant)) {
                $medias = $medias->where('media_name_long', sprintf('%s_%s', $mediaNameId, $variant));
            }

            $medias = $medias->take($take)
                ->skip($skip)
                ->get();

            $this->response->data = new stdclass();
            $this->response->data->records = $medias;
            // @todo: use more accurate counter
            // $this->response->data->total_records = $totalRecords;
            // $this->response->data->returned_records = $returnedRecords;

        } catch (ACLForbiddenException $e) {
            $httpCode = 500;
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = Config::get('app.debug') ? [$e->getFile(), $e->getLine()] : null;
            $this->response->message = $e->getMessage();
        } catch (InvalidArgsException $e) {
            $httpCode = 500;
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = Config::get('app.debug') ? [$e->getFile(), $e->getLine()] : null;
            $this->response->message = $e->getMessage();
        } catch (QueryException $e) {
            $httpCode = 500;
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = Config::get('app.debug') ? [$e->getFile(), $e->getLine()] : null;
            $this->response->message = $e->getMessage();
        } catch (\Exception $e) {
            $httpCode = 500;
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = Config::get('app.debug') ? [$e->getFile(), $e->getLine()] : null;
            $this->response->message = $e->getMessage();
        }

        return $this->render($httpCode);
    }

    /**
     * Save image metadata in Media
     * @return array
     */
    private function saveMetadata($imageMetas)
    {
        $result = array();
        $imageMetas->last_image_order = $imageMetas->last_image_order + 1;

        foreach ($imageMetas->files as $variant=>$file) {
            // Save each variant
            $media = new Media();
            $media->object_id = $imageMetas->object_id;
            $media->object_name = $imageMetas->object_name;
            $media->media_name_id = $imageMetas->media_name_id;
            $media->media_name_long = sprintf('%s_%s', $imageMetas->media_name_id, $variant);
            $media->file_name = $file->filename . '.' . $file->extension;
            $media->file_extension = $file->extension;
            $media->file_size = $file->filesize();
            $media->mime_type = $file->mime;
            $media->path = $file->basePath();
            $media->realpath = realpath($file->basePath());
            $media->metadata = sprintf('img-%s', $imageMetas->last_image_order);
            $media->modified_by = $imageMetas->modified_by;
            $media->save();
            $result[] = $media;

            // queue for uploading image to amazon s3
            $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);

            if ($usingCdn) {
                $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');
                $queueName = Config::get('orbit.cdn.queue_name', 'cdn_upload');

                Queue::push('Orbit\\Queue\\CdnUpload\\CdnUploadNewQueue', [
                    'object_id'     => $imageMetas->object_id,
                    'media_name_id' => $imageMetas->media_name_id,
                    'old_path'      => null,
                    'es_type'       => null,
                    'es_id'         => null,
                    'es_country'    => null,
                    'bucket_name'   => $bucketName
                ], $queueName);
            }
        }

        return $result;
    }

    /**
     * Set the enableTransaction
     * @param boolean $enabled The source of the caller
     * @return MediaAPIController
     */
    public function setEnableTransaction($enabled = true)
    {
        $this->enableTransaction = $enabled;

        return $this;
    }

    /**
     * Set custom file input name that will be read by Input class.
     *
     * @param string $inputName the name of input that will be read.
     * @return MediaAPIController current instance.
     */
    public function setInputName($inputName = '')
    {
        $this->inputName = $inputName;

        return $this;
    }

    /**
     * So we need to determine object_id that is being linked or will be linked to a media.
     * Normally, it should be passed by api client in the request, but when it is not,
     * we can try guessing the objectId by checking the link between user and some of its relations.
     *
     * At the moment, we can guess by checking relationship between user and UserMerchantReview.
     *
     * We will return null if no object linked to current User.
     * Or will return objectId being passed by api client.
     *
     * @param  [type] $filterLinkedObjectId [description]
     * @param  [type] $objectId           [description]
     * @return [type]                     [description]
     */
    private function getObjectId($objectId = null, $user = null)
    {
        // Available relation...
        $validRelations = ['userMerchantReview'];

        // TODO: Should be moved somewhere to keep this api clean.
        $filterLinkedObjectId = \Input::get('media_relation', null);
        if (! empty($filterLinkedObjectId) && empty($objectId)) {

            // Validate user - object - media relation.
            if (! in_array($filterLinkedObjectId, $validRelations)) {
                throw new Exception("Invalid user relation.", 500);
            }

            $user->load($filterLinkedObjectId);
            $linkToObject = $user->{$filterLinkedObjectId};
            if (! empty($linkToObject)) {
                $objectId = $linkToObject->merchant_id !== 0 ? $linkToObject->merchant_id : null;
            }
        }

        return $objectId;
    }

    /**
     * Set custom file input name that will be read by Input class.
     *
     * @param string $inputName the name of input that will be read.
     * @return MediaAPIController current instance.
     */
    public function setSkipRoleChecking($skip = true)
    {
        $this->skipRoleChecking = $skip;

        return $this;
    }
}
