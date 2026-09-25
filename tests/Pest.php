<?php

use JeffersonGoncalves\Carve\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

// Pest 4 ships fixture(); Pest 3 (prefer-lowest) does not.
if (! function_exists('fixture')) {
    function fixture(string $file): string
    {
        return __DIR__.'/Fixtures/'.$file;
    }
}
