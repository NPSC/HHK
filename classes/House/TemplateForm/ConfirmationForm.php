<?php

namespace HHK\House\TemplateForm;

use HHK\HTMLControls\HTMLContainer;
use HHK\House\Reservation\Reservation_1;
use HHK\Member\Role\Guest;

use HHK\sec\Labels;
use HHK\sec\Session;

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
class ConfirmationForm extends AbstractTemplateForm {


    public function makeReplacements(Reservation_1 $reserv, Guest $guest, $amount, $notes) {

		$uS = Session::getInstance();

		$labels = Labels::getLabels();

		$visitFeeNotice = "";

		//populate visitFeeNotice
		if($reserv->getExpectedDays($reserv->getExpectedArrival(), $reserv->getExpectedDeparture()) > $uS->VisitFeeDelayDays || $uS->VisitFeeDelayDays == 0){
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
            'Notes' => $notes,
            'VisitFeeNotice' => $visitFeeNotice,
            'ImgPath' => $uS->resourceURL . 'conf/img/',
        );

    }

    public static function createNotes($text, $editable) {

        $notesText = '';

        if ($editable) {
            $notesText .= HTMLContainer::generateMarkup('p', HTMLContainer::generateMarkup('span', "Special Note", array('style'=>'font-weight:bold;')));
            $notesText .= HTMLContainer::generateMarkup('textarea', '', array('id'=>'tbCfmNotes', 'name'=>'tbCfmNotes', 'rows'=>'3', 'cols'=>'80'));
        } else if (strlen($text) > 5) {
            $notesText .= HTMLContainer::generateMarkup('p', HTMLContainer::generateMarkup('span', "Special Note", array('style'=>'font-weight:bold;')) . "<br/>" . nl2br($text));
            $notesText .= '<br />';
        }

        return $notesText;
    }

}
