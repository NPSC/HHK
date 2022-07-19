<?php
use HHK\sec\WebInit;
use HHK\Config_Lite\Config_Lite;
use HHK\sec\Session;
use HHK\sec\SecurityComponent;
use HHK\Payment\PaymentSvcs;
use HHK\HTMLControls\HTMLContainer;
use HHK\Exception\RuntimeException;
use HHK\House\HouseServices;
use HHK\House\PSG;
use HHK\SysConst\VolMemberType;
use HHK\SysConst\MemBasis;
use HHK\Member\RoleMember\GuestMember;
use HHK\Member\Address\Address;
use HHK\Member\Address\Phones;
use HHK\Member\Address\Emails;
use HHK\History;
use HHK\HTMLControls\HTMLTable;
use HHK\House\Registration;
use HHK\Member\EmergencyContact\EmergencyContact;
use HHK\House\Hospital\HospitalStay;
use HHK\House\Vehicle;
use HHK\House\Hospital\Hospital;
use HHK\Purchase\FinAssistance;
use HHK\Member\Address\Addresses;
use HHK\SysConst\GLTableNames;
use HHK\HTMLControls\HTMLInput;
use HHK\SysConst\VisitStatus;
use HHK\Tables\Reservation\ReservationRS;
use HHK\Tables\EditRS;
use HHK\House\Reservation\Reservation_1;
use HHK\Purchase\RoomRate;
use HHK\SysConst\ItemPriceCode;
use HHK\SysConst\MemStatus;
use HHK\CreateMarkupFromDB;
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\SysConst\RoomRateCategories;
use HHK\House\Room\RoomChooser;
use HHK\sec\Labels;

/**
 * GuestEdit.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
require ("homeIncludes.php");

$wInit = new WebInit();

$dbh = $wInit->dbh;

// get session instance
$uS = Session::getInstance();

$labels = Labels::getLabels();

$resultMessage = "";
$alertMessage = '';
$id = 0;
$idPsg = 0;
$psg = NULL;
$uname = $uS->username;
$guestTabIndex = 0;
$guestName = '';
$psgmkup = '';
$memberData = array();
$showSearchOnly = FALSE;
$ngRss = array();

$memberFlag = SecurityComponent::is_Authorized("guestadmin");

$receiptMarkup = '';
$paymentMarkup = '';


// Hosted payment return
try {

    if (is_null($payResult = PaymentSvcs::processSiteReturn($dbh, $_REQUEST)) === FALSE) {

        $receiptMarkup = $payResult->getReceiptMarkup();

        if ($payResult->getDisplayMessage() != '') {
            $paymentMarkup = HTMLContainer::generateMarkup('p', $payResult->getDisplayMessage());
        }
    }

} catch (RuntimeException $ex) {
    $paymentMarkup = $ex->getMessage();
}



/*
 * called with get parameters id=x, load that id if feasable.
 */
if (isset($_GET["id"])) {

    $id = intval(filter_var($_GET["id"], FILTER_SANITIZE_NUMBER_INT), 10);
}

if (isset($_GET['psg'])) {

    $idPsg = intval(filter_var($_GET["psg"], FILTER_SANITIZE_NUMBER_INT), 10);

} else if (isset($_POST['hdnpsg'])) {

    $idPsg = intval(filter_var($_POST["hdnpsg"], FILTER_SANITIZE_NUMBER_INT), 10);

}


if (isset($_GET["tab"])) {

	$guestTabIndex = intval(filter_var($_GET["tab"], FILTER_SANITIZE_NUMBER_INT), 10);
}



/*
* This is the ID that the previous page instance saved for us.
*/
if (isset($_POST["hdnid"])) {

   $h_idTxt = filter_var($_POST["hdnid"], FILTER_SANITIZE_NUMBER_INT);
   $id = intval($h_idTxt, 10);

   if ($uS->guestId != $id) {
        $alertMessage = "Posted id does not match what the server remembers.";
        $id = 0;
   }
}


if ($id > 0) {

    // Check psg
    $ngRss = PSG::getNameGuests($dbh, $id);

    if (count($ngRss) == 0) {
        // Check for guest/patient category
        $stmv = $dbh->query("Select IFNULL(Vol_Code, '') as Vol_Code from name_volunteer2 where idName = $id and Vol_Category = 'Vol_Type' and Vol_Code in ('" . VolMemberType::Guest . "', '" . VolMemberType::Patient . "');");

        if ($stmv->rowCount() > 0) {

            while ($r = $stmv->fetch(\PDO::FETCH_NUM)) {

                if ($r[0] == VolMemberType::Patient) {
                }
            }

        } else {
        	$alertMessage = "This person is not a ".$labels->getString('MemberType', 'patient', 'Patient')." or ".$labels->getString('MemberType', 'guest', 'Guest') . (isset($uS->groupcodes['mm']) || $wInit->page->is_Admin() ? " " . HTMLContainer::generateMarkup('a', 'Go to Member Edit', array('href'=>'../admin/NameEdit.php?id='.$id)) : '');
            $showSearchOnly = TRUE;
        }
    }
} else {
    $showSearchOnly = TRUE;
}


