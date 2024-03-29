<?php

namespace HHK\Member\Role;

use HHK\Member\RoleMember\AgentMember;
use HHK\SysConst\{MemBasis, MemDesignation};
use HHK\Exception\RuntimeException;

/**
 * Agent.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Agent
 * @package name
 * @author Eric
 */
class Agent extends AbstractRole{

    /**
     * Summary of __construct
     * @param \PDO $dbh
     * @param mixed $idPrefix
     * @param mixed $id
     * @param mixed $title
     * @throws \HHK\Exception\RuntimeException
     */
    public function __construct(\PDO $dbh, $idPrefix, $id, $title = 'Referral Agent') {

        $this->currentlyStaying = NULL;
        $this->idVisit = NULL;
        $this->emergContact = NULL;
        $this->title = $title;
        $this->patientPsg = NULL;

        $this->roleMember = new AgentMember($dbh, MemBasis::Indivual, $id);
        $this->roleMember->setIdPrefix($idPrefix);

        if ($this->roleMember->getMemberDesignation() != MemDesignation::Individual) {
            throw new RuntimeException("Must be individuals, not organizations");
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

        $message = "";
        $idPrefix = $this->getRoleMember()->getIdPrefix();

        // Name
        $message .= $this->getRoleMember()->saveChanges($dbh, $post);

        // Phone
        $message .= $this->getPhonesObj()->savePost($dbh, $post, $uname, $idPrefix);

        // Email
        $message .= $this->getEmailsObj()->savePost($dbh, $post, $uname, $idPrefix);

        return $message;
    }

}
?>