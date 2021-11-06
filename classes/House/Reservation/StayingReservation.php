<?php

namespace HHK\House\Reservation;

use HHK\Exception\RuntimeException;
use HHK\HTMLControls\HTMLContainer;
use HHK\House\Family\Family;
use HHK\House\ReserveData\ReserveData;
use HHK\House\Resource\AbstractResource;
use HHK\House\Room\RoomChooser;
use HHK\House\Visit\Visit;
use HHK\SysConst\VisitStatus;
use HHK\Tables\EditRS;
use HHK\Tables\Reservation\ReservationRS;
use HHK\Tables\Visit\VisitRS;
use HHK\sec\{SecurityComponent, Session};
use HHK\sec\Labels;

/**
 * Description of StayingReservation
 *
 * @author Eric
 */

class StayingReservation extends CheckingIn {

    public function createMarkup(\PDO $dbh) {

        if ($this->reserveData->getIdVisit() < 1 || $this->reserveData->getSpan() < 0) {
            throw new RuntimeException('The visit is not defined.');
        }

        $this->createFamilyMarkup($dbh);

        $this->reserveData->setResvSection($this->createAddGuestMarkup($dbh));

        return $this->reserveData->toArray();

    }

    public function save(\PDO $dbh, $post) {

        // Check for new room
        if (isset($post['cbNewRoom'])) {
            // New Room
            $this->reserveData->setIdResv(0);
            $this->reserveData->setIdVisit(0);
            $this->reserveData->setSpan(0);
            $post['rid'] = 0;
            $post['vid'] = 0;
            $post['span'] = 0;
            $post['rbPriGuest'] = 0;
            $post['resvCkinNow'] = 'yes';

            $checkingIn = new ActiveReservation($this->reserveData, new ReservationRS(), new Family($dbh, $this->reserveData, TRUE));
            $checkingIn->save($dbh, $post);
            return $checkingIn;

        } else {
            // Same room, just add the guests.
            $this->addGuestStay($dbh, $post);
        }

        return $this;
    }

    protected function createFamilyMarkup(\PDO $dbh) {

        $psgMembers = $this->reserveData->getPsgMembers();

        $this->reserveData->addConcurrentRooms($this->findConflictingReservations($dbh, $this->reserveData->getIdPsg(), $this->reserveData->getIdResv(), $psgMembers, $this->reserveData->getSpanStartDT(), $this->reserveData->getSpanEndDT(), $this->reserveData->getResvPrompt()));
        $this->reserveData->addConcurrentRooms($this->findConflictingStays($dbh, $psgMembers, $this->reserveData->getSpanStartDT(), $this->reserveData->getIdPsg(),$this->reserveData->getSpanEndDT(), $this->reserveData->getIdVisit(), $this->reserveData->getSpan()));

        $this->reserveData->setPsgMembers($psgMembers);

        $this->reserveData->setFamilySection($this->family->createFamilyMarkup($dbh, $this->reserveData));

    }

    protected function createAddGuestMarkup(\PDO $dbh) {

        $uS = Session::getInstance();

        $resvSectionHeaderPrompt = 'Add ' . Labels::getString('MemberType', 'visitor', 'Guest') . 's:';

        $nowDT = new \DateTime();
        $nowDT->setTime(intval($uS->CheckInTime), 0, 0);

        $resv = new Reservation_1($this->reservRs);

        // Dates
        $this->reserveData->setArrivalDT($nowDT);


        // Room Chooser
        $roomChooser = new RoomChooser($dbh, $resv, 1, $resv->getExpectedArrival(), $resv->getExpectedDeparture());
        $dataArray['rChooser'] = $roomChooser->createAddGuestMarkup($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN));

        $dataArray = array_merge($dataArray, $this->createExpDatesControl(TRUE, $this->reserveData->getSpanStartDT()->format('M j, Y')));

        // Vehicles
        if ($uS->TrackAuto) {
            $dataArray['vehicle'] = $this->vehicleMarkup($dbh);
        }

        // Reservation status title
        $dataArray['rStatTitle'] = 'Adding Guests';

        // Reservation notes
        $dataArray['notes'] = HTMLContainer::generateMarkup('fieldset',
            HTMLContainer::generateMarkup('legend', 'Visit Notes', array('style'=>'font-weight:bold;'))
            , array('id'=>'hhk-noteViewer', 'style'=>'clear:left; float:left; width:90%; font-size: 0.9em;', 'class'=>'hhk-panel'));


        // Collapsing header
        $hdr = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('span', $resvSectionHeaderPrompt, array('style'=>'float:left;font-size:.9em; margin-right: 1em;'))
            . HTMLContainer::generateMarkup('span', '', array('id'=>'addGuestHeader', 'style'=>'float:left;'))
            , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));


        return array('hdr'=>$hdr, 'rdiv'=>$dataArray);
    }

    protected function addGuestStay(\PDO $dbh, $post) {

        $uS = Session::getInstance();
        $visitRs = new VisitRs();


        if ($this->reserveData->hasError()) {
            return;
        }

        // Does visit exist
        $stmt = $dbh->query("Select * from visit where Status = '" . VisitStatus::CheckedIn . "' and idReservation = " . $this->reserveData->getIdResv() . ";");

        if ($stmt->rowCount() == 0) {
            throw new RuntimeException('Visit not found for reservation Id ' . $this->reserveData->getIdResv());
        }

        $vrows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        EditRS::loadRow($vrows[0], $visitRs);

        // Checking the new guest stay dates.
        $this->checkVisitDates($uS->CheckInTime);

        if ($this->reserveData->hasError()) {
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
        $numOccupants = $resc->getCurrantOccupants($dbh) + count($this->getStayingMembers());

        if ($numOccupants > $resc->getMaxOccupants()) {
            $this->reserveData->addError("The maximum occupancy (" . $resc->getMaxOccupants() . ") for room " . $resc->getTitle() . " is exceded.  ");
            return;
        }

        // Save any new guests now.  (after error checking)
        $this->initialSave($dbh, $post);

        // open visit
        $visit = new Visit($dbh, 0, $visitRs->idVisit->getStoredVal(), NULL, NULL, $resc, $uS->username, $visitRs->Span->getStoredVal());

        // Add guests
        foreach ($this->getStayingMembers() as $m) {

            if ($uS->PatientAsGuest === FALSE && $m->isPatient()) {
                $this->reserveData->addError('Patients cannot stay.  ');
                continue;
            }

            $visit->addGuestStay($m->getId(), $this->reserveData->getArrivalDT()->format('Y-m-d H:i:s'), $this->reserveData->getArrivalDT()->format('Y-m-d H:i:s'), $this->reserveData->getDepartureDT()->format('Y-m-d H:i:s'));
        }


        //
        // Checkin  Saves visit
        //
        $visit->checkin($dbh, $uS->username);

        $this->payResult = NULL;

        $this->resc = $resc;
        $this->visit = new Visit($dbh, 0, $visitRs->idVisit->getStoredVal(), NULL, NULL, $resc, $uS->username, $visitRs->Span->getStoredVal());

        return;
    }

}
?>