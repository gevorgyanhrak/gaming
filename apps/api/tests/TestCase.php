<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    // Applied here (not via Pest's ->use()) so the guard below overrides the trait's hook:
    // a class's own methods take precedence over trait methods, inherited ones do not.
    use RefreshDatabase;

    /**
     * Guard against wiping a real database: RefreshDatabase drops every table, so it
     * may only ever run against the dedicated test database.
     *
     * @return void
     */
    protected function beforeRefreshingDatabase()
    {
        $database = config('database.connections.'.config('database.default').'.database');

        if ($database !== 'testing') {
            throw new RuntimeException("Refusing to refresh database [{$database}]: tests must run against [testing].");
        }
    }
}
