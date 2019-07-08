<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWebappsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('webapps', function (Blueprint $table) {
            $table->string('uid')->primary();
            $table->string('channel1');
            $table->string('channel2')->nullable()->default(null);
            $table->string('uniqueid1')->nullable()->default(null);
            $table->string('uniqueid2')->nullable()->default(null);
            $table->string('event', 50)->nullable()->default(null);
            $table->string('direction', 50)->nullable()->default(null);
            $table->string('from_number')->nullable()->default(null);
            $table->string('to_number')->nullable()->default(null);
            $table->dateTime('starttime')->nullable()->default(null);
            $table->dateTime('endtime')->nullable()->default(null);
            $table->integer('totalduration')->nullable()->default(null);
            $table->string('context')->nullable()->default(null);
            $table->string('bridged')->nullable()->default(null);
            $table->string('state')->nullable()->default(null);
            $table->string('callcause')->nullable()->default(null);
            $table->string('recordingpath')->nullable()->default(null);
            $table->string('recordingurl')->nullable()->default(null);

            $table->index(['channel1', 'channel2']);
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
        Schema::dropIfExists('webapps');
    }
}
