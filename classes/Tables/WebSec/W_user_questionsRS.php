<?php
namespace HHK\ Tables\WebSec;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer, DbDateSanitizer};

/**
 * W_user_questionsRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class W_user_questionsRS extends AbstractTableRS {
    
    public $idQuestion; // int(11) NOT NULL
    public $Question; // varchar(180)
    public $Status; // varchar(1),
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    function __construct($TableName = "w_user_questions") {
        $this->idQuestion = new DB_Field("idQuestion", 0, new DbIntSanitizer());
        $this->Question = new DB_Field("Question", "", new DBStrSanitizer(180), TRUE, TRUE);
        $this->Status = new DB_Field("Status", "a", new DBStrSanitizer(1), TRUE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
    
}
?>