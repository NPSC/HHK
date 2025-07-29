<?php
namespace HHK\SysConst;

/**
 * CalendarStatusColors.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class CalendarStatusColors {
    const CheckedIn = 'ci';
    const CheckingInFuture = 'cifut';
    const CheckingInPast = 'cipast';
    const CheckingInToday = 'citod';
    const CheckingInTomorrow = 'citom';
    const CheckedOut = 'co';
    public const CheckingOutToday = 'cotod';
    const CheckingOutTomorrow = 'cotom';
    const CheckedInPastExpectedDepart = 'copast';
    const Waitlist = 'w';
    const Unconfirmed = 'uc';
    const ReservedSpan = 'rv';
}