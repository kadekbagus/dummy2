<?php namespace Text\Util;
/**
 * Class for dealing with lines of text
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class LineChecker
{
    /**
     * The string or text
     *
     * @var string
     */
    protected $text;

    /**
     * Constructor
     *
     * @param string $text
     */
    public function __construct($text)
    {
        $this->text = $text;
    }

    /**
     * Static method to instantiate the class.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $text
     * @return LineChecker
     */
    public static function create($text)
    {
        return new static($text);
    }

    /**
     * Method to check every line which have to be no more than X characters.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param int $limit
     * @return array - Empty if no line exceed X characters
     *               - Would be contains [
     *                                    'line'    => INT,
     *                                    'text'    => TEXT,
     *                                    'chars'   => NUMBER_OF_CHARS
     *                                   ]
     */
    public function noMoreThan($limit)
    {
        // Lines which exceed the limit
        $exceeds = array();

        // Make sure there is no trailing spaces
        $text = trim($this->text);

        // Split each text using "\n" new line
        $lines = explode("\n", $text);

        foreach ($lines as $lineNumber=>$line) {
            $len = strlen($line);

            if ($len > $limit) {
                $exceeds[] = array(
                    'line'  => $lineNumber + 1,
                    'text'  => $line,
                    'chars' => $len
                );
            }
        }

        return $exceeds;
    }
}
