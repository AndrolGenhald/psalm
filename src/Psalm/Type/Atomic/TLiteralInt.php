<?php

namespace Psalm\Type\Atomic;

use Psalm\Type\Atomic;

use function get_class;

/**
 * Denotes an integer value where the exact numeric value is known.
 */
final class TLiteralInt extends TInt
{
    /** @var int */
    public $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function getKey(bool $include_extra = true): string
    {
        return 'int(' . $this->value . ')';
    }

    public function getId(bool $exact = true, bool $nested = false): string
    {
        if (!$exact) {
            return 'int';
        }

        return (string) $this->value;
    }

    public function getAssertionString(): string
    {
        return 'int(' . $this->value . ')';
    }

    /**
     * @param  array<lowercase-string, string> $aliased_classes
     *
     */
    public function toNamespacedString(
        ?string $namespace,
        array $aliased_classes,
        ?string $this_class,
        bool $use_phpdoc_format
    ): string {
        return $use_phpdoc_format ? 'int' : (string) $this->value;
    }

    public function equals(Atomic $other_type, bool $ensure_source_equality): bool
    {
        if (get_class($other_type) !== static::class) {
            return false;
        }

        if (($this->from_docblock && $ensure_source_equality)
            || ($other_type->from_docblock && $ensure_source_equality)
        ) {
            return false;
        }

        return $this->value === $other_type->value;
    }
}
