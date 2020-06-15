<?php

namespace Leedch\Mysql\Tools;

use Exception;
use PDOException;
use Leedch\Mysql\Mysql;

/**
 * Script to execute DB Updates
 *
 * @author leed
 */
class Update extends Mysql
{
    protected $id;
    protected $name;
    protected $folder;
    protected $createDate;
    
    protected $knownUpdateFiles = [];
    protected $tableName;
    
    protected function getTableName() : string
    {
        return leedch_mysqlResourceTableDbUpdate;
    }
    
    /**
     * This method is triggered in Bash to process DB Update Files
     */
    public function updateDb()
    {
        $this->createUpdateTableIfNotExists();
        $this->getProcessedFiles();
        
        $path = leedch_resourceMysqlUpdateFolder;
        $files = $this->findNewFiles($path);
        
        $this->processNewFiles($files);
    }
    
    protected static function getDocRoot() : string 
    {
        $docroot = __DIR__ . '/../../../../../';
        return realpath($docroot)."/";
    }
    
    protected function createUpdateTableIfNotExists() 
    {
        $sql = "CREATE TABLE IF NOT EXISTS `".$this->getTableName()."` ("
                . "`id` INT NOT NULL AUTO_INCREMENT COMMENT 'Primary Key' , "
                . "`name` VARCHAR(255) NOT NULL COMMENT 'File Name' , "
                . "`folder` VARCHAR(255) NOT NULL COMMENT 'Folder Path' , "
                . "`createDate` DATETIME NOT NULL COMMENT 'Execution Date' , "
                . "PRIMARY KEY (`id`)) ENGINE = InnoDB;";
        $this->db->query($sql);
    }
    
    /**
     * Reads the Update Files and triggers the Update
     * @param array $files
     * @throws Exception
     */
    protected function processNewFiles(array $files)
    {
        foreach ($files as $file) {
            if (!file_exists($file)) {
                //This is bad
                throw new Exception('Cant find File: '.$file);
            }
            include $file;
            
            if (!isset($arrUpdate)) {
                throw new Exception('Array $arrUpdate does not exist in '.$file);
            }
            
            try {
                $this->processArray($arrUpdate);
                $this->addFileToProcessedList($file);
            } catch (Exception $ex) {
                echo $ex->getMessage();
            }
            
            unset($arrUpdate);
        }
    }
    
    /**
     * Puts the Filename into the update table
     * @param string $filePath
     */
    protected function addFileToProcessedList(string $filePath)
    {
        $arrFile = explode(DIRECTORY_SEPARATOR, $filePath);
        $fileName = array_pop($arrFile);
        $folder = implode(DIRECTORY_SEPARATOR, $arrFile).DIRECTORY_SEPARATOR;
        
        $folder = str_replace(static::getDocRoot(), "", realpath($folder));
        
        $tmp = new Update();
        $tmp->name = $fileName;
        $tmp->folder = $folder."/";
        $tmp->createDate = strftime("%Y-%m-%d %H:%M:%S");
        $tmp->save();
        
        echo "Processed: ".$fileName."\n";
        unset($tmp);
    }
    
    /**
     * Processes the SQL code in the Array
     * @param array $arrUpdate
     */
    protected function processArray(array $arrUpdate)
    {
        //Cant do transactions, because we cannot prepare statements
        //$this->db->beginTransaction();
        
        foreach ($arrUpdate as $sql) {
            $this->db->exec($sql);
        }
    }
    
    /**
     * Get List of processed files, catches exception if db_update does not exist
     * @return void
     */
    protected function getProcessedFiles()
    {
        $sql = "SELECT * FROM `".$this->tableName."` ORDER BY `id` ASC;";
        try {
            $arrResult = $this->getAllRows();
        } catch (PDOException $ex) {
            echo $ex->getMessage().PHP_EOL;
            return;
        }
        
        foreach ($arrResult as $row) {
            $this->knownUpdateFiles[$row['id']] = $row['folder'].$row['name'];
            $this->cleanFolderIfInOldFormat($row);
        }
    }
    
    protected function cleanFolderIfInOldFormat(array $row) 
    {
        $docroot = static::getDocRoot();
        if (isset($row['folder']) && strpos($row['folder'], $docroot) === 0) {
            $entry = new Update();
            $entry->load($row['id']);
            $entry->folder = str_replace($docroot, "", realpath($row['folder']))."/";
            $entry->save();
        }
    }
    
    /**
     * Creates an array of Update Files that were not yet processed
     * @param string $path
     * @return array
     */
    protected function findNewFiles(string $path) : array
    {
        $arrReturn = [];
        $arrFolders = scandir($path);
        $arrSkip = [
            '.',
            '..',
        ];
        
        //$path = str_replace(static::getDocRoot(), "", realpath($path));
        
        foreach ($arrFolders as $file) {
            //No Folder Defaults
            if (in_array($file, $arrSkip)) {
                continue;
            }
            //Process Subfolders
            if (is_dir($path.$file)) {
                $arrSubFolderFiles = $this->findNewFiles($path.$file."/");
                $arrReturn = $this->addArrayToNewFiles($arrSubFolderFiles, $arrReturn);
                continue;
            }
            $arrReturn[] = $path.$file;
        }
        
        //Remove file from list if already processed
        foreach($arrReturn as $key => $file) {
            $filePathShort = str_replace(static::getDocRoot(), "", realpath($file));
            if (in_array($filePathShort, $this->knownUpdateFiles)) {
                unset($arrReturn[$key]);
            }
        }
        return $arrReturn;
    }
    
    /**
     * Used to gather files in a subfolder of the mysql update folder
     * 
     * @param array $newArray
     * @param array $existingArray
     * @return array
     */
    protected function addArrayToNewFiles(array $newArray, array $existingArray) : array
    {
        foreach ($newArray as $newFile) {
            $existingArray[] = $newFile;
        }
        return $existingArray;
    }
}
