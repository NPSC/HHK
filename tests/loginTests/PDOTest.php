<?php
use HHK\sec\Login;
use PHPUnit\Framework\TestCase;

class PDOTest extends TestCase {

    /**
     * Test Database connection
     * @return void
     */
    public function testDbConnection() {
        $dbh = Login::initHhkSession(CONF_PATH, ciCFG_FILE);
        $this->assertInstanceOf("PDO", $dbh);
    }

}

?>