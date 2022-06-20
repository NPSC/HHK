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

    // Updated 6/2022 EKC: handle new form of disabled, readonly, etc

    protected static function getAttrMarkup(array $attr) {
        $attributes = '';

        if (isset($attr['name']) && !isset($attr['id'])) {
            $attr['id'] = str_replace('[]', '', $attr['name']);
        }

        foreach ($attr as $k => $v) {

            if (empty($k)) {
                continue;
            }

            $k = strtolower($k);

            switch ($k) {

                case 'disabled':
                    $attributes .= ' ' . $k;
                    break;

                case 'readonly':
                    $attributes .= ' ' . $k;
                    break;

                case 'checked':
                    $attributes .= ' ' . $k;
                    break;

                case 'required':
                    $attributes .= ' ' . $k;
                    break;

                case 'autofocus':
                    $attributes .= ' ' . $k;
                    break;

                case 'multiple':
                    $attributes .= ' ' . $k;
                    break;

                case 'formnovalidate':
                    $attributes .= ' ' . $k;
                    break;

                default:
                    $attributes .= ' ' . $k . '="' .$v . '"';
            }

        }

        return $attributes;
    }
}

?>