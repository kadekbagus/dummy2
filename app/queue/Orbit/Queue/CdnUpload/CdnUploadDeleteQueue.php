<?php namespace Orbit\Queue\CdnUpload;
/**
 * Process queue for upload new image to Amazon s3
 *
 */
use Sync;
use Mail;
use Config;
use DB;
use Aws;
use Media;
use Orbit\Database\ObjectID;
use Log;

class CdnUploadDeleteQueue
{
    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author shelgi prasetyo <shelgi@dominopos.com>
     * @param Job $job
     * @param array $data [user_id => NUM]
     */
    public function fire($job, $data)
    {
        $prefix = DB::getTablePrefix();
        $objectId = $data['object_id'];
        $mediaNameId = $data['media_name_id'];
        $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');
        $contentType = 'image/png';

        try {
            $sdk = new Aws\Sdk(Config::get('orbit.aws-sdk', []));
            $s3 = $sdk->createS3();

            $media = Media::select('media_id', 'path', 'realpath', 'cdn_url', 'cdn_bucket_name')->where('object_id', $objectId);

            if (! empty($mediaNameId)) {
                $media->where('media_name_id', $mediaNameId);
            }

            $media = $media->get();
            $message = array();
            foreach ($media as $file) {
                $response = $s3->deleteObject([
                    'Bucket' => $file->cdn_bucket_name,
                    'Key' => $file->path
                ]);

                $s3Media = Media::find($file->media_id);
                $s3Media->cdn_url = null;
                $s3Media->cdn_bucket_name = null;
                $s3Media->save();

                $contentMessage = sprintf('Delete file from S3; Status: OK; Object_id: %s; Bucket_name: %s; File: %s;',
                                $objectId,
                                $file->cdn_bucket_name,
                                $file->cdn_url);

                if (! empty($mediaNameId)) {
                    $contentMessage = sprintf('Delete file from S3; Status: OK; Media name: %s, Object_id: %s; Bucket_name: %s; File: %s;',
                                $mediaNameId,
                                $objectId,
                                $file->cdn_bucket_name,
                                $file->cdn_url);
                }

                $message[] = $contentMessage;
                Log::info($contentMessage);
            }

            return [
                'status' => 'ok',
                'message' => $message
            ];

        } catch (Aws\S3\Exception\S3Exception $e) {
            $message = sprintf('Delete file from S3; Status: FAIL; Object_id: %s; Message: %s',
                                $objectId,
                                $e->getMessage());

            if (! empty($mediaNameId)) {
                $message = sprintf('Delete file from S3; Status: FAIL; Media name: %s, Object_id: %s; Message: %s',
                            $mediaNameId,
                            $objectId,
                            $e->getMessage());
            }

            Log::info($message);

            return [
                'status' => 'fail',
                'message' => $message
            ];
        }

        // // Don't care if the job success or not we will provide user
        // // another link to resend the activation
        $job->delete();
    }
}