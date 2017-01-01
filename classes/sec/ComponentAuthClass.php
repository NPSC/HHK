<?php
/**
 * ComponentAuthClass.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ComponentAuthClass extends SecurityComponent {


    public static function is_Authorized($name) {

        if (self::is_Admin()) {
            return TRUE;
        }

        $uS = Session::getInstance();

        $pageCode = array();
        // try reading the page table
        if ($name != "" && isset($uS->webPages[$name])) {
            $r = $uS->webPages[$name];

            if (!is_null($r)) {
                $pageCode = $r["Codes"];
            }
        }

        // check authorization codes.
        $tokn = self::does_User_Code_Match($pageCode);

        return $tokn;
    }

}

