<?php

namespace HHK;

use HHK\Purchase\RoomRate;
use HHK\SysConst\{WebRole, ReservationStatus, ItemPriceCode, RoomRateCategories, GLTableNames, RoomState};
use HHK\Tables\EditRS;
use HHK\Tables\House\Room_RateRS;
use HHK\sec\Labels;
use HHK\sec\{Session, SecurityComponent};
use HHK\Exception\InvalidArgumentException;
use HHK\HTMLControls\{HTMLTable, HTMLContainer};
use HHK\House\Reservation\Reservation_1;

/**
 * History.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of History
 * @package name
 * @author Eric
 */

class History {

    protected $resvEvents;
    protected $roomRates;
    protected $locations;
    protected $diags;

    public static function addToGuestHistoryList(\PDO $dbh, $id, $role) {
        if ($id > 0 && $role < WebRole::Guest) {
            $query = "INSERT INTO member_history (idName, Guest_Access_Date) VALUES ($id, now())
        ON DUPLICATE KEY UPDATE Guest_Access_Date = now();";
           
            $stmt = $dbh->prepare($query);
            $stmt->execute();
        }
    }

    public static function addToMemberHistoryList(\PDO $dbh, $id, $role) {
        if ($id > 0 && $role < WebRole::Guest) {
            $query = "INSERT INTO member_history (idName, Admin_Access_Date) VALUES ($id, now())
        ON DUPLICATE KEY UPDATE Admin_Access_Date = now();";
            //$query = "replace admin_history (idName, Access_Date) values ($id, now());";
            $stmt = $dbh->prepare($query);
            $stmt->execute();
        }
    }

    public static function getHistoryMarkup(\PDO $dbh, $view, $page) {

        if ($view == "") {
            throw new InvalidArgumentException("Database view name must be defined.");
        }

        $query = "select * from $view";
        $stmt = $dbh->query($query);

        $table = new HTMLTable();
        $table->addHeaderTr(
                $table->makeTh("Id")
                . $table->makeTh("Name")
                . $table->makeTh("Preferred Address")
                . $table->makeTh("Email")
                . $table->makeTh("Phone")
                . $table->makeTh("Company")
                );


        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // Build the address
            $addr = $row["Address_1"];
            $stateComma = ", ";
            $country = '';

            if (trim($row["Address_2"]) != "") {
                $addr .= " " . $row["Address_2"];
            }

            if (trim($row["StateProvince"]) == '') {
                $stateComma = '';
            }

            if ($row['Country_Code'] == 'US') {
                $country = '';
            } else {
                $country = "  " . $row['Country_Code'];
            }

            $addr .= " " . $row["City"] . $stateComma . $row["StateProvince"] . " " . $row["PostalCode"] . $country;


            // Build the page anchor
            if ($page != '') {
                $anchr = HTMLContainer::generateMarkup('a', $row['Id'], array('href'=>"$page?id=" . $row["Id"]));
            } else {
                $anchr = $row["Id"];
            }

            $table->addBodyTr(
                    $table->makeTd($anchr)
                    . $table->makeTd($row["Fullname"])
                    . $table->makeTd(trim($addr))
                    . $table->makeTd($row["Preferred_Email"])
                    . $table->makeTd($row["Preferred_Phone"])
                    . $table->makeTd($row["Company"]));

        }

