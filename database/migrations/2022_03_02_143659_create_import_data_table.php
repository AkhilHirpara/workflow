<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateImportDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('import_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->longText('row_details')->comment("Key from 'import_columns' and row data as value");
            $table->integer('task_status')->default(0)->comment('0=Pending,1=Inprogress,2=Completed');
            $table->integer('integrity_status')->default(0)->comment('0=Pending,1=Completed');
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
        Schema::dropIfExists('import_data');
    }
}
