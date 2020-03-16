<?php

namespace Orbit\Helper\Request\Contracts;

/**
 * Interface that provides a set of contracts that make sure
 * Request with uploaded files handled properly.
 *
 * @author Budi <budi@gotomalls.com>
 */
interface RequestWithUpload
{
    public function handleUpload();

    public function getUploadedFiles();
}
