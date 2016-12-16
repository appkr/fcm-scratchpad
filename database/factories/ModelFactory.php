<?php

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

$faker = Faker\Factory::create('ko_kr');

/** @var \Illuminate\Database\Eloquent\Factory $factory */
$factory->define(App\User::class, function () use ($faker) {
    static $password;

    return [
        'name' => $faker->name,
        'email' => $faker->unique()->safeEmail,
        'password' => $password ?: $password = bcrypt('secret'),
        'remember_token' => str_random(10),
    ];
});

$factory->define(App\Device::class, function () use ($faker) {
    return [
        'device_id' => str_random(16),
        'os_enum' => 'ANDROID',
        'model' => $faker->randomElement(['LG-F160', 'SHV-E250', 'LG-F320', 'Nexus 7', 'IM-A870', 'SM-G900']),
        'operator' => $faker->randomElement(['SKTelecom', 'olleh', 'LGU+']),
        'api_level' => rand(16, 24),
        'push_service_enum' => 'FCM',
        'push_service_id' => str_random(162),
    ];
});
