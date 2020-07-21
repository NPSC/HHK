<?php
namespace HHK\SysConst;

/**
 * ReservationStatus.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ReservationStatus {
    const Committed = 'a';
    const Waitlist = 'w';
    const NoShow = 'ns';
    const TurnDown = 'td';
    const Canceled = 'c';
    const Canceled1 = 'c1';
    const Canceled2 = 'c2';
    const Canceled3 = 'c3';
    const Canceled4 = 'c4';
    const Pending = 'p';
    const Staying = 's';
    const Checkedout = 'co';
    const UnCommitted = 'uc';
    const Imediate = 'im';
}
?>