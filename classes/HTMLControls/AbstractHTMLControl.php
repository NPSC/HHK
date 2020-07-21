<?php
namespace HHK\HTMLControls;

/**
 * AbstractHTMLControl.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


abstract class AbstractHTMLControl {

    protected static function getAttrMarkup(array $attr) {
        $attributes = '';

        if (isset($attr['name']) && !isset($attr['id'])) {
            $attr['id'] = str_replace('[]', '', $attr['name']);
        }

        foreach ($attr as $k => $v) {
            $attributes .= ' ' . $k . '="' .$v . '"';
        }

        return $attributes;
    }
}

?>