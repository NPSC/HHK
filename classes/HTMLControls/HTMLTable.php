<?php
namespace HHK\HTMLControls;

/**
 * HTMLTable.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Class HTMLTable
 */
class HTMLTable extends AbstractHTMLControl {

    private $bodyTR;

    private $headerTR = '';

    private $footerTR = '';

    public function __construct($bodyTR = '') {
        $this->bodyTR = $bodyTR;
    }

    public function generateMarkup(array $attributes = array(), $caption = '') {

        $trHeader = "";
        if (($this->headerTR != '')) {
            $trHeader .= '<thead>' . $this->headerTR . '</thead>';
        }

        $trFooter = "";
        if (($this->footerTR != '')) {
            $trFooter .= '<tfoot>' . $this->footerTR . '</tfoot>';
        }

        if ($caption != '') {
            $caption = '<caption>' . $caption . '</caption>';
        }


        return '<table' . self::getAttrMarkup($attributes) . '>' . $caption . $trHeader . '<tbody>' . $this->bodyTR . '</tbody>' . $trFooter . '</table>';
    }

    public static function generateDirectMarkup($bodyTR, array $attributes = array()) {

        return '<table' . self::getAttrMarkup($attributes) . '><tbody>' . $bodyTR . '</tbody></table>';
    }

    public function addHeader($contents) {
        $this->headerTR .= $contents;
        return $this;
    }
    public function addHeaderTr($contents, array $attr = array()) {
        $this->headerTR .= HTMLContainer::generateMarkup('tr', $contents, $attr);
        return $this;
    }

    public function addFooter($contents) {
        $this->footerTR .= $contents;
        return $this;
    }

    public function addFooterTr($contents, array $attr = array()) {
        $this->footerTR .= HTMLContainer::generateMarkup('tr', $contents, $attr);
        return $this;
    }

    public function addBodyTr($contents, array $attr = array()) {
        $this->bodyTR .= HTMLContainer::generateMarkup('tr', $contents, $attr);
        return $this;
    }

    public function addBody($contents) {
        $this->bodyTR .= $contents;
        return $this;
    }

    public function prependBodyTr($contents) {
        $this->bodyTR = HTMLContainer::generateMarkup('tr', $contents) . $this->bodyTR;
        return $this;
    }

    public static function makeTd($contents = '', array $attr = array()) {
        return HTMLContainer::generateMarkup('td', $contents, $attr);
    }

    public static function makeTh($contents = '', array $attr = array()) {
        return HTMLContainer::generateMarkup('th', $contents, $attr);
    }

}

?>