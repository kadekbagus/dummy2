<?php

use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Intervention\Image\ImageManagerStatic as Image;
use Intervention\Image\File as ImageFile;
use OrbitShop\API\v1\ControllerAPI;

/**
 * Controller for Media related task, all roles should be able to access this
 * controller rather than to use UploadAPIController that duplicates same processes
 * we should create one uniformed controller to handle media everywhere
 */
class MediaAPIController extends ControllerAPI
{
    /** Allowed roles */
    protected $uploadRoles = ['merchant database admin'];

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

            // @todo Validate input
            $objectId = Input::post('object_id');
            $mediaNameId = Input::post('media_name_id');
            $objectName = Config::get('orbit.upload.media.image.media_names.' . $mediaNameId);
            $images = Input::files('images');
            // @todo get file name

            foreach ($images as $image) {
                // Image information to be saved in Media
                $imageMetas = new \stdclass();
                $imageMetas->object_id = $objectId;
                $imageMetas->object_name = $objectName;
                $imageMetas->media_name_id = $mediaNameId;

                $_image = Image::make($image);

                // Check allowed file mime
                if (! in_array($_image->mime(), Config::get('orbit.upload.media.image.mime_type'))) {
                    throw new Exception(sprintf("File type is not supported. (%s)", Config::get('orbit.upload.media.image.mime_type')), 1);
                }

                // Check maximum file size
                if ($_image->filesize() > Config::get('orbit.upload.media.image.file_size')) {
                    throw new Exception(sprintf("File size is too big. (%s kB)", Config::get('orbit.upload.media.image.file_size') / 1000) , 1);
                }

                // save original image @todo change path and filename
                $_image->save(Config::get('orbit.upload.media.image.path'), Config::get('orbit.upload.media.image.qualities.orig'));

                // build original image metas
                $imageMetas->files = [];
                $imageMetas->files['orig'] = $_image;

                // Resize for thumbnails
                // clone original image
                $_image_desktop = clone $_image;
                $_image_mobile = clone $_image;

                $desktopConfig = Config::get('orbit.upload.media.image.thumbnail_size.desktop');
                $mobileConfig = Config::get('orbit.upload.media.image.thumbnail_size.mobile');

                $desktopConstraint = null;
                // fit or resize?
                if ($desktopConfig['crop_fit']) {
                    $_image_desktop->fit($desktopConfig['width'], $desktopConfig['height']);
                } else {
                    if ($desktopConfig['keep_aspect_ratio']) {
                        $desktopConstraint = function ($constraint) {
                            $constraint->aspectRatio();
                        };
                    }
                    $_image_desktop->resize($desktopConfig['width'], null, $desktopConstraint);
                }
                // save image  @todo change path and filename
                $_image_desktop->save(Config::get('orbit.upload.media.image.path'), Config::get('orbit.upload.media.image.qualities.high'));
                // add to image metas
                $imageMetas->files['desktop_thumb'] = $_image_desktop;

                $mobileConstraint = null;
                // fit or resize?
                if ($mobileConfig['crop_fit']) {
                    $_image_mobile->fit($mobileConfig['width'], $mobileConfig['height']);
                } else {
                    if ($mobileConfig['keep_aspect_ratio']) {
                        $mobileConstraint = function ($constraint) {
                            $constraint->aspectRatio();
                        };
                    }
                    $_image_mobile->resize($mobileConfig['width'], null, $mobileConstraint);
                }
                // save image @todo change path and filename
                $_image_mobile->save(Config::get('orbit.upload.media.image.path'), Config::get('orbit.upload.media.image.qualities.high'));
                // add to image metas
                $imageMetas->files['mobile_thumb'] = $_image_mobile;

                // save image with medium quality
                $_image_medium = clone $_image;

                // save image in Media model
                $this->saveMetadata($imageMetas);
            }

            // queue for data amazon s3
            $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);
            if ($usingCdn) {
                $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');
                $queueName = Config::get('orbit.cdn.queue_name', 'cdn_upload');
                // push to queue
                Queue::push('Orbit\\Queue\\CdnUpload\\CdnUploadDeleteQueue', [
                    'object_id'     => $objectId,
                    'media_name_id' => $mediaNameId,
                    'old_path'      => [],
                    'bucket_name'   => $bucketName
                ], $queueName);
            }

            $this->response->data = new \stdclass();
        } catch (ACLForbiddenException $e) {
            $httpCode = 500;
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
     */
    private function saveMetadata($imageMetas)
    {
        // @todo: save each image variation meta to Media
    }
}
