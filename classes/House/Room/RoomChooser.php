<?php

namespace HHK\House\Room;

use HHK\Purchase\{FinAssistance, VisitCharges};
use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector, HTMLTable};
use HHK\House\Constraint\{ConstraintsReservation, ConstraintsVisit};
use HHK\House\Reservation\Reservation_1;
use HHK\House\Resource\AbstractResource;
use HHK\House\Visit\Visit;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\SysConst\DefaultSettings;
use HHK\SysConst\{GLTableNames, ReservationStatus, VisitStatus};
use HHK\Tables\EditRS;
use HHK\Tables\Visit\Visit_onLeaveRS;
use HHK\sec\Session;
use HHK\sec\Labels;

/*
 * RoomChooser.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2023 <nonprofitsoftwarecorp.org>
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
    protected $maxRoomsPerPatient;
    protected $oldResvId;

    /**
     *
     * @var Reservation_1
     */
    public $resv;
    /**
     *
     * @var AbstractResource
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
        $this->maxRoomsPerPatient = $uS->RoomsPerPatient;
        $this->newGuests = intval($numNewGuests, 10);
        $this->currentGuests = 0;
        $this->resv = $resv;
        $this->selectedResource = NULL;
        $this->maxOccupants = 0;

        if (is_null($chkinDT) === FALSE && is_string($chkinDT)) {
            $chkinDT = new \DateTime($chkinDT);
        }

        if (is_null($chkoutDT) === FALSE && is_string($chkoutDT)) {
            $chkoutDT = new \DateTime($chkoutDT);
        }

        $this->checkinDT = $chkinDT;
        $this->checkoutDT = $chkoutDT;

        // Current resource
        if ($resv->getIdResource() > 0) {

            $this->selectedResource = AbstractResource::getResourceObj($dbh, $resv->getIdResource());
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

//     public function createConstraintsChooser(\PDO $dbh, $idReservation, $numGuests, $constraintsDisabled = FALSE, $roomTitle = '') {

//         $constraintMkup = self::createResvConstMkup($dbh, $idReservation, $constraintsDisabled, '', $this->oldResvId);

//         if ($constraintMkup == '') {
//             return '';
//         }

//         $tbl = new HTMLTable();

//         $tbl->addBodyTr(HTMLTable::makeTh("Total Guests:", array('class'=>'tdlabel'))
//                 .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $numGuests, array('id'=>'spnNumGuests','style'=>'font-weight:bold;')), array('style'=>'text-align:center;'))
//                 );

//         if ($roomTitle != '') {
//             $tbl->addBodyTr(HTMLTable::makeTh("Room:", array('class'=>'tdlabel'))
//                 .HTMLTable::makeTd($roomTitle)
//                 );
//         }

//         return HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('fieldset',
//                 HTMLContainer::generateMarkup('legend', 'Constraints Chooser', array('style'=>'font-weight:bold;'))
//                 . $tbl->generateMarkup() . $constraintMkup, array('class'=>'hhk-panel')),
//                 array('style'=>'float:left;'));

//     }


    public function createCheckinMarkup(\PDO $dbh, $isAuthorized, $constraintsDisabled = FALSE, $omitSelf = TRUE, $overrideMaxOcc = 0) {

        if ($this->resv->getStatus() === ReservationStatus::Committed || $this->resv->getStatus() === ReservationStatus::Waitlist) {

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

    public function createAddGuestMarkup(\PDO $dbh, $isAuthorized, $visitStatus = '', $numOccupants = 0) {

        if (($visitStatus == '' || $visitStatus == VisitStatus::CheckedIn) && $this->resv->getStatus() === ReservationStatus::Staying) {

            $this->findResources($dbh, $isAuthorized, FALSE, 0);

            return $this->createAddedMarkup($dbh, TRUE);

        } else {
            return $this->createStaticMarkup($dbh, $numOccupants);
        }
    }

    public function createChangeRoomsMarkup(\PDO $dbh, $idGuest, $isAuthorized) {

        $table = new HTMLTable();
        $table->addHeaderTr(HTMLTable::makeTh('Change Rooms from ' . $this->selectedResource->getTitle(), array('colspan' => '2')));

        $table->addBodyTr(
            HTMLTable::makeTd('As of:', array('class' => 'tdlabel', 'rowspan'=>'2'))
            . HTMLTable::makeTd(
                HTMLInput::generateMarkup('rpl', array('name'=>'rbReplaceRoom', 'id'=>'rbReplaceRoomrpl', 'type'=>'radio'))
                .HTMLContainer::generateMarkup('label', 'Start of Visit Span - '. $this->checkinDT->format('M d, Y'), array('style'=>'margin-left:.3em;', 'for'=>'rbReplaceRoomrpl'
                    , 'title'=>'The visit span is the date of the last room change, rate change, or start of visit.'))
        ));

        $table->addBodyTr(
            HTMLTable::makeTd(
                HTMLInput::generateMarkup('new', array('name'=>'rbReplaceRoom', 'id'=>'rbReplaceRoomnew', 'type'=>'radio'))
                .HTMLContainer::generateMarkup('label', 'Date', array('style'=>'margin-left:.3em; margin-right:.3em;', 'for'=>'rbReplaceRoomnew'))
                .HTMLInput::generateMarkup('', array('name'=>'resvChangeDate', 'class'=>'hhk-feeskeys'))
            ));

        $table->addBodyTr(
            HTMLTable::makeTd('Change to:', array('class' => 'tdlabel', 'id'=>'hhk-roomChsrtitle'))
            . HTMLTable::makeTd($this->createChangeRoomsSelector($dbh, $isAuthorized)
                . HTMLContainer::generateMarkup('span', '', array('id'=>'rmDepMessage', 'style'=>'margin-left: 0.8em; display:none'))));

        $table->addBodyTr(
            HTMLTable::makeTd(
                HTMLInput::generateMarkup('', array('name'=>'cbUseDefaultRate', 'id'=>'cbUseDefaultRate', 'checked'=>'checked', 'type'=>'checkbox'))
                .HTMLContainer::generateMarkup('label', 'Change to the new room rate', array('style'=>'margin-left:.3em; margin-right:.3em;', 'for'=>'cbUseDefaultRate'))
                , array('colspan'=>'2'))
            , array('id'=>'trUseDefaultRate', 'style'=>'display:none; text-align:center;'));

        $table->addBodyTr(
            HTMLTable::makeTd('', array('colspan'=>'2', 'id'=>'rmChgMsg', 'style'=>'color:red;display:none')));

        return $table->generateMarkup(array('id' => 'moveTable', 'style' => 'margin-right:.5em; margin-top:.3em; max-width:350px;'));
    }

    public function createChangeRoomsSelector(\PDO $dbh, $isAuthorized) {

        // get empty rooms
        $rescs = $this->findResources($dbh, $isAuthorized, FALSE);

        // Include blank option
        $rmBigEnough[] = array(0 => '0', 1 => '');

        foreach ($rescs as $r) {
            $rmBigEnough[] = array($r->getIdResource(), $r->getTitle(), $r->optGroup);
        }

        return HTMLSelector::generateMarkup(
                HTMLSelector::doOptionsMkup($rmBigEnough, '0', FALSE), array('id' => 'selResource', 'name' => 'selResource', 'class' => 'hhk-chgroom'));

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
                'defaultRateCat' => $rc->getDefaultRoomCategory(),
                "title" => $rc->getTitle(),
                'key' => $rc->getKeyDeposit($uS->guestLookups[GLTableNames::KeyDepositCode]),
                'status' => 'a',
                'merchant' => $rc->getMerchant(),
            );
        }

        // Blank
        $resArray['0'] = array(
            "maxOcc" => 0,
            "rate" => ($this->resv->getFixedRoomRate() ? $this->resv->getFixedRoomRate() : 0),
            'defaultRateCat' => '',
            "title" => '',
            'key' => 0,
            'status' => '',
            'merchant' => '',
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

        if (($this->resv->getStatus() == ReservationStatus::Committed || $this->resv->getStatus() == ReservationStatus::UnCommitted || $this->resv->getStatus() == ReservationStatus::Staying || $this->resv->getStatus() == ReservationStatus::Waitlist) &&
                isset($resOptions[$this->resv->getIdResource()])) {

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
        $visitConstraints = $this->resv->getVisitConstraints($dbh);
        $constraintMkup = '';
        $guestsRoom = '';

        // Add constraints markup.
        if (count($resvConstraints->getConstraints()) + count($visitConstraints->getConstraints()) > 0) {

            $constraintMkup = self::createResvConstMkup($dbh, $this->resv->getIdReservation(), $constraintsDisabled, $classId, $this->oldResvId);

            if ($constraintMkup == '') {
                $constraintMkup = "<p style='padding:4px;'>(No Room Attributes Selected.)<p>";
            }
        }


        if ($this->resv->isNew() === FALSE) {

            $tbl = new HTMLTable();
            $tbl->addHeaderTr(HTMLTable::makeTh("Total " . Labels::getString('MemberType', 'visitor', 'Guest') . "s") . HTMLTable::makeTh('Room', array('id'=>'hhk-roomChsrtitle')));

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
            $guestsRoom = $tbl->generateMarkup(array('id'=>'tblRescList')) . $errorMarkup;
        }


        if ($guestsRoom == '' && $constraintMkup == '') {
            $mk1 = '';
        } else {

            // fieldset wrapper
            $mk1 = HTMLContainer::generateMarkup('div',
                HTMLContainer::generateMarkup('fieldset',
                        HTMLContainer::generateMarkup('legend', 'Room Chooser', array('style'=>'font-weight:bold;'))
                        . $guestsRoom
                        . HTMLContainer::generateMarkup('div', $constraintMkup, array('style'=>'clear:left; float:left;')),
                        array('class'=>'hhk-panel'))
                    , array('style'=>'display: inline-block', 'class'=>'mr-3')
                );
        }

        return $mk1;
    }

    protected function createStaticMarkup(\PDO $dbh, $numOccupants = 0) {

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
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', "$numOccupants", array('id'=>'spnNumGuests','style'=>'font-weight:bold;')), array('style'=>'text-align:center;'))
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
                array('style'=>'display: inline-block', 'class'=>'mr-3'));

    }

    protected function createAddedMarkup(\PDO $dbh, $constraintsDisabled) {

        $errorMessage = '';
        $rmSelectorMarkup = '';

        if ($this->maxRoomsPerPatient > 1) {
            $rmSelectorMarkup = HTMLInput::generateMarkup('', array('name'=>'cbNewRoom', 'type'=>'checkbox'));
        }

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
                . ($rmSelectorMarkup == '' ? '' : HTMLTable::makeTh('New Room', array('id'=>'hhk-roomChsrtitle'))));

        $tbl->addBodyTr(
                HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $this->getCurrentGuests()), array('style'=>'text-align:center;'))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('id'=>'spnNumGuests')), array('style'=>'text-align:center;'))
                .HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $curRoomMarkup), array('style'=>'text-align:center;'))
                .($rmSelectorMarkup == '' ? '' : HTMLTable::makeTd(HTMLContainer::generateMarkup('span', $rmSelectorMarkup, array('id'=>'spanSelResc')), array('style'=>'text-align:center;')))
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
                        , array('style'=>'display: inline-block', 'class'=>'mb-3')
                );


        return $mk1;

    }

    public static function createResvConstMkup(\PDO $dbh, $resvId, $disableCtrl = FALSE, $classId = '', $oldResvId = 0) {

        $tbl = new HTMLTable();
        $mkup = '';

        $rhasCtrls = self::makeConstraintsCheckboxes(new ConstraintsReservation($dbh, $resvId, $oldResvId), $tbl, $disableCtrl, $classId . ' hhk-constraintsCB');
        $vhasCtrls = self::makeConstraintsCheckboxes(new ConstraintsVisit($dbh, $resvId, $oldResvId), $tbl, $disableCtrl, $classId);

        if ($rhasCtrls || $vhasCtrls) {
        	$mkup = $tbl->generateMarkup(array('id'=>'hhk-constraintsTbl'));
        }

        return $mkup;
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
        $defaultCat = '';
        $nites = 1;
        $guestNites = 1;
        $numberGuests = 1;
        $credit = 0;
        $idRoomRate = 0;
        $fixedRate = 0;
        $rateAdjust = 0;

        $priceModel = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);

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
            $cat = filter_var($post['rcat'],FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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

        if (isset($post['rid'])) {

            $idResv = intval(filter_var($post['rid'],FILTER_SANITIZE_NUMBER_INT), 10);

            if ($idResv > 0) {

                $stmt = $dbh->query("Select idRoom_rate, Room_Rate_Category from reservation where idReservation = $idResv");
                $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

                if (is_array($rows)) {

                    if (isset($rows[0]) && $cat == $rows[0][1]) {
                        $idRoomRate = $rows[0][0];
                    }
                }
            }
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
            $cat = DefaultSettings::Rate_Category;
        }

        $adnlGuestNites = ($numberGuests > 1 ? $guestNites - $nites : 0); //WI 10/18/2023 - amountCalculator expects Additional guest nights, NOT total guest nights

        $amt = ($priceModel->amountCalculator($nites, $idRoomRate, $cat, $fixedRate, $adnlGuestNites) * (1 + ($rateAdjust / 100)));

        foreach ($priceModel->getActiveModelRoomRates() as $rs) {

            if ($rs->FA_Category->getStoredVal() == $cat) {
                $catTitle = $rs->Title->getStoredVal();
                $catRate = $rs->Reduced_Rate_1->getStoredVal();
                break;
            }
        }


        return array(
            'amt'=> number_format($amt, 2, '.', ''),
            'cat'=>  $cat,
            'catTitle' => $catTitle . ':  $' . ($fixedRate > 0 ? $fixedRate : $catRate));
    }

}
?>