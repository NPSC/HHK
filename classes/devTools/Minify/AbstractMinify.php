<?php
namespace HHK\devTools\Minify;

abstract class AbstractMinify {

    protected $contents;

    public function __construct(string $unminifiedFilePath = ""){
        $this->contents = file_get_contents(REL_BASE_DIR . $unminifiedFilePath);
    }

}
?>