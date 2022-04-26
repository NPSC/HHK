<?php

namespace HHK\Update;

use HHK\HTMLControls\{HTMLTable, HTMLContainer};
use HHK\SysConst\CodeVersion;
use HHK\sec\Session;


/**
 * Patch.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Patch
 *
 * @author Eric
 */
class Patch {

    public $oldVersion = '';
    public $newVersion = '';

    public $results;

    public function __construct() {
        $this->results = array();
    }

    public function updateWithSqlStmts(\PDO $dbh, $tfile, $type = '', $delimiter = ';', $splitAt = ';') {

        $this->results = array();

        if ($tfile == '') {
            return $type . ' Filename is missing.  ';
        }

        $tquery = file_get_contents($tfile);

        $tresult = self::multiQueryPDO($dbh, $tquery, $delimiter, $splitAt);

        if (count($tresult) > 0) {

            foreach ($tresult as $err) {
                $this->results[$err['errno']] = $err;
            }

        } else {
            return $type . ' Successful<br/>';
        }
    }

    public static function multiQueryPDO(\PDO $dbh, $query, $delimiter = ";", $splitAt = ';') {

        $msg = array();

        if ($query === FALSE || trim($query) == '') {
            return $msg[] = array('error'=>'Empty query file ', 'errno'=> '', 'query'=> $query );
        }

        $qParts = explode($splitAt, $query);

        try{
            $dbh->beginTransaction();

            foreach ($qParts as $q) {

                $q = trim($q);
                if ($q == '' || $q == $delimiter || $q == 'DELIMITER') {
                    continue;
                }

                $dbh->exec($q);
            }
            $dbh->commit();
        }catch(\Exception $e){
            $dbh->rollBack();
            $msg[] = array('error'=>$e->getMessage(), 'errno'=>$e->getCode(), 'query'=>$q);
        }

        return $msg;
    }

    public static function patchTabMu() {

        $uS = Session::getInstance();

        // Database info
        $dbt = new HTMLTable();

        $dbt->addBodyTr(HTMLTable::makeTd('Database:', array('class' => 'tdlabel')) . HTMLTable::makeTd($uS->dbms));
        $dbt->addBodyTr(HTMLTable::makeTd('URL:', array('class' => 'tdlabel')) . HTMLTable::makeTd($uS->databaseURL));
        $dbt->addBodyTr(HTMLTable::makeTd('Schema:', array('class' => 'tdlabel')) . HTMLTable::makeTd($uS->databaseName));
        $dbt->addBodyTr(HTMLTable::makeTd('User:', array('class' => 'tdlabel')) . HTMLTable::makeTd($uS->databaseUName));

        $markup = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'DB Info', array('style'=>'font-weight:bold;'))
                . $dbt->generateMarkup(), array('style'=>'float:left; margin:5px;', 'class'=>'hhk-panel'));

        // Software info
        $tbl = new HTMLTable();

        $tbl->addBodyTr(
                HTMLTable::makeTd('Build:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(CodeVersion::BUILD));
        $tbl->addBodyTr(
                HTMLTable::makeTd('Version:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(CodeVersion::VERSION));
        $tbl->addBodyTr(
                HTMLTable::makeTd('Patch:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(CodeVersion::PATCH));
        $tbl->addBodyTr(
                HTMLTable::makeTd('Git Id:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(CodeVersion::GIT_Id));
        $tbl->addBodyTr(
                HTMLTable::makeTd('Release Date:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(CodeVersion::REL_DATE));

        $markup .= HTMLContainer::generateMarkup('fieldset',
            HTMLContainer::generateMarkup('legend', 'Software Version', array('style'=>'font-weight:bold;'))
            . $tbl->generateMarkup(), array('style'=>'float:left; margin:5px; margin-left:25px;', 'class'=>'hhk-panel'));


        // Contributors
//        $ctbl = new HTMLTable();
//
//        $contributors = array(
//            array("ML", "Eubanks"),
//            array("E", "Crane"),
//            array("K", "Lannan"),
//            array("R", "Chan"),
//            array("B", "VanderMeer"),
//            array("W", "Ireland"),
//            );
//
//        foreach ($contributors as $c) {
//
//            $ctbl->addBodyTr(
//                HTMLTable::makeTd($c[0], array('class' => 'tdlabel'))
//                . HTMLTable::makeTd($c[1]));
//
//        }
//
//        $markup .= HTMLContainer::generateMarkup('fieldset',
//                HTMLContainer::generateMarkup('legend', 'Major Contributors<br/>in order of Appearance', array('style'=>'font-weight:bold;'))
//                . $ctbl->generateMarkup(), array('style'=>'float:left; margin:5px; margin-left:25px;', 'class'=>'hhk-panel'));

        return HTMLContainer::generateMarkup('div', $markup, array());
    }

}

