<?php
namespace Psalm\Type\Atomic;

use Psalm\Codebase;
use Psalm\Internal\MethodIdentifier;
use Psalm\Internal\Type\Comparator\GenericTypeComparator;
use Psalm\Internal\Type\Comparator\ObjectComparator;
use Psalm\Internal\Type\Comparator\TypeComparisonResult2;
use Psalm\Internal\Type\TemplateResult;
use Psalm\Type;
use Psalm\Type\Atomic;

use function array_map;
use function get_class;
use function implode;
use function strrpos;
use function substr;

/**
 * Denotes an object type where the type of the object is known e.g. `Exception`, `Throwable`, `Foo\Bar`
 */
class TNamedObject extends Atomic
{
    use HasIntersectionTrait;

    /** @var array<class-string<Atomic>, true> */
    protected const CONTAINED_BY = parent::CONTAINED_BY + [
        TObject::class => true,
    ];

    /**
     * This intentionally does not include parent's, since TObject cannot be coerced to TFalse.
     */
    protected const COERCIBLE_TO = [
        TTrue::class => true,
    ];

    /**
     * @var string
     */
    public $value;

    /**
     * @var bool
     */
    public $was_static = false;

    /**
     * Whether or not this type can represent a child of the class named in $value
     * @var bool
     */
    public $definite_class = false;

    /**
     * @param string $value the name of the object
     */
    public function __construct(string $value, bool $was_static = false, bool $definite_class = false)
    {
        if ($value[0] === '\\') {
            $value = substr($value, 1);
        }

        $this->value = $value;
        $this->was_static = $was_static;
        $this->definite_class = $definite_class;
    }

    public function __toString(): string
    {
        return $this->getKey();
    }

    public function getKey(bool $include_extra = true): string
    {
        if ($include_extra && $this->extra_types) {
            return $this->value . '&' . implode('&', $this->extra_types);
        }

        return $this->value;
    }

    public function getId(bool $nested = false): string
    {
        if ($this->extra_types) {
            return $this->value . '&' . implode(
                '&',
                array_map(
                    function ($type) {
                        return $type->getId(true);
                    },
                    $this->extra_types
                )
            );
        }

        return $this->was_static ? $this->value . '&static' : $this->value;
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
        if ($this->value === 'static') {
            return 'static';
        }

        $intersection_types = $this->getNamespacedIntersectionTypes(
            $namespace,
            $aliased_classes,
            $this_class,
            $use_phpdoc_format
        );

        return Type::getStringFromFQCLN(
            $this->value,
            $namespace,
            $aliased_classes,
            $this_class,
            true,
            $this->was_static
        ) . $intersection_types;
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
        if ($this->value === 'static') {
            return $php_major_version >= 8 ? 'static' : null;
        }

        if ($this->was_static && $this->value === $this_class) {
            return $php_major_version >= 8 ? 'static' : 'self';
        }

        $result = $this->toNamespacedString($namespace, $aliased_classes, $this_class, false);
        $intersection = strrpos($result, '&');
        if ($intersection === false || (
                ($php_major_version === 8 && $php_minor_version >= 1) ||
                ($php_major_version >= 9)
            )
        ) {
            return $result;
        }
        return substr($result, $intersection+1);
    }

    public function canBeFullyExpressedInPhp(int $php_major_version, int $php_minor_version): bool
    {
        return ($this->value !== 'static' && $this->was_static === false) || $php_major_version >= 8;
    }

    public function replaceTemplateTypesWithArgTypes(
        TemplateResult $template_result,
        ?Codebase $codebase
    ): void {
        $this->replaceIntersectionTemplateTypesWithArgTypes($template_result, $codebase);
    }

    public function getChildNodes(): array
    {
        return $this->extra_types ?? [];
    }

    /**
     * @psalm-mutation-free
     */
    protected function containedByAtomic(
        Atomic $other,
        ?Codebase $codebase
        // bool $allow_interface_equality = false,
    ): TypeComparisonResult2 {
        if (get_class($other) === TGenericObject::class || get_class($other) === TIterable::class) {
            if ($codebase === null) {
                return TypeComparisonResult2::false();
            }
            // TODO
            return TypeComparisonResult2::true(GenericTypeComparator::isContainedBy(
                $codebase,
                $this,
                $other
            ));
        }

        if (get_class($other) === self::class) {
            if ($codebase === null) {
                return TypeComparisonResult2::false();
            }
            // TODO
            return TypeComparisonResult2::true(ObjectComparator::isShallowlyContainedBy(
                $codebase,
                $this,
                $other,
                true,
                null
            ));
        }

        if ($other instanceof TString) {
            if ($codebase !== null && $codebase->classOrInterfaceExists($this->value)) {
                // if ($codebase->php_major_version >= 8
                //     && ($this->value === 'Stringable'
                //         || ($codebase->classlikes->classExists($this->value)
                //             && $codebase->classlikes->classImplements($this->value, 'Stringable'))
                //         || $codebase->classlikes->interfaceExtends($this->value, 'Stringable'))
                // ) {
                //     // TODO
                // }

                $to_string_method_id = new MethodIdentifier($this->value, '__tostring');
                if ($codebase->methods->methodExists($to_string_method_id)) {
                    $self_class = null;
                    $to_string_return_type = $codebase->methods->getMethodReturnType($to_string_method_id, $self_class);
                    // TODO come up with a better way to construct this?
                    // Named arguments aren't supported till PHP 8, so that's not an option.
                    return TypeComparisonResult2::requiresToStringCast(
                        $to_string_return_type !== null
                            && $to_string_return_type->containedBy($other, $codebase)->result
                    );
                }
            }

            return TypeComparisonResult2::false();
        }

        return parent::containedByAtomic($other, $codebase);
    }

    // public function queueClassLikesForScanning(
    //     Codebase $codebase,
    //     ?FileStorage $file_storage = null,
    //     array $phantom_classes = []
    // ): void {
    //     $fq_classlike_name_lc = strtolower($this->value);

    //     if (!isset($phantom_classes[$this->value])
    //         && !isset($phantom_classes[$fq_classlike_name_lc])
    //     ) {
    //         $codebase->scanner->queueClassLikeForScanning(
    //             $this->value,
    //             false,
    //             !$this->from_docblock,
    //             $phantom_classes
    //         );

    //         if ($file_storage) {
    //             $file_storage->referenced_classlikes[$fq_classlike_name_lc] = $this->value;
    //         }
    //     }
    // }
}
