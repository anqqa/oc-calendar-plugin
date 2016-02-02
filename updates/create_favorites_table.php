<?php namespace Klubitus\Calendar\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateFavoritesTable extends Migration {

    public function up() {
        Schema::create('favorites', function($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('event_id')->references('id')->on('events');
        });
    }


    public function down() {
//        Schema::dropIfExists('favorites');
    }

}
