<?php

/**
 * ws_resc.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
/**
 *  includes and requires
 */
require ("homeIncludes.php");

require(DB_TABLES . "visitRS.php");
require(DB_TABLES . "registrationRS.php");
require(DB_TABLES . "ReservationRS.php");
require (DB_TABLES . 'ActivityRS.php');
require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'AttributeRS.php');
require (DB_TABLES . 'PaymentsRS.php');

require CLASSES . 'AuditLog.php';
require CLASSES . 'History.php';
require (CLASSES . 'CreateMarkupFromDB.php');
require (CLASSES . 'Notes.php');
require CLASSES . 'TableLog.php';

require (PMT . 'Invoice.php');
require (PMT . 'InvoiceLine.php');
require (PMT . 'Receipt.php');
require (PMT . 'CreditToken.php');

require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');

require(HOUSE . "psg.php");
require(HOUSE . "visitViewer.php");
require(HOUSE . "VisitLog.php");
require (HOUSE . 'RoomLog.php');
require (HOUSE . 'Registration.php');
require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'Guest.php');
require (HOUSE . 'Patient.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'ResourceView.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'RoomChooser.php');
require (HOUSE . 'Attributes.php');
require (HOUSE . 'Constraint.php');
require (HOUSE . 'ActivityReport.php');
require (HOUSE . 'Reservation_1.php');
require (HOUSE . 'ReservationSvcs.php');
require (HOUSE . 'RoomReport.php');
require (HOUSE . 'VisitCharges.php');

require (CLASSES . 'HouseLog.php');
require (CLASSES . 'Purchase/Item.php');
require (CLASSES . 'Purchase/RoomRate.php');
require (CLASSES . 'FinAssistance.php');
require (CLASSES . 'US_Holidays.php');
require (CLASSES . 'Photo.php');
require (CLASSES . 'SiteConfig.php');
require (CLASSES . 'Document.php');
require (CLASSES . 'ListDocuments.php');

$wInit = new webInit(WebPageCode::Service);

/* @var $dbh PDO */
$dbh = $wInit->dbh;
$guestAdmin = SecurityComponent::is_Authorized("guestadmin");
addslashesextended($_REQUEST);
$c = "";

// Get our command
if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_STRING);
}

$uS = Session::getInstance();
$events = array();

