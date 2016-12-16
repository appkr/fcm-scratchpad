<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDevicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->increments('id');
            // Store 외래 키
            $table->integer('user_id')->unsigned()->index();
            // 단말기 고유 식별 ID. 단말기 공장 초기화시 바뀔 수 있음.
            $table->string('device_id')->nullable();
            // 단말기 운영 체제
            $table->string('os_enum')->nullalbe();
            // 단말기 모델명
            $table->string('model')->nullable();
            // 단말기 통신사
            $table->string('operator')->nullable();
            // SDK API 버전
            $table->integer('api_level')->nullable();
            // 푸시 메시지를 위한 단말기 고유 식별 ID
            $table->string('push_service_enum')->nullable();
            $table->string('push_service_id')->nullable();

            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

//            $table->primary(['device_id, push_service_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign('devices_user_id_foreign');
        });

        Schema::dropIfExists('devices');
    }
}
