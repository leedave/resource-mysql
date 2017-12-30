# Using Resource Mysql #

This Component is a Database Abstraction Layer for MySQL. 

The idea is that you extend a class from \Leedch\Mysql\Mysql. Once done this class
can perform DB operations without you worrying how. 

The following example would create a (fictional) Class and store a new row into 
the Database with the Column "Name" set to Dave
```php
$myClass = new Person();
$myClass->name = 'Dave';
$myClass->save();
```

## Installation ##
Copy the config example (/vendor/leedch/configs/resource-mysql.php) anywhere you
want, set configurations according to the comments and load it when your app starts
 
## Usage ##
1. Create a new class extending \Leedch\Mysql\Mysql
2. Add the method getTableName() to the class. It must return the name of the table associated to your class
3. Enjoy

