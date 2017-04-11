<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class FillTableGrabCategoriesCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'grab:fill-categories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fill table grab_categories.';

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
        $dryRun = $this->option('dry-run');
        $take = 50;
        $skip = 0;

        if ($dryRun) {
            $this->info('[DRY RUN MODE - Not Insert on DB] ');
        }

        // grab data categories - category name, description, status
        $grab_categories_array = [
            "Eat,,",
            "Play,,",
            "Shop,,",
            "Travel,,",
            "Service,,",
        ];

        foreach ($grab_categories_array as $idx => $data) {
            $explode_data = explode(',', $data);

            $grab_category_name = $explode_data[0];
            $grab_description = $explode_data[1];
            $grab_status = $explode_data[2] === '' ? 'active' : $explode_data[2];

            $validator_value = [
                'grab_status' => $grab_status,
            ];

            $validator_check = [
                'grab_status' => 'in:active,inactive'
            ];

            $validator = Validator::make(
                $validator_value,
                $validator_check
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }

            // exist grab category
            $category = GrabCategory::where('category_name', $grab_category_name)->first();

            if (empty($category)) {
                // create data
                $newcategory = new GrabCategory();
                $newcategory->category_name = $grab_category_name;
                $newcategory->description = $grab_description;
                $newcategory->status = $grab_status;

                if (! $dryRun) {
                    $newcategory->save();
                }

                $this->info(sprintf("Insert category %s with description %s and status %s", $grab_category_name, $grab_description, $grab_status));

            } else {
                // update exist data
                $category->description = $grab_description;
                $category->status = $grab_status;

                if (! $dryRun) {
                    $category->save();
                }

                $this->info(sprintf("Update category %s with description %s and status %s", $grab_category_name, $grab_description, $grab_status));
            }

            $this->info("Done");
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
            array('dry-run', null, InputOption::VALUE_NONE, 'Dry run, do not Insert to grab_categories.', null),
        );
    }

}
