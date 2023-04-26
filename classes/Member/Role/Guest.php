<?php

namespace HHK\Member\Role;

use HHK\SysConst\{MemBasis, MemDesignation};
use HHK\sec\Session;
use HHK\Exception\RuntimeException;
use HHK\HTMLControls\{HTMLContainer, HTMLTable};
use HHK\House\ReserveData\PSGMember\PSGMember;
use HHK\Member\RoleMember\GuestMember;

/**
 * Guest.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Member type of Guest
 * @author Eric
 */
class Guest extends AbstractRole {


    /**
     *
     * @param \PDO $dbh
     * @param integer $id
     * @return GuestMember
     */
    public function __construct(\PDO $dbh, $idPrefix, $id, $title = 'Guest') {

        $this->currentlyStaying = NULL;
        $this->idVisit = NULL;
        $this->emergContact = NULL;
        $this->title = $title;
        $this->patientPsg = NULL;

        $this->roleMember = new GuestMember($dbh, MemBasis::Indivual, $id);
        $this->roleMember->setIdPrefix($idPrefix);

        if ($this->roleMember->getMemberDesignation() != MemDesignation::Individual) {
            throw new RuntimeException("Must be individuals, not organizations");
        }

    }

    public function createThinMarkup(PSGMember $mem, $lockRelChooser) {

        $uS = Session::getInstance();

        $mu = parent::createThinMarkup($mem, $lockRelChooser);

        if ($uS->GuestAddr) {
            // Address toggle comtrols
            $mu .= HTMLTable::makeTd(
                HTMLContainer::generateMarkup('ul'
                        , HTMLContainer::generateMarkup('li',
                                HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-check'))
                                , array('class'=>'ui-state-highlight ui-corner-all hhk-AddrFlag', 'data-pref'=>$this->getRoleMember()->getIdPrefix(), 'id'=>$this->getRoleMember()->getIdPrefix().'liaddrflag'))
                        . HTMLContainer::generateMarkup('li',
                                HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-circle-triangle-n'))
                                , array('class'=>'ui-state-default ui-corner-all hhk-togAddr', 'data-pref'=>$this->getRoleMember()->getIdPrefix(), 'id'=>$this->getRoleMember()->getIdPrefix().'toggleAddr'))
                        , array('class'=>'ui-widget ui-helper-clearfix hhk-ui-icons'))
                , array('style'=>'text-align:center;min-width:50px;')
                );

        } else {
            $mu .= HTMLTable::makeTd('');
        }

        return $mu;
    }

    /**
     *
     * @param \PDO $dbh
     * @param array $post
     * @return string Message for end user.
     */
    public function save(\PDO $dbh, array $post, $uname, $isStaying = FALSE) {

        $message = parent::save($dbh, $post, $uname);

        $idPrefix = $this->getRoleMember()->getIdPrefix();

        // Use House Phone?
        if (isset($post[$idPrefix . 'rbPhPref']) && filter_var($post[$idPrefix . 'rbPhPref'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) == 'yr') {
            $this->useHousePhone = TRUE;
        }

        // Guest Checkin Date
        if (isset($post[$idPrefix.'gstDate'])) {
            $this->setCheckinDate(filter_var($post[$idPrefix.'gstDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        } else if (isset($post['gstDate'])) {
            $this->setCheckinDate(filter_var($post['gstDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        // Guest Checkout Date
        if (isset($post[$idPrefix.'gstCoDate'])) {
            $this->setExpectedCheckOut(filter_var($post[$idPrefix.'gstCoDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        } else if (isset($post['gstCoDate'])) {
            $this->setExpectedCheckOut(filter_var($post['gstCoDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        return $message;
    }

   public function getCheckinDate() {
        if (is_null($this->checkinDate)) {
            return '';
        }
        return $this->checkinDate->format('Y-m-d H:i:s');
    }

    public function getExpectedCheckinDate() {
        if (is_null($this->checkinDate)) {
            return '';
        }
        return $this->checkinDate->format('Y-m-d H:i:s');
    }

    public function getCheckinDT() {
        return $this->checkinDate;
    }

    public function setCheckinDate($stringDate, $time = 'H:i:s') {

        if ($stringDate != '') {

            $uS = Session::getInstance();

            $ciDT = new \DateTime($stringDate);
            $ciDT->setTimezone(new \DateTimeZone($uS->tz));
            $dt = $ciDT->format('Y-m-d');
            $now = date($time);

            $this->checkinDate = new \DateTime($dt . ' ' . $now);
        }
    }

    public function setExpectedCheckinDate($stringDate) {

        if ($stringDate != '') {

            $uS = Session::getInstance();

            $ciDT = new \DateTime($stringDate);
            $ciDT->setTimezone(new \DateTimeZone($uS->tz));
            $dt = $ciDT->format('Y-m-d ' . $uS->CheckInTime . ':00');

            $this->checkinDate = new \DateTime($dt);
        }

    }

    public function getExpectedCheckOut() {
        if (is_null($this->expectedCheckOut)) {
            return '';
        }
        return $this->expectedCheckOut->format('Y-m-d H:i:s');
    }

    public function getExpectedCheckOutDT() {
        return $this->expectedCheckOut;
    }

    public function setExpectedCheckOut($stringDate) {

        if ($stringDate != '') {

            $uS = Session::getInstance();

            $ciDT = new \DateTime($stringDate);
            $ciDT->setTimezone(new \DateTimeZone($uS->tz));
            $dt = $ciDT->format('Y-m-d');

            $this->expectedCheckOut = new \DateTime($dt . ' ' . $uS->CheckOutTime . ':00:00');
        }
    }

    public function setTitle($title) {
        $this->title = $title;
    }

}
?>