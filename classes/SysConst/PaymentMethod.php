<?php
namespace HHK\SysConst;

/**
 * PaymentMethod.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class PaymentMethod {
    const Cash = 1;
    const Charge = 2;
    const Check = 3;
    const ChgAsCash = 4;
    const Transfer = 5;
}
?>