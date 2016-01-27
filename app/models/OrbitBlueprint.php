<?php

/**
 * Custom Blueprint subclass with extra methods for the more uncommon field types.
 */
class OrbitBlueprint extends \Illuminate\Database\Schema\Blueprint
{
    public function encodedId($column, $length=16)
    {
        return $this->addColumn('char', $column, ['length' => $length, 'character_set' => 'ascii', 'collation' => 'ascii_bin']);
    }
}
