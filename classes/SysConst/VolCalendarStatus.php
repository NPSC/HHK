<?php
namespace HHK\SysConst;

/**
 * VolCalendarStatus.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

// Calendar status for Table mcalendar
class VolCalendarStatus {
    const Active = 'a';
    const Logged = 't'; // Time is logged in the volunteer time table.
    const Deleted = 'd';
}
?>