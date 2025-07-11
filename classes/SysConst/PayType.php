<?php
namespace HHK\SysConst;

/**
 * PayType.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class PayType {
    public const string Cash = 'ca';
    public const string Charge = 'cc';
    public const string Check = 'ck';
    public const string Invoice = 'in';
    public const string ChargeAsCash = 'cx';
    public const string Transfer = 'tf';
}
