<?php

namespace Kauffinger\AgenticChatBubble\Tests;

use Kauffinger\AgenticChatBubble\ServiceProvider;
use Livewire\LivewireServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getPackageProviders($app)
    {
        return array_merge(parent::getPackageProviders($app), [
            LivewireServiceProvider::class,
        ]);
    }
}
