<?php
namespace HHK\Admin\Import;

class Upload {

    protected \PDO $dbh;
    protected array $rawData;
    const TBL_NAME = "Import";

    public function __construct(\PDO $dbh, array $file) {
        $this->dbh = $dbh;
        $this->rawData = $this->parseFile($file);
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
                        $fieldList .= ",";
                        $insertList .= ",";
                    }

                    $fieldList .= "`" . $field . "`";
                    $insertList .= ":" . $field;
                }
                $fieldList .= ")";
                $insertList .= ");";
                $insertSql = "INSERT INTO `" . self::TBL_NAME . "` " . $fieldList . " VALUES " . $insertList;
                $stmt = $this->dbh->prepare($insertSql);

                foreach($this->rawData as $line){
                    $data = array();
                    foreach ($line as $key=>$value){
                        $data[":" . $key] = $value;
                    }
                    $stmt->execute($data);
                }
                $this->dbh->commit();
                return true;
            }else{
                throw new \ErrorException("Failed to insert row: " . "No fields found in file.");
            }
        }catch (\Exception $e){
            $this->dbh->rollBack();
            throw $e;
        }
    }

    private function parseFile(array $csvFile){
        if($csvFile['type'] == 'text/csv'){

            $csv = array_map('str_getcsv', file($csvFile['tmp_name']));
            array_walk($csv, function(&$a) use ($csv) {
                $a = array_combine($csv[0], $a);
            });
            array_shift($csv); // remove column header

            return $csv;

        }else{
            throw new \ErrorException("Uploaded file is not a CSV file. Type is " . $csvFile['type']);
        }
    }

    private function createTable(){
        if(count($this->rawData) > 0){
            $fields = array_keys($this->rawData[0]);

            if(count($fields) > 0){
                $stmt = "CREATE TABLE `" . SELF::TBL_NAME . "`(`importId` INT AUTO_INCREMENT";

                //add fields
                foreach($fields as $field){
                    $stmt .= ", `" . $field . "` VARCHAR(255) NULL";
                }
                $stmt .= ", PRIMARY KEY(importId));";

                if($this->dbh->exec($stmt) === false){
                    throw new \ErrorException("SQL Error: " . $this->dbh->errorInfo()[2]);
                }else{
                    return true;
                }
            }else{
                throw new \ErrorException("Unable to create " . self::TBL_NAME . " table: No fields found in file");
            }
        }else{
            throw new \ErrorException("Unable to create " . self::TBL_NAME . " table: Unable to parse file");
        }
    }

}
?>