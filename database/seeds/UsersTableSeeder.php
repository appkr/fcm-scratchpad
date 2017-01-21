<?php

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        App\User::truncate();
        factory(App\User::class)->create([
            'name' => '김고객',
            'email' => 'user@example.com',
        ]);
    }
}
