<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_name');
            $table->string('identifier')->nullable();
            $table->unsignedBigInteger('investor_id')->nullable();
            $table->unsignedBigInteger('platform_id')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->bigInteger('percentage_completed')->nullable();
            $table->integer('status')->default(3)->comment('0=Deleted,1=Active,2=Completed,3=Incomplete Creation ');
            $table->bigInteger('import_time_taken')->nullable();
            $table->integer('last_completed_step')->nullable();
            $table->unsignedBigInteger('created_by');
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
        Schema::dropIfExists('projects');
    }
}
