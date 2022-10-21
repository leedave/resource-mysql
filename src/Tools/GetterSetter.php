<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Leedch\Mysql\Tools;

use Leedch\Mysql\Mysql;

/**
 * Description of GetterSetter
 *
 * @author leed
 */
class GetterSetter extends Mysql
{
    protected function getTableName() : string
    {
        return "";
    }
    
    public function generateGettersAndSetters(string $tableName): string 
    {
        $response = "";
        $this->setTableName($tableName);
        $columns = $this->getTableColumns();
        foreach ($columns as $column) {
            $response .= static::generateGetterAndSetter($column);
        }
        return $response;
    }
    
    protected static function generateGetterAndSetter(array $columnData): string
    {
        if (!isset($columnData['Type'])) {
            return "";
        }
        $arrType = explode("(", $columnData['Type']);
        $type = $arrType[0];
        $field = $columnData['Field'];
        $columnTypes = static::getTableColumnTypes();
        $phpType = in_array($type, $columnTypes)?$columnTypes[$type]:"string";
        
        return "public function get". ucwords($field)."(): ".$phpType."\n"
            .  "{\n"
            . '    return ('.$phpType.') $this->'.$field.";\n"
            .  "}\n"
            .  "\n"
            .  "public function set". ucwords($field)."(".$phpType." $".$field.")\n"
            . "{\n"
            . '    $this->'.$field.' = $'.$field.";\n"
            . "}\n"
            . "\n"
            ;
    }
    
    public static function getTableColumnTypes(): array
    {
        return [
            "varchar" => "string",
            "text" => "string",
            "date" => "string",
            "datetime" => "string",
            "time" => "string",
            "year" => "string",
            "boolean" => "int",
            "int" => "int",
            "tinyint" => "int",
            "smallint" => "int",
            "mediumint" => "int",
            "bigint" => "int",
            "timestamp" => "int",
        ];
    }
}
