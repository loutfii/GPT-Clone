<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Dis à Pest d'utiliser la base Laravel pour les tests Feature
uses(TestCase::class, RefreshDatabase::class)->in('Feature');
