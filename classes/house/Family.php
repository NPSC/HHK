<?php

/**
 * Description of Family
 *
 * @author Eric
 */
class Family {

    protected $members;
    protected $roleObj;
    protected $rData;
    protected $patientId;

    public function __construct(\PDO $dbh, ReserveData $rData) {

        $this->rData = $rData;
        $this->patientId = 0;

        $this->loadMembers($dbh);

    }

    protected function loadMembers(\PDO $dbh) {

        $uS = Session::getInstance();
        $this->members = array();

        if ($this->rData->getidPsg() > 0) {

            // PSG is defined
            $ngRs = new Name_GuestRS();
            $ngRs->idPsg->setStoredVal($this->rData->getidPsg());
            $rows = EditRS::select($dbh, $ngRs, array($ngRs->idPsg));

            foreach ($rows as $r) {
                $ngrs = new Name_GuestRS();
                EditRS::loadRow($r, $ngrs);

                if ($ngrs->Relationship_Code->getStoredVal() == RelLinkType::Self) {
                    $this->roleObj[$ngrs->idName->getStoredVal()] = new Patient($dbh, $ngrs->idName->getStoredVal(), $ngrs->idName->getStoredVal());
                    $this->roleObj[$ngrs->idName->getStoredVal()]->setPatientRelationshipCode($ngrs->Relationship_Code->getStoredVal());
                    $this->members[$ngrs->idName->getStoredVal()]['role'] = 'p';
                    $this->members[$ngrs->idName->getStoredVal()]['stay'] = ($uS->PatientAsGuest ? '0' : 'x');
                    $this->patientId = $ngrs->idName->getStoredVal();
                } else {
                    $this->roleObj[$ngrs->idName->getStoredVal()] = new Guest($dbh, $ngrs->idName->getStoredVal(), $ngrs->idName->getStoredVal());
                    $this->roleObj[$ngrs->idName->getStoredVal()]->setPatientRelationshipCode($ngrs->Relationship_Code->getStoredVal());
                    $this->members[$ngrs->idName->getStoredVal()]['role'] = 'g';
                    $this->members[$ngrs->idName->getStoredVal()]['stay'] = '0';
                }
            }
        }

        // Load new member?
        if ($this->rData->getId() > 0 && isset($this->members[$this->rData->getId()]) === FALSE) {
            $this->roleObj[$this->rData->getId()] = new Guest($dbh, $this->rData->getId(), $this->rData->getId());
            $this->members[$this->rData->getId()]['role'] = '';
            $this->members[$this->rData->getId()]['stay'] = '0';
        }

        // Load empty member
        $this->roleObj[0] = new Guest($dbh, '0', 0);
        $this->members[0]['role'] = '';
        $this->members[0]['stay'] = '0';


        // Update who is staying
        $this->loadResvGuests($dbh);
    }

    protected function loadResvGuests(\PDO $dbh) {

        if ($this->rData->getIdResv() > 0) {

            $resvGuestRs = new Reservation_GuestRS();
            $resvGuestRs->idReservation->setStoredVal($this->rData->getIdResv());
            $rgs = EditRS::select($dbh, $resvGuestRs, array($resvGuestRs->idReservation));

            foreach ($rgs as $g) {

                if (isset($this->members[$g['idGuest']]) && $this->members[$g['idGuest']]['stay'] != 'x') {
                    $this->members[$g['idGuest']]['stay'] = '1';
                }
            }
        }
    }

    public function createFamilyMarkup(ReservationRS $resvRs) {

        $uS = Session::getInstance();
        $tbl = new HTMLTable();
        $mk1 = '';

        $tbl->addHeaderTr($this->roleObj[0]->getNameObj()->createMarkupHdr($this->rData->getPatLabel(), FALSE) . HTMLTable::makeTh('Staying'));


        // Put the patient first.
        if ($this->getPatientId() > 0) {

            $name = $this->roleObj[$this->getPatientId()]->getNameObj();
            $tbl->addBodyTr($this->roleObj[$this->getPatientId()]->createThinMarkup($this->members[$this->getPatientId()]['stay'], ($this->rData->getidPsg() == 0 ? FALSE : TRUE)));
        }

        foreach ($this->roleObj as $m) {

            // Skip the patient
            if ($m->getIdName() > 0 && $m->getIdName() == $this->getPatientId()) {
                continue;
            }

            $name = $m->getNameObj();
            $tbl->addBodyTr($m->createThinMarkup($this->members[$m->getIdName()]['stay'], ($this->rData->getidPsg() == 0 ? FALSE : TRUE)));
        }

        $hdr = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('span', 'Visitors ')
            . HTMLInput::generateMarkup('Add More', array('type'=>'button', 'id'=>'addMoreVisitors'))
            , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));

        // Waitlist notes
        if ($uS->UseWLnotes) {

            $mk1 = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', $this->rData->getWlNotesLabel(), array('style'=>'font-weight:bold;'))
                . HTMLContainer::generateMarkup('textarea', $resvRs->Checkin_Notes->getStoredVal(), array('name'=>'taCkinNotes', 'rows'=>'2', 'cols'=>'75')),
                array('class'=>'hhk-panel', 'style'=>'clear:both; margin-top:10px; font-size:.9em;'));
            }

        $div = HTMLContainer::generateMarkup('div', $tbl->generateMarkup(array('id'=>'tblFamily')) . $mk1, array('style'=>'padding:5px;', 'class'=>'ui-corner-bottom hhk-panel hhk-tdbox'));

        return array('hdr'=>$hdr, 'div'=>$div);

    }


    public function getPatientId() {
        return $this->patientId;
    }


}
