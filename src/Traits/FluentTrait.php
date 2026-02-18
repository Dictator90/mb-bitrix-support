<?php

namespace MB\Bitrix\Traits;

use BadMethodCallException;
use Bitrix\Main\Text\StringHelper;
use InvalidArgumentException;
use MB\Support\Conditionable\Condition;
use MB\Support\Str;

/**
 * Trait for implementing Fluent Interface with setters and getters
 *
 * Provides magic methods for fluent property access using set/get/has/is prefixes.
 * Supports property validation and configuration.
 *
 *  <code>
 *   // Configuration example:
 *   $this->configure([
 *       'allowed_properties' => ['id', 'username', 'email', 'cost'],
 *       'validation' => [
 *           'id' => fn($value) => (int)$value,
 *           'email' => function($value) {
 *               if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
 *                   throw new \InvalidArgumentException("Invalid email");
 *               }
 *               return $value;
 *           }
 *       ]
 *   ]);
 *
 *   // Usage example:
 *   $this->setUsername('john_doe')
 *        ->setEmail('john@example.com')
 *        ->setId(123);
 *
 *   $email = $this->getEmail();
 *
 *   if ($this->hasEmail()) {
 *       $isForRich = $this->isCost('>', '3000')
 *   }
 *
 *  </code>
 *
 * @see Condition for isProperty
 * @package MB\Core\Support
 */

trait FluentTrait
{
    /**
     * @var array Property values
     */
    private array $fluentData = [];

    /**
     * @var array Property configuration
     */
    private array $fluentConfig = [];

    /**
     * Magic method for chainable calls
     *
     * @param string $method Method name
     * @param array $args Method arguments
     * @return mixed
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $args): mixed
    {
        if (str_starts_with($method, 'set') && strlen($method) > 3) {
            $property = $this->toSnakeCase(substr($method, 3));
            $this->set($property, $args[0] ?? null);
            return $this;
        }

        if (str_starts_with($method, 'get') && strlen($method) > 3) {
            $property = $this->toSnakeCase(substr($method, 3));
            return $this->get($property, $args[0] ?? null);
        }

        if (str_starts_with($method, 'has') && strlen($method) > 3) {
            $property = $this->toSnakeCase(substr($method, 3));
            return $this->has($property);
        }

        if (str_starts_with($method, 'is') && strlen($method) > 2) {
            $property = $this->toSnakeCase(substr($method,  2));
            return $this->is($property, $args[0], $args[1] ?? null);
        }

        if (method_exists($this, $method)) {
            $result = $this->$method(...$args);
            return $result === $this ? $this : $result;
        }

        throw new BadMethodCallException("Method {$method} not found in " . static::class);
    }

    /**
     * Set property value
     *
     * @param string $property Property name
     * @param mixed $value Property value
     * @return static
     */
    public function set(string $property, mixed $value): static
    {
        $this->validateProperty($property);

        if ($this->hasValidation($property)) {
            $value = $this->validateValue($property, $value);
        }

        $this->fluentData[$property] = $value;
        return $this;
    }

    /**
     * Get property value
     *
     * @param string $property Property name
     * @param mixed $default Default value if property doesn't exist
     * @return mixed
     */
    public function get(string $property, mixed $default = null): mixed
    {
        $this->validateProperty($property);
        return $this->fluentData[$property] ?? $default;
    }

    /**
     * Check if property exists
     *
     * @param string $property Property name
     * @return bool
     */
    public function has(string $property): bool
    {
        return array_key_exists($property, $this->fluentData);
    }

    /**
     * Check property value by condition
     *
     * @param string $property
     * @param $operator
     * @param $value
     * @return bool
     */
    public function is(string $property, $operator, $value): bool
    {
        if ($this->has($property)) {
            return Condition::create($property, $operator, $value)->calculate()->result();
        }

        return false;
    }

    /**
     * Remove property
     *
     * @param string $property Property name
     * @return static
     */
    public function remove(string $property): static
    {
        unset($this->fluentData[$property]);
        return $this;
    }

    /**
     * Bulk set properties from array
     *
     * @param array $data Associative array of properties and values
     * @return static
     */
    public function fill(array $data): static
    {
        foreach ($data as $property => $value) {
            $this->set($property, $value);
        }
        return $this;
    }

    /**
     * Get all properties as array
     *
     * @return array
     */
    public function all(): array
    {
        return $this->fluentData;
    }

    /**
     * Clear all properties
     *
     * @return static
     */
    public function clear(): static
    {
        $this->fluentData = [];
        return $this;
    }

    /**
     * Configure properties behavior
     *
     * @param array $config Configuration array
     * @return static
     */
    public function configure(array $config): static
    {
        $this->fluentConfig = array_merge($this->fluentConfig, $config);
        return $this;
    }

    /**
     * Validate if property is allowed
     *
     * @param string $property Property name
     * @throws \InvalidArgumentException
     */
    protected function validateProperty(string $property): void
    {
        $allowedProperties = $this->fluentConfig['allowed_properties'] ?? [];

        if (!empty($allowedProperties) && !in_array($property, $allowedProperties)) {
            throw new \InvalidArgumentException("Property {$property} is not allowed");
        }
    }

    /**
     * Check if property has validation rules
     *
     * @param string $property Property name
     * @return bool
     */
    protected function hasValidation(string $property): bool
    {
        return isset($this->fluentConfig['validation'][$property]);
    }

    /**
     * Validate property value according to validation rules
     *
     * @param string $property Property name
     * @param mixed $value Property value to validate
     * @return mixed Validated value
     * @throws InvalidArgumentException If validation fails
     */
    protected function validateValue(string $property, mixed $value): mixed
    {
        $validation = $this->fluentConfig['validation'][$property] ?? null;

        if ($validation === null) {
            return $value;
        }

        if (!is_callable($validation)) {
            throw new \RuntimeException(
                sprintf('Validation rule for property "%s" must be callable', $property)
            );
        }

        try {
            $result = $validation($value);

            if ($result === false) {
                throw new \InvalidArgumentException(
                    sprintf('Value "%s" is invalid for property "%s"',
                        $value,
                        $property)
                );
            }

            if ($result === true) {
                return $value;
            }

            return $result;

        } catch (\InvalidArgumentException $e) {
            // Пробрасываем исключения валидации как есть
            throw $e;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(
                sprintf('Validation error for property "%s": %s', $property, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Convert CamelCase to snake_case
     *
     * @param string $input Input string
     * @return string
     */
    protected function toSnakeCase(string $input): string
    {
        return Str::snake($input);
    }

    /**
     * Convert snake_case to CamelCase
     *
     * @param string $input Input string
     * @return string
     */
    protected function toCamelCase(string $input): string
    {
        return Str::camel($input);
    }

    /**
     * Magic method for property access (get)
     *
     * @param string $property Property name
     * @return mixed
     */
    public function __get(string $property): mixed
    {
        return $this->get($this->toSnakeCase($property));
    }

    /**
     * Magic method for property access (set)
     *
     * @param string $property Property name
     * @param mixed $value Property value
     */
    public function __set(string $property, mixed $value): void
    {
        $this->set($this->toSnakeCase($property), $value);
    }

    /**
     * Magic method for property existence check
     *
     * @param string $property Property name
     * @return bool
     */
    public function __isset(string $property): bool
    {
        return $this->has($this->toSnakeCase($property));
    }

    /**
     * Magic method for property removal
     *
     * @param string $property Property name
     */
    public function __unset(string $property): void
    {
        $this->remove($this->toSnakeCase($property));
    }
}