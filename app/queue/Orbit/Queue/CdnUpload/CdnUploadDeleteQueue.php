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
        $oldPath = (! empty($data['old_path'])) ? $data['old_path'] : '';
        $esType = (! empty($data['es_type'])) ? $data['es_type'] : '';
        $esId = (! empty($data['es_id'])) ? $data['es_id'] : '';
        $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');

        try {
            $sdk = new Aws\Sdk(Config::get('orbit.aws-sdk', []));
            $s3 = $sdk->createS3();

            // delete old image in s3
            foreach ($oldPath as $oldFile) {
                if (! empty($oldFile['cdn_url'])) {
                    $delResponse = $s3->deleteObject([
                        'Bucket' => $oldFile['cdn_bucket_name'],
                        'Key' => $oldFile['path']
                    ]);

                    $contentMessage = sprintf('Delete file from S3; Status: OK; Object_id: %s; Bucket_name: %s; File: %s;',
                                $objectId,
                                $oldFile['cdn_bucket_name'],
                                $oldFile['cdn_url']);

                    if (! empty($mediaNameId)) {
                        $contentMessage = sprintf('Delete file from S3; Status: OK; Media name: %s, Object_id: %s; Bucket_name: %s; File: %s;',
                                    $mediaNameId,
                                    $objectId,
                                    $oldFile['cdn_bucket_name'],
                                    $oldFile['cdn_url']);
                    }

                    $message[] = $contentMessage;
                    Log::info($contentMessage);
                }
            }

            // // Don't care if the job success or not we will provide user
            // // another link to resend the activation
            $job->delete();

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
        }

        // Bury the job for later inspection
        JobBurier::create($job, function($theJob) {
            // The queue driver does not support bury.
            $theJob->delete();
        })->bury();

        return [
            'status' => 'fail',
            'message' => $message
        ];
    }
}