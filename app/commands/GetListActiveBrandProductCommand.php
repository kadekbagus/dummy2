<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Get list of active Brand Product.
 *
 * @author Budi <budi@gotomalls.com>
 */
class GetListActiveBrandProductCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'brand-product:list-active';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all active brand product.';

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
            $brandProducts = DB::select("SELECT bp.{$fields}
                FROM {$prefix}brand_products bp
                WHERE bp.status = 'active'
                {$raw}
                LIMIT $skip, $take");

            $skip = $take + $skip;

            foreach ($brandProducts as $brandProduct) {
                $values = get_object_vars($brandProduct);
                printf("%s\n", implode($separator, $values));
            }
        } while (! empty($brandProducts));
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
