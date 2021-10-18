<?php

namespace HHK\Note;

use HHK\DataTableServer\SSP;

/**
 * ListNotes.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ListNotes
 *
 * @author Eric
 */
class ListNotes {

    public static function loadList (\PDO $dbh, $linkId, $linkType, $parms, $concatNotes = FALSE) {

        $columns = array(
            array( 'db' => 'Timestamp',  'dt' => 'Date' ),
            array( 'db' => 'User_Name',   'dt' => 'User' ),
            array( 'db' => 'Note_Text', 'dt' => 'Note'),
            array( 'db' => 'Title', 'dt' => 'Title'),
            array( 'db' => 'Note_Id', 'dt' => 'NoteId'),
            array( 'db' => 'Action', 'dt' => 'Action'),
            array( 'db' => 'flag', 'dt' => 'Flag'),
        );

        $dbView = '';
        $whereField = '';
        $whereClause = '';
        $priKey = 'Note_Id';
        $idPsg = LinkNote::findIdPsg($dbh, $linkType, $linkId);
        
        if ($concatNotes) {

            if ($idPsg > 0) {
                $linkType = "concat";
                $linkId = $idPsg;
            }
        }

        if ($linkType == '') {
            return array('error'=>'The Link Type is missing.');
        }

        switch ($linkType) {

            case Note::ResvLink:

                $dbView = 'vresv_notes';
                $whereField = 'Reservation_Id';
                $whereClause = "$whereField IN ($linkId, '') AND idPsg = $idPsg";
                break;

            case Note::VisitLink:

                $dbView = 'vvisit_notes';
                $whereField = 'idVisit';
                $whereClause = "$whereField IN ($linkId, '') AND idPsg = $idPsg";
                break;

            case Note::PsgLink:

                $dbView = 'vpsg_notes';
                $whereField = 'Psg_Id';
                $whereClause = "$whereField = $linkId";
                break;
                
            case "concat":
                $dbView = 'vpsg_notes_concat';
                $whereField = 'Psg_Id';
                $whereClause = "$whereField = $linkId";
                break;

            case Note::MemberLink:

                //break;

            case Note::RoomLink:

                //break;

            default:
                return array('error'=>'The Link Type is not found: ' . $linkType);
        }

        return SSP::complex ( $parms, $dbh, $dbView, $priKey, $columns, null, "$whereClause" );

    }

}
