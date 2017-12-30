<?php

namespace Leedch\Website\Test;

use Leedch\Mysql\Mysql;

/**
 * Description of Pdotest
 *
 * @author leed
 */
class Pdotest extends Mysql
{
    protected $id;
    protected $name = "Demo Entry";
    protected $description = "Just testing stuff";
    protected $createDate = "2001-01-01 00:20:23";
    
    protected function getTableName() : string
    {
        return 'pdotest';
    }
    
}