if ($showSearchOnly === FALSE) {


// Get the name data.
$name = new GuestMember($dbh, MemBasis::Indivual, $id, NULL);
$name->setIdPrefix('');
$id = $name->get_idName();

$address = new Address($dbh, $name, $uS->nameLookups[GLTableNames::AddrPurpose]);
$phones = new Phones($dbh, $name, $uS->nameLookups[GLTableNames::PhonePurpose]);
$emails = new Emails($dbh, $name, $uS->nameLookups[GLTableNames::EmailPurpose]);


// Guest History - add this ID.
History::addToGuestHistoryList($dbh, $id, $uS->rolecode);


// Check that the guest is a member of the indicated PSG.
if ($idPsg > 0) {

    $foundIt = FALSE;

    foreach ($ngRss as $n) {
        if ($n->idPsg->getStoredVal() == $idPsg) {
            $foundIt = TRUE;
        }
    }

    // The psg is not attached to this guest.
    if ($foundIt === FALSE) {
        $alertMessage = $labels->getString('MemberType', 'guest', 'Guest').' is not a memeber of the PSG indicated on the URL (GET param).  ';
        $idPsg = 0;
    }

} else {

    // PSG Chooser
    if (count($ngRss) > 1) {

        // Select psg
        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh('Who is the ' . $labels->getString('MemberType', 'patient', 'Patient') . '?'));

        foreach ($ngRss as $n) {

            $gpsg = new PSG($dbh, $n->idPsg->getStoredVal());

            $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('a', $gpsg->getPatientName($dbh), array('href'=> 'GuestEdit.php?id='.$id.'&psg='.$gpsg->getIdPsg())))
                );

        }

        $psgmkup = HTMLContainer::generateMarkup('h3', 'Choose ' . $labels->getString('GuestEdit', 'psgTab', 'Patient Support Group')) .$tbl->generateMarkup();
        $guestTabIndex = 1;

    } else if (count($ngRss) == 1) {
        $ngRs = array_pop($ngRss);
        $idPsg = $ngRs->idPsg->getStoredVal();
    }
}


$psg = new PSG($dbh, $idPsg);
$registration = new Registration($dbh, $psg->getIdPsg());
$emergContact = new EmergencyContact($dbh, $id);
$idPatient = $psg->getIdPatient();
//$hospitalStay = new HospitalStay($dbh, $idPatient, 0, true, true);


/*
 * This is the main SAVE submit button.  It checks for a change in any data field
 * and updates the database accordingly.
 */
if (isset($_POST["btnSubmit"])) {
    $msg = "";

    addslashesextended($_POST);

    try {
        // Name
        $msg .= $name->saveChanges($dbh, $_POST);
        $id = $name->get_idName();

        // Address
        $msg .= $address->savePost($dbh, $_POST, $uname);

        // Phone number
        $msg .= $phones->savePost($dbh, $_POST, $uname);

        // Email Address
        $msg .= $emails->savePost($dbh, $_POST, $uname);

        // Emergency contact
        $emergContact->save($dbh, $id, $_POST, $uname);


        // house
        if ($psg->getIdPsg() > 0) {

            $delMe = FALSE;

            // Delete member
            if (isset($_POST['delpMem'])) {

                foreach ($_POST['delpMem'] as $k => $v) {

                    $k = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT),10);

                    if ($psg->removeGuest($dbh, $k, $uname)) {
                        $msg .= 'Member removed from PSG.  ';

                        if ($k == $id) {
                            $delMe = TRUE;
                        }
                    } else {
                        $msg .= 'Member cannot be removed from PSG.  ';
                    }
                }

            } else {

                if (isset($_POST['selPrel'])) {

                    foreach ($_POST['selPrel'] as $k => $v) {
                        $k = intval(filter_var($k, FILTER_SANITIZE_NUMBER_INT),10);
                        $v = filter_var($v, FILTER_SANITIZE_STRING);
                        $psg->setNewMember($k, $v);
                    }
                }

                if (isset($_POST['cbLegalCust'])) {

                    foreach ($psg->psgMembers as $g => $v) {

                        if (isset($_POST['cbLegalCust'][$g])) {
                            $v->Legal_Custody->setNewVal(1);
                        } else {
                            $v->Legal_Custody->setNewVal(0);
                        }
                    }

                } else {

                    foreach ($psg->psgMembers as $g => $v) {
                        $v->Legal_Custody->setNewVal(0);
                    }
                }
            }

            // Notes
            $psgNotes = '';
            if (isset($_POST['txtPSGNotes'])) {
                $psgNotes = $_POST['txtPSGNotes'];
            }

            // Last info confirmed date
            if (isset($_POST['cbLastConfirmed']) && isset($_POST['txtLastConfirmed'])) {
                $lastConfirmed = filter_var($_POST['txtLastConfirmed'], FILTER_SANITIZE_STRING);
                $psg->setLastConfirmed($lastConfirmed);
            }

            $msg .= $psg->savePSG($dbh, $psg->getIdPatient(), $uname, $psgNotes);

            if ($delMe) {
                exit(
                    HTMLContainer::generateMarkup('h2', 'Deleted from PSG.  ' . HTMLContainer::generateMarkup('a', 'Continue', array('href'=>'GuestEdit.php?id='.$id)))
                );
            }

            // Registration
            $registration->extractRegistration($dbh, $_POST);
            $registration->extractVehicleFlag($_POST);
            $msg .= $registration->saveRegistrationRs($dbh, $psg->getIdPsg(), $uname);


            if ($uS->TrackAuto && $registration->getNoVehicle() == 0) {
                Vehicle::saveVehicle($dbh, $_POST, $registration->getIdRegistration());
            }


            if ($uS->IncomeRated) {
                // Financial Assistance
                $finApp = new FinAssistance($dbh, $registration->getIdRegistration());

                $faCategory = '';
                $faStat = '';
                $reason = '';
                $notes = '';
                $faStatDate = '';

                if (isset($_POST['txtFaIncome'])) {
                    $income = filter_var(str_replace(',', '', $_POST['txtFaIncome']),FILTER_SANITIZE_NUMBER_INT);
                    $finApp->setMontylyIncome($income);
                }

                if (isset($_POST['txtFaSize'])) {
                    $size = intval(filter_var($_POST['txtFaSize'],FILTER_SANITIZE_NUMBER_INT), 10);
                    $finApp->setHhSize($size);
                }

                // FA Category
                if (isset($_POST['hdnRateCat'])) {
                    $faCategory = filter_var($_POST['hdnRateCat'], FILTER_SANITIZE_STRING);
                }

                if (isset($_POST['SelFaStatus']) && $_POST['SelFaStatus'] != '') {
                    $faStat = filter_var($_POST['SelFaStatus'], FILTER_SANITIZE_STRING);
                }

                if (isset($_POST['txtFaStatusDate']) && $_POST['txtFaStatusDate'] != '') {
                    $faDT = setTimeZone($uS, filter_var($_POST['txtFaStatusDate'], FILTER_SANITIZE_STRING));
                    $faStatDate = $faDT->format('Y-m-d');
                }

                // Reason text
                if (isset($_POST['txtFaReason'])) {
                    $reason = filter_var($_POST['txtFaReason'], FILTER_SANITIZE_STRING);
                }

                // Notes
                if (isset($_POST['txtFaNotes'])) {
                    $notes = filter_var($_POST['txtFaNotes'], FILTER_SANITIZE_STRING);
                }

                // Save Fin App dialog.
                $finApp->saveDialogMarkup($dbh, $faStat, $faCategory, $reason, $faStatDate, $notes, $uS->username);
            }

        }

        // success
        $resultMessage = $msg;


    } catch (Exception $ex) {
        $resultMessage = $ex->getMessage();
    }
}

