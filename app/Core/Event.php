<?php

namespace Axer\Core;

class Event
{
    protected static array $listeners = [];

    public static function listen(string $event, callable $callback): void
    {
        self::$listeners[$event][] = $callback;
    }

    public static function dispatch(string $event, ...$args)
    {
        $responses = [];
        if (isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $listener) {
                $responses[] = call_user_func_array($listener, $args);
            }
        }
        return $responses;
    }
}
