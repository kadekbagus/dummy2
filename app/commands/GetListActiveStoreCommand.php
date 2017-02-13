<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class GetListActiveStoreCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'store:list-active';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all active stores (tenants).';

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
        $fields = $this->option('fields');
        $separator = $this->option('separator');
        $raw = $this->option('raw-query');
        $prefix = DB::getTablePrefix();

        //replace country
        $fields = str_replace('country', 'p.country', $fields);
        $fields = str_replace('merchant_id', 't.merchant_id', $fields);
        $fields = str_replace('city', 'p.city', $fields);

        do {
            $stores = DB::select("SELECT {$fields}
                FROM {$prefix}merchants t
                LEFT JOIN (SELECT merchant_id, city, country, status FROM {$prefix}merchants WHERE object_type='mall') p
                    ON p.merchant_id = t.parent_id
                WHERE t.object_type = 'tenant'
                    AND t.status = 'active'
                    AND p.status = 'active'
                {$raw}
                LIMIT $skip, $take");

            $skip = $take + $skip;

            foreach ($stores as $store) {
                $values = get_object_vars($store);
                printf("%s\n", implode($separator, $values));
            }
        } while (! empty($stores));
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
            array('fields', null, InputOption::VALUE_OPTIONAL, 'List of fields separated by comma.', '*'),
            array('separator', null, InputOption::VALUE_OPTIONAL, 'Separator used when printing value.', ','),
            array('raw-query', null, InputOption::VALUE_OPTIONAL, 'Raw query to be injected.', NULL),
        );
    }

}
