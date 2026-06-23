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

    public function __construct(string $table)
    {
        $this->table = $table;
        $config = App::getInstance()->getContainer()->get(Config::class);
        $this->pdo = Connection::getInstance($config);
    }

    public static function table(string $table): self
    {
        return new self($table);
    }
    
    public static function raw(string $sql): string
    {
        return $sql;
    }
    
    public static function transaction(callable $callback)
    {
        $config = App::getInstance()->getContainer()->get(Config::class);
        $pdo = Connection::getInstance($config);
        
        try {
            $pdo->beginTransaction();
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function select(string ...$columns): self
    {
        $this->selects = $columns;
        return $this;
    }

    public function where(string $column, $operator, $value = null, string $boolean = 'AND'): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = compact('column', 'operator', 'value', 'boolean');
        $this->bindings[] = $value;

        return $this;
    }
    
    public function orWhere(string $column, $operator, $value = null): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = [
            'type' => 'In',
            'column' => $column,
            'placeholders' => $placeholders,
            'boolean' => $boolean
        ];
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): self
    {
        $type = $not ? 'NotNull' : 'Null';
        $this->wheres[] = compact('type', 'column', 'boolean');
        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orderBys[] = compact('column', 'direction');
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }
    
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = compact('table', 'first', 'operator', 'second', 'type');
        return $this;
    }
    
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function get(): array
    {
        $sql = $this->buildSelectQuery();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->fetchAll();
    }
    
    public function first(): ?array
    {
        $this->limit(1);
        $result = $this->get();
        return $result ? $result[0] : null;
    }

    public function insert(array $values): bool
    {
        if (empty($values)) {
            return false;
        }

        $columns = implode(', ', array_keys($values));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array_values($values));
    }
    
    public function insertGetId(array $values): int
    {
        $this->insert($values);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(array $values): int
    {
        if (empty($values)) {
            return 0;
        }

        $set = implode(', ', array_map(fn($key) => "{$key} = ?", array_keys($values)));
        $sql = "UPDATE {$this->table} SET {$set} " . $this->buildWhereClause();
        
        $bindings = array_merge(array_values($values), $this->bindings);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table} " . $this->buildWhereClause();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->rowCount();
    }

    public function count(): int
    {
        $this->selects = ['COUNT(*) as aggregate'];
        $result = $this->first();
        return (int) ($result['aggregate'] ?? 0);
    }
    
    public function paginate(int $perPage = 20, int $page = 1): array
    {
        // Clone the builder to count total without limits/offsets
        $countBuilder = clone $this;
        $total = $countBuilder->count();
        
        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);
        $data = $this->get();
        
        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }

    protected function buildSelectQuery(): string
    {
        $sql = "SELECT " . implode(', ', $this->selects) . " FROM {$this->table}";
        
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }
        
        $sql .= $this->buildWhereClause();

        if (!empty($this->orderBys)) {
            $orders = array_map(fn($order) => "{$order['column']} {$order['direction']}", $this->orderBys);
            $sql .= " ORDER BY " . implode(', ', $orders);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    protected function buildWhereClause(): string
    {
        if (empty($this->wheres)) {
            return "";
        }

        $clauses = [];
        foreach ($this->wheres as $i => $where) {
            $boolean = $i == 0 ? '' : $where['boolean'] . ' ';
            
            if (isset($where['type']) && $where['type'] === 'In') {
                $clauses[] = "{$boolean}{$where['column']} IN ({$where['placeholders']})";
            } elseif (isset($where['type']) && $where['type'] === 'Null') {
                $clauses[] = "{$boolean}{$where['column']} IS NULL";
            } elseif (isset($where['type']) && $where['type'] === 'NotNull') {
                $clauses[] = "{$boolean}{$where['column']} IS NOT NULL";
            } else {
                $clauses[] = "{$boolean}{$where['column']} {$where['operator']} ?";
            }
        }

        return " WHERE " . implode(' ', $clauses);
    }
}
