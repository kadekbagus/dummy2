<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\Database\ObjectID;

class CopyStoreDataCommand extends Command {

  /**
   * The console command name.
   *
   * @var string
   */
  protected $name = 'base-store:copy';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'copy base merchant translation and banner image to base store.';

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
    try {
            DB::beginTransaction();
            $updateFlag = false;
            $input = ! empty($this->option('id')) ? $this->option('id') : file_get_contents("php://stdin");
            $input = trim($input);

            if (empty($input)) {
                throw new Exception("Input needed.", 1);
            }

            $baseStore = BaseStore::where('base_store_id', '=', $input)->first();
            if (!$baseStore) {
               throw new Exception(sprintf('store with id "%s" not found', $input), 1);
            }

            $translations = [];
            $baseStoreId = $baseStore->base_store_id;
            $baseMerchantId = $baseStore->base_merchant_id;
            $baseMerchant = BaseMerchant::where('base_merchant_id', '=', $baseMerchantId)->first();
            $merchantName = $baseMerchant->name;

            // copy translation
            $baseMerchantTranslation = BaseMerchantTranslation::where('base_merchant_id', '=', $baseMerchantId)->get();

            if (count($baseMerchantTranslation)) {
              // delete previous translation
                $deleteTranslation = BaseStoreTranslation::where('base_store_id', '=', $baseStoreId)->delete();

                foreach ($baseMerchantTranslation as $base_translation) {
                    $translations[] = [ 'base_store_translation_id' => ObjectID::make(),
                                        'base_store_id' => $baseStoreId,
                                        'language_id' => $base_translation->language_id,
                                        'description' => $base_translation->description,
                                        'custom_title' => $base_translation->custom_title,
                                       "created_at" => date("Y-m-d H:i:s"),
                                       "updated_at" => date("Y-m-d H:i:s") ];
                }
                if (! empty($translations)) {
                  $updateFlag = true;
                  DB::table('base_store_translations')->insert($translations);
                  $this->info(sprintf('translation for "%s" store_id "%s" has been successfully copy', $merchantName, $baseStoreId));
                }
            } else {

              $this->error(sprintf('translation for "%s" store_id "%s" not found', $merchantName, $baseStoreId));
            }

            // copy banner image
            $bannerMerchant = Media::where('object_name', 'base_merchant')
                                    ->where('media_name_id', 'base_merchant_banner')
                                    ->where('object_id', $baseMerchantId)
                                    ->get();

            $storeBanner = array();
            if (count($bannerMerchant)) {
              $path = public_path();
                $baseConfig = Config::get('orbit.upload.base_store');
                $type = 'banner';

                // delete previous store banner
              $pastMedia = Media::where('object_id', $baseStoreId)
                                ->where('object_name', 'base_store')
                                ->where('media_name_id', 'base_store_banner');

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

                foreach ($bannerMerchant as $bm) {

                    $filename = $baseStoreId . '-' . $bm->file_name;
                    $sourceMediaPath = $path . DS . $baseConfig[$type]['path'] . DS . $bm->file_name;
                    $destMediaPath = $path . DS . $baseConfig[$type]['path'] . DS . $filename;

                    if (! @copy($sourceMediaPath, $destMediaPath)) {
                      throw new Exception(sprintf('failed copy banner image for "%s" store_id "%s"', $merchantName, $baseStoreId));
                    }

                    $storeBanner[] = [ "media_id" => ObjectID::make(),
                                       "media_name_id" => 'base_store_banner',
                                       "media_name_long" => str_replace('base_merchant_', 'base_store_', $bm->media_name_long),
                                       "object_id" => $baseStoreId,
                                       "object_name" => 'base_store',
                                       "file_name" => $bm->file_name,
                                       "file_extension" => $bm->file_extension,
                                       "file_size" => $bm->file_size,
                                       "mime_type" => $bm->mime_type,
                                       "path" => $baseConfig[$type]['path'] . DS . $filename,
                                       "realpath" => $destMediaPath,
                                       "cdn_url" => $bm->cdn_url,
                                       "cdn_bucket_name" => $bm->cdn_bucket_name,
                                       "metadata" => $bm->metadata,
                                       "created_at" => date("Y-m-d H:i:s"),
                                       "updated_at" => date("Y-m-d H:i:s")];
                }

              if (! empty($storeBanner)) {
                  $updateFlag = true;
                  DB::table('media')->insert($storeBanner);
                  $this->info(sprintf('banner image for "%s" store_id "%s" has been successfully copy', $merchantName, $baseStoreId));
              }
            } else {
              $this->error(sprintf('banner image for "%s" store_id "%s" not found', $merchantName, $baseStoreId));
            }

            if ($updateFlag) {
                $syncType = 'store';
                $syncData = [$baseStoreId];
                $queueName = Config::get('queue.connections.store_sync.queue', 'store_sync');

                $user = User::where('username', 'orbitadmin')->firstOrFail();

                $this->info(sprintf('running store sync with store_id "%s" user "%s"', $baseStoreId, $user->user_email));

                // queue for data synchronization
                Queue::push('Orbit\\Queue\\StoreSynchronization', [
                    'sync_type' => $syncType,
                    'sync_data' => $syncData,
                    'user' => $user->user_id
                ], $queueName);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error($e->getMessage());
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
             array('id', null, InputOption::VALUE_OPTIONAL, 'Store id or base store id to copy.', null),
            array('dry-run', null, InputOption::VALUE_NONE, 'Run in dry-run mode, no data will be sent', null),
        );
  }

}



