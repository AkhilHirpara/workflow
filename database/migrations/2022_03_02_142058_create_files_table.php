<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('original_filename');
            $table->string('filename');
            $table->integer('folder_id')->default(0)->comment('folder id,if any');
            // $table->integer('file_type')->comment('1-file,2-folder');
            $table->string('type')->nullable()->comment('Import/Export/Personal');
            $table->integer('parent_fileid')->default(0)->comment('Parent file id,if any');
            $table->string('total_rows')->nullable();
            $table->string('imported_rows')->nullable();
            $table->integer('import_status')->nullable()->comment('0=Failed,1=Completed,2=Pending');
            $table->string('import_start_time')->nullable();
            $table->string('import_end_time')->nullable();
            $table->integer('status')->default(1)->comment('1=Active,0=Inactive');
            $table->text('note')->nullable();
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
        Schema::dropIfExists('files');
    }
}
