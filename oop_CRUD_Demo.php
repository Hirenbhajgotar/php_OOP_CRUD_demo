<?php

/*
* DEPENDENCIES:
* include 'class.MySqliConnection.inc'; //custom class
*
*/

class TableManager{

    private $lastQuery;
    private $lastInsertId;
    private $tableName;
    private $tableIdName;
    private $columnNames = array();
    private $lastResult = array();
    private $curentRow = array();
    private $newPost = array();

    /*
    * Class constructor 
    * [1] (string) $tableName // name of the table which you want to work with
    * [2] (string) $tableIdName // name of the ID field which will be used to delete and update records
    * @return void
    */
    function __construct($tableName, $tableIdName){
        $this->tableIdName = $tableIdName;
        $this->tableName = $tableName;
        $this->getColumnNames();
        $this->curentRow = $this->columnNames;
    }

public function getColumnNames(){
    $sql_query = 'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = "'.$this->tableName.'"';
    $mysqli = $this->connection();
    $this->lastQuery = $sql_query;
    $result = $mysqli->query($sql_query);
    if (!$result) {
        throw new Exception("Database Error [{$this->database->errno}] {$this->database->error}");
    }
    while($row = $result->fetch_array(MYSQLI_ASSOC)){           
        $this->columnNames[$row['COLUMN_NAME']] = null;
    }
}

    /*
    * Used by a Constructor to set native parameters or virtual array curentRow of the class
    * [1] (array) $v
    * @return void
    */
    function setRowValues($v){

        if(!is_array($v)){
            $this->curentRow = $v;
            return true;    
        }
        foreach ($v as $a => $b) {
            $method = 'set'.ucfirst($a);
            if(is_callable(array($this, $method))){
                //if method is callable use setSomeFunction($k, $v) to filter the value
                $this->$method($b);
            }else{
                $this->curentRow[$a] = $b;
            }
        }

    }

    /*
    * Used by a constructor to set native parameters or virtual array curentRow of the class
    * [0]
    * @return void
    */
    function __toString(){
        var_dump($this);
    }

    /*
    * Query Database for information - Select column in table where column = somevalue
    * [1] (string) $column_name // name od a column
    * [2] (string) $quote_pos // searched value in a specified column
    * @return void
    */
    public function getRow($column_name = false, $quote_post = false){
        $mysqli = $this->connection();
        $quote_post = $mysqli->real_escape_string($quote_post);
        $this->tableName = $mysqli->real_escape_string($this->tableName);
        $column_name = $mysqli->real_escape_string($column_name);
        if($this->tableName && $column_name && $quote_post){
            $sql_query = 'SELECT * FROM '.$this->tableName.' WHERE '.$column_name.' = "'.$quote_post.'"';
            $this->lastQuery = $sql_query;
            $result = $mysqli->query($sql_query);
            if (!$result) {
                throw new Exception("Database Error [{$this->database->errno}] {$this->database->error}");
            }
            while($row = $result->fetch_array(MYSQLI_ASSOC)){
                $this->lastResult[$row['ID']] = $row;
                $this->setRowValues($row);
            }
        }
        if($this->tableName && $column_name && !$quote_post){
            $sql_query = 'SELECT '.$column_name.' FROM '.$this->tableName.'';
            $this->lastQuery = $sql_query;
            $result = $mysqli->query($sql_query);
            if (!$result) {
                throw new Exception("Database Error [{$this->database->errno}] {$this->database->error}");
            }
            while($row = $result->fetch_array(MYSQLI_ASSOC)){           
                $this->lastResult[] = $row;
                $this->setRowValues($row);
            }       
        }
        if($this->tableName && !$column_name && !$quote_post){
            $sql_query = 'SELECT * FROM '.$this->tableName.'';
            $this->lastQuery = $sql_query;
            $result = $mysqli->query($sql_query);
            if (!$result) {
                throw new Exception("Database Error [{$this->database->errno}] {$this->database->error}");
            }
            while($row = $result->fetch_array(MYSQLI_ASSOC)){           
                $this->lastResult[$row['ID']] = $row;
                $this->setRowValues($row);
            }       
        }
    }


