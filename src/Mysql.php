<?php

namespace Leedch\Mysql;

use Exception;
use PDO;
use PDOException;
use Leedch\Mysql\Tools\PdoSingleton;
use Leedch\Mysql\Log\MysqlLog;

/**
 * This class acts as a resource handler for other classes, it should allow them
 * to save their data without knowing anything about the source, thus be a
 * database abstraction level
 *
 * @author leed
 */
abstract class Mysql
{

    protected $host;
    protected $database;
    protected $username;
    protected $password;
    protected $charset;
    protected $tableName = null;
    /* @var $db \PDO */
    protected $db;
    protected $log;

    protected $primaryKey = "id";

    /**
     * Basic Constructor
     */
    public function __construct()
    {
        $this->host = leedch_resourceMysqlHost;
        $this->database = leedch_resourceMysqlDatabase;
        $this->username = leedch_resourceMysqlUsername;
        $this->password = leedch_resourceMysqlPassword;
        $this->charset = leedch_resourceMysqlCharset;
        $this->log = new MysqlLog();

        $this->tableName = $this->getTableName();
        $this->connect();
    }

    /**
     * This method must return the name of the table associated with the class
     */
    protected abstract function getTableName() : string;

    /**
     * Sets $this->host
     * @param string $host
     */
    public function setHost(string $host)
    {
        $this->host = $host;
    }

    /**
     * Sets $this->database
     * @param string $database
     */
    public function setDatabase(string $database) {
        $this->database = $database;
    }

    /**
     * Sets $this->user
     * @param string $user
     */
    public function setUser(string $user)
    {
        $this->user = $user;
    }

    /**
     * Sets $this->password
     * @param string $password
     */
    public function setPassword(string $password)
    {
        $this->password = $password;
    }

    /**
     * Sets $this->charset
     * @param string $charset
     */
    public function setCharset(string $charset)
    {
        $this->charset = $charset;
    }

    /**
     * Sets $this->tableName
     * @param string $tableName
     */
    public function setTableName(string $tableName)
    {
        $this->tableName = $tableName;
    }

    /**
     * Connect to MySql DB using constant params
     */
    public function connect()
    {
        $arrParams = [
            'host='.$this->host,
            'dbname='.$this->database,
            'charset='.$this->charset,
        ];

        $this->db = PdoSingleton::getInstance()->getConnection($arrParams, $this->username, $this->password);
    }

    /**
     * checks if $this->tableName is set
     * @return boolean
     */
    protected function isTableNameSet()
    {
        if ($this->tableName) {
            return true;
        }
        return false;
    }

    /**
     * Returns Columns in table
     * @return array
     */
    public function getTableColumns() : array
    {
        if (!$this->isTableNameSet()) {
            return [];
        }

        $sql = "SHOW COLUMNS FROM `".$this->tableName."`";
        $stmt = $this->db->query($sql);
        $arrResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $arrResult;
    }

    /**
     * Save if new, update if primary key isset
     * @return bool
     */
    public function save() : bool
    {
        if (!$this->isTableNameSet()) {
            return false;
        }
        $pkName = $this->primaryKey;
        if (isset($this->$pkName) && (int) $this->$pkName > 0) {
            $this->updateEntry();
        } else {
            $this->createEntry();
        }
        return true;
    }

    /**
     * Delete Row from DB Table
     * @return int
     */
    public function delete() : int
    {
        $sql = "DELETE FROM `".$this->tableName."` "
            . "WHERE `".$this->primaryKey."` = '".$this->id."';";
        return $this->db->exec($sql);
    }

    /**
     * Inset a new Row into DB Table
     */
    protected function createEntry()
    {
        $arrColumns = $this->getTableColumns();
        $arrColumnNames = [];
        $arrColumnTypes = [];

        foreach ($arrColumns as $column) {
            $arrColumnNames[] = $column['Field'];
            $arrColumnTypes[] = $column['Type'];
        }

        $sql = 'INSERT INTO `'.$this->tableName.'` '
            . '(`'.implode('`, `', $arrColumnNames).'`) '
            . 'VALUES '
            . '(:'.implode(', :', $arrColumnNames).')';

        $arrInsert = [];
        for ($i = 0; $i < count($arrColumnNames); $i++) {
            $name = $arrColumnNames[$i];
            $value = isset($this->$name)?$this->$name:"";
            if (substr((string) $arrColumnTypes[$i], 0, 3) == 'int') {
                $value = (int) $value;
            } elseif ($arrColumnTypes[$i] == 'date') {
                $value = date("Y-m-d", strtotime($value));
            } elseif ($arrColumnTypes[$i] == 'datetime') {
                $value = date("Y-m-d H:i:s", strtotime($value));
            }
            $arrInsert[':'.$name] = $value;
        }


        $this->db->beginTransaction();
        $stmt = $this->db->prepare($sql);

        try {
            $stmt->execute($arrInsert);
            $this->db->commit();
        } catch (PDOException $ex) {
            $this->db->rollBack();
            $this->log->critical(
                "Could not save to mysql".PHP_EOL
                .$sql.PHP_EOL
                .print_r($arrInsert, true).PHP_EOL
                .$ex->getMessage()
            );
        }
    }

