<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use OrbitShop\API\v1\Helper\RecursiveFileIterator;

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
     * Read the json file.
     */
    protected function readJSON($file)
    {
        if (! file_exists($file) ) {
           throw new Exception('Could not found json file.');
        }

        $conf = @json_decode(file_get_contents($file), true);
        $basefile = $basefile = basename($file);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception( sprintf('Error JSON %s: %s', $basefile, json_last_error_msg()) );
        }

        return $conf;
    }

    /**
     * Import to create new tenant
     */
    protected function newTenant()
    {
        try {
            $skipFloor = TRUE;
            $merchantId = $this->option('merchant-id');
            $fileName = $this->option('file');
            $userBaseDir = $this->option('basedir');
            $basefile = basename($fileName);
            $basedir = (! empty($userBaseDir) && file_exists($userBaseDir)) ? $userBaseDir : dirname($fileName);

            $data = $this->readJSON($fileName);
            $data['tenant_name'] = trim($data['tenant_name']);

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
                        throw new Exception(sprintf('ERROR: Category "%s" for tenant "%s" is not exist.', $category_name, $data['tenant_name']));
                    }
                }
                if ($data['status'] === 'active') {
                    $floor = Object::where('merchant_id', $merchantId)
                                    ->where('object_type', 'floor')
                                    ->where('status', 'active')
                                    ->where('object_name', $data['floor'])
                                    ->first();

                    if (empty($floor)) {
                        $errMessage = sprintf('WARNING: Floor %s for tenant %s (%s) is not exist on mall %s.', $data['floor'], $data['tenant_name'], $basefile, $mall->name);
                        if (! $skipFloor) {
                            throw new Exception($errMessage);
                        }
                        $this->error($errMessage);
                    }

                    if ($data['unit'] === '') {
                        $errMessage = sprintf('WARNING: Unit for tenant %s (%s) is not filled on mall %s.', $data['tenant_name'], $basefile, $mall->name);
                        $this->error($errMessage);
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
                        throw new Exception( sprintf('Insert Merchant %s Sosmed Failed!', $data['tenant_name']) );
                    }
                }

                foreach ($data['categories'] as $category_name) {
                    $category = Category::where('merchant_id', $merchantId)->where('category_name', $category_name)->first();
                    $categoryMerchant = new CategoryMerchant();
                    $categoryMerchant->category_id = $category->category_id;
                    $categoryMerchant->merchant_id = $newtenant->merchant_id;
                    if (! $categoryMerchant->save()) {
                        throw new Exception( sprintf('Insert Category %s Failed!', $data['tenant_name']) );
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
                            throw new Exception( sprintf('Insert Keyword %s Failed!', $data['tenant_name']) );
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
                        throw new Exception( sprintf('Insert Keyword Object %s Failed!', $data['tenant_name']) );
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
                        throw new Exception( sprintf('Insert Tenant Translation %s Failed!', $data['tenant_name']) );
                    }
                }

                $images = array();
                $skipImage = $this->option('skip-image');
                $imageMode = $this->option('image-mode');

                // Made with angry and hurry
                if (! $skipImage) {
                    foreach ($data['images'] as $key => $image) {
                        if ($key === 'tenant_logo') {
                            try {
                                if ($imageMode === 'auto') {
                                    $image = $this->getDirForTenant($basedir, $data['tenant_name'], 'logo', 'auto');
                                    $image = $this->getFilesFromDir($image)[0];
                                } else {
                                    $image = $basedir . '/' . $image;
                                    $image = $this->getFilesFromDir($image)[0];
                                }
                            } catch (Exception $e) {
                                $this->error( sprintf('WARNING: File image %s (%s) for tenant %s not found.', $key, $image, $data['tenant_name']) );
                                continue;
                            }

                            if (! file_exists($image)) {
                                $this->error( sprintf('WARNING: File image %s (%s) for tenant %s not found.', $key, $image, $data['tenant_name']) );
                                continue;
                            }
                            $path_info = pathinfo($image);

                            $images['type'] = 'logo';
                            $images['type_dir'] = 'logo';
                            $images['name'] = $path_info['basename'];
                            $images['path'] = $image;

                            try {
                                $this->uploadImages($newtenant, $images);
                            } catch (Exception $e) {
                                $this->error( sprintf('WARNING: File image %s (%s) for tenant %s save failed.', $key, $image, $data['tenant_name']) );
                            }
                        }

                        if ($key === 'tenant_image') {
                            try {
                                if ($imageMode === 'auto') {
                                    $image = $this->getDirForTenant($basedir, $data['tenant_name'], 'images', 'auto');
                                    $image = $this->getFilesFromDir($image, 3);
                                } else {
                                    $image = $basedir . '/' . $image;
                                    $image = $this->getFilesFromDir($image, 3);
                                }
                            } catch (Exception $e) {
                                $image = is_array($image) ? current($image) : $image;
                                $this->error( sprintf('WARNING: File image %s (%s) for tenant %s not found. %s', $key, $image, $data['tenant_name'], $e->getMessage()) );
                                continue;
                            }

                            if (empty($image)) {
                                $image = $basedir . '/' . $data['tenant_name'] . '/images';
                                $this->error( sprintf('WARNING: File image %s (%s) for tenant %s not found.', $key, $image, $data['tenant_name']) );
                                continue;
                            }

                            foreach ($image as $idx => $tenant_image) {
                                if (! file_exists($tenant_image)) {
                                    $this->error( sprintf('WARNING: File image %s (%s) for tenant %s not found.', $key, $image, $data['tenant_name']) );
                                    continue;
                                }

                                $path_info = pathinfo($tenant_image);
                                $images['type'] = 'image';
                                $images['type_dir'] = 'pictures';
                                $images['name'] = $path_info['basename'];
                                $images['path'] = $tenant_image;

                                try {
                                    $this->uploadImages($newtenant, $images, ($idx+1));
                                } catch (Exception $e) {
                                    $this->error( sprintf('WARNING: File image %s (%s) for tenant %s save failed.', $key, $image, $data['tenant_name']) );
                                }
                            }
                        }

                        if ($key === 'image_map') {
                            try {
                                if ($imageMode === 'auto') {
                                    $image = $this->getDirForTenant($basedir, $data['tenant_name'], 'map', 'auto');
                                    $image = $this->getFilesFromDir($image)[0];
                                } else {
                                    $image = $basedir . '/' . $image;
                                    $image = $this->getFilesFromDir($image)[0];
                                }
                            } catch (Exception $e) {
                                $this->error( sprintf('WARNING: File image %s (%s) for tenant %s not found.', $key, $image, $data['tenant_name']) );
                                continue;
                            }

                            if (! file_exists($image)) {
                                $this->error( sprintf('File image %s (%s) for tenant %s not found.', $key, $image, $data['tenant_name']) );
                                continue;
                            }

                            $path_info = pathinfo($image);
                            $images['type'] = 'map';
                            $images['type_dir'] = 'maps';
                            $images['name'] = $path_info['basename'];
                            $images['path'] = $image;


                            try {
                                $this->uploadImages($newtenant, $images);
                            } catch (Exception $e) {
                                $this->error( sprintf('WARNING: File image %s (%s) for tenant %s save failed.', $key, $image, $data['tenant_name']) );
                            }
                        }
                    }
                }

                DB::commit();
                $this->info( sprintf('Tenant %s successfully imported.', $data['tenant_name']) );
            }
        } catch (Exception $e) {
            DB::rollback();
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
        }
    }

    /**
     * @param string $basedir base directory
     * @param string $tenant_name
     * @param string $type Type of the file: 'logo', 'images', 'map'
     * @param string $mode How to get the files value: 'from_json' or 'auto'.
     *                     auto means it assumes images/TENANT_NAME/{logo,images,map}
     * @param string
     * @return string
     */
    protected function getDirForTenant($basedir, $tenant_name, $type, $mode='from_json')
    {
        if ($mode === 'from_json') {
            return $basedir;
        }

        switch ($type) {
            case 'images':
                $file = sprintf('%s/images/%s/images', $basedir, $tenant_name);
                break;

            case 'map':
                $file = sprintf('%s/images/%s/map', $basedir, $tenant_name);
                break;

            case 'logo':
                $file = sprintf('%s/images/%s/logo', $basedir, $tenant_name);
                break;

            default:
                throw new Exception('Unknown type ' . $type . ' for getImagesForTenant.');
        }

        return $file;
    }

    /**
     * Get files from directory with a limit
     *
     * @param string $dir Directory name
     * @param int $limit Limit the result
     */
    protected function getFilesFromDir($dir, $limit=1)
    {
        if (is_file($dir)) {
            return (array)$dir;
        }

        $pictureOnly = function($file, $fullPath) {
            $fileExt = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (in_array($fileExt, ['jpg', 'png', 'jpeg'])) {
                return TRUE;
            }

            return FALSE;
        };

        $files = [];
        $recursiveIterator = RecursiveFileIterator::create($dir)
                                                  ->setCallbackMatcher($pictureOnly)
                                                  ->includeFullPath();

        $counter = 0;
        foreach ($recursiveIterator->get() as $file) {
            $files[] = $file;

            if (++$counter >= $limit) {
                break;
            }
        }

        return $files;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $mode = $this->option('mode');
        switch ($mode) {
            case 'update':

            case 'insert':
            default:
                $this->newTenant();
        }
    }

    protected function uploadImages($tenant, $images, $order = 1)
    {
        $tenant_id = $tenant->merchant_id;
        $tenant_name = $tenant->name;
        $image_type = $images['type'];
        $image_type_dir = $images['type_dir'];
        $image_name = $images['name'];
        $image_path = $images['path'];

        $path_info = pathinfo($image_path);
        $extension = $path_info['extension'];
        $filename = sprintf('%s-%s-%s_%s_%s_orig.%s', $tenant_id, Str::slug($tenant_name), time(), $order, $image_type, $extension);
        $upload_dir = sprintf('public/uploads/retailers/%s/', $image_type_dir);
        $path = sprintf('uploads/retailers/%s/', $image_type_dir) . $filename;

        $metadata = [];
        $metadata[0]['filename'] = $filename;
        $metadata[0]['realpath'] = $image_path;
        $metadata[0]['file_size'] = filesize($image_path);
        $metadata[0]['mime_type'] = 'image/png';
        $metadata[0]['name_id'] = 'retailer_' . $image_type;
        $metadata[0]['name_id_long'] = 'retailer_' . $image_type . '_orig';
        $metadata[0]['upload_path'] = $path;

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
            if (! $media->save()) {
                throw new Exception(sprintf('Failed to save image %s to DB.', $filename));
            }
        }

        $this->info( sprintf('Tenant %s %s (%s) successfuly saved.', $tenant->name, $image_type, $order) );
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
            array('mode', 'insert', InputOption::VALUE_REQUIRED, 'Import mode, "insert" or "update".'),
            array('file', null, InputOption::VALUE_REQUIRED, 'JSON file.'),
            array('merchant-id', null, InputOption::VALUE_REQUIRED, 'Merchant id.'),
            array('skip-image', FALSE, InputOption::VALUE_NONE, 'Skip image upload.'),
            array('image-mode', 'auto', InputOption::VALUE_OPTIONAL, 'Value "from_json" or "auto".'),
            array('basedir', NULL, InputOption::VALUE_OPTIONAL, 'Base directory for the images.')
        );
    }
}
