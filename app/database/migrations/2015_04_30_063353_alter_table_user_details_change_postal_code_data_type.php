<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableUserDetailsChangePostalCodeDataType extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // change postal_code data type from integer to varchar
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'user_details` MODIFY `postal_code` VARCHAR(50) NULL DEFAULT NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // change postal_code data type from varchar to integer
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'user_details` MODIFY `postal_code` INT(10) UNSIGNED NULL DEFAULT NULL');
    }

}
