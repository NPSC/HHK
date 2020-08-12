<?php

namespace HHK;

use HHK\Exception\RuntimeException;

/**
 * US_Holidays.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of US_Holidays
 *
 * @author Eric
 */
class US_Holidays {

    private $year, $list;
    const ONE_DAY = 86400; // Number of seconds in one day
    const Federal = 'f';
    const Designated = 'dh';

    function __construct(\PDO $dbh, $year = null) {

        $this->year = (is_null($year))? (int) date("Y") : (int) $year;

        if (! is_int($this->year) || $this->year < 1997)
        {
            throw new \Exception($year.' is not a valid year. Valid values are integers greater than 1996.');
        }

        $stmt = $dbh->query("Select dh1, dh2, dh3, dh4 from desig_holidays where Year = ".$this->year);
        $dhs = $stmt->fetchall(\PDO::FETCH_ASSOC);

        if (count($dhs) == 0) {
            $dhs[0]['dh1'] = ''; $dhs[0]['dh2'] = ''; $dhs[0]['dh3'] = ''; $dhs[0]['dh4'] = '';
        }

        $stmt = $dbh->query("Select Code, Substitute from gen_lookups where Table_Name = 'Holiday'");
        $hols = $stmt->fetchall(\PDO::FETCH_ASSOC);

        if (count($hols) == 0) {
            throw new RuntimeException('Holidays are not defined.  ');
        }

        $this->set_list();

        // Insert designated holidays
        foreach ($dhs[0] as $k => $dh) {

            $this->list[] = array('name'=>strtoupper($k), 'timestamp'=>($dh == '' ? 0 :strtotime($dh)), 'type'=>self::Designated, 'use'=>'1');

        }

        // Remove unobserved holidays
        foreach ($hols as $h) {
            $this->list[$h['Code']]['use'] = $h['Substitute'];
        }


    }

    private function adjust_fixed_holiday($timestamp)
    {
        $weekday = date("w", $timestamp);
        if ($weekday == 0)
        {
            return $timestamp + self::ONE_DAY;
        }
        if ($weekday == 6)
        {
            return $timestamp - self::ONE_DAY;
        }
        return $timestamp;
    }

    private function set_list()
    {
        $this->list = array
        (
            array
            (
                "name" => "New Year's Day",
                        // January 1st, if not Saturday/Sunday
                "timestamp" => $this->adjust_fixed_holiday(strtotime("first day of january $this->year")),
                 "type" => self::Federal,
                'use' => '1'
                ),
            array
            (
                "name" => "Birthday of Martin Luther King, Jr.",
                        // 3rd Monday of January
                "timestamp" => strtotime("third monday ofjanuary $this->year"),
                 "type" => self::Federal,
                'use' => '1'
                ),
            array
            (
                "name" => "Wasthington's Birthday",
                        // 3rd Monday of February
                "timestamp" => strtotime("third monday of february $this->year"),
                 "type" => self::Federal,
                'use' => '1'
                ),
            array
            (
                "name" => "Memorial Day ",
                        // last Monday of May
                "timestamp" => strtotime("last monday of may $this->year"),
                 "type" => self::Federal,
                'use' => '1'
                ),
            array
            (
                "name" => "Independence day ",
                        // July 4, if not Saturday/Sunday
                "timestamp" => $this->adjust_fixed_holiday(strtotime("july 4 $this->year")),
                 "type" => self::Federal,
                'use' => '1'
                ),
            array
            (
                "name" => "Labor Day ",
                        // 1st Monday of September
                "timestamp" => strtotime("first monday of september $this->year"),
                 "type" => self::Federal,
                'use' => '1'
                ),
            array
            (
                "name" => "Columbus Day ",
                        // 2nd Monday of October
                "timestamp" => strtotime("second monday of october $this->year"),
                 "type" => self::Federal,
                'use' => '1'
                ),
            array
            (
                "name" => "Veteran's Day ",
                        // November 11, if not Saturday/Sunday
                "timestamp" => $this->adjust_fixed_holiday(strtotime("november 11 $this->year")),
                 "type" => self::Federal,
                'use' => '1'
                ),
            array
            (
                "name" => "Thanksgiving Day ",
                        // 4th Thursday of November
                "timestamp" => strtotime("last thursday of November $this->year"),
                 "type" => self::Federal,
                'use' => '1'
                ),
            array
            (
                "name" => "Christmas ",
                        // December 25 every year, if not Saturday/Sunday
                "timestamp" => $this->adjust_fixed_holiday(strtotime("december 25 $this->year")),
                 "type" => self::Federal,
                'use' => '1'
            )
        );
    }

    public function get_list()
    {
        return $this->list;
    }

    public function is_holiday($timestamp)
    {
        foreach ($this->list as $holiday)
        {
           if ($holiday['use'] == '1' && $timestamp >= $holiday["timestamp"] && $timestamp < ($holiday["timestamp"] + self::ONE_DAY)) {
               return true;
           }
        }

        return false;
    }

    public function getYear() {
        return $this->year;
    }
}

