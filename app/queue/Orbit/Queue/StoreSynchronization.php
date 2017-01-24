<?php namespace Orbit\Queue;
/**
 * Process queue for store synchronization
 * in merchant database manager app
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */
use BaseMerchant;
use BaseMerchantCategory;
use BaseMerchantTranslation;
use BaseStore;
use BaseMerchantKeyword;
use Tenant;
use KeywordObject;
use CategoryMerchant;
use ObjectPartner;
use BaseObjectPartner;
use MerchantTranslation;
use Media;
use PreSync;
use Sync;
use Config;
use DB;
use Carbon\Carbon as Carbon;
use Orbit\Database\ObjectID;
use User;
use Event;
use Helper\EloquentRecordCounter as RecordCounter;
use Queue;
use Orbit\FakeJob;

class StoreSynchronization
{
    protected $debug = FALSE;

    /**
     * Laravel main method to fire a job on a queue.
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     * @param Job $job
     * @param array $data [sync_type => store, sync_data=>array(), user]
     */
    public function fire($job, $data)
    {

        $type = $data['sync_type'];

        switch ($type) {
            default:
            case 'store':
                $this->syncStore($data, 'store');
                break;

            case 'merchant':
                $this->syncStore($data, 'merchant');
                break;
        }

        // Don't care if the job success or not we will provide user
        // another link to resend the activation
        $job->delete();
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    protected function syncStore($data, $type) {
        try {
            $prefix = DB::getTablePrefix();
            $sync_data = $data['sync_data'];
            $user_id = $data['user'];
            $chunk = Config::get('orbit.mdm.synchronization.chunk', 50);

            $user = User::where('user_id', $user_id)->firstOrFail();

            $stores = BaseStore::getAllPreSyncStore();

            if (is_array($sync_data)) {
                switch ($type) {
                    case 'merchant':
                        $filter = 'base_merchants.base_merchant_id';
                        break;

                    default:
                    case 'store':
                        $filter = 'base_stores.base_store_id';
                        break;
                }

                $stores->whereIn($filter, $sync_data);
            }

            DB::beginTransaction();

            $newSync = new Sync;
            $newSync->user_id = $user_id;
            $newSync->sync_type = 'store';
            $newSync->total_sync = RecordCounter::create($stores)->count();
            $newSync->finish_sync = 0;
            $newSync->save();

            $sync_id = $newSync->sync_id;

            DB::commit();

            Event::fire('orbit.basestore.sync.begin', $newSync);

            $pre_stores = clone $stores;

            $pre_stores->chunk($chunk, function($_pre_stores) use ($sync_id, $user)
            {
                DB::beginTransaction();
                // insert table presync
                $presyncData = array();
                foreach ($_pre_stores as $pre)
                {
                    $presyncData[] = [ "pre_sync_id" => ObjectID::make(),
                                       "sync_id" => $sync_id,
                                       "object_id" => $pre->base_store_id,
                                       "object_type" => 'store',
                                       "created_at" => date("Y-m-d H:i:s"),
                                       "updated_at" => date("Y-m-d H:i:s") ];

                    $message = sprintf('*** Store synchronization, pre_sync -- sync_id: %s, store_id: %s; store_name: %s, location_id: %s, location_name: %s, user_email: %s ***',
                                    $sync_id,
                                    $pre->base_store_id,
                                    $pre->name,
                                    $pre->merchant_id,
                                    $pre->location_name,
                                    $user->user_email);
                    $this->debug($message . "\n");
                    \Log::info($message);
                }

                if (! empty($presyncData)) {
                    DB::table('pre_syncs')->insert($presyncData);
                }

                DB::commit();
            });

            $storeName = '';
            $stores->chunk($chunk, function($_stores) use ($sync_id, $user, &$storeName)
            {
                foreach ($_stores as $store) {
                    $this->debug(sprintf("memory usage: %s\n", memory_get_peak_usage() / 1024));

                    // Begin database transaction
                    DB::beginTransaction();
                    $base_store_id = $store->base_store_id;
                    $base_merchant_id = $store->base_merchant_id;

                    //save to orb_merchant
                    $tenant = Tenant::where('merchant_id', $base_store_id)->first();
                    if (! is_object($tenant)) {
                        $tenant = new Tenant;
                    }

                    $storeName = $store->name;
                    $tenant->merchant_id = $base_store_id;
                    $tenant->name = $store->name;
                    $tenant->description = $store->description;
                    $tenant->status = $store->status;
                    $tenant->logo = $store->path;
                    $tenant->object_type = 'tenant';
                    $tenant->parent_id = $store->merchant_id;
                    $tenant->is_mall = 'no';
                    $tenant->is_subscribed = 'Y';
                    $tenant->url = $store->url;
                    $tenant->floor_id = empty($store->floor_id) ? 0 : $store->floor_id;
                    $tenant->floor = $store->object_name;
                    $tenant->unit = $store->unit;
                    $tenant->phone = $store->phone;
                    $tenant->masterbox_number = $store->verification_number;
                    $tenant->save();

                    // save to table merchant_translation
                    // delete translation
                    $delete_translation = MerchantTranslation::where('merchant_id', $base_store_id)->delete(true);
                    // insert translation
                    $base_translations = BaseMerchantTranslation::where('base_merchant_id', $base_merchant_id)->get();
                    $translations = array();
                    foreach ($base_translations as $base_translation) {
                        $translations[] = [ 'merchant_translation_id' => ObjectID::make(),
                                            'merchant_id' => $base_store_id,
                                            'merchant_language_id' => $base_translation->language_id,
                                            'description' => $base_translation->description,
                                            'created_by' => 0,
                                            'modified_by' => 0,
                                           "created_at" => date("Y-m-d H:i:s"),
                                           "updated_at" => date("Y-m-d H:i:s") ];
                    }
                    if (! empty($translations)) {
                        DB::table('merchant_translations')->insert($translations);
                    }

                    // save to keyword_object
                    // delete keyword
                    $delete_keyword = KeywordObject::where('object_id', $base_store_id)->delete(true);
                    // insert keyword
                    $base_keywords = BaseMerchantKeyword::where('base_merchant_id', $base_merchant_id)->get();
                    $keywords = array();
                    foreach ($base_keywords as $base_keyword) {
                        $keywords[] = [ 'keyword_object_id' => ObjectID::make(),
                                        'keyword_id' => $base_keyword->keyword_id,
                                        'object_type' => 'tenant',
                                        'object_id' => $base_store_id,
                                           "created_at" => date("Y-m-d H:i:s"),
                                           "updated_at" => date("Y-m-d H:i:s") ];
                    }
                    if (! empty($keywords)) {
                        DB::table('keyword_object')->insert($keywords);
                    }

                    // save to category
                    // delete category
                    $delete_category = CategoryMerchant::where('merchant_id', $base_store_id)->delete(true);
                    // insert category
                    $base_categories = BaseMerchantCategory::join('categories', 'categories.category_id', '=', 'base_merchant_category.category_id')
                                                            ->where('base_merchant_category.base_merchant_id', $base_merchant_id)
                                                            ->where('categories.status', '!=', 'deleted')->get();
                    $categories = array();
                    foreach ($base_categories as $base_category) {
                        $categories[] = [ 'category_merchant_id' => ObjectID::make(),
                                          'category_id' => $base_category->category_id,
                                          'merchant_id' => $base_store_id,
                                           "created_at" => date("Y-m-d H:i:s"),
                                           "updated_at" => date("Y-m-d H:i:s") ];
                    }
                    if (! empty($categories)) {
                        DB::table('category_merchant')->insert($categories);
                    }

                    // save to object_partner
                    // delete object_partner
                    $delete_object_partner = ObjectPartner::where('object_id', $base_store_id)->where('object_type', 'tenant')->delete(true);
                    // insert object_partner
                    $base_object_partners = BaseObjectPartner::join('partners', 'partners.partner_id', '=', 'base_object_partner.partner_id')
                                                            ->where('base_object_partner.object_id', $base_merchant_id)
                                                            ->where('base_object_partner.object_type', 'tenant')->get();
                    $object_partner = array();
                    foreach ($base_object_partners as $base_object_partner) {
                        $object_partner[] = [ 'object_partner_id' => ObjectID::make(),
                                          'object_id' => $base_store_id,
                                          'object_type' => 'tenant',
                                          'partner_id' => $base_object_partner->partner_id,
                                           "created_at" => date("Y-m-d H:i:s"),
                                           "updated_at" => date("Y-m-d H:i:s") ];
                    }
                    if (! empty($object_partner)) {
                        DB::table('object_partner')->insert($object_partner);
                    }

                    // save to media
                    // delete media (logo, image, map)
                    $oldMedia = Media::where('object_name', 'retailer')->where('object_id', $base_store_id)->get();
                    $realpath = array();
                    $oldPath = array();
                    $isUpdate = false;

                    foreach ($oldMedia as $file) {
                        $isUpdate = true;
                        $realpath[] = $file->realpath;

                        //get old path before delete
                        $oldPath[$file->media_id]['path'] = $file->path;
                        $oldPath[$file->media_id]['cdn_url'] = $file->cdn_url;
                        $oldPath[$file->media_id]['cdn_bucket_name'] = $file->cdn_bucket_name;

                        // No need to check the return status, just delete and forget
                        @unlink($file->realpath);
                    }

                    // queue for data amazon s3
                    $fakeJob = new FakeJob();
                    $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);
                    $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');
                    $queueName = Config::get('orbit.cdn.queue_name', 'cdn_upload');

                    if ($usingCdn) {
                        $data = [
                            'object_id'     => $base_store_id,
                            'media_name_id' => null,
                            'old_path'      => $oldPath,
                            'es_type'       => 'store',
                            'es_id'         => $storeName,
                            'bucket_name'   => $bucketName
                        ];

                        $esQueue = new \Orbit\Queue\CdnUpload\CdnUploadDeleteQueue();
                        $response = $esQueue->fire($fakeJob, $data);
                    }
                    $delete_media = Media::where('object_name', 'retailer')->where('object_id', $base_store_id)->delete(true);

                    // copy logo from base_store directory to retailer directory
                    $logo = Media::where('object_name', 'base_merchant')
                                ->where('media_name_id', 'base_merchant_logo')
                                ->where('object_id', $base_merchant_id)
                                ->get();
                    $logo_image = $this->updateMedia('logo', $logo, $base_store_id);

                    // copy picture from base_store directory to retailer directory
                    $pic = Media::where('object_name', 'base_store')
                                ->where('media_name_id', 'base_store_image')
                                ->where('object_id', $base_store_id)
                                ->get();
                    $pic_image = $this->updateMedia('picture', $pic, $base_store_id);

                    // copy map from base_store directory to retailer directory
                    $map = Media::where('object_name', 'base_store')
                                ->where('media_name_id', 'base_store_map')
                                ->where('object_id', $base_store_id)
                                ->get();
                    $map_image = $this->updateMedia('map', $map, $base_store_id);

                    $images = array_merge($logo_image, $pic_image, $map_image);

                    // get presync data
                    $presync = PreSync::where('sync_id', $sync_id)
                                    ->where('object_type', 'store')
                                    ->where('object_id', $base_store_id)
                                    ->first();
                    // move to post_sync
                    $postsync = $presync->moveToPostSync();
                    // increment finish_sync value
                    $sync = $postsync->incrementSyncCounter();

                    if ($sync->isCompleted()) {
                        // if total_sync === finish_sync, update status to done
                        $sync->done();
                    }

                    DB::commit();

                    $message = sprintf('*** Store synchronization success, store_id: %s; store_name: %s, location_id: %s, location_name: %s, user_email: %s ***',
                                    $base_store_id,
                                    $store->name,
                                    $store->merchant_id,
                                    $store->location_name,
                                    $user->user_email);
                    $this->debug($message . "\n");
                    \Log::info($message);

                    $delete_images = array_diff($realpath, $images);
                    foreach ($delete_images as $rp) {
                        $this->debug(sprintf("Starting to unlink in: %s\n", $rp));
                        if (! @unlink($rp)) {
                            $this->debug("Failed to unlink\n");
                        }
                    }

                    // queue for data amazon s3
                    if ($usingCdn) {
                        Queue::push('Orbit\\Queue\\CdnUpload\\CdnUploadNewQueue', [
                            'object_id'     => $base_store_id,
                            'media_name_id' => null,
                            'old_path'      => null,
                            'es_type'       => 'store',
                            'es_id'         => $storeName,
                            'bucket_name'   => $bucketName
                        ], $queueName);
                    }

                }
            });

            if (!empty($storeName)) {
                // Notify the queueing system to update Elasticsearch document
                Queue::push('Orbit\\Queue\\Elasticsearch\\ESStoreUpdateQueue', [
                    'name' => $storeName
                ]);
            }

            Event::fire('orbit.basestore.sync.complete', $newSync);
        } catch (InvalidArgsException $e) {
            \Log::error('*** Store synchronization error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();
        } catch (QueryException $e) {
            \Log::error('*** Store synchronization error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();
        } catch (Exception $e) {
            \Log::error('*** Store synchronization error, messge: ' . $e->getMessage() . '***');
            DB::rollBack();
        }
    }

    protected function updateMedia($type, $data, $store_id) {
        $path = public_path();

        $baseConfig = Config::get('orbit.upload.base_store');
        $retailerConfig = Config::get('orbit.upload.retailer');
        $images = array();

        foreach ($data as $dt) {
            $filename = $dt->file_name;
            switch ($type) {
                case 'logo':
                    $filename = $store_id . '-' . $dt->file_name;
                    $nameid = "retailer_logo";
                    break;

                case 'picture':
                    $nameid = "retailer_image";
                    break;

                case 'map':
                    $nameid = "retailer_map";
                    break;

                default:
                    $nameid = "";
                    break;
            }

            $sourceMediaPath = $path . DS . $baseConfig[$type]['path'] . DS . $dt->file_name;
            $destMediaPath = $path . DS . $retailerConfig[$type]['path'] . DS . $filename;
            $this->debug(sprintf("Starting to copy from: %s to %s\n", $sourceMediaPath, $destMediaPath));
            if (! @copy($sourceMediaPath, $destMediaPath)) {
                $this->debug("Failed to copy\n");
            }

            if ($dt->object_name === 'base_merchant') {
                $name_long = str_replace('base_merchant_', 'retailer_', $dt->media_name_long);
            } else {
                $name_long = str_replace('base_store_', 'retailer_', $dt->media_name_long);
                $name_long = str_replace('base_', 'retailer_', $name_long);
            }

            $newMedia = new Media;
            $newMedia->media_name_id = $nameid;
            $newMedia->media_name_long = $name_long;
            $newMedia->object_id = $store_id;
            $newMedia->object_name = 'retailer';
            $newMedia->file_name = $filename;
            $newMedia->file_extension = $dt->file_extension;
            $newMedia->file_size = $dt->file_size;
            $newMedia->mime_type = $dt->mime_type;
            $newMedia->path = $retailerConfig[$type]['path'] . DS . $filename;
            $newMedia->realpath = $destMediaPath;
            $newMedia->metadata = $dt->metadata;
            $newMedia->modified_by = $dt->modified_by;
            $newMedia->created_at = $dt->created_at;
            $newMedia->updated_at = $dt->updated_at;
            $newMedia->save();

            $images[] = $newMedia->realpath;
        }

        return $images;
    }

    protected function debug($message = '')
    {
        if ($this->debug) {
            echo $message;
        }
    }

    public function setDebug($value)
    {
        $this->debug = $value;
    }
}