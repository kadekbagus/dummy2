<?php namespace Orbit\Helper\Net\LinkPreview;

interface ObjectLinkPreviewInterface
{
    public function setInput(array $input);
    public function getInput();
    public function getPreviewData();
}
