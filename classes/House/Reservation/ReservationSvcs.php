<?php

namespace HHK\House\Reservation;


use HHK\HTMLControls\{HTMLContainer, HTMLSelector};
use HHK\House\Report\ActivityReport;
use HHK\Member\Role\Guest;
use HHK\Note\{LinkNote, Note};
use HHK\Notification\Mail\HHKMailer;
use HHK\Purchase\FinAssistance;
use HHK\SysConst\{DefaultSettings, GLTableNames, ReservationStatus, VisitStatus};
use HHK\Tables\EditRS;
use HHK\sec\Labels;
use HHK\sec\{SecurityComponent, Session};
use HHK\Tables\PaymentGW\Guest_TokenRS;
use HHK\House\Room\RoomChooser;
use HHK\SysConst\RoomRateCategories;
use HHK\House\RegistrationForm\RegisterForm;
use HHK\House\RegistrationForm\RegistrationForm;
use HHK\House\TemplateForm\ConfirmationForm;
use HHK\House\Hospital\HospitalStay;
use HHK\Member\Role\Agent;
use HHK\House\RegistrationForm\CustomRegisterForm;

/**
 * ReservationSvcs.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ReservationSvcs
 *
 * @author Eric
 */
class ReservationSvcs
{

    public static function viewActivity(\PDO $dbh, $idResv)
    {
        if ($idResv < 1) {
            return array(
                'error' => 'Reservation not defined.  '
            );
        }

        return array(
            'activity' => ActivityReport::reservLog($dbh, '', '', $idResv)
        );
    }

