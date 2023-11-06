<?php
use HHK\Common;
use HHK\sec\Login;
use HHK\Update\Install;
use PHPUnit\Framework\TestCase;

class InstallTest extends TestCase {

    /**
     * Login test
     * @return void
     */
    public function testInstallDatabase() {
        $login = new Login();
        $dbh = Common::initPDO(true);
        $installer = new Install();
        $result = $installer->installDB($dbh);
        $this->assertEquals("", $result);
    }

}
