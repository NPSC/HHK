<?php
namespace HHK\House\GuestRegister;

class Event
{

    // Tests whether the given ISO8601 string has a time-of-day or not
    const ALL_DAY_REGEX = '/^\d{4}-\d\d-\d\d$/'; // matches strings like "2013-12-29"

    public string $title;
    public bool $allDay; // a boolean
    public \DateTimeInterface $start; // a DateTime
    public \DateTimeInterface|null $end; // a DateTime, or null
    public array $properties = array(); // an array of other misc properties


    // Constructs an Event object from the given array of key=>values.
    // You can optionally force the timezone of the parsed dates.
    public function __construct(array $array, \DateTimeZone|string|null $timezone = null)
    {

        $this->title = '';
        ;
        if (isset($array['title'])) {
            $this->title = $array['title'];
        }

        if (isset($array['allDay'])) {
            // allDay has been explicitly specified
            $this->allDay = (bool) $array['allDay'];
        } else {
            // Guess allDay based off of ISO8601 date strings
            $this->allDay = preg_match(self::ALL_DAY_REGEX, $array['start']) &&
                (!isset($array['end']) || preg_match(self::ALL_DAY_REGEX, $array['end']));
        }

        if (is_string($timezone)) {
            $timezone = new \DateTimeZone($timezone);
        }

        if ($this->allDay) {
            // If dates are allDay, we want to parse them in UTC to avoid DST issues.
            $timezone = null;
        }

        // Parse dates
        $this->start = GuestRegister::parseDateTime($array['start'], $timezone);
        $this->end = isset($array['end']) ? GuestRegister::parseDateTime($array['end'], $timezone) : null;

        // Record misc properties
        foreach ($array as $name => $value) {
            if (!in_array($name, array('title', 'allDay', 'start', 'end'))) {
                $this->properties[$name] = $value;
            }
        }
    }


    // Returns whether the date range of our event intersects with the given all-day range.
    // $rangeStart and $rangeEnd are assumed to be dates in UTC with 00:00:00 time.
    public function isWithinDayRange(\DateTimeInterface $rangeStart, \DateTimeInterface $rangeEnd): bool
    {

        // Normalize our event's dates for comparison with the all-day range.
        $eventStart = GuestRegister::stripTime($this->start);

        if (isset($this->end)) {
            $eventEnd = GuestRegister::stripTime($this->end); // normalize
        } else {
            $eventEnd = $eventStart; // consider this a zero-duration event
        }

        // Check if the two whole-day ranges intersect.
        return $eventStart < $rangeEnd && $eventEnd >= $rangeStart;
    }


    // Converts this Event object back to a plain data array, to be used for generating JSON
    public function toArray(): array
    {

        // Start with the misc properties (don't worry, PHP won't affect the original array)
        $array = $this->properties;

        $array['title'] = $this->title;

        // Figure out the date format. This essentially encodes allDay into the date string.
        if ($this->allDay) {
            $format = 'Y-m-d'; // output like "2013-12-29"
        } else {
            $format = 'c'; // full ISO8601 output, like "2013-12-29T09:00:00+08:00"
        }

        // Serialize dates into strings
        $array['start'] = $this->start->format($format);
        if (isset($this->end)) {
            $array['end'] = $this->end->format($format);
        }

        return $array;
    }

}