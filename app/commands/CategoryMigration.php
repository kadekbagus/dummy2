<?php
/**
 * Migration for mall category to root category
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CategoryMigration extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'category:migration';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Migration to root category';

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
        $mall = $this->option('mall');
        $prefix = DB::getTablePrefix();

		// new root category
		$rootCategory = array("Books & Stationeries",
							"Education & Children Related",
							"Department Store & Supermarket",
							"Electronics & Gadget",
							"Entertainment & Leisure",
							"Fashion & Accessories",
							"Food & Beverage",
							"Health & Wellness",
							"Office, Home & Furnishing",
							"Watches & Jewelery",
							"Service",
							"Sports & Lifestyle");

		foreach ($rootCategory as $root => $val) {
			$checkCategory = Category::where('category_name', $val)->where('merchant_id', 0)->first();
			
			if(empty($checkCategory)) {
				$category = new Category();
				$category->merchant_id = 0;
				$category->category_name = $val;
				$category->status = 'active';
				$category->save();
			}
		}
		
		// mapping old category to root category
		$mapping = array(
			"Books & Stationeries" 				=> "Books, Gifts, Hobbies & Music",
			"Education & Children Related" 		=> "Children Related",
			"Department Store & Supermarket" 	=> "Department Store",
			"Electronics & Gadget" 				=> "Electronics & Gadgets",
			"Entertainment & Leisure" 			=> "Entertainment",
			"Fashion & Accessories" 			=> "Fashion & Accessories",
			"Food & Beverage" 					=> "Food & Beverages",
			"Health & Wellness" 				=> array("Health", "Health, Beauty & Personal Care"),
			"Office, Home & Furnishing" 		=> "Home Related",
			"Watches & Jewelery" 				=> "Jewellery, Watches & Optics",
			"Service" 							=> "Services",
			"Sports & Lifestyle"				=> "Sport Related"
		);

        // migration
		foreach ($mapping as $root => $old) {
			if(is_array($old)) {
				$oldCategory = implode("', '", $old);
			} else { 
				$oldCategory = $old;
			}

			DB::unprepared("
				UPDATE {$prefix}category_merchant 
                JOIN
    				(SELECT *
    				FROM {$prefix}category_merchant
    				WHERE category_id in (SELECT category_id
    						FROM {$prefix}categories
    						WHERE merchant_id = 0 and category_name in ('{$oldCategory}'))
    					AND merchant_id in (SELECT merchant_id
    						FROM {$prefix}merchants
    				        WHERE parent_id = '{$mall}')) as x 
                ON x.category_merchant_id = {$prefix}category_merchant.category_merchant_id
				SET {$prefix}category_merchant.category_id = (SELECT category_id
                                                				FROM {$prefix}categories
                                                				WHERE category_name = '{$root}')
			");

            $this->info("migrate from '" . $oldCategory . "' to '" . $root . "'");
		}

        $this->info("Category migration success");

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
		return array(
            array('mall', null, InputOption::VALUE_REQUIRED, 'Mall or Merchant ID.'),
        );
	}

}
