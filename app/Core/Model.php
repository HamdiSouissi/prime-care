<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Abstract base Model with prepared PDO queries, soft-deletes, UTC timestamps, and fillable whitelist.
 */
abstract class Model
{
    protected string $table = '';

    protected string $primaryKey = 'id';

    /** @var array<int, string> */
    protected array $fillable = [];

    protected bool $usesSoftDeletes = true;

    protected bool $usesTimestamps = true;

    protected bool $usesCreatedBy = true;

    /**
     * Find a record by primary key, excluding soft-deleted rows by default.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id, bool $withTrashed = false): ?array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `%s` = :id',
            $this->safeIdentifier($this->table),
            $this->safeIdentifier($this->primaryKey)
        );

        if ($this->usesSoftDeletes && !$withTrashed) {
            $sql .= ' AND `deleted_at` IS NULL';
        }

        $sql .= ' LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Find a record by primary key or throw a 404 RuntimeException.
     *
     * @return array<string, mixed>
     */
    public function findOrFail(int $id, bool $withTrashed = false): array
    {
        $record = $this->find($id, $withTrashed);
        if ($record === null) {
            throw new RuntimeException('Ressource introuvable.', 404);
        }

        return $record;
    }

    /**
     * Query records matching equality conditions.
     *
     * @param array<string, mixed> $conditions
     * @return array<int, array<string, mixed>>
     */
    public function where(
        array $conditions = [],
        string $orderBy = 'id',
        string $direction = 'ASC',
        bool $withTrashed = false
    ): array {
        $clauses = [];
        $params = [];

        if ($this->usesSoftDeletes && !$withTrashed) {
            $clauses[] = '`deleted_at` IS NULL';
        }

        foreach ($conditions as $column => $value) {
            $col = $this->safeIdentifier((string) $column);
            if ($value === null) {
                $clauses[] = sprintf('`%s` IS NULL', $col);
            } else {
                $paramKey = 'w_' . $col;
                $clauses[] = sprintf('`%s` = :%s', $col, $paramKey);
                $params[$paramKey] = $value;
            }
        }

        $whereSql = empty($clauses) ? '' : ' WHERE ' . implode(' AND ', $clauses);
        $safeOrder = $this->safeIdentifier($orderBy);
        $safeDir = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $sql = sprintf(
            'SELECT * FROM `%s`%s ORDER BY `%s` %s',
            $this->safeIdentifier($this->table),
            $whereSql,
            $safeOrder,
            $safeDir
        );

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    /**
     * Find the first record matching conditions.
     *
     * @param array<string, mixed> $conditions
     * @return array<string, mixed>|null
     */
    public function firstWhere(array $conditions, bool $withTrashed = false): ?array
    {
        $rows = $this->where($conditions, $this->primaryKey, 'ASC', $withTrashed);
        return $rows[0] ?? null;
    }

    /**
     * Insert a new record using the fillable whitelist, UTC timestamps, and created_by.
     *
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): int
    {
        $filtered = $this->filterFillable($attributes);
        $now = gmdate('Y-m-d H:i:s');

        if ($this->usesTimestamps) {
            $filtered['created_at'] = $filtered['created_at'] ?? $now;
            $filtered['updated_at'] = $filtered['updated_at'] ?? $now;
        }

        if ($this->usesCreatedBy && !array_key_exists('created_by', $filtered)) {
            $filtered['created_by'] = Auth::id();
        }

        $columns = array_keys($filtered);
        $escapedCols = array_map(fn(string $col): string => '`' . $this->safeIdentifier($col) . '`', $columns);
        $placeholders = array_map(fn(string $col): string => ':' . $this->safeIdentifier($col), $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->safeIdentifier($this->table),
            implode(', ', $escapedCols),
            implode(', ', $placeholders)
        );

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($filtered);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Update an existing record by primary key using the fillable whitelist.
     *
     * @param array<string, mixed> $attributes
     */
    public function update(int $id, array $attributes): bool
    {
        $filtered = $this->filterFillable($attributes);
        if ($this->usesTimestamps) {
            $filtered['updated_at'] = gmdate('Y-m-d H:i:s');
        }

        if (empty($filtered)) {
            return false;
        }

        $sets = [];
        $params = ['pk_id' => $id];
        foreach ($filtered as $column => $value) {
            $col = $this->safeIdentifier($column);
            $sets[] = sprintf('`%s` = :u_%s', $col, $col);
            $params['u_' . $col] = $value;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = :pk_id',
            $this->safeIdentifier($this->table),
            implode(', ', $sets),
            $this->safeIdentifier($this->primaryKey)
        );

        $stmt = Database::connection()->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Soft-delete a record by setting deleted_at and updated_at in UTC.
     */
    public function softDelete(int $id): bool
    {
        if (!$this->usesSoftDeletes) {
            throw new RuntimeException('Cette table ne supporte pas la suppression logique.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $sql = sprintf(
            'UPDATE `%s` SET `deleted_at` = :deleted_at, `updated_at` = :updated_at WHERE `%s` = :id',
            $this->safeIdentifier($this->table),
            $this->safeIdentifier($this->primaryKey)
        );

        $stmt = Database::connection()->prepare($sql);
        return $stmt->execute([
            'deleted_at' => $now,
            'updated_at' => $now,
            'id' => $id,
        ]);
    }

    /**
     * Paginate records with optional equality conditions.
     *
     * @param array<string, mixed> $conditions
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public function paginate(
        int $page = 1,
        int $perPage = 20,
        array $conditions = [],
        string $orderBy = 'id',
        string $direction = 'DESC'
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $clauses = [];
        $params = [];

        if ($this->usesSoftDeletes) {
            $clauses[] = '`deleted_at` IS NULL';
        }

        foreach ($conditions as $column => $value) {
            $col = $this->safeIdentifier((string) $column);
            if ($value === null) {
                $clauses[] = sprintf('`%s` IS NULL', $col);
            } else {
                $paramKey = 'p_' . $col;
                $clauses[] = sprintf('`%s` = :%s', $col, $paramKey);
                $params[$paramKey] = $value;
            }
        }

        $whereSql = empty($clauses) ? '' : ' WHERE ' . implode(' AND ', $clauses);
        $table = $this->safeIdentifier($this->table);

        $countSql = sprintf('SELECT COUNT(*) FROM `%s`%s', $table, $whereSql);
        $countStmt = Database::connection()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $safeOrder = $this->safeIdentifier($orderBy);
        $safeDir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $itemsSql = sprintf(
            'SELECT * FROM `%s`%s ORDER BY `%s` %s LIMIT :limit OFFSET :offset',
            $table,
            $whereSql,
            $safeOrder,
            $safeDir
        );

        $stmt = Database::connection()->prepare($itemsSql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        /** @var array<int, array<string, mixed>> $items */
        $items = $stmt->fetchAll();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Filter attributes against the model's $fillable whitelist.
     *
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function filterFillable(array $attributes): array
    {
        $filtered = [];
        foreach ($this->fillable as $field) {
            if (array_key_exists($field, $attributes)) {
                $filtered[$field] = $attributes[$field];
            }
        }

        return $filtered;
    }

    /**
     * Strictly validate a SQL identifier (table or column name).
     */
    protected function safeIdentifier(string $identifier): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException('Identifiant SQL invalide : ' . $identifier);
        }

        return $identifier;
    }
}
