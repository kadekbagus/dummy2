<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableLuckyDrawsAddColumnExternalLuckyDrawId extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lucky_draws', function(Blueprint $table)
        {
            $table->string('external_lucky_draw_id', 50)->nullable()->after('max_number');
            $table->index(['external_lucky_draw_id'], 'external_lucky_draw_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lucky_draws', function(Blueprint $table)
        {
            $table->dropIndex('external_lucky_draw_id_idx');
            $table->dropColumn('external_lucky_draw_id');
        });
    }

}