    /*
    * Connection class gets instance of db connection or if not exsist creats one
    * [0]
    * @return $mysqli
    */
    private function connection(){
        $this->lastResult = "";
        $db = MySqliConnection::getInstance('localhost', 'root', '', 'sandbox');
        $mysqli = $db->getConnection();
        return $mysqli;
    }

    /*
    * ...
    * [1] (string) $getMe
    * @return void
    */
    function __get($getMe){
        if(isset($this->curentRow[$getMe])){
            return $this->curentRow[$getMe];
        }else{
            throw new Exception("Error Processing Request - No such a property in (array) $this->curentRow", 1);
        }

    }

    /*
    * ...
    * [2] (string) $setMe, (string) $value
    * @return void
    */
    function __set($setMe, $value){
        $temp = array($setMe=>$value);
        $this->setRowValues($temp);
    }

    /*
    * Dumps the object
    * [0] 
    * @return void
    */
    function dump(){
        echo "<hr>";
        var_dump($this);
        echo "<hr>";
    }

    /*
    * Sets Values for $this->newPost array which will be than inserted by insertNewPost() function
    * [1] (array) $newPost //array of avlue that will be inserted to $this->newPost
    * @return bolean
    */
    public function setNewRow($arr){

        if(!is_array($arr)){
            $this->newPost = $arr;
            return false;   
        }
        foreach ($arr as $k => $v) {
            if(array_key_exists($k, $this->columnNames)){
                $method = 'set'.ucfirst($k);
                if(is_callable(array($this, $method))){
                    if($this->$method($v) == false){ //if something go wrong
                        $this->newPost = array(); //empty the newPost array and return flase
                        throw new Exception("There was a problem in setting up New Post parameters. [Cleaning array]", 1);
                    }
                }else{
                    $this->newPost[$k] = $v;
                }
            }else{
                $this->newPost = array(); //empty the newPost array and return flase
                throw new Exception("The column does not exsist in this table. [Cleaning array]", 1);
            }
        }

    }


    /*
    * Inserts new post held in $this->newPost
    * [0]
    * @return bolean
    */
    public function insertNewRow(){
        // check if is set, is array and is not null
        if(isset($this->newPost) && !is_null($this->newPost) && is_array($this->newPost)){
            $mysqli = $this->connection();
            $count_lenght_of_array = count($this->newPost);

            // preper insert query
            $sql_query = 'INSERT INTO '.$this->tableName.' (';
            $i = 1;
            foreach ($this->newPost as $key => $value) {
                $sql_query .=$key;
                if ($i < $count_lenght_of_array) {
                    $sql_query .=', ';
                }

                $i++;
             } 

            $i = 1;  
            $sql_query .=') VALUES (';
            foreach ($this->newPost as $key => $value) {
                $sql_query .='"'.$value.'"';
                if ($i < $count_lenght_of_array) {
                    $sql_query .=', ';
                }

                $i++;
             }  

            $sql_query .=')';
            var_dump($sql_query);
            if($mysqli->query($sql_query)){
                $this->lastInsertId = $mysqli->insert_id;
                $this->lastQuery = $sql_query;
            }

            $this->getInsertedPost($this->lastInsertId);
        }
    }   

    /*
    * getInsertedPost function query the last inserted id and assigned it to the object.
    * [1] (integer) $id // last inserted id from insertNewRow fucntion
    * @return void
    */
    private function getInsertedPost($id){
        $mysqli = $this->connection();
        $sql_query = 'SELECT * FROM '.$this->tableName.' WHERE '.$this->tableIdName.' = "'.$id.'"';
        $result = $mysqli->query($sql_query);
        while($row = $result->fetch_array(MYSQLI_ASSOC)){
            $this->lastResult[$row['ID']] = $row;
            $this->setRowValues($row);
        }       
    }

    /*
    * getInsertedPost function query the last inserted id and assigned it to the object.
    * [0]
    * @return bolean // if deletion was successful return true
    */
    public function deleteLastInsertedPost(){
        $mysqli = $this->connection();
        $sql_query = 'DELETE FROM '.$this->tableName.' WHERE '.$this->tableIdName.' = '.$this->lastInsertId.'';
        $result = $mysqli->query($sql_query);
        if($result){
            $this->lastResult[$this->lastInsertId] = "deleted";
            return true;
        }else{
            throw new Exception("We could not delete last inserted row by ID [{$mysqli->errno}] {$mysqli->error}");

        }
        var_dump($sql_query);       
    }

