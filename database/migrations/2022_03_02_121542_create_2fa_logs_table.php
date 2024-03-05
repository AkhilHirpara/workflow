<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Create2faLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('twofa_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->bigInteger('twofa_code');
            $table->boolean('email_sent')->default(0);
            $table->timestamp('email_sent_time')->nullable();
            $table->boolean('twofa_verified')->default(0);
            $table->timestamp('twofa_verified_time')->nullable();
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
        Schema::dropIfExists('twofa_logs');
    }
}
