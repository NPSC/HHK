<?php
namespace HHK\SysConst;

/**
 * PaymentMethod.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class PaymentMethod {
    public const int Cash = 1;
    public const int Charge = 2;
    public const int Check = 3;
    public const int ChgAsCash = 4;
    public const int Transfer = 5;
}