$isPatient = false;

// Heading member name text
if ($name->isNew()) {

    $niceName = "New ".$labels->getString('MemberType', 'guest', 'Guest');

} else {

    if ($psg->getIdPatient() == $name->get_idName()) {
        $niceName = $labels->getString('MemberType', 'patient', 'Patient').": " . $name->getMemberName();
        $isPatient = true;
    } else {
        $niceName = $labels->getString('MemberType', 'guest', 'Guest').": " . $name->getMemberName();
    }
}


//
// Name Edit Row
$tbl = new HTMLTable();
$tbl->addHeaderTr($name->createMarkupHdr($labels, TRUE));
$tbl->addBodyTr($name->createMarkupRow('', TRUE));

$nameMarkup = $tbl->generateMarkup();

// Demographics
$demogTab = $name->createDemographicsPanel($dbh, FALSE, FALSE, [], $isPatient);

// Excludes
$ta = $name->createExcludesPanel();

$ExcludeTab = $ta['markup'];

// Addresses
$addrPanelMkup = $address->createMarkup('', TRUE, $uS->county);
// Phone Numbers
$phoneMkup = $phones->createMarkup();
// Email addresses
$emailMkup = $emails->createMarkup();
$prefMkup = Addresses::getPreferredPanel($phones, $emails);

// Contact last updated
$contactLastUpdated = '';

if ($name->get_lastUpdated() != '') {
    $contactLastUpdated = $name->getContactLastUpdatedMU(new DateTime($name->get_lastUpdated()), 'Name');
}

// Add Emergency contact
$emergencyTabMarkup = HTMLContainer::generateMarkup('div',
        $emergContact->createMarkup($uS->guestLookups[GLTableNames::PatientRel]));


$visitList = "";
$psgOnly = FALSE;
$regTabMarkup = "";
$psgTabMarkup = "";
$vehicleTabMarkup = "";
$reservMarkup = '';



