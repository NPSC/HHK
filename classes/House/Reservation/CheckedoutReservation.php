<?php

namespace HHK\House\Reservation;

use HHK\sec\{SecurityComponent, Session};
use HHK\Exception\RuntimeException;
use HHK\House\ReserveData\ReserveData;
use HHK\House\Resource\AbstractResource;
use HHK\House\Room\RoomChooser;
use HHK\House\Visit\Visit;
use HHK\Tables\EditRS;
use HHK\Tables\Visit\{StaysRS, VisitRS};
use HHK\sec\Labels;
use HHK\HTMLControls\{HTMLContainer, HTMLInput};


/**
 * Description of CheckedoutReservation
 *
 * @author Eric
 */

class CheckedoutReservation extends CheckingIn {

    /**
     * Summary of createMarkup
     * @param \PDO $dbh
     * @throws \HHK\Exception\RuntimeException
     * @return array
     */
    public function createMarkup(\PDO $dbh) {

        if ($this->reserveData->getIdVisit() < 1 || $this->reserveData->getSpan() < 0) {
            throw new RuntimeException('The visit is not defined.');
        }


        $this->createFamilyMarkup($dbh);

        $this->reserveData->setResvSection($this->createAddGuestMarkup($dbh));

        return $this->reserveData->toArray();

    }

    /**
     * Summary of save
     * @param \PDO $dbh
     * @return static
     */
    public function save(\PDO $dbh) {

        // Same room, just add the guests.
        $this->addGuestStay($dbh);

        return $this;
    }

    /**
     * Summary of createFamilyMarkup
     * @param \PDO $dbh
     * @param mixed $formUserData
     * @return void
     */
    protected function createFamilyMarkup(\PDO $dbh, $formUserData = array()) {

        $psgMembers = $this->reserveData->getPsgMembers();

        $this->reserveData->addConcurrentRooms($this->findConflictingReservations($dbh, $this->reserveData->getIdPsg(), $this->reserveData->getIdResv(), $psgMembers, $this->reserveData->getSpanStartDT(), $this->reserveData->getSpanEndDT(), $this->reserveData->getResvTitle()));
        $this->reserveData->addConcurrentRooms($this->findConflictingStays($dbh, $psgMembers, $this->reserveData->getSpanStartDT(), $this->reserveData->getIdPsg(), $this->reserveData->getSpanEndDT(), $this->reserveData->getIdVisit(), $this->reserveData->getSpan()));

        $this->reserveData->setPsgMembers($psgMembers);

        $this->reserveData->setFamilySection($this->family->createFamilyMarkup($dbh, $this->reserveData));

    }

    /**
     * Summary of createAddGuestMarkup
     * @param \PDO $dbh
     * @return array
     */
    protected function createAddGuestMarkup(\PDO $dbh) {

        $uS = Session::getInstance();

        $nowDT = new \DateTime();
        $nowDT->setTime(intval($uS->CheckInTime), 0, 0);

        $resv = new Reservation_1($this->reservRs);

        // Room Chooser
        $roomChooser = new RoomChooser($dbh, $resv, 0, $this->reserveData->getSpanStartDT(), $this->reserveData->getSpanEndDT());

        $resvSectionHeaderPrompt = 'Add ' . Labels::getString('MemberType', 'visitor', 'Guest') . 's; ';

        // Calculate room occupation
        $occs = 0;
        foreach ($this->reserveData->getPsgMembers() as $s) {
            if ($s->getStayObj()->getStay() == 'r') {
                $occs++;
            }
        }

        if ($occs < $roomChooser->getSelectedResource()->getMaxOccupants()) {

            $this->reserveData
            ->setArrivalDT($this->reserveData->getSpanStartDT())
            ->setDepartureDT($this->reserveData->getSpanEndDT());

            $dataArray = $this->createExpDatesControl(FALSE, $this->reserveData->getSpanStartDT()->format('M j, Y'), $this->reserveData->getSpanEndDT()->format('M j, Y'));

        } else {
            $resvSectionHeaderPrompt = 'This room is already at its maximum occupancy.';
            $dataArray['hideCkinBtn'] = TRUE;
        }

        // Room Chooser
        $dataArray['rChooser'] = $roomChooser->createAddGuestMarkup($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN), $this->reserveData->getSpanStatus(), $occs);


        // Reservation status title
        $dataArray['rStatTitle'] = 'Checked Out - Adding Guests';

