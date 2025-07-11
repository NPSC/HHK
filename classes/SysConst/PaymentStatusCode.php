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
    public const string Paid = 's';
    public const string VoidSale = 'v';
    public const string Retrn = 'r';
    public const string Reverse = 'rv';
    public const string VoidReturn = "vr";
    public const string Declined = 'd';
}
