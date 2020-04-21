<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Get list of active Product Affiliation.
 *
 * @author Budi <budi@gotomalls.com>
 */
class GetListActiveProductAffiliationCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'product-affiliation:list-active';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all active product affiliation.';

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

        do {
            $products = DB::select("SELECT p.{$fields}
                FROM {$prefix}products p
                WHERE p.status = 'active'
                {$raw}
                LIMIT $skip, $take");

            $skip = $take + $skip;

            foreach ($products as $product) {
                $values = get_object_vars($product);
                printf("%s\n", implode($separator, $values));
            }
        } while (! empty($products));
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