        // Reservation notes
        $dataArray['notes'] = HTMLContainer::generateMarkup('fieldset',
            HTMLContainer::generateMarkup('legend', 'Visit Notes', array('style'=>'font-weight:bold;'))
            , array('id'=>'hhk-noteViewer', 'class'=>'hhk-panel', 'style'=>'width:100%;'));


        // Collapsing header
        $hdr = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('span', $resvSectionHeaderPrompt, array('style'=>'float:left;font-size:.9em; margin-right: 1em;'))
            . HTMLContainer::generateMarkup('span', '', array('id'=>'addGuestHeader', 'style'=>'float:left;'))
            , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));


        return array('hdr'=>$hdr, 'rdiv'=>$dataArray);
    }

    /**
     * Summary of createExpDatesControl
     * @param mixed $updateOnChange
     * @param mixed $startDate
     * @param mixed $endDate
     * @param mixed $lastVisitMU
     * @return array
     */
    protected function createExpDatesControl($updateOnChange = TRUE, $startDate = FALSE, $endDate = FALSE, $lastVisitMU = '') {

        $uS = Session::getInstance();
        $nowDT = new \DateTime();
        $nowDT->setTime(0, 0, 0);

        $days = '';
        $prefix = '';


        $cidAttr = array('name'=>$prefix.'gstDate', 'readonly'=>'readonly', 'size'=>'14', 'class'=>' ui-state-highlight');


        if (is_null($this->reserveData->getArrivalDT()) === FALSE && is_null($this->reserveData->getDepartureDT()) === FALSE) {
            $days = $this->reserveData->getDepartureDT()->diff($this->reserveData->getArrivalDT(), TRUE)->days;
        }

        $mkup = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('span', 'Arrival: '.
                HTMLInput::generateMarkup(($this->reserveData->getArrivalDateStr()), $cidAttr))
            .HTMLContainer::generateMarkup('span', 'Expected Departure: '.
                HTMLInput::generateMarkup(($this->reserveData->getDepartureDateStr()), array('name'=>$prefix.'gstCoDate', 'readonly'=>'readonly', 'size'=>'14', 'class'=>' ui-state-highlight'))
                , array('style'=>'margin-left:.7em;'))
            .HTMLContainer::generateMarkup('span', 'Expected Days: '.
                HTMLInput::generateMarkup($days, array('name'=>$prefix.'gstDays', 'readonly'=>'readonly', 'size'=>'4', 'class'=>' ui-state-highlight'))
                , array('style'=>'margin-left:.7em;'))
            .HTMLContainer::generateMarkup('span', $lastVisitMU, array('style'=>'margin-left:1em; font-size:.8em;'))
            , array('style'=>'font-size:.9em;', 'id'=>$prefix.'noPicker'));

        return array('mu'=>$mkup, 'defdays'=>$uS->DefaultDays, 'daysEle'=>$prefix.'gstDays', 'updateOnChange'=>$updateOnChange, 'startDate'=>$startDate, 'endDate'=>$endDate);

    }


    /**
     * Summary of addGuestStay
     * @param \PDO $dbh
     * @throws \HHK\Exception\RuntimeException
     * @return CheckedoutReservation
     */
    protected function addGuestStay(\PDO $dbh) {

        $uS = Session::getInstance();
        $visitRs = new VisitRS();

        $this->initialSave($dbh);

        if ($this->reserveData->hasError()) {
            return $this;
        }

        // Modified added guest arrival and depture dates.
        $guestArrDT = new \DateTime($this->reserveData->getArrivalDateStr('Y-m-d 10:0:0'));
        $guestDepDT = new \DateTime($this->reserveData->getDepartureDateStr('Y-m-d 10:0:0'));

        if ($guestArrDT > $guestDepDT) {
            // dates reversed...
            $this->reserveData->addError('Dates are reversed.  ');
            return;
        }

        // GEt visit record
        $stmt = $dbh->query("Select * from visit where idVisit = " . $this->reserveData->getIdVisit() . " and Span = " . $this->reserveData->getSpan());

        if ($stmt->rowCount() == 0) {
            throw new RuntimeException('Visit not found for reservation Id ' . $this->reserveData->getIdResv());
        }

        $vrows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        EditRS::loadRow($vrows[0], $visitRs);

        // Set up comparison dates.
        $spanArrDT = new \DateTime($visitRs->Span_Start->getStoredVal());
        $spanArrDT->setTime(10, 0, 0);

        $spanDepDT = new \DateTime($visitRs->Span_End->getStoredVal());
        $spanDepDT->setTime(10, 0, 0);

        // Checking the stay arrival date
        if ($guestArrDT < $spanArrDT || $guestArrDT > $spanDepDT) {
            // Bad arrival Date
            $this->reserveData->setArrivalDateStr($visitRs->Arrival_Date->getStoredVal());
        }

        // Departure dates.
        if ($guestDepDT < $spanArrDT || $guestDepDT > $spanDepDT) {
            // Bad Departure Date
            $this->reserveData->setDepartureDateStr($visitRs->Span_End->getStoredVal());
        }


        // get the stays
        $stmts = $dbh->query("Select idName, idRoom, Span_Start_Date, Span_End_Date from stays where idVisit = " . $this->reserveData->getIdVisit() . " and Visit_Span = " . $this->reserveData->getSpan());

        // Count people staying during the new guest's time.
        $stays = array();

        while ($s = $stmts->fetch(\PDO::FETCH_ASSOC)) {

            $stayArrDT = new \DateTime($s['Span_Start_Date']);
            $stayArrDT->setTime(10, 0, 0);

            $stayDepDT = new \DateTime($s['Span_End_Date']);
            $stayDepDT->setTime(10, 0, 0);

            // Checking the dates
            if ($guestArrDT > $stayDepDT || $guestDepDT < $stayArrDT) {
                // out of bounds
                continue;
            }

            $stays[$s['idName']] = 1;
            $roomId = $s['idRoom'];
        }



        $addingMembers = array();

        // Guest aleady present?
        foreach ($this->getStayingMembers() as $m) {

            if (isset($stays[$m->getId()]) === FALSE) {

                // Ok to add
                $stayRS = new StaysRS();

                $stayRS->idName->setNewVal($m->getId());
                $stayRS->idVisit->setNewVal($visitRs->idVisit->getStoredVal());
                $stayRS->Visit_Span->setNewVal($visitRs->Span->getStoredVal());
                $stayRS->idRoom->setNewVal($roomId);
                $stayRS->Checkin_Date->setNewVal($this->reserveData->getArrivalDateStr());
                $stayRS->Checkout_Date->setNewVal($this->reserveData->getDepartureDateStr());
                $stayRS->Span_Start_Date->setNewVal($this->reserveData->getArrivalDateStr());
                $stayRS->Span_End_Date->setNewVal($this->reserveData->getDepartureDateStr());
                $stayRS->Expected_Co_Date->setNewVal($this->reserveData->getDepartureDateStr());
                $stayRS->Status->setNewVal($visitRs->Status->getStoredVal());
                $stayRS->Updated_By->setNewVal($uS->username);
                $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
                $addingMembers[] = $stayRS;
            }
        }

        // Amyone left?
        if (count($addingMembers) < 1) {
            $this->reserveData->addError('No one left to stay.  ');
            return;
        }

        // Get the resource
        $resc = null;
        if ($visitRs->idResource->getStoredVal() > 0) {
            $resc = AbstractResource::getResourceObj($dbh, $visitRs->idResource->getStoredVal());
        } else {
            $this->reserveData->addError('Room not found.  ');
            return;
        }

        // Maximym occupants...
        $numOccupants = count($addingMembers) + count($stays);

        if ($numOccupants > $resc->getMaxOccupants()) {
            $this->reserveData->addError("The maximum occupancy (" . $resc->getMaxOccupants() . ") for room " . $resc->getTitle() . " is exceded.  ");
            return;
        }

        // I guess that's it.
        foreach ($addingMembers as $a) {
            EditRS::insert($dbh, $a);
        }

        $this->resc = $resc;
        $this->visit = new Visit($dbh, 0, $visitRs->idVisit->getStoredVal(), NULL, NULL, $resc, $uS->username, $visitRs->Span->getStoredVal());

        return;
    }

    /**
     * Summary of initialSave
     * @param \PDO $dbh
     * @return void
     */
    protected function initialSave(\PDO $dbh) {

        $uS = Session::getInstance();

        // Save members, psg, hospital
        if ($this->family->save($dbh, $_POST, $this->reserveData, $uS->username) === FALSE) {
            return;
        }

        if (count($this->getStayingMembers()) < 1) {
            // Nobody set to stay
            $this->reserveData->addError('Nobody is set to stay for this ' . $this->reserveData->getResvTitle() . '.  ');
            return;
        }


        // Arrival and Departure dates
        try {
            $this->setDates();
        } catch (RuntimeException $hex) {
            $this->reserveData->addError($hex->getMessage());
            return;
        }

    }
}
?>