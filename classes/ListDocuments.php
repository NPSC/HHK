<?php

/**
 * ListDocuments.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ListDocuments
 *
 * @author Will
 */
class ListDocuments {

    public static function loadList(\PDO $dbh, $linkId, $linkType, $parms) {

        $columns = array(
            array('db' => 'Timestamp', 'dt' => 'Date'),
            array('db' => 'Created_By', 'dt' => 'User'),
            array('db' => 'Title', 'dt' => 'Title'),
            array('db' => 'Doc_Id', 'dt' => 'DocId'),
            array('db' => 'Action', 'dt' => 'Action'),
            array('db' => 'ViewDoc', 'dt' => 'View Doc'),
            array('db' => 'Guest', 'dt' => 'Guest')
        );

        $dbView = 'v_docs';
        $whereField = '';
        $priKey = 'Doc_Id';

        if ($linkType == '') {
            return array('error' => 'The Link Type is missing.');
        }

        switch ($linkType) {

            case Document::GuestLink:

                $whereField = 'Guest_Id';
                break;

            case Document::PsgLink:

                $whereField = 'PSG_Id';
                break;

            default:
                return array('error' => 'The Link Type is not found: ' . $linkType);
        }

        //return array("table" => $dbView, "WhereField"=> $whereField, "whereID"=>$linkId);

        return SSP::complex($parms, $dbh, $dbView, $priKey, $columns, null, "$whereField = $linkId");
    }

}
