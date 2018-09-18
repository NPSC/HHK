<?php

/*
 * RoomChooser.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of RoomChooser
 *
 * @author Eric
 */
class RoomChooser {

    /**
     * @var bool
     */
    protected $openCheckin;
    protected $newGuests;
    protected $currentGuests;
    protected $maxOccupants;
    protected $oldResvId;

    /**
     *
     * @var \Reservation_1
     */
    public $resv;
    /**
     *
     * @var \Resource
     */
    protected $selectedResource;
    /**
     *
     * @var \DateTime
     */
    protected $checkinDT;
    /**
     *
     * @var \DateTime
     */
    protected $checkoutDT;


    public function __construct(\PDO $dbh, Reservation_1 $resv, $numNewGuests, $chkinDT = NULL, $chkoutDT = NULL) {

        $uS = Session::getInstance();

        $this->openCheckin = $uS->OpenCheckin;
        $this->newGuests = intval($numNewGuests, 10);
        $this->currentGuests = 0;
        $this->resv = $resv;
        $this->selectedResource = NULL;
        $this->maxOccupants = 0;

        if (is_null($chkinDT) === FALSE && is_string($chkinDT)) {
            $chkinDT = new DateTime($chkinDT);
        }

        if (is_null($chkoutDT) === FALSE && is_string($chkoutDT)) {
            $chkoutDT = new DateTime($chkoutDT);
        }

        $this->checkinDT = $chkinDT;
        $this->checkoutDT = $chkoutDT;

        // Current resource
        if ($resv->getIdResource() > 0) {

            $this->selectedResource = Resource::getResourceObj($dbh, $resv->getIdResource());
            $this->maxOccupants = $this->selectedResource->getMaxOccupants();

            if ($this->resv->getStatus() == ReservationStatus::Staying) {
                $this->currentGuests = $this->selectedResource->getCurrantOccupants($dbh);
            }
        }

    }

    public function setOldResvId($oldResvId) {
        $this->oldResvId = intval($oldResvId);
        return $this;
    }

    public function setNumNewGuests($numNewGuests){
        $this->newGuests = intval($numNewGuests, 10);
        return $this;
    }

    public function getTotalGuests() {
        return $this->newGuests + $this->currentGuests;
    }

    public function getCurrentGuests() {
        return $this->currentGuests;
    }

    public function getSelectedResource() {
        return $this->selectedResource;
    }

