<?php
namespace HHK\House\Report;

use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLTable};
use HHK\Payment\{Statement, PostPayment};
use HHK\Payment\PaymentGateway\AbstractPaymentGateway;
use HHK\SysConst\{GLTableNames, InvoiceStatus, PaymentMethod, PaymentStatusCode, ReservationStatus, VisitStatus};
use HHK\sec\Labels;
use HHK\sec\Session;

/**
 * ActivityReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class ActivityReport {

    /**
     *
     * @param \PDO $dbh
     * @param string $startDate
     * @param string $endDate
     * @return string HTML table markup
     */
    public static function staysLog(\PDO $dbh, $startDate, $endDate, $idPsg = 0) {

        if ($idPsg > 0) {
            $stmt = $dbh->query("select * from vstays_log sl where sl.idName in (select idName from name_guest where idPsg = $idPsg);");
        } else {
            $query = "select * from vstays_log where (DATE(Timestamp) >= :start and DATE(Timestamp) <= :end);";

            $stmt = $dbh->prepare($query);
            $stmt->execute(array(':start' => $startDate, ':end' => $endDate));
        }

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $visitId = 0;
        $spanId = 0;
        $visitIcon = "<span class='ui-icon ui-icon-folder-open' style='float: left; margin-right: .3em;' title='Open Visit Viewer'></span>";

        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh("Room") . HTMLTable::makeTh(Labels::getString('MemberType', 'visitor', 'Guest')) . HTMLTable::makeTh("Action") . HTMLTable::makeTh("Date") . HTMLTable::makeTh("By"));


        foreach ($rows as $r) {


            $logData = array();

            if ($r['Log_Text'] != '') {

                $decod = json_decode($r['Log_Text']);

                foreach ($decod as $k => $v) {

                    // remove ticks from labels
                    $key = str_ireplace('`', '', $k);

                    if (stristr($v, '|_|') !== FALSE) {
                        $parts = explode('|', $v);
                        if (count($parts) == 3) {
                            $logData[$key] = array('old' => $parts[0], 'new' => $parts[2]);
                        }
                    } else {
                        $logData[$key] = array('new' => $v);
                    }
                }
            } else {
                continue;
            }

            if ($r['idVisit'] != $visitId) {
                $visitId = $r['idVisit'];
                $spanId = $r['Span'];
                if ($spanId == 0) {
                    $tbl->addBodyTr(HTMLTable::makeTd(
                                    HTMLContainer::generateMarkup('span', 'Visit Id: ' . $visitId . '-' . ($spanId) . $visitIcon, array('class' => 'hhk-viewvisit', 'data-visitid' => $visitId . '_' . $spanId)), array('colspan' => '7', 'style' => 'background-color: lightgrey;')));
                } else {
                    // Catch it on the Span check below
                    $spanId = 0;
                }
            }

            if ($r['Span'] != $spanId) {
                $spanId = $r['Span'];
                $tbl->addBodyTr(HTMLTable::makeTd(
                                HTMLContainer::generateMarkup('span', 'Visit Id: ' . $visitId . '-' . ($spanId) . $visitIcon, array('class' => 'hhk-viewvisit', 'data-visitid' => $visitId . '_' . $spanId)), array('colspan' => '7', 'style' => 'background-color:#EFEFEF;')));
            }

            $trow = HTMLTable::makeTd($r['Title']);

            if (isset($logData['Status'])) {

                $trow .= HTMLTable::makeTd(HTMLContainer::generateMarkup('a', $r['Name_First'] . " " . $r['Name_Last'], array('href' => 'GuestEdit.php?id=' . $r['idName'] . ($r['idPsg'] == 0 ? '' : '&psg=' . $r['idPsg']))));

                if ($logData['Status']['new'] == VisitStatus::NewSpan) {
                    // Changed rooms
                    $trow .= HTMLTable::makeTd('Move from room') . HTMLTable::makeTd(date('M j, Y H:i:s', strtotime($logData['Span_End_Date']['new'])));
                } else if ($logData['Status']['new'] == VisitStatus::ChangeRate) {
                    // Changed rooms
                    $trow .= HTMLTable::makeTd('Rate Change') . HTMLTable::makeTd(date('M j, Y H:i:s', strtotime($logData['Span_End_Date']['new'])));
                } else if ($logData['Status']['new'] == VisitStatus::CheckedIn) {

                    if (isset($logData['Visit_Span']) && isset($logData['Checkin_Date']['new'])) {
                        // changed rooms
                        $trow .= HTMLTable::makeTd('Move to room') . HTMLTable::makeTd(date('M j, Y H:i:s', strtotime($logData['Checkin_Date']['new'])));
                    } else if (isset($logData['Checkin_Date']['new'])) {
                        // Checking in
                        $trow .= HTMLTable::makeTd('Check In') . HTMLTable::makeTd(date('M j, Y H:i:s', strtotime($logData['Checkin_Date']['new'])));
                    } else {
                        // Undo check out
                        $trow .= HTMLTable::makeTd('Undo Check Out') . HTMLTable::makeTd(date('M j, Y H:i:s', strtotime($r['Timestamp'])));
                    }
                } else if ($logData['Status']['new'] == VisitStatus::CheckedOut && isset($logData['Span_End_Date']['new'])) {
                    // Checking out
                    $trow .= HTMLTable::makeTd('Check Out') . HTMLTable::makeTd(date('M j, Y H:i:s', strtotime($logData['Span_End_Date']['new'])));
                }
            } else {
                // something other than status changed
                $actiontbl = new HTMLTable();
                $actiontbl->addHeaderTr(HTMLTable::makeTh('Field') . HTMLTable::makeTh('Was') . HTMLTable::makeTh('Now'));
                foreach ($logData as $k => $log) {
                    $actiontbl->addBodyTr(HTMLTable::makeTd($k) . HTMLTable::makeTd((isset($log['old']) ? $log['old'] : '')) . HTMLTable::makeTd($log['new']));
                }
                $trow .= HTMLTable::makeTd($actiontbl->generateMarkup(), array('colspan' => '2'));

                $trow .= HTMLTable::makeTd(date('M j, Y H:i:s', strtotime($r['Timestamp'])));
            }

            $trow .= HTMLTable::makeTd($r['User_Name']);


            $tbl->addBodyTr($trow);
        }

        return $tbl->generateMarkup(array(), '<h3>Visit Activity</h3>');
    }

    /**
     * Summary of reservLog
     * @param \PDO $dbh
     * @param string $startDate
     * @param string $endDate
     * @param mixed $resvId
     * @param int $idPsg
     * @return string
     */
    public static function reservLog(\PDO $dbh, $startDate, $endDate, $resvId = 0, $idPsg = 0) {

        $uS = Session::getInstance();

        if ($resvId > 0) {

            $idResv = intval($resvId, 10);
            $stmt = $dbh->query("select * from vreservation_log where idReservation = $idResv;");

        } else if ($idPsg > 0) {

            $stmt = $dbh->query("select * from vreservation_log sl where sl.idName in (select idName from name_guest where idPsg = $idPsg);");

        } else {

            $query = "select * from vreservation_log where (DATE(Timestamp) >= :start and DATE(Timestamp) <= :end);";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(':start' => $startDate, ':end' => $endDate));
        }

        $reservId = 0;
        $reservIcon = "<span class='ui-icon ui-icon-folder-open' style='float: left; margin-right: .3em;' title='Open Reservtion Viewer'></span>";
        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh("Room") . HTMLTable::makeTh(Labels::getString('MemberType', 'visitor', 'Guest')) . HTMLTable::makeTh("Action") . HTMLTable::makeTh("Date") . HTMLTable::makeTh("By"));

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $logData = array();

            if ($r['Log_Text'] != '') {

                $decod = json_decode($r['Log_Text']);

                foreach ($decod as $k => $v) {

                    // remove ticks from labels
                    $key = str_ireplace('`', '', $k);

                    if (stristr($v, '|_|') !== FALSE) {

                        $parts = explode('|', $v);
                        if (count($parts) == 3) {
                            $logData[$key] = array('old' => $parts[0], 'new' => $parts[2]);
                        }
                    } else {
                        $logData[$key] = array('old' => '', 'new' => $v);
                    }
                }
            } else {
                continue;
            }

            if ($r['idReservation'] != $reservId) {
                $reservId = $r['idReservation'];
                $tbl->addBodyTr(HTMLTable::makeTd(
                		HTMLContainer::generateMarkup('span', 'Reservation Id: ' . $reservId . $reservIcon, array('class' => 'hhk-viewvisit', 'data-reservid' => $r['idReservation'])), array('colspan' => '7', 'style' => 'background-color: lightgrey;')));
            }

            $trow = HTMLTable::makeTd($r['Title']);

            if (isset($logData['Status']) && $r['Sub_Type'] == 'update') {

                $trow .= HTMLTable::makeTd(HTMLContainer::generateMarkup('a', $r['Name_First'] . " " . $r['Name_Last'], array('href' =>'Reserve.php' . '?id=' . $r['idName'])));

                if (isset($uS->guestLookups['ReservStatus'][$logData['Status']['new']])) {
                    $statTitle = $uS->guestLookups['ReservStatus'][$logData['Status']['new']][1];
                } else {
                    $statTitle = '';
                }

                if ($logData['Status']['new'] == ReservationStatus::Staying && $logData['Status']['old'] == ReservationStatus::Checkedout) {
                    $trow .= HTMLTable::makeTd('Undo Checkout, ' . $statTitle) . HTMLTable::makeTd(date('M j, Y', strtotime($r['Timestamp'])));
                } else {
                    $trow .= HTMLTable::makeTd($statTitle) . HTMLTable::makeTd(date('M j, Y', strtotime($r['Timestamp'])));
                }
            } else {
                // something other than status changed
                $actiontbl = new HTMLTable();
                $actiontbl->addHeaderTr(HTMLTable::makeTh('Field') . HTMLTable::makeTh('Was') . HTMLTable::makeTh('Now'));
                foreach ($logData as $k => $log) {
                    $actiontbl->addBodyTr(HTMLTable::makeTd($k) . HTMLTable::makeTd($log['old']) . HTMLTable::makeTd($log['new']));
                }
                $trow .= HTMLTable::makeTd($actiontbl->generateMarkup(), array('colspan' => '2'));

                $trow .= HTMLTable::makeTd(date('M j, Y H:i', strtotime($r['Timestamp'])));
            }

            $trow .= HTMLTable::makeTd($r['User_Name']);


            $tbl->addBodyTr($trow);
        }

        return $tbl->generateMarkup(array(), '<h3>Reservation Activity</h3>');
    }

    /**
     * Summary of HospStayLog
     * @param \PDO $dbh
     * @param string $startDate
     * @param string $endDate
     * @param int $idPsg
     * @return string
     */
    public static function HospStayLog(\PDO $dbh, $startDate, $endDate, $idPsg = 0) {

        $uS = Session::getInstance();
        $labels = Labels::getLabels();
        $idP = intval($idPsg, 10);

        if ($idP > 0) {

            $stmt = $dbh->query("select * from vhospitalstay_log where idPsg = $idP;");

        } else if ($startDate != '' && $endDate != '') {

            $query = "select * from vhospitalstay_log where (DATE(Timestamp) >= :start and DATE(Timestamp) <= :end) order by idPsg, Timestamp;";
            $stmt = $dbh->prepare($query);
            $stmt->execute(array(':start' => $startDate, ':end' => $endDate));

        } else {
            return 'Error- Missing dates and idPsg.  ';
        }


        $diagnoses = readGenLookupsPDO($dbh, 'Diagnosis');
        $locations = readGenLookupsPDO($dbh, 'Location');
        $psgId = 0;

        $stmtd = $dbh->query("select n.idName, n.Name_Full from `name` n join name_volunteer2 nv on n.idName = nv.idName where nv.Vol_Category = 'Vol_Type' and nv.Vol_Code = 'doc'");
        $doctors = array();
        while ($d = $stmtd->fetch(\PDO::FETCH_ASSOC)) {
            $doctors[$d['idName']] = $d['Name_Full'];
        }

        $stmtra = $dbh->query("select n.idName, n.Name_Full from `name` n join name_volunteer2 nv on n.idName = nv.idName where nv.Vol_Category = 'Vol_Type' and nv.Vol_Code = 'ra'");
        $referralAgents = array();
        while ($ra = $stmtra->fetch(\PDO::FETCH_ASSOC)) {
            $referralAgents[$ra['idName']] = $ra['Name_Full'];
        }

        $reservIcon = "<span class='ui-icon ui-icon-folder-open' style='float: left; margin-right: .3em;' title='Open " . $labels->getString('MemberType', 'patient', 'Patient') . " Edit page'></span>";

        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh("Action") . HTMLTable::makeTh("Date") . HTMLTable::makeTh("By"));

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $logData = array();

            if ($r['Log_Text'] == '') {
                continue;
            }

            $decod = json_decode($r['Log_Text']);

            foreach ($decod as $k => $v) {

                // remove ticks from labels
                $key = str_ireplace('`', '', $k);

                if (stristr($v, '|_|') !== FALSE) {

                    $parts = explode('|', $v);
                    if (count($parts) == 3) {

                        if ($key == 'Diagnosis') {
                            if (isset($diagnoses[$parts[0]])) {
                                $parts[0] = $diagnoses[$parts[0]][1];
                            }
                            if (isset($diagnoses[$parts[2]])) {
                                $parts[2] = $diagnoses[$parts[2]][1];
                            }
                        }

                        if ($key == 'Location') {
                            if (isset($locations[$parts[0]])) {
                                $parts[0] = $locations[$parts[0]][1];
                            }
                            if (isset($locations[$parts[2]])) {
                                $parts[2] = $locations[$parts[2]][1];
                            }
                        }

                        $logData[$key] = array('old' => $parts[0], 'new' => $parts[2]);
                    }
                } else {
                    if ($key == 'Diagnosis' && isset($diagnoses[$v])) {
                        $v = $diagnoses[$v][1];
                    }

                    if ($key == 'Location' && isset($locations[$v])) {
                        $v = $locations[$v][1];
                    }

                    $logData[$key] = array('old' => '', 'new' => $v);
                }
            }


            if ($r['idPsg'] != $psgId) {
                $psgId = $r['idPsg'];
                $tbl->addBodyTr(HTMLTable::makeTd(
                                HTMLContainer::generateMarkup('span', 'PSG Id: ' . $psgId . $reservIcon
                                        . HTMLContainer::generateMarkup('a', $labels->getString('MemberType', 'patient', 'Patient') . ': ' . $r['Name_First'] . " " . $r['Name_Last'], array('href' => 'GuestEdit.php?id=' . $r['idName'], 'style' => 'margin-left:1em;')), array('class' => 'hhk-viewvisit', 'data-patientid' => $r['idName'])), array('colspan' => '7', 'style' => 'background-color: lightgrey;')));
            }

            $trow = '';

            $actiontbl = new HTMLTable();
            $actiontbl->addHeaderTr(HTMLTable::makeTh('Field') . HTMLTable::makeTh('Was') . HTMLTable::makeTh('Now'));
            foreach ($logData as $k => $log) {

                if ($log['old'] == '0') {
                    $log['old'] = '';
                }
                if ($log['new'] == '0') {
                    $log['new'] = '';
                }

                switch ($k) {
                    case 'idHospital':
                        if (isset($uS->guestLookups[GLTableNames::Hospital][$log['old']])) {
                            $log['old'] = $uS->guestLookups[GLTableNames::Hospital][$log['old']][1];
                        }
                        if (isset($uS->guestLookups[GLTableNames::Hospital][$log['new']])) {
                            $log['new'] = $uS->guestLookups[GLTableNames::Hospital][$log['new']][1];
                        }

                        break;

                    case 'idAssociation':
                        if (isset($uS->guestLookups[GLTableNames::Hospital][$log['old']])) {
                            $log['old'] = $uS->guestLookups[GLTableNames::Hospital][$log['old']][1] != '(None)' ? $uS->guestLookups[GLTableNames::Hospital][$log['old']][1] : '';
                        }
                        if (isset($uS->guestLookups[GLTableNames::Hospital][$log['new']])) {
                            $log['new'] = $uS->guestLookups[GLTableNames::Hospital][$log['new']][1] != '(None)' ? $uS->guestLookups[GLTableNames::Hospital][$log['new']][1] : '';
                        }

                        break;

                    case 'idReferralAgent':
                        if (isset($referralAgents[$log['old']])) {
                            $log['old'] = $referralAgents[$log['old']];
                        }
                        if (isset($referralAgents[$log['new']])) {
                            $log['new'] = $referralAgents[$log['new']];
                        }
                        break;

                    case 'idDoctor':
                        if (isset($doctors[$log['old']])) {
                            $log['old'] = $doctors[$log['old']];
                        }
                        if (isset($doctors[$log['new']])) {
                            $log['new'] = $doctors[$log['new']];
                        }
                        break;

                    case 'Arrival_Date':

                        if ($log['old'] != '') {
                            $log['old'] = date('M j, Y', strtotime($log['old']));
                        }
                        if ($log['new'] != '') {
                            $log['new'] = date('M j, Y', strtotime($log['new']));
                        }

                        break;

                    case 'Expected_Departure':

                        if ($log['old'] != '') {
                            $log['old'] = date('M j, Y', strtotime($log['old']));
                        }
                        if ($log['new'] != '') {
                            $log['new'] = date('M j, Y', strtotime($log['new']));
                        }

                        break;

                    case 'Last_Updated':

                        if ($log['old'] != '') {
                            $log['old'] = date('M j, Y H:i', strtotime($log['old']));
                        }
                        if ($log['new'] != '') {
                            $log['new'] = date('M j, Y H:i', strtotime($log['new']));
                        }

                        break;
                }

                $actiontbl->addBodyTr(HTMLTable::makeTd($k) . HTMLTable::makeTd($log['old']) . HTMLTable::makeTd($log['new']));
            }


            $trow .= HTMLTable::makeTd($actiontbl->generateMarkup());

            $trow .= HTMLTable::makeTd(date('M j, Y H:i', strtotime($r['Timestamp'])));


            $trow .= HTMLTable::makeTd($r['User_Name']);

            $tbl->addBodyTr($trow);
        }

        return $tbl->generateMarkup(array(), '<h3>' . $labels->getString('hospital', 'hospital', 'Hospital') . ' Stay Activity</h3>');
    }

    /**
     * Summary of feesLog
     * @param \PDO $dbh
     * @param \DateTime|null $startDT
     * @param \DateTime|null $endDT
     * @param array $feeStatuses
     * @param array $selectedPayTypes
     * @param int $idReg
     * @param string $title
     * @param bool $showDeletedInv
     * @return string
     */
    public static function feesLog(\PDO $dbh, $startDT, $endDT, $feeStatuses, $selectedPayTypes, $idReg, $title = 'Recent Payments Report', $showDeletedInv = TRUE) {

        $uS = Session::getInstance();
        $whStatus = '';
        $whType = '';
        $whId = '';
        $whDates = '';
        $totals = [];
        $payStatusText = '';
        $payTypeTotals = [];
        $payTypeText = '';
        $showExternlId = FALSE;

        $labels = Labels::getLabels();

        // The following section doesnt make sense.
        // $config = $_ENV;

        // // Show the external payment ID for houses with CMS integration
        // if(!empty($config['Service_Name'])) {
        //     $showExternlId = TRUE;
        // }

        $gateway = AbstractPaymentGateway::factory($dbh, $uS->PaymentGateway, AbstractPaymentGateway::getCreditGatewayNames($dbh, 0, 0, 0));

        // Dates
        if ($startDT != NULL) {
            $whDates .= " and (CASE WHEN lp.Payment_Last_Updated = '' THEN DATE(lp.Payment_Date) ELSE DATE(lp.Payment_Last_Updated) END) >= DATE('" . $startDT->format('Y-m-d') . "') ";
        }

        if ($endDT != NULL) {
            $whDates .= " and (CASE WHEN lp.Payment_Last_Updated = '' THEN DATE(lp.Payment_Date) ELSE DATE(lp.Payment_Last_Updated) END) <= DATE('" . $endDT->format('Y-m-d') . "') ";
        }

        // Set up status totals array
        if (count($feeStatuses) === 1 && $feeStatuses[0] == '') {
            $active = 'y';
        } else {
            $active = 'n';
        }

        foreach (readGenLookupsPDO($dbh, 'Payment_Status') as $s) {
            $totals[$s[0]] = ['amount' => 0.00, 'count' => 0, 'title' => $s[1], 'active' => $active];
        }

        $rtnIncluded = FALSE;

        foreach ($feeStatuses as $s) {

            if ($s != '') {

                // Set up query where part.
                if ($whStatus == '') {
                    $whStatus = "'" . $s . "'";
                } else {
                    $whStatus .= ",'" . $s . "'";
                }

                if ($s == PaymentStatusCode::Retrn) {
                    $rtnIncluded = TRUE;
                }

                $totals[$s]['active'] = 'y';

                if ($payStatusText == '') {
                    $payStatusText .= $totals[$s]['title'];
                } else {
                    $payStatusText .= ', ' . $totals[$s]['title'];
                }
            }
        }

        if ($whStatus != '') {

            if ($rtnIncluded) {
                $whStatus = " and (`lp`.`Payment_Status` in (" . $whStatus . ") or (`lp`.`Is_Refund` = 1 && `lp`.`Payment_Status` = '" . PaymentStatusCode::Paid . "')) ";
            } else {
                $whStatus = " and `lp`.`Payment_Status` in (" . $whStatus . ") ";
            }
        }


        // Set up pay tpes totals array
        if (count($selectedPayTypes) === 1 && $selectedPayTypes[0] == '') {
            $active = 'y';
        } else {
            $active = 'n';
        }

        // get payment methods
        $stmtp = $dbh->query("select * from payment_method");
        while ($t = $stmtp->fetch(\PDO::FETCH_NUM)) {
            if ($t[0] > 0 && strtolower($t[1]) != 'chgascash') {
                $payTypeTotals[$t[0]] = ['amount' => 0.00, 'count' => 0, 'title' => $t[1], 'active' => $active];
            }
        }


        foreach ($selectedPayTypes as $s) {

            if ($s != '') {
                // Set up query where part.
                if ($whType == '') {
                    $whType = $s;
                } else {
                    $whType .= ",$s";
                }

                $payTypeTotals[$s]['active'] = 'y';
                if ($payTypeText == '') {
                    $payTypeText .= $payTypeTotals[$s]['title'];
                } else {
                    $payTypeText .= ', ' . $payTypeTotals[$s]['title'];
                }
            }
        }

        if ($whType != '') {
            $whType = " and `lp`.`idPayment_Method` in ($whType) ";
        }

        // Guest id selector
        if ($idReg > 0) {
            $whId = " and `lp`.`idGroup` = $idReg ";
        }

        if ($showDeletedInv === FALSE) {
            $whId .= " and `lp`.`Deleted` = 0 ";
        }

        $query = "Select
    lp.*,
    ifnull(`n`.`Name_First`, '') as `First`,
    ifnull(`n`.`Name_Last`, '') as `Last`,
    ifnull(`n`.`Company`, '') as `Company`,
    ifnull(`r`.`Title`, '') as `Room`,
    ifnull(`re`.`idPsg`, 0) as `idPsg`,
    `v`.`idVisit`,
    `v`.`Span`
from
    `vlist_inv_pments` `lp`
        left join
    `name` `n` ON `lp`.`Sold_To_Id` = `n`.`idName`
        left join
    `visit` `v` on `lp`.`Order_Number` = `v`.`idVisit` and `lp`.`Suborder_Number` = `v`.`Span`
	left join
    `resource` `r` ON `v`.`idResource` = `r`.`idResource`
        left join
    `registration` `re` on `v`.`idRegistration` = `re`.`idRegistration`
where `lp`.`idPayment` > 0
 $whDates $whStatus $whType $whId Order By lp.idInvoice;";

        $stmt = $dbh->query($query);
        $invoices = Statement::processPayments($stmt, ['First', 'Last', 'Company', 'Room', 'idPsg', 'idVisit', 'Span', 'Invoice_Created_At']);

        $rowCount = $stmt->rowCount();

        // Create header
        $hdrTbl = new HTMLTable();
        $hdrTbl->addBodyTr(HTMLTable::makeTh('Records:', ['style' => 'text-align:right;']) . HTMLTable::makeTd($rowCount));

        if ($startDT != NULL && $endDT != NULL) {
            $hdrTbl->addBodyTr(HTMLTable::makeTh('Dates:', ['style' => 'text-align:right;']) . HTMLTable::makeTd($startDT->format('M j, Y') . ' to ' . $endDT->format('M j, Y')));
        }

        $hdrTbl->addBodyTr(HTMLTable::makeTh('Payment Statuses:', ['style' => 'text-align:right;']) . HTMLTable::makeTd($payStatusText == '' ? 'All' : $payStatusText));
        $hdrTbl->addBodyTr(HTMLTable::makeTh('Payment Types:', ['style' => 'text-align:right;']) . HTMLTable::makeTd($payTypeText == '' ? 'All' : $payTypeText));

        $header = HTMLContainer::generateMarkup('h2', $title, ['class' => 'ui-widget-header']) . $hdrTbl->generateMarkup(['style' => 'margin-bottom:10px;float:left; ']);

        // Main fees listing table
        $tbl = new HTMLTable();
        $tbl->addHeaderTr(
            HTMLTable::makeTh("Visit")
                . HTMLTable::makeTh("Room")
                . HTMLTable::makeTh("Payor")
                . HTMLTable::makeTh("Invoice")
                . HTMLTable::makeTh("Pay Type")
                . HTMLTable::makeTh("Detail")
                . HTMLTable::makeTh("Status")
                . HTMLTable::makeTh($labels->getString('statement', 'paymentHeader', 'Payment'))
                . HTMLTable::makeTh("Action")
                . HTMLTable::makeTh("Date")
                . HTMLTable::makeTh("Updated")
                . HTMLTable::makeTh("By")
                . HTMLTable::makeTh("Created")
                . ($showExternlId ? HTMLTable::makeTh("Ext. Id") : '')
                . HTMLTable::makeTh('Notes'));


        foreach ($invoices as $i) {

            $r = $i['i'];

            $invNumber = $r['Invoice_Number'];

            // Invoice number
            if ($invNumber != '') {

                $iAttr = ['href' => 'ShowInvoice.php?invnum=' . $r['Invoice_Number'], 'style' => 'float:left;', 'target' => '_blank'];

                if ($r['Invoice_Deleted'] > 0) {
                    $iAttr['style'] = 'color:red;';
                    $iAttr['title'] = 'Invoice is Deleted.';

                } else if ($r['Invoice_Status'] != InvoiceStatus::Paid && $r['Invoice_Amount'] - $r['Invoice_Balance'] != 0) {

                    $iAttr['title'] = 'Partial payment.';
                    $invNumber .= HTMLContainer::generateMarkup('sup', '-p');
                }

                $invNumber = HTMLContainer::generateMarkup('a', $invNumber, $iAttr)
                        . HTMLContainer::generateMarkup('span', '', ['class' => 'ui-icon ui-icon-comment invAction', 'id' => 'invicon' . $r['idInvoice'], 'data-iid' => $r['idInvoice'], 'style' => 'cursor:pointer;', 'title' => 'View Items']);
            }

            $invoiceMkup = HTMLContainer::generateMarkup('span', $invNumber, ["style" => 'white-space:nowrap']);

            // Set up actions
            foreach ($i['p'] as $p) {

                $stat = '';
                $attr['style'] = 'text-align:right;';

                $amt = $p['Payment_Amount'];

                $dateDT = new \DateTime($p['Payment_Date']);

                $updatedDateString = '';
                if ($p['Last_Updated'] !== '') {
                	$lastUpdatedDT = new \DateTime($p['Last_Updated']);
                	$updatedDateString = $lastUpdatedDT->format('c');
                }

                // Action column.
                $actionContent = PostPayment::actionButton($gateway, $p, $r['Invoice_Status'], $payTypeTotals, $stat, $amt, $attr);


                $payTypeTitle = $p['Payment_Method_Title'];
                if ($p['idPayment_Method'] == PaymentMethod::Charge) {
                    $payTypeTitle = 'Credit Card';
                    $attr['readonly'] = 'readonly';
                }

                // Overwrites some of the above
                if ($r['Sold_To_Id'] == $uS->subsidyId) {
                    // House Subsidy
                    $payTypeTitle = $labels->getString('statement', 'houseSubsidy', 'House Discount');
                    $nameTd = $r['Company'];
                } else if ($r['Bill_Agent'] == 'a') {
                    // 3rd Party
                    $nameTd = $r['Company'];

                    if ($r['Last'] != '') {

                        if ($r['Company'] != '') {
                            $nameTd = $r['Company'] . ' c/o ';
                        }

                        $nameTd .= $r['First'] . " " . $r['Last'];
                    }
                } else {
                    $nameTd = HTMLContainer::generateMarkup('a', $r['First'] . " " . $r['Last'], array('href' => 'GuestEdit.php?id=' . $r['Sold_To_Id'] . '&psg=' . $r['idPsg']));
                }

                // Set up the payment detail column.
                $payDetail = '';
                if ($p['idPayment_Method'] == PaymentMethod::Charge) {

                    if (isset($p['auths'])) {

                        foreach ($p['auths'] as $a) {

                            if ($a['Card_Type'] != '') {
                                $payDetail = $a['Card_Type'] . ' - ' . $a['Masked_Account'];
                            }

                            IF ($a['Auth_Last_Updated'] !== '') {
                            	$lastUpdatedDT = new \DateTime($a['Auth_Last_Updated']);

                            	if ($lastUpdatedDT != $dateDT) {
                            		$updatedDateString = $lastUpdatedDT->format('c');
                            	}
                            }
                        }
                    }
                } else if ($p['idPayment_Method'] == PaymentMethod::Check || $p['idPayment_Method'] == PaymentMethod::Transfer) {

                    $payDetail = $p['Check_Number'];
                }


                $trow = HTMLTable::makeTd((isset($r["idVisit"]) && isset($r["Span"]) ? $r["idVisit"] ."-" . $r["Span"] : ""));
                $trow .= HTMLTable::makeTd($r['Room']);
                $trow .= HTMLTable::makeTd($nameTd);
                $trow .= HTMLTable::makeTd($invoiceMkup);
                $trow .= HTMLTable::makeTd($payTypeTitle);
                $trow .= HTMLTable::makeTd($payDetail);
                $trow .= HTMLTable::makeTd($stat);
                $trow .= HTMLTable::makeTd(number_format($amt, 2), $attr);
                $trow .= HTMLTable::makeTd($actionContent);
                $trow .= HTMLTable::makeTd($dateDT->format('c'));
                $trow .= HTMLTable::makeTd($updatedDateString);
                $trow .= HTMLTable::makeTd($p['Payment_Updated_By'] == '' ? $p['Payment_Created_By'] : $p['Payment_Updated_By']);
                $trow .= HTMLTable::makeTd($r['Invoice_Created_At']);

                if ($showExternlId) {
                    $trow .= HTMLTable::makeTd($p['Payment_External_Id']);
                }
                $trow .= HTMLTable::makeTd($p['Payment_Note']);

                $tbl->addBodyTr($trow);

                $totals[$p['Payment_Status']]['amount'] += $amt;
                $totals[$p['Payment_Status']]['count'] ++;
                $payTypeTotals[$p['idPayment_Method']]['count'] ++;
            }

            // Add House payments
            foreach ($i['h'] as $h) {

                $houseAction = HTMLInput::generateMarkup('Delete', ['type' => 'button', 'id' => 'btndelwaive' . $h['id'], 'class' => 'hhk-deleteWaive', 'style' => 'font-size: 0.8em', 'data-ilid' => $h['id'], 'data-iid' => $r['idInvoice']]);

                $tbl->addBodyTr(
                    HTMLTable::makeTd((isset($r["idVisit"]) && isset($r["Span"]) ? $r["idVisit"] ."-" . $r["Span"] : ""))
                    . HTMLTable::makeTd($r['Room'])
                    . HTMLTable::makeTd($uS->siteName)
                    . HTMLTable::makeTd($invoiceMkup)
                    . HTMLTable::makeTd(str_replace(';', '', $h['Desc']))
                    . HTMLTable::makeTd('')
                    . HTMLTable::makeTd('')
                    . HTMLTable::makeTd(number_format(abs($h['Amount']), 2), ['style' => 'text-align:right;color:gray;'])
                    . HTMLTable::makeTd($houseAction)
                    . HTMLTable::makeTd(date('c', strtotime($r['Invoice_Date'])))
                    . HTMLTable::makeTd('')
                    . HTMLTable::makeTd($r['Invoice_Updated_By'])
                    . HTMLTable::makeTd($r['Invoice_Created_At'])
                    . ($showExternlId ? HTMLTable::makeTd('') : '')
                    . HTMLTable::makeTd('')
                );
            }
        }

        $listing = $tbl->generateMarkup(['id' => 'feesTable', 'style' => 'margin-top:10px;']);

        $summary = '';

        if ($rowCount > 0) {

            $summ = new HTMLTable();
            $summ->addBodyTr(HTMLTable::makeTh('Payment Status', ['colspan' => '3']));

            foreach ($totals as $t) {

                if ($t['active'] != 'y') {
                    continue;
                }

                $summ->addBodyTr(HTMLTable::makeTd($t['title']) . HTMLTable::makeTd($t['count']) . HTMLTable::makeTd(number_format($t['amount'], 2), ['style' => 'text-align:right;']));
            }

            // Payment type block list
            $pType = new HTMLTable();
            $pType->addBodyTr(HTMLTable::makeTh('Payment Type', ['colspan' => '3']));

            // Payment type totals
            foreach ($payTypeTotals as $pt) {

                if ($pt['active'] == 'y') {
                    $pType->addBodyTr(HTMLTable::makeTd($pt['title']) . HTMLTable::makeTd($pt['count']) . HTMLTable::makeTd(number_format($pt['amount'], 2), ['style' => 'text-align:right;']));
                }

            }


            $summary = HTMLContainer::generateMarkup('div', $summ->generateMarkup(), ['style' => 'float:left; margin-left:.8em;']);
            $summary .= HTMLContainer::generateMarkup('div', $pType->generateMarkup(), ['style' => 'float:left; margin-left:.8em;']);
        }

        $refresh = HTMLInput::generateMarkup("Refresh", ['type' => 'button', 'id' => 'btnPayHistRef', 'style' => 'float:right; margin-right:0.8em;margin-top:1em;']);

        return HTMLContainer::generateMarkup('div', "$header$summary$refresh$listing", ['style' => 'min-width:900px;']);
    }

    /**
     * Summary of unpaidInvoiceLog
     * @param \PDO $dbh
     * @param bool $includeAction
     * @return string
     */
    public static function unpaidInvoiceLog(\PDO $dbh, $includeAction = TRUE) {

        // Get labels
        $labels = Labels::getLabels();

        $uS = Session::getInstance();

        $query = "SELECT
`i`.`idInvoice`,
`i`.`Delegated_Invoice_Id`,
`i`.`Invoice_Number`,
`i`.`Amount`,
`i`.`Carried_Amount`,
`i`.`Balance`,
`i`.`Order_Number`,
`i`.`Suborder_Number`,
`i`.`Sold_To_Id`,
n.Name_Full as Sold_To_Name,
n.Company,
ifnull(np.Name_Full, '') as Patient_Name,
ifnull(re.Title, '') as `Title`,
ifnull(hs.idHospital, 0) as `idHospital`,
ifnull(hs.idAssociation, 0) as `idAssociation`,
ifnull(hs.idPatient, 0) as `idPatient`,
`i`.`idGroup`,
`i`.`Invoice_Date`,
`i`.`Payment_Attempts`,
i.BillStatus,
i.BillDate,
i.EmailDate,
i.Notes,
`i`.`Status`,
`i`.`Updated_By`,
`i`.`Last_Updated`
FROM `invoice` `i` left join `name` n on i.Sold_To_Id = n.idName
    left join visit v on i.Order_Number = v.idVisit and i.Suborder_Number = v.Span
    left join hospital_stay hs on hs.idHospital_stay = v.idHospital_stay
    left join name np on hs.idPatient = np.idName
    left join resource re on v.idResource = re.idResource
where i.Deleted = 0 and i.`Status` = '" . InvoiceStatus::Unpaid . "';";

        $tbl = new HTMLTable();
        $tbl->addHeaderTr(
            HTMLTable::makeTh('Action')
            . HTMLTable::makeTh('Invoice')
            . HTMLTable::makeTh('Date')
            . HTMLTable::makeTh('Status')
            . HTMLTable::makeTh('Bill Date')
            . HTMLTable::makeTh('Email Date')
            . HTMLTable::makeTh('Payor')
            . HTMLTable::makeTh('Room')
            . HTMLTable::makeTh($labels->getString('hospital', 'hospital', 'Hospital'))
            . HTMLTable::makeTh($labels->getString('MemberType', 'patient', 'Patient'))
            . HTMLTable::makeTh('Amount')
            . HTMLTable::makeTh('Payments')
            . HTMLTable::makeTh('Balance')
            . HTMLTable::makeTh('Notes')
        );

        $invStatuses = readGenLookupsPDO($dbh, 'Invoice_Status');

        $stmt = $dbh->query($query);

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // Hospital
            $hospital = '';

            if ($r['idAssociation'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']]) && $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] != '(None)') {
                $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] . ' / ';
            }

            if ($r['idHospital'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idHospital']])) {
                $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idHospital']][1];
            }

            $statusTxt = '';
            if (isset($invStatuses[$r['Status']])) {
                $statusTxt = $invStatuses[$r['Status']][1];
            }



            $payor = $r['Company'];
            if ($r['Sold_To_Name'] != '') {

                if ($r['Company'] != '') {
                    $payor = $r['Company'] . ' c/o ';
                }

                $payor .= $r['Sold_To_Name'];
            }


            $patient = $r['Patient_Name'];
            if ($r['idPatient'] > 0) {
                $patient = HTMLContainer::generateMarkup('a', $patient, array('href' => 'GuestEdit.php?id=' . $r['idPatient']));
            }


            $invNumber = $r['Invoice_Number'];

            if ($invNumber != '') {

                $iAttr = array('href' => 'ShowInvoice.php?invnum=' . $r['Invoice_Number'], 'style' => 'float:left;', 'target' => '_blank');

                if ($r['Amount'] - $r['Balance'] != 0) {

                    $iAttr['title'] = 'Partial payment.';
                    $invNumber .= HTMLContainer::generateMarkup('sup', '-p');
                }

                $invNumber = HTMLContainer::generateMarkup('a', $invNumber, $iAttr)
                        . HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-comment invAction', 'id' => 'invicon3' . $r['idInvoice'], 'data-stat' => 'view', 'data-iid' => $r['idInvoice'], 'style' => 'cursor:pointer;', 'title' => 'View Items'));
            }

            $invoiceMkup = HTMLContainer::generateMarkup('span', $invNumber, array("style" => 'white-space:nowrap'));

            $payments = number_format(($r['Amount'] - $r['Balance']), 2);
            if (($r['Amount'] - $r['Balance']) != 0) {
                $payments = HTMLContainer::generateMarkup('span', $payments, array('style' => 'float:right;'))
                        . HTMLContainer::generateMarkup('span', '', array('class' => 'ui-icon ui-icon-comment invAction', 'id' => 'vwpmt' . $r['idInvoice'], 'data-iid' => $r['idInvoice'], 'data-stat' => 'vpmt', 'style' => 'cursor:pointer;', 'title' => 'View Payments'));
            }

            $dateDT = new \DateTime($r['Invoice_Date']);

            $billDate = ($r['BillDate'] == '' ? '' : date('c', strtotime($r['BillDate'])));
            $emailDate = ($r['EmailDate'] == '' ? '' : (new \DateTime($r["EmailDate"]))->format('M j, Y'));

            $actionTd = '';

            if ($includeAction) {

                $actionTd = HTMLTable::makeTd(HTMLContainer::generateMarkup(
                        'ul', HTMLContainer::generateMarkup('li', 'Action' .
                                HTMLContainer::generateMarkup('ul'
                                    , HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', 'Pay', ['class' => 'invLoadPc', 'data-payor' => $payor, 'data-id' => $r['Sold_To_Id'], 'data-iid' => $r['idInvoice']])) // seperate return JS than the following.
                                    . HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', 'Set Billed', ['id' => 'aidSetNotes' . $r['Invoice_Number'], 'class' => 'invSetBill', 'data-payor' => $payor, 'data-inb' => $r['Invoice_Number']]))
                                    . HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', 'Email Invoice', ['id' => 'ainvem' . $r['idInvoice'], 'class' => 'invAction', 'data-stat' => 'vem', 'data-inb' => $r['Invoice_Number']]))
                                    . ($r['Payment_Attempts'] > 0 ? HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', 'Show Payments', ['id' => 'ainvsp' . $r['idInvoice'], 'class' => 'invAction', 'data-stat' => 'vpmt', 'data-iid' => $r['idInvoice']])) : '')
                                    . HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', 'Delete', ['id' => 'ainv' . $r['idInvoice'], 'class' => 'invAction', 'data-stat' => 'del', 'data-iid' => $r['idInvoice'], 'data-payor' => $payor, 'data-inb' => $r['Invoice_Number']]))
                        )), ['class' => 'gmenu']));
            }


            $tbl->addBodyTr(
                    $actionTd
                    . HTMLTable::makeTd($invoiceMkup, ['style' => 'text-align:center;'])
                    . HTMLTable::makeTd($dateDT->format('c'))
                    . HTMLTable::makeTd($statusTxt)
                    . HTMLTable::makeTd($billDate, array('id' => 'trBillDate' . $r['Invoice_Number']))
                    . HTMLTable::makeTd($emailDate)
                    . HTMLTable::makeTd($payor)
                    . HTMLTable::makeTd($r['Title'], ['style' => 'text-align:center;'])
                    . HTMLTable::makeTd($hospital)
                    . HTMLTable::makeTd($patient)
                    . HTMLTable::makeTd(number_format($r['Amount'], 2), ['style' => 'text-align:right;'])
                    . HTMLTable::makeTd($payments, ['style' => 'text-align:right;'])
                    . HTMLTable::makeTd(number_format($r['Balance'], 2), ['style' => 'text-align:right;'])
                    . HTMLTable::makeTd(HTMLContainer::generateMarkup('div', $r['Notes'], ['id' => 'divInvNotes' . $r['Invoice_Number']]))
            );
        }

        // if ($stmt->rowCount() == 0) {
        //     $tbl->addBodyTr(HTMLTable::makeTd('-No Data-', ['colspan' => '13', 'style' => 'text-align:center;']));
        // }

        return $tbl->generateMarkup(['id' => 'InvTable', 'width' => '100%']);
    }

}
