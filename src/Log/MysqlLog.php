<?php

namespace Leedch\Mysql\Log;

use Leedch\Logger\Logger;

/**
 * This just extends Monolog to put in some additional configurations
 * @author leed
 */
class MysqlLog extends Logger
{
    public function __construct() {
        parent::__construct(leedch_resourceMysqlLogName);
        $this->setFileHandler(leedch_resourceMysqlPathLogFile);
        if (leedch_resourceMysqlLogEmail && leedch_resourceMysqlLogServerEmail && leedch_resourceMysqlAppName) {
            $this->setMailHandler(leedch_resourceMysqlLogEmail, "Mysql Emergeny @ ".leedch_resourceMysqlAppName, leedch_resourceMysqlLogServerEmail, Logger::CRITICAL);
        }
    }    
}
