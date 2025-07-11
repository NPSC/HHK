<?php
namespace HHK\SysConst;

/**
 * MpTranType.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class MpTranType {
    const Sale = 'Sale';
    const PreAuth = 'PreAuth';
    const ZeroAuth = 'ZeroAuth';
    const ReturnAmt = 'ReturnAmount';
    const ReturnSale = 'ReturnSale';
    const Void = 'VoidSale';
    const VoidReturn = 'VoidReturn';
    const Reverse = 'ReverseSale';
    const CardOnFile = 'COF';
    const Adjust = 'CreditAdjust';
}
?>