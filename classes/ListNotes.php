<?php
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

    public static function loadList (\PDO $dbh, $linkId, $linkType, $parms) {

        $columns = array(
            array( 'db' => 'Timestamp',  'dt' => 'Date' ),
            array( 'db' => 'User_Name',   'dt' => 'User' ),
            array( 'db' => 'Note_Text', 'dt' => 'Note'),
            array( 'db' => 'Note_Id', 'dt' => 'NoteId'),
            array( 'db' => 'Action', 'dt' => 'Action')
        );

        $dbView = '';
        $whereField = '';
        $priKey = 'Note_Id';

        if ($linkType == '') {
            return array('error'=>'The Link Type is missing.');
        }

        switch ($linkType) {

            case Note::ResvLink:

                $dbView = 'vresv_notes';
                $whereField = 'Reservation_Id';
                break;

            case Note::VisitLink:

                $dbView = 'vvisit_notes';
                $whereField = 'idVisit';
                break;

            case Note::PsgLink:

                $dbView = 'vpsg_notes';
                $whereField = 'Psg_Id';
                break;

            case Note::MemberLink:

                //break;

            case Note::RoomLink:

                //break;

            default:
                return array('error'=>'The Link Type is not found: ' . $linkType);
        }

        return SSP::complex ( $parms, $dbh, $dbView, $priKey, $columns, null, "$whereField = $linkId" );

    }

}
