<?php

namespace Psalm\Type\Atomic;

use Psalm\Type\Atomic;

use function addcslashes;
use function get_class;
use function mb_strlen;
use function mb_substr;

/**
 * Denotes a string whose value is known.
 */
class TLiteralString extends TString
{
    /** @var string */
    public $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getKey(bool $include_extra = true): string
    {
        return 'string(' . $this->value . ')';
    }

    public function getId(bool $exact = true, bool $nested = false): string
    {
        if (!$exact) {
            return 'string';
        }
        // quote control characters, backslashes and double quote
        $no_newline_value = addcslashes($this->value, "\0..\37\\\"");
        if (mb_strlen($this->value) > 80) {
            return "'" . mb_substr($no_newline_value, 0, 80) . '...' . "'";
        }

        return "'" . $no_newline_value . "'";
    }

    public function getAssertionString(): string
    {
        return 'string(' . $this->value . ')';
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
        return $use_phpdoc_format ? 'string' : "'" . $this->value . "'";
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
