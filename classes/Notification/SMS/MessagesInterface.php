<?php

namespace HHK\Notification\SMS;

interface MessagesInterface {

    /**
     * Fetch messages
     * @param string $contactPhone
     * @param int $limit
     * @param string $since
     * @throws \HHK\Exception\SmsException
     * @return array
     */
    public function fetchMessages(string $contactPhone, int $limit = 20, string $since = ""):array;

}