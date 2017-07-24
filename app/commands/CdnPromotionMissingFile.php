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
        $take = 50;
        $skip = 0;
        $now = date('Y-m-d H:i:s', strtotime($this->option('more-than')));    // no TZ calculation
        $prefix = DB::getTablePrefix();

        do {
            $promotions = DB::select("SELECT n.news_id
                FROM {$prefix}media m
                JOIN {$prefix}news_translations nt ON nt.news_translation_id = m.object_id
                JOIN {$prefix}news n ON n.news_id = nt.news_id
                WHERE m.object_name = 'news_translation' AND
                n.object_type = 'promotion' AND
                (m.cdn_url IS NULL or m.cdn_url = '') AND
                m.path IS NOT NULL AND
                m.created_at > '{$now}'
                GROUP BY n.news_id
                LIMIT $skip, $take");

            $skip = $take + $skip;

            foreach ($promotions as $promotion) {
                $values = get_object_vars($promotion);
                printf("%s,%s\n", implode($values), 'promotion');
            }

        } while (! empty($promotions));
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
