<?php

namespace HHK\Member\Role;

use HHK\Exception\RuntimeException;
use HHK\Member\RoleMember\DoctorMember;
use HHK\SysConst\{MemBasis, MemDesignation};

/**
 * Doctor.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Doctor
 * @package name
 * @author Eric
 */
class Doctor extends AbstractRole{

    /**
     * Summary of __construct
     * @param \PDO $dbh
     * @param mixed $idPrefix
     * @param mixed $id
     * @param mixed $title
     * @throws \HHK\Exception\RuntimeException
     */
    public function __construct(\PDO $dbh, $idPrefix, $id, $title = 'Doctor') {

        $this->currentlyStaying = NULL;
        $this->idVisit = NULL;
        $this->emergContact = NULL;
        $this->title = $title;
        $this->patientPsg = NULL;

        $this->roleMember = new DoctorMember($dbh, MemBasis::Indivual, $id);
        $this->roleMember->setIdPrefix($idPrefix);

        if ($this->roleMember->getMemberDesignation() != MemDesignation::Individual) {
            throw new RuntimeException("Doctors Must be individuals, not organizations");
        }

    }


    /**
     * Summary of save
     * @param \PDO $dbh
     * @param array $post
     * @param mixed $uname
     * @param mixed $isStaying
     * @return string
     */
    public function save(\PDO $dbh, array $post, $uname, $isStaying = FALSE) {

        // Name
        $idPrefix = $this->getRoleMember()->getIdPrefix();
        //$post[$idPrefix.'selPrefix'] = 'dr';

        $message = $this->getRoleMember()->saveChanges($dbh, $post);

        // Phone
        $message .= $this->getPhonesObj()->savePost($dbh, $post, $uname, $idPrefix);

        // Email
        $message .= $this->getEmailsObj()->savePost($dbh, $post, $uname, $idPrefix);
        return $message;
    }

}
?>