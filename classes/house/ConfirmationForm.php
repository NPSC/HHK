<?php

/**
 * ConfirmationForm.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ConfirmationForm
 *
 * @author Eric
 */
class ConfirmationForm extends TemplateForm {


    public function makeReplacements(Reservation_1 $reserv, Guest $guest, $amount, $notes) {

        $uS = Session::getInstance();
        $labels = new Config_Lite(LABEL_FILE);
        $visitFeeNotice = "";

        //populate visitFeeNotice
        if ($reserv->getExpectedDays($reserv->getExpectedArrival(), $reserv->getExpectedDeparture()) > $uS->VisitFeeDelayDays || $uS->VisitFeeDelayDays == 0) {
            if ($reserv->getVisitFee() > 0) {
                $visitFeeNotice = $labels->getString('referral', 'VisitFeeConfirmLabel', '') . " $" . number_format($reserv->getVisitFee(), 2) . ".";
            }
        }

        return array(
            'GuestName' => $guest->getRoleMember()->get_fullName(),
            'ExpectedArrival' => date('M j, Y', strtotime($reserv->getExpectedArrival())),
            'ExpectedDeparture' => date('M j, Y', strtotime($reserv->getExpectedDeparture())),
            'DateToday' => date('M j, Y'),
            'Nites' => $reserv->getExpectedDays($reserv->getExpectedArrival(), $reserv->getExpectedDeparture()),
            'Amount' => ($amount == '' ? 0 : number_format($amount, 2)),
            'Notes' => '',  //$notes,
            'VisitFeeNotice' => $visitFeeNotice,
        );
    }

    public static function createNotes($text, $editable) {

        $notesText = '';

        if ($editable) {
            //$notesText .= HTMLContainer::generateMarkup('p', HTMLContainer::generateMarkup('span', "Special Note", array('style' => 'font-weight:bold;')));
            $notesText .= HTMLContainer::generateMarkup('textarea', '', array('id' => 'tbCfmNotes', 'name' => 'tbCfmNotes', 'placeholder'=>'Special Note', 'rows' => '3', 'cols' => '80'));
        } else if (strlen($text) > 5) {
            $notesText .= HTMLContainer::generateMarkup('p', HTMLContainer::generateMarkup('span', "Special Note", array('style' => 'font-weight:bold;')) . "<br/>" . nl2br($text));
            $notesText .= '<br />';
        }

        return $notesText;
    }

}
