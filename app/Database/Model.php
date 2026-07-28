<?php

namespace Axer\Database;

abstract class Model implements \JsonSerializable
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $guarded = ['id'];
    protected array $casts = [];
    protected bool $timestamps = true;
    protected bool $softDelete = false;

    protected array $attributes = [];
    protected array $original = [];

    /** Set once the row is known to exist in the database. */
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        if ($this->table === '') {
            $class = (new \ReflectionClass($this))->getShortName();
            $this->table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class)) . 's';
        }

        $this->fill($attributes);
    }

    public static function query(): QueryBuilder
    {
        $instance = new static();
        $query = QueryBuilder::table($instance->getTable());

        if ($instance->usesSoftDeletes()) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    public static function all(): array
    {
        return static::hydrateMany(static::query()->get());
    }

    public static function find($id): ?static
    {
        if ($id === null || $id === '') {
            return null;
        }

        $instance = new static();
        $record = static::query()->where($instance->getKeyName(), $id)->first();

        return $record ? static::hydrate($record) : null;
    }

    /**
     * Fetch many models by primary key in one query.
     */
    public static function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $instance = new static();

        return static::hydrateMany(
            static::query()->whereIn($instance->getKeyName(), array_values(array_unique($ids)))->get()
        );
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();

        return $model;
    }

    /**
     * Build a model from a database row. Rows loaded this way are marked as
     * existing so the next save() issues an UPDATE, not an INSERT.
     */
    public static function hydrate(array $record): static
    {
        $model = new static();
        $model->setRawAttributes($record, true);
        $model->exists = true;

        return $model;
    }

    /** @return static[] */
    public static function hydrateMany(array $records): array
    {
        return array_map(static fn(array $row) => static::hydrate($row), $records);
    }

    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    public function isFillable(string $key): bool
    {
        if (in_array($key, $this->guarded, true)) {
            return false;
        }

        if ($this->fillable === []) {
            return true;
        }

        return in_array($key, $this->fillable, true);
    }

    public function setAttribute(string $key, $value): void
    {
        if (isset($this->casts[$key])) {
            $value = $this->castForStorage($this->casts[$key], $value);
        }

        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key)
    {
        $value = $this->attributes[$key] ?? null;

        if ($value !== null && isset($this->casts[$key])) {
            return $this->castFromStorage($this->casts[$key], $value);
        }

        return $value;
    }

    /**
     * MySQL hands every column back as a string. Casting turns them into
     * the types callers actually expect, so `$product->price * 2` and
     * `if ($product->featured)` behave.
     */
    protected function castFromStorage(string $type, $value)
    {
        return match ($type) {
            'int', 'integer' => (int) $value,
            'float', 'double', 'decimal' => (float) $value,
            'bool', 'boolean' => (bool) $value && $value !== '0',
            'json', 'array' => is_string($value) ? (json_decode($value, true) ?? []) : $value,
            'datetime' => $value,
            default => $value,
        };
    }

    protected function castForStorage(string $type, $value)
    {
        if (in_array($type, ['json', 'array'], true) && (is_array($value) || is_object($value))) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (in_array($type, ['bool', 'boolean'], true) && is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $value;
    }

    /**
     * Attribute access.
     *
     * Relation methods are only consulted when there is no attribute of
     * that name. The old order checked method_exists() first, so a column
     * called "delete" or "save" would *invoke that method* on a property
     * read.
     */
    public function __get(string $key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttribute($key);
        }

        // Only public relation-style methods, never the persistence API.
        if (!in_array($key, self::reservedMethods(), true) && method_exists($this, $key)) {
            return $this->$key();
        }

        return null;
    }

    public function __set(string $key, $value)
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    protected static function reservedMethods(): array
    {
        return [
            'save', 'delete', 'fill', 'query', 'all', 'find', 'findMany', 'create',
            'hydrate', 'hydrateMany', 'exists', 'getKey', 'getKeyName', 'getTable',
            'toArray', 'jsonSerialize', 'isDirty', 'getChanges', 'refresh',
        ];
    }

    public function save(): bool
    {
        $this->beforeSave();

        if ($this->timestamps) {
            $this->attributes['updated_at'] = date('Y-m-d H:i:s');

            if (!$this->exists()) {
                $this->attributes['created_at'] = date('Y-m-d H:i:s');
            }
        }

        $query = QueryBuilder::table($this->table);

        if ($this->exists()) {
            $attributes = $this->attributes;
            // Never rewrite the primary key while using it as the target.
            unset($attributes[$this->primaryKey]);

            if ($attributes === []) {
                return true;
            }

            $query->where($this->primaryKey, $this->getKey())->update($attributes);

            // rowCount() is 0 when the values are unchanged. Treating that
            // as failure meant a no-op save reported an error and skipped
            // afterSave().
            $success = true;
        } else {
            $id = $query->insertGetId($this->attributes);
            $success = $id > 0;

            if ($success) {
                $this->attributes[$this->primaryKey] = $id;
                $this->exists = true;
            }
        }

        if ($success) {
            $this->original = $this->attributes;
            $this->afterSave();
        }

        return $success;
    }

    public function update(array $attributes): bool
    {
        return $this->fill($attributes)->save();
    }

    public function delete(): bool
    {
        $this->beforeDelete();

        if (!$this->exists()) {
            return false;
        }

        $query = QueryBuilder::table($this->table)->where($this->primaryKey, $this->getKey());

        if ($this->softDelete) {
            $success = $query->update(['deleted_at' => date('Y-m-d H:i:s')]) > 0;
        } else {
            $success = $query->delete() > 0;
        }

        if ($success) {
            $this->exists = false;
        }

        return $success;
    }

    /**
     * Does this model correspond to a stored row?
     *
     * Previously this only checked whether an `id` attribute was present,
     * so `new Product(['id' => 7])` was considered persisted and save()
     * issued an UPDATE against a row that might not exist.
     */
    public function exists(): bool
    {
        return $this->exists || isset($this->original[$this->primaryKey]);
    }

    public function getKey()
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function usesSoftDeletes(): bool
    {
        return $this->softDelete;
    }

    public function setRawAttributes(array $attributes, bool $sync = false): self
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->original = $attributes;
        }

        return $this;
    }

    public function isDirty(): bool
    {
        return $this->getChanges() !== [];
    }

    /**
     * @return array attributes whose value differs from the loaded row
     */
    public function getChanges(): array
    {
        $changes = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $changes[$key] = $value;
            }
        }

        return $changes;
    }

    public function refresh(): self
    {
        if (!$this->exists()) {
            return $this;
        }

        $fresh = QueryBuilder::table($this->table)->where($this->primaryKey, $this->getKey())->first();

        if ($fresh) {
            $this->setRawAttributes($fresh, true);
        }

        return $this;
    }

    public function toArray(): array
    {
        $array = [];

        foreach ($this->attributes as $key => $value) {
            $array[$key] = $this->getAttribute($key);
        }

        return $array;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    // Event hooks
    protected function beforeSave(): void {}
    protected function afterSave(): void {}
    protected function beforeDelete(): void {}

    /**
     * @return static[] related models
     */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): array
    {
        $foreignKey = $foreignKey ?: strtolower((new \ReflectionClass($this))->getShortName()) . '_id';
        $localKey = $localKey ?: $this->primaryKey;

        $rows = $related::query()->where($foreignKey, $this->getAttribute($localKey))->get();

        // The old version returned bare arrays here, so callers could not
        // use any model behaviour on the results.
        return $related::hydrateMany($rows);
    }

    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null)
    {
        $foreignKey = $foreignKey ?: strtolower((new \ReflectionClass($related))->getShortName()) . '_id';

        return $related::find($this->getAttribute($foreignKey));
    }

    public static function __callStatic(string $method, array $parameters)
    {
        return static::query()->$method(...$parameters);
    }
}
