<?php
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

    public static function addToGuestHistoryList(\PDO $dbh, $id, $role) {
        if ($id > 0 && $role < WebRole::Guest) {
            $query = "INSERT INTO member_history (idName, Guest_Access_Date) VALUES ($id, now())
        ON DUPLICATE KEY UPDATE Guest_Access_Date = now();";
            //$query = "replace guest_history (idName, Access_Date) values ($id, now());";
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
            throw new Hk_Exception_InvalidArguement("Database view name must be defined.");
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


    public function getReservedGuestsMarkup(\PDO $dbh, $status = ReservationStatus::Committed, $page = "Referral.php", $includeAction = TRUE) {

        if (is_null($this->roomRates)) {
            $this->roomRates = RoomRate::makeDescriptions($dbh);
        }

        if (is_null($this->resvEvents)) {

            $query = "select * from vreservation_events where Status = '$status' order by Arrival_Date";
            $stmt = $dbh->query($query);
            $this->resvEvents = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }


        if (is_null($this->locations)) {
            $this->locations = readGenLookupsPDO($dbh, 'Location');
        }

        return $this->createMarkup($status, $page, $includeAction);
    }

    protected function createMarkup($status, $page, $includeAction) {

        $uS = Session::getInstance();
        // Get labels
        $labels = new Config_Lite(LABEL_FILE);
        $returnRows = array();

        foreach ($this->resvEvents as $r) {

            $fixedRows = array();
            $gName = $r['Guest Name'];

            // Action
            if ($includeAction) {
                $fixedRows['Action'] =  HTMLContainer::generateMarkup(
                    'ul', HTMLContainer::generateMarkup('li', 'Action' .
                        HTMLContainer::generateMarkup('ul',
                           HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', $uS->guestLookups['ReservStatus'][ReservationStatus::Canceled][1], array('href'=>'#', 'class'=>'resvStat', 'data-stat'=>  ReservationStatus::Canceled, 'data-rid'=>$r['idReservation'])))
                           . HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', $uS->guestLookups['ReservStatus'][ReservationStatus::NoShow][1], array('href'=>'#', 'class'=>'resvStat', 'data-stat'=>  ReservationStatus::NoShow, 'data-rid'=>$r['idReservation'])))
                           . HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', $uS->guestLookups['ReservStatus'][ReservationStatus::TurnDown][1], array('href'=>'#', 'class'=>'resvStat', 'data-stat'=>  ReservationStatus::TurnDown, 'data-rid'=>$r['idReservation'])))
                           . ($includeAction ? HTMLContainer::generateMarkup('li', '-------') : '')
                           . ($includeAction && ($status == ReservationStatus::Committed || $status == ReservationStatus::UnCommitted) ? HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', $uS->guestLookups['ReservStatus'][ReservationStatus::Waitlist][1], array('href'=>'#', 'class'=>'resvStat', 'data-stat'=>  ReservationStatus::Waitlist, 'data-rid'=>$r['idReservation']))) : '')
                           . ($includeAction && $status == ReservationStatus::Committed ? HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', $uS->guestLookups['ReservStatus'][ReservationStatus::UnCommitted][1], array('href'=>'#', 'class'=>'resvStat', 'data-stat'=>  ReservationStatus::UnCommitted, 'data-rid'=>$r['idReservation']))) : '')
                           . ($includeAction && $status == ReservationStatus::UnCommitted ? HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', $uS->guestLookups['ReservStatus'][ReservationStatus::Committed][1], array('href'=>'#', 'class'=>'resvStat', 'data-stat'=>  ReservationStatus::Committed, 'data-rid'=>$r['idReservation']))) : '')
                           . HTMLContainer::generateMarkup('li', '-------')
                          . ($uS->ccgw != '' ? HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', 'Credit Card', array('href'=>'#', 'class'=>'stupCredit', 'data-id'=>$r['idGuest'], 'data-reg'=>$r['idRegistration'], 'data-name'=>$gName))) : '')
                    )), array('class' => 'gmenu'));
            }


            // Build the page anchor
            if ($page != '') {
                $fixedRows['Guest'] = HTMLContainer::generateMarkup('a', $gName, array('href'=>"$page?rid=" . $r["idReservation"]));
            } else {
                $fixedRows['Guest'] = $gName;
            }


            // Days
            $stDay = new \DateTime($r['Arrival_Date']);
            $stDay->setTime(10, 0, 0);


                $fixedRows['Expected Arrival'] = $stDay->format('D, M j, Y');

            // Departure Date
            if ($r['Expected_Departure'] != '') {

                $edDay = new \DateTime($r['Expected_Departure']);
                $edDay->setTime(10, 0, 0);

                $fixedRows['Nights'] = $edDay->diff($stDay, TRUE)->days;
                $fixedRows['Expected Departure'] = $edDay->format('M j, Y');

            } else {

                $fixedRows['Nights'] = '';
                $fixedRows['Expected Departure'] = '';
            }

            // Room name?
            if ($status == ReservationStatus::Committed || $status == ReservationStatus::UnCommitted) {
                $fixedRows["Room"] = $r["Room Title"];
            }

            // Rate
            if ($uS->RoomPriceModel != ItemPriceCode::None && isset($this->roomRates[$r['idRoom_rate']])) {
                $fixedRows['Rate'] = $this->roomRates[$r['idRoom_rate']];
            } else {
                $fixedRows['Rate'] = '';
            }

            // Number of guests
            $fixedRows["Occupants"] = $r["Number_Guests"];

            // Hospital
            $hospital = '';
            if ($r['idAssociation'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']]) && $uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']][1] != '(None)') {
                $hospital .= $uS->guestLookups[GL_TableNames::Hospital][$r['idAssociation']][1] . ' / ';
            }
            if ($r['idHospital'] > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$r['idHospital']])) {
                $hospital .= $uS->guestLookups[GL_TableNames::Hospital][$r['idHospital']][1];
            }

            $fixedRows[$labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital')] = $hospital;

            // Hospital Location
            if (count($this->locations) > 0) {
                $fixedRows[$labels->getString('hospital', 'location', 'Unit')] = $r['Location'];
            }

            // Patient Name
            $fixedRows[$labels->getString('MemberType', 'patient', 'Patient')] = $r['Patient Name'];

            $returnRows[] = $fixedRows;

        }

        return $returnRows;

    }

    public static function getCheckedInGuestMarkup(\PDO $dbh, $page = "GuestEdit.php", $includeAction = TRUE) {

        $uS = Session::getInstance();

        $hospList = array();
        if (isset($uS->guestLookups[GL_TableNames::Hospital])) {
            $hospList = $uS->guestLookups[GL_TableNames::Hospital];
        }

        //$roomRates = RoomRate::makeDescriptions($dbh);

        return self::getCheckedInMarkup($dbh, $uS->ccgw, $hospList, $page, $includeAction);
    }

    public static function getCheckedInMarkup(\PDO $dbh, $creditGw, $hospitals, $page, $includeAction = TRUE) {

        $uS = Session::getInstance();

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);

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


        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $fixedRows = array();

            // Action
            if ($includeAction) {
                if (isset($r['Action'])) {
                    $fixedRows['Action'] =  HTMLContainer::generateMarkup(
                        'ul', HTMLContainer::generateMarkup('li', 'Action' .
                                HTMLContainer::generateMarkup('ul',
                                ($uS->RoomPriceModel != ItemPriceCode::None ? HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', 'Take a Payment', array('href'=>'#', 'class'=>'stpayFees', 'data-name'=>$r['Guest'], 'data-id'=>$r['Id'], 'data-vid'=>$r['idVisit'], 'data-spn'=>$r['Span']))) : '')
                              . HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', 'Check Out', array('href'=>'#', 'class'=>'stckout', 'data-name'=>$r['Guest'], 'data-id'=>$r['Id'], 'data-vid'=>$r['idVisit'], 'data-spn'=>$r['Span'])))
                              . HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', 'Edit Visit', array('href'=>'#', 'class'=>'stvisit', 'data-name'=>$r['Guest'], 'data-id'=>$r['Id'], 'data-vid'=>$r['idVisit'], 'data-spn'=>$r['Span'])))
                              . ($r['Room_Status'] == RoomState::Clean ? HTMLContainer::generateMarkup('a', 'Set Room Dirty', array('href'=>'#', 'class'=>'stcleaning', 'data-idroom'=>$r['RoomId'], 'data-clean'=>RoomState::Dirty)) : '')
                              . ($r['Room_Status'] != RoomState::Clean ? HTMLContainer::generateMarkup('a', 'Set Room Clean', array('href'=>'#', 'class'=>'stcleaning', 'data-idroom'=>$r['RoomId'], 'data-clean'=>  RoomState::Clean)) : '')
                              . HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', 'Change Rooms', array('href'=>'#', 'class'=>'stchgrooms', 'data-name'=>$r['Guest'], 'data-id'=>$r['Id'], 'data-vid'=>$r['idVisit'], 'data-spn'=>$r['Span'])))
                              . (ComponentAuthClass::is_Authorized('guestadmin') === FALSE || count($hdArry) == 0 ? '' : HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', 'Apply Discount', array('href'=>'#', 'class'=>'applyDisc', 'data-vid'=>$r['idVisit']))))
                              . ($creditGw != '' ? HTMLContainer::generateMarkup('li', HTMLContainer::generateMarkup('a', 'Credit Card', array('href'=>'#', 'class'=>'stupCredit', 'data-id'=>$r['Id'], 'data-reg'=>$r['idRegistration'], 'data-name'=>$r['Guest']))) : '')
                        )), array('class' => 'gmenu'));

                }
            }


            // Build the page anchor
            if ($page != '') {
                $fixedRows['Guest'] = HTMLContainer::generateMarkup('a', $r['Guest'], array('href'=>"$page?id=" . $r["Id"] . '&psg=' . $r['idPsg']));
            } else {
                $fixedRows['Guest'] = $r['Guest'];
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
                    $fixedRows['Guest'] = HTMLContainer::generateMarkup('span', $fixedRows['Guest'], array('class'=>'ui-state-error','title'=>'On Leave - past due!'));

                } else {
                    // on leave
                    $fixedRows['Guest'] = HTMLContainer::generateMarkup('span', $fixedRows['Guest'], array('class'=>'ui-state-highlight','title'=>'On Leave until ' . $stDay->format('M j')));
                }
            }


            // Date?
            $fixedRows['Checked-In'] = date('D, M j, Y', strtotime($r['Checked-In']));

            // Days
            $stDay = new \DateTime($r['Checked-In']);
            $stDay->setTime(10, 0, 0);
            $edDay = new \DateTime(date('Y-m-d 10:00:00'));
            //$edDay->setTime(11, 0, 0);
            $fixedRows['Nights'] = $edDay->diff($stDay, TRUE)->days;

            // Expected Departure
            if ($r['Expected Depart'] != '') {
                $fixedRows['Expected Departure'] = date('D, M j, Y', strtotime($r['Expected Depart']));
            } else {
                $fixedRows['Expected Departure'] = '';
            }

            // Room name?
            $fixedRows["Room"] = $r["Room"];
            if ($page != '') {
                $fixedRows["Room"] = HTMLContainer::generateMarkup('span', $r["Room"], array('style'=>'background-color:' . $r["backColor"]. ';color:' . $r["textColor"] . ';'));

                if ($r['Room_Status'] != RoomState::Clean && $r['Cleaning_Cycle_Code'] != $noCleaning) {
                    $fixedRows['Room'] .= HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-notice', 'style'=>'float:right;', 'title'=>'Room is Dirty'));
                }
            }

            // Rate
            if ($uS->RoomPriceModel != ItemPriceCode::None) {

                if (isset($roomRates[$r['idRoom_rate']])) {

                    $fixedRows['Rate'] = $roomRates[$r['idRoom_rate']]['Title'];

                    if ($roomRates[$r['idRoom_rate']]['FA_Category'] == RoomRateCategorys::Fixed_Rate_Category) {
                        $fixedRows['Amount'] = number_format($r['Pledged_Rate'], (floor($r['Pledged_Rate']) != $r['Pledged_Rate'] ? 2 : 0));
                    } else {
                        $fixedRows['Amount'] = ($roomRates[$r['idRoom_rate']]['Reduced_Rate_1'] == 0 ? '0' :  number_format($roomRates[$r['idRoom_rate']]['Reduced_Rate_1'], (floor($roomRates[$r['idRoom_rate']]['Reduced_Rate_1']) != $roomRates[$r['idRoom_rate']]['Reduced_Rate_1'] ? 2 : 0)));
                    }

                } else {

                    $fixedRows['Rate'] = ' ';
                    $fixedRows['Amount'] = ' ';
                }
            }

            // House Phone
            if (strtolower($r['Use House Phone']) == 'y' && $r['Phone'] == '') {
                $fixedRows['Phone'] = $r['Room Phone'] . ' (H)';
            } else {
                $fixedRows['Phone'] = $r['Phone'];
            }


            // Hospital
            $hospital = '';
            if ($r['idAssociation'] > 0 && isset($hospitals[$r['idAssociation']]) && $hospitals[$r['idAssociation']][1] != '(None)') {
                $hospital .= $hospitals[$r['idAssociation']][1] . ' / ';
            }
            if ($r['idHospital'] > 0 && isset($hospitals[$r['idHospital']])) {
                $hospital .= $hospitals[$r['idHospital']][1];
            }

            $fixedRows[$labels->getString('resourceBuilder', 'hospitalsTab', 'Hospital')] = $hospital;

            // Patient Name
            $fixedRows[$labels->getString('MemberType', 'patient', 'Patient')] = $r['Patient'];

            if ($page != '') {
                $fixedRows[$labels->getString('MemberType', 'patient', 'Patient')] = HTMLContainer::generateMarkup('span', $r['Patient'], array('class'=>'hhk-getPSGDialog', 'style'=>'cursor:pointer;width:100%;text-decoration: underline;', 'data-psg'=>$r['idPsg']));
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

