<?php namespace OrbitShop\API\v1\Helper;
/**
 * Get list of files and directories recursively.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Exception;

class RecursiveFileIterator
{
    protected $directory = NULL;
    protected $sort = 'sort-ascending';
    protected $includeDirName = TRUE;
    protected $includeFullPath = FALSE;
    protected $callbackMatcher = NULL;
    protected $language = [];

    /**
     * Construct
     *
     * @param string $directory - Directory name
     * @param string $sort - Sorting mode: 'sort-ascending' or 'sort-descending'
     * @param array $language - Language list for localization
     * @return void
     * @throws exception
     */
    public function __construct($directory, $sort='sort-ascending', $language=array())
    {
        $this->setDirectory($directory);
        $this->setLanguage($language);
        $this->setSorting($sort);
    }

    /**
     * Static method to instantiate the class.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $directory - Directory name
     * @param string $sort - Sorting mode: 'sort-ascending' or 'sort-descending'
     * @param array $language - Language list for localization
     * @return void
     * @throws exception
     */
    public static function create($directory, $sort='sort-ascending')
    {
        return new static($directory, $sort);
    }

    /**
     * A recursive call to traverse into all directories.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $dir - The Fullpath of directory which processed
     * @param string $currentDir - The current directory name without fullpath
     *                             used for prefix.
     * @return array
     */
    public function get($dir='', $currentDir='')
    {
        // Sort argument for scandir()
        // FALSE = Ascending
        // TRUE = Ascending
        $scanSort = $this->sort === 'sort-ascending' ? FALSE : TRUE;

        // Hold the result
        $result = array();

        // Should be the first time if it was empty
        if ($dir === '') {
            $dir = $this->directory;
        }

        foreach (scandir($dir, $scanSort) as $file) {
            // Do not proceed a dot and double dot
            if ($file === '.' || $file === '..') {
                continue;
            }

            $_file = $dir . DIRECTORY_SEPARATOR . $file;

            // If the current file is a directory, traverse into it
            if (is_dir($_file)) {
                $result = array_merge($result, $this->get($_file, $file . DIRECTORY_SEPARATOR));
            } else {
                // Call the callback matcher, the callback should return boolean
                $callback = $this->callbackMatcher;
                if (is_callable($callback)) {
                    if (! $callback($file, $_file)) {
                        continue;
                    }
                }

                // If this is a file append to our result variable
                if ($this->includeFullPath) {
                    $result[] = $_file;
                } else {
                    $_directoryPrefix = $this->includeDirName ? $currentDir : '';
                    $result[] = $_directoryPrefix . $file;
                }
            }
        }

        return $result;
    }

    /**
     * Set the value of directory property.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $directory - Directory name
     * @return RecursiveFileIterator
     */
    public function setDirectory($directory)
    {
        if (! file_exists($directory)) {
            $message = sprintf($this->language['directory_not_found'], $directory);
            throw new Exception ($message);
        }
        $this->directory = $directory;

        return $this;
    }

    /**
     * Method to include directory name on the result (Prefix).
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return RecursiveFileIterator
     */
    public function includeDirectoryName()
    {
        $this->includeDirName = TRUE;

        return $this;
    }

    /**
     * Method to include the fullpath of the file.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return RecursiveFileIterator
     */
    public function includeFullPath()
    {
        $this->includeFullPath = TRUE;

        return $this;
    }

    /**
     * Set the callback to be used to compare the files based on criteria which
     * specify by the callback.
     *
     * The callback should accept 2 arguments and return boolean.
     *   1. The file name
     *   2. The fullpath of the file name
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param callback $callback
     * @return RecursiveFileIterator
     */
    public function setCallbackMatcher($callback)
    {
        $this->callbackMatcher = $callback;

        return $this;
    }

    /**
     * Set the language used for localization.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $lang Array of sentences
     * @return RecursiveFileIterator
     */
    public function setLanguage(array $lang)
    {
        $this->language = $lang + $this->defaultLangList();

        return $this;
    }

    /**
     * Set the value of sorting.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $sort - Sorting mode: 'sort-ascending' or 'sort-descending'
     * @return RecursiveFileIterator
     */
    public function setSorting($sort)
    {
        $valid = array('sort-ascending', 'sort-descending');
        if (! in_array($sort, $valid)) {
            throw new Exception($this->language['wrong_sorting_mode']);
        }
        $this->sort = $sort;

        return $this;
    }

    /**
     * Return the default configuration for the language.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     */
    protected function defaultLangList()
    {
        return array(
            'directory_not_found'   => 'Directory %s is not found.',
            'not_a_directory'       => '%s seems not a directory.',
            'wrong_sorting_mode'    => 'Invalid sorting mode.'
        );
    }

    /**
     * Magic call to get the property.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        if (property_exists($this, $key)) {
            return $this->$key;
        }

        return NULL;
    }
}
