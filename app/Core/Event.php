<?php

namespace Axer\Core;

class Event
{
    protected static array $listeners = [];

    public static function listen(string $event, callable $callback, int $priority = 10): void
    {
        self::$listeners[$event][$priority][] = $callback;
    }

    public static function dispatch(string $event, ...$args): array
    {
        $responses = [];
        if (!isset(self::$listeners[$event])) {
            return $responses;
        }

        // Sort by priority descending
        krsort(self::$listeners[$event]);

        foreach (self::$listeners[$event] as $priorityGroup) {
            foreach ($priorityGroup as $listener) {
                $responses[] = call_user_func_array($listener, $args);
            }
        }

        return $responses;
    }

    public static function filter(string $event, $value, array $context = [])
    {
        if (!isset(self::$listeners[$event])) {
            return $value;
        }

        krsort(self::$listeners[$event]);

        foreach (self::$listeners[$event] as $priorityGroup) {
            foreach ($priorityGroup as $listener) {
                $value = call_user_func($listener, $value, $context);
            }
        }

        return $value;
    }
}
