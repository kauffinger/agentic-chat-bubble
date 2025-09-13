<?php

namespace Kauffinger\AgenticChatBubble\Helpers;

class PositionHelper
{
    public static function getButtonClasses(string $position): string
    {
        return match ($position) {
            'bottom-right' => 'fixed right-6 bottom-6 z-50',
            'top-right' => 'fixed top-6 right-6 z-50',
            'top-left' => 'fixed top-6 left-6 z-50',
            default => 'fixed bottom-6 left-6 z-50',
        };
    }

    public static function getWindowClasses(string $position): string
    {
        return match ($position) {
            'bottom-right' => 'fixed inset-0 h-full w-full border-2 border-neutral-800 bg-white sm:absolute sm:inset-auto sm:right-0 sm:bottom-20 sm:h-[500px] sm:w-[380px] sm:border-2 sm:shadow-lg',
            'top-right' => 'fixed inset-0 h-full w-full border-2 border-neutral-800 bg-white sm:absolute sm:inset-auto sm:top-20 sm:right-0 sm:h-[500px] sm:w-[380px] sm:border-2 sm:shadow-lg',
            'top-left' => 'fixed inset-0 h-full w-full border-2 border-neutral-800 bg-white sm:absolute sm:inset-auto sm:top-20 sm:left-0 sm:h-[500px] sm:w-[380px] sm:border-2 sm:shadow-lg',
            default => 'fixed inset-0 h-full w-full border-2 border-neutral-800 bg-white sm:absolute sm:inset-auto sm:bottom-20 sm:left-0 sm:h-[500px] sm:w-[380px] sm:border-2 sm:shadow-lg',
        };
    }

    public static function getAllPositionClasses(): array
    {
        $positions = ['bottom-right', 'top-right', 'top-left', 'bottom-left'];
        $classes = [];

        foreach ($positions as $position) {
            $buttonClasses = explode(' ', self::getButtonClasses($position));
            $windowClasses = explode(' ', self::getWindowClasses($position));
            $classes = array_merge($classes, $buttonClasses, $windowClasses);
        }

        return array_unique($classes);
    }
}
