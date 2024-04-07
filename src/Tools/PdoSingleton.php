<?php

namespace Leedch\Mysql\Tools;

use PDO;

/**
 * Prevent to many PDO DB Connections by loading as singleton
 *
 * @author leed
 */
class PdoSingleton
{
    private $arrConnections;
    private static $_instance;
    /**
     * Use this to load as Singleton
     * @return \Leedch\Website\Resource\Mysql\PdoSingleton
     */
    public static function getInstance()
    {
        if(!self::$_instance){
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function getConnection(
        array $arrParams,
        string $username,
        string $password
    ): PDO
    {
        $hash = md5(implode(";", $arrParams));
        if(!isset($this->arrConnections[$hash])){
            $pdo = new PDO('mysql:'.implode(";", $arrParams), $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->arrConnections[$hash] = $pdo;
            return $this->arrConnections[$hash];
        }else{
            return $this->arrConnections[$hash];
        }
    }
}
