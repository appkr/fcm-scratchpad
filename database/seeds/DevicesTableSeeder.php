<?php

use Illuminate\Database\Seeder;

class DevicesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        App\Device::truncate();

        $users = App\User::all();

        $users->each(function ($user) {
            $user->devices()->save(
                factory(App\Device::class)->make()
            );
            $user->devices()->save(
                factory(App\Device::class)->make()
            );
        });
    }
}
