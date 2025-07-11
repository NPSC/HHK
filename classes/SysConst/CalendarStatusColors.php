<?php
namespace HHK\SysConst;

/**
 * CalendarStatusColors.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class CalendarStatusColors {
    public const string CheckedIn = 'ci';
    public const string CheckingInFuture = 'cifut';
    public const string CheckingInPast = 'cipast';
    public const string CheckingInToday = 'citod';
    public const string CheckingInTomorrow = 'citom';
    public const string CheckedOut = 'co';
    public const string CheckingOutToday = 'cotod';
    public const string CheckingOutTomorrow = 'cotom';
    public const string CheckedInPastExpectedDepart = 'copast';
    public const string Waitlist = 'w';
    public const string Unconfirmed = 'uc';
    public const string ReservedSpan = 'rv';
}
