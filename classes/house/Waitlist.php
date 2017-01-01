<?php
/**
 * Waitlist.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Waitlist
 * @package name
 * @author Eric
 */
class Waitlist {

    public static function findEntry(PDO $dbh, $idPatient) {
        $wlRS = new WaitlistRS();
        $wlRS->idPatient->setStoredVal($idPatient);
        $rows = EditRS::select($dbh, $wlRS, array($wlRS->idPatient));
        return $rows;
    }

    public static function makeGuestEntry(PDO $dbh, $idGuest, $returnDateString, $uname) {

        if ($idGuest == 0 || $returnDateString == '') {
            return;
        }

        $uS = Session::getInstance();

        try{
            $rDate = new DateTime($returnDateString);
            $rDate->setTimezone(new DateTimeZone($uS->tz));
        } catch (Exception $ex) {
            return;
        }

        if ($rDate <= new DateTime()) {
            return;
        }

        $wlRS = new WaitlistRS();

        $guest = new Guest($dbh, 'g', $idGuest);

        $psg = $guest->getPatientPsg();

        $wlRS->idPatient->setNewVal($psg->getIdPatient());
        $wlRS->Hospital->setNewVal('');
        $wlRS->idPsg->setNewVal($psg->getIdPsg());


        $wlRS->Arrival_Date->setNewVal($rDate->format('Y-m-d H:i:s'));
        $wlRS->Number_Adults->setNewVal(1);
        $wlRS->idGuest->setNewVal($idGuest);
        $phObj = $guest->getPhonesObj();
        $phData = $phObj->get_Data();;    // gets preferred numbers.
        $wlRS->Phone->setNewVal($phData["Phone_Num"]);

        $wlRS->Status->setNewVal('a');
        $wlRS->Expected_Duration->setNewVal(5);
        $wlRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
        $wlRS->Updated_By->setNewVal($uname);
        // Insert
        $numRec = EditRS::insert($dbh, $wlRS);

        return $numRec;
    }