//
// Guest
//
if ($psg->getIdPsg() > 0) {

    $psgTabMarkup = $psg->createEditMarkup($dbh, $uS->guestLookups[GLTableNames::PatientRel], $labels, 'GuestEdit.php', $id, FALSE);

    $ccMarkup = '';

        $ccMarkup = HTMLcontainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Credit Cards', array('style'=>'font-weight:bold;'))
                . HouseServices::guestEditCreditTable($dbh, $registration->getIdRegistration(), $id, 'g')
                . HTMLInput::generateMarkup('Update Credit', array('type'=>'button','id'=>'btnCred', 'data-indx'=>'g', 'data-id'=>$id, 'data-idreg'=>$registration->getIdRegistration(), 'style'=>'margin:5px;float:right;'))
            ,array('id'=>'upCreditfs', 'style'=>'float:left;', 'class'=>'hhk-panel ignrSave')));


    $regTabMarkup = HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
            HTMLContainer::generateMarkup('legend', 'Registration', array('style'=>'font-weight:bold;'))
            . $registration->createRegMarkup($dbh, $memberFlag)
            , array('style'=>'float:left;', 'class'=>'hhk-panel'))) . $ccMarkup;

    if ($uS->TrackAuto) {
        $vehicleTabMarkup = Vehicle::createVehicleMarkup($dbh, $registration->getIdRegistration(), $registration->getNoVehicle());
    }

    // Look for visits

    $visitRows = array();
    if ($registration->getIdRegistration() > 0) {

        $query = "select * from vspan_listing where "
                . "(Actual_Span_Nights > 0 or `Status` = '". VisitStatus::CheckedIn . "' or DATE(Arrival_Date) = DATE(now()))"
                . " and idRegistration = " . $registration->getIdRegistration() . " order by Span_Start DESC;";
        $stmt = $dbh->query($query);
        $visitRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }


    $stays = array();
    if ($id > 0) {
        $query = "select * from vstays_listing where idName = $id order by Checkin_Date desc;";
        $stmt = $dbh->query($query);
        $stays = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }



    if (count($visitRows) > 0) {

        // preprocess visits
        for ($j = count($visitRows) - 1; $j >= 0 ; $j--) {

            if (isset($visitRows[$j - 1])) {
                $visitRows[$j]['nxtRoom'] = ' to ' . $visitRows[$j - 1]['Title'];
            } else {
                $visitRows[$j]['nxtRoom'] = '';
            }
        }


        foreach ($visitRows as $r) {

            $room = $r['Status_Title'] . ' to ' . $r['Title'];
            $stIcon = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-check', 'style'=>'float: left; margin-left:.3em;', 'title'=>$r['Status_Title']));
            $hospitalButton = '';
            $stayIcon = '';

            foreach ($stays as $s) {

                if ($s['idVisit'] == $r['idVisit'] && $s['Visit_Span'] == $r['Span']) {
                    $stayIcon = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-suitcase', 'style'=>'float: left; margin-left:.3em;', 'title'=>$labels->getString('MemberType', 'guest', 'Guest').' Stayed'));
                    break;
                }

            }

            if ($r['Status'] == VisitStatus::NewSpan) {

                // Get the next room if room was changed
                $room = $r['Status_Title'] . ' from ' . $r['Title'] . $r['nxtRoom'];
                $stIcon = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-newwin', 'style'=>'float: left; margin-left:.3em;', 'title'=>$r['Status_Title']));

            } else if ($r['Status'] == VisitStatus::ChangeRate) {

                $room = $r['Status_Title'] . " (Room: " . $r['Title'] . ")";
                $stIcon = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-tag', 'style'=>'float: left; margin-left:.3em;', 'title'=>$r['Status_Title']));

            } else if ($r['Status'] == VisitStatus::CheckedOut) {

                $room =  $r['Status_Title'] . ' from ' . $r['Title'];
                $stIcon = HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-extlink', 'style'=>'float: left; margin-left:.3em;', 'title'=>$r['Status_Title']));
            }

            // Compile header
            $hdr = HTMLContainer::generateMarkup('h3', HTMLContainer::generateMarkup('span',
                    'Visit ' .$r['idVisit'] . '-' . $r['Span'] . ': '
                    . (date('Y') == date('Y', strtotime($r['Span_Start'])) ? date('M j', strtotime($r['Span_Start'])) : date('M j, Y', strtotime($r['Span_Start'])))
                    . " to "
                    . ($r['Span_End'] == '' ? date('M j', strtotime($r['Expected_Departure'])) : date('M j', strtotime($r['Span_End'])))
            		. ".  " . $room . $stIcon . $stayIcon . $hospitalButton),
                    array('class'=>'ui-accordion-header ui-helper-reset ui-state-default ui-corner-all hhk-view-visit', 'data-vid'=>$r['idVisit'], 'data-span'=>$r['Span'], 'data-gid'=>$id, 'style'=>'min-height:20px; padding-top:5px;'));

            $visitList .= $hdr;

        }

    }

    // Reservation
    $stmt = $dbh->query("select r.*, hs.idHospital from reservation r left join hospital_stay hs on r.idHospital_Stay = hs.idHospital_stay
 where idRegistration = ". $registration->getIdRegistration() . " order by idReservation desc");
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);


    foreach ($rows as $r) {
        $reservRs = new ReservationRS();
        EditRS::loadRow($r, $reservRs);

        $reserv = new Reservation_1($reservRs);
        $rtbl = new HTMLTable();
        $rtbl->addHeaderTr(HTMLTable::makeTh('Id').HTMLTable::makeTh('Status').HTMLTable::makeTh('Arrival').HTMLTable::makeTh('Depart').HTMLTable::makeTh('Room').HTMLTable::makeTh('Rate'));

        // Get the room rate category names
        $categoryTitles = RoomRate::makeDescriptions($dbh);

        $rtbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('a', $reserv->getIdReservation(), array('href'=>'Reserve.php?rid=' . $reserv->getIdReservation())))
                . HTMLTable::makeTd($reserv->getStatusTitle($dbh, $reserv->getStatus()))
                . HTMLTable::makeTd(date('M jS, Y', strtotime($reserv->getArrival())))
                . HTMLTable::makeTd(date('M jS, Y', strtotime($reserv->getDeparture())))
                . HTMLTable::makeTd($reserv->getRoomTitle($dbh))
                . ($uS->RoomPriceModel != ItemPriceCode::None ? HTMLTable::makeTd($categoryTitles[$reserv->getIdRoomRate()]) : HTMLTable::makeTd(''))
                );

        $constraintMkup = RoomChooser::createResvConstMkup($dbh, $reserv->getIdReservation(), TRUE);
        if ($constraintMkup == '') {
            $constraintMkup = "<p style='padding:4px;'>(No Room Attributes Selected.)<p>";
        }

        $rtbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('div', $constraintMkup, array('style'=>'float:left;margin-left:10px;')), array('colspan'=>'7')));

        $hospitalButton = '';

        $hdr = HTMLContainer::generateMarkup('h3', HTMLContainer::generateMarkup('span',
                $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ': '
                . (date('Y') == date('Y', strtotime($reserv->getArrival())) ? date('M j', strtotime($reserv->getArrival())) : date('M j, Y', strtotime($reserv->getArrival())))
                . " to " .(date('Y') == date('Y', strtotime($reserv->getDeparture())) ? date('M j', strtotime($reserv->getDeparture())) : date('M j, Y', strtotime($reserv->getDeparture())))
        		. '.  ' . $reserv->getStatusIcon($dbh) . $hospitalButton
                , array('style'=>'margin-left:10px;')), array('style'=>'min-height:25px; padding-top:5px;'));

        $reservMarkup .= $hdr . HTMLContainer::generateMarkup('div', $rtbl->generateMarkup());

    }


} else {
    $psgTabMarkup = $psgmkup;
    $psgOnly = TRUE;
}

