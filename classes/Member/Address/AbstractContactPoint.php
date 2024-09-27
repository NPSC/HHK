<?php

namespace HHK\Member\Address;

use HHK\Member\AbstractMember;

/**
 * AbstractContactPoint.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 *  AbstractContactPoint
 * Base class for a members' street address, phone and email.
 */

abstract class AbstractContactPoint {

    /** @var array Holds the address type codes, ex. 'Home' or 'Work' */
    protected $codes = array();

    /** @var array Holds an iTable for each address type in $codes */
    protected $rSs = array();

    /** @var AbstractMember pointer to the Member object */
    protected $name;


    /**
     *
     * @param \PDO $dbh
     * @param AbstractMember $name
     * @param array $codes
     */
    function __construct(\PDO $dbh, AbstractMember $name, array $codes) {

        $this->name = $name;

        // Filter codes for member designation
        foreach ($codes as $c) {
            if ($c[AbstractMember::SUBT] == $name->getMemberDesignation() || $c[AbstractMember::SUBT] == ""|| $c[AbstractMember::SUBT] == "hhk-home") {
                $this->codes[$c[AbstractMember::CODE]] = $c;
            }
        }

        $this->rSs =  $this->loadRecords($dbh);

    }


    /**
     * Summary of setPreferredCode
     * @param mixed $code
     * @return string
     */
    public abstract function setPreferredCode($code);

    /**
     * Summary of get_preferredCode
     * @return string
     */
    public abstract function get_preferredCode();

    /**
     * Summary of getTitle
     * @return string
     */
    public abstract function getTitle();

    /**
     * Summary of getLastUpdated
     * @param mixed $code
     * @return mixed
     */
    public function getLastUpdated($code = '') {

        if ($code == '') {
            $code = $this->get_preferredCode();
        }

        $rs = $this->get_recordSet($code);

        if (is_null($rs)) {
            return '';
        }

        return $rs->Last_Updated->getStoredVal();

    }


    /**
     * The extending objects must each load their type of record set.
     */
    protected abstract function loadRecords(\PDO $dbh);

    /**
     * Summary of createMarkup
     * @param mixed $inputClass
     * @return void
     */
    public abstract function createMarkup($inputClass = "");

    /**
     * Summary of savePost
     * @param \PDO $dbh
     * @param mixed $post
     * @param mixed $user
     * @return void
     */
    public abstract function savePost(\PDO $dbh, array $post, $user);

    /**
     * Summary of get_Data
     * @param mixed $code
     * @return array
     */
    public abstract function get_Data($code = "");

    /**
     * Summary of isRecordSetDefined
     * @param mixed $code
     * @return void
     */
    public abstract function isRecordSetDefined($code);


    /**
     * Summary of get_recordSet
     * @param mixed $code
     * @return mixed
     */
    public function get_recordSet($code) {

        if (isset($this->rSs[$code])) {
            return $this->rSs[$code];
        }

        return null;
    }

    /**
     * Summary of get_CodeArray
     * @return array
     */
    public function get_CodeArray() {
        return $this->codes;
    }
}
?>