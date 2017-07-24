<?php
/**
 * Command for showing missing cdn image file for news
 *
 * @author kadek <kadek@dominopos.com>
 */

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CdnNewsMissingFile extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cdn:news-missing-file';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find news id not have cdn file.';

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
            $news = DB::select("SELECT n.news_id
                FROM {$prefix}media m
                JOIN {$prefix}news_translations nt ON nt.news_translation_id = m.object_id
                JOIN {$prefix}news n ON n.news_id = nt.news_id
                WHERE m.object_name = 'news_translation' AND
                n.object_type = 'news' AND
                (m.cdn_url IS NULL or m.cdn_url = '') AND
                m.path IS NOT NULL AND
                m.created_at > '{$now}'
                GROUP BY n.news_id
                LIMIT $skip, $take");

            $skip = $take + $skip;

            foreach ($news as $event) {
                $values = get_object_vars($event);
                printf("%s,%s\n", implode($values), 'news');
            }

        } while (! empty($news));
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
