<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableMacAddressesAddIpAddress extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('mac_addresses', function(Blueprint $table)
        {
            // xxx.xxx.xxx.xxx
            $table->char('ip_address', 15)->nullable()->default(NULL)->after('mac_address');

            $table->index(['ip_address'], 'ip_address_idx');
            $table->index(['ip_address', 'mac_address'], 'mac_ip_address_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('mac_addresses', function(Blueprint $table)
        {
            $table->dropIndex('ip_address_idx');
            $table->dropIndex('mac_ip_address_idx');
            $table->dropColumn('ip_address');
        });
    }
}
