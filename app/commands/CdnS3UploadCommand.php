<?php
/**
 * Upload images to Amazon S3.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\Queue\CdnUpload\CdnUploadUpdateQueue;
use Orbit\FakeJob;

class CdnS3UploadCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cdn:s3-upload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upload images to Amazon S3.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $objectType = $this->option('object-type');
        $objectId = $this->option('object-id');
        if (empty($objectId)) {
            $objectId = file_get_contents('php://stdin');
        }
        $objectId = trim($objectId);
        $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');

        switch ($objectType) {
            case 'coupon':
                $object = Coupon::with('translations.media', 'media')->where('promotion_id', $objectId)->first();
                $options = [
                    'primary_key' => 'promotion_id',
                    'translation_primary_key' => 'coupon_translation_id',
                    'using_translation' => TRUE,
                    'bucket_name' => $bucketName
                ];
                $this->genericObjectUpload($object, $objectType, $options);
                break;

            case 'promotion':
                $object = News::with('translations.media', 'media')->where('news_id', $objectId)->first();
                $options = [
                    'primary_key' => 'news_id',
                    'translation_primary_key' => 'news_translation_id',
                    'using_translation' => TRUE,
                    'bucket_name' => $bucketName
                ];
                $this->genericObjectUpload($object, $objectType, $options);
                break;

            case 'news':
                $object = News::with('translations.media', 'media')->where('news_id', $objectId)->first();
                $options = [
                    'primary_key' => 'news_id',
                    'translation_primary_key' => 'news_translation_id',
                    'using_translation' => TRUE,
                    'bucket_name' => $bucketName
                ];
                $this->genericObjectUpload($object, $objectType, $options);
                break;

            case 'mall':
                $object = Mall::with('media')->where('merchant_id', $objectId)->first();
                $options = [
                    'primary_key' => 'merchant_id',
                    'translation_primary_key' => '',
                    'using_translation' => FALSE,
                    'bucket_name' => $bucketName
                ];
                $this->genericObjectUpload($object, $objectType, $options);
                break;

            case 'store':
                $stores = Tenant::with('media')->where('merchant_id', $objectId)->get();
                $options = [
                    'primary_key' => 'merchant_id',
                    'translation_primary_key' => '',
                    'using_translation' => FALSE,
                    'bucket_name' => $bucketName
                ];

                foreach ($stores as $object) {
                    $this->genericObjectUpload($object, $objectType, $options);
                }
                break;

            case 'category':
                $object = Category::with('media')->where('category_id', $objectId)->first();
                $options = [
                    'primary_key' => 'category_id',
                    'translation_primary_key' => '',
                    'using_translation' => FALSE,
                    'bucket_name' => $bucketName
                ];
                $this->genericObjectUpload($object, $objectType, $options);
                break;

            default:
                throw new Exception('Unknown object-type value');
        }
    }

    /**
     * Generic images uploader for various object such as coupon, promotions and events
     *
     * @param Object $object
     * @param string $objectType
     * @param array $options
     * @return void
     */
    protected function genericObjectUpload($object, $objectType, $options)
    {
        $job = new FakeJob();
        $queue = new CdnUploadUpdateQueue();
        $dryRun = $this->option('dry-run');

        // Main media
        foreach ($object->media as $media) {
            $message = sprintf("Uploading %s (%s) file %s...", $objectType, $object->{$options['primary_key']}, $media->file_name);
            if ($dryRun) {
                $this->info('[DRY RUN] ' . $message . 'OK');
                continue;
            }

            $esId = $object->{$options['primary_key']};
            if ($objectType === 'store') {
                $esId = $object->name;
            }

            $queueData = [
                'object_id' => $object->{$options['primary_key']},
                'media_name_id' => $media->media_name_id,
                'old_path' => [], // No need to delete, we just reupload
                'es_type' => $objectType,
                'es_id' => $esId,
                'use_relative_path' => TRUE,
                'bucket_name' => $options['bucket_name']
            ];

            // Upload to S3
            $response = (new CdnUploadUpdateQueue())->fire($job, $queueData);
            if ($response['status'] === 'ok') {
                $this->info($message . 'OK');
                continue;
            }

            $this->error($message . 'FAILED');
        }

        // Translation media
        if (! $options['using_translation']) {
            return;
        }

        $translations = $object->translations;
        foreach ($translations as $translation) {
            foreach ($translation->media as $media) {
                $message = sprintf("Uploading %s translation (%s) file %s...",
                                    $objectType,
                                    $translation->{$options['translation_primary_key']},
                                    $media->file_name);
                if ($dryRun) {
                    $this->info('[DRY RUN] ' . $message . 'OK');
                    continue;
                }

                $queueData = [
                    'object_id' => $translation->{$options['translation_primary_key']},
                    'media_name_id' => $media->media_name_id,
                    'old_path' => [], // No need to delete, we just reupload
                    'es_type' => $objectType,
                    'use_relative_path' => TRUE,
                    'es_id' => $object->{$options['primary_key']},
                    'bucket_name' => $options['bucket_name']
                ];

                // Upload to S3
                $response = (new CdnUploadUpdateQueue())->fire($job, $queueData);
                if ($response['status'] === 'ok') {
                    $this->info($message . 'OK');
                    continue;
                }

                $this->error($message . 'FAILED');
            }
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('dry-run', null, InputOption::VALUE_NONE, 'Dry run, do not upload to s3.', null),
            array('object-type', null, InputOption::VALUE_REQUIRED, 'Name of the object, one of: promotion, coupon, store, event, mall, or category.', null),
            array('object-id', null, InputOption::VALUE_OPTIONAL, 'ID of object, could be one of: promotion id, coupon id, store id, event id, mall id, or category id.', null),
        );
    }

}
