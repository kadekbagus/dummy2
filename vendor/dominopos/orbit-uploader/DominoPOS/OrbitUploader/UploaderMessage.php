<?php namespace DominoPOS\OrbitUploader;
/**
 * Class for storing errors or success message of the OrbitUploader library.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class UploaderMessage
{
    /**
     * List of messages
     *
     * @var array
     */
    protected $messages = array();

    /**
     * List of default messages
     *
     * @var array
     */
    protected $default = array();

    /**
     * Class constructor for booting up the messages
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $messages
     */
    public function __construct(array $messages)
    {
        $this->default = array(
            'errors' => array(
                'unknown_error'         => 'Unknown upload error.',
                'no_file_uploaded'      => 'No file being uploaded.',
                'path_not_found'        => 'Unable to find the upload directory.',
                'no_write_access'       => 'Unable to write to the upload directory.',
                'file_too_big'          => 'Picture size is too big, maximum size allowed is :size :unit.',
                'file_type_not_allowed' => 'File extension ":extension" is not allowed.',
                'mime_type_not_allowed' => 'File with mime type of ":mime" is not allowed.',
                'dimension_not_allowed' => 'Maximum dimension allowed is :maxdimension, your image dimension is :yoursdimension',
                'unable_to_upload'      => 'Unable to move the uploaded file.'
            ),
            'success' => array(
                'upload_ok'             => 'File has been uploaded successfully.'
            ),
        );
        $this->messages = $messages + $this->default;
    }

    /**
     * Static method to instantiate the object.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $messages
     * @return UploaderMessage
     */
    public static function create(array $messages)
    {
        return new static($messages);
    }

    /**
     * Get the message based on "." dotted key.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $key The configuraiton name
     * @param array $replace List of variabel name which would be subtituted.
     * @return string
     */
    public function getMessage($key, $replace=array())
    {
        $message = UploaderConfig::getConfigVal($key, $this->messages);
        if (is_null($message)) {
            return NULL;
        }

        // Subtitute the variabel name of the message
        if (! empty($replace)) {
            foreach ($replace as $varname=>$value) {
                $message = str_replace(':' . $varname, $value, $message);
            }
        }

        return $message;
    }
}
