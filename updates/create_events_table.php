<?php namespace Klubitus\Calendar\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;


class CreateEventsTable extends Migration {

    public function up() {
        Schema::create('events', function($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->timestamps();
            $table->timestamp('begins_at');
            $table->timestamp('ends_at');
            $table->foreign('author_id')->references('id')->on('users');

            $table->string('name', 64);
            $table->string('url')->nullable();
            $table->string('ticket_url')->nullable();
            $table->integer('age')->nullable();
            $table->decimal('price', 5, 2)->nullable();
            $table->text('info')->nullable();
            $table->text('music')->nullable();
            $table->integer('flyer_id')->nullable(); // @TODO: Move to flyers plugin
            $table->string('flyer_url')->nullable();
            $table->string('flyer_front_url')->nullable();

            $table->string('venue_id')->nullable(); // @TODO: Move to venues plugin
            $table->string('venue_name')->nullable();
            $table->string('venue_url')->nullable();
            $table->integer('venue_hidden')->nullable();
            $table->string('city_name')->nullable();

            $table->integer('favorite_count')->default(0);
            $table->integer('update_count')->default(0);

            $table->bigInteger('facebook_id')->nullable()->unique();
            $table->string('facebook_organizer')->nullable();

            $table->index(['begins_at', 'ends_at'], 'event_time');
        });
    }


    public function down() {
        //Schema::dropIfExists('events');
    }

}