    /**
     * Updates Row
     */
    protected function updateEntry()
    {
        $arrColumns = $this->getTableColumns();
        $arrUpdates = [];
        $arrColumnNames = [];
        $arrColumnTypes = [];

        foreach ($arrColumns as $column) {
            $arrColumnNames[] = $column['Field'];
            $arrColumnTypes[] = $column['Type'];
            $arrUpdates[] = "`".$column['Field']."` = :".$column['Field'];
        }

        $pkName = $this->primaryKey;
        $pkVal = $this->$pkName;

        $sql = 'UPDATE `'.$this->tableName.'` '
            . 'SET '.implode(", ", $arrUpdates).' '
            . 'WHERE `'.$pkName."` = '".$pkVal."';"
            ;

        $arrInsert = $this->updatePrepareStatementArray($arrColumnNames, $arrColumnTypes);

        $this->db->beginTransaction();
        $stmt = $this->db->prepare($sql);

        try {
            $stmt->execute($arrInsert);
            $this->db->commit();
        } catch (PDOException $ex) {
            $this->db->rollBack();
            $this->log->critical(
                "Could not save to mysql".PHP_EOL
                .$sql.PHP_EOL
                .print_r($arrInsert).PHP_EOL
                .$ex->getMessage()
            );
        }
    }

    /**
     * Returns an array of pattern [':name' => 'value'] for updating current row
     * using PDO prepared statements
     *
     * @param array $arrColumnNames
     * @param array $arrColumnTypes
     * @return array
     */
    protected function updatePrepareStatementArray(
        array $arrColumnNames,
        array $arrColumnTypes
    ) : array
    {
        $arrInsert = [];
        for ($i = 0; $i < count($arrColumnNames); $i++) {
            $name = $arrColumnNames[$i];
            $value = isset($this->$name)?$this->$name:"";
            if (substr((string) $arrColumnTypes[$i], 0, 3) == 'int') {
                $value = (int) $value;
            } elseif ($arrColumnTypes[$i] == 'datetime') {
                $value = date("Y-m-d H:i:s", strtotime($value));
            }
            $arrInsert[':'.$name] = $value;
        }
        return $arrInsert;
    }

