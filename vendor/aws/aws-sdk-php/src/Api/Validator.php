<?php
namespace Aws\Api;

use Aws;

/**
 * Validates a schema against a hash of input.
 */
class Validator
{
    private $path = [];
    private $errors = [];
    private $constraints = [];

    private static $defaultConstraints = [
        'required' => true,
        'min'      => true,
        'max'      => false,
        'pattern'  => false
    ];

    /**
     * @param array $constraints Associative array of constraints to enforce.
     *                           Accepts the following keys: "required", "min",
     *                           "max", and "pattern". If a key is not
     *                           provided, the constraint will assume false.
     */
    public function __construct(array $constraints = null)
    {
        static $assumedFalseValues = [
            'required' => false,
            'min'      => false,
            'max'      => false,
            'pattern'  => false
        ];
        $this->constraints = empty($constraints)
            ? self::$defaultConstraints
            : $constraints + $assumedFalseValues;
    }

    /**
     * Validates the given input against the schema.
     *
     * @param string $name  Operation name
     * @param Shape  $shape Shape to validate
     * @param array  $input Input to validate
     *
     * @throws \InvalidArgumentException if the input is invalid.
     */
    public function validate($name, Shape $shape, array $input)
    {
        $this->dispatch($shape, $input);

        if ($this->errors) {
            $message = sprintf(
                "Found %d error%s while validating the input provided for the "
                    . "%s operation:\n%s",
                count($this->errors),
                count($this->errors) > 1 ? 's' : '',
                $name,
                implode("\n", $this->errors)
            );
            $this->errors = [];

            throw new \InvalidArgumentException($message);
        }
    }

    private function dispatch(Shape $shape, $value)
    {
        static $methods = [
            'structure' => 'check_structure',
            'list'      => 'check_list',
            'map'       => 'check_map',
            'blob'      => 'check_blob',
            'boolean'   => 'check_boolean',
            'integer'   => 'check_numeric',
            'float'     => 'check_numeric',
            'long'      => 'check_numeric',
            'string'    => 'check_string',
            'byte'      => 'check_string',
            'char'      => 'check_string'
        ];

        $type = $shape->getType();
        if (isset($methods[$type])) {
            $this->{$methods[$type]}($shape, $value);
        }
    }

    private function check_structure(StructureShape $shape, $value)
    {
        if (!$this->checkAssociativeArray($value)) {
            return;
        }

        if ($this->constraints['required'] && $shape['required']) {
            foreach ($shape['required'] as $req) {
                if (!isset($value[$req])) {
                    $this->path[] = $req;
                    $this->addError('is missing and is a required parameter');
                    array_pop($this->path);
                }
            }
        }

        foreach ($value as $name => $v) {
            if ($shape->hasMember($name)) {
                $this->path[] = $name;
                $this->dispatch(
                    $shape->getMember($name),
                    isset($value[$name]) ? $value[$name] : null
                );
                array_pop($this->path);
            }
        }
    }

    private function check_list(ListShape $shape, $value)
    {
        if (!is_array($value)) {
            $this->addError('must be an array. Found '
                . Aws\describe_type($value));
            return;
        }

        $this->validateRange($shape, count($value), "list element count");

        $items = $shape->getMember();
        foreach ($value as $index => $v) {
            $this->path[] = $index;
            $this->dispatch($items, $v);
            array_pop($this->path);
        }
    }

    private function check_map(MapShape $shape, $value)
    {
        if (!$this->checkAssociativeArray($value)) {
            return;
        }

        $values = $shape->getValue();
        foreach ($value as $key => $v) {
            $this->path[] = $key;
            $this->dispatch($values, $v);
            array_pop($this->path);
        }
    }

    private function check_blob(Shape $shape, $value)
    {
        static $valid = [
            'string' => true,
            'integer' => true,
            'double' => true,
            'resource' => true
        ];

        $type = gettype($value);
        if (!isset($valid[$type])) {
            if ($type != 'object' || !method_exists($value, '__toString')) {
                $this->addError('must be an fopen resource, a '
                    . 'GuzzleHttp\Stream\StreamInterface object, or something '
                    . 'that can be cast to a string. Found '
                    . Aws\describe_type($value));
            }
        }
    }

    private function check_numeric(Shape $shape, $value)
    {
        if (!is_numeric($value)) {
            $this->addError('must be numeric. Found '
                . Aws\describe_type($value));
            return;
        }

        $this->validateRange($shape, $value, "numeric value");
    }

    private function check_boolean(Shape $shape, $value)
    {
        if (!is_bool($value)) {
            $this->addError('must be a boolean. Found '
                . Aws\describe_type($value));
        }
    }

    private function check_string(Shape $shape, $value)
    {
        if (!$this->checkCanString($value)) {
            $this->addError('must be a string or an object that implements '
                . '__toString(). Found ' . Aws\describe_type($value));
            return;
        }

        $this->validateRange($shape, strlen($value), "string length");

        if ($this->constraints['pattern']) {
            $pattern = $shape['pattern'];
            if ($pattern && !preg_match("/$pattern/", $value)) {
                $this->addError("Pattern /$pattern/ failed to match '$value'");
            }
        }
    }

    private function validateRange(Shape $shape, $length, $descriptor)
    {
        if ($this->constraints['min']) {
            $min = $shape['min'];
            if ($min && $length < $min) {
                $this->addError("expected $descriptor to be >= $min, but "
                    . "found $descriptor of $length");
            }
        }

        if ($this->constraints['max']) {
            $max = $shape['max'];
            if ($max && $length > $max) {
                $this->addError("expected $descriptor to be <= $max, but "
                    . "found $descriptor of $length");
            }
        }
    }

    private function checkCanString($value)
    {
        static $valid = [
            'string'  => true,
            'integer' => true,
            'double'  => true,
            'NULL'    => true,
        ];

        $type = gettype($value);

        return isset($valid[$type]) ||
            ($type == 'object' && method_exists($value, '__toString'));
    }

    private function checkAssociativeArray($value)
    {
        if (!is_array($value) || isset($value[0])) {
            $this->addError('must be an associative array. Found '
                . Aws\describe_type($value));
            return false;
        }

        return true;
    }

    private function addError($message)
    {
        $this->errors[] =
            implode('', array_map(function ($s) { return "[{$s}]"; }, $this->path))
            . ' '
            . $message;
    }
}
