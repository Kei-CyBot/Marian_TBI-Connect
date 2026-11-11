<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        URL::defaults(['rolePrefix' => 'member']);
    }

    public function actingAs($user, $guard = null)
    {
        URL::defaults(['rolePrefix' => $user->rolePrefix()]);

        return parent::actingAs($user, $guard);
    }
}
