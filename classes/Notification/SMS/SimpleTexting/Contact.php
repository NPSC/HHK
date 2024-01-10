<?php

namespace HHK\Notification\SMS\SimpleTexting;

Class Contact {

    protected \PDO $dbh;

    public function __construct(\PDO $dbh){
        $this->dbh = $dbh;
    }

}

?>