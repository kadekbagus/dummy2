<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTableSettingTranslations extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        Schema::create('setting_translations', function (Blueprint $table) {
            $table->char('setting_translation_id',16);
            $table->char('setting_id',16);
            $table->char('merchant_language_id',16);
            $table->string('setting_value', 255)->nullable();
            $table->string('status', 15)->default('active');
            $table->timestamps();
            $table->index(['setting_id', 'merchant_language_id'], 'widget_language_idx');
            $table->index(['status'], 'status_idx');
            $table->char('created_by',16)->nullable();
            $table->char('modified_by',16)->nullable();
            $table->index(['created_by'], 'created_by_idx');
            $table->index(['modified_by'], 'modified_by_idx');            
        });	
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('setting_stranslations');		
	}

}
