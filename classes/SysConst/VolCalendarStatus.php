<?php
namespace HHK\SysConst;

/**
 * VolCalendarStatus.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

// Calendar status for Table mcalendar
class VolCalendarStatus {
    public const string Active = 'a';
    public const string Logged = 't'; // Time is logged in the volunteer time table.
    public const string Deleted = 'd';
}
