<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIntegrityTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('integrity', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('row_id')->comment("From 'import_data' table");
            $table->longText('answers')->nullable()->comment('Json format of answers');
            $table->integer('status')->default(0)->comment('0=Pending,1=Inprogress,2=Completed');
            $table->unsignedBigInteger('worked_by')->nullable();
            $table->integer('last_review_status')->default(0)->nullable();
            $table->unsignedBigInteger('last_review_doneby')->nullable();
            $table->timestamp('last_review_check_date')->nullable();
            $table->timestamp('worked_date')->nullable();
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
        Schema::dropIfExists('integrity');
    }
}
