<?php

use Illuminate\Support\Facades\Config;
use Kauffinger\AgenticChatBubble\Services\ToolRegistry;
use Prism\Prism\Tool;

beforeEach(function () {
    // Clear the ToolRegistry before each test
    app()->singleton(ToolRegistry::class);
    app(ToolRegistry::class)->clear();
    Config::set('agentic-chat-bubble.tools', []);
});

describe('Tool Registration', function () {
    it('registers a tool', function () {
        $mockTool = (new Tool)
            ->as('test_tool')
            ->for('Test tool')
            ->using(fn () => 'test result');

        $registry = app(ToolRegistry::class);
        $registry->register($mockTool);

        expect($registry->count())->toBe(1);
        expect($registry->all())->toHaveCount(1);
        expect($registry->all()[0])->toBe($mockTool);
    });

    it('registers multiple tools at once', function () {
        $mockTool1 = (new Tool)
            ->as('tool1')
            ->for('Tool 1')
            ->using(fn () => 'result1');

        $mockTool2 = (new Tool)
            ->as('tool2')
            ->for('Tool 2')
            ->using(fn () => 'result2');

        $registry = app(ToolRegistry::class);
        $registry->registerMany([$mockTool1, $mockTool2]);

        expect($registry->count())->toBe(2);
        expect($registry->all())->toHaveCount(2);
    });

    it('clears all registered tools', function () {
        $mockTool = (new Tool)
            ->as('test_tool')
            ->for('Test tool')
            ->using(fn () => 'test result');

        $registry = app(ToolRegistry::class);
        $registry->register($mockTool);
        expect($registry->count())->toBe(1);

        $registry->clear();
        expect($registry->count())->toBe(0);
        expect($registry->all())->toHaveCount(0);
    });
});

describe('Tool Resolution', function () {
    it('resolves tools from config as class strings', function () {
        $mockTool = (new Tool)
            ->as('test_tool')
            ->for('Test tool')
            ->using(fn () => 'test result');

        app()->bind('TestToolClass', fn () => $mockTool);
        Config::set('agentic-chat-bubble.tools', ['TestToolClass']);

        $tools = app(ToolRegistry::class)->getAllTools();
        expect($tools)->toHaveCount(1);
        expect($tools[0])->toBe($mockTool);
    });

    it('resolves tools from config as callables', function () {
        $mockTool = (new Tool)
            ->as('callable_tool')
            ->for('Callable tool')
            ->using(fn () => 'callable result');

        Config::set('agentic-chat-bubble.tools', [fn () => $mockTool]);

        $tools = app(ToolRegistry::class)->getAllTools();
        expect($tools)->toHaveCount(1);
        expect($tools[0])->toBe($mockTool);
    });

    it('resolves tools from config as objects', function () {
        $mockTool = (new Tool)
            ->as('object_tool')
            ->for('Object tool')
            ->using(fn () => 'object result');

        Config::set('agentic-chat-bubble.tools', [$mockTool]);

        $tools = app(ToolRegistry::class)->getAllTools();
        expect($tools)->toHaveCount(1);
        expect($tools[0])->toBe($mockTool);
    });

    it('resolves dynamically registered tools', function () {
        $mockTool1 = (new Tool)
            ->as('dynamic1')
            ->for('Dynamic tool 1')
            ->using(fn () => 'result1');

        $mockTool2 = (new Tool)
            ->as('dynamic2')
            ->for('Dynamic tool 2')
            ->using(fn () => 'result2');

        app(ToolRegistry::class)->register($mockTool1);
        app(ToolRegistry::class)->register($mockTool2);

        $tools = app(ToolRegistry::class)->getAllTools();
        expect($tools)->toHaveCount(2);
    });

    it('merges config and dynamic tools', function () {
        $configTool = (new Tool)
            ->as('config_tool')
            ->for('Config tool')
            ->using(fn () => 'config result');

        $dynamicTool = (new Tool)
            ->as('dynamic_tool')
            ->for('Dynamic tool')
            ->using(fn () => 'dynamic result');

        Config::set('agentic-chat-bubble.tools', [$configTool]);
        app(ToolRegistry::class)->register($dynamicTool);

        $tools = app(ToolRegistry::class)->getAllTools();
        expect($tools)->toHaveCount(2);
        expect($tools[0])->toBe($configTool);
        expect($tools[1])->toBe($dynamicTool);
    });

    it('throws exception for invalid callable', function () {
        Config::set('agentic-chat-bubble.tools', [fn () => 'not a tool']);

        expect(fn () => app(ToolRegistry::class)->getAllTools())
            ->toThrow(InvalidArgumentException::class, 'Callable must return a Tool instance');
    });

    it('throws exception for invalid tool type', function () {
        $registry = app(ToolRegistry::class);

        expect(fn () => $registry->resolveTools([123]))
            ->toThrow(InvalidArgumentException::class, 'Tool must be a string, callable, or Tool instance');
    });
});

describe('Tool Resolution Without Registration', function () {
    it('resolves tools without registering them', function () {
        $mockTool = (new Tool)
            ->as('temp_tool')
            ->for('Temporary tool')
            ->using(fn () => 'temp result');

        $registry = app(ToolRegistry::class);
        $resolvedTools = $registry->resolveTools([$mockTool]);

        expect($resolvedTools)->toHaveCount(1);
        expect($resolvedTools[0])->toBe($mockTool);

        // Verify it wasn't registered
        expect($registry->count())->toBe(0);
    });

    it('resolves mixed tool types without registering them', function () {
        $mockTool1 = (new Tool)
            ->as('tool1')
            ->for('Tool 1')
            ->using(fn () => 'result1');

        $mockTool2 = (new Tool)
            ->as('tool2')
            ->for('Tool 2')
            ->using(fn () => 'result2');

        app()->bind('TestTool', fn () => $mockTool2);

        $registry = app(ToolRegistry::class);
        $resolvedTools = $registry->resolveTools([
            $mockTool1,
            'TestTool',
            fn () => $mockTool1,
        ]);

        expect($resolvedTools)->toHaveCount(3);
        expect($registry->count())->toBe(0); // Nothing should be registered
    });
});
