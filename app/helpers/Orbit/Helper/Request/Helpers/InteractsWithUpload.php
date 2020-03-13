<?php

namespace Orbit\Helper\Request\Helpers;

/**
 * Provide basic implementation of RequestWithUpload interface.
 *
 * @author Budi <budi@gotomalls.com>
 */
trait InteractsWithUpload
{
    /**
     * The uploaded files.
     * @var array
     */
    protected $uploads = [];

    /**
     * Handle uploaded files.
     * Intentionally does nothing, because it meant to be overridden by
     * the request class.
     */
    abstract public function handleUpload();

    /**
     * Get the list of uploaded files. Override this method in case
     * request class needs custom format for the uploaded files.
     *
     * @return array
     */
    public function getUploadedFiles()
    {
        return $this->uploads;
    }
}
