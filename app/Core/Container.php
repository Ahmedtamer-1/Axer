<?php

namespace Axer\Core;

class Container
{
    protected array $instances = [];
    protected array $bindings = [];

    public function singleton(string $abstract, callable $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function get(string $abstract)
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $this->instances[$abstract] = call_user_func($this->bindings[$abstract], $this);
            return $this->instances[$abstract];
        }

        if (class_exists($abstract)) {
            $this->instances[$abstract] = new $abstract();
            return $this->instances[$abstract];
        }

        throw new \Exception("Target [$abstract] is not instantiable.");
    }
}
