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
            if ($user->id === 1) {
                $user->devices()->save(
                    factory(App\Device::class)->make([
                        'push_service_id' => 'eIrjxWASTb0:APA91bF8mv9AdXMAxQ0ALcvFJ4zvfzLxDs7LmGXrKB4btklQKuhcD94KTJV7tCghnxSQMAsShTjzjWHfWDC1aXe_JAQO0Ao4nuFEfpQI0QaUyX7Mh0aFm1RLVDhcP7nAArzaxF6jBFJx'
                    ])
                );
            }

            $user->devices()->save(
                factory(App\Device::class)->make()
            );
            $user->devices()->save(
                factory(App\Device::class)->make()
            );
        });
    }
}