    /*
    * deleteRow function delete the row with from a table based on a passed id
    * [1] (integer) $id // id of the table row to be delated
    * @return bolean // if deletion was successful return true
    */
    public function deleteRow($id){
        $mysqli = $this->connection();
        $sql_query = 'DELETE FROM '.$this->tableName.' WHERE '.$this->tableIdName.' = '.$id.'';
        $result = $mysqli->query($sql_query);
        if($result){
            $this->lastResult[$this->lastInsertId] = "deleted";
            return true;
        }else{
            return false;
        }
        var_dump($sql_query);       
    }

    /*
    * deleteAllRows function deletes all rows from a table
    * [0]
    * @return bolean // if deletion was successful return true
    */
    public function deleteAllRows(){
        $mysqli = $this->connection();
        $sql_query = 'DELETE FROM '.$this->tableName.'';
        $result = $mysqli->query($sql_query);
        if($result){
            return true;
        }else{
            return false;
        }       
    }

    /*
    * updateRow function updates all values to object values in a row with id
    * [1] (integer) $id
    * @return bolean // if deletion was successful return true
    */
    public function updateRow($update_where = false){
        $id = $this->curentRow[$this->tableIdName];
        $mysqli = $this->connection();
        $updateMe = $this->curentRow;
        unset($updateMe[$this->tableIdName]);
        $count_lenght_of_array = count($updateMe);
        // preper insert query
        $sql_query = 'UPDATE '.$this->tableName.' SET ';
        $i = 1;
        foreach ($updateMe as $k => $v) {
            $sql_query .= $k.' = "'.$v.'"';
            if ($i < $count_lenght_of_array) {
                $sql_query .=', ';
            }

            $i++;
         }
        if($update_where == false){ 
            //update row only for this object id
            $sql_query .=' WHERE '.$this->tableIdName.' = '.$this->curentRow[$this->tableIdName].'';
        }else{
            //add your custom update where query
            $sql_query .=' WHERE '.$update_where.'';
        }

        var_dump($sql_query);
        if($mysqli->query($sql_query)){
            $this->lastQuery = $sql_query;
        }
        $result = $mysqli->query($sql_query);
        if($result){
            return true;
        }else{
            return false;
        }       
    }

}



/*TO DO

1 insertPost(X, X) write function to isert data and in to database;
2 get better query system and display data from database;
3 write function that displays data of a object not databsae;
object should be precise and alocate only one instance of the post at a time. 
// Updating the Posts to curent object $this->curentRow values
->updatePost();

// Deleting the curent post by ID

// Add new row to database

*/





/*
USE EXAMPLE
$Post = new TableManager("post_table", "ID"); // New Object

// Getting posts from the database based on pased in paramerters
$Post->getRow('post_name', 'SOME POST TITLE WHICH IS IN DATABASE' );
$Post->getRow('post_name');
$Post->getRow();



MAGIC GET will read current object  $this->curentRow parameter values by refering to its key as in a varible name
echo $Post->ID.
echo $Post->post_name;
echo $Post->post_description;
echo $Post->post_author;


$Task = new TableManager("table_name", "table_ID_name"); // creating new TableManager object

$addTask = array( //just an array [colum_name] => [values]
    'task_name' => 'New Post',
    'description' => 'New Description Post',
    'person' => 'New Author',
    );
$Task->setNewRow($addTask); //preper new values for insertion to table
$Task->getRow('ID', '12'); //load value from table to object

$Task->insertNewRow(); //inserts new row
$Task->dump(); //diplays object properities

$Task->person = "John"; //magic __set() method will look for setPerson(x,y) method firs if non found will assign value as it is. 
$Task->description = "John Doe is a funny guy"; //magic __set() again
$Task->task_name = "John chellange"; //magic __set() again

$test = ($Task->updateRow("ID = 5")) ? "WORKS FINE" : "ERROR"; //update cutom row with object values
echo $test;
$test = ($Task->updateRow()) ? "WORKS FINE" : "ERROR"; //update curent data loaded in object
echo $test;
*/
