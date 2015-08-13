<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableUsersAddColumnMembership extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function(Blueprint $table)
        {
            $table->datetime('membership_since')->nullable()->after('membership_number');
            $table->string('external_user_id', 50)->nullable()->after('membership_since');
            $table->index(['external_user_id'], 'external_user_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function(Blueprint $table)
        {
            $table->dropIndex('external_user_id_idx');
            $table->dropColumn('external_user_id');
            $table->dropColumn('membership_since');
        });
    }

}
