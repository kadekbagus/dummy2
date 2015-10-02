<?php

/**
 * Custom Blueprint subclass with extra methods for the more uncommon field types.
 */
class OrbitBlueprint extends \Illuminate\Database\Schema\Blueprint
{
    public function encodedId($column)
    {
        return $this->addColumn('char', $column, ['length' => 16, 'character_set' => 'ascii', 'collation' => 'ascii_bin']);
    }
}
