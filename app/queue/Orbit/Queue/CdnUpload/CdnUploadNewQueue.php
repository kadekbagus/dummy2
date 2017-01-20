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
use Log;
use Queue;
use Orbit\Helper\Util\JobBurier;

class CdnUploadNewQueue
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
        $oldPath = (! empty($data['old_path'])) ? $data['old_path'] : array();
        $esType = (! empty($data['es_type'])) ? $data['es_type'] : '';
        $esId = (! empty($data['es_id'])) ? $data['es_id'] : '';
        $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');

        try {
            $sdk = new Aws\Sdk(Config::get('orbit.aws-sdk', []));
            $s3 = $sdk->createS3();

            $localMedia = Media::select('media_id', 'path', 'realpath', 'cdn_url', 'cdn_bucket_name', 'mime_type')->where('object_id', $objectId);

            if (! empty($mediaNameId)) {
                $localMedia->where('media_name_id', $mediaNameId);
            }

            $localMedia = $localMedia->get();
            $message = array();
            foreach ($localMedia as $localFile) {
                $response = $s3->putObject([
                    'Bucket' => $bucketName,
                    'Key' => $localFile->path,
                    'SourceFile' => $localFile->realpath,
                    'ContentType' => $localFile->mime_type
                ]);

                $s3Media = Media::find($localFile->media_id);
                $s3Media->cdn_url = $response['ObjectURL'];
                $s3Media->cdn_bucket_name = $bucketName;
                $s3Media->save();

                $contentMessage = sprintf('Upload file to S3; Status: OK; Object_id: %s; Bucket_name: %s; File: %s;',
                                $objectId,
                                $bucketName,
                                $response['ObjectURL']);

                if (! empty($mediaNameId)) {
                    $contentMessage = sprintf('Upload file to S3; Status: OK; Media name: %s, Object_id: %s; Bucket_name: %s; File: %s;',
                                $mediaNameId,
                                $objectId,
                                $bucketName,
                                $response['ObjectURL']);
                }

                $message[] = $contentMessage;
                Log::info($contentMessage);
            }

            switch ($esType) {
                case 'news':
                    Queue::push('Orbit\\Queue\\Elasticsearch\\ESNewsUpdateQueue', [
                        'news_id' => $esId
                    ]);
                    break;

                case 'promotion':
                    Queue::push('Orbit\\Queue\\Elasticsearch\\ESPromotionUpdateQueue', [
                        'news_id' => $esId
                    ]);
                    break;

                case 'coupon':
                    Queue::push('Orbit\\Queue\\Elasticsearch\\ESCouponUpdateQueue', [
                        'coupon_id' => $esId
                    ]);
                    break;

                case 'mall':
                    Queue::push('Orbit\\Queue\\Elasticsearch\\ESMallUpdateQueue', [
                        'mall_id' => $esId
                    ]);
                    break;

                case 'store':
                    // to be edit
                    break;
            }

            // // Don't care if the job success or not we will provide user
            // // another link to resend the activation
            $job->delete();

            return [
                'status' => 'ok',
                'message' => $message
            ];
        } catch (Aws\S3\Exception\S3Exception $e) {
            $message = sprintf('Upload file to S3; Status: FAIL; Object_id: %s; Message: %s',
                                $objectId,
                                $e->getMessage());

            if (! empty($mediaNameId)) {
                $message = sprintf('Upload file to S3; Status: FAIL; Media name: %s, Object_id: %s; Message: %s',
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