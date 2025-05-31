<?php
namespace HHK\SysConst;

/**
 * VisitStatus.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class VisitStatus {
    public const Active = "a";
    public const CheckedIn = "a";
    public const CheckedOut = "co";
    public const Pending = "p";
    public const NewSpan = "n";
    public const ChangeRate = "cp";
    public const OnLeave = 'l';
    public const Cancelled = 'c';
    public const Reserved = 'r';
}