    /**
     * Testing Method, do not use
     */
    public function testPdoPreparedStatements()
    {
        $sql = 'INSERT INTO `pdotest` '
            . '(`name`, `description`, `createDate`) '
            . 'VALUES '
            . '(:name, :description, :createDate)';
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare($sql);

            $arrInsert = [
                [
                    ":name" => "Dave Lee",
                    ":description" => "Master of the universe",
                    ":createDate" => "1980-06-25 01:00:25",
                ],
                [
                    ":name" => "Baby",
                    ":description" => "Born just now",
                    ":createDate" => date("Y-m-d H:i:s")
                ]
            ];

            foreach ($arrInsert as $insert) {
                $stmt->execute($insert);
            }

            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->log->critical('Error Saving to mySQL: '.$e->getMessage());
        }

    }

    /**
     * Simple SELECT Query, no transactions, fetch multiple entries
     *
     * @param array $arrWhat
     * @param array $arrWhere array of strings Example: "`name` LIKE 'Dave'"
     * @param array $arrOrder
     * @param array $arrLimit
     * @return array
     * @throws Exception
     */
    public function getAllRows(
        array $arrWhat = ['*'],
        array $arrWhere = [],
        array $arrOrder = ['`id` ASC'],
        array $arrLimit = []
    ) : array
    {
        if (count($arrWhat) < 1) {
            throw new Exception('Missing MySQL Select Params');
        }
        $strWhere = "";
        $strOrder = "";
        $strLimit = "";

        if (count($arrWhere) > 0) {
            $strWhere = "WHERE ".implode(" AND ", $arrWhere)." ";
        }

        if (count($arrOrder) > 0) {
            $strOrder = "ORDER BY ".implode(", ", $arrOrder)." ";
        }

        if (count($arrLimit) > 0) {
            $strLimit = "LIMIT ".implode(",", $arrLimit)." ";
        }

        $whatTemp = "`".implode("`,`", $arrWhat)."`";
        $what = str_replace("`*`", "*", $whatTemp);
        $sql = "SELECT ".$what." "
            . "FROM `".$this->tableName."` "
            . $strWhere
            . $strOrder
            . $strLimit
            ;
        //echo $sql;
        $stmt = $this->db->query($sql);
        $arrRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($arrRows === false) {
            throw new Exception('failed to execute '.$sql);
        }
        return $arrRows;
    }

    /**
     * Get Rows from Table using a Prepared Statement
     * @param array $arrWhat
     * @param array $arrWhere associative ["name" => "Dave"] OR ["name" => ["operator" => "like", "value" => "Dave"]]
     * @param array $arrOrder
     * @param array $arrLimit
     * @return array
     */
    public function loadByPrepStmt(
        array $arrWhat = ['*'],
        array $arrWhere = [],
        array $arrOrder = ['`id` ASC'],
        array $arrLimit = []
    ) : array
    {
        $arrStmt = [];
        $strWhere = "";
        if (count($arrWhere) > 0) {
            $arrWhereNames = [];
            foreach ($arrWhere as $key => $val) {
                if (is_array($val) && strtolower($val['operator']) === "like") {
                    $arrWhereNames[] = "`".$key."` LIKE :".$key;
                    $arrStmt[':'.$key] = $val['value'];
                } elseif (is_array($val) && strtolower($val['operator']) === "in" && is_array($val['value'])) {
                    $arrWhereNames[] = "`" . $key . "` IN ('" . implode("','", $val['value']) . "')";
                    // $arrStmt[':'.$key] = $val['value'];
                } else {
                    $arrWhereNames[] = "`".$key."` = :".$key;
                    $arrStmt[':'.$key] = $val;
                }
            }
            $strWhere = "WHERE ". implode(" AND ", $arrWhereNames) . " ";
        }

        $strOrder = "";
        if (count($arrOrder) > 0) {
            $strOrder = "ORDER BY ".implode(", ", $arrOrder) . " ";
        }

        $strLimit = "";
        if (isset($arrLimit[0])) {
            $strLimit .= "LIMIT ".((int) $arrLimit[0]);
        }
        if (isset($arrLimit[1])) {
            $strLimit .= "," . ((int) $arrLimit[1]);
        }
        $sql = "SELECT ".implode(",", $arrWhat)." FROM `".$this->tableName."` "
            . $strWhere
            . $strOrder
            . $strLimit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($arrStmt);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$result) {
            return [];
        }

        return $result;
    }

    /**
     * Extension to LoadByPrepStmt, this will return result as JSON instead of array
     * @param array $arrWhat
     * @param array $arrWhere
     * @param array $arrOrder
     * @param array $arrLimit
     * @return string JSON
     */
    public function getJsonRows(
        array $arrWhat = ['*'],
        array $arrWhere = [],
        array $arrOrder = ['`id` ASC'],
        array $arrLimit = []
    ) : string
    {
        $rows = $this->loadByPrepStmt($arrWhat, $arrWhere, $arrOrder, $arrLimit);
        $json = json_encode($rows, JSON_UNESCAPED_UNICODE);
        return $json;
    }

    /**
     * Load specific entry from DB
     * @param int $id primary key
     * @return void
     */
    public function load(int $id)
    {
        $sql = "SELECT * FROM `".$this->tableName."` "
            . "WHERE `".$this->primaryKey."` = '".$id."'"
            . "LIMIT 0,1;";
        $stmt = $this->db->query($sql);

        try {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $ex) {
            $this->log->crit('Could not find requested entry in mySQL: '.$sql."\n".$ex->getMessage());
        }

        if (!is_array($row)) {
            return;
        }

        $this->loadWithData($row);
    }

    /**
     * Use this when iterating a * sql call for multiple rows, it saves doing another
     * sql call for data you already have
     *
     * @param array $data
     */
    public function loadWithData(array $data)
    {
        foreach ($data as $key => $val) {
            $this->$key = $val;
        }
    }

    public function query(string $sql) : array
    {
        $sql = $this->fixSqlQueryDetails($sql);
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    public function execute(string $sql)
    {
        $sql = $this->fixSqlQueryDetails($sql);
        $result = $this->db->exec($sql);
        return $result;
    }

    /**
     * Some Servers don't accept `*`, so we replace it with *
     * @param string $sql
     * @return string
     */
    protected function fixSqlQueryDetails(string $sql) : string
    {
        $response = str_replace("`*`", "*", $sql);
        return $response;
    }
}