    public static function createDialog($r, $hosplist, $rStatusList) {

        $wltbl = new HTMLTable();

        $wltbl->addBodyTr(HTMLTable::makeTd('', array('class'=>'tdlabel'))
                .HTMLTable::makeTh(HTMLContainer::generateMarkup('span', '', array('id'=>'wlValidate'))));

        // Guest
        $gLast = isset($r['Guest_Last']) ? $r['Guest_Last'] : '';
        $gFirst = isset($r['Guest_First']) ? $r['Guest_First'] : '';
        $idGuest = isset($r['idGuest']) ? $r['idGuest'] : '0';
        $wltbl->addBodyTr(HTMLTable::makeTh('Guest')
                . HTMLTable::makeTd(
                HTMLTable::generateDirectMarkup(
                HTMLContainer::generateMarkup('tr',
                        HTMLTable::makeTd('Search: ' . HTMLInput::generateMarkup('', array('id'=>'wlgName', 'class'=>'wsDiag')), array('colspan'=>'4', 'style'=>'border:none;')))
                . HTMLContainer::generateMarkup('tr',
                        HTMLTable::makeTd('First:', array('class'=>'tdlabel'))
                        . HTMLTable::makeTd(HTMLInput::generateMarkup($gFirst, array('id'=>'wlgFirst', 'class'=>'wsDiag', 'size'=>'15')), array('class'=>'tdlabel'))
                        . HTMLTable::makeTd('Last:', array('class'=>'tdlabel'))
                        . HTMLTable::makeTd(HTMLInput::generateMarkup($gLast, array('id'=>'wlgLast', 'class'=>'wsDiag', 'size'=>'18'))
                                . HTMLInput::generateMarkup($idGuest, array('type'=>'hidden', 'id'=>'wlIdGuest', 'class'=>'wsDiag')), array('class'=>'tdlabel'))
                )), array('colspan'=>'2')));

        // Patient
        $pLast = isset($r['Patient_Last']) ? $r['Patient_Last'] : '';
        $pFirst = isset($r['Patient_First']) ? $r['Patient_First'] : '';
        $idPatient = isset($r['idPatient']) ? $r['idPatient'] : '0';
        $wltbl->addBodyTr(HTMLTable::makeTh('Patient')
                . HTMLTable::makeTd(
                HTMLTable::generateDirectMarkup(
                HTMLContainer::generateMarkup('tr',
                        HTMLTable::makeTd('Search: ' . HTMLInput::generateMarkup('', array('id'=>'wlpName', 'class'=>'wsDiag')), array('colspan'=>'4', 'style'=>'border:none;')))
                . HTMLContainer::generateMarkup('tr',
                        HTMLTable::makeTd('First:', array('class'=>'tdlabel'))
                        . HTMLTable::makeTd(HTMLInput::generateMarkup($pFirst, array('id'=>'wlpFirst', 'class'=>'wsDiag', 'size'=>'15')), array('class'=>'tdlabel'))
                        . HTMLTable::makeTd('Last:', array('class'=>'tdlabel'))
                        . HTMLTable::makeTd(HTMLInput::generateMarkup($pLast, array('id'=>'wlpLast', 'class'=>'wsDiag', 'size'=>'18'))
                                . HTMLInput::generateMarkup($idPatient, array('type'=>'hidden', 'id'=>'wlIdPatient', 'class'=>'wsDiag')), array('class'=>'tdlabel'))
                )), array('colspan'=>'2')));


        // Hospital
        $hospital = isset($r['Hospital']) ? $r['Hospital'] : "";
        $wltbl->addBodyTr(HTMLTable::makeTh('Hospital')
                . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                        HTMLSelector::doOptionsMkup(removeOptionGroups($hosplist), $hospital),
                        array('id'=>'selwlHospital', 'title'=>'Select a Hospital', 'class'=>'wsDiag')
                        )));

        // Arival Date
        $ardate = (isset($r['Arrival_Date']) ? $r['Arrival_Date'] : '');
        $days = (isset($r['Expected_Duration']) ? $r['Expected_Duration'] : '1');
        $wltbl->addBodyTr(HTMLTable::makeTh('Stay')
            . HTMLTable::makeTd(
                HTMLTable::generateDirectMarkup(
                HTMLContainer::generateMarkup('tr',
                        HTMLTable::makeTd('Expected Check-in:', array('class'=>'tdlabel'))
                        . HTMLTable::makeTd(HTMLInput::generateMarkup($ardate == '' ? '': date('m/d/Y', strtotime($ardate)), array('id'=>'arDate', 'class'=>'wsDiag ckdate')), array('class'=>'tdlabel'))
                        . HTMLTable::makeTd('# of Days:', array('class'=>'tdlabel'))
                        . HTMLTable::makeTd(HTMLInput::generateMarkup($days, array('id'=>'wlDays', 'class'=>'wsDiag number-only', 'size'=>'5')), array('class'=>'tdlabel')),
                        array('colspan'=>'2')))));

        // Phone
        $phone = (isset($r['Phone']) ? $r['Phone'] : '');
        $wltbl->addBodyTr(HTMLTable::makeTh('Phone')
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup($phone, array('id'=>'wlPhone', 'class'=>'wsDiag hhk-phoneInput'))
                        . HTMLContainer::generateMarkup('span', '*Error', array('style'=>'color:red;'))));

        // Email
        $email = (isset($r['Email']) ?$r['Email'] : '');
        $wltbl->addBodyTr(HTMLTable::makeTh('Email')
                . HTMLTable::makeTd(
                        HTMLInput::generateMarkup($email, array('id'=>'wlEmail', 'class'=>'wsDiag hhk-emailInput', 'size'=>'35'))
                        . HTMLContainer::generateMarkup('span', '*Error', array('style'=>'color:red;'))));

        // Number of Adults & Children
        $adult = (isset($r['Number_Adults']) ? $r['Number_Adults'] : '1');
        $child = (isset($r['Number_Children']) ? $r['Number_Children'] : '');
        $wltbl->addBodyTr(HTMLTable::makeTh('Number')
            . HTMLTable::makeTd(
                HTMLTable::generateDirectMarkup(
                HTMLContainer::generateMarkup('tr',
                        HTMLTable::makeTd('Adults:', array('class'=>'tdlabel'))
                        . HTMLTable::makeTd(HTMLInput::generateMarkup(($adult > 0 ? $adult : '1'), array('id'=>'wlAdult', 'class'=>'wsDiag number-only', 'size'=>'5')), array('class'=>'tdlabel'))
                        . HTMLTable::makeTd('Children:', array('class'=>'tdlabel'))
                        . HTMLTable::makeTd(HTMLInput::generateMarkup($child, array('id'=>'wlChild', 'class'=>'wsDiag number-only', 'size'=>'5')), array('class'=>'tdlabel')),
                        array('colspan'=>'2')))));

        // Status
        $status = (isset($r['Status']) ? $r['Status'] : 'a');

