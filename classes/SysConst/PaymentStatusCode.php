<?php
namespace HHK\SysConst;

/**
 * PaymentStatusCode.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class PaymentStatusCode {
    const Paid = 's';
    const VoidSale = 'v';
    const Retrn = 'r';
    const Reverse = 'rv';
    const VoidReturn = "vr";
    const Declined = 'd';
}
?>