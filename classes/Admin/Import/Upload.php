<?php
namespace HHK\Admin\Import;

use ErrorException;
use PDOException;

class Upload {

    protected \PDO $dbh;
    protected array $rawData;
    const TBL_NAME = "Import";

    public function __construct(\PDO $dbh, array|int $file) {
        $this->dbh = $dbh;
        
        if(is_array($file)){
            $this->rawData = $this->parseFile($file);
        //}elseif(is_int($file) && $file > 0){
        //    $this->rawData = $this->makeFakeMembers($file);
        }else{
            throw new ErrorException("CSV file or number of fake members is required");
        }
    }

    public function upload(){
        try{
            $this->dbh->beginTransaction();

            $this->createTable();

            $fields = array_keys($this->rawData[0]);
            $fieldList = "";

            if(count($fields) > 0){
                $fieldList = "(";
                $insertList = "(";

                //add fields
                foreach($fields as $key=>$field){
                    if($key != 0){
                        $fieldList .= ", ";
                        $insertList .= ", ";
                    }

                    $fieldList .= "`".$field."`";
                    $insertList .= ":".$field;
                }
                $fieldList .= ")";
                $insertList .= ");";
                $insertSql = "INSERT INTO `" . self::TBL_NAME . "` " . $fieldList . " VALUES " . $insertList;
                $stmt = $this->dbh->prepare($insertSql);

                foreach($this->rawData as $line){
                    try{
                        $data = array();
                        foreach ($line as $key=>$value){
                            $data[":" . $key] = htmlentities($value);
                        }
                        $stmt->execute($data);
                    }catch(PDOException $e){
                        throw new ErrorException($e->getMessage(). " Query: ".$stmt->queryString." params: ".print_r($data, true)." ImportLine: '".$line."'");
                    }
                }

                if($this->dbh->inTransaction()){
                    $this->dbh->commit();
                }
                
                return true;
            }else{
                throw new ErrorException("Failed to insert row: " . "No fields found in file.");
            }
        }catch (\Exception $e){
            if($this->dbh->inTransaction()){
                $this->dbh->rollBack();
            }
            
            throw $e;
        }
    }

    private function parseFile(array $csvFile){
        if($csvFile['type'] == 'text/csv'){

            $csv = array_map('str_getcsv', file($csvFile['tmp_name']));

            //clean up column header
            array_walk($csv[0], function(&$val){
                $val = preg_replace("/[^A-Za-z0-9]/", '', $val);
            });

            array_walk($csv, function(&$a) use ($csv) {
                $a = array_combine($csv[0], $a);
            });
            array_shift($csv); // remove column header

            return $csv;

        }else{
            throw new ErrorException("Uploaded file is not a CSV file. Type is " . $csvFile['type']);
        }
    }

    /**
     * Generate an array of fake people instead of reading CSV
     * 
     * @param int $numMembers Number of fake people to create
     * @throws \ErrorException
     * @return array
     */
    private function makeFakeMembers(int $numMembers){

        $faker = \Faker\Factory::create('en_US');
        
        if ($numMembers > 0) {
            $members = [];
            for ($i = 0; $i < $numMembers; $i++){
                $members[] = [
                    "FirstName"=>$faker->unique()->firstName(),
                    "LastName" =>$faker->lastName(),
                    "Email"    =>$faker->email(),
                    "Phone"    =>$faker->phoneNumber(),
                    "Street"   =>$faker->buildingNumber() . " " . $faker->streetName(),
                    "City"     =>$faker->city(),
                    "State"    =>$faker->state(),
                    "ZipCode"  =>$faker->postcode(),
                ];
            }

            return $members;
        }else{
            throw new ErrorException("Number of members must be > 0");
        }
    }

    private function createTable(){
        if(count($this->rawData) > 0){
            $fields = array_keys($this->rawData[0]);

            if(count($fields) > 0){
                $stmt = "CREATE OR REPLACE TABLE `" . SELF::TBL_NAME . "`(`importId` INT AUTO_INCREMENT, `imported` BOOL, status ENUM('pending', 'processing', 'done') DEFAULT 'pending', workerId  varchar(32) ";

                //add fields
                foreach($fields as $field){
                    $field = trim($field);
                    if($field == "Notes"){
                        $stmt .= ", `" . $field . "` TEXT NULL";
                    }else if(strlen($field) > 0){
                        $stmt .= ", `" . $field . "` VARCHAR(255) NULL";
                    }
                }
                $stmt .= ", PRIMARY KEY(importId));";

                if($this->dbh->exec($stmt) === false){
                    throw new ErrorException("SQL Error: " . $this->dbh->errorInfo()[2]);
                }else{
                    return true;
                }
            }else{
                throw new ErrorException("Unable to create " . self::TBL_NAME . " table: No fields found in file");
            }
        }else{
            throw new ErrorException("Unable to create " . self::TBL_NAME . " table: Unable to parse file");
        }
    }

}
?>