$guestPhotoMarkup = "";
if($uS->ShowGuestPhoto){
	$guestPhotoMarkup = showGuestPicture($name->get_idName(), $uS->MemberImageSizePx);
}

$guestName = "<span style='font-size:2em;'>$niceName</span>";

if ($name->getNoReturnDemog() != '') {
    $guestName = "<span style='font-size:2em;color:red;'>$niceName - No Return: " . $uS->nameLookups['NoReturnReason'][$name->getNoReturnDemog()][1] . "</span>";
} else if ($name->get_status() == MemStatus::Deceased) {
    $guestName = "<span style='font-size:2em;color:#914A4A;'>$niceName - Deceased</span>";
}


$memberData["id"] = $name->get_idName();
$memberData["memDesig"] = $name->getMemberDesignation();
$memberData['addrPref'] = ($name->get_preferredMailAddr() == '' ? '1' : $name->get_preferredMailAddr());
$memberData['phonePref'] = $name->get_preferredPhone();
$memberData['emailPref'] = $name->get_preferredEmail();
$memberData['memName'] = $name->getMemberName();
$memberData['memStatus'] = $name->get_status();
$memberData['idPsg'] = $psg->getIdPsg();
$memberData['idReg'] = $registration->getIdRegistration();
$memberData['psgOnly'] = $psgOnly;
$memberData['guestLabel'] = $labels->getString('MemberType', 'guest', 'Guest');
$memberData['visitorLabel'] = $labels->getString('MemberType', 'visitor', 'Guest');

$idReg = $registration->getIdRegistration();



} else {
    // Show just the search message.
    $guestName = "<h2 style='font-size:2em;'>Search for a " .$labels->getString('MemberType', 'guest', 'Guest'). "/" . $labels->getString('MemberType', 'patient', 'Patient') . ":</h2>";
    $idReg = 0;
}


// Guest History tab markup
$recHistory = History::getGuestHistoryMarkup($dbh);


// Currently Checked In guests
$currentCheckedIn = CreateMarkupFromDB::generateHTML_Table(History::getCheckedInGuestMarkup($dbh, 'GuestEdit.php', FALSE, TRUE, $labels->getString('MemberType', 'patient', 'Patient'), $labels->getString('hospital', 'hospital', 'Hospital')), 'curres');

$showCharges = TRUE;
$addnl = readGenLookupsPDO($dbh, 'Addnl_Charge');
$discs = readGenLookupsPDO($dbh, 'House_Discount');

// decide to show payments and invoices
if ($uS->RoomPriceModel == ItemPriceCode::None && count($addnl) == 0 && count($discs) == 0 && $uS->VisitFee === false && $uS->KeyDeposit === false) {
    $showCharges = FALSE;
}


