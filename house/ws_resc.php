<?php

use HHK\Document\Document;
use HHK\Document\FormDocument;
use HHK\Document\FormTemplate;
use HHK\Document\ListDocuments;
use HHK\History;
use HHK\House\Constraint\Constraints;
use HHK\House\Report\ActivityReport;
use HHK\House\Report\RoomReport;
use HHK\House\ResourceView;
use HHK\House\Room\Room;
use HHK\House\Vehicle;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\Member\IndivMember;
use HHK\Payment\Invoice\InvoiceActions;
use HHK\Photo;
use HHK\sec\SecurityComponent;
use HHK\sec\Session;
use HHK\sec\WebInit;
use HHK\SysConst\GLTableNames;
use HHK\SysConst\MemBasis;
use HHK\SysConst\ReservationStatus;
use HHK\SysConst\RoomState;
use HHK\SysConst\WebPageCode;
use HHK\Update\SiteConfig;



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


$wInit = new WebInit(WebPageCode::Service);

/* @var $dbh PDO */
$dbh = $wInit->dbh;
$guestAdmin = SecurityComponent::is_Authorized("guestadmin");
$c = "";

// Get our command
if (isset($_REQUEST["cmd"])) {
    $c = htmlspecialchars($_REQUEST["cmd"]);
}

$uS = Session::getInstance();


$events = [];

