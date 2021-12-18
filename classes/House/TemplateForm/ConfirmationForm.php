<?php

namespace HHK\House\TemplateForm;

use HHK\HTMLControls\HTMLContainer;
use HHK\House\Reservation\Reservation_1;
use HHK\Member\Role\Guest;
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\SysConst\RoomRateCategories;
use HHK\House\PSG;

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


    public function makeReplacements(\PDO $dbh, Reservation_1 $reserv, Guest $guest, $amount, $notes) {

		$uS = Session::getInstance();

		$labels = Labels::getLabels();

		$visitFeeNotice = "";

		//populate visitFeeNotice
		if($reserv->getExpectedDays($reserv->getExpectedArrival(), $reserv->getExpectedDeparture()) > $uS->VisitFeeDelayDays || $uS->VisitFeeDelayDays == 0){
                    if ($reserv->getVisitFee() > 0) {
			$visitFeeNotice = $labels->getString('referral', 'VisitFeeConfirmLabel', '') . " $" . number_format($reserv->getVisitFee(), 2) . ".";
                    }
		}

		//get Room Rate
		$priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
		$rateAdjust = $reserv->getRateAdjust();
		$idRate = $reserv->getIdRoomRate();
		$rateCat = $reserv->getRoomRateCategory();
		$rateRs = $priceModel->getCategoryRateRs($idRate);
		$pledgedRate = $reserv->getFixedRoomRate();
		$roomRateTitle = (isset($rateRs) ? $rateRs->Title->getStoredVal():'');

		if($rateCat == RoomRateCategories::Fixed_Rate_Category){
		    $roomRateAmount = number_format($pledgedRate,2);
		}else if(isset($rateRs)){
		    $roomRateAmount = number_format($rateRs->Reduced_Rate_1->getStoredVal(),2);
		}else{
		    $roomRateAmount = '';
		}

		$nightlyRate = (1 + $rateAdjust/100) * $priceModel->amountCalculator(1, $idRate, $rateCat, $pledgedRate);

		//get patient
		$idPsg = $reserv->getIdPsg($dbh);
		$psg = new PSG($dbh, $idPsg);
		$patientName = $psg->getPatientName($dbh);

        return array(
            'GuestName' => $guest->getRoleMember()->get_fullName(),
            'GuestAddr1' => $guest->getAddrObj()->get_Data()['Address_1'],
            'GuestAddr2' => $guest->getAddrObj()->get_Data()['Address_2'],
            'GuestCity' => $guest->getAddrObj()->get_Data()['City'],
            'GuestState' => $guest->getAddrObj()->get_Data()['State_Province'],
            'GuestZip' => $guest->getAddrObj()->get_Data()['Postal_Code'],
            'GuestPhone' => $guest->getPhonesObj()->get_Data()["Phone_Num"],
            'GuestEmail' => $guest->getEmailsObj()->get_Data()["Email"],
            'PatientName' =>$patientName,
            'numGuests' => $reserv->getNumberGuests($dbh),
            'Room' => $reserv->getRoomTitle($dbh),
            'ExpectedArrival' => date('M j, Y', strtotime($reserv->getExpectedArrival())),
            'ExpectedDeparture' => date('M j, Y', strtotime($reserv->getExpectedDeparture())),
            'DateToday' => date('M j, Y'),
            'Nites' => $reserv->getExpectedDays($reserv->getExpectedArrival(), $reserv->getExpectedDeparture()),
            'RoomRateTitle' =>$roomRateTitle,
            'RoomRateAmount' =>$roomRateAmount,
            'RateAdjust' =>($rateAdjust < 0 ? number_format(abs($rateAdjust),0): '0'),
            'NightlyRate' => number_format($nightlyRate,2),
            'Amount' => ($amount == '' ? 0 : number_format($amount, 2)),
            'Notes' => $notes,
            'VisitFeeNotice' => $visitFeeNotice,
            'ImgPath' => $uS->resourceURL . 'conf/img/',
        );

    }

    public static function createNotes($text, $editable, $tabIndex) {

        $notesText = '';

        if ($editable) {
            $notesText .= HTMLContainer::generateMarkup('p', HTMLContainer::generateMarkup('span', "Special Note", array('style'=>'font-weight:bold;')));
            $notesText .= HTMLContainer::generateMarkup('textarea', '', array('id'=>'tbCfmNotes'. $tabIndex, 'name'=>'tbCfmNotes'.$tabIndex, 'rows'=>'3', 'cols'=>'80'));
        } else if (strlen($text) > 5) {
            $notesText .= HTMLContainer::generateMarkup('p', HTMLContainer::generateMarkup('span', "Special Note", array('style'=>'font-weight:bold;')) . "<br/>" . nl2br($text));
            $notesText .= '<br />';
        }

        return $notesText;
    }

}
?>
