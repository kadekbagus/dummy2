<?php
/**
 * Command for insert, update or delete the pokestop map
 *
 * @author Firmansyah <firmansyah@dominopos.com>
 */

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class PokestopMap extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'pokestop';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for insert or delete pokestop map';

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
        $mode = $this->option('mode');

        switch ($mode) {
            case 'insert':
                $this->insertPokestopMap();
                break;

            case 'update':
                $this->insertPokestopMap();
                break;

            case 'delete':
                $this->deletePokestopMap();
                break;

            default:
                $this->info("You must choose: insert, update, or delete");
                break;
        }
    }


    protected function insertPokestopMap()
    {
        try {
            $prefix = DB::getTablePrefix();
            $mallId = $this->option('mall_id');
            $imageUrl = $this->option('image_url');

            $mall = Mall::excludeDeleted()->where('merchant_id', '=', $mallId)->first();

            if (empty($mall)) {
                throw new Exception('Merchant or mall is not found.');
            } else {

                DB::beginTransaction();

                // Check exist pokestop map in the mall
                $pokestopMap = News::excludeDeleted()
                    ->where('object_type', 'pokestop')
                    ->where('mall_id', $mallId)
                    ->first();

                    if (empty($pokestopMap)) {
                        // Save news as a pokestop
                        $newPokestopMap = new News();
                        $newPokestopMap->mall_id = $mallId;
                        $newPokestopMap->object_type = 'pokestop';
                        $newPokestopMap->news_name = 'pokestop';
                        $newPokestopMap->status = 'active';
                        $newPokestopMap->save();

                        // Insert to media
                        if ($newPokestopMap) {
                            $media = new Media();
                            $media->object_id = $newPokestopMap->news_id;
                            $media->object_name = 'pokestop';
                            $media->media_name_id = 'pokestop_image';
                            $media->realpath = $imageUrl;
                            $media->save();
                        }
                } else {

                    //Delete old media
                    $pastMediaDeleted = Media::where('object_id', $pokestopMap->news_id)
                        ->where('object_name', 'pokestop')
                        ->delete();

                    // Insert to media
                    if ($pastMediaDeleted) {
                        $media = new Media();
                        $media->object_id = $pokestopMap->news_id;
                        $media->object_name = 'pokestop';
                        $media->media_name_id = 'pokestop_image';
                        $media->realpath = $imageUrl;
                        $media->save();
                    }
                }

                DB::commit();
                $this->info( sprintf('Pokestop map in mall %s successfully added.', $mall->name) );
            }

        } catch (Exception $e) {
            DB::rollback();
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
        }
    }

    protected function deletePokestopMap()
    {
        try {
            $prefix = DB::getTablePrefix();
            $mallId = $this->option('mall_id');

            $mall = Mall::excludeDeleted()->where('merchant_id', '=', $mallId)->first();

            if (empty($mall)) {
                throw new Exception('Merchant or mall is not found.');
            } else {

                DB::beginTransaction();

                $pokestopMap = News::excludeDeleted()
                            ->where('object_type', 'pokestop')
                            ->where('mall_id', $mallId)
                            ->first();

                if (empty($pokestopMap)) {
                    throw new Exception('Pokestop is not found.');
                } else {

                    $pokestopMap->delete();

                    if ($pokestopMap) {
                        $pastMedia = Media::where('object_id', $pokestopMap->news_id)
                                    ->where('object_name', 'pokestop')
                                    ->delete();
                    }

                    DB::commit();
                    $this->info( sprintf('Pokestop map in mall %s successfully deleted.', $mall->name) );
                }
            }

        } catch (Exception $e) {
            DB::rollback();
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
        }
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
            array('mode', null, InputOption::VALUE_REQUIRED, 'Mode insert, update or delete', null),
            array('mall_id', null, InputOption::VALUE_REQUIRED, 'Merchant or mall id', null),
            array('image_url', null, InputOption::VALUE_REQUIRED, 'Image url', null),
        );
    }

}