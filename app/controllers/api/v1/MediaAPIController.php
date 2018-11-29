<?php

use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Intervention\Image\ImageManagerStatic as Image;
use Intervention\Image\File as ImageFile;
use OrbitShop\API\v1\ControllerAPI;

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
    protected $uploadRoles = ['merchant database admin'];

    /**
     * * This uploader receive multiple file input and will make 4 variant for each image
     * (original, desktop thumbnail, mobile thumbnail, and medium quality image)
     *
     * Variant: 'orig', 'desktop_thumb', 'mobile_thumb', 'medium_quality'
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

            $objectId = Input::post('object_id');
            $mediaNameId = Input::post('media_name_id');
            $images = Input::files('images');

            $mediaNames = implode(',', array_keys(Config::get('orbit.upload.media.image.media_names')));

            $validator = Validator::make(
                array(
                    'object_id' => $objectId,
                    'media_name_id' => $mediaNameId,
                    'images' => $images,
                ),
                array(
                    'object_id' => 'required',
                    'media_name_id' => 'required|in:' . $mediaNames,
                    'images' => 'required|array',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage, 1);
            }

            // Begin database transaction
            $this->beginTransaction();

            // get object name based on media_name_id
            $objectName = Config::get('orbit.upload.media.image.media_names.' . $mediaNameId);

            $filenameFormat = Config::get('orbit.upload.media.image.file_name_format');
            $filepathFormat = Config::get('orbit.upload.media.image.path_format');
            // returned image data
            $compiledImages = [];

            foreach ($images as $i => $image) {
                $returnedImage = new \stdclass();
                $returnedImage->index = $i;

                $origFileName = $image->getClientOriginalName();
                $fileExtension = strtolower(substr(strrchr($origFileName, '.'), 1));

                // Image information to be saved in Media
                $imageMetas = new \stdclass();
                $imageMetas->object_id = $objectId;
                $imageMetas->object_name = $objectName;
                $imageMetas->media_name_id = $mediaNameId;
                $imageMetas->modified_by = $user->user_id;

                $_image = Image::make($image);

                // Check allowed file mime
                if (! in_array($_image->mime(), Config::get('orbit.upload.media.image.mime_type'))) {
                    throw new Exception(sprintf("File type is not supported. (%s)", Config::get('orbit.upload.media.image.mime_type')), 1);
                }

                // Check maximum file size
                if ($_image->filesize() > Config::get('orbit.upload.media.image.file_size')) {
                    throw new Exception(sprintf("File size is too big. (%s kB)", Config::get('orbit.upload.media.image.file_size') / 1000) , 1);
                }

                // save original image
                $_imagePath = sprintf($filepathFormat, $objectName, $objectId, $mediaNameId) . sprintf($filenameFormat, time(), $objectId, 'orig', $fileExtension);
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
                $_imageDesktopPath = sprintf($filepathFormat, $objectName, $objectId, $mediaNameId) . sprintf($filenameFormat, time(), $objectId, 'd-thumb', $fileExtension);
                $_imageDesktop->save($_imageDesktopPath, Config::get('orbit.upload.media.image.qualities.high'));
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
                $_imageMobilePath = sprintf($filepathFormat, $objectName, $objectId, $mediaNameId) . sprintf($filenameFormat, time(), $objectId, 'm-thumb', $fileExtension);
                $_imageMobile->save($_imageMobilePath, Config::get('orbit.upload.media.image.qualities.high'));
                // add to image metas
                $imageMetas->files['mobile_thumb'] = $_imageMobile;

                // save image with medium quality
                $_imageMediumQuality = clone $_image;
                $_imageMediumQualityPath = sprintf($filepathFormat, $objectName, $objectId, $mediaNameId) . sprintf($filenameFormat, time(), $objectId, 'q-med', $fileExtension);
                $_imageMediumQuality->save($_imageMediumQualityPath, Config::get('orbit.upload.media.image.qualities.medium'));
                // add to image metas
                $imageMetas->files['medium_quality'] = $_imageMediumQuality;

                // save image in Media model
                $savedData = $this->saveMetadata($imageMetas);
                $returnedImage->variants = $savedData;
                $compiledImages[] = $returnedImage;
            }

            // Commit the changes
            $this->commit();

            $this->response->data = $compiledImages;

        } catch (ACLForbiddenException $e) {
            $httpCode = 500;
            $this->rollBack();
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = [$e->getFile(), $e->getLine()];
            $this->response->message = $e->getMessage();
        } catch (InvalidArgsException $e) {
            $httpCode = 500;
            $this->rollBack();
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = [$e->getFile(), $e->getLine()];
            $this->response->message = $e->getMessage();
        } catch (QueryException $e) {
            $httpCode = 500;
            $this->rollBack();
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = [$e->getFile(), $e->getLine()];
            $this->response->message = $e->getMessage();
        } catch (\Exception $e) {
            $httpCode = 500;
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = [$e->getFile(), $e->getLine()];
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

            $mediaId = Input::post('media_id');

            $validator = Validator::make(
                array(
                    'media_id' => $mediaId
                ),
                array(
                    'object_id' => 'required'
                )
            );


            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage, 1);
            }

            // Begin database transaction
            $this->beginTransaction();

            // get 1 media variant id
            $media = Media::where('media_id', $mediaId)->firstOrFail();

            $objectId = $media->object_id;
            $mediaNameId = $media->media_name_id;
            // get object name based on media_name_id
            $objectName = Config::get('orbit.upload.media.image.media_names.' . $mediaNameId);

            // get all variant from the given media ID
            $deletedMedias = Media::where('object_id', $objectId)
                ->where('object_name', $objectName)
                ->where('media_name_id', $mediaNameId)
                ->get();

            foreach ($deletedMedias as $deletedMedia) {
                $oldPath = [];

                //get old path before delete
                $oldPath[$deletedMedia->media_id]['path'] = $deletedMedia->path;
                $oldPath[$deletedMedia->media_id]['cdn_url'] = $deletedMedia->cdn_url;
                $oldPath[$deletedMedia->media_id]['cdn_bucket_name'] = $deletedMedia->cdn_bucket_name;

                // No need to check the return status, just delete and forget
                @unlink($deletedMedia->realpath);

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

            // Commit the changes
            $this->commit();

            $this->response->data = $objectId;

        } catch (ACLForbiddenException $e) {
            $httpCode = 500;
            $this->rollBack();
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = [$e->getFile(), $e->getLine()];
            $this->response->message = $e->getMessage();
        } catch (InvalidArgsException $e) {
            $httpCode = 500;
            $this->rollBack();
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = [$e->getFile(), $e->getLine()];
            $this->response->message = $e->getMessage();
        } catch (QueryException $e) {
            $httpCode = 500;
            $this->rollBack();
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = [$e->getFile(), $e->getLine()];
            $this->response->message = $e->getMessage();
        } catch (\Exception $e) {
            $httpCode = 500;
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = [$e->getFile(), $e->getLine()];
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

            $objectId = Input::get('object_id');
            $mediaNameId = Input::get('media_name_id');
            $thumbOnly = Input::get('thumb_only');

            $mediaNames = implode(',', array_keys(Config::get('orbit.upload.media.image.media_names')));

            $validator = Validator::make(
                array(
                    'object_id' => $objectId,
                    'media_name_id' => $mediaNameId,
                ),
                array(
                    'object_id' => 'required',
                    'media_name_id' => 'required|in:' . $mediaNames,
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage, 1);
            }

            // get object name based on media_name_id
            $objectName = Config::get('orbit.upload.media.image.media_names.' . $mediaNameId);

            $medias = Media::where('object_id', $objectId)
                ->where('object_name', $objectName)
                ->where('media_name_id', $mediaNameId);

            if ($thumbOnly === 'y') {
                $medias = $medias->where('media_name_long', sprintf('%s_%s', $mediaNameId, 'desktop_thumb'));
            }

            $medias = $medias->get();

            $this->response->data = $medias;

        } catch (ACLForbiddenException $e) {
            $httpCode = 500;
            $this->rollBack();
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = [$e->getFile(), $e->getLine()];
            $this->response->message = $e->getMessage();
        } catch (InvalidArgsException $e) {
            $httpCode = 500;
            $this->rollBack();
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = [$e->getFile(), $e->getLine()];
            $this->response->message = $e->getMessage();
        } catch (QueryException $e) {
            $httpCode = 500;
            $this->rollBack();
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = [$e->getFile(), $e->getLine()];
            $this->response->message = $e->getMessage();
        } catch (\Exception $e) {
            $httpCode = 500;
            $this->response->code = $httpCode;
            $this->response->status = 'error';
            $this->response->data = [$e->getFile(), $e->getLine()];
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

        $count = 0;
        foreach ($imageMetas->files as $variant=>$file) {
            // Save each variant
            $media = new Media();
            $media->object_id = $imageMetas->object_id;
            $media->object_name = $imageMetas->object_name;
            $media->media_name_id = $imageMetas->media_name_id;
            $media->media_name_long = sprintf('%s_%s', $imageMetas->media_name_id, $variant);
            $media->file_name = $file->filename;
            $media->file_extension = $file->extension;
            $media->file_size = $file->filesize();
            $media->mime_type = $file->mime;
            $media->path = $file->basePath();
            $media->realpath = realpath($file->basePath());
            $media->metadata = 'order-' . $count;
            $media->modified_by = $imageMetas->modified_by;
            $media->save();
            $result[] = $media;
            $count++;

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
}
