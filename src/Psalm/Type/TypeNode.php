<?php
namespace Psalm\Type;

interface TypeNode
{
    /**
     * @return array<TypeNode>
     */
    public function getChildNodes() : array;

    public function hasTemplate(): bool;
}
