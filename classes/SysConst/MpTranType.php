<?php
namespace HHK\SysConst;

/**
 * MpTranType.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class MpTranType {
    public const string Sale = 'Sale';
    public const string PreAuth = 'PreAuth';
    public const string ZeroAuth = 'ZeroAuth';
    public const string ReturnAmt = 'ReturnAmount';
    public const string ReturnSale = 'ReturnSale';
    public const string Void = 'VoidSale';
    public const string VoidReturn = 'VoidReturn';
    public const string Reverse = 'ReverseSale';
    public const string CardOnFile = 'COF';
    public const string Adjust = 'CreditAdjust';
}
