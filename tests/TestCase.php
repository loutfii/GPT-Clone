<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // évite l’erreur "Vite manifest not found" pendant les tests
        $this->withoutVite();

        // IMPORTANT : ne plus forcer les features ici.
        // On les gère via config/fortify.php et config/jetstream.php.
    }
}
