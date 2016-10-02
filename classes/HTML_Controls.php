<?php
/**
 * HTML_Controls.php
 *
 * @package Hospitality HouseKeeper
 * @category  Site
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */


abstract class HTMLControl {

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


class HTMLContainer extends HTMLControl {

    public static function generateMarkup($tag, $contents, array $attributes = array()) {
        return '<'. $tag . HTMLControl::getAttrMarkup($attributes) . '>' . $contents . '</'. $tag . '>';
    }
}

class HTMLInput extends HTMLControl {

    public static function generateMarkup($contents, array $attributes) {

        if ($contents != '') {
            $attributes['value'] = $contents;
        }
        if (!isset($attributes['type'])) {
            $attributes['type'] = 'text';
        }
        return '<input' . HTMLControl::getAttrMarkup($attributes) . '/>';
    }
}

/**
 * Class HTMLTable
 */
class HTMLTable extends HTMLControl {

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


        return '<table' . HTMLControl::getAttrMarkup($attributes) . '>' . $caption . $trHeader . '<tbody>' . $this->bodyTR . '</tbody>' . $trFooter . '</table>';
    }

    public static function generateDirectMarkup($bodyTR, array $attributes = array()) {

        return '<table' . HTMLControl::getAttrMarkup($attributes) . '><tbody>' . $bodyTR . '</tbody></table>';
    }

    public function addHeader($contents) {
        $this->headerTR .= $contents;
    }
    public function addHeaderTr($contents, array $attr = array()) {
        $this->headerTR .= HTMLContainer::generateMarkup('tr', $contents, $attr);
    }

    public function addFooter($contents) {
        $this->footerTR .= $contents;
    }

    public function addFooterTr($contents, array $attr = array()) {
        $this->footerTR .= HTMLContainer::generateMarkup('tr', $contents, $attr);
    }

    public function addBodyTr($contents, array $attr = array()) {
        $this->bodyTR .= HTMLContainer::generateMarkup('tr', $contents, $attr);
    }

    public function addBody($contents) {
        $this->bodyTR .= $contents;
    }

    public function prependBodyTr($contents) {
        $this->bodyTR = HTMLContainer::generateMarkup('tr', $contents) . $this->bodyTR;
    }

    public static function makeTd($contents, array $attr = array()) {
        return HTMLContainer::generateMarkup('td', $contents, $attr);
    }

    public static function makeTh($contents, array $attr = array()) {
        return HTMLContainer::generateMarkup('th', $contents, $attr);
    }

}


/**
 * HTML Selector Control Class includes static methods to load options
 *
 */
class HTMLSelector extends HTMLControl {

    /**
     *
     * @param string $contents
     * @param array $attributes
     * @return type
     */
    public static function generateMarkup($contents, array $attributes = array()) {

        return '<select' . HTMLControl::getAttrMarkup($attributes) . '>' . $contents . '</select>';
    }


    /**
     * getLookups -
     *
     * @param PDO $dbh
     * @param string $query - select must list the Id first and the text second.
     * @param mixed $selected - selected indexes if any
     * @return string
     */
    public static function getLookups(PDO $dbh, $query, $selected = '', $offerBlank = FALSE) {

        $stmt = $dbh->prepare($query);
        $stmt->setFetchMode(PDO::FETCH_NUM);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);

        $opt = '';
        $selectors = array();
        $optionSelected = FALSE;

        if (is_array($selected)) {
            $selectors = $selected;
        } else {
            if ($selected != '') {
                $selectors[$selected] = 'y';
            }
        }

        if ($offerBlank) {

            if ($optionSelected) {
                $opt .= HTMLContainer::generateMarkup('option', '', array('selected'=>'selected'));
            } else {
                $opt .= HTMLContainer::generateMarkup('option', '');
            }
        }

        foreach ($rows as $r) {

            $attrs = array('value'=>$r[0]);

            if (isset($selectors[$r[0]])) {
                $attrs['selected'] = 'selected';
                $optionSelected = TRUE;
            } else {
                unset($attrs['selected']);
            }

            $opt .= HTMLContainer::generateMarkup('option', $r[1], $attrs);
        }


        return $opt;
    }



    /**
     *
     * @param array $gArray - 0 = index, 1 = description, 2 = option group name.
     * @param string $sel - Selected value (if any)
     * @param bool $offerBlank - Offer a blank option
     * @return string
     */
    public static function doOptionsMkup($gArray, $sel, $offerBlank = TRUE) {

        $data = "";
        $sels = array();
        $groups = array();

        if (is_array($sel) === FALSE) {
            $sels[] = trim($sel);
        } else {
            $sels = $sel;
        }

        if ($offerBlank) {

            if (array_search('', $sels) === FALSE) {
                $data = HTMLContainer::generateMarkup('option', '', array('value'=>''));
            } else {
                $data = HTMLContainer::generateMarkup('option', '', array('selected'=>'selected', 'value'=>''));
            }
        }

        if (is_array($gArray)) {

            foreach ($gArray as $row) {

                if (isset($row[2]) === FALSE) {
                    $row[2] = '';
                }

                if (array_search($row[0], $sels) === FALSE) {
                    $groups[$row[2]] = (isset($groups[$row[2]]) ? $groups[$row[2]] : '') . HTMLContainer::generateMarkup('option', $row[1], array('value'=>$row[0]));
                } else {
                    $groups[$row[2]] = (isset($groups[$row[2]]) ? $groups[$row[2]] : '') . HTMLContainer::generateMarkup('option', $row[1], array('selected'=>'selected', 'value'=>$row[0]));
                }
            }

            // Ungrouped options go first
            if (isset($groups[''])) {
                $data .= $groups[''];
            }

            foreach ($groups as $groupName => $optMarkup) {

                if ($groupName != '') {
                    $data .= HTMLContainer::generateMarkup('optgroup', $optMarkup, array('label'=>$groupName));
                }
            }
        }

        return $data;

    }

}


