<?php
/**
 * Command to update category image
 *
 * @author Ahmad <Ahmad@dominopos.com>
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CategoryImageCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'category:update-image';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to update category image.';

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
        $category_name = trim($this->option('name'));

        $category = DB::table('categories')
                      ->where('status', '!=', 'deleted')
                      ->where('category_name', '=', $category_name)
                      ->first();

        if (empty($category)) {
            throw new Exception('Category is not found.');
        }

        $imagePath = $this->option('image');
        if (! file_exists($imagePath)) {
            throw new Exception(sprintf('Image file %s is not found.', $imagePath));
        }

        $this->deleteOldImage($category->category_id);

        $uploadDir = 'public/uploads/categories/' . $category->category_id;
        $imageName = basename($imagePath);
        $extension = pathinfo($imagePath, PATHINFO_EXTENSION);

        $metadata = [];
        $metadata['filename'] = sprintf('%s-%s-%s.%s', $category->category_id, Str::slug($category->category_name), strtotime('now'), $extension);
        $metadata['realpath'] = realpath($imagePath);
        $metadata['file_size'] = filesize($metadata['realpath']);
        $metadata['mime_type'] = mime_content_type ($imagePath);
        $metadata['name_id'] = 'category_image';
        $metadata['name_id_long'] = 'category_image_orig';
        $metadata['upload_path'] = 'uploads/categories/' . $category->category_id . '/' . $metadata['filename'];

        $this->info('Copying category image');
        if (! file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, TRUE);
        }

        if (! copy($metadata['realpath'], $uploadDir . '/' . $metadata['filename'])) {
            $this->warning('Failed to copy image.');
        }

        $media = new Media();
        $media->object_id = $category->category_id;
        $media->object_name = 'category';
        $media->media_name_id = $metadata['name_id'];
        $media->media_name_long = $metadata['name_id_long'];
        $media->file_name = $metadata['filename'];
        $media->file_extension = $extension;
        $media->file_size = $metadata['file_size'];
        $media->mime_type = $metadata['mime_type'];
        $media->path = $metadata['upload_path'];
        $media->realpath = realpath('public' . DS . $media->path);
        $media->metadata = 'order-0';
        $media->modified_by = 1;
        $media->save();

        $this->info('Image has been updated.');
    }

    protected function deleteOldImage($categoryId)
    {
        $files = Media::where('object_id', $categoryId)
                        ->where('object_name', 'category')
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
            ['name', NULL, InputOption::VALUE_REQUIRED, 'Active category name.'],
            ['image', NULL, InputOption::VALUE_REQUIRED, 'Path to the image.']
        ];
    }
}
