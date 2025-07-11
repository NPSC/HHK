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
    public const string Active = "a";
    public const string CheckedIn = "a";
    public const string CheckedOut = "co";
    public const string Pending = "p";
    public const string NewSpan = "n";
    public const string ChangeRate = "cp";
    public const string OnLeave = 'l';
    public const string Cancelled = 'c';
    public const string Reserved = 'r';
}