// Save guest Id.
$uS->guestId = $id;

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title><?php echo $wInit->pageTitle; ?></title>
        <meta http-equiv="x-ua-compatible" content="IE=edge">
        <?php echo JQ_UI_CSS; ?>
        <?php echo MULTISELECT_CSS; ?>
        <?php echo HOUSE_CSS; ?>
        <?php echo JQ_DT_CSS; ?>
        <?php echo NOTY_CSS; ?>
        <?php echo INCIDENT_CSS; ?>
        <?php echo UPPLOAD_CSS; ?>
        <?php echo FAVICON; ?>
        <?php echo GRID_CSS; ?>
        <?php echo NAVBAR_CSS; ?>

        <script type="text/javascript" src="<?php echo JQ_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_UI_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MOMENT_JS ?>"></script>
        <script type="text/javascript" src="<?php echo JQ_DT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PRINT_AREA_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo STATE_COUNTRY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo CREATE_AUTO_COMPLETE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo ADDR_PREFS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo MULTISELECT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo INVOICE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo PAYMENT_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo RESV_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTES_VIEWER_JS ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo NOTY_SETTINGS_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo VISIT_DIALOG_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo DIRRTY_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo JSIGNATURE_JS; ?>"></script>
        <script type="text/javascript" src="<?php echo INCIDENT_REP_JS; ?>"></script>
        <!--  <script type="text/javascript" src="<?php echo BOOTSTRAP_JS; ?>"></script> -->

        <?php if ($uS->UseDocumentUpload || $uS->ShowGuestPhoto) {
            echo '<script type="text/javascript" src="' . UPPLOAD_JS . '"></script>';
        ?>
        	<script>
        		$(document).ready(function(){
        			window.uploader = new Upploader.Uppload({lang: Upploader.en});
        		});
        	</script>
        <?php
            echo '<script type="text/javascript" src="' . DOC_UPLOAD_JS . '"></script>';
        }

        if ($uS->PaymentGateway == AbstractPaymentGateway::INSTAMED) {echo INS_EMBED_JS;} ?>

    </head>
    <body <?php if ($wInit->testVersion) {echo "class='testbody'";} ?>>
        <?php echo $wInit->generatePageMenu(); ?>
        <div id="contentDiv">
        	<div class="hhk-flex hhk-flex-wrap">
                <div style="margin-top:5px;">
                    <?php echo $guestName; ?>
                </div>
                <div class="ui-widget ui-widget-content ui-corner-all hhk-flex hhk-flex-wrap" style="font-size:.9em;background:#EFDBC2; margin:10px; padding:5px;">
                    <div style="margin-right: 0.5em;" class="hhk-flex">
                    	<label for="txtsearch" style="min-width:fit-content">Name Search </label>
                    	<input type="search" class="allSearch" id="txtsearch" size="20" title="Enter at least 3 characters to invoke search" style="width: 100%" />
                    </div>
                    <?php if($uS->searchMRN){ ?>
                    <div style="margin-right: 0.5em;">
                    	<span>MRN Search </span>
                    	<input type="search" class="allSearch" id="txtMRNsearch" size="15" title="Enter at least 3 characters to invoke search" />
                    </div>
                    <?php } ?>
                    <div>
                    	<span>Phone Search </span>
                    	<input type="search" class="allSearch" id="txtPhsearch" size="15" title="Enter at least 5 numerals to invoke search" />
                	</div>
                </div>
            </div>
            <?php if ($alertMessage != '') { ?>
            <div class="alertContainer">
                <div id="alertMessage" style="display:inline-block; margin-top:5px;margin-bottom:5px; " class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox">
                    <?php echo $alertMessage; ?>
                </div>
            </div>
            <?php } ?>
            <?php if ($showSearchOnly === FALSE) { ?>
            <form action="GuestEdit.php" method="post" id="form1" name="form1" >
                <div id="paymentMessage" style="margin-top:5px;margin-bottom:5px; display:none; width: fit-content" class="ui-widget ui-widget-content ui-corner-all ui-state-highlight hhk-panel hhk-tdbox"></div>
                <div class="mb-2 ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-visitdialog hhk-flex hhk-widget-mobile">
	                <?php echo $guestPhotoMarkup; ?>
	                <div class="hhk-panel">
                        <?php echo $nameMarkup; ?>
                       <?php echo $contactLastUpdated; ?>
	                </div>
                </div>
                <div class="hhk-showonload hhk-tdbox" style="display:none;" >
                <div id="divNametabs" class="hhk-tdbox hhk-mobile-tabs">
                    <div class="hhk-flex ui-widget-header ui-corner-all">
                    	<div class="d-md-none d-flex"><span class="ui-icon ui-icon-triangle-1-w"></span></div>
                        <ul class="hhk-flex">
                            <li><a href="#nameTab" title="Addresses, Phone Numbers, Email Addresses">Contact Info</a></li>
                            <li><a href="#demoTab" title="<?php echo $labels->getString('MemberType', 'guest', 'Guest'); ?> Demographics">Demographics</a></li>
                            <li><a href="#exclTab" title="Exclude Addresses"><?php echo $ta['tabIcon']; ?> Exclude</a></li>
                            <?php if ($memberFlag) {  ?>
                            <li id="visitLog"><a href="#vvisitLog">Activity Log</a></li>
                            <?php } ?>
                            <li id="chglog"><a href="#vchangelog">Change Log</a></li>
                        </ul>
                        <div class="d-md-none d-flex"><span class="ui-icon ui-icon-triangle-1-e"></span></div>
                    </div>
                    <div id="demoTab"  class="ui-tabs-hide  hhk-visitdialog hhk-flex">
                        <?php echo $demogTab; ?>
                    </div>
                    <div id="vchangelog" class="ignrSave">
                      <table style="width:100%;" id="dataTbl"></table>
                    </div>
                    <div id="exclTab"  class="ui-tabs-hide  hhk-visitdialog" style="display:none;">
                        <?php echo $ExcludeTab; ?>
                    </div>
                    <?php if ($memberFlag) {  ?>
                    <div id="vvisitLog"  class="ui-tabs-hide  hhk-visitdialog ignrSave" style="display:none;">
                        <table><tr>
                            <th>Reports</th><th>Dates</th>
                        </tr><tr>
                            <td><input id='cbVisits' type='checkbox' checked="checked"/> Visits</td>
                            <td>Starting: <input type="text" id="txtactstart" class="ckdate" value="" /></td>
                        </tr><tr>
                            <td><input id='cbReserv' type='checkbox'/> Reservations</td>
                            <td>Ending: <input type="text" id="txtactend" class="ckdate" value="" /></td>
                        </tr><tr>
                            <td colspan="2"><input id='cbHospStay' type='checkbox'/> <?php echo $labels->getString('hospital', 'hospital', 'Hospital'); ?> Stays</td>

                        </tr><tr>

                            <td colspan="2" style="text-align: right;"><input type="button" id="btnActvtyGo" value="Submit"/></td>
                        </tr></table>
                        <div id="activityLog" class="hhk-visitdialog"></div>
                    </div>
                    <?php } ?>
                    <div id="nameTab"  class="ui-tabs-hide  hhk-visitdialog" style="display:none;">
                        <div class="hhk-showonload hhk-tdbox hhk-flex hhk-flex-wrap" style="display:none;" >
                            <div id="phEmlTabs" class="mr-3">
                                    <ul class="hhk-flex">
                                        <li><a href="#prefTab" title="Show only preferred phone and Email">Summary</a></li>
                                        <li><a href="#phonesTab" title="Edit the Phone Numbers and designate the preferred number">Phone</a></li>
                                        <li><a href="#emailTab" title="Edit the Email Addresses and designate the preferred address">Email</a></li>
                                    </ul>
                                    <div id="prefTab" class="ui-tabs-hide" >
                                        <?php echo $prefMkup; ?>
                                    </div>
                                    <div id="phonesTab" class="ui-tabs-hide" >
                                        <?php echo $phoneMkup; ?>
                                    </div>
                                    <div id="emailTab" class="ui-tabs-hide" >
                                        <?php echo $emailMkup; ?>
                                    </div>
                            </div>
                            <div id="addrsTabs" class="ui-tabs-hide ignrSave mr-3">
                                <?php echo $addrPanelMkup; ?>
                            </div>
                            <div id="emergTabs" class="ui-tabs-hide" >
                                <ul><li><a href="#vemerg">Emergency Contact</a></li></ul>
                                <div id="vemerg" class="ui-tabs-hide">
                                    <?php echo $emergencyTabMarkup; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
                <?php if ($id > 0) {  ?>
                <div id="psgList" class="hhk-showonload hhk-tdbox hhk-visitdialog hhk-mobile-tabs" style="display:none; margin:10px 0;">
                    <div class="hhk-flex ui-widget-header ui-corner-all">
                    	<div class="d-xl-none d-flex"><span class="ui-icon ui-icon-triangle-1-w"></span></div>
                        <ul class="hhk-flex">
                            <li><a href="#vVisits">Visits</a></li>
                            <li id="lipsg"><a href="#vpsg"><?php echo $labels->getString('guestEdit', 'psgTab', 'Patient Support Group'); ?></a></li>
                            <li><a href="#vregister">Registration/Credit</a></li>
                            <li><a href="#vreserv"><?php echo $labels->getString('guestEdit', 'reservationTab', 'Reservations'); ?></a></li>
                            <?php if ($uS->IncomeRated && $showCharges) {  ?>
                            <li id="fin"><a href="#vfin">Financial Assistance...</a></li>
                            <?php } if ($showCharges) {  ?>
                            <li id="pmtsTable"><a href="ws_resc.php?cmd=payRpt&id=<?php echo $registration->getIdRegistration(); ?>" title="Payment History">Payments</a></li>
                            <?php } ?>
                            <li><a href="ShowStatement.php?cmd=show&reg=<?php echo $idReg; ?>" title="Comprehensive Statement">Statement</a></li>
                            <?php if ($uS->TrackAuto) { ?>
                            <li><a href="#vvehicle">Vehicles</a></li>
                            <?php } ?>
                            <?php if ($uS->UseIncidentReports) { ?>
                            <li><a href="#vincidents">Incidents</a></li>
                            <?php } ?>
                            <?php if ($uS->UseDocumentUpload) { ?>
                            <li><a href="#vDocs">Documents</a></li>
                            <?php } ?>
                        </ul>
                        <div class="d-xl-none d-flex"><span class="ui-icon ui-icon-triangle-1-e"></span></div>
                    </div>
                    <div id="vpsg" class="ui-tabs-hide"  style="display:none;">
                        <div id="divPSGContainer"><?php echo $psgTabMarkup; ?></div>
                    </div>
                    <div id="vregister"  class="ui-tabs-hide" style="display:none;">
                        <div id="divRegContainer"><?php echo $regTabMarkup; ?></div>
                    </div>
                    <div id="vvehicle"  class="ui-tabs-hide" style="display:none;">
                        <div><?php echo $vehicleTabMarkup; ?></div>
                    </div>
                    <div id="vreserv"  class="ui-tabs-hide" style="display:none;">
                        <div id="resvAccordion">
                        <?php echo $reservMarkup; ?>
                        </div>
                    </div>
                    <div id="vchangelog" class="ignrSave">
                      <table style="width:100%;" id="dataTbl" ></table>
                    </div>
                    <div id="vfin"></div>
                    <div id="vVisits" class="ui-tabs-hide">
                        <div id="visitAccordion" style="min-width: max-content">
                        <?php echo $visitList; ?>
                        </div>
                    </div>
                    <?php if ($uS->UseIncidentReports) { ?>
                    <div id="vincidents" class="ui-tabs-hide" style="display: none;">
	                    <div id="vIncidentContent"></div>
                    </div>
                    <?php } ?>
                    <?php if ($uS->UseDocumentUpload) { ?>
                    <div id="vDocs" class="ui-tabs-hide" style="display: none;">
	                    <div id="vDocsContent"></div>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
                <!-- End of Tabs Control -->
                <div id="submitButtons" class="ui-corner-all" style="font-size:0.9em;">
                    <input type="submit" name="btnSubmit" value="Save" id="btnSubmit" />
                </div>
                <input type="hidden" name="hdnid" id="hdnid" value="<?php echo $id; ?>" />
                <input type='hidden' name='hdnpsg' id='hdnpsg' value='<?php echo $idPsg; ?>'/>
            </form>
            <?php } ?>
            <div id="divFuncTabs" class="hhk-mobile-tabs" style="display:none; margin-bottom: 50px;" >
                <ul class="hhk-flex">
                    <li><a href="#vckin">Current <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s</a></li>
                    <li><a href="#vhistory">Recently Viewed <?php echo $labels->getString('MemberType', 'visitor', 'Guest'); ?>s</a></li>
                </ul>
                <div id="vhistory" class="hhk-tdbox ui-tabs-hide" style="background:#EFDBC2;">
                    <?php echo $recHistory; ?>
                </div>
                <div id="vckin" class="hhk-tdbox ui-tabs-hide" style="background:#EFDBC2;">
                    <?php echo $currentCheckedIn; ?>
                </div>
            </div>
            <div id="zipSearch" class="hhk-tdbox-noborder" style="display:none;">
                <table style="width:100%;">
                    <tr>
                        <td class="tdlabel">Postal Code: </td><td><input type="text" id="txtZipSch" class="input-medium ignrSave" value="" title="Type in the postal code."/></td>
                    </tr>
                    <tr><td colspan="3" id="placeList"></td></tr>
                </table>
            </div>
            <div id="keysfees" style="font-size: .85em;"></div>
            <div id="pmtRcpt" style="font-size: .9em; display:none;"></div>
            <div id="regDialog" style="display:none;"></div>
            <div id="hsDialog" class="hhk-tdbox hhk-visitdialog hhk-hsdialog" style="display:none;font-size:.8em;"></div>
            <div id="faDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.8em;"></div>
            <div id="incidentDialog" class="hhk-tdbox hhk-visitdialog" style="display:none;font-size:.8em;"></div>
            <div id="submit" style="display:none;">
                <table>
                    <tr>
                        <td>Search: </td><td><input type="text" id="txtRelSch" size="15" value="" title="Type at least 3 letters to invoke the search."/></td>
                    </tr>
                    <tr><td><input type="hidden" id="hdnRelCode" value=""/></td><td></td></tr>
                </table>
            </div>
        </div>  <!-- div id="contentDiv"-->
        <form name="xform" id="xform" method="post"></form>
        <table id="feesTable" style="display:none;"></table>
        <script type="text/javascript">
            var memberData = <?php echo json_encode($memberData); ?>;
            var psgTabIndex = parseInt('<?php echo $guestTabIndex; ?>', 10);
            var rctMkup = '<?php echo $receiptMarkup; ?>';
            var pmtMkup = '<?php echo $paymentMarkup; ?>';
            var dateFormat = '<?php echo $labels->getString("momentFormats", "report", "MMM d, YYYY"); ?>';
            var fixedRate = '<?php echo RoomRateCategories::Fixed_Rate_Category; ?>';
            var resultMessage = '<?php echo $resultMessage; ?>';
            var showGuestPhoto = '<?php echo $uS->ShowGuestPhoto; ?>';
            var useDocUpload = '<?php echo $uS->UseDocumentUpload; ?>';
        </script>
        <script type="text/javascript" src="<?php echo GUESTLOAD_JS; ?>"></script>
    </body>
</html>
