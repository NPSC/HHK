<?php
/**
 * ConfirmationForm.php
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2015 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of ConfirmationForm
 *
 * @author Eric
 */
class ConfirmationForm {

    const NOTES = '<notes>';
    const GNAME = '<gname>';
    const ARRIVAL = '<arrival>';
    const DEPARTURE = '<departure>';
    const AMOUNT = '<amount>';
    const NIGHTS = '<nites>';

    public static function createForm($templateText, $gName, $ckin, $ckout, $nites, $total, $notes) {

        $text = '';

        if ($notes == 'tb') {
            $text .= HTMLContainer::generateMarkup('p', HTMLContainer::generateMarkup('span', "Special Note", array('class'=>'hhk-header')));
            $text .= HTMLContainer::generateMarkup('textarea', '', array('id'=>'tbCfmNotes', 'rows'=>'3', 'cols'=>'80'));
        } else if (strlen($notes) > 5) {
            $text .= HTMLContainer::generateMarkup('p', HTMLContainer::generateMarkup('span', "Special Note", array('class'=>'hhk-header')). "<br/>" . nl2br($notes));
            $text .= '<br />';
        }

        $m1 = str_replace(self::NOTES, $text,
                str_replace(self::AMOUNT, number_format($total, 2),
                    str_replace(self::NIGHTS, $nites,
                        str_replace(self::DEPARTURE, date('D M j, Y', strtotime($ckout)),
                            str_replace(self::ARRIVAL, date('D M j, Y', strtotime($ckin)),
                                str_replace(self::GNAME, $gName, $templateText))))));

        return $m1;
    }


    public static function getFormTemplate($fileName) {

        $path = REL_BASE_DIR . 'conf' . DS . $fileName;

        if (file_exists($path)) {

            if (($text = file_get_contents($path)) === FALSE) {
                throw new Hk_Exception_Runtime("Confirmation file template not read, path = " . $path);
            }
        } else {
            throw new Hk_Exception_Runtime("Confirmation file template does not exist, path = " . $path);
        }

        return $text;
    }
}
