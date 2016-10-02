<?php
/**
 * Doctor.php
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
 * Description of Doctor
 * @package name
 * @author Eric
 */
class Doctor extends Role{

    protected function factory(PDO $dbh, $id) {
        return new DoctorMember($dbh, MemBasis::Indivual, $id);
    }


    public function save(PDO $dbh, array $post, $uname) {

        // Name
        $idPrefix = $this->getNameObj()->getIdPrefix();
        //$post[$idPrefix.'selPrefix'] = 'dr';

        $message = $this->getNameObj()->saveChanges($dbh, $post);

        // Phone
        $message .= $this->getPhonesObj()->savePost($dbh, $post, $uname, $idPrefix);

        // Email
        $message .= $this->getEmailsObj()->savePost($dbh, $post, $uname, $idPrefix);
        return $message;
    }



}

