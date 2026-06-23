<?php

namespace Lume\Database;

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

    public function __construct(array $attributes = [])
    {
        if (empty($this->table)) {
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
        return static::query()->get();
    }

    public static function find($id)
    {
        $instance = new static();
        $record = static::query()->where($instance->getKeyName(), $id)->first();
        
        if ($record) {
            $model = new static();
            $model->setRawAttributes($record, true);
            return $model;
        }
        
        return null;
    }

    public static function create(array $attributes)
    {
        $model = new static($attributes);
        $model->save();
        return $model;
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
        if (in_array($key, $this->guarded)) {
            return false;
        }
        if (empty($this->fillable)) {
            return true;
        }
        return in_array($key, $this->fillable);
    }

    public function setAttribute(string $key, $value): void
    {
        if (isset($this->casts[$key])) {
            if ($this->casts[$key] === 'json' && is_array($value)) {
                $value = json_encode($value);
            }
        }
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key)
    {
        $value = $this->attributes[$key] ?? null;
        
        if ($value !== null && isset($this->casts[$key])) {
            if ($this->casts[$key] === 'json' && is_string($value)) {
                return json_decode($value, true);
            }
        }
        
        return $value;
    }

    public function __get(string $key)
    {
        // Check for relation methods first
        if (method_exists($this, $key)) {
            return $this->$key();
        }
        return $this->getAttribute($key);
    }

    public function __set(string $key, $value)
    {
        $this->setAttribute($key, $value);
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
        $attributes = $this->attributes;
        
        if ($this->exists()) {
            $success = $query->where($this->primaryKey, $this->getKey())->update($attributes) > 0;
        } else {
            $id = $query->insertGetId($attributes);
            $this->setAttribute($this->primaryKey, $id);
            $success = $id > 0;
        }

        if ($success) {
            $this->original = $this->attributes;
            $this->afterSave();
        }

        return $success;
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

        return $success;
    }

    public function exists(): bool
    {
        return isset($this->attributes[$this->primaryKey]);
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

    // Relations placeholders (To be implemented fully if needed, basic representation)
    protected function hasMany(string $related, string $foreignKey = null, string $localKey = null)
    {
        $instance = new $related;
        $foreignKey = $foreignKey ?: strtolower((new \ReflectionClass($this))->getShortName()) . '_id';
        $localKey = $localKey ?: $this->primaryKey;
        
        return $related::query()->where($foreignKey, $this->getAttribute($localKey))->get();
    }

    protected function belongsTo(string $related, string $foreignKey = null, string $ownerKey = null)
    {
        $instance = new $related;
        $foreignKey = $foreignKey ?: strtolower((new \ReflectionClass($related))->getShortName()) . '_id';
        $ownerKey = $ownerKey ?: $instance->getKeyName();
        
        return $related::find($this->getAttribute($foreignKey));
    }
    
    public static function __callStatic(string $method, array $parameters)
    {
        return static::query()->$method(...$parameters);
    }
}
