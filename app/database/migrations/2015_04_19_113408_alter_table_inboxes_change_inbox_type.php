<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableInboxesChangeInboxType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $prefix = DB::getTablePrefix();
        DB::statement("ALTER TABLE `{$prefix}inboxes`
                        CHANGE COLUMN `inbox_type` `inbox_type` VARCHAR(20)
                        CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NULL DEFAULT NULL");

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $prefix = DB::getTablePrefix();
        DB::statement("ALTER TABLE `{$prefix}inboxes`
                        CHANGE COLUMN `inbox_type` `inbox_type` CHAR(1)
                        CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NULL DEFAULT NULL");
    }

}
