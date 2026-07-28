<?php

namespace Axer\Database;

use PDO;
use Axer\Core\App;
use Axer\Core\Config;

class QueryBuilder
{
    protected PDO $pdo;
    protected string $table;
    protected array $selects = ['*'];
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $orderBys = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $joins = [];
    protected array $groupBys = [];

    /** Marker so a genuine NULL can be told apart from "argument omitted". */
    private const NO_VALUE = "\0__axer_no_value__\0";

    public function __construct(string $table)
    {
        $this->table = $this->qualifyTable($table);
        $this->pdo = Connection::resolve();
    }

    public static function table(string $table): self
    {
        return new self($table);
    }

    /**
     * Mark a fragment as raw SQL.
     *
     * This performs NO escaping — never hand it user input. It exists so
     * callers can write aggregates like SUM(total).
     */
    public static function raw(string $sql): Expression
    {
        return new Expression($sql);
    }

    public static function transaction(callable $callback)
    {
        $pdo = Connection::resolve();

        // Nested transaction() calls would previously throw, because PDO
        // refuses a second beginTransaction(). Join the outer one instead.
        if ($pdo->inTransaction()) {
            return $callback($pdo);
        }

        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            // Catch Throwable, not Exception: a TypeError inside the callback
            // used to escape without ever rolling the transaction back,
            // leaving the connection wedged mid-transaction.
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    public function select(...$columns): self
    {
        $this->selects = $columns ?: ['*'];
        return $this;
    }

    public function addSelect(...$columns): self
    {
        if ($this->selects === ['*']) {
            $this->selects = [];
        }

        $this->selects = array_merge($this->selects, $columns);
        return $this;
    }

    /**
     * where('col', 5) / where('col', '>', 5) / where('col', '=', null)
     *
     * The old signature used `$value === null` to detect the two-argument
     * form, which made it impossible to bind a real NULL and silently
     * rewrote where('expires_at', '>', null) into where('expires_at', '=',
     * '>'). A sentinel default fixes both.
     */
    public function where($column, $operator = self::NO_VALUE, $value = self::NO_VALUE, string $boolean = 'AND'): self
    {
        // where(function ($q) { ... }) — a nested group.
        if ($column instanceof \Closure) {
            return $this->whereGroup($column, $boolean);
        }

        if ($value === self::NO_VALUE) {
            $value = $operator;
            $operator = '=';
        }

        if ($value === self::NO_VALUE) {
            throw new \InvalidArgumentException('where() requires a value.');
        }

        // NULL cannot be compared with = in SQL; translate automatically.
        if ($value === null && in_array($operator, ['=', '!=', '<>'], true)) {
            return $this->whereNull($column, $boolean, $operator !== '=');
        }

        $this->assertOperator($operator);

        $this->wheres[] = [
            'type' => 'Basic',
            'column' => $column,
            'operator' => $operator,
            'boolean' => $boolean,
        ];
        $this->bindings[] = $value;

        return $this;
    }

    public function orWhere($column, $operator = self::NO_VALUE, $value = self::NO_VALUE): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * Nested where group, so AND/OR precedence can be controlled:
     *
     *   ->where('token', $t)
     *   ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now))
     *
     * produces `token = ? AND (expires_at IS NULL OR expires_at > ?)`.
     * Without this, mixing where() and orWhere() produced SQL whose
     * precedence did not match the caller's intent.
     */
    public function whereGroup(\Closure $callback, string $boolean = 'AND'): self
    {
        $nested = new self($this->rawTableName());
        $callback($nested);

        if ($nested->wheres === []) {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'Nested',
            'sql' => $nested->buildWhereClause(false),
            'boolean' => $boolean,
        ];
        $this->bindings = array_merge($this->bindings, $nested->bindings);

        return $this;
    }

    public function orWhereGroup(\Closure $callback): self
    {
        return $this->whereGroup($callback, 'OR');
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self
    {
        // IN () is a syntax error in MySQL. An empty set matches nothing
        // (or everything, when negated) — express that directly.
        if ($values === []) {
            $this->wheres[] = [
                'type' => 'Raw',
                'sql' => $not ? '1 = 1' : '1 = 0',
                'boolean' => $boolean,
            ];

            return $this;
        }

        $values = array_values($values);

        $this->wheres[] = [
            'type' => 'In',
            'column' => $column,
            'placeholders' => implode(', ', array_fill(0, count($values), '?')),
            'not' => $not,
            'boolean' => $boolean,
        ];
        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): self
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): self
    {
        $this->wheres[] = [
            'type' => $not ? 'NotNull' : 'Null',
            'column' => $column,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        return $this->whereNull($column, $boolean, true);
    }

    public function orWhereNull(string $column): self
    {
        return $this->whereNull($column, 'OR');
    }

    public function whereLike(string $column, string $value, string $boolean = 'AND'): self
    {
        // Escape the LIKE wildcards so a search for "100%" is a literal
        // match rather than a prefix match. The ESCAPE clause is spelled
        // out because backslash is only the implicit escape character in
        // MySQL, not in standard SQL.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);

        $this->wheres[] = [
            'type' => 'Like',
            'column' => $column,
            'boolean' => $boolean,
        ];
        $this->bindings[] = '%' . $escaped . '%';

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $this->orderBys[] = [
            'column' => $this->wrapIdentifier($column),
            'direction' => $direction,
        ];

        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->groupBys[] = $this->wrapIdentifier($column);
        }

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $type = strtoupper($type);

        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT'], true)) {
            throw new \InvalidArgumentException("Unsupported join type: {$type}");
        }

        $this->assertOperator($operator);

        $this->joins[] = [
            'table' => $this->qualifyTable($table),
            'first' => $this->wrapIdentifier($first),
            'operator' => $operator,
            'second' => $this->wrapIdentifier($second),
            'type' => $type,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function get(): array
    {
        $stmt = $this->pdo->prepare($this->buildSelectQuery());
        $stmt->execute($this->bindings);

        return $stmt->fetchAll();
    }

    public function first(): ?array
    {
        // Take a copy so first() does not permanently pin LIMIT 1 onto a
        // builder the caller may want to reuse.
        $clone = clone $this;
        $clone->limit(1);

        $result = $clone->get();

        return $result[0] ?? null;
    }

    /**
     * Single column from every row — handy for building whereIn() lists.
     */
    public function pluck(string $column): array
    {
        $rows = $this->get();
        $short = str_contains($column, '.') ? substr(strrchr($column, '.'), 1) : $column;

        return array_column($rows, $short);
    }

    /**
     * Index rows by a column. Used to turn a single batched query into a
     * lookup table and avoid per-row follow-up queries.
     */
    public function keyBy(string $column): array
    {
        $out = [];

        foreach ($this->get() as $row) {
            if (isset($row[$column])) {
                $out[$row[$column]] = $row;
            }
        }

        return $out;
    }

    public function exists(): bool
    {
        return $this->first() !== null;
    }

    public function insert(array $values): bool
    {
        if ($values === []) {
            return false;
        }

        $columns = implode(', ', array_map([$this, 'wrapColumn'], array_keys($values)));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})");

        return $stmt->execute(array_values($values));
    }

    /**
     * Insert many rows in a single statement.
     */
    public function insertMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $columns = array_keys(reset($rows));
        $wrapped = implode(', ', array_map([$this, 'wrapColumn'], $columns));
        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $bindings = [];

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $bindings[] = $row[$column] ?? null;
            }
        }

        $sql = "INSERT INTO {$this->table} ({$wrapped}) VALUES "
            . implode(', ', array_fill(0, count($rows), $rowPlaceholder));

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    public function insertGetId(array $values): int
    {
        if (!$this->insert($values)) {
            return 0;
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function update(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $set = implode(', ', array_map(fn($key) => $this->wrapColumn($key) . ' = ?', array_keys($values)));
        $sql = "UPDATE {$this->table} SET {$set}" . $this->buildWhereClause();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge(array_values($values), $this->bindings));

        return $stmt->rowCount();
    }

    public function increment(string $column, int $amount = 1): int
    {
        $wrapped = $this->wrapColumn($column);
        $sql = "UPDATE {$this->table} SET {$wrapped} = {$wrapped} + ?" . $this->buildWhereClause();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$amount], $this->bindings));

        return $stmt->rowCount();
    }

    public function delete(): int
    {
        // A DELETE with no WHERE empties the table. Require an explicit
        // truncate() for that instead of doing it by accident.
        if ($this->wheres === []) {
            throw new \RuntimeException(
                'Refusing to run DELETE without a WHERE clause. Use truncate() if that is intended.'
            );
        }

        $stmt = $this->pdo->prepare("DELETE FROM {$this->table}" . $this->buildWhereClause());
        $stmt->execute($this->bindings);

        return $stmt->rowCount();
    }

    public function truncate(): void
    {
        $this->pdo->exec("TRUNCATE TABLE {$this->table}");
    }

    public function count(): int
    {
        $clone = clone $this;
        $clone->selects = [self::raw('COUNT(*) AS aggregate')];
        $clone->orderBys = [];   // ORDER BY on an aggregate is dead weight
        $clone->limit = null;
        $clone->offset = null;

        $result = $clone->first();

        return (int) ($result['aggregate'] ?? 0);
    }

    public function sum(string $column): float
    {
        $clone = clone $this;
        $clone->selects = [self::raw('SUM(' . $this->wrapIdentifier($column) . ') AS aggregate')];
        $clone->orderBys = [];
        $clone->limit = null;
        $clone->offset = null;

        $result = $clone->first();

        return (float) ($result['aggregate'] ?? 0);
    }

    public function paginate(int $perPage = 20, int $page = 1): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);

        $total = $this->count();

        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);

        $lastPage = (int) max(1, ceil($total / $perPage));

        return [
            'data' => $this->get(),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
            'from' => $total ? (($page - 1) * $perPage) + 1 : 0,
            'to' => min($page * $perPage, $total),
            'has_more' => $page < $lastPage,
        ];
    }

    protected function buildSelectQuery(): string
    {
        $columns = implode(', ', array_map([$this, 'wrapSelect'], $this->selects));
        $sql = "SELECT {$columns} FROM {$this->table}";

        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        $sql .= $this->buildWhereClause();

        if ($this->groupBys !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBys);
        }

        if ($this->orderBys !== []) {
            $orders = array_map(fn($o) => "{$o['column']} {$o['direction']}", $this->orderBys);
            $sql .= ' ORDER BY ' . implode(', ', $orders);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        // MySQL rejects OFFSET without LIMIT.
        if ($this->offset !== null && $this->offset > 0) {
            if ($this->limit === null) {
                $sql .= ' LIMIT 18446744073709551615';
            }

            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    protected function buildWhereClause(bool $withKeyword = true): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $clauses = [];

        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? '' : $where['boolean'] . ' ';
            $column = isset($where['column']) ? $this->wrapIdentifier($where['column']) : '';

            $clauses[] = match ($where['type']) {
                'In' => $boolean . $column . ($where['not'] ? ' NOT IN ' : ' IN ') . "({$where['placeholders']})",
                'Null' => $boolean . $column . ' IS NULL',
                'NotNull' => $boolean . $column . ' IS NOT NULL',
                'Like' => $boolean . $column . " LIKE ? ESCAPE '\\'",
                'Nested' => $boolean . '(' . $where['sql'] . ')',
                'Raw' => $boolean . $where['sql'],
                default => $boolean . $column . ' ' . $where['operator'] . ' ?',
            };
        }

        return ($withKeyword ? ' WHERE ' : '') . implode(' ', $clauses);
    }

    protected function rawTableName(): string
    {
        return str_replace('`', '', $this->table);
    }

    /**
     * Only a fixed set of comparison operators may reach the SQL string.
     */
    protected function assertOperator(string $operator): void
    {
        static $allowed = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];

        if (!in_array(strtoupper($operator), $allowed, true)) {
            throw new \InvalidArgumentException("Unsupported SQL operator: {$operator}");
        }
    }

    /**
     * Backtick an identifier so a column name can never carry SQL.
     *
     * orderBy() and select() interpolated their arguments straight into the
     * query, so any endpoint that let a user choose a sort column was an
     * injection point.
     */
    protected function wrapIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        if ($identifier === '*') {
            return '*';
        }

        return implode('.', array_map(static function (string $part): string {
            $part = trim($part);

            if ($part === '*') {
                return '*';
            }

            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                throw new \InvalidArgumentException("Invalid SQL identifier: {$part}");
            }

            return '`' . $part . '`';
        }, explode('.', $identifier)));
    }

    protected function wrapColumn(string $column): string
    {
        return $this->wrapIdentifier($column);
    }

    protected function qualifyTable(string $table): string
    {
        return $this->wrapIdentifier($table);
    }

    /**
     * Select entries may be plain columns or Expression objects.
     */
    protected function wrapSelect($column): string
    {
        if ($column instanceof Expression) {
            return (string) $column;
        }

        // Support the legacy "col AS alias" form.
        if (preg_match('/^(.+?)\s+AS\s+(.+)$/i', (string) $column, $m)) {
            return $this->wrapIdentifier($m[1]) . ' AS ' . $this->wrapIdentifier($m[2]);
        }

        return $this->wrapIdentifier((string) $column);
    }
}
