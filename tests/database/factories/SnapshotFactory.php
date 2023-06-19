<?php

use Faker\Generator as Faker;
use Tests\Models\Snapshot;

$factory->define(Snapshot::class, function (Faker $faker) {
    return [
        'title' => $faker->sentence,
        'url' => $faker->url,
    ];
});
