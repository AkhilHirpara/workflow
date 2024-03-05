<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateImportColumnsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('import_columns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('column_heading');
            $table->boolean('status')->default(1)->comment("1=Include,0=Exclude");
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
        Schema::dropIfExists('import_columns');
    }
}
