<?php
namespace HHK\HTMLControls;

/**
 * HTMLInput.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class HTMLInput extends AbstractHTMLControl {

    public static function generateMarkup($contents, array $attributes) {

        if ($contents != '') {
            $attributes['value'] = $contents;
        }
        if (!isset($attributes['type'])) {
            $attributes['type'] = 'text';
        }
        return '<input' . self::getAttrMarkup($attributes) . '/>';
    }
}