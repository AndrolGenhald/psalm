<?php
namespace Psalm\Type\Atomic;

use Psalm\Codebase;
use Psalm\Internal\Type\Comparator\TypeComparisonResult2;
use Psalm\Type\Atomic;

use function get_class;

/**
 * Denotes the `no-return`/`never-return` type for functions that never return, either throwing an exception or
 * terminating (like the builtin `exit()`).
 */
class TNever extends Atomic
{
    public function __toString(): string
    {
        return 'never';
    }

    public function getKey(bool $include_extra = true): string
    {
        return 'never';
    }

    /**
     * @param  array<lowercase-string, string> $aliased_classes
     */
    public function toPhpString(
        ?string $namespace,
        array $aliased_classes,
        ?string $this_class,
        int $php_major_version,
        int $php_minor_version
    ): ?string {
        return null;
    }

    public function canBeFullyExpressedInPhp(int $php_major_version, int $php_minor_version): bool
    {
        return false;
    }

    /**
     * @psalm-mutation-free
     */
    protected function containedByAtomic(
        Atomic $other,
        ?Codebase $codebase
    ): TypeComparisonResult2 {
        // TODO change to use CONTAINED_BY with only self?
        return TypeComparisonResult2::true(get_class($other) === self::class);
    }
}
