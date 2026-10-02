<?php

namespace Database\Seeders;

use RuntimeException;

trait DemoEnvironmentOnly
{
    protected function assertDemoEnvironment(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Demonstration data is only available in local and testing environments.');
        }
    }
}
