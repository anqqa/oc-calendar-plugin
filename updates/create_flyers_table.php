<?php namespace Klubitus\Calendar\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateFlyersTable extends Migration {

    public function up() {
        Schema::create('flyers', function($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->timestamps();
            $table->integer('event_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamp('begins_at');
            $table->integer('comment_count')->default(0);
        });
    }

    public function down() {
//        Schema::dropIfExists('flyers');
    }

}
