<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->integer('category')->default(1)->comment('1=File,2=Loan,3=Delinquency');
            $table->string('export_heading');
            $table->boolean('comment_required')->default(0)->comment('1=Required,0=Not Required');
            $table->text('question');
            $table->longText('choices');
            $table->boolean('status')->default(1)->comment('1=Active,0=Inactive');
            $table->unsignedBigInteger('template_id')->nullable();
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
        Schema::dropIfExists('questions');
    }
}
