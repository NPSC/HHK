<?php
namespace HHK\SysConst;

/**
 * VisitStatus.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class VisitStatus {
    const Active = "a";
    const CheckedIn = "a";
    const CheckedOut = "co";
    const Pending = "p";
    const NewSpan = "n";
    const ChangeRate = "cp";
    const OnLeave = 'l';
}
?>