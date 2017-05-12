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

    public static function moreRoomsMarkup($currentRoomCount, $isChecked) {

        $attrs = array('id'=>'cbAddnlRoom', 'type'=>'checkbox', 'style'=>'margin-right:.3em;');

        if ($isChecked) {
            $attrs['checked'] = 'checked';
        }
        // fieldset wrapper
        $mk1 = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend', 'Additional Room', array('style'=>'font-weight:bold;'))
                        . HTMLContainer::generateMarkup('p', 'Currently reserving ' . $currentRoomCount . ' room' . ($currentRoomCount == 1 ? '' : 's'), array('style'=>'margin-bottom:10px;'))
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


    public function createCheckinMarkup(\PDO $dbh, $isAuthorized, $constraintsDisabled = FALSE, $omitSelf = TRUE) {

        if ($this->resv->getStatus() === ReservationStatus::Committed || $this->resv->getStatus() === ReservationStatus::Imediate || $this->resv->getStatus() === ReservationStatus::Waitlist) {

            $rescs = $this->findResources($dbh, $isAuthorized, $omitSelf);

            if (isset($rescs[$this->resv->getIdResource()])) {
                $this->selectedResource = $rescs[$this->resv->getIdResource()];
            }

            return $this->createChooserMarkup($dbh, $constraintsDisabled);

        } else {

            return $this->createStaticMarkup($dbh);
        }
    }

    public function createResvMarkup(\PDO $dbh, $isAuthorized, $constraintsDisabled = FALSE, $classId = '') {

        if (($this->resv->getStatus() === ReservationStatus::Committed || $this->resv->getStatus() === ReservationStatus::UnCommitted || $this->resv->getStatus() === ReservationStatus::Waitlist || $this->resv->isNew())) {

            $rescs = $this->findResources($dbh, $isAuthorized);

            if (isset($rescs[$this->resv->getIdResource()])) {
                $this->selectedResource = $rescs[$this->resv->getIdResource()];
            }

            return $this->createChooserMarkup($dbh, $constraintsDisabled, $classId);

        } else {

            return $this->createStaticMarkup($dbh);
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


    public function findResources(\PDO $dbh, $isAuthorized, $omitSelf = TRUE) {

        if ($isAuthorized) {
            $resources = $this->resv->findGradedResources($dbh, $this->checkinDT->format('Y-m-d H:i:s'), $this->checkoutDT->format('Y-m-d H:i:s'), $this->getTotalGuests(), array('room','rmtroom','part'), $omitSelf);
        } else {
            $resources = $this->resv->findResources($dbh, $this->checkinDT->format('Y-m-d H:i:s'), $this->checkoutDT->format('Y-m-d H:i:s'), $this->getTotalGuests(), array('room','rmtroom','part'), $omitSelf);
        }

        return $resources;
    }


    protected function createChooserMarkup(\PDO $dbh, $constraintsDisabled, $classId = '') {

        $resources = array();
        $errorMessage = '';

        // Load available resources
        foreach ($this->resv->getAvailableResources() as $r) {
            $resources[$r->getIdResource()] = array($r->getIdResource(), $r->getTitle(), $r->optGroup);
        }

        // add waitlist option to the top of the list
        $resources[0] = array(0, '-None-', '');


        // Selected resource
        $idResourceChosen = $this->resv->getIdResource();

        if ($this->resv->getStatus() == ReservationStatus::Waitlist) {

            $idResourceChosen = 0;

        } else if (($this->resv->getStatus() == ReservationStatus::Committed || $this->resv->getStatus() == ReservationStatus::UnCommitted) && isset($resources[$this->resv->getIdResource()])) {

            $myResc = $resources[$this->resv->getIdResource()];

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
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('span',
                        HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($resources, $idResourceChosen), array('name'=>'selResource', 'class'=>$classId)), array('id'=>'spanSelResc'))
                        )
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
                        array('class'=>'hhk-panel')),
                        array('style'=>'float:left;'));


        return HTMLContainer::generateMarkup('div', $mk1, array('style'=>'clear:both;'));
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
        $ttg = $this->getTotalGuests();
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

            }
        }

        if ($income > 0 && $size > 0) {
            $cat = FinAssistance::getAssistanceCategory($dbh, $income, $size);
        } else if ($cat == '') {
            $cat = Default_Settings::Rate_Category;
        }

        foreach ($priceModel->getActiveModelRoomRates() as $rs) {

            if ($rs->FA_Category->getStoredVal() == $cat) {
                $catTitle = $rs->Title->getStoredVal();
                $catRate = $rs->Reduced_Rate_1->getStoredVal();
                break;
            }
        }


        $amt = ($priceModel->amountCalculator($nites, $idRoomRate, $cat, $fixedRate, $nites) * (1 + $rateAdjust));

        return array(
            'amt'=> number_format($amt, 2),
            'cat'=>  $cat,
            'catTitle' => $catTitle . ':  $' . ($fixedRate > 0 ? $fixedRate : $catRate));
    }

}