        return HTMLContainer::generateMarkup("div", $table->generateMarkup(), array('class'=>'hhk-history-list'));
    }

    public static function getGuestHistoryMarkup(\PDO $dbh, $page = "GuestEdit.php") {
        return self::getHistoryMarkup($dbh, "vguest_history_records", $page);
    }
    public static function getMemberHistoryMarkup(\PDO $dbh, $page = "NameEdit.php") {
        return self::getHistoryMarkup($dbh, "vadmin_history_records", $page);
    }


    public function getReservedGuestsMarkup(\PDO $dbh, $status = ReservationStatus::Committed, $includeAction = TRUE, $start = '', $days = 1, $static = FALSE, $orderBy = '') {

        if (is_null($this->roomRates)) {
            $this->roomRates = RoomRate::makeDescriptions($dbh);
        }

        // Reservation page name
        $page = 'Reserve.php';

        $whDate = '';

        if ($start != '') {
            try {
                $startDT = new \DateTime(filter_var($start, FILTER_SANITIZE_STRING));
                $days = intval($days);

                $endDT = new \DateTime(filter_var($start, FILTER_SANITIZE_STRING));
                $endDT->add(new \DateInterval('P' . $days . 'D'));

                $whDate = " and DATE(Expected_Arrival) >= DATE('" . $startDT->format('Y-m-d') . "') and DATE(Expected_Arrival) <= DATE('" . $endDT->format('Y-m-d') . "') ";

            } catch (\Exception $ex) {
                $whDate = '';
            }
        }

        if (is_null($this->resvEvents)) {

            $query = "select * from vreservation_events where Status = '$status' $whDate $orderBy";
            $stmt = $dbh->query($query);
            $this->resvEvents = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        return $this->createMarkup($status, $page, $includeAction, $static);
    }

    protected function makeResvCanceledStatuses($resvStatuses, $idResv) {

        $markup = HTMLContainer::generateMarkup('li', '-------');

        foreach ($resvStatuses as $s) {

            if (Reservation_1::isRemovedStatus($s[0])) {
                $markup .= HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', $s[1], array('class'=>'resvStat', 'data-stat'=>$s[0], 'data-rid'=>$idResv)));
            }
        }

        return $markup;

    }

    protected function createMarkup($status, $page, $includeAction, $static = FALSE) {

        $uS = Session::getInstance();
        // Get labels
        
        $labels = Labels::getLabels();
        $returnRows = array();

        foreach ($this->resvEvents as $r) {

            $fixedRows = array();

            // Action
            if ($includeAction && !$static) {
                $fixedRows['Action'] =  HTMLContainer::generateMarkup(
                    'ul', HTMLContainer::generateMarkup('li', 'Action' .
                        HTMLContainer::generateMarkup('ul',
                           HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', 'View ' . $labels->getString('guestEdit', 'reservationTitle', 'Reservation'), array('href'=>'Reserve.php' . '?rid='.$r['idReservation'], 'style'=>'text-decoration:none;')))
                           . $this->makeResvCanceledStatuses($uS->guestLookups['ReservStatus'], $r['idReservation'])
                           . ($includeAction && ($status == ReservationStatus::Committed || $status == ReservationStatus::UnCommitted) ? HTMLContainer::generateMarkup('li', '-------') . HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', $uS->guestLookups['ReservStatus'][ReservationStatus::Waitlist][1], array('class'=>'resvStat', 'data-stat'=>  ReservationStatus::Waitlist, 'data-rid'=>$r['idReservation']))) : '')
                           . ($includeAction && $status == ReservationStatus::Committed ? HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', $uS->guestLookups['ReservStatus'][ReservationStatus::UnCommitted][1], array('class'=>'resvStat', 'data-stat'=>  ReservationStatus::UnCommitted, 'data-rid'=>$r['idReservation']))) : '')
                           . ($includeAction && $status == ReservationStatus::UnCommitted ? HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', $uS->guestLookups['ReservStatus'][ReservationStatus::Committed][1], array('class'=>'resvStat', 'data-stat'=>  ReservationStatus::Committed, 'data-rid'=>$r['idReservation']))) : '')
                          . ($uS->ccgw != '' ? HTMLContainer::generateMarkup('li', '-------') . HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', 'Credit Card', array('class'=>'stupCredit', 'data-id'=>$r['idGuest'], 'data-reg'=>$r['idRegistration'], 'data-name'=>$r['Guest Name']))) : '')
                    )), array('class' => 'gmenu'));
            }

            $fixedRows['Guest First'] = $r['Guest First'];

            // Build the page anchor
            if ($page != '' && !$static) {
                $fixedRows['Guest Last'] = HTMLContainer::generateMarkup('a', $r['Guest Last'], array('href'=>"$page?rid=" . $r["idReservation"]));
            } else {
                $fixedRows['Guest Last'] = $r['Guest Last'];
            }

            // Date reservation is filed.
            if ($status == ReservationStatus::Waitlist && $uS->ShowCreatedDate) {

                $bDay = new \DateTime($r['Timestamp']);

                if ($static) {
                    $fixedRows['Timestamp'] = $bDay->format('Y-m-d');
                } else {
                    $fixedRows['Timestamp'] = $bDay->format('c');
                }
            }


            // Days
            $stDay = new \DateTime($r['Expected_Arrival']);
            $stDay->setTime(10, 0, 0);

            if ($static) {
                $fixedRows['Expected Arrival'] = $stDay->format('Y-m-d');
            } else {
                $fixedRows['Expected Arrival'] = $stDay->format('c');
            }

            // Departure Date
            if ($r['Expected_Departure'] != '') {

                $edDay = new \DateTime($r['Expected_Departure']);
                $edDay->setTime(10, 0, 0);

                $fixedRows['Nights'] = $edDay->diff($stDay, TRUE)->days;

                if ($static) {
                    $fixedRows['Expected Departure'] = $edDay->format('Y-m-d');
                } else {
                    $fixedRows['Expected Departure'] = $edDay->format('c');
                }


            } else {

                $fixedRows['Nights'] = '';
                $fixedRows['Expected Departure'] = '';
            }

            // Room name?
            $fixedRows["Room"] = $r["Room Title"];

            // Phone?
            if ($status == ReservationStatus::Waitlist) {
                $fixedRows["Phone"] = $r["Phone"];
            }

            // Rate
            if ($status != ReservationStatus::Waitlist) {
                if ($uS->RoomPriceModel != ItemPriceCode::None && isset($this->roomRates[$r['idRoom_rate']])) {

                    $fixedRows['Rate'] = $this->roomRates[$r['idRoom_rate']];

                    if ($r['Rate'] == RoomRateCategories::Fixed_Rate_Category && $r['Fixed_Room_Rate'] > 0) {
                        $fixedRows['Rate'] = $this->roomRates[$r['idRoom_rate']] . ': $' . number_format($r['Fixed_Room_Rate'], 2);
                    }
                } else {
                    $fixedRows['Rate'] = '';
                }
            }

            // Number of guests
            $fixedRows["Occupants"] = $r["Number_Guests"];

            $patientTitle = $labels->getString('MemberType', 'patient', 'Patient');

            // Patient Name
            $fixedRows['Patient'] = $r['Patient Name'];

            if ($r['Patient_Staying'] > 0 && !$static) {
                $fixedRows['Patient'] .= HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-suitcase', 'style'=>'float:right;', 'title'=>"$patientTitle Planning to stay"));
            }


            // Hospital
            if (count($uS->guestLookups[GLTableNames::Hospital]) > 1) {
                $hospital = '';
                if ($r['idAssociation'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']]) && $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] != '(None)') {
                    $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] . ' / ';
                }
                if ($r['idHospital'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idHospital']])) {
                    $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idHospital']][1];
                }

                $fixedRows['Hospital'] = $hospital;
            }

            // Hospital Location
            $fixedRows['Location'] = $r['Location'];

            // Diagnosis
            $fixedRows['Diagnosis'] = $r['Diagnosis'];

            if ($status == ReservationStatus::Waitlist && $uS->UseWLnotes) {
                $fixedRows['WL Notes'] = $r['Checkin_Notes'];
            }

            if ($status == ReservationStatus::Waitlist && $static && $uS->UseWLnotes) {

                unset($fixedRows['Patient']);
                $fixedRows = array('Patient' => $r['Patient Name']) + $fixedRows;

//                if ($r['Patient_Staying'] > 0) {
//                    $fixedRows['Patient'] .= HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-suitcase', 'style'=>'float:right;', 'title'=>"$patientTitle Planning to stay"));
//                }
            }


            $returnRows[] = $fixedRows;

        }

        return $returnRows;

    }

    public static function getCheckedInGuestMarkup(\PDO $dbh, $page = "GuestEdit.php", $includeAction = TRUE, $static = FALSE, $patientColName = 'Patient', $hospColName = 'Hospital') {

        $uS = Session::getInstance();

        $hospList = array();
        if (isset($uS->guestLookups[GLTableNames::Hospital])) {
            $hospList = $uS->guestLookups[GLTableNames::Hospital];
        }

        return self::getCheckedInMarkup($dbh, $uS->PaymentGateway, $hospList, $page, $includeAction, $static, $patientColName, $hospColName);
    }

    public static function getCheckedInMarkup(\PDO $dbh, $creditGw, $hospitals, $page, $includeAction = TRUE, $static = FALSE, $patientColName = 'Patient', $hospColName = 'Hospital') {

        $uS = Session::getInstance();

        $roomRates = array();
        $rateRs = new Room_RateRS();
        $roomRatesRaw = EditRS::select($dbh, $rateRs, array());

        foreach ($roomRatesRaw as $c) {
            $roomRates[$c['idRoom_rate']] = $c;
        }

        unset($roomRatesRaw);

        $cleanCodes = readGenLookupsPDO($dbh, 'Room_Cleaning_Days');
        $noCleaning = '';
        foreach ($cleanCodes as $i) {
            if ($i['Substitute'] == '0') {
                $noCleaning = $i['Code'];
            }
        }

        unset($cleanCodes);

        $query = "select * from vcurrent_residents order by `Room`;";
        $stmt = $dbh->query($query);

        $returnRows = array();

        // Show adjust button?
        $hdArry = readGenLookupsPDO($dbh, "House_Discount");
        $roomStatuses = readGenLookupsPDO($dbh, 'Room_Status');


        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $fixedRows = array();

            // Action
            if ($includeAction && !$static) {
                if (isset($r['Action'])) {
                    $fixedRows['Action'] =  HTMLContainer::generateMarkup(
                        'ul', HTMLContainer::generateMarkup('li', 'Action' .
                                HTMLContainer::generateMarkup('ul',
                                ($uS->RoomPriceModel != ItemPriceCode::None ? HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', 'Take a Payment', array('class'=>'stpayFees', 'data-name'=>$r['Guest'], 'data-id'=>$r['Id'], 'data-vid'=>$r['idVisit'], 'data-spn'=>$r['Span']))) : '')
                              . HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', 'Check Out', array('class'=>'stckout', 'data-name'=>$r['Guest'], 'data-id'=>$r['Id'], 'data-vid'=>$r['idVisit'], 'data-spn'=>$r['Span'])))
                              . HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', 'Edit Visit', array('class'=>'stvisit', 'data-name'=>$r['Guest'], 'data-id'=>$r['Id'], 'data-vid'=>$r['idVisit'], 'data-spn'=>$r['Span'])))
                                		. HTMLContainer::generateMarkup('li', ($r['Room_Status'] == RoomState::Clean || $r['Room_Status'] == RoomState::Ready ? HTMLContainer::generateMarkup('div', 'Set Room '.$roomStatuses[RoomState::Dirty][1], array('class'=>'stcleaning', 'data-idroom'=>$r['RoomId'], 'data-clean'=>RoomState::Dirty)) : HTMLContainer::generateMarkup('div', 'Set Room '.$roomStatuses[RoomState::Clean][1], array('class'=>'stcleaning', 'data-idroom'=>$r['RoomId'], 'data-clean'=>  RoomState::Clean))))
                              . HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', 'Change Rooms', array('class'=>'stchgrooms', 'data-name'=>$r['Guest'], 'data-id'=>$r['Id'], 'data-vid'=>$r['idVisit'], 'data-spn'=>$r['Span'])))
                              . (SecurityComponent::is_Authorized('guestadmin') === FALSE || count($hdArry) == 0 ? '' : HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', 'Apply Discount', array('class'=>'applyDisc', 'data-vid'=>$r['idVisit']))))
//                              . ($creditGw != '' ? HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('div', 'Credit Card', array('class'=>'stupCredit', 'data-id'=>$r['Id'], 'data-reg'=>$r['idRegistration'], 'data-name'=>$r['Guest']))) : '')
                        )), array('class' => 'gmenu'));

                }
            }

            $fixedRows['Guest First'] = $r['Guest First'];

            // Build the page anchor
            if ($page != '') {
                $fixedRows['Guest Last'] = HTMLContainer::generateMarkup('a', $r['Guest Last'], array('href'=>"$page?id=" . $r["Id"] . '&psg=' . $r['idPsg']));
            } else {
                $fixedRows['Guest Last'] = $r['Guest Last'];
            }

            // Indicate On leave
            if ($r['On_Leave'] > 0 && $page != '') {

                $daysOnLv = intval($r['On_Leave']);

                $now = new \DateTime();
                $now->setTime(0, 0, 0);

                $stDay = new \DateTime($r['Span_Start_Date']);
                $stDay->setTime(0, 0, 0);
                $stDay->add(new \DateInterval('P' . $daysOnLv . 'D'));

                if ($now > $stDay) {
                    // Past Due
                    $fixedRows['Guest Last'] = HTMLContainer::generateMarkup('span', $fixedRows['Guest Last'], array('class'=>'ui-state-error','title'=>'On Leave - past due!'));

                } else {
                    // on leave
                    $fixedRows['Guest Last'] = HTMLContainer::generateMarkup('span', $fixedRows['Guest Last'], array('class'=>'ui-state-highlight','title'=>'On Leave until ' . $stDay->format('M j')));
                }
            }


            // Date?
            if ($static) {
                $fixedRows['Checked In'] = date('M j, Y H:i', strtotime($r['Checked-In']));
            } else {
                $fixedRows['Checked In'] = date('Y-m-d', strtotime($r['Checked-In']));
            }

            // Days
            $stDay = new \DateTime($r['Checked-In']);
            $stDay->setTime(10, 0, 0);
            $edDay = new \DateTime(date('Y-m-d 10:00:00'));

            $fixedRows['Nights'] = $edDay->diff($stDay, TRUE)->days;

            // Expected Departure
            if ($r['Expected Depart'] != '') {

                if ($static) {
                    $fixedRows['Expected Departure'] = date('M j, Y', strtotime($r['Expected Depart']));
                } else {
                    $fixedRows['Expected Departure'] = date('Y-m-d', strtotime($r['Expected Depart']));
                }

            } else {
                $fixedRows['Expected Departure'] = '';
            }

            // Room name?
            $fixedRows["Room"] = $r["Room"];
            if ($page != '') {
                $fixedRows["Room"] = HTMLContainer::generateMarkup('span', $r["Room"], array('style'=>'background-color:' . $r["backColor"]. ';color:' . $r["textColor"] . ';'));

                if ($r['Room_Status'] != RoomState::Clean && $r['Room_Status'] != RoomState::Ready && $r['Cleaning_Cycle_Code'] != $noCleaning) {
                	$fixedRows['Room'] .= HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-notice', 'style'=>'float:right;', 'title'=>'Room is '.$roomStatuses[RoomState::Dirty][1]));
                }
            }

            // Rate
            if ($uS->RoomPriceModel != ItemPriceCode::None) {

                if (isset($roomRates[$r['idRoom_rate']])) {

                    $fixedRows['Rate'] = $roomRates[$r['idRoom_rate']]['Title'];

                    if ($roomRates[$r['idRoom_rate']]['FA_Category'] == RoomRateCategories::Fixed_Rate_Category && $r['Pledged_Rate'] > 0) {
                        $fixedRows['Rate'] .= ': $' .number_format($r['Pledged_Rate'], 2);
                    } else if ($roomRates[$r['idRoom_rate']]['FA_Category'] == RoomRateCategories::FlatRateCategory) {
                        $fixedRows['Rate'] .= ': $' . number_format($roomRates[$r['idRoom_rate']]['Reduced_Rate_1'], 2);
                    }

                } else {

                    $fixedRows['Rate'] = '';

                }
            }

            // House Phone
            if (strtolower($r['Use House Phone']) == 'y' && $r['Phone'] == '') {
                $fixedRows['Phone'] = $r['Room Phone'] . ' (H)';
            } else {
                $fixedRows['Phone'] = $r['Phone'];
            }


            // Hospital
            if (count($hospitals) > 1) {
                $hospital = '';
                if ($r['idAssociation'] > 0 && isset($hospitals[$r['idAssociation']]) && $hospitals[$r['idAssociation']][1] != '(None)') {
                    $hospital .= $hospitals[$r['idAssociation']][1] . ' / ';
                }
                if ($r['idHospital'] > 0 && isset($hospitals[$r['idHospital']])) {
                    $hospital .= $hospitals[$r['idHospital']][1];
                }

                $fixedRows[$hospColName] = $hospital;
            }

            // Patient Name
            $fixedRows[$patientColName] = $r['Patient'];

            if ($page != '' && !$static) {
                $fixedRows[$patientColName] = HTMLContainer::generateMarkup('span', $r['Patient'], array('class'=>'hhk-getPSGDialog', 'style'=>'cursor:pointer;width:100%;text-decoration: underline;', 'data-psg'=>$r['idPsg']));
            }

            $returnRows[] = $fixedRows;
        }

        return $returnRows;

    }


    public static function getVolEventsMarkup(\PDO $dbh, \DateTime $startDate) {

        $query = "select * from vrecent_calevents where `Last Updated` > '" .$startDate->format('Y-m-d'). "' order by Category, `Last Updated`;";
        $stmt = $dbh->query($query);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $fixedRows = array();

        foreach ($rows as $r) {

            // Date?
            $r['Start'] = date('D, M jS. g:i a', strtotime($r['Start']));
            $r['End'] = date('D, M jS. g:i a', strtotime($r['End']));
            $r['Last Updated'] = date('D, M jS. g:i a', strtotime($r['Last Updated']));

            if ($r['Status'] == 'Deleted') {
                $r['Status'] = HTMLContainer::generateMarkup('span', $r['Status'], array('style'=>'background-color:red;color:yellow;'));
            }

            $fixedRows[] = $r;

        }
        return $fixedRows;

    }

}
?>