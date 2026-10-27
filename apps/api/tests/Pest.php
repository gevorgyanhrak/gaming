<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the application and run against the MySQL "testing"
| database (see phpunit.xml); TestCase refreshes it and refuses any other
| database. Unit tests stay framework-free; game engines are tested there.
|
*/

pest()->extend(TestCase::class)->in('Feature');
