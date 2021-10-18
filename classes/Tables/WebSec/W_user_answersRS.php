<?php
namespace HHK\Tables\WebSec;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbStrSanitizer, DbIntSanitizer, DbDateSanitizer};

/**
 * W_user_answers.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class W_user_answersRS extends AbstractTableRS {
    
    public $idAnswer; // int(11) NOT NULL
    public $idUser; // int(11) NOT NULL
    public $idQuestion; // int(11) NOT NULL,
    public $Answer; // varchar(100)
    public $Timestamp;  // timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    function __construct($TableName = "w_user_answers") {
        $this->idAnswer = new DB_Field("idAnswer", 0, new DbIntSanitizer());
        $this->idUser = new DB_Field("idUser", 0, new DBIntSanitizer());
        $this->idQuestion = new DB_Field("idQuestion", 0, new DBIntSanitizer());
        $this->Answer = new DB_Field("Answer", "", new DbStrSanitizer(100), TRUE, TRUE);
        $this->Timestamp = new DB_Field("Timestamp", NULL, new DbDateSanitizer("Y-m-d H:i:s"), FALSE);
        parent::__construct($TableName);
    }
}
?>