//        if (isset($r['Timestamp'])) {
//            $timestamp = date('m/d/Y', strtotime($r['Timestamp']));
//        } else {
            $timestamp = date('m/d/Y', time());
//        }
        $wltbl->addBodyTr(HTMLTable::makeTh('Status')
            . HTMLTable::makeTd(
                HTMLTable::generateDirectMarkup(
                HTMLContainer::generateMarkup('tr',
                        HTMLTable::makeTd('Status', array('class'=>'tdlabel'))
                        . HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($rStatusList, $status),
                            array('id'=>'selwlStatus', 'title'=>'Select wait list status', 'class'=>'wsDiag')),
                        array('class'=>'tdlabel'))
                        . HTMLTable::makeTd('Date Entered', array('class'=>'tdlabel'))
                        . HTMLTable::makeTd(HTMLInput::generateMarkup($timestamp,
                            array('id'=>'wlDate', 'class'=>'wsDiag', 'readonly'=>'readonly', 'style'=>'border:none;', 'size'=>'15')),
                                array('class'=>'tdlabel')))),
                        array('colspan'=>'2')));


        // Final Status
//        $final = (isset($r['Final_Status'])  ? $r['Final_Status'] : "");
//        $tdate = (isset($r['Final_Status_Date']) ? $r['Final_Status_Date'] : '');
//        $wltbl->addBodyTr(HTMLTable::makeTd('Finally', array('class'=>'tdlabel'))
//            . HTMLTable::makeTd(
//                HTMLTable::generateDirectMarkup(
//                HTMLContainer::generateMarkup('tr',
//                        HTMLTable::makeTd('Final Status', array('class'=>'tdlabel'))
//                        . HTMLTable::makeTd(HTMLSelector::generateMarkup(
//                    HTMLSelector::doOptionsMkup($fStatusList, $final),
//                    array('id'=>'selwlFinalStatus', 'title'=>'Select wait list final status', 'class'=>'wsDiag')), array('class'=>'tdlabel'))
//                        . HTMLTable::makeTd('Date', array('class'=>'tdlabel'))
//                        . HTMLTable::makeTd(HTMLInput::generateMarkup($tdate == '' ? '': date('m/d/Y', strtotime($tdate)),
//                            array('id'=>'wlfsDate', 'class'=>'wsDiag ckdate')), array('class'=>'tdlabel')),
//                        array('colspan'=>'2')))), array('class'=>'hide-new'));

        // Notes
        $idWL = (isset($r['idWaitlist']) ? $r['idWaitlist'] : '0');
        $notes = (isset($r['Notes']) ? $r['Notes'] : '');

        $wltbl->addBodyTr(HTMLTable::makeTd('Notes:', array('class'=>'tdlabel'))
                . HTMLTable::makeTd(Notes::markupShell($notes, 'wlNotes', 2, 'wsDiag')
                                        . HTMLInput::generateMarkup($idWL, array('type'=>'hidden', 'id'=>'idWL', 'class'=>'wsDiag')))
                );

        return $wltbl->generateMarkup();

    }

    public static function updateEntry(PDO $dbh, $idWaitlist, $wlStatus, $uname) {

        if ($idWaitlist < 1) {
            return;
        }

        $wlRS = new WaitlistRS();
        $wlRS->idWaitlist->setStoredVal($idWaitlist);
        $rows = EditRS::select($dbh, $wlRS, array($wlRS->idWaitlist));

        if (count($rows) != 1) {
            return;
        }

        EditRS::loadRow($rows[0], $wlRS);
        $wlRS->Status->setNewVal($wlStatus);
        $wlRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
        $wlRS->Updated_By->setNewVal($uname);
        $numRec = EditRS::update($dbh, $wlRS, array($wlRS->idWaitlist));

    }

    public static function deleteEntry(PDO $dbh, $idWaitlist) {

        if ($idWaitlist < 1) {
            return;
        }

        $wlRS = new WaitlistRS();
        $wlRS->idWaitlist->setStoredVal($idWaitlist);
        EditRS::delete($dbh, $wlRS, array($wlRS->idWaitlist));

    }

    public static function saveDialog(PDO $dbh, array $parms, $uname = '') {

        $wlRS = new WaitlistRS();
        $uS = Session::getInstance();

        $idWL = 0;
        if (isset($parms['idWL'])) {
            $idWL = intval(filter_var($parms['idWL'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if ($idWL > 0) {

            // Existing
            $wlRS->idWaitlist->setStoredVal($idWL);
            $rows = EditRS::select($dbh, $wlRS, array($wlRS->idWaitlist));

            if (count($rows) != 1) {
                throw new Hk_Exception_Runtime("Failed to Find Waitlist Index");
            }

            EditRS::loadRow($rows[0], $wlRS);

            $wlStatus = '';
            if (isset($parms['selwlStatus'])) {
                $wlStatus = filter_var($parms['selwlStatus'], FILTER_SANITIZE_STRING);
            }

            $wlRS->Status->setNewVal($wlStatus);

        } else {

            // New Wait list entry
            $wlRS->Status->setNewVal('a');

        }

        $rDate = new DateTime();

        if (isset($parms['wlpFirst'])) {
            $wlRS->Patient_First->setNewVal(ucfirst(filter_var($parms['wlpFirst'], FILTER_SANITIZE_STRING)));
        }
        if (isset($parms['wlpLast'])) {
            $wlRS->Patient_Last->setNewVal(ucfirst(filter_var($parms['wlpLast'], FILTER_SANITIZE_STRING)));
        }
        if (isset($parms['wlgFirst'])) {
            $wlRS->Guest_First->setNewVal(ucfirst(filter_var($parms['wlgFirst'], FILTER_SANITIZE_STRING)));
        }
        if (isset($parms['wlgLast'])) {
            $wlRS->Guest_Last->setNewVal(ucfirst(filter_var($parms['wlgLast'], FILTER_SANITIZE_STRING)));
        }
        if (isset($parms['wlIdGuest'])) {
            $wlRS->idGuest->setNewVal(filter_var($parms['wlIdGuest'], FILTER_SANITIZE_NUMBER_INT));
        }
        if (isset($parms['wlIdPatient'])) {
            $wlRS->idPatient->setNewVal(filter_var($parms['wlIdPatient'], FILTER_SANITIZE_NUMBER_INT));
        }
        if (isset($parms['wlPhone'])) {
            $wlRS->Phone->setNewVal(filter_var($parms['wlPhone'], FILTER_SANITIZE_STRING));
        }
        if (isset($parms['wlEmail'])) {
            $wlRS->Email->setNewVal(filter_var($parms['wlEmail'], FILTER_SANITIZE_STRING));
        }
        if (isset($parms['arDate'])) {
            try{
                $rDate = new DateTime(filter_var($parms['arDate'], FILTER_SANITIZE_STRING));
                $rDate->setTimezone(new DateTimeZone($uS->tz));
                $wlRS->Arrival_Date->setNewVal($rDate->format('Y-m-d H:i:s'));
            } catch (Exception $ex) {

            }
        }
        if (isset($parms['wlDays'])) {
            $wlRS->Expected_Duration->setNewVal(filter_var($parms['wlDays'], FILTER_SANITIZE_STRING));
        }
        if (isset($parms['selwlHospital'])) {
            $wlRS->Hospital->setNewVal(filter_var($parms['selwlHospital'], FILTER_SANITIZE_STRING));
        }
        if (isset($parms['wlAdult'])) {
            $wlRS->Number_Adults->setNewVal(filter_var($parms['wlAdult'], FILTER_SANITIZE_STRING));
        }
        if (isset($parms['wlChild'])) {
            $wlRS->Number_Children->setNewVal(filter_var($parms['wlChild'], FILTER_SANITIZE_STRING));
        }
        if (isset($parms['wlNotes'])) {
            $notes = filter_var($parms['wlNotes'], FILTER_SANITIZE_STRING);

            if ($notes != '') {
                $oldNotes = is_null($wlRS->Notes->getStoredVal()) ? '' : $wlRS->Notes->getStoredVal();
                $wlRS->Notes->setNewVal($oldNotes . "\r\n" . date('m-d-Y') . ', ' . $uname . ' - ' . $notes);
            }

        }
        $wlRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
        $wlRS->Updated_By->setNewVal($uname);

        // Write record
        $numRec = 0;
        if ($parms['idWL'] > 0) {
            // Update
            $numRec = EditRS::update($dbh, $wlRS, array($wlRS->idWaitlist));
        } else {
            // Insert
            $numRec = EditRS::insert($dbh, $wlRS);
        }

        EditRS::updateStoredVals($wlRS);

        $gMarkup = "<html><body><h3>New Anticipated Visit for " .$rDate->format('D M jS, Y') . "</h3>";

        $tbl = new HTMLTable();

        $tbl->addBodyTr(HTMLTable::makeTd("Guest").HTMLTable::makeTd($wlRS->Guest_First->getStoredVal() . ' ' . $wlRS->Guest_Last->getStoredVal()));
        $tbl->addBodyTr(HTMLTable::makeTd("Patient").HTMLTable::makeTd($wlRS->Patient_First->getStoredVal() . ' ' . $wlRS->Patient_Last->getStoredVal()));
        $tbl->addBodyTr(HTMLTable::makeTd("Expected Check-in").HTMLTable::makeTd($rDate->format('D M jS, Y')));
        $tbl->addBodyTr(HTMLTable::makeTd("Notes").HTMLTable::makeTd($wlRS->Notes->getStoredVal()));


        $gMarkup .= $tbl->generateMarkup();

        $gMarkup .= "</body></html>";

        $subj = "New Anticipated Visit for " .$rDate->format('D M jS, Y') . "; recorded by " . $uS->username . ".";


        // Get the site configuration object
        $config = new Config_Lite(ciCFG_FILE);

        // Send email
        $mail = prepareEmail($config);

        $mail->From = $uS->noreplyAddr;
        $mail->FromName = $config->getString('site', 'Site_Name', 'Hospitality HouseKeeper');
        $mail->addAddress($uS->adminEmailAddr);     // Add a recipient
        $mail->isHTML(true);

        $mail->Subject = $subj;


        $mail->msgHTML($gMarkup);
        $mail->send();

        return $numRec;
    }

}

