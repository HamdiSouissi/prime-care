<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use finfo;

/**
 * Input & file validator with French error messages from lang/fr.php.
 */
final class Validator
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, array<int, string>|string> */
    private array $rules;

    /** @var array<string, array<int, string>> */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, array<int, string>|string> $rules
     */
    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->validate();
    }

    /**
     * Create and run a validator instance.
     *
     * @param array<string, mixed> $data
     * @param array<string, array<int, string>|string> $rules
     */
    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get all validation errors indexed by field name.
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get the first error message for each invalid field.
     *
     * @return array<string, string>
     */
    public function firstErrors(): array
    {
        $first = [];
        foreach ($this->errors as $field => $messages) {
            if (!empty($messages)) {
                $first[$field] = $messages[0];
            }
        }
        return $first;
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $fieldRules) {
            $ruleList = is_array($fieldRules) ? $fieldRules : explode('|', (string) $fieldRules);
            $value = $this->data[$field] ?? null;
            $isNullable = in_array('nullable', $ruleList, true);

            if ($this->isEmpty($value) && !in_array('required', $ruleList, true)) {
                if ($isNullable || !in_array('file', $ruleList, true)) {
                    continue;
                }
            }

            foreach ($ruleList as $ruleString) {
                if ($ruleString === '' || $ruleString === 'nullable') {
                    continue;
                }

                $parts = explode(':', $ruleString, 2);
                $ruleName = $parts[0];
                $paramStr = $parts[1] ?? '';
                $params = $paramStr !== '' ? explode(',', $paramStr) : [];

                if (!$this->checkRule($field, $value, $ruleName, $params, $paramStr)) {
                    break;
                }
            }
        }
    }

    /**
     * @param array<int, string> $params
     */
    private function checkRule(
        string $field,
        mixed $value,
        string $rule,
        array $params,
        string $rawParam
    ): bool {
        $label = Lang::get('fields.' . $field);
        if ($label === 'fields.' . $field) {
            $label = str_replace('_', ' ', $field);
        }

        switch ($rule) {
            case 'required':
                if ($this->isEmpty($value)) {
                    $this->addError($field, Lang::get('validation.required', ['field' => $label]));
                    return false;
                }
                return true;

            case 'email':
                if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $this->addError($field, Lang::get('validation.email', ['field' => $label]));
                    return false;
                }
                return true;

            case 'min':
                $min = (int) ($params[0] ?? 0);
                $len = is_string($value) ? mb_strlen($value) : (is_numeric($value) ? (float) $value : 0);
                if ($len < $min) {
                    $this->addError($field, Lang::get('validation.min', ['field' => $label, 'min' => $min]));
                    return false;
                }
                return true;

            case 'max':
                $max = (int) ($params[0] ?? 0);
                $len = is_string($value) ? mb_strlen($value) : (is_numeric($value) ? (float) $value : 0);
                if ($len > $max) {
                    $this->addError($field, Lang::get('validation.max', ['field' => $label, 'max' => $max]));
                    return false;
                }
                return true;

            case 'between':
                $min = (int) ($params[0] ?? 0);
                $max = (int) ($params[1] ?? 0);
                $len = is_string($value) ? mb_strlen($value) : (is_numeric($value) ? (float) $value : -1);
                if ($len < $min || $len > $max) {
                    $this->addError(
                        $field,
                        Lang::get('validation.between', ['field' => $label, 'min' => $min, 'max' => $max])
                    );
                    return false;
                }
                return true;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, Lang::get('validation.numeric', ['field' => $label]));
                    return false;
                }
                return true;

            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, Lang::get('validation.integer', ['field' => $label]));
                    return false;
                }
                return true;

            case 'date':
                if (!is_string($value)) {
                    $this->addError($field, Lang::get('validation.date', ['field' => $label]));
                    return false;
                }
                $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
                if ($dt === false || $dt->format('Y-m-d') !== $value) {
                    $this->addError($field, Lang::get('validation.date', ['field' => $label]));
                    return false;
                }
                return true;

            case 'time':
                if (!is_string($value) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) {
                    $this->addError($field, Lang::get('validation.time', ['field' => $label]));
                    return false;
                }
                return true;

            case 'in':
                if (!in_array((string) $value, $params, true)) {
                    $this->addError($field, Lang::get('validation.in', ['field' => $label]));
                    return false;
                }
                return true;

            case 'regex':
                if (!is_string($value) || @preg_match($rawParam, $value) !== 1) {
                    $this->addError($field, Lang::get('validation.regex', ['field' => $label]));
                    return false;
                }
                return true;

            case 'unique':
                // Format: unique:table,column[,ignoreId]
                $table = preg_replace('/[^a-zA-Z0-9_]/', '', $params[0] ?? '') ?: '';
                $column = preg_replace('/[^a-zA-Z0-9_]/', '', $params[1] ?? $field) ?: $field;
                $ignoreId = isset($params[2]) && $params[2] !== '' ? (int) $params[2] : null;

                $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = :val AND `deleted_at` IS NULL', $table, $column);
                $bind = ['val' => (string) $value];
                if ($ignoreId !== null) {
                    $sql .= ' AND `id` != :ignore_id';
                    $bind['ignore_id'] = $ignoreId;
                }
                $stmt = Database::connection()->prepare($sql);
                $stmt->execute($bind);
                if ((int) $stmt->fetchColumn() > 0) {
                    $this->addError($field, Lang::get('validation.unique', ['field' => $label]));
                    return false;
                }
                return true;

            case 'confirmed':
                $confirmField = $field . '_confirmation';
                $confirmVal = $this->data[$confirmField] ?? null;
                if ($value !== $confirmVal) {
                    $this->addError($field, Lang::get('validation.confirmed', ['field' => $label]));
                    return false;
                }
                return true;

            case 'file':
                if (!is_array($value) || ($value['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $this->addError($field, Lang::get('validation.file', ['field' => $label]));
                    return false;
                }
                return true;

            case 'image':
                if (!is_array($value) || ($value['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $this->addError($field, Lang::get('validation.image', ['field' => $label]));
                    return false;
                }
                $tmpName = (string) ($value['tmp_name'] ?? '');
                if ($tmpName === '' || !is_file($tmpName)) {
                    $this->addError($field, Lang::get('validation.image', ['field' => $label]));
                    return false;
                }
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string) $finfo->file($tmpName);
                if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    $this->addError($field, Lang::get('validation.image', ['field' => $label]));
                    return false;
                }
                return true;

            case 'max_size':
                // Parameter in KB (e.g., max_size:5120 for 5 MB)
                $maxKb = (int) ($params[0] ?? 5120);
                $maxBytes = $maxKb * 1024;
                $size = is_array($value) ? (int) ($value['size'] ?? 0) : 0;
                if ($size > $maxBytes) {
                    $maxMb = round($maxKb / 1024, 1);
                    $this->addError($field, Lang::get('validation.max_size', ['field' => $label, 'max' => $maxMb]));
                    return false;
                }
                return true;
        }

        return true;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (is_array($value)) {
            if (isset($value['error']) && (int) $value['error'] === UPLOAD_ERR_NO_FILE) {
                return true;
            }
            return empty($value);
        }
        return false;
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