    public static function moreRoomsMarkup($currentRoomCount, $isChecked, $currentStatus = ReservationStatus::Staying) {

        $attrs = array('id'=>'cbAddnlRoom', 'type'=>'checkbox', 'style'=>'margin-right:.3em;');

        if ($isChecked) {
            $attrs['checked'] = 'checked';
        }

        $title = 'Currently reserving ';

        switch ($currentStatus) {

            case ReservationStatus::Staying:
                $title = 'Currently using ';
                break;

            case ReservationStatus::Committed:
            case ReservationStatus::UnCommitted:
                $title = 'Currently reserving ';
                break;

            case ReservationStatus::Waitlist:
                $title = 'Currently waitlisted for ';
                break;
        }

        // fieldset wrapper
        $mk1 = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend', 'Additional Room', array('style'=>'font-weight:bold;'))
                        . HTMLContainer::generateMarkup('p', $title . $currentRoomCount . ' room' . ($currentRoomCount == 1 ? '' : 's'), array('style'=>'margin-bottom:10px;'))
                        . HTMLInput::generateMarkup('Put the new guests in a new room', $attrs)
                        . HTMLContainer::generateMarkup('label', 'Put the new guest(s) in a new room', array('for'=>'cbAddnlRoom'))
                        , array('class'=>'hhk-panel')),
                        array('style'=>'float:left;margin-bottom:10px;'));


        return HTMLContainer::generateMarkup('div', $mk1, array('style'=>'clear:both;'));

    }

    public function createConstraintsChooser(\PDO $dbh, $idReservation, $numGuests, $constraintsDisabled = FALSE, $roomTitle = '') {

        $constraintMkup = self::createResvConstMkup($dbh, $idReservation, $constraintsDisabled, '', $this->oldResvId);

        if ($constraintMkup == '') {
            return '';
        }

        $tbl = new HTMLTable();

        $tbl->addBodyTr(HTMLTable::makeTh("Total Guests:", array('class'=>'tdlabel'))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $numGuests, array('id'=>'spnNumGuests','style'=>'font-weight:bold;')), array('style'=>'text-align:center;'))
                );

        if ($roomTitle != '') {
            $tbl->addBodyTr(HTMLTable::makeTh("Room:", array('class'=>'tdlabel'))
                .HTMLTable::makeTd($roomTitle)
                );
        }

        return HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Constraints Chooser', array('style'=>'font-weight:bold;'))
                . $tbl->generateMarkup() . $constraintMkup, array('class'=>'hhk-panel')),
                array('style'=>'float:left;'));

    }


    public function createCheckinMarkup(\PDO $dbh, $isAuthorized, $constraintsDisabled = FALSE, $omitSelf = TRUE, $overrideMaxOcc = 0) {

        if ($this->resv->getStatus() === ReservationStatus::Committed || $this->resv->getStatus() === ReservationStatus::Imediate || $this->resv->getStatus() === ReservationStatus::Waitlist) {

            $rescs = $this->findResources($dbh, $isAuthorized, $omitSelf, $overrideMaxOcc);

            if (isset($rescs[$this->resv->getIdResource()])) {
                $this->selectedResource = $rescs[$this->resv->getIdResource()];
            }

            return $this->createChooserMarkup($dbh, $constraintsDisabled);

        } else {

            return $this->createStaticMarkup($dbh);
        }
    }

    public function createResvMarkup(\PDO $dbh, $isAuthorized, $classId = '') {

        if (($this->resv->getStatus() === ReservationStatus::Committed || $this->resv->getStatus() === ReservationStatus::UnCommitted || $this->resv->getStatus() === ReservationStatus::Waitlist || $this->resv->isNew())) {

            $rescs = $this->findResources($dbh, $isAuthorized, TRUE, 1);

            if (isset($rescs[$this->resv->getIdResource()])) {
                $this->selectedResource = $rescs[$this->resv->getIdResource()];
            }

            return $this->createChooserMarkup($dbh, FALSE, $classId);

        } else {

            return $this->createStaticMarkup($dbh);
        }
    }

    public function createAddGuestMarkup(\PDO $dbh, $isAuthorized) {

        if ($this->resv->getStatus() === ReservationStatus::Staying) {

            $this->findResources($dbh, $isAuthorized, TRUE, 0);
//
//            if (isset($rescs[$this->resv->getIdResource()])) {
//                $this->selectedResource = $rescs[$this->resv->getIdResource()];
//            }

            return $this->createAddedMarkup($dbh, FALSE);
        }
    }

    public function createChangeRoomsMarkup(\PDO $dbh, VisitCharges $visitCharge, $idGuest, $isAuthorized) {

        $uS = Session::getInstance();

        // get empty rooms
        $rescs = $this->findResources($dbh, $isAuthorized, FALSE);

        $rmBigEnough[] = array(0 => '0', 1 => '');

        foreach ($rescs as $r) {
            $rmBigEnough[] = array($r->getIdResource(), $r->getTitle(), $r->optGroup);
        }

        $paymentMarkup = '';

        $table = new HTMLTable();
        $table->addHeaderTr(HTMLTable::makeTh('Change Rooms', array('colspan' => '2')));

        if (count($rmBigEnough) > 1) {
            // Send along a room selector
            $table->addBodyTr(
                    HTMLTable::makeTd('From room:', array('class' => 'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup($this->selectedResource->getTitle(), array('id'=>'myRescId', 'style'=>'border:none;', 'data-pmdl'=>$uS->RoomPriceModel, 'data-idresc'=>$this->resv->getIdResource(), 'readonly'=>'readonly')))
                    );

            $table->addBodyTr(
                    HTMLTable::makeTd('Change to:', array('class' => 'tdlabel'))
                    . HTMLTable::makeTd(HTMLSelector::generateMarkup(
                              HTMLSelector::doOptionsMkup($rmBigEnough, '0', FALSE), array('id' => 'selResource', 'name' => 'selResource', 'class' => 'hhk-feeskeys hhk-chgroom'))
                            . HTMLContainer::generateMarkup('span', '', array('id'=>'rmDepMessage', 'style'=>'color:red;display:none'))));

            $table->addBodyTr(
                HTMLTable::makeTd('As of:', array('class' => 'tdlabel', 'rowspan'=>'2'))
                . HTMLTable::makeTd(
                    HTMLInput::generateMarkup('rpl', array('name'=>'rbReplaceRoom', 'id'=>'rbReplaceRoomrpl', 'type'=>'radio', 'checked'=>'checked', 'class'=>'hhk-feeskeys'))
                    .HTMLContainer::generateMarkup('label', 'Start of Visit', array('style'=>'margin-left:.3em;', 'for'=>'rbReplaceRoomrpl'))
            ));
            $table->addBodyTr(
                HTMLTable::makeTd(
                    HTMLInput::generateMarkup('new', array('name'=>'rbReplaceRoom', 'id'=>'rbReplaceRoomnew', 'type'=>'radio', 'class'=>'hhk-feeskeys'))
                    .HTMLContainer::generateMarkup('label', 'Date', array('style'=>'margin-left:.3em; margin-right:.3em;', 'for'=>'rbReplaceRoomnew'))
                    .HTMLInput::generateMarkup('', array('name'=>'resvChangeDate', 'class'=>'hhk-feeskeys ckdate', 'readonly'=>'readonly'))
                ));

            $table->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('span','', array('id'=>'rmChgMsg', 'style'=>'color:red;display:none')), array('colspan'=>'2')));

            // Key Deposit
            if ($uS->KeyDeposit) {

                $keyDepAmount = $visitCharge->getKeyFeesPaid();

                if ($keyDepAmount == 0) {
                    $paymentMarkup = PaymentChooser::createChangeRoomMarkup($dbh, $idGuest, $this->resv->getIdRegistration(), $visitCharge);
                }
            }

        } else {

            $table->addBodyTr(HTMLTable::makeTd('No available rooms'));
        }

        return $table->generateMarkup(array('id' => 'moveTable', 'style' => 'float:left; margin-right:.5em; margin-top:.3em; max-width:350px;')) . $paymentMarkup;
    }


    public function findResources(\PDO $dbh, $isAuthorized, $omitSelf = TRUE, $overrideMaxOcc = 0) {

        if ($isAuthorized) {
            $resources = $this->resv->findGradedResources($dbh, $this->checkinDT->format('Y-m-d H:i:s'), $this->checkoutDT->format('Y-m-d H:i:s'), ($overrideMaxOcc == 0 ? $this->getTotalGuests() : $overrideMaxOcc), array('room','rmtroom','part'), $omitSelf);
        } else {
            $resources = $this->resv->findResources($dbh, $this->checkinDT->format('Y-m-d H:i:s'), $this->checkoutDT->format('Y-m-d H:i:s'), ($overrideMaxOcc == 0 ? $this->getTotalGuests() : $overrideMaxOcc), array('room','rmtroom','part'), $omitSelf);
        }

        return $resources;
    }

    public function makeRoomsArray() {

        $uS = Session::getInstance();

        $resources = $this->resv->getAvailableResources();
        $resArray = array();

        foreach ($resources as $rc) {

            if ($this->getSelectedResource() != NULL && $rc->getIdResource() == $this->getSelectedResource()->getIdResource()) {
                $assignedRate = $this->resv->getFixedRoomRate();
            } else {
                $assignedRate = $rc->getRate($uS->guestLookups['Static_Room_Rate']);
            }

            $resArray[$rc->getIdResource()] = array(
                "maxOcc" => $rc->getMaxOccupants(),
                "rate" => $assignedRate,
                "title" => $rc->getTitle(),
                'key' => $rc->getKeyDeposit($uS->guestLookups[GL_TableNames::KeyDepositCode]),
                'status' => 'a'
            );
        }

        // Blank
        $resArray['0'] = array(
            "maxOcc" => 0,
            "rate" => 0,
            "title" => '',
            'key' => 0,
            'status' => ''
        );

        return $resArray;
    }

    public function makeRoomSelector($resOptions, $idResourceChosen) {

        return HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($resOptions, $idResourceChosen, FALSE), array('name'=>'selResource'));
    }

    public function makeRoomSelectorOptions() {

        $resOptions[] = array(0, '-None-', '');

        // Load available resources
        foreach ($this->resv->getAvailableResources() as $r) {
            $resOptions[$r->getIdResource()] = array($r->getIdResource(), $r->getTitle(), $r->optGroup);
        }

        return $resOptions;
    }

    public function getRoomSelectionError(\PDO $dbh, $resOptions) {

        $errorMessage = '';

        if (($this->resv->getStatus() == ReservationStatus::Committed || $this->resv->getStatus() == ReservationStatus::UnCommitted || $this->resv->getStatus() == ReservationStatus::Staying) && isset($resOptions[$this->resv->getIdResource()])) {

            $myResc = $resOptions[$this->resv->getIdResource()];

            if (isset($myResc[2]) && $myResc[2] != '') {
                $errorMessage = $myResc[2];
            }

        } else if ($this->resv->getIdResource() > 0) {

            $untestedRescs = $this->resv->getUntestedResources();

            if (isset($untestedRescs[$this->resv->getIdResource()])) {

                $errorMessage = 'Room ' . $this->selectedResource->getTitle() . ' is not suitable.';

            } else if ($this->selectedResource->getCurrantOccupants($dbh) > 0) {

                $errorMessage = 'Room ' . $this->selectedResource->getTitle() . ' is already in use.';

            } else {

                $errorMessage = 'Room ' . $this->selectedResource->getTitle() . ' may be too small.';
            }
        }

        return $errorMessage;
    }

    protected function createChooserMarkup(\PDO $dbh, $constraintsDisabled, $classId = '') {

        $resOptions = $this->makeRoomSelectorOptions();

        $errorMessage = $this->getRoomSelectionError($dbh, $resOptions);

        $resvConstraints = $this->resv->getConstraints($dbh);
        $constraintMkup = '';

        if (count($resvConstraints->getConstraints()) > 0) {

            $constraintMkup = self::createResvConstMkup($dbh, $this->resv->getIdReservation(), $constraintsDisabled, $classId, $this->oldResvId);

            if ($constraintMkup == '') {
                $constraintMkup = "<p style='padding:4px;'>(No Room Attributes Selected.)<p>";
            }
        }

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(HTMLTable::makeTh("Total Guests") . HTMLTable::makeTh('Room', array('id'=>'hhk-roomChsrtitle')));

        $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $this->getTotalGuests(), array('id'=>'spnNumGuests','style'=>'font-weight:bold;')), array('style'=>'text-align:center;'))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $this->makeRoomSelector($resOptions, $this->resv->getIdResource()), array('id'=>'spanSelResc')))
                );

        // set up room suitability message area
        $errArray = array('class'=>'ui-state-highlight', 'id'=>'hhkroomMsg');
        if ($errorMessage == '') {
            $errArray['style'] = 'display:none;';
        }

        $errorMarkup = HTMLContainer::generateMarkup('p',
                HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-info', 'style'=>'float: left; margin-right: .3em;margin-top:1px;'))
                . $errorMessage, $errArray);


        // fieldset wrapper
        $mk1 = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend', 'Room Chooser', array('style'=>'font-weight:bold;'))
                        . $tbl->generateMarkup(array('id'=>'tblRescList'))
                        . $errorMarkup
                        . HTMLContainer::generateMarkup('div', $constraintMkup, array('style'=>'clear:left; float:left;')),
                        array('class'=>'hhk-panel'))
                        , array('style'=>'float:left;')
                );


        return $mk1;
    }

    protected function createStaticMarkup(\PDO $dbh) {

        $roomSelectedMsg = '';

        if (is_null($this->selectedResource) === FALSE) {

            $pass = $this->resv->testResource($dbh, $this->selectedResource);

            if ($pass === FALSE) {
                $roomSelectedMsg =  HTMLContainer::generateMarkup('p', 'Room ' . $this->selectedResource->getTitle() . ' is not suitable.', array('class'=>'ui-state-error'));
            }
        } else {
                HTMLContainer::generateMarkup('p', $roomSelectedMsg = 'Missing a room assignment.', array('class'=>'ui-state-error'));

        }

        $constraintMkup = self::createResvConstMkup($dbh, $this->resv->getIdReservation(), TRUE, '', $this->oldResvId);

        $ttbl = new HTMLTable();

        $ttbl->addBodyTr( HTMLTable::makeTh("Total Guests:")
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $this->getTotalGuests(), array('id'=>'spnNumGuests','style'=>'font-weight:bold;')), array('style'=>'text-align:center;'))
                .(is_null($this->selectedResource) ? '' : HTMLTable::makeTh('Room:'). HTMLTable::makeTd($this->selectedResource->getTitle(), array('style'=>'font-weight:bold;')))
                .HTMLTable::makeTh('Nights:') . HTMLTable::makeTd($this->resv->getExpectedDays(), array('style'=>'text-align:center;font-weight:bold;'))
                );

        if ($constraintMkup == '') {
            $constraintMkup = "<p style='padding:4px;'>(No Room Attributes Selected.)<p>";
        }

        return HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Room Info', array('style'=>'font-weight:bold;'))
                //.HTMLContainer::generateMarkup('div', $this->resv->getStatusIcon(), array('style'=>'float:right;'))
                . $ttbl->generateMarkup() . $constraintMkup . $roomSelectedMsg, array('class'=>'hhk-panel')),
                array('style'=>'float:left;'));

    }

    protected function createAddedMarkup(\PDO $dbh, $constraintsDisabled) {

        $resOptions = $this->makeRoomSelectorOptions();

        $errorMessage = $this->getRoomSelectionError($dbh, $resOptions);

        $resvConstraints = $this->resv->getConstraints($dbh);
        $constraintMkup = '';

        if (count($resvConstraints->getConstraints()) > 0) {

            $constraintMkup = self::createResvConstMkup($dbh, $this->resv->getIdReservation(), $constraintsDisabled, '', $this->oldResvId);

            if ($constraintMkup == '') {
                $constraintMkup = "<p style='padding:4px;'>(No Room Attributes Selected.)<p>";
            }
        }

        // Current room
        $curRoomMarkup = $this->selectedResource->getTitle();

        if ($this->currentGuests >= $this->selectedResource->getMaxOccupants()) {
            $curRoomMarkup .= ' (Full)';
        }

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(HTMLTable::makeTh("Existing Guests")
                . HTMLTable::makeTh("New Guests")
                . HTMLTable::makeTh("Current Room")
                . HTMLTable::makeTh('New Room', array('id'=>'hhk-roomChsrtitle')));

        $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $this->getCurrentGuests()), array('style'=>'text-align:center;'))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('id'=>'spnNumGuests')), array('style'=>'text-align:center;'))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $curRoomMarkup), array('style'=>'text-align:center;'))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $this->makeRoomSelector($resOptions, $this->resv->getIdResource()), array('id'=>'spanSelResc')))
                );

        // set up room suitability message area
        $errArray = array('class'=>'ui-state-highlight', 'id'=>'hhkroomMsg');
        if ($errorMessage == '') {
            $errArray['style'] = 'display:none;';
        }

        $errorMarkup = HTMLContainer::generateMarkup('p',
                HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-info', 'style'=>'float: left; margin-right: .3em;'))
                . $errorMessage, $errArray);


        // fieldset wrapper
        $mk1 = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend', 'Room Chooser', array('style'=>'font-weight:bold;'))
                        . $tbl->generateMarkup(array('id'=>'tblRescList'))
                        . $errorMarkup
                        . HTMLContainer::generateMarkup('div', $constraintMkup, array('style'=>'clear:left; float:left;')),
                        array('class'=>'hhk-panel'))
                        , array('style'=>'float:left;')
                );


        return $mk1;

    }
    public static function createResvConstMkup(\PDO $dbh, $resvId, $disableCtrl = FALSE, $classId = '', $oldResvId = 0) {

        $tbl = new HTMLTable();
        $hasCtrls = FALSE;

        $rhasCtrls = self::makeConstraintsCheckboxes(new ConstraintsReservation($dbh, $resvId, $oldResvId), $tbl, $disableCtrl, $classId . ' hhk-constraintsCB');
        $vhasCtrls = self::makeConstraintsCheckboxes(new ConstraintsVisit($dbh, $resvId, $oldResvId), $tbl, $disableCtrl, $classId);

        if ($rhasCtrls || $vhasCtrls) {
            $hasCtrls = TRUE;
        }

        if ($hasCtrls) {
            $rtn = $tbl->generateMarkup(array('id'=>'hhk-constraintsTbl'));
        } else {
            $rtn = '';
        }

        return $rtn;
    }

    protected static function makeConstraintsCheckboxes($constraints, &$tbl, $disableCtrl, $classId) {

        $hasCtrls = FALSE;

        foreach ($constraints->getConstraints() as $c) {

            $attrs = array('type'=>'checkbox', 'id'=>'cbRS'.$c['idConstraint'], 'name'=>'cbRS['.$c['idConstraint'] . ']', 'data-cnid'=>$c['idConstraint']);

            if ($c['isActive'] == 1) {
                $attrs['checked'] = 'checked';
            }

            // disable control and only show checked constraints
            if ($disableCtrl && $c['isActive'] == 1) {
                $attrs['disabled'] = 'disabled';
            } else if ($disableCtrl) {
                continue;
            }

            if ($classId != '') {
                $attrs['class'] = $classId;
            }

            $hasCtrls = TRUE;

            $tbl->addBodyTr(
                HTMLTable::makeTd($c['Title'], array('class'=>'tdlabel'))
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', $attrs))
                );

        }

        return $hasCtrls;
    }


    public static function roomAmtCalculation(\PDO $dbh, $post) {

        $uS = Session::getInstance();
        $income = 0;
        $size = 0;
        $cat = '';
        $catTitle = '';
        $catRate = 0;
        $nites = 1;
        $guestNites = 1;
        $numberGuests = 1;
        $credit = 0;
        $idRoomRate = 0;
        $fixedRate = 0;
        $rateAdjust = 0;

        $priceModel = PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

        if (isset($post['income'])) {
            $strIncome = str_ireplace(',', '', $post['income']);
            $income = intval(filter_var($strIncome,FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($post['hhsize'])) {
            $size = intval(filter_var($post['hhsize'],FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($post['nites'])) {
            $nites = intval(filter_var($post['nites'],FILTER_SANITIZE_NUMBER_INT), 10);

            if ($nites < 1) {
                $nites = 1;
            }

            $guestNites = $nites;
        }

        if (isset($post['credit'])) {
            $credit = intval(filter_var($post['credit'],FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($post['rcat'])) {
            $cat = filter_var($post['rcat'],FILTER_SANITIZE_STRING);

        }

        if (isset($post['fxd'])) {
            $fixedRate = floatval(filter_var($post['fxd'],FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
        }

        if (isset($post['adj'])) {
            $rateAdjust = floatval(filter_var($post['adj'],FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
        }

        if (isset($post['gsts'])) {
            $numberGuests = intval(filter_var($post['gsts'],FILTER_SANITIZE_NUMBER_INT), 10);
        }


        $priceModel->setCreditDays($credit);
        $priceModel->setVisitStatus(VisitStatus::CheckedIn);

        if (isset($post['vid'])) {
            $idVisit = intval(filter_var($post['vid'],FILTER_SANITIZE_NUMBER_INT), 10);

            if ($idVisit > 0) {

                $visit = new Visit($dbh, 0, $idVisit);

                $idRoomRate = $visit->getIdRoomRate();
                $fixedRate = $visit->getPledgedRate();
                $cat = $visit->getRateCategory();

                // On leave visits - get previous pay rate
                if ($uS->EmptyExtendLimit > 0) {

                    $vol = new Visit_onLeaveRS();
                    $vol->idVisit->setStoredVal($idVisit);
                    $rows = EditRS::select($dbh, $vol, array($vol->idVisit));

                    if (count($rows) > 0) {

                        EditRS::loadRow($rows[0], $vol);

                        $idRoomRate = $vol->idRoom_rate->getStoredVal();
                        $fixedRate = $vol->Pledged_Rate->getStoredVal();
                        $cat = $vol->Rate_Category->getStoredVal();
                    }

                }

                $priceModel->setVisitStatus($visit->getVisitStatus());

                $visitCharges = new VisitCharges($idVisit);
                $visitCharges->sumPayments($dbh)
                        ->sumCurrentRoomCharge($dbh, $priceModel, 0, TRUE);

                $priceModel->setCreditDays($visitCharges->getNightsPaid());

                $guestNites = count($visit->stays) * $nites;

            } else {
                $guestNites = $numberGuests * $nites;
            }
        }

        if ($income > 0 && $size > 0) {
            $cat = FinAssistance::getAssistanceCategory($dbh, $income, $size);
        } else if ($cat == '') {
            $cat = Default_Settings::Rate_Category;
        }

        $amt = ($priceModel->amountCalculator($nites, $idRoomRate, $cat, $fixedRate, $guestNites) * (1 + $rateAdjust));

        foreach ($priceModel->getActiveModelRoomRates() as $rs) {

            if ($rs->FA_Category->getStoredVal() == $cat) {
                $catTitle = $rs->Title->getStoredVal();
                $catRate = $rs->Reduced_Rate_1->getStoredVal();
                break;
            }
        }


        return array(
            'amt'=> number_format($amt, 2),
            'cat'=>  $cat,
            'catTitle' => $catTitle . ':  $' . ($fixedRate > 0 ? $fixedRate : $catRate));
    }

}
