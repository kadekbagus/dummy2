<?php
/**
 * Command for showing missing cdn image file for promotion
 *
 * @author kadek <kadek@dominopos.com>
 */

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CdnPromotionMissingFile extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cdn:promotion-missing-file';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find promotion id not have cdn file.';

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
        $date = $this->option('more-than');

        if (DateTime::createFromFormat('Y-m-d H:i:s', $date) == false) {
           throw new Exception('Format date is invalid, format date must be Y-m-d H:i:s ie (2017-12-20 16:55:28)');
        }

        $promotions = Media::select('news.news_id')
                    ->join('news_translations', 'news_translations.news_translation_id', '=', 'media.object_id')
                    ->join('news', 'news.news_id', '=', 'news_translations.news_id')
                    ->where('media.object_name', '=', 'news_translation')
                    ->where('news.object_type', '=', 'promotion')
                    ->whereNull('media.cdn_url')
                    ->whereNotNull('media.path')
                    ->where('media.created_at', '>=', $date)
                    ->groupBy('news.news_id')
                    ->get();

        if (count($promotions)) {
            foreach($promotions as $promotion) {
                printf("%s,%s\n", $promotion->news_id, 'promotion');
            }
        } else {
            $this->info('no missing cdn found');
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
            array('more-than', null, InputOption::VALUE_OPTIONAL, 'Date more than.', null),
        );
    }

}
