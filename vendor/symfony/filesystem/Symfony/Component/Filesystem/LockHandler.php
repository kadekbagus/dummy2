<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem;

use Symfony\Component\Filesystem\Exception\IOException;

/**
 * LockHandler class provides a simple abstraction to lock anything by means of
 * a file lock.
 *
 * A locked file is created based on the lock name when calling lock(). Other
 * lock handlers will not be able to lock the same name until it is released
 * (explicitly by calling release() or implicitly when the instance holding the
 * lock is destroyed).
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Romain Neutron <imprec@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class LockHandler
{
    private $file;
    private $handle;

    /**
     * @param  string      $name     The lock name
     * @param  string|null $lockPath The directory to store the lock. Default values will use temporary directory
     * @throws IOException If the lock directory could not be created or is not writable
     */
    public function __construct($name, $lockPath = null)
    {
        $lockPath = $lockPath ?: sys_get_temp_dir();

        if (!is_dir($lockPath)) {
            $fs = new Filesystem();
            $fs->mkdir($lockPath);
        }

        if (!is_writable($lockPath)) {
            throw new IOException(sprintf('The directory "%s" is not writable.', $lockPath), 0, null, $lockPath);
        }

        $name = sprintf('%s-%s', preg_replace('/[^a-z0-9\._-]+/i', '-', $name), hash('sha256', $name));

        $this->file = sprintf('%s/%s', $lockPath, $name);
    }

    /**
     * Lock the resource
     *
     * @param  bool        $blocking wait until the lock is released
     * @return bool        Returns true if the lock was acquired, false otherwise
     * @throws IOException If the lock file could not be created or opened
     */
    public function lock($blocking = false)
    {
        if ($this->handle) {
            return true;
        }

        // Set an error handler to not trigger the registered error handler if
        // the file can not be opened.
        set_error_handler('var_dump', 0);
        $this->handle = @fopen($this->file, 'c');
        restore_error_handler();

        if (!$this->handle) {
            throw new IOException(sprintf('Unable to fopen "%s".', $this->file), 0, null, $this->file);
        }

        // On Windows, even if PHP doc says the contrary, LOCK_NB works, see
        // https://bugs.php.net/54129
        if (!flock($this->handle, LOCK_EX | ($blocking ? 0 : LOCK_NB))) {
            fclose($this->handle);
            $this->handle = null;

            return false;
        }

        return true;
    }

    /**
     * Release the resource
     */
    public function release()
    {
        if ($this->handle) {
            flock($this->handle, LOCK_UN | LOCK_NB);
            fclose($this->handle);
            $this->handle = null;
        }
    }
}
