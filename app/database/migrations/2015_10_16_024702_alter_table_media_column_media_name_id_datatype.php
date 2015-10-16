<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMediaColumnMediaNameIdDatatype extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // change column media_name_id to datatype varchar(50) 'utf8_unicode_ci'
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'media` MODIFY `media_name_id` varchar(50) COLLATE utf8_unicode_ci NOT NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // change column media_name_id to datatype char(16) 'ascii - ascii_bin'
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'media` MODIFY `media_name_id` char(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
    }

}
