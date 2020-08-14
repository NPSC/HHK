<?php
namespace HHK\ Tables\Registration;

use HHK\Tables\AbstractTableRS;
use HHK\Tables\Fields\{DB_Field, DbIntSanitizer, DbStrSanitizer};

/**
 * VehicleRS.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of VehicleRS
 * @package name
 * @author Eric
 */
class VehicleRS extends AbstractTableRS {

    public $idVehicle;   // int(11) NOT NULL AUTO_INCREMENT,
    public $idRegistration;   // int(11) NOT NULL,
    public $idName;  // INT(11) NOT NULL DEFAULT 0 COMMENT ''
    public $Make;   // varchar(45) NOT NULL DEFAULT '',
    public $Model;   // varchar(45) NOT NULL DEFAULT '',
    public $Color;   // varchar(45) NOT NULL DEFAULT '',
    public $State_Reg;   // varchar(2) NOT NULL DEFAULT '',
    public $License_Number;   // varchar(15) NOT NULL DEFAULT '',
    public $No_Vehicle;
    public $Note;  // VARCHAR(445) NOT NULL DEFAULT '' COMMENT ''

    function __construct($TableName = 'vehicle') {

        $this->idVehicle = new DB_Field('idVehicle', 0, new DbIntSanitizer());
        $this->idRegistration = new DB_Field('idRegistration', 0, new DbIntSanitizer());
        $this->idName = new DB_Field('idName', 0, new DbIntSanitizer());
        $this->Make = new DB_Field('Make', '', new DbStrSanitizer(45));
        $this->Model = new DB_Field('Model', '', new DbStrSanitizer(45));
        $this->Color = new DB_Field('Color', '', new DbStrSanitizer(45));
        $this->State_Reg = new DB_Field('State_Reg', '', new DbStrSanitizer(2));
        $this->License_Number = new DB_Field('License_Number', '', new DbStrSanitizer(15));
        $this->No_Vehicle = new DB_Field('No_Vehicle', '', new DbStrSanitizer(4));
        $this->Note = new DB_Field('Note', '', new DbStrSanitizer(440));
        parent::__construct($TableName);
    }
}
?>