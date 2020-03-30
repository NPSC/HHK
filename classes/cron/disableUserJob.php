<?php
/**
 * disableUserJob.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class disableUserJob extends cron {

    function __construct(\PDO $dbh, $uS) {
        $this->title = "Disable Inactive Users Job";
        parent::__construct($dbh, $uS);
    }
    
    public function action(){
        $users = $this->getUsers($this->dbh);
        $userCount = 0;
        $userInactiveDays = SysConfig::getKeyValue($this->dbh, 'sys_config', 'userInactiveDays');
        if($users){
            foreach($users as $user){
                if($user['Last_Login']){
                    
                    $lastLogin = new DateTime($user['Last_Login']);
                    $lastLogin->setTime(0,0);
                    $now = new DateTime();
                    $today = $now->setTime(0,0);
                    $days = $lastLogin->diff($today)->format('%a');
                    if($days >= $userInactiveDays){
                        $stmt = "update w_users set `status` = 'd' where idName = $user[idName]";
                        if($this->dbh->exec($stmt) > 0){
                            $this->insertUserLog($this->dbh, $user['User_Name'], "User deactivated from inactivity");
                            $userCount++;
                        }
                        
                    }
                }
            }
        }
        return $userCount . " users deactivated";
    }
    
    protected function getUsers(\PDO $dbh){
        $stmt = $dbh->query("SELECT * FROM w_users;");
        
        if ($stmt->rowCount() > 0) {
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $rows;
        }else{
            return false;
        }
    }
    
    protected function insertUserLog(\PDO $dbh, $username, $action) {
        
        $remoteIp = "::1";
        if($dbh->exec("insert into w_user_log (Username, Access_Date, IP, `Action`) values ('" . $username . "', now(), '$remoteIp', '$action')")){
            return true;
        }else{
            return false;
        }
    }

}