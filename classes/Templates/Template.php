<?php
namespace HHK\Templates;

/**
 * Template.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Template Class
 *
 * Facilitate HHK->webpack templating
 *
 */
class Template {

    /**
     * @var string $head - added inside <head> tag between webpack and $headStyles
     */
    public string $head;

    /**
     * @var string $headStyles - <style> tag added to end of <head> tag
     */
    public string $headStyles;

    /**
     * @var string $inlineJS - includes a deferred inline script tag
     */
    public string $inlineJS;

    /**
     * @var string $contentDiv - main content div <div id="contentDiv"></div>
     */
    public string $contentDiv;

    /**
     * @var string $footer - Added at the end of <body>, outside $contentDiv
     */
    public string $footer;

    public function __construct(){
        $this->head = "";
        $this->contentDiv = "";
        $this->footer = "";
    }

}

?>