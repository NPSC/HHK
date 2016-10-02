<?php
/**
 * Agent.php
 *
 *
 * @category  member
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2015 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of Agent
 * @package name
 * @author Eric
 */
class Agent extends Role{

    protected function factory(PDO $dbh, $id) {
        return new AgentMember($dbh, MemBasis::Indivual, $id);
    }


    public function save(PDO $dbh, array $post, $uname) {

        $message = "";
        $idPrefix = $this->getNameObj()->getIdPrefix();

        // Name
        $message .= $this->getNameObj()->saveChanges($dbh, $post);

        // Phone
        $message .= $this->getPhonesObj()->savePost($dbh, $post, $uname, $idPrefix);

        // Email
        $message .= $this->getEmailsObj()->savePost($dbh, $post, $uname, $idPrefix);

        return $message;
    }

}