try {

    switch ($c) {

        case 'getguestphoto':

            $guestId = intval(filter_input(INPUT_GET, 'guestId', FILTER_SANITIZE_NUMBER_INT), 10);

            $photo = new Photo();
            $photo->loadGuestPhoto($dbh, $guestId);

            header("Content-Type: " . $photo->getImageType());
            echo $photo->getImage();
            exit();

            //break;

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
                $docTitle = filter_input(INPUT_POST, 'docTitle', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $mimeType = filter_input(INPUT_POST, 'mimetype', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
                        $events = ["idDoc"=> $document->getIdDocument(), "length"=>$doc['size']];
                }else{
                        $events = ["error" => "Unable to save document"];
                }

                break;

        case 'getdoc':

                $docId = intval(filter_input(INPUT_GET, 'docId', FILTER_SANITIZE_NUMBER_INT), 10);

                $document = new Document($docId);
                $document->loadDocument($dbh);

                if($document->getCategory() == 'form' && $document->getType() == 'json'){
                    header("location: showReferral.php?form=" . $document->getIdDocument());
                }else if ($document->getType() == "reg"){
                    header("location: ShowRegForm.php?idDoc=" . $document->getIdDocument());
                }else{
                    if($document->getExtension()){
                            $ending = "." . $document->getExtension();
                    }else{
                            $ending = "";
                    }

                    header("Content-Type: " . $document->getMimeType());
                    header('Content-Disposition: inline; filename="' . $document->getTitle() . $ending . '"');
                    echo $document->getDoc();
                    exit();
                }
                break;

        case 'updatedoc':

            $docId = intval(filter_input(INPUT_POST, 'docId', FILTER_SANITIZE_NUMBER_INT), 10);
            $docTitle = filter_input(INPUT_POST, 'docTitle', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $docGuestId = intval(filter_input(INPUT_POST, 'docGuestId', FILTER_SANITIZE_NUMBER_INT), 10);

            if (is_null($docId) || $docId === FALSE) {
                throw new Exception('DocId missing');
            }

            $document = new Document($docId);

            $document->saveTitle($dbh, $docTitle);
            $document->saveGuest($dbh, $docGuestId);

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
                //require (HOUSE . 'Vehicle.php');
                $tag = filter_var($_REQUEST['letters'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $events = Vehicle::searchTag($dbh, $tag);
            }

            break;

        case 'actrpt':

            if (isset($_REQUEST["start"]) && $_REQUEST["start"] != '') {
                $startDate = filter_var($_REQUEST["start"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $startDT = new DateTime($startDate);
            } else {
                $startDT = new DateTime();
            }

            if (isset($_REQUEST["end"]) && $_REQUEST["end"] != '') {
                $endDate = filter_var($_REQUEST["end"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $endDT = new DateTime($endDate);
            } else {
                $endDT = new DateTime();
            }

            $strt = $startDT->format('Y-m-d');
            $end = $endDT->format('Y-m-d');

            $idPsg = 0;
            if (isset($_REQUEST["psg"])) {
                $idPsg = intval(filter_var($_REQUEST["psg"], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $markup = '';
            if (isset($_REQUEST['visit'])) {
                $markup .= HTMLContainer::generateMarkup('div', ActivityReport::staysLog($dbh, $strt, $end, $idPsg), ['style' => 'float:left;']);
            }

            if (isset($_REQUEST['resv'])) {
                $markup .= HTMLContainer::generateMarkup('div', ActivityReport::reservLog($dbh, $strt, $end, 0, $idPsg), ['style' => 'float:left;']);
            }

            if (isset($_REQUEST['hstay'])) {


                $markup .= HTMLContainer::generateMarkup('div', ActivityReport::HospStayLog($dbh, $strt, $end, $idPsg), ['style' => 'float:left;']);
            }

            if (isset($_REQUEST['fee'])) {

                $st = [];
                if (isset($_REQUEST['st'])) {
                    $st = filter_var_array($_REQUEST['st'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                }
                $pt = [];
                if (isset($_REQUEST['pt'])) {
                    $pt = filter_var_array($_REQUEST['pt'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                }
                $id = 0;
                if (isset($_REQUEST["id"])) {
                    $id = intval(filter_var($_REQUEST["id"], FILTER_SANITIZE_NUMBER_INT), 10);
                }

                $showDelInv = FALSE;
                if (isset($_REQUEST['sdinv'])) {
                    $showDelInv = TRUE;
                }

                $markup = HTMLContainer::generateMarkup('div', ActivityReport::feesLog($dbh, $startDT, $endDT, $st, $pt, $id, 'Payments Report', $showDelInv), ['style' => 'margin-left:5px;']);
            }

            if (isset($_REQUEST['inv'])) {


                $markup = HTMLContainer::generateMarkup('div', ActivityReport::unpaidInvoiceLog($dbh), ['style' => 'margin-left:5px;']);
            }

            $events = (isset($_REQUEST['direct'])) ? HTMLContainer::generateMarkup('div', $markup, ['style' => 'position:relative;top:12px;']) . HTMLContainer::generateMarkup('div', '', ['style' => 'clear:both;']) : ['success' => HTMLContainer::generateMarkup('div', $markup, ['style' => 'position:relative;top:12px;']) . HTMLContainer::generateMarkup('div', '', ['style' => 'clear:both;'])];

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

            $events = ($id > 0) ? HTMLContainer::generateMarkup('div', ActivityReport::feesLog($dbh, NULL, NULL, [0 => ''], [0 => ''], $id, 'Payment History', FALSE), ['id' => 'rptfeediv', 'class' => 'ignrSave']) : '';
            break;

        case "getResc":

            $id = 0;
            if (isset($_REQUEST["id"])) {
                $id = intval(filter_var($_REQUEST["id"], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            $type = '';
            if (isset($_REQUEST["tp"])) {
                $type = filter_var($_REQUEST["tp"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            if ($type == 'resc') {

                $hospList = [];
                if (isset($uS->guestLookups[GLTableNames::Hospital])) {
                    $hospList = $uS->guestLookups[GLTableNames::Hospital];
                }

                $events = ResourceView::resourceDialog($dbh, $id, $uS->guestLookups[GLTableNames::RescType], $hospList);
            } else if ($type == 'room') {

                $roomRates = [];
                if (isset($uS->guestLookups['Static_Room_Rate'])) {
                    $roomRates = $uS->guestLookups['Static_Room_Rate'];
                }

                $reportCategories = readGenLookupsPDO($dbh, 'Room_Rpt_Cat');


                $events = ResourceView::roomDialog($dbh, $id, $uS->guestLookups[GLTableNames::RoomType], $uS->guestLookups[GLTableNames::RoomCategory], $reportCategories, $roomRates, $uS->guestLookups[GLTableNames::KeyDepositCode], $uS->KeyDeposit);
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
                $type = filter_var($_REQUEST["tp"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }
            $title = '';
            if (isset($_REQUEST["title"])) {
                $title = filter_var($_REQUEST["title"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $events = ResourceView::getStatusEvents($dbh, $id, $type, $title, $uS->guestLookups[GLTableNames::RescStatus], readGenLookupsPDO($dbh, 'OOS_Codes'));

            break;

        case 'saveStatEvent':

            $id = 0;
            if (isset($_REQUEST["id"])) {
                $id = intval(filter_var($_REQUEST["id"], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            $type = '';
            if (isset($_REQUEST["tp"])) {
                $type = filter_var($_REQUEST["tp"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
                $type = filter_var($_REQUEST["tp"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
                $type = filter_var($_REQUEST["tp"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
                $tbl = htmlspecialchars($_REQUEST['tbl']);
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

            $iid = 0;
            if (isset($_REQUEST["iid"])) {
                $iid = intval(filter_var($_REQUEST["iid"], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            $type = '';
            if (isset($_REQUEST["action"])) {
                $type = filter_var($_REQUEST["action"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $x = 0;
            if (isset($_POST['x'])) {
                $x = filter_var($_POST['x'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $container = '';
            if (isset($_POST['container'])) {
                $container = filter_var($_POST['container'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $showBillTo = FALSE;
            if (isset($_REQUEST["sbt"])) {
                $showBillTo = filter_var($_REQUEST["sbt"], FILTER_VALIDATE_BOOLEAN);
            }

            $events = InvoiceActions::invoiceAction($dbh, $iid, $type, $x, $container, $showBillTo);
            break;

        case 'invSetBill':

            $invNum = '';
            if (isset($_POST['inb'])) {
                $invNum = filter_var($_POST['inb'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $element = '';
            if (isset($_POST['ele'])) {
                $element = filter_var($_POST['ele'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $notesElement = '';
            if (isset($_POST['ntele'])) {
                $notesElement = filter_var($_POST['ntele'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $invDateStr = '';
            if (isset($_POST["date"])) {
                $invDateStr = filter_var($_POST["date"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $invNotes = '';
            if (isset($_POST["nts"])) {
                $invNotes = filter_var($_POST["nts"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $events = InvoiceActions::invoiceSetBill($dbh, $invNum, $invDateStr, $uS->username, $element, $invNotes, $notesElement);
            break;

        case 'saveRmCleanCode':

            $id = 0;
            if (isset($_REQUEST["idr"])) {
                $id = intval(filter_var($_REQUEST["idr"], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            $stat = '';
            if (isset($_REQUEST["stat"])) {
                $stat = filter_var($_REQUEST["stat"], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
                $tbl = filter_var($_REQUEST['tbl'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

	//start Date
            $startDate = '';
            if (isset($_REQUEST['stdte'])) {
                $startDate = filter_var($_REQUEST['stdte'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

		//end Date
            $endDate = '';
            if (isset($_REQUEST['enddte'])) {
                $endDate = filter_var($_REQUEST['enddte'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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

        case "getformtemplates" :
            $events = ['forms' => FormTemplate::listTemplates($dbh)];
            break;

        case "loadformtemplate" :
            $idDocument = 0;
            if(isset($_REQUEST['idDocument'])) {
                $idDocument = filter_var($_REQUEST['idDocument'], FILTER_VALIDATE_INT);
                $formTemplate = new FormTemplate();
                if($formTemplate->loadTemplate($dbh, $idDocument)){
                    $events = [
                        'status' => 'success',
                        'formTitle' => htmlspecialchars_decode($formTemplate->getTitle(), ENT_QUOTES),
                        'formTemplate' => $formTemplate->getTemplate(),
                        'formSettings' => $formTemplate->getSettings(),
                        'formURL' => $uS->resourceURL . 'house/showReferral.php?template=' . $idDocument
                    ];
                }else{
                    $events = ["error" => "Form not found"];
                }
            }
            break;

        case "saveformtemplate" :
            $idDocument = 0;
            if(isset($_REQUEST['idDocument'])) {
                $idDocument = filter_var($_REQUEST['idDocument'], FILTER_VALIDATE_INT);
            }

            $title = '';
            if(isset($_REQUEST['title'])) {
                $title = filter_var($_REQUEST['title'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $doc = '';
            if(isset($_REQUEST['doc'])) {
                try{
                    // Use funciton to test the doc.
                    json_decode(base64_decode($_REQUEST['doc']));
                    $doc = $_REQUEST['doc'];
                }catch(\Exception $e){

                }
            }

            $style = '';
            if(isset($_REQUEST['style'])) {
                $csstidy = new csstidy();
                $csstidy->parse($_REQUEST['style']);
                $style = $csstidy->print->plain();
            }

            $successTitle = '';
            if(isset($_REQUEST['successTitle'])) {
                $successTitle = filter_var($_REQUEST['successTitle'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $successContent = '';
            if(isset($_REQUEST['successContent'])) {
                $successContent = filter_var($_REQUEST['successContent'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $enableRecaptcha = '';
            if(isset($_REQUEST['enableRecaptcha'])) {
                $enableRecaptcha = filter_var($_REQUEST['enableRecaptcha'], FILTER_VALIDATE_BOOLEAN);
            }

            $enableReservation = '';
            if(isset($_REQUEST['enableReservation'])) {
                $enableReservation = filter_var($_REQUEST['enableReservation'], FILTER_VALIDATE_BOOLEAN);
            }

            $notifySubject = '';
            if(isset($_REQUEST['notifySubject'])) {
                $notifySubject = filter_var($_REQUEST['notifySubject'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $notifyMe = false;
            if(isset($_REQUEST['notifyMe'])) {
                $notifyMe = boolval(filter_var($_REQUEST['notifyMe'], FILTER_VALIDATE_BOOLEAN));
            }

            $notifyMeSubject = '';
            if(isset($_REQUEST['notifyMeSubject'])) {
                $notifyMeSubject = filter_var($_REQUEST['notifyMeSubject'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $notifyMeContent = '';
            if(isset($_REQUEST['notifyMeContent'])) {
                $notifyMeContent = filter_var($_REQUEST['notifyMeContent'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $initialGuests = '';
            if(isset($_REQUEST['initialGuests'])) {
                $initialGuests = intval(filter_var($_REQUEST['initialGuests'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $maxGuests = '';
            if(isset($_REQUEST['maxGuests'])) {
                $maxGuests = intval(filter_var($_REQUEST['maxGuests'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $fontImport = '';
            if(isset($_REQUEST['fontImport']) && is_array($_REQUEST['fontImport'])) {
                $fontImport = [];
                foreach($_REQUEST['fontImport'] as $font){
                    $fontImport[] = filter_var($font, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                }
            }

            $formTemplate = new FormTemplate();
            $formTemplate->loadTemplate($dbh, $idDocument);
            if($idDocument > 0) {
                $events = $formTemplate->save($dbh, $title, $doc, $style, $fontImport, $successTitle, $successContent, $enableRecaptcha, $enableReservation, $notifySubject, $notifyMe, $notifyMeSubject, $notifyMeContent, $initialGuests, $maxGuests, $uS->username);
            }else{
                $events = $formTemplate->saveNew($dbh, $title, $doc, $style, $fontImport, $successTitle, $successContent, $enableRecaptcha, $enableReservation, $notifySubject, $notifyMe, $notifyMeSubject, $notifyMeContent, $initialGuests, $maxGuests, $uS->username);
            }

            break;

        case "listforms" :
            $status = '';
            if(isset($_REQUEST['status'])){
                $status = htmlspecialchars($_REQUEST['status']);
            }

            $totalsOnly = false;
            if(isset($_REQUEST['totalsonly'])){
                $totalsOnly = filter_var($_REQUEST['totalsonly'], FILTER_VALIDATE_BOOLEAN);
            }

            $events = FormDocument::listForms($dbh, $status, $_GET, $totalsOnly);

            break;

        case "updateFormStatus" :
            $idDocument = 0;
            if(isset($_REQUEST['idDocument'])) {
                $idDocument = filter_var($_REQUEST['idDocument'], FILTER_VALIDATE_INT);
            }

            $status = '';
            if(isset($_REQUEST['status'])){
                $status = htmlspecialchars($_REQUEST['status']);
            }

            $formDocument = new FormDocument();
            $formDocument->loadDocument($dbh, $idDocument);
            $formDocument->updateStatus($dbh, $status);

            break;

        case "viewInsurance":
            $idName = 0;
            if(isset($_REQUEST['idName'])){
                $idName = filter_var($_REQUEST['idName'], FILTER_VALIDATE_INT);
                $mem = new IndivMember($dbh, MemBasis::Indivual, $idName);

                $events = ["markup" => $mem->createInsuranceSummaryPanel($dbh)];
            }
            break;

        case "getCssVars":
            $events = getCssVars($uS);
            break;

        case "getNameDetails":
            $post = filter_input_array(INPUT_POST, ['idNames'=>['filter', FILTER_SANITIZE_NUMBER_INT, 'flags'=>FILTER_FORCE_ARRAY], 'title'=>['filter', FILTER_SANITIZE_FULL_SPECIAL_CHARS]]);
            $events = getNameDetails($dbh, $post);
            break;
        default:
            $events = ["error" => "Bad Command: \"" . $c . "\""];
    }

} catch (PDOException $ex) {
    $events = ["error" => "Database Error: " . $ex->getMessage()];

} catch (Exception $ex) {
    $events = ["error" => "Programming Error: " . $ex->getMessage()];
}



if (is_array($events)) {
    echo (json_encode($events));
} else {
    echo $events;
}

exit();



function getCssVars(Session $uS){
    header('Content-Type: text/css');
    $vars = "";

    $vars .= ($uS->printScale ? "
    @media print{
        body{
            font-size: " . $uS->printScale/100 . "rem;
        }
    }" : '');

    return $vars;
}

function getNameDetails(\PDO $dbh, $post){
    if(isset($post['idNames'])){

        $query = "select distinct n.idName, n.Name_First, n.Name_Last, na.Address_1 as `address1`, na.Address_2 as `address2`,	na.City as `city`, na.State_Province, na.Postal_Code
        from name n
        left join name_address na on n.idName = na.idName AND na.Purpose = n.Preferred_Mail_Address
        where n.idName in (" . implode(',',$post['idNames']) . ")";

        $stmt = $dbh->prepare($query);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultTbl = new HTMLTable();
        $resultTbl->addHeaderTr(HTMLTable::makeTh('ID').HTMLTable::makeTh('First Name').HTMLTable::makeTh('Last Name').HTMLTable::makeTh('Address 1').HTMLTable::makeTh('Address 2').HTMLTable::makeTh('City').HTMLTable::makeTh('State').HTMLTable::makeTh('Zip'));
        foreach($results as $row){
            $tr = "";
            foreach($row as $key=>$value){
                if($key == "idName"){
                    $tr.= HTMLTable::makeTd(HTMLContainer::generateMarkup('a',$value, ['href'=>'GuestEdit.php?id='.$value]));
                }else{
                    $tr.= HTMLTable::makeTd($value);
                }
            }
            $resultTbl->addBodyTr($tr);
            
        }
        //$resultTbl->addfooterTr(HTMLTable::makeTd(implode(', ', $post['idNames']), ['colspan'=>'8']));
        $resultMkup = HTMLContainer::generateMarkup('div', $resultTbl->generateMarkup([], (isset($post['title']) ? HTMLContainer::generateMarkup("h4", $post['title'], ['class'=>"my-2"]) : '')), ['class'=>'ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-visitdialog', 'style'=>'width: fit-content; max-width: 100%; font-size: 0.9em; padding: 5px;', 'id'=>'nameDetails']);
        return ["idNames"=>$post["idNames"], "rowCount"=>$stmt->rowCount(), "resultMkup"=>$resultMkup];

    }else{
        return ['error'=>"idNames parameter required"];
    }
}
