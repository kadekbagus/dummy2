<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableLuckyDrawNumbersChangeUserIdToNullable extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // change column user_id to nullable
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'lucky_draw_numbers` MODIFY `user_id` BIGINT(20) UNSIGNED NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // change column user_id to not nullable
        DB::statement('ALTER TABLE `' . DB::getTablePrefix() . 'lucky_draw_numbers` MODIFY `user_id` BIGINT(20) UNSIGNED NOT NULL');
    }

}
