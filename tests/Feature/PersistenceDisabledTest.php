<?php

namespace Tests\Feature;

use Tests\TestCase;

class PersistenceDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('inference.persistence', false);
    }

    public function test_routes_are_not_registered_when_persistence_disabled(): void
    {
        $this->postJson('/api/anthropic/v1/messages/batches', [])->assertNotFound();
    }
}
