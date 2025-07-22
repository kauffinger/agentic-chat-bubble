<?php

namespace Kauffinger\AgenticChatBubble\Tests;

use Kauffinger\AgenticChatBubble\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
