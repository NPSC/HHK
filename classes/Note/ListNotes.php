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

    /**
     * Summary of loadList
     * @param \PDO $dbh
     * @param mixed $linkId
     * @param mixed $linkType
     * @param mixed $parms
     * @param mixed $concatNotes
     * @return array
     */
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

            case "curguests":

                $dbView = 'vresv_notes';
                $whereField = 'Reservation_Status';
                $whereClause = "$whereField IN ('s')";
                $columns[] = array('db'=> 'Room', 'dt'=>'Room');
                $columns[] = array('db'=> 'Room', 'dt'=>'group');
                break;

            case "waitlist":

                $dbView = 'vresv_notes';
                $whereField = 'Reservation_Status';
                $whereClause = "$whereField IN ('w')";
                $columns[] = array('db'=> 'Primary Guest', 'dt'=>'Primary Guest');
                $columns[] = array('db'=> 'Primary Guest', 'dt'=>'group');
                break;

            case "confirmed":

                $dbView = 'vresv_notes';
                $whereField = 'Reservation_Status';
                $whereClause = "$whereField IN ('a')";
                $columns[] = array('db'=> 'Primary Guest', 'dt'=>'Primary Guest');
                $columns[] = array('db'=> 'Primary Guest', 'dt'=>'group');
                break;

            case "unconfirmed":

                $dbView = 'vresv_notes';
                $whereField = 'Reservation_Status';
                $whereClause = "$whereField IN ('uc')";
                $columns[] = array('db'=> 'Primary Guest', 'dt'=>'Primary Guest');
                $columns[] = array('db'=> 'Primary Guest', 'dt'=>'group');
                break;
            
            case Note::VisitLink:

                $dbView = 'vvisit_notes';
                $whereField = 'idVisit';
                $whereClause = "$whereField IN ($linkId, '') AND idPsg = $idPsg";
                break;

            case Note::PsgLink:

                $dbView = 'vpsg_notes_concat';
                $whereField = 'Psg_Id';
                $whereClause = "$whereField = $linkId";
                break;

            case "concat":
                $dbView = 'vpsg_notes_concat';
                $whereField = 'Psg_Id';
                $whereClause = "$whereField = $linkId";
                break;

            case Note::DocumentLink:
                $dbView = 'vdoc_notes';
                $whereField = 'Doc_Id';
                $whereClause = "$whereField = $linkId";
                break;

            case Note::StaffLink:
                $dbView = 'vstaff_notes';
                $whereClause = "";
                $columns[] = array('db'=> 'Category', 'dt'=>'Category');
                $columns[] = array('db'=> 'PrimaryGuest', 'dt'=>'Guest');
                $columns[] = array('db'=> 'idGuest', 'dt'=>'idGuest');
                $columns[] = array('db'=> 'idPsg', 'dt'=>'idPsg');
                $columns[] = array('db'=> 'room', 'dt'=>'room');

                break;
            case Note::MemberLink:
                $dbView = 'vmem_notes';
                $whereField = 'idName';
                $whereClause = "$whereField = $linkId";
                break;

            case Note::RoomLink:

                $dbView = 'vroom_notes';
                $whereField = 'idRoom';
                $whereClause = "$whereField = $linkId";
                break;

            default:
                return array('error'=>'The Link Type is not found: ' . $linkType);
        }

        return SSP::complex ( $parms, $dbh, $dbView, $priKey, $columns, null, "$whereClause" );

    }

}
