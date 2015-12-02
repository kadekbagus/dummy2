<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;

class OrbitMySqlSchemaGrammar extends \Illuminate\Database\Schema\Grammars\MySqlGrammar
{

    /**
     * Sets an instance of myself as the schema grammar for a connection,
     * with that connection's table prefix.
     *
     * @param \Illuminate\Database\Connection $conn
     * @return void
     */
    public static function useFor(\Illuminate\Database\Connection $conn)
    {
        $me = new OrbitMySqlSchemaGrammar();
        $conn->setSchemaGrammar($conn->withTablePrefix($me));
    }

    /**
     * Constructs and adds some modifiers handled here.
     */
    function __construct()
    {
        // put "CharacterSet" and "Collation" before "After"

        $added = false;
        $modifiers = $this->modifiers;
        $this->modifiers = [];
        foreach ($modifiers as $mod) {
            if ($mod === 'After') {
                $added = true;
                // must be added in this order
                $this->modifiers[] = "CharacterSet";
                $this->modifiers[] = "Collation";
            }
            $this->modifiers[] = $mod;
        }
        if (!$added) {
            $this->modifiers[] = "CharacterSet";
            $this->modifiers[] = "Collation";
        }
    }

    /**
     * Returns DB type for binary string of a certain length.
     * @param Fluent $column
     * @return string
     */
    protected function typeBinaryString(Fluent $column)
    {
        return "binary({$column->length})";
    }

    /**
     * Get the SQL for specifiying a column's character set.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $column
     * @return string|null
     */
    protected function modifyCharacterSet(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, ['char', 'varchar', 'text', 'enum', 'set']) && $column->characterSet) {
            return " CHARACTER SET {$column->characterSet} ";
        }
    }

    /**
     * Get the SQL for specifiying a column's character set.
     *
     * @param  \Illuminate\Database\Schema\Blueprint $blueprint
     * @param  \Illuminate\Support\Fluent $column
     * @return string|null
     */
    protected function modifyCollation(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, ['char', 'varchar', 'text', 'enum', 'set']) && $column->collation) {
            return " COLLATE {$column->collation} ";
        }
    }
}
