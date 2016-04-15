<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class TenantImport extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'tenant:import';
    private $uploadImage = FALSE;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for importing tenant from json file.';

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

            $merchantId = $this->option('merchant_id');
            $fileName = $this->option('file');

            if (! file_exists($fileName) ) {
               throw new Exception('Could not found json file.');
            }

            $conf = @json_decode(file_get_contents($fileName), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON error: ' . json_last_error_msg());
            }
            $data = $conf;

            $mall = Mall::excludeDeleted()->where('merchant_id', '=', $merchantId)->first();

            if (empty($mall)) {
                throw new Exception('Merchant or mall is not found.');
            } else {
                if ($data['tenant_name'] === '') {
                    throw new Exception('Tenant name cannot empty.');
                }

                foreach ($data['categories'] as $category_name) {
                    $category = Category::where('merchant_id', $merchantId)->where('category_name', $category_name)->first();
                    if (empty($category)) {
                        throw new Exception('Category is not exist.');
                    }
                }
                if ($data['status'] === 'active') {
                    $floor = Object::where('merchant_id', $merchantId)
                                    ->where('object_type', 'floor')
                                    ->where('status', 'active')
                                    ->where('object_name', $data['floor'])
                                    ->first();

                    if (empty($floor)) {
                        throw new Exception('Floor is not exist.');
                    }

                    if ($data['unit'] === '') {
                        throw new Exception('Unit is required.');
                    }
                }

                foreach ($data['description'] as $desc) {
                    $language =  Language::where('name', '=', $desc['language'])->first();
                    if (empty($language)) {
                        throw new Exception('Language is not exist.');
                    }
                    $merchantLanguage = MerchantLanguage::where('merchant_id', '=', $merchantId)
                                                        ->where('status', '=', 'active')
                                                        ->where('language_id', '=', $language->language_id)
                                                        ->first();
                    if (empty($merchantLanguage)) {
                        throw new Exception('Merchant Language is not exist.');
                    }
                }

                DB::beginTransaction();

                $newtenant = new Tenant();
                $newtenant->omid = '';
                $newtenant->orid = '';
                $newtenant->email = '';
                $newtenant->name = $data['tenant_name'];
                $newtenant->description = $data['description'][0]['content'];
                $newtenant->phone = $data['phone_number'];
                $newtenant->parent_id = $merchantId;
                $newtenant->url = $data['url'];
                $newtenant->floor = $data['floor'];
                $newtenant->unit = $data['unit'];
                $newtenant->status = $data['status'];
                $newtenant->logo = $data['images']['tenant_logo'];

                if (! $newtenant->save()) {
                    throw new Exception('Insert Tenant Failed!');
                }
                $this->info(sprintf('Success, Insert %s', $data['tenant_name']));

                $newSpendingRules = new SpendingRule();
                $newSpendingRules->object_id = $newtenant->merchant_id;
                $newSpendingRules->with_spending = 'Y';

                if (! $newSpendingRules->save()) {
                    throw new Exception('Insert Spending Failed!');
                }

                if ($data['facebook_id'] !== '') {
                    $socmedId = SocialMedia::whereSocialMediaCode('facebook')->first()->social_media_id;

                    $merchantSocmed = MerchantSocialMedia::whereMerchantId($merchantId)->whereSocialMediaId($socmedId)->first();

                    if (!$merchantSocmed) {
                        $merchantSocmed = new MerchantSocialMedia;
                        $merchantSocmed->social_media_id = $socmedId;
                        $merchantSocmed->merchant_id = $merchantId;
                    }

                    $merchantSocmed->social_media_uri = $data['facebook_id'];
                    if (! $merchantSocmed->save()) {
                        throw new Exception('Insert Merchant Sosmed Failed!');
                    }
                }

                foreach ($data['categories'] as $category_name) {
                    $category = Category::where('merchant_id', $merchantId)->where('category_name', $category_name)->first();
                    $categoryMerchant = new CategoryMerchant();
                    $categoryMerchant->category_id = $category->category_id;
                    $categoryMerchant->merchant_id = $newtenant->merchant_id;
                    if (! $categoryMerchant->save()) {
                        throw new Exception('Insert Category Failed!');
                    }
                }

                foreach ($data['keyword'] as $keyword) {
                    $keyword_id = null;

                    $keyword = trim($keyword);
                    if (empty($keyword)) {
                        continue;
                    }

                    $existKeyword = Keyword::excludeDeleted()
                        ->where('keyword', '=', $keyword)
                        ->where('merchant_id', '=', $merchantId)
                        ->first();

                    if (empty($existKeyword)) {
                        $newKeyword = new Keyword();
                        $newKeyword->merchant_id = $merchantId;
                        $newKeyword->keyword = $keyword;
                        $newKeyword->status = 'active';
                        if (! $newKeyword->save()) {
                            throw new Exception('Insert Keyword Failed!');
                        }

                        $keyword_id = $newKeyword->keyword_id;
                    } else {
                        $keyword_id = $existKeyword->keyword_id;
                    }

                    $newKeywordObject = new KeywordObject();
                    $newKeywordObject->keyword_id = $keyword_id;
                    $newKeywordObject->object_id = $newtenant->merchant_id;
                    $newKeywordObject->object_type = 'tenant';
                    if (! $newKeywordObject->save()) {
                        throw new Exception('Insert Keyword Object Failed!');
                    }
                }

                foreach ($data['description'] as $desc) {
                    $language =  Language::where('name', '=', $desc['language'])->first();

                    $merchantLanguage = MerchantLanguage::where('merchant_id', '=', $merchantId)
                                                        ->where('status', '=', 'active')
                                                        ->where('language_id', '=', $language->language_id)
                                                        ->first();

                    $tenantTranslation = new MerchantTranslation();
                    $tenantTranslation->merchant_id = $newtenant->merchant_id;
                    $tenantTranslation->merchant_language_id = $language->language_id;
                    $tenantTranslation->description = $desc['content'];
                    $tenantTranslation->status = 'active';
                    if (! $tenantTranslation->save()) {
                        throw new Exception('Insert Tenant Translation Failed!');
                    }
                }



                $images = array();
                foreach ($data['images'] as $key => $image) {
                    if ($key === 'tenant_logo') {
                        if (! file_exists($image)) {
                            throw new Exception( sprintf('File image %s not found.', $image) );
                        }
                        $path_info = pathinfo($image);
                        $images['type'] = 'logo';
                        $images['type_dir'] = 'logo';
                        $images['name'] = $path_info['basename'];
                        $images['path'] = $image;

                        if ($image !== '') {
                            $this->uploadImage = TRUE;
                        }
                        $this->uploadImages($newtenant, $images);
                    }

                    if ($key === 'tenant_images') {
                        foreach ($image as $idx => $tenant_image) {
                            if (! file_exists($tenant_image)) {
                                throw new Exception( sprintf('File image %s not found.', $tenant_image) );
                            }

                            $path_info = pathinfo($tenant_image);
                            $images['type'] = 'images';
                            $images['type_dir'] = 'pictures';
                            $images['name'] = $path_info['basename'];
                            $images['path'] = $tenant_image;

                            if ($tenant_image !== '') {
                                $this->uploadImage = TRUE;
                            }
                            $this->uploadImages($newtenant, $images, ($idx+1));
                        }
                    }

                    if ($key === 'image_map') {
                        if (! file_exists($image)) {
                            throw new Exception( sprintf('File image %s not found.', $image) );
                        }

                        $path_info = pathinfo($image);
                        $images['type'] = 'map';
                        $images['type_dir'] = 'maps';
                        $images['name'] = $path_info['basename'];
                        $images['path'] = $image;

                        if ($image !== '') {
                            $this->uploadImage = TRUE;
                        }
                        $this->uploadImages($newtenant, $images);
                    }
                }

                DB::commit();
                $this->info("Success, Data Inserted!");
            }
        } catch (Exception $e) {
            DB::rollback();
            $this->error([$e->getLine(), $e->getMessage()]);
        }

    }

    protected function uploadImages($tenant, $images, $order = 1)
    {
        if (! $this->uploadImage) {
            $this->info('Skipping ' . $images['type'] . ' seeder.');
            return TRUE;
        }

        $tenant_id = $tenant->merchant_id;
        $tenant_name = $tenant->name;
        $image_type = $images['type'];
        $image_type_dir = $images['type_dir'];
        $image_name = $images['name'];
        $image_path = $images['path'];

        $filename = sprintf('%s-%s-%s_%s', $tenant_id, Str::slug($tenant_name), time(), $order);
        $upload_dir = sprintf('public/uploads/retailers/%s/', $image_type_dir);
        $path = sprintf('uploads/retailers/%s/', $image_type_dir) . $filename;
        $path_info = pathinfo($image_path);
        $extension = '.' . $path_info['extension'];

        $metadata = [];
        $metadata[0]['filename'] = $filename;
        $metadata[0]['realpath'] = $image_path;
        $metadata[0]['file_size'] = filesize($image_path);
        $metadata[0]['mime_type'] = 'image/png';
        $metadata[0]['name_id'] = 'retailer_' . $image_type;
        $metadata[0]['name_id_long'] = 'retailer_' . $image_type . '_orig';
        $metadata[0]['upload_path'] = $path . $extension;

        $metadata[1]['filename'] = $filename . 'resize-default';
        $metadata[1]['realpath'] = $image_path;
        $metadata[1]['file_size'] = filesize($image_path);
        $metadata[1]['mime_type'] = 'image/png';
        $metadata[1]['name_id'] = 'retailer_' . $image_type;
        $metadata[1]['name_id_long'] = 'retailer_' . $image_type . '_resize_default';
        $metadata[1]['upload_path'] = $path . $extension;

        foreach ($metadata as $i => $file) {
            if (! file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, TRUE);
            }
            copy($file['realpath'], $upload_dir . '/' . $file['filename']);

            $media = new Media();
            $media->object_id = $tenant->merchant_id;
            $media->object_name = 'retailer';
            $media->media_name_id = $file['name_id'];
            $media->media_name_long = $file['name_id_long'];
            $media->file_name = $file['filename'];
            $media->file_extension = $extension;
            $media->mime_type = $file['mime_type'];
            $media->path = $file['upload_path'];
            $media->realpath = realpath($file['realpath']);
            $media->metadata = 'order-' . $i;
            $media->modified_by = 1;
            $media->save();
        }

        $this->info('tenant ' . $image_type . ' ' . $order . ' seeded.');
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array();
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('file', null, InputOption::VALUE_REQUIRED, 'JSON file.'),
            array('merchant_id', null, InputOption::VALUE_REQUIRED, 'Merchant id.'),
        );
    }

}
