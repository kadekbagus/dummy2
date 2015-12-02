<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableLanguageAddNameLogAndIndex extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('languages', function(Blueprint $table)
        {
            $table->string('name_long', 50)->nullable()->after('name');
            $table->index(['name_long'], 'name_long_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('languages', function(Blueprint $table)
        {
            $table->dropIndex('name_long_idx');
            $table->dropColumn('name_long');
        });
    }

}
