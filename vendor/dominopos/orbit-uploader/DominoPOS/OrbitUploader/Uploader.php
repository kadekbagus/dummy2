<?php namespace DominoPOS\OrbitUploader;
/**
 * Library for dealing with file upload.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use \DominoPOS\OrbitUploader\UploaderConfig;
use \DominoPOS\OrbitUploader\UploaderMessage;
use \Exception;
use \finfo;
use \Eventviva\ImageResize;

class Uploader
{
    /**
     * Hold the UploaderConfig object
     *
     * @var UploaderConfig
     */
    protected $config = NULL;

    /**
     * Hold the UploaderMessage object
     *
     * @var UploaderMessage
     */
    protected $message = NULL;

    /**
     * Increment start number, used to increment the name of file so it does not
     * override the old files. This should be used when the file name is
     * not unique.
     *
     * @var int
     */
    public $incrementNumberStart = 1;

    /**
     * Flag to determine running in dry run mode.
     *
     * @var boolean
     */
    public $dryRun = FALSE;

    /**
     * List of static error codes
     */
    const ERR_UNKNOWN = 31;
    const ERR_NO_FILE = 32;
    const ERR_SIZE_LIMIT = 33;
    const ERR_FILE_EXTENSION = 34;
    const ERR_FILE_MIME = 35;
    const ERR_NOWRITE_ACCESS = 36;
    const ERR_MOVING_UPLOAD_FILE = 37;

    /**
     * Class constructor for passing the Uploader config and UploaderMessage
     * to the class
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function __construct(\DominoPOS\OrbitUploader\UploaderConfig $config,
                                \DominoPOS\OrbitUploader\UploaderMessage $message)
    {
        $this->config = $config;
        $this->message = $message;
    }

    /**
     * Static method to instantiate the object.
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public static function create(\DominoPOS\OrbitUploader\UploaderConfig $config,
                                  \DominoPOS\OrbitUploader\UploaderMessage $message)
    {
        return new static($config, $message);
    }

    /**
     * Main logic to upload the file to the server.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $files The actual $_FILES
     * @param string $name The upload file name
     * @return array
     * @throws Exception
     */
    public function upload($files)
    {
        if (! isset($files)) {
            throw new Exception(
                $this->message->getMessage('errors.no_file_uploaded'),
                static::ERR_NO_FILE
            );
        }

        $files = static::simplifyFilesVar($files);
        $result = array();

        foreach ($files as $i=>$file) {
            // Check for basic PHP upload error
            switch ($file->error) {
                case UPLOAD_ERR_OK;
                    break;

                case UPLOAD_ERR_NO_FILE:
                    throw new Exception(
                        $this->message->getMessage('errors.no_file_uploaded'),
                        static::ERR_NO_FILE
                    );
                    break;

                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $units = static::bytesToUnits($this->config->getConfig('file_size'));
                    $message = $this->message('errors.file_too_big', array(
                        'size' => $units['newsize'],
                        'unit' => $units['unit']
                    ));
                    throw new Exception($message, static::ERR_SIZE_LIMIT);

                default:
                    throw new Exception(
                        $this->message->getMessage('errors.unknown_error'),
                        static::ERR_UNKNOWN
                    );
            }

            $result[$i] = array();
            $result[$i]['orig'] = $file;
            $result[$i]['new'] = $file;

            // Check the actual size of the file
            $maxAllowedSize = $this->config->getConfig('file_size');
            if ($file->size > $maxAllowedSize) {
                $units = static::bytesToUnits($maxAllowedSize);
                $message = $this->message->getMessage('errors.file_too_big', array(
                    'size' => $units['newsize'],
                    'unit' => $units['unit']
                ));

                throw new Exception($message, static::ERR_SIZE_LIMIT);
            }
            $result[$i]['file_size'] = $file->size;

            // Check for allowed file extension
            $allowedExtensions = $this->config->getConfig('file_type');
            $allowedExtensionsLower = array_map('strtolower', $allowedExtensions);
            $ext = strtolower(substr(strrchr($file->name, '.'), 1));
            if (! in_array($ext, $allowedExtensionsLower)) {
                throw new Exception(
                    $this->message->getMessage('errors.file_type_not_allowed', array(
                        'extension' => '.' . $ext
                    )),
                    static::ERR_FILE_EXTENSION
                );
            }
            $result[$i]['file_ext'] = $ext;

            // Check for allowed mime-type
            $allowedMime = $this->config->getConfig('mime_type');
            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $mime = $finfo->file($file->tmp_name);
            if (! in_array($mime, $allowedMime)) {
                throw new Exception(
                    $this->message->getMessage('errors.mime_type_not_allowed', array(
                        'mime' => $mime
                    )),
                    static::ERR_FILE_MIME
                );
            }
            $result[$i]['mime_type'] = $mime;

            // Check if the target directory is writeable
            $targetDir = $this->config->getConfig('path');

            // Are we need to create the directory?
            if ($this->config->getConfig('create_directory') === TRUE) {
                if (! file_exists($targetDir)) {
                    // Try to create the directory
                    if (! @mkdir($targetDir, 0777, TRUE)) {
                        throw new Exception(
                            $this->message->getMessage('errors.no_write_access'),
                            static::ERR_NOWRITE_ACCESS
                        );
                    }
                } else {
                    // Only check the original upload path value
                    if (! is_writable($targetDir)) {
                        throw new Exception(
                            $this->message->getMessage('errors.no_write_access'),
                            static::ERR_NOWRITE_ACCESS
                        );
                    }
                }
            }

            // Append with year and month when necesscary
            if ($this->config->getConfig('append_year_month') === TRUE) {
                $yearMonth = date('Y/m');
                $targetDir = $targetDir . '/' . $yearMonth;

                if (! file_exists($targetDir))
                {
                    if (! @mkdir($targetDir, 0777, TRUE)) {
                        throw new Exception(
                            $this->message->getMessage('errors.no_write_access'),
                            static::ERR_NOWRITE_ACCESS
                        );
                    }
                }
            }

            // Call the 'before_saving' callback
            $before_saving = $this->config->getConfig('before_saving');
            if (is_callable($before_saving)) {
                $before_saving($this, $result[$i], $targetDir);
            }
            $newFileName = $result[$i]['new']->name;

            // Suffix increment to prevent writing the same name for multiple
            // file uploades
            $suffixIncrement = '_' . ($i + $this->incrementNumberStart);

            // Apply suffix to the file name
            $suffix = $this->config->getConfig('suffix');

            // If suffix is a callback then run it
            $fileNameOnly = pathinfo($newFileName, PATHINFO_FILENAME);
            if (is_callable($suffix)) {
                $suffix = $suffix($this, $file, $newFileName);
            }
            $newFileName = $fileNameOnly . $suffix . $suffixIncrement . '.' . $ext;
            $result[$i]['file_name'] = $newFileName;

            $targetFileName = $targetDir . DS . $newFileName;

            // Do not upload when we are in dry run mode
            if ($this->dryRun === FALSE) {
                if (! static::moveUploadedFile($file->tmp_name, $targetFileName)) {
                    throw new Exception(
                        $this->message->getMessage('unable_to_upload'),
                        static::ERR_MOVING_UPLOAD_FILE
                    );
                }
            }

            $result[$i]['path'] = $targetFileName;
            $result[$i]['realpath' ] = realpath($targetFileName);
            $result[$i]['resized'] = array();
            $result[$i]['cropped'] = array();
            $result[$i]['scaled'] = array();

            // Do some post processing to the uploaded file if the type are image
            if (strpos($mime, 'image') !== FALSE) {
                $resizer = new ImageResize($result[$i]['realpath']);

                // Image need to be resized?
                if ($this->config->getConfig('resize_image') === TRUE) {
                    // Loop through each profiles
                    foreach ($this->config->getConfig('resize') as $profile=>$config) {
                        $width = $config['width'];
                        $height = $config['height'];

                        // Keep the aspect ratio?
                        if ($this->config->getConfig('keep_aspect_ratio') === TRUE) {
                            // Just resize based on width and let resizer determine
                            // the height automatically
                            $resizer->resizeToWidth($width);
                        } else {
                            // Resize the image as we want it
                            $resizer->resize($width, $height);
                        }

                        $resizedSuffix = $this->config->getResizedImageSuffix($profile);
                        $resizedName = $fileNameOnly . $suffix . '-' . $resizedSuffix . $suffixIncrement . '.' . $ext;
                        $targetResizedName = $targetDir . DS . $resizedName;

                        $resizer->save($targetResizedName);

                        $result[$i]['resized'][$profile] = array(
                            'file_name' => $resizedName,
                            'file_size' => filesize($targetResizedName),
                            'path'      => $targetResizedName,
                            'realpath'  => realpath($targetResizedName)
                        );
                    }
                }

                // Image need to be cropped?
                if ($this->config->getConfig('crop_image') === TRUE) {
                    // Loop through each profiles
                    foreach ($this->config->getConfig('crop') as $profile=>$config) {
                        $width = $config['width'];
                        $height = $config['height'];

                        // Crop the image
                        $resizer->resize($width, $height);

                        $croppedSuffix = $this->config->getCroppedImageSuffix($profile);
                        $croppedName = $fileNameOnly . $suffix . '-' . $croppedSuffix . $suffixIncrement . '.' . $ext;
                        $targetCroppedName = $targetDir . DS . $croppedName;

                        $resizer->save($targetCroppedName);

                        $result[$i]['cropped'][$profile] = array(
                            'file_name' => $croppedName,
                            'file_size' => filesize($targetCroppedName),
                            'path'      => $targetCroppedName,
                            'realpath'  => realpath($targetCroppedName)
                        );
                    }
                }

                // Image need to be scacled?
                if ($this->config->getConfig('scale_image') === TRUE) {
                    // Loop through each profiles
                    foreach ($this->config->getConfig('scale') as $profile=>$scale) {
                        // Crop the image
                        $resizer->scale($scale);

                        $scaledSuffix = $this->config->getScaledImageSuffix($profile);
                        $scaledName = $fileNameOnly . $suffix . '-' . $scaledSuffix . $suffixIncrement . '.' . $ext;
                        $targetScaledName = $targetDir . DS . $scaledName;

                        $resizer->save($targetScaledName);

                        $result[$i]['scaled'][$profile] = array(
                            'file_name' => $scaledName,
                            'file_size' => filesize($targetScaledName),
                            'path'      => $targetScaledName,
                            'realpath'  => realpath($targetScaledName)
                        );
                    }
                }
            }

            // Call the callback 'after_saving'
            $after_saving = $this->config->getConfig('after_saving');
            if (is_callable($after_saving))
            {
                $after_saving($this, $result);
            }
        }

        return $result;
    }

    /**
     * Return the instance of UploaderConfig object.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return UploaderConfig
     */
    public function getUploaderConfig()
    {
        return $this->config;
    }

    /**
     * Return the instance of UploaderMessage object.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return UploaderMessage
     */
    public function getUploaderMessage()
    {
        return $this->message;
    }

    /**
     * Restructure the original $_FILES upload variable into more friendly
     * access. This method does not modify the original $_FILES. As an example
     * the end result would be something like this:
     *
     * Array
     * (
     *     [0] => stdClass Object
     *         (
     *             [name] => foo.txt
     *             [type] => text/plain
     *             [tmp_name] => /tmp/xyz
     *             [error] => 0
     *             [size] => 1234
     *         )
     *
     *     [1] => stdClass Object
     *         (
     *             [name] => bar.jpg
     *             [type] => image/jpeg
     *             [tmp_name] => /tmp/abc
     *             [error] =>
     *             [size] => 2345
     *         )
     *
     * )
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @credit http://php.net/manual/en/features.file-upload.multiple.php#53240
     * @param array $files Should be $_FILES
     * @param string $name Name of the element
     * @return array
     */
    public static function simplifyFilesVar($files)
    {
        $newVar = array();

        // Get all the keys, like 'name', 'tmp_name', 'error', 'size'
        $keys = array_keys($files);

        // Turn it into array if it was single file upload
        if (! is_array($files['name'])) {
            foreach ($keys as $key) {
                $files[$key] = (array)$files[$key];
            }
        }

        // How many files being uploaded
        $count = count($files['name']);

        for ($i=0; $i<$count; $i++) {
            $object = new \stdClass();

            foreach ($keys as $key) {
                $object->$key = $files[$key][$i];
            }

            $newVar[$i] = $object;
        }

        return $newVar;
    }

    /**
     * A wrapper around native move_uploaded_file(), so it become more testable
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $from - Source path
     * @param string $to - Destination path
     * @return boolean
     */
    protected function moveUploadedFile($from, $to)
    {
        return move_uploaded_file($from, $to);
    }

    /**
     * Method to convert the size from bytes to more human readable units. As
     * an example:
     *
     * Input 356 produces => array('unit' => 'bytes', 'newsize' => 356)
     * Input 2045 produces => array('unit' => 'kB', 'newsize' => 2.045)
     * Input 1055000 produces => array('unit' => 'MB', 'newsize' => 1.055)
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param int $size - The size in bytes
     * @return array
     */
    public static function bytesToUnits($size)
    {
       $kb = 1000;
       $mb = $kb * 1000;
       $gb = $mb * 1000;

       if ($size > $gb) {
            return array('unit' => 'GB', 'newsize' => $size / $gb);
       }

       if ($size > $mb) {
            return array('unit' => 'MB', 'newsize' => $size / $mb);
       }

       if ($size > $kb) {
            return array('unit' => 'kB', 'newsize' => $size / $kb);
       }

       return array('unit' => 'bytes', 'newsize' => 1);
    }
}
