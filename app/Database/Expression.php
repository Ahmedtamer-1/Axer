<?php

namespace Axer\Database;

/**
 * A raw SQL fragment that bypasses identifier quoting.
 *
 * QueryBuilder now backticks every identifier it is given, which is what
 * makes orderBy()/select() safe against injection. Aggregates and
 * subqueries still need to reach the query verbatim, so they are wrapped
 * in this marker type instead of being detected by guesswork.
 *
 * Never construct one from user input.
 */
final class Expression implements \Stringable
{
    public function __construct(private readonly string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
