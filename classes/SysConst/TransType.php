<?php
namespace HHK\SysConst;

/**
 * TransType.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class TransType {
    const Sale = 's';
    const Void = 'vs';
    const Retrn = 'r';
    const VoidReturn = 'vr';
    const Reverse = 'rv';
    const undoRetrn = 'ur';
    const ZeroAuth = 'za';
}
?>