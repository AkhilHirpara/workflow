<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmailLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->text('subject');
            $table->timestamp('sent_on')->nullable();
            $table->string('sent_to')->comment("User email address");
            $table->unsignedBigInteger('user_id');
            $table->text('delete_token')->nullable();
            $table->boolean('is_deleted')->default(0);
            $table->timestamp('delete_date')->nullable();
            $table->integer('email_reminder_num')->default(1)->comment("1,2,3");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_logs');
    }
}
