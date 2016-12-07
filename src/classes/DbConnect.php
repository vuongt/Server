<?php

/**
 * Manage the connexion to database
 *
 */
class DbConnect {

    private $conn;

    function __construct() {
    }

    /**
     * Establish the connection
     * @return mysqli
     */
    function connect() {
        include_once dirname(__FILE__) . '/config.php';

        // Connect to mysql database
        $this->conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

        // Verify if there are any errors while connecting to the database
        if (mysqli_connect_errno()) {
            echo "Impossible de se connecter à MySQL: " . mysqli_connect_error();
        }

        //return the connection
        return $this->conn;
    }

}

?>