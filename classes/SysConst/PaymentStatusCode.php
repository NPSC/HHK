<?php
namespace HHK\SysConst;

/**
 * PaymentStatusCode.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class PaymentStatusCode {
    public const Paid = 's';
    public const VoidSale = 'v';
    public const Retrn = 'r';
    public const Reverse = 'rv';
    public const VoidReturn = "vr";
    public const Declined = 'd';
}
