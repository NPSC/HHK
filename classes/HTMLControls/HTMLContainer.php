<?php
namespace HHK\HTMLControls;
/**
 * HTMLContainer.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


class HTMLContainer extends AbstractHTMLControl {

    public static function generateMarkup($tag, $contents, array $attributes = array()) {
        return '<'. $tag . self::getAttrMarkup($attributes) . '>' . $contents . '</'. $tag . '>';
    }
}

?>