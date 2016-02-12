<?php
/**
 * Command to update merchant logo
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MerchantLogoCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'merchant:logo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to update merchant logo (including retailer or mall).';

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
        $merchantId = $this->option('merchant-id');
        $merchant = DB::table('merchants')
                      ->where('status', '!=', 'deleted')
                      ->where('merchant_id', '=', $merchantId)
                      ->first();

        if (empty($merchant)) {
            throw new Exception('Merchant or mall is not found.');
        }

        $logoPath = $this->option('image');
        if (! file_exists($logoPath)) {
            throw new Exception(sprintf('Image file %s for logo is not found.', $logoPath));
        }

        $type = $this->option('object-type');

        $this->deleteOldLogo($merchantId, $type);

        $uploadDir = 'public/uploads/seeds/' . $merchantId;
        $imageName = basename($logoPath);

        $metadata = [];
        $metadata[0]['filename'] = sprintf('logo-original-%s', $imageName);
        $metadata[0]['realpath'] = realpath($logoPath);
        $metadata[0]['file_size'] = filesize($metadata[0]['realpath']);
        $metadata[0]['mime_type'] = 'image/png';
        $metadata[0]['name_id'] = 'mall_logo';
        $metadata[0]['name_id_long'] = 'mall_logo_orig';
        $metadata[0]['upload_path'] = 'uploads/seeds/' . $merchantId . '/' . $metadata[0]['filename'];

        $metadata[1]['filename'] = sprintf('logo-small-%s', $imageName);
        $metadata[1]['realpath'] = realpath($logoPath);
        $metadata[1]['file_size'] = filesize($metadata[0]['realpath']);
        $metadata[1]['mime_type'] = 'image/png';
        $metadata[1]['name_id'] = 'mall_logo';
        $metadata[1]['name_id_long'] = 'mall_logo_resized_default';
        $metadata[1]['upload_path'] = 'uploads/seeds/' . $merchantId . '/' . $metadata[1]['filename'];

        $extension = pathinfo($logoPath, PATHINFO_EXTENSION);

        foreach ($metadata as $i => $file) {
            if (! file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, TRUE);
            }

            copy($file['realpath'], $uploadDir . '/' . $file['filename']);

            $media = new Media();
            $media->object_id = $merchantId;
            $media->object_name = 'mall';
            $media->media_name_id = $file['name_id'];
            $media->media_name_long = $file['name_id_long'];
            $media->file_name = $file['filename'];
            $media->file_extension = $extension;
            $media->file_size = $file['file_size'];
            $media->mime_type = $file['mime_type'];
            $media->path = $file['upload_path'];
            $media->realpath = realpath($file['realpath']);
            $media->metadata = 'order-' . $i;
            $media->modified_by = 1;
            $media->save();
        }

        $this->info('Logo has been updated.');
    }

    protected function deleteOldLogo($merchantId, $type)
    {
        $files = Media::where('object_id', $merchantId)->where('object_name', $type)
                      ->where('media_name_id', 'mall_logo')
                      ->get();

        foreach ($files as $file) {
            $this->info(sprintf('Deleting file %s', $file->path));
            @unlink('public/' . $file->path);
            $file->delete();
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['merchant-id', NULL, InputOption::VALUE_REQUIRED, 'ID of the merchant.'],
            ['object-type', NULL, InputOption::VALUE_REQUIRED, 'Type of the merchant object: merchant, mallgroup, retailer or mall.'],
            ['image', NULL, InputOption::VALUE_REQUIRED, 'Path to the image of the logo.']
        ];
    }
}
