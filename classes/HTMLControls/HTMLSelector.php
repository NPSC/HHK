<?php
namespace HHK\HTMLControls;

/**
 * HTMLSelector.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * HTML Selector Control Class includes static methods to load options
 *
 */
class HTMLSelector extends AbstractHTMLControl {

    /**
     *
     * @param string $contents
     * @param array $attributes
     */
    public static function generateMarkup($contents, array $attributes = array()) {

        return '<select' . self::getAttrMarkup($attributes) . '>' . $contents . '</select>';
    }


    /**
     * getLookups -
     *
     * @param \PDO $dbh
     * @param string $query - select must list the Id first and the text second.
     * @param mixed $selected - selected indexes if any
     * @return string
     */
    public static function getLookups(\PDO $dbh, $query, $selected = '', $offerBlank = FALSE) {

        $stmt = $dbh->prepare($query);
        $stmt->setFetchMode(\PDO::FETCH_NUM);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

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
            }

            $opt .= HTMLContainer::generateMarkup('option', $r[1], $attrs);
        }


        return $opt;
    }



    /**
     *
     * @param array $gArray - 0 = index, 1 = description, 2 = option group name.
     * @param array|string  $sel - Selected value (if any)
     * @param bool $offerBlank - Offer a blank option
     * @param string $placeholder - set 
     * @return string
     */
    public static function doOptionsMkup($gArray, $sel, $offerBlank = TRUE, $placeholder = "") {

        $data = "";
        $sels = array();
        $groups = array();

        if (is_array($sel) === FALSE) {
            $sels[] = trim($sel);
        } else {
            $sels = $sel;
        }

        if ($offerBlank) {

            $attrs = ["value" => ""];

            if($placeholder !== ""){
                $attrs["disabled"] = "disabled";
            }

            //if blank is selected or selection doesn't exist, select blank
            if (array_search('', $sels) !== FALSE || count($sels) == 0) {
                $attrs["selected"] = "selected";
            }

            $data = HTMLContainer::generateMarkup('option', $placeholder, $attrs);
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

    public static function removeOptionGroups($gArray)
    {
        $clean = array();
        if (is_array($gArray)) {
            foreach ($gArray as $s) {
                $clean[$s[0]] = array(
                    $s[0],
                    $s[1]
                );
            }
        }
        return $clean;
    }

}