try {

    switch ($c) {

        case 'getguestphoto':

            $guestId = intval(filter_input(INPUT_GET, 'guestId', FILTER_SANITIZE_NUMBER_INT), 10);

            $photo = new Photo();
            $photo->loadGuestPhoto($dbh, $guestId);

            header("Content-Type: " . $photo->getImageType());
            echo $photo->getImage();
            exit();

            break;

        case 'putguestphoto':

            SiteConfig::checkUploadFile('guestPhoto');

            $guestId = filter_input(INPUT_POST, 'guestId', FILTER_SANITIZE_NUMBER_INT);
            $guestPhoto = $_FILES['guestPhoto'];

            if (is_null($guestId) || $guestId === FALSE) {
                throw new Exception('GuestId missing');
            } else if (is_null($guestPhoto) || $guestPhoto === FALSE) {
                throw new Exception('guest Photo missing');
            }

            $photo = new Photo();

            $photo->saveGuestPhoto($dbh, $guestId, $guestPhoto, $uS->MemberImageSizePx, $uS->username);

            break;

        case 'deleteguestphoto':

            $guestId = intval(filter_input(INPUT_POST, 'guestId', FILTER_SANITIZE_NUMBER_INT), 10);

            if ($guestId < 1) {
                throw new Exception("GuestId missing");
            } else {
                // Delete it.
                $delete = "CALL delete_guest_photo($guestId)";
                $dbh->exec($delete);
            }

            break;
            
        case 'getDocumentList':
        
        	$guestId = intval(filter_input(INPUT_GET, 'guestId', FILTER_SANITIZE_NUMBER_INT), 10);
        	$psgId = intval(filter_input(INPUT_GET, 'psgId', FILTER_SANITIZE_NUMBER_INT), 10);
        	
        	if($guestId > 0){
	        	$events = ListDocuments::loadList($dbh, $guestId, Document::GuestLink, $_GET);
        	}else if($psgId > 0){
	        	$events = ListDocuments::loadList($dbh, $psgId, Document::PsgLink, $_GET);
        	}
        	
        	break;
        	
		case 'putdoc':
		
			SiteConfig::checkUploadFile('file');
			
			$guestId = intval(filter_input(INPUT_POST, 'guestId', FILTER_SANITIZE_NUMBER_INT), 10);
        	$psgId = intval(filter_input(INPUT_POST, 'psgId', FILTER_SANITIZE_NUMBER_INT), 10);
        	$docTitle = filter_input(INPUT_POST, 'docTitle', FILTER_SANITIZE_STRING);
        	$mimeType = filter_input(INPUT_POST, 'mimetype', FILTER_SANITIZE_STRING);
        	$doc = $_FILES['file'];
        	
        	if (is_null($guestId) || $guestId === FALSE) {
                throw new Exception('GuestId missing');
            } else if (is_null($doc) || $doc === FALSE) {
                throw new Exception('Document is missing');
            }
        	
        	$docContents = file_get_contents($doc['tmp_name']);
        	
            $document = Document::createNew($docTitle, $mimeType, $docContents, $uS->username);
            
            $document->saveNew($dbh);
            
            if($document->linkNew($dbh, $guestId, $psgId) > 0){
	            $events = ["idDoc"=> $document->getIdDocument(), "length"=>strlen($docContents)];
            }else{
	            $events = ["error" => "Unable to save document"];
            }
        	
			break;
			
		case 'getdoc':
			
			$docId = intval(filter_input(INPUT_GET, 'docId', FILTER_SANITIZE_NUMBER_INT), 10);

            $document = new Document($docId);
            $document->loadDocument($dbh);
            
            if($document->getExtension()){
	            $ending = "." . $document->getExtension();
            }else{
	            $ending = "";
            }

            header("Content-Type: " . $document->getMimeType());
            header('Content-Disposition: inline; filename="' . $document->getTitle() . $ending . '"');
            echo $document->getDoc();
            exit();
			
			break;
			
		case 'updatedoctitle':
			
			$docId = intval(filter_input(INPUT_POST, 'docId', FILTER_SANITIZE_NUMBER_INT), 10);
        	$docTitle = filter_input(INPUT_POST, 'docTitle', FILTER_SANITIZE_STRING);
        	
        	if (is_null($docId) || $docId === FALSE) {
                throw new Exception('DocId missing');
            }
        	
            $document = new Document($docId);
            
            $document->saveTitle($dbh, $docTitle);
            
	        $events = ["idDoc"=> $document->getIdDocument()];
        	
			break;
			
		case 'deletedoc':
			
			$docId = intval(filter_input(INPUT_POST, 'docId', FILTER_SANITIZE_NUMBER_INT), 10);
        	
        	if (is_null($docId) || $docId === FALSE || $docId < 1) {
                throw new Exception('DocId missing');
            }
        	
            $document = new Document($docId);
                        
            if($document->deleteDocument($dbh, $uS->username) > 0){
	            $events = ["status"=> "success", "idDoc"=> $document->getIdDocument(), "msg"=>"Document deleted successfully"];
            }else{
	            $events = ["error" => "Unable to delete document"];
            }
        	
			break;
			
		case 'undodeletedoc':
			
			$docId = intval(filter_input(INPUT_POST, 'docId', FILTER_SANITIZE_NUMBER_INT), 10);
        	
        	if (is_null($docId) || $docId === FALSE || $docId < 1) {
                throw new Exception('DocId missing');
            }
        	
            $document = new Document($docId);
                        
            if($document->undoDeleteDocument($dbh, $uS->username) > 0){
	            $events = ["status"=> "success", "idDoc"=> $document->getIdDocument(), "msg"=>"Document restored successfully"];
            }else{
	            $events = ["error" => "Unable to restore document"];
            }
        	
			break;
			
        case 'vehsch':

            if (isset($_REQUEST['letters'])) {
                require (HOUSE . 'Vehicle.php');
                $tag = filter_var($_REQUEST['letters'], FILTER_SANITIZE_STRING);
                $events = Vehicle::searchTag($dbh, $tag);
            }

            break;

        case 'actrpt':

            if (isset($_REQUEST["start"]) && $_REQUEST["start"] != '') {
                $startDate = filter_var($_REQUEST["start"], FILTER_SANITIZE_STRING);
                $startDT = setTimeZone($uS, $startDate);
            } else {
                $startDT = new DateTime();
            }

            if (isset($_REQUEST["end"]) && $_REQUEST["end"] != '') {
                $endDate = filter_var($_REQUEST["end"], FILTER_SANITIZE_STRING);
                $endDT = setTimeZone($uS, $endDate);
            } else {
                $endDT = new DateTime();
            }

            $strt = $startDT->format('Y-m-d');
            $end = $endDT->format('Y-m-d');

            $markup = '';
            if (isset($_REQUEST['visit'])) {
                $markup .= HTMLContainer::generateMarkup('div', ActivityReport::staysLog($dbh, $strt, $end), array('style' => 'float:left;'));
            }

            if (isset($_REQUEST['resv'])) {
                $markup .= HTMLContainer::generateMarkup('div', ActivityReport::reservLog($dbh, $strt, $end), array('style' => 'float:left;'));
            }

            if (isset($_REQUEST['hstay'])) {

                $idPsg = 0;
                if (isset($_REQUEST["psg"])) {
                    $idPsg = intval(filter_var($_REQUEST["psg"], FILTER_SANITIZE_NUMBER_INT), 10);
                }

                $markup .= HTMLContainer::generateMarkup('div', ActivityReport::HospStayLog($dbh, $strt, $end, $idPsg), array('style' => 'float:left;'));
            }

            if (isset($_REQUEST['fee'])) {

                $st = array();
                if (isset($_REQUEST['st'])) {
                    $st = filter_var_array($_REQUEST['st'], FILTER_SANITIZE_STRING);
                }
                $pt = array();
                if (isset($_REQUEST['pt'])) {
                    $pt = filter_var_array($_REQUEST['pt'], FILTER_SANITIZE_STRING);
                }
                $id = 0;
                if (isset($_REQUEST["id"])) {
                    $id = intval(filter_var($_REQUEST["id"], FILTER_SANITIZE_NUMBER_INT), 10);
                }

                $showDelInv = FALSE;
                if (isset($_REQUEST['sdinv'])) {
                    $showDelInv = TRUE;
                }

                $markup = HTMLContainer::generateMarkup('div', ActivityReport::feesLog($dbh, $startDT, $endDT, $st, $pt, $id, 'Payments Report', $showDelInv), array('style' => 'margin-left:5px;'));
            }

            if (isset($_REQUEST['inv'])) {


                $markup = HTMLContainer::generateMarkup('div', ActivityReport::unpaidInvoiceLog($dbh), array('style' => 'margin-left:5px;'));
            }

            if (isset($_REQUEST['direct'])) {
                $events = HTMLContainer::generateMarkup('div', $markup, array('style' => 'position:relative;top:12px;')) . HTMLContainer::generateMarkup('div', '', array('style' => 'clear:both;'));
            } else {
                $events = array('success' => HTMLContainer::generateMarkup('div', $markup, array('style' => 'position:relative;top:12px;')) . HTMLContainer::generateMarkup('div', '', array('style' => 'clear:both;')));
            }

            break;

        case 'hstay':

            $idPsg = 0;
            if (isset($_REQUEST["psg"])) {
                $idPsg = intval(filter_var($_REQUEST["psg"], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $events = ActivityReport::HospStayLog($dbh, '', '', $idPsg);

            break;

        case 'payRpt':

            $id = 0;
            if (isset($_REQUEST["id"])) {
                $id = intval(filter_var($_REQUEST["id"], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            if ($id > 0) {
                $events = HTMLContainer::generateMarkup('div', ActivityReport::feesLog($dbh, '', '', array(0 => ''), array(0 => ''), $id, 'Payment History', FALSE), array('id' => 'rptfeediv', 'class' => 'ignrSave'))
                        . '<script type= "text/javascript"> ' .
                        "$('#feesTable').dataTable({
    'dom': '<\"ignrSave\"if>rt<\"ignrSave\"lp><\"clear\">',
    'displayLength': 50,
    'lengthMenu': [[25, 50, -1], [25, 50, 'All']],
    'columnDefs': [
        {'targets': 8,
         'type': 'date',
         'render': function ( data, type, row ) {return dateRender(data, type);}
        }
    ]
});" . '// Void/Return button
$("#rptfeediv").on("click", ".hhk-voidPmt", function() {
    var btn = $(this);
    var amt = parseFloat(btn.data("amt"));
    if (btn.val() != "Saving..." && confirm("Void/Reverse this payment for $" + amt.toFixed(2).toString() + "?")) {
        btn.val("Saving...");
        sendVoidReturn(btn.attr("id"), "rv", btn.data("pid"));
    }
});

$("#rptfeediv").on("click", ".hhk-voidRefundPmt", function () {
    var btn = $(this);
    if (btn.val() != "Saving..." && confirm("Void this Return?")) {
        btn.val("Saving...");
        sendVoidReturn(btn.attr("id"), "vr", btn.data("pid"));
    }
});
$("#rptfeediv").on("click", ".hhk-undoReturnPmt", function () {
    var btn = $(this);
    var amt = parseFloat(btn.data("amt"));
    if (btn.val() != "Saving..." && confirm("Undo this Return/Refund for $" + amt.toFixed(2).toString() + "?")) {
        btn.val("Saving...");
        sendVoidReturn(btn.attr("id"), "ur", btn.data("pid"));
    }
});

$("#rptfeediv").on("click", ".hhk-deleteWaive", function () {
    var btn = $(this);
    if (btn.val() != "Deleting..." && confirm("Delete this House Waive?")) {
        btn.val("Deleting...");
        sendVoidReturn(btn.attr("id"), "d", btn.data("ilid"), btn.data("iid"));
    }
});

$("#rptfeediv").on("click", ".hhk-returnPmt", function() {
    var btn = $(this);
    var amt = parseFloat(btn.data("amt"));
    if (btn.val() != "Saving..." && confirm("Return this payment for $" + amt.toFixed(2).toString() + "?")) {
        btn.val("Saving...");
        sendVoidReturn(btn.attr("id"), "r", btn.data("pid"), amt);
    }
});
$("#rptfeediv").on("click", ".invAction", function (event) {
    invoiceAction($(this).data("iid"), "view", event.target.id);
});
$("#rptfeediv").on("click", ".pmtRecpt", function () {
    reprintReceipt($(this).data("pid"), "#pmtRcpt");
});
$(document).mousedown(function (event) {
    var target = $(event.target);
    if ($("div#pudiv").length > 0 && target[0].id !== "pudiv" && target.parents("#" + "pudiv").length === 0) {
        $("div#pudiv").remove();
    }
});
</script>';
            } else {
                $events = '';
            }
            break;

        case "getResc":

            $id = 0;
            if (isset($_REQUEST["id"])) {
                $id = intval(filter_var($_REQUEST["id"], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            $type = '';
            if (isset($_REQUEST["tp"])) {
                $type = filter_var($_REQUEST["tp"], FILTER_SANITIZE_STRING);
            }

            if ($type == 'resc') {

                $hospList = array();
                if (isset($uS->guestLookups[GL_TableNames::Hospital])) {
                    $hospList = $uS->guestLookups[GL_TableNames::Hospital];
                }

                $events = ResourceView::resourceDialog($dbh, $id, $uS->guestLookups[GL_TableNames::RescType], $hospList);
            } else if ($type == 'room') {

                $roomRates = array();
                if (isset($uS->guestLookups['Static_Room_Rate'])) {
                    $roomRates = $uS->guestLookups['Static_Room_Rate'];
                }

                $reportCategories = readGenLookupsPDO($dbh, 'Room_Rpt_Cat');


                $events = ResourceView::roomDialog($dbh, $id, $uS->guestLookups[GL_TableNames::RoomType], $uS->guestLookups[GL_TableNames::RoomCategory], $reportCategories, $roomRates, $uS->guestLookups[GL_TableNames::KeyDepositCode], $uS->KeyDeposit);
            } else if ($type == 'rs') {
                // constraint
                $constraints = new Constraints($dbh);
                $events = $constraints->editMarkup($dbh, $id);
            }

            break;

        case 'getStatEvent':

            $id = 0;
            if (isset($_REQUEST["id"])) {
                $id = intval(filter_var($_REQUEST["id"], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            $type = '';
            if (isset($_REQUEST["tp"])) {
                $type = filter_var($_REQUEST["tp"], FILTER_SANITIZE_STRING);
            }
            $title = '';
            if (isset($_REQUEST["title"])) {
                $title = filter_var($_REQUEST["title"], FILTER_SANITIZE_STRING);
            }

            $events = ResourceView::getStatusEvents($dbh, $id, $type, $title, $uS->guestLookups[GL_TableNames::RescStatus], readGenLookupsPDO($dbh, 'OOS_Codes'));

            break;

        case 'saveStatEvent':

            $id = 0;
            if (isset($_REQUEST["id"])) {
                $id = intval(filter_var($_REQUEST["id"], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            $type = '';
            if (isset($_REQUEST["tp"])) {
                $type = filter_var($_REQUEST["tp"], FILTER_SANITIZE_STRING);
            }

            $events = ResourceView::saveStatusEvents($dbh, $id, $type, $_POST);

            break;

        case 'redit':

            $id = 0;
            if (isset($_REQUEST["id"])) {
                $id = intval(filter_var($_REQUEST["id"], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            $type = '';
            if (isset($_REQUEST["tp"])) {
                $type = filter_var($_REQUEST["tp"], FILTER_SANITIZE_STRING);
            }

            if ($type == 'rs') {
                // constraint
                $constraints = new Constraints($dbh);
                $events = $constraints->saveMarkup($dbh, $id, $_POST['parm'], $uS->username);
            } else {

                $events = ResourceView::saveResc_Room($dbh, $id, $type, $_POST['parm'], $uS->username, $uS->ShrRm, $uS->KeyDeposit);
            }

            break;

        case 'rdel':

            $id = 0;
            if (isset($_REQUEST["id"])) {
                $id = intval(filter_var($_REQUEST["id"], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            $type = '';
            if (isset($_REQUEST["tp"])) {
                $type = filter_var($_REQUEST["tp"], FILTER_SANITIZE_STRING);
            }

            if ($type == 'rs') {

                $constraints = new Constraints($dbh);
                $events = $constraints->delete($dbh, $id);
            } else {

                $events = ResourceView::deleteResc_Room($dbh, $id, $type, $uS->username);
            }

            break;

        case 'getHist':

            $tbl = '';
            if (isset($_REQUEST['tbl'])) {
                $tbl = filter_var($_REQUEST['tbl'], FILTER_SANITIZE_STRING);
            }

            $history = new History();

            switch ($tbl) {
                case 'curres':
                    $events['curres'] = History::getCheckedInGuestMarkup($dbh, "GuestEdit.php", TRUE);
                    break;

                case 'daily':
                    $events['daily'] = RoomReport::dailyReport($dbh);
                    break;

                case 'reservs':
                    $events['reservs'] = $history->getReservedGuestsMarkup($dbh, ReservationStatus::Committed, TRUE);
                    break;

                case 'unreserv':
                    $events['unreserv'] = $history->getReservedGuestsMarkup($dbh, ReservationStatus::UnCommitted, TRUE);
                    break;

                case 'waitlist':
                    $events['waitlist'] = $history->getReservedGuestsMarkup($dbh, ReservationStatus::Waitlist, TRUE);
                    break;
            }

            break;

        case 'invAct':

            $id = 0;
            if (isset($_REQUEST["iid"])) {
                $id = intval(filter_var($_REQUEST["iid"], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            $type = '';
            if (isset($_REQUEST["action"])) {
                $type = filter_var($_REQUEST["action"], FILTER_SANITIZE_STRING);
            }

            $x = 0;
            if (isset($_POST['x'])) {
                $x = filter_var($_POST['x'], FILTER_SANITIZE_STRING);
            }

            $showBillTo = FALSE;
            if (isset($_REQUEST["sbt"])) {
                $showBillTo = filter_var($_REQUEST["sbt"], FILTER_VALIDATE_BOOLEAN);
            }

            $events = invoiceAction($dbh, $id, $type, $x, $showBillTo);
            break;

        case 'invSetBill':

            $invNum = '';
            if (isset($_POST['inb'])) {
                $invNum = filter_var($_POST['inb'], FILTER_SANITIZE_STRING);
            }

            $element = '';
            if (isset($_POST['ele'])) {
                $element = filter_var($_POST['ele'], FILTER_SANITIZE_STRING);
            }

            $notesElement = '';
            if (isset($_POST['ntele'])) {
                $notesElement = filter_var($_POST['ntele'], FILTER_SANITIZE_STRING);
            }

            $invDateStr = '';
            if (isset($_POST["date"])) {
                $invDateStr = filter_var($_POST["date"], FILTER_SANITIZE_STRING);
            }

            $invNotes = '';
            if (isset($_POST["nts"])) {
                $invNotes = filter_var($_POST["nts"], FILTER_SANITIZE_STRING);
            }

            $events = invoiceSetBill($dbh, $invNum, $invDateStr, $uS->username, $element, $invNotes, $notesElement);
            break;

        case 'saveRmCleanCode':

            $id = 0;
            if (isset($_REQUEST["idr"])) {
                $id = intval(filter_var($_REQUEST["idr"], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            $stat = '';
            if (isset($_REQUEST["stat"])) {
                $stat = filter_var($_REQUEST["stat"], FILTER_SANITIZE_STRING);
            }

            $room = new Room($dbh, $id);

            if ($room->setCleanStatus($stat) === FALSE) {
                $events['msg'] = 'Room Cleaning State change FAILED.';
            } else {
                $events['msg'] = 'Room Cleaning state changed';
            }

            $room->saveRoom($dbh, $uS->username);

            $events['curres'] = 'y';

            break;

        case 'cleanStat':

            $tbl = '';
            if (isset($_REQUEST['tbl'])) {
                $tbl = filter_var($_REQUEST['tbl'], FILTER_SANITIZE_STRING);
            }

	//start Date
            $startDate = '';
            if (isset($_REQUEST['stdte'])) {
                $startDate = filter_var($_REQUEST['stdte'], FILTER_SANITIZE_STRING);
            }

		//end Date
            $endDate = '';
            if (isset($_REQUEST['enddte'])) {
                $endDate = filter_var($_REQUEST['enddte'], FILTER_SANITIZE_STRING);
            }

            switch ($tbl) {
                case 'roomTable':
                    $events['roomTable'] = ResourceView::roomsClean($dbh, '', $guestAdmin);
                    break;

                case 'dirtyTable':
                    $events['dirtyTable'] = ResourceView::roomsClean($dbh, RoomState::Dirty, $guestAdmin);
                    break;

                case 'outTable':
                    $events['outTable'] = ResourceView::showCoList($dbh, $startDate, $endDate);
                    break;
                    
                case 'inTable':
                    $events['inTable'] = ResourceView::showCiList($dbh, $startDate, $endDate);
                    break;
            }

            break;

        case "clnlog" :

            $idRoom = 0;
            if (isset($_REQUEST["rid"])) {
                $idRoom = filter_var(urldecode($_REQUEST["rid"]), FILTER_VALIDATE_INT);
            }

            $events = ResourceView::CleanLog($dbh, $idRoom, $_POST);
            break;

        default:
            $events = array("error" => "Bad Command: \"" . $c . "\"");
    }

} catch (PDOException $ex) {
    $events = array("error" => "Database Error: " . $ex->getMessage());
} catch (Exception $ex) {
    $events = array("error" => "Programming Error: " . $ex->getMessage());
}



if (is_array($events)) {
    echo (json_encode($events));
} else {
    echo $events;
}

exit();

function invoiceSetBill(\PDO $dbh, $invNum, $invDateStr, $user, $element, $notes, $notesElement) {

    if ($invNum == '') {
        return array('error' => 'Empty Invoice Number.');
    }

    if ($invDateStr != '') {

        try {
            $billDT = setTimeZone(NULL, $invDateStr);
        } catch (Exception $ex) {
            return array('error' => 'Bad Date:  ' . $ex->getMessage());
        }
    } else {
        $billDT = NULL;
    }

    $invoice = new Invoice($dbh, $invNum);

    $wrked = $invoice->setBillDate($dbh, $billDT, $user, $notes);


    if ($wrked) {
        return array('success' => 'Invoice number ' . $invNum . ' updated.',
            'elemt' => $element,
            'strDate' => (is_null($billDT) ? '' : $billDT->format('M j, Y')),
            'notes' => $invoice->getNotes(),
            'notesElemt' => $notesElement
        );
    }

    return array('error' => 'Set invoice billing date Failed.');
}

function invoiceAction(\PDO $dbh, $iid, $action, $eid, $showBillTo = FALSE) {

    if ($iid < 1) {
        return array('error' => 'Bad Invoice Id');
    }

    $uS = Session::getInstance();
    $mkup = '';

    if ($action == 'view') {

        // Return listing of lines

        $stmt = $dbh->query("SELECT
    i.idInvoice,
    i.`Invoice_Number`,
    i.`Balance`,
    i.`Amount`,
    i.Deleted,
    i.Sold_To_Id,
    n.Name_Full,
    n.Company,
    ng.Name_Full AS `GuestName`,
    v.idVisit,
    v.Span,
    il.Description,
    il.Amount as `LineAmount`,
    il.Deleted as `Item_Deleted`
FROM
    `invoice` i
        LEFT JOIN
    name n ON i.Sold_To_Id = n.idName
        LEFT JOIN
    visit v ON i.Order_Number = v.idVisit
        AND i.Suborder_Number = v.Span
        LEFT JOIN
    name ng ON v.idPrimaryGuest = ng.idName
    left join invoice_line il on i.idInvoice = il.Invoice_Id
WHERE
    i.idInvoice = $iid");

        $tbl = new HTMLTable();
        $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);


        foreach ($lines as $l) {

            if ($l['Item_Deleted'] == 0) {

                $tbl->addBodyTr(
                        HTMLTable::makeTd($l['Description'], array('class' => 'tdlabel'))
                        . HTMLTable::makeTd(number_format($l['LineAmount'], 2), array('style' => 'text-align:right;')));
            }
        }


        $divAttr = array('id' => 'pudiv', 'class' => 'ui-widget ui-widget-content ui-corner-all hhk-tdbox', 'style' => 'clear:both; float:left; position:absolute; min-width:300px;');
        $tblAttr = array('style' => 'background-color:lightyellow; width:100%;');

        if ($lines[0]['Deleted'] == 1) {
            $tblAttr['style'] = 'background-color:red;';
        }

        $mkup = HTMLContainer::generateMarkup('div', $tbl->generateMarkup(
                $tblAttr, 'Items For Invoice #' . $lines[0]['Invoice_Number'] . HTMLContainer::generateMarkup('span', ' (' . $lines[0]['GuestName'] . ')', array('style' => 'font-size:.8em;')))
               . ($showBillTo ? Invoice::getBillToAddress($dbh, $lines[0]['Sold_To_Id'])->generateMarkup(array(), 'Bill To') : '')
               , $divAttr);

        return array('markup' => $mkup, 'eid' => $eid);

    } else if ($action == 'vpmt') {

        // Return listing of Payments
        $divAttr = array('id' => 'pudiv', 'class' => 'ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-panel', 'style' => 'clear:both; float:left;');
        $tblAttr = array('style' => 'background-color:lightyellow;');

        $tbl = new HTMLTable();
        $mkup = HTMLContainer::generateMarkup('div', 'No Payments', $divAttr);

        $stmt = $dbh->query("Select * from vlist_inv_pments where idPayment > 0 and idInvoice = $iid");
        $invoices = Receipt::processPayments($stmt, array());

        foreach ($invoices as $r) {

            $tbl->addHeaderTr(HTMLTable::makeTh('Date') . HTMLTable::makeTh('Method') . HTMLTable::makeTh('Status') . HTMLTable::makeTh('Amount'));

            // Payments
            foreach ($r['p'] as $p) {

                $tbl->addBodyTr(
                        HTMLTable::makeTd(($p['Payment_Date'] == '' ? '' : date('M j, Y', strtotime($p['Payment_Date']))), array('class' => 'tdlabel'))
                        . HTMLTable::makeTd($p['Payment_Method_Title'], array('class' => 'tdlabel'))
                        . HTMLTable::makeTd($p['Payment_Status_Title'], array('class' => 'tdlabel'))
                        . HTMLTable::makeTd(number_format($p['Payment_Amount'], 2), array('style' => 'text-align:right;'))
                );
            }

            $mkup = HTMLContainer::generateMarkup('div', $tbl->generateMarkup($tblAttr, 'Payments For Invoice #: ' . $r['i']['Invoice_Number']), $divAttr);
        }

        return array('markup' => $mkup, 'eid' => $eid);
    } else if ($action == 'del') {

        $invoice = new Invoice($dbh);
        $invoice->loadInvoice($dbh, $iid);

        try {
            $invoice->deleteInvoice($dbh, $uS->username);
            return array('delete' => 'Invoice Number ' . $invoice->getInvoiceNumber() . ' is deleted.', 'eid' => $eid);
        } catch (Hk_Exception_Payment $ex) {
            return array('error' => $ex->getMessage());
        }
    } else if ($action == 'srch') {

        $invNum = $iid . '%';
        $stmt = $dbh->query("Select idInvoice, Invoice_Number from invoice where Invoice_Number like '$invNum'");

        $numbers = array();

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $numbers[] = array('id' => $r['idInvoice'], 'value' => $r['Invoice_Number']);
        }

        return $numbers;
    }

    return array('error' => 'Bad Invoice Action.  ');
}