    public static function getCurrentReservations(\PDO $dbh, $idResv, $id, $idPsg, \DateTimeInterface $startDT, \DateTimeInterface $endDT)
    {
        $rows = array();

        if ($idPsg > 0 && $id > 0) {
            // look for both
            $stmt = $dbh->query("select * from vfind_guests " . "where (idPsg = $idPsg or idGuest = $id) and idReservation != $idResv and " . "Date(Arrival_Date) < DATE('" . $endDT->format('Y-m-d') . "') and Date(Departure_Date) > DATE('" . $startDT->format('Y-m-d') . "')");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } else if ($idPsg > 0) {

            $stmt = $dbh->query("select * from vfind_guests where idPsg = $idPsg and idReservation != $idResv and " . "Date(Arrival_Date) < DATE('" . $endDT->format('Y-m-d') . "') and Date(Departure_Date) > DATE('" . $startDT->format('Y-m-d') . "')");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } else if ($id > 0) {

            // EKC 9/15/2023; changed all three to vfind_guests from vresv_guests.
            //$stmt = $dbh->query("select * from vresv_guest where idGuest= $id and idReservation != $idResv and " . "Date(Arrival_Date) < DATE('" . $endDT->format('Y-m-d') . "') and Date(Departure_Date) > DATE('" . $startDT->format('Y-m-d') . "')");
            $stmt = $dbh->query("select * from vfind_guests where idGuest= $id and idReservation != $idResv and " . "Date(Arrival_Date) < DATE('" . $endDT->format('Y-m-d') . "') and Date(Departure_Date) > DATE('" . $startDT->format('Y-m-d') . "')");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        return $rows;
    }

    public static function getConfirmForm(\PDO $dbh, $idReservation, $idGuest, $amount, $sendEmail = FALSE, $notes = '', $emailAddr = '', $docCode = false, $ccEmailAddr = '')
    {
        if ($idReservation == 0) {
            return array(
                'error' => 'Bad reservation Id: ' . $idReservation
            );
        }

        $uS = Session::getInstance();
        $docs = array();
        $li = '';
        $tabContent = '';
        $dataArray = array();
        $cronJobs = array();
        $reservStatuses = array();

        $reserv = Reservation_1::instantiateFromIdReserv($dbh, $idReservation);

        if ($idGuest == 0) {
            $idGuest = $reserv->getIdGuest();
        }

        $guest = new Guest($dbh, '', $idGuest);

        $stmt = $dbh->query("Select d.`idDocument`, g.`Code`, g.`Description` from `document` d join gen_lookups g on d.idDocument = g.`Substitute` join gen_lookups fu on fu.`Substitute` = g.`Table_Name` where fu.`Code` = 'c' AND fu.`Table_Name` = 'Form_Upload' order by g.`Order`");
        $docRows = $stmt->fetchAll();

        if (count($docRows) > 0) {

            //get cron jobs
            $stmt = $dbh->query("select * from cronjobs where `Code` = 'SendConfirmationEmailJob' and `Status` = 'a'");
            $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach($jobs as $job){
                $params = json_decode($job["Params"], true);
                if(isset($params["EmailTemplate"]) && $params["EmailTemplate"] > 0){
                    $job["Params"] = $params;
                    $cronJobs[$params["EmailTemplate"]][$job["idJob"]] = $job;
                }
            }
            $reservStatuses = readLookups($dbh, "reservStatus", "Code");

            foreach ($docRows as $d) {

                $confirmForm = new ConfirmationForm($dbh, $d['idDocument']);
                $formNotes = $confirmForm->createNotes($notes, ! $sendEmail, $d['Code']);

                $docs[$d['Code']] = array(
                    'doc' => $confirmForm->createForm($confirmForm->makeReplacements($dbh, $reserv, $guest, $amount, $formNotes)),
                    'subjectLine' => ($confirmForm->getSubjectLine() != "" ? $confirmForm->getSubjectLine() : Labels::getString('referral', 'Res_Confirmation_Subject', htmlspecialchars_decode($uS->siteName, ENT_QUOTES) . ' Reservation Confirmation')),
                    'style' => RegisterForm::getStyling(),
                    'tabIndex' => $d['Code'],
                    'tabTitle' => $d['Description'],
                    'docId' => $d['idDocument']
                );
                
                if(isset($cronJobs[$d['idDocument']])){
                    $docs[$d['Code']]["cronJobs"] = $cronJobs[$d['idDocument']];
                }else{
                    $docs[$d['Code']]["cronJobs"] = [];
                }
            }
        } else {

            $docs[] = array(
                'doc' => 'The confirmation document is missing.',
                'subjectLine' => Labels::getString('referral', 'Res_Confirmation_Subject', htmlspecialchars_decode($uS->siteName, ENT_QUOTES) . ' Reservation Confirmation'),
                'style' => RegisterForm::getStyling(),
                'tabIndex' => 'en',
                'tabTitle' => 'English',
                'docId' => false,
                'cronJobs'=>[]
            );
        }

        foreach ($docs as $r) {
            $cronAlert = "";
            $intervalStr = "";

            $li .= HTMLContainer::generateMarkup('li',
                HTMLContainer::generateMarkup('a', $r['tabTitle'] . ($r["cronJobs"] ? '<i class="ml-2 bi bi-watch"></i>':'') , array('href'=>'#'.$r['tabIndex'])), array('data-docId'=>$r['docId']));

            if(count($r["cronJobs"]) > 0){
                foreach($r["cronJobs"] as $job){
                    $jobTime = new \DateTime($reserv->getExpectedArrival());
                    $jobTime->sub(new \DateInterval("P" . $job["Params"]["solicitBuffer"] . "D"));

                    switch($job["Interval"]){
                        case "daily":
                            $jobTime->setTime($job["Hour"], $job["Minute"]);
                            $intervalStr = "at " . $jobTime->format("g:i a") . " <strong>";
                            break;
                        case "hourly":
                            $intervalStr = "every hour at " . $job["Minute"] . " minutes past the hour <strong> ";
                            break;
                        case "weekly":
                            $weekdays = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
                            $jobTime->setTime($job["Hour"], $job["Minute"]);
                            $cronAlert .= "This email is sent automatically at " . $jobTime->format("g:i a") . " if " . $jobTime->format("M j, Y") . " (" . $job["Params"]["solicitBuffer"] . " days before the expected arrival) falls on a " . $weekdays[$job["Day"]] . (isset($reservStatuses[$job["Params"]["ResvStatus"]]) ? " for <strong>" . $reservStatuses[$job["Params"]["ResvStatus"]]["Title"] . " Reservations</strong> " : "");
                            break;
                        case "monthly":
                            $jobTime->setTime($job["Hour"], $job["Minute"]);
                            $cronAlert = "This email is sent automatically at " . $jobTime->format("g:i a") . " if " . $jobTime->format("M j, Y") . " (" . $job["Params"]["solicitBuffer"] . " days before the expected arrival) falls on day " . $job["Day"] . " of the month " . (isset($reservStatuses[$job["Params"]["ResvStatus"]]) ? " for <strong>" . $reservStatuses[$job["Params"]["ResvStatus"]]["Title"] . " Reservations</strong> " : "");
                            break;
                        default:
                            $intervalStr = "";
                    }
                    
                    $cronAlert .= ($intervalStr != '' ? "This email is sent automatically " . $intervalStr . $job["Params"]["solicitBuffer"] . " days before</strong> (" . $jobTime->format("M j, Y") . ") the expected arrival date " . (isset($reservStatuses[$job["Params"]["ResvStatus"]]) ? "of <strong>" . $reservStatuses[$job["Params"]["ResvStatus"]]["Title"] . " Reservations</strong> " : ""):""); 
                }
                $cronAlert = HTMLContainer::generateMarkup("div", '<i class="mr-3 bi bi-info-circle-fill"></i>' . $cronAlert, ['class' => 'mb-4 p-2 ui-corner-all ui-state-highlight']);
            }

            $tabContent .= HTMLContainer::generateMarkup('div', $cronAlert .
                HTMLContainer::generateMarkup('div', ($r['doc'] != '' ? $r['doc']: '<div class="ui-state-error">The confirmation document is empty</div>'), array('id'=>'PrintArea'.$r['tabIndex'])),
                array('id'=>$r['tabIndex']));

            $sty = $r['style'];
        }

        $ul = HTMLContainer::generateMarkup('ul', $li, array());
        $tabControl = HTMLContainer::generateMarkup('div', $ul . $tabContent, array('id'=>'confirmTabDiv', 'class'=>'user-agent-spacing'));

        if ($emailAddr == '') {
            $emAddr = $guest->getEmailsObj()->get_data($guest->getEmailsObj()
                ->get_preferredCode());
            $emailAddr = $emAddr["Email"];
        }

        if ($uS->CCAgentConf && $uS->ReferralAgent && $ccEmailAddr == '' && !$sendEmail){
            $hstay = new HospitalStay($dbh, 0, $reserv->getIdHospitalStay());
            $agent = new Agent($dbh, '', $hstay->getAgentId());
            $ccEmailAddr = $agent->getEmailsObj()->get_Data($agent->getEmailsObj()->get_preferredCode());
            $ccEmailAddr = $ccEmailAddr["Email"];
        }

        if ($sendEmail && $docCode && isset($docs[$docCode])) {

            if ($emailAddr != '') {

                try{
                    $mail = new HHKMailer($dbh);
                    $mail->From = $uS->FromAddress;
                    $mail->FromName = htmlspecialchars_decode($uS->siteName, ENT_QUOTES);
                    
                    $tos = explode(',', $emailAddr);
                    foreach ($tos as $k=>$to) {
                        $mail->addAddress(filter_var($to, FILTER_SANITIZE_EMAIL)); // Add a recipient
                    }

                    $ccs = explode(',', $ccEmailAddr);
                    foreach ($ccs as $cc){
                        if($cc != ''){
                            $mail->addCC(filter_var($cc, FILTER_SANITIZE_EMAIL));
                        }
                    }

                    $mail->addReplyTo($uS->ReplyTo);

                    $bccs = explode(',', $uS->BccAddress);
                    foreach ($bccs as $bcc) {
                        $bcc = trim($bcc);
                        if ($bcc != '') {
                            $mail->addBCC(filter_var($bcc, FILTER_SANITIZE_EMAIL));
                        }
                    }

                    $mail->isHTML(true);

                    $mail->Subject = htmlspecialchars_decode($docs[$docCode]['subjectLine'], ENT_QUOTES);
                    $mail->msgHTML($docs[$docCode]['doc']);

                    $mail->send();

                    // Make a note in the reservation.
                    $noteText = (isset($docs[$docCode]['tabTitle']) ? $docs[$docCode]['tabTitle'] . ' ' : '') . 'Confirmation Email';

                    try {
                        $arrive = (new \DateTime($reserv->getArrival()))->format("M d, Y");
                        $depart = (new \DateTime($reserv->getDeparture()))->format("M d, Y");

                        $noteText .= " for " . $arrive . " to " . $depart;
                    }catch(\Exception $e){

                    }
                    
                    $noteText .= ' sent to ' . implode(", ", $mail->getToString()) .  " with subject: " . $docs[$docCode]["subjectLine"];
                    if($ccEmailAddr != '' && count($ccs) > 0){
                        $noteText .= '; CC\'d to ' . implode(", ", $mail->getCCString());
                    }
                    if ($notes != '') { // add special note if any are present
                        $noteText .= ' with a ' . Labels::getString("Referral", "specialNoteConfEmail", "Special Note") . ': ' . str_replace('\n', ' ', $notes);
                    }
                    LinkNote::save($dbh, $noteText, $reserv->getIdReservation(), Note::ResvLink, '', $uS->username, $uS->ConcatVisitNotes);

                    $reserv->saveReservation($dbh, $reserv->getIdRegistration(), $uS->username);

                    $dataArray['mesg'] = "Email sent.  ";
                    $dataArray['status'] = 'success';

                }catch(\Exception $e){
                    $dataArray['mesg'] = "Email failed!  " . $mail->ErrorInfo;
                    $dataArray['status'] = 'error';
                }
            } else {
                $dataArray['mesg'] = "Guest email address is blank.  ";
                $dataArray['status'] = 'error';
            }
        } else {

            $dataArray['confrv'] = $tabControl;
            $dataArray['email'] = $emailAddr;
            if(isset($ccEmailAddr)){
                $dataArray['ccemail'] = $ccEmailAddr;
            }
        }

        return $dataArray;
    }

    public static function generateCkinDoc(\PDO $dbh, $idReservation = 0, $idVisit = 0, $span = 0, $logoURL = '', $notes = '')
    {
        $uS = Session::getInstance();
        $return = array("docs"=>[]);
        $docs = array();

        $stmt = $dbh->query("Select g.`Code`, g.`Description`, d.`Abstract`, d.`Doc`, d.idDocument as `docId` from `document` d join gen_lookups g on d.idDocument = g.`Substitute` where g.`Table_Name` = 'Reg_Agreement' order by g.`Order`");
        $docRows = $stmt->fetchAll();

        if ($uS->RegForm == 1) {

            $regForm = new RegisterForm();

            if (count($docRows) > 0) {

                foreach ($docRows as $d) {

                    $docs[] = array(
                        'doc' => $regForm->prepareRegForm($dbh, $idVisit, $span, $idReservation, $d['Doc']),
                        'style' => RegisterForm::getStyling(),
                        'tabIndex' => $d['Code'],
                        'tabTitle' => $d['Description']
                    );
                }
            } else {

                $docs[] = array(
                    'doc' => $regForm->prepareRegForm($dbh, $idVisit, $span, $idReservation, 'The registration agreement document is missing. '),
                    'style' => RegisterForm::getStyling(),
                    'tabIndex' => 'en',
                    'tabTitle' => 'English'
                );
            }


        } else if($uS->RegForm == 3){

            if (count($docRows) > 0) {

                foreach ($docRows as $d) {

                    $regSettings = [];
                    if(!empty($d['Abstract']) && @json_decode($d['Abstract'], true)){
                        $regSettings = json_decode($d['Abstract'], true);
                    }

                    $regForm = new CustomRegisterForm($d["Code"], $regSettings);

                    $docs[] = array(
                        'doc' => $regForm->prepareRegForm($dbh, $idVisit, $span, $idReservation, $d),
                        'style' => '',
                        'tabIndex' => $d['Code'],
                        'tabTitle' => $d['Description'],
                        'pageTitle' => $regForm->getPageTitle(),
                        'allowSave' => (!empty($regForm->settings["Signatures"]["eSign"]) && ($regForm->settings["Signatures"]["eSign"] == 'jSign' || $regForm->settings["Signatures"]["eSign"] == 'topaz')),
                        'signType' => (!empty($regForm->settings["Signatures"]["eSign"]) ? $regForm->settings["Signatures"]["eSign"] : '')
                    );
                }
            } else {

                $regForm = new CustomRegisterForm();

                $docs[] = array(
                    'doc' => $regForm->prepareRegForm($dbh, $idVisit, $span, $idReservation, 'The registration agreement document is missing. '),
                    'style' => '',
                    'tabIndex' => 'en',
                    'tabTitle' => 'English'
                );
            }

            //get primary guest
            if ($idVisit > 0) {

                $stmtv = $dbh->prepare("Select v.idPrimaryGuest, v.idRegistration, r.idPsg, v.idReservation from visit v join registration r on v.idRegistration = r.idRegistration where idVisit = :idv and span = :span");
                $stmtv->execute(array(
                    ':idv' => $idVisit,
                    ':span' => $span
                ));
                $rows = $stmtv->fetchAll(\PDO::FETCH_ASSOC);

                if(count($rows) == 1){
                    $return["idPrimaryGuest"] = $rows[0]["idPrimaryGuest"];
                    $return["idPsg"] = $rows[0]["idPsg"];
                    $return["idReservation"] = $rows[0]["idReservation"];
                }

            }else if($idReservation > 0){
                $stmtr = $dbh->prepare("Select rg.idGuest, reg.idPsg from reservation r join reservation_guest rg on r.idReservation = rg.idReservation join registration reg on r.idRegistration = reg.idRegistration where rg.idReservation = :idr and `Primary_Guest` = '1'");
                $stmtr->execute(array(
                    ':idr' => $idReservation
                ));
                $rows = $stmtr->fetchAll(\PDO::FETCH_ASSOC);

                if(count($rows) == 1){
                    $return["idPrimaryGuest"] = $rows[0]["idGuest"];
                    $return["idPsg"] = $rows[0]["idPsg"];
                }

            }

        } else if ($uS->RegForm == 2) {

            // IMD

            $instructFileName = '';
            $roomTitle = '';
            $additionalGuests = array();
            $priGuest = new Guest($dbh, '', 0);
            $idHospitalStay = 0;
            $idRegistration = 0;
            $patientName = '';
            $hospitalName = '';
            $cardName = '';
            $cardNumber = '';
            $cardType = '';
            $expectedPayType = '';
            $todaysDate = '';

            if ($idVisit > 0) {

                // arrival date and time
                $stmtv = $dbh->prepare("Select idPrimaryGuest, idHospital_stay, idRegistration from visit where idVisit = :idv");
                $stmtv->execute(array(
                    ':idv' => $idVisit
                ));
                $rows = $stmtv->fetchAll(\PDO::FETCH_NUM);

                if (count($rows) == 0) {
                    return array(
                        'doc' => 'Error - Visit not found.',
                        'style' => ' '
                    );
                }

                $todaysDate = date('M j, Y');
                $idHospitalStay = $rows[0][1];
                $idRegistration = $rows[0][2];
                $idPG = $rows[0][0];
                $priGuest = new Guest($dbh, '', $idPG);

                // Get additional Guests
                $query = "select * from vadditional_guests where Status in ('" . VisitStatus::Active . "','" . VisitStatus::CheckedOut . "') and idVisit = :idv LIMIT 6";
                $stmt = $dbh->prepare($query, array(
                    \PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY
                ));
                $stmt->bindValue(':idv', $idVisit, \PDO::PARAM_INT);
                $stmt->execute();
                $stays = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                $ids = array();

                // List each additional guest.
                foreach ($stays as $s) {

                    $roomTitle = $s['Title'];

                    if (isset($ids[$s['idName']])) {
                        // Dump dups.
                        continue;
                    }

                    $ids[$s['idName']] = 1;

                    if ($s['idName'] != $idPG) {

                        $guest = new Guest($dbh, '', $s['idName']);
                        $guest->setPatientRelationshipCode($s['Relationship_Code']);
                        $guest->setCheckinDate($s['Checkin_Date']);
                        $guest->setExpectedCheckOut($s['Expected_Co_Date']);
                        $additionalGuests[] = $guest;
                    } else {

                        $priGuest->setCheckinDate($s['Checkin_Date']);
                        $priGuest->setExpectedCheckOut($s['Expected_Co_Date']);
                    }
                }
            } else if ($idReservation > 0) {

                // Room title
                $resv = Reservation_1::instantiateFromIdReserv($dbh, $idReservation);

                $roomTitle = $resv->getRoomTitle($dbh);
                $idHospitalStay = $resv->getIdHospitalStay();
                $idRegistration = $resv->getIdRegistration();
                $notes = $resv->getCheckinNotes();

                if (isset($uS->nameLookups[GLTableNames::PayType][$resv->getExpectedPayType()])) {
                    $expectedPayType = $uS->nameLookups[GLTableNames::PayType][$resv->getExpectedPayType()][1];
                }

                // Guests
                $guestIds = self::getReservGuests($dbh, $idReservation);

                foreach ($guestIds as $id => $stat) {

                    if ($stat == '1') {

                        $priGuest = new Guest($dbh, '', $id);
                        $priGuest->setCheckinDate($resv->getExpectedArrival());
                    } else {

                        $guest = new Guest($dbh, '', $id);
                        $guest->setCheckinDate($resv->getExpectedArrival());
                        $additionalGuests[] = $guest;
                    }
                }
            } else {
                return array(
                    'doc' => 'Error - Parameters are not specified.',
                    'style' => ' '
                );
            }

            // Patient and Hospital
            if ($idHospitalStay > 0) {

                $stmt = $dbh->query("Select n.Name_Full, n.BirthDate, case when ha.Description = '' then ha.`Title` else ha.Description end as `Assoc`, case when hh.Description = '' then hh.`Title` else hh.Description end as `Hosp`
    from
        hospital_stay hs
            left join
        `name` n ON hs.idPatient = n.idName
            left join
        hospital ha ON hs.idAssociation = ha.idHospital
            left join
        hospital hh ON hs.idHospital = hh.idHospital
    where
        hs.idHospital_stay = $idHospitalStay");

                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($rows as $r) {
                    $patientName = $r['Name_Full'] . ($r['BirthDate'] == '' ? '' : ' (' . date('M j, Y', strtotime($r['BirthDate'])) . ')');
                    $hospitalName = ($r['Assoc'] == '' || $r['Assoc'] == '(None)' ? $r['Hosp'] : $r['Assoc'] . ' / ' . $r['Hosp']);
                }
            }

            // Set the billing guest
            $billingGuest = new Guest($dbh, '', 0);

            // payment info
            if ($idRegistration > 0) {

                $t = new Guest_TokenRS();

                $t->idRegistration->setStoredVal($idRegistration);
                $rows = EditRS::select($dbh, $t, array(
                    $t->idRegistration
                ));

                foreach ($rows as $r) {

                    $t = new Guest_TokenRS();
                    EditRS::loadRow($r, $t);

                    // Grab the primary guest, otherwise, use one of the others.
                    if ($t->idGuest->getStoredVal() == $priGuest->getIdName()) {

                        $billingGuest = $priGuest;
                        $cardName = $t->CardHolderName->getStoredVal();
                        $cardNumber = $t->MaskedAccount->getStoredVal();
                        $cardType = $t->CardType->getStoredVal();
                        break;
                    } else if ($t->idGuest->getStoredVal() != 0) {

                        $billingGuest = new Guest($dbh, '', $t->idGuest->getStoredVal());
                        $cardName = $t->CardHolderName->getStoredVal();
                        $cardNumber = $t->MaskedAccount->getStoredVal();
                        $cardType = $t->CardType->getStoredVal();
                    }
                }
            }

            // Show the room on the registration form?
            if ($uS->RegFormNoRm) {
                $roomTitle = '';
            }

            $regdoc = new RegistrationForm();
            $logoWidth = $uS->statementLogoWidth;

            if (count($docRows) > 0) {
                foreach ($docRows as $d) {

                    $docs[] = array(
                        'doc' => $regdoc->getDocument($dbh, $priGuest, $billingGuest, $additionalGuests, $patientName, $hospitalName, $roomTitle, $cardName, $cardType, $cardNumber, $logoURL, $logoWidth, $instructFileName, $d['Doc'], $expectedPayType, $notes, $todaysDate),
                        'style' => $regdoc->getStyle(),
                        'tabIndex' => $d['Code'],
                        'tabTitle' => $d['Description']
                    );
                }
            } else {

                $docs[] = array(
                    'doc' => $regdoc->getDocument($dbh, $priGuest, $billingGuest, $additionalGuests, $patientName, $hospitalName, $roomTitle, $cardName, $cardType, $cardNumber, $logoURL, $logoWidth, $instructFileName, '', $expectedPayType, $notes, $todaysDate),
                    'style' => $regdoc->getStyle(),
                    'tabIndex' => 'en',
                    'tabTitle' => 'English'
                );
            }
        } else {
            return array(
                'docs' => [],
                'error' => 'Error - Registration Form is not defined in the system configuration.',
                'style' => ' '
            );
        }

        $return["docs"] = $docs;
        return $return;
    }

    /**
     * Get signed reg forms HTML
     *
     * @param \PDO $dbh
     * @param number $idPsg
     * @param number $idReservation
     * @param number $idVisit
     * @param number $span
     *
     * @return array
     */
    public static function getSignedCkinDocs(\PDO $dbh, $idPsg = 0, $idReservation = 0, $idVisit = 0, $span = 0){
        $sql = 'select * from v_signed_reg_forms where PSG_Id = :idPsg';
        $params = array(':idPsg'=>$idPsg);

        if($idReservation > 0 || $idVisit > 0){
            $sql .= " AND (";
            if($idReservation > 0){
                $sql .= ' Resv_Id = :idResv';
                $params[':idResv'] = $idReservation;
            }
            if($idReservation > 0 && $idVisit > 0){
                $sql .= " OR ";
            }
            if($idVisit > 0){
                $sql .= '  Visit_Id = :idVisit';
                $params[':idVisit'] = $idVisit;
            }
            $sql .= ")";
        }

        $stmt = $dbh->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getReservGuests(\PDO $dbh, $idReservation)
    {
        $idResv = intval($idReservation, 10);
        $ids = array();

        $stmt = $dbh->query("Select * from reservation_guest where idReservation = $idResv");

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $ids[$r['idGuest']] = $r['Primary_Guest'];
        }

        return $ids;
    }

    public static function moveResvAway(\PDO $dbh, \DateTimeInterface $firstArrival, \DateTimeInterface $lastDepart, $idResource, $uname)
    {

    	$uS = Session::getInstance();

    	// Move other reservations to alternative rooms?
        $rRows = Reservation_1::findReservations($dbh, $firstArrival->format('Y-m-d H:i:s'), $lastDepart->format('Y-m-d H:i:s'), $idResource);

        $reply = '';

        if (count($rRows) > 0) {

            // Move reservations to other rooms.
            foreach ($rRows as $r) {

                $resv = Reservation_1::instantiateFromIdReserv($dbh, $r[0]);
                $rArrivalDT = new \DateTime($resv->getExpectedArrival());
                $move = TRUE;

                if ($uS->IncludeLastDay == TRUE && $rArrivalDT->format('Y-m-d') == $lastDepart->format('Y-m-d')) {
                	// Dont move
                	$move = FALSE;
                }

                if ($resv->getStatus() != ReservationStatus::Staying && $resv->getStatus() != ReservationStatus::Checkedout && $move) {

                    $resv->move($dbh, 0, 0, $uname, TRUE);
                    $reply .= $resv->getResultMessage();
                }
            }
        }

        return $reply;
    }

    public static function moveReserv(\PDO $dbh, $idReservation, $startDelta, $endDelta)
    {
        $uS = Session::getInstance();
        $dataArray = array();

        if (SecurityComponent::is_Authorized('guestadmin') === FALSE) {
            return array(
                "error" => "User not authorized to move reservations."
            );
        }

        if ($idReservation == 0) {
            return array(
                "error" => "Reservation not specified."
            );
        }

        if ($startDelta == 0 && $endDelta == 0) {
            return array(
                "error" => "Reservation not moved."
            );
        }

        if (abs($endDelta) > ($uS->MaxExpected) || abs($startDelta) > ($uS->MaxExpected)) {
            return array(
                "error" => 'Move refused, change too large: Start Delta = ' . $startDelta . ', End Delta = ' . $endDelta
            );
        }

        // save the reservation info
        $reserv = Reservation_1::instantiateFromIdReserv($dbh, $idReservation);
        $worked = $reserv->move($dbh, $startDelta, $endDelta, $uS->username);
        $reply = $reserv->getResultMessage();

        if ($worked) {

            // Return checked in guests markup?
            if ($reserv->getStatus() == ReservationStatus::Committed) {
                $dataArray['reservs'] = 'y';
            } else if ($reserv->getStatus() == ReservationStatus::UnCommitted && $uS->ShowUncfrmdStatusTab) {
                $dataArray['unreserv'] = 'y';
            } else if ($reserv->getStatus() == ReservationStatus::Waitlist) {
                $dataArray['waitlist'] = 'y';
            }
            $dataArray["success"] = $reply;
        }else{
            return ["error"=>$reply];
        }


        return $dataArray;
    }

    public static function getIncomeDialog(\PDO $dbh, $rid, $rgId)
    {
        $dataArray = array();
        $cat = DefaultSettings::Rate_Category;

        if ($rid > 0) {
            $resv = Reservation_1::instantiateFromIdReserv($dbh, $rid);
            $cat = ($resv->getRoomRateCategory() != '' ? $resv->getRoomRateCategory() : DefaultSettings::Rate_Category);
            $rgId = $resv->getIdRegistration();
        }

        $finApp = new FinAssistance($dbh, $rgId, $cat);

        $dataArray['incomeDiag'] = HTMLContainer::generateMarkup('form', HTMLContainer::generateMarkup('div', $finApp->createIncomeDialog()), array(
            'id' => 'formf'
        ));

        $dataArray['rstat'] = $finApp->isApproved();
        $dataArray['rcat'] = $finApp->getFaCategory();

        return $dataArray;
    }

    public static function saveFinApp(\PDO $dbh, $post)
    {
        $uS = Session::getInstance();

        $idReserv = 0;
        $idRegistration = 0;
        $reserv = NULL;

        if (isset($post['rgId'])) {
            $idRegistration = intval(filter_var($post['rgId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($post['rid'])) {
            $idReserv = intval(filter_var($post['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if ($idReserv > 0) {
            $reserv = Reservation_1::instantiateFromIdReserv($dbh, $idReserv);
            $idRegistration = $reserv->getIdRegistration();
        }

        if ($idRegistration < 1) {
            return array(
                'error' => 'The Registration is not defined.  '
            );
        }

        $finApp = new FinAssistance($dbh, $idRegistration);

        $faCategory = '';
        $faStat = '';
        $reason = '';
        $notes = '';
        $faStatDate = '';

        if (isset($post['txtFaIncome'])) {
            $income = filter_var(str_replace(',', '', $post['txtFaIncome']), FILTER_SANITIZE_NUMBER_INT);
            $finApp->setMontylyIncome($income);
        }

        if (isset($post['txtFaSize'])) {
            $size = intval(filter_var($post['txtFaSize'], FILTER_SANITIZE_NUMBER_INT), 10);
            $finApp->setHhSize($size);
        }

        // FA Category
        if (isset($post['hdnRateCat'])) {
            $faCategory = filter_var($post['hdnRateCat'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        if (isset($post['SelFaStatus']) && $post['SelFaStatus'] != '') {
            $faStat = filter_var($post['SelFaStatus'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        if (isset($post['txtFaStatusDate']) && $post['txtFaStatusDate'] != '') {
            $faDT = setTimeZone($uS, filter_var($post['txtFaStatusDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $faStatDate = $faDT->format('Y-m-d');
        }

        // Reason text
        if (isset($post['txtFaReason'])) {
            $reason = filter_var($post['txtFaReason'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        // Notes
        if (isset($post['txtFaNotes'])) {
            $notes = filter_var($post['txtFaNotes'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        // Save Fin App dialog.
        $finApp->saveDialogMarkup($dbh, $faStat, $faCategory, $reason, $faStatDate, $notes, $uS->username);

        if (is_null($reserv) === FALSE && isset($post['fadjAmount']) && isset($post['txtFaFixedRate'])) {

            $rateAdj = intval(filter_var($post['fadjAmount'], FILTER_SANITIZE_NUMBER_INT), 10);
            $assignedRate = filter_var($post['txtFaFixedRate'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

            // update the reservation.
            if ($finApp->isApproved()) {

                $reserv->setRateAdjust($rateAdj);
                $reserv->setRoomRateCategory($finApp->getFaCategory());
                $reserv->setFixedRoomRate($assignedRate);
            } else {

                $reserv->setRateAdjust(0);
                $reserv->setRoomRateCategory(RoomRateCategories::FlatRateCategory);
                $reserv->setFixedRoomRate(0);
            }

            $reserv->saveReservation($dbh, $reserv->getIdRegistration(), $uS->username);
        }

        $dataArray['rstat'] = $finApp->isApproved();
        $dataArray['rcat'] = $finApp->getFaCategory();

        return $dataArray;
    }

    public static function changeReservStatus(\PDO $dbh, $idReservation, $status)
    {
        if ($idReservation == 0 || $status == '') {
            return array(
                'error' => 'Bad input parameters: Reservation Id = ' . $idReservation . ', new Status = ' . $status
            );
        }

        $uS = Session::getInstance();

        $resv = Reservation_1::instantiateFromIdReserv($dbh, $idReservation);
        $oldStatus = $resv->getStatus();

        if ($oldStatus == ReservationStatus::Waitlist && ($status == ReservationStatus::Committed || $status == ReservationStatus::UnCommitted)) {
            return array(
                'error' => 'Cannot change from Waitlist to Confirmed or Unconfirmed here.'
            );
        }

        if ($status == ReservationStatus::Waitlist) {
            $resv->setIdResource(0);
        }

        $resv->setStatus($status);

        $resv->saveReservation($dbh, $resv->getIdRegistration(), $uS->username);

        $dataArray['success'] = 'Reservation status changed to ' . $uS->guestLookups['ReservStatus'][$status][1];

        if ($oldStatus == ReservationStatus::Committed || $status == ReservationStatus::Committed) {
            $dataArray['reservs'] = 'y';
        }

        if ($oldStatus == ReservationStatus::UnCommitted || $status == ReservationStatus::UnCommitted) {
            $dataArray['unreserv'] = 'y';
        }

        if ($oldStatus == ReservationStatus::Waitlist || $status == ReservationStatus::Waitlist) {
            $dataArray['waitlist'] = 'y';
        }

        return $dataArray;
    }

    /**
     * Summary of getRoomList
     * @param \PDO $dbh
     * @param \HHK\House\Reservation\Reservation_1 $resv
     * @param string $eid
     * @param bool $isAuthorized
     * @param int $numGuests
     * @return array
     */
    public static function getRoomList(\PDO $dbh, Reservation_1 $resv, $eid, $isAuthorized, $numGuests = 0)
    {
        if ($numGuests <= 0) {
            $numGuests = $resv->getNumberGuests();
        }

        if ($isAuthorized) {
            $resv->findGradedResources($dbh, $resv->getExpectedArrival(), $resv->getExpectedDeparture(), $numGuests, array(
                'room',
                'rmtroom',
                'part'
            ), TRUE);
        } else {
            $resv->findResources($dbh, $resv->getExpectedArrival(), $resv->getExpectedDeparture(), $numGuests, array(
                'room',
                'rmtroom',
                'part'
            ), TRUE);
        }

        $resources = array();
        $errorMessage = '';

        // Load available resources
        foreach ($resv->getAvailableResources() as $r) {
            $resources[$r->getIdResource()] = array(
                $r->getIdResource(),
                $r->getTitle(),
                $r->optGroup
            );
        }

        // add waitlist option to the top of the list
        $resources[0] = array(
            0,
            '-None-',
            ''
        );

        // Selected resource
        $idResourceChosen = $resv->getIdResource();

        if ($resv->getStatus() == ReservationStatus::Waitlist) {

            $idResourceChosen = 0;
        } else if (($resv->getStatus() == ReservationStatus::Committed || $resv->getStatus() == ReservationStatus::UnCommitted) && isset($resources[$resv->getIdResource()])) {

            $myResc = $resources[$resv->getIdResource()];

            if (isset($myResc[2]) && $myResc[2] != '') {
                $errorMessage = $myResc[2];
            }
        } else {

            $untestedRescs = $resv->getUntestedResources();
            if (isset($untestedRescs[$resv->getIdResource()])) {
                $errorMessage = 'Room ' . $resv->getRoomTitle($dbh) . ' is not suitable.';
            } else {
                $errorMessage = 'Room ' . $resv->getRoomTitle($dbh) . ' may be too small.';
            }
        }

        // Adjust size of selector
        $selSize = count($resources);
        if ($selSize > 8) {
            $selSize = 8;
        } else if ($selSize < 4) {
            $selSize = 4;
        }

        $dataArray['ctrl'] = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($resources, $idResourceChosen), array(
            'name' => 'selResource'
        ));
        $dataArray['container'] = HTMLContainer::generateMarkup('div',
        		HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($resources, $idResourceChosen), array(
            'id' => 'selRoom',
            'size' => $selSize
        )), array(
            'id' => 'pudiv',
            'class' => "ui-widget ui-widget-content ui-helper-clearfix ui-corner-all",
            'style' => "font-size:0.9em;position: absolute; z-index: 1; display: block;"
        )); // top:".$y."px; left:".$xa."px;
        $dataArray['eid'] = $eid;
        $dataArray['msg'] = $errorMessage;
        $dataArray['rid'] = $resv->getIdReservation();

        return $dataArray;
    }

    public static function reviseConstraints(\PDO $dbh, $idResv, $idResc, $numGuests, $expArr, $expDep, $cbs, $isAuthorized = FALSE, $omitSelf = TRUE)
    {
        $resv = Reservation_1::instantiateFromIdReserv($dbh, $idResv);

        $resv->saveConstraints($dbh, $cbs);

        $roomChooser = new RoomChooser($dbh, $resv, $numGuests, new \DateTime($expArr), new \DateTime($expDep));
        $roomChooser->findResources($dbh, $isAuthorized, $omitSelf, $numGuests);

        $resOptions = $roomChooser->makeRoomSelectorOptions();
        $errorMessage = $roomChooser->getRoomSelectionError($dbh, $resOptions);

        return array(
            'rooms' => $roomChooser->makeRoomsArray(),
            'selectr' => $roomChooser->makeRoomSelector($resOptions, $idResc),
            'idResource' => $idResc,
            'msg' => $errorMessage
        );
    }

}
