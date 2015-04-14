<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateInboxesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('inboxes', function(Blueprint $table)
        {
            $table->engine = 'InnoDB';
            $table->bigIncrements('inbox_id');
            $table->bigInteger('user_id')->unsigned()->default(null);
            $table->bigInteger('from_id')->unsigned()->default(null);
            $table->bigInteger('from_name')->default(null)->nullable();

            $table->string('subject', 250)->nullable()->default(null);
            $table->text('content')->nullable()->default(null);
            $table->char('inbox_type', 1)->nullable();
            $table->char('is_read', 1)->nullable();

            $table->string('status', 15)->nullable();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('modified_by')->unsigned()->nullable();
            $table->timestamps();

            $table->index(['user_id'], 'user_idx');
            $table->index(['from_id'], 'from_idx');
            $table->index(['from_name'], 'from_name_idx');
            $table->index(['status'], 'status_idx');
            $table->index(['inbox_type'], 'inbox_type_idx');
            $table->index(['is_read'], 'is_read_idx');

            $table->index(['is_read', 'status'], 'status_is_read_idx');
            $table->index(['is_read', 'inbox_type'], 'inbox_type_is_read_idx');
            $table->index(['is_read', 'inbox_type', 'status'], 'status_inbox_type_is_read_idx');

            $table->index(['created_by'], 'created_by_idx');
            $table->index(['modified_by'], 'modified_by_idx');
            $table->index(['created_at'], 'created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('inboxes');
    }
}
