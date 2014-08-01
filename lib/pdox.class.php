<?php

namespace Tsugi;

/* 
 * This is our "improved" version of PDO
 *
 * The PDOX class adds a number of non-trivial convienence methods
 * to the underlying PHP PDO class.   These methods combine several
 * PDO calls into a single call for common patterns and add far more
 * extensive error checking and simpler error handling.
 */
class PDOX extends \PDO {

    /*
     * Prepare and execute an SQL query and retrieve a single row.
     *
     * If the SQL is badly formed, this function will die.
     *
     * @return This either returns the associative array containing
     *         the row or FALSE.
     */
    function rowDie($sql, $arr=FALSE, $error_log=TRUE) {
        $stmt = self::queryDie($sql, $arr, $error_log);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row;
    }

    /*
     * Prepare and execute an SQL query.
     *
     * If the SQL is badly formed, this function will die.
     *
     * @return This either returns the statement that results
     *         from the execute() call if the SQL is well formed.
     */
    function queryDie($sql, $arr=FALSE, $error_log=TRUE) {
        global $CFG;
        $stmt = self::queryReturnError($sql, $arr, $error_log);
        if ( ! $stmt->success ) {
            error_log("Sql Failure:".$stmt->errorImplode." ".$sql);
            if ( isset($CFG) && isset($CFG->dirroot) && isset($CFG->DEVELOPER) && $CFG->DEVELOPER) {
                $sanity = $CFG->dirroot."/sanity-db.php";
                if ( file_exists($sanity) ) {
                    include_once($sanity);
                }
            }
            die($stmt->errorImplode); // with error_log
        }
        return $stmt;
    }

    /*
     * Prepare and execute an SQL query with lots of error checking.
     *
     * It turns out that to properly check all of the return values
     * and possible errors which using prepare() and execute()
     * we have all that logic one place.
     *
     * In order to simplify the error handling for the code making use
     * of this method, the returned PDO statement is augmented as
     * follows:
     *
     * $stmt->success is TRUE/FALSE based on the success of the operation
     * $stmt->ellapsed_time includes the length of time the query took
     *
     * If the prepare fails, we set up the following values to mirror
     * a execute() failure.
     *
     * $q->errorCode
     * $q->errorInfo
     *
     * We also concatenate the valued in errorInfo in the following attribute:
     * $q->errorImplode
     *
     * While this seems a bit obtuse, it allows the prepare() and execute() 
     * to be collapsed into one call with simple error checking upon return.
     *
     * @return This either returns a PDO statement that results
     *         from the execute() call if the SQL is well formed.
     *         See above for detia on how the statement is augmented.
     */
    function queryReturnError($sql, $arr=FALSE, $error_log=TRUE) {
        $errormode = $this->getAttribute(\PDO::ATTR_ERRMODE);
        if ( $errormode != \PDO::ERRMODE_EXCEPTION) {
            $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }
        $q = FALSE;
        $success = FALSE;
        $message = '';
        if ( $arr !== FALSE && ! is_array($arr) ) $arr = Array($arr);
        $start = microtime(true);
        // debug_log($sql, $arr);
        try {
            $q = $this->prepare($sql);
            if ( $arr === FALSE ) {
                $success = $q->execute();
            } else {
                $success = $q->execute($arr);
            }
        } catch(\Exception $e) {
            $success = FALSE;
            $message = $e->getMessage();
            if ( $error_log ) error_log($message);
        }
        if ( ! is_object($q) ) $q = stdClass();
        if ( isset( $q->success ) ) {
            error_log("\PDO::Statement should not have success member");
            die("\PDO::Statement should not have success member"); // with error_log
        }
        $q->success = $success;
        if ( isset( $q->ellapsed_time ) ) {
            error_log("\PDO::Statement should not have ellapsed_time member");
            die("\PDO::Statement should not have ellapsed_time member"); // with error_log
        }
        $q->ellapsed_time = microtime(true)-$start;
        // In case we build this...
        if ( !isset($q->errorCode) ) $q->errorCode = '42000';
        if ( !isset($q->errorInfo) ) $q->errorInfo = Array('42000', '42000', $message);
        if ( !isset($q->errorImplode) ) $q->errorImplode = implode(':',$q->errorInfo);
        // Restore ERRMODE if we changed it
        if ( $errormode != \PDO::ERRMODE_EXCEPTION) {
            $this->setAttribute(\PDO::ATTR_ERRMODE, $errormode);
        }
        return $q;
    }

    /*
     * Prepare and execute an SQL query and retrieve all the rows as an array
     *
     * While this might seem like a bad idea, the coding style for Tsugi is
     * make every query a paged query with a limited number of records to 
     * be retrieved to in most cases, it is quite reasonable to retrieve 
     * 10-30 rows into an array.
     *
     * If code wants to stream the results of a query, they should do their
     * own query and loop through the rows in their own code.
     *
     * If the SQL is badly formed, this function will die.
     *
     * @return An array of rows from the query.  If there are no rows,
     *         an empty array is returned.
     */
    function allRowsDie($sql, $arr=FALSE, $error_log=TRUE) {
        $stmt = self::queryDie($sql, $arr, $error_log);
        $rows = array();
        while ( $row = $stmt->fetch(\PDO::FETCH_ASSOC) ) {
            array_push($rows, $row);
        }
        return $rows;
    }

    /*
     * Retrieve the metadata for a table.
     * TODO: Sample return data
     */
    function metadata($tablename) {
        $sql = "SHOW COLUMNS FROM ".$tablename;
        $q = self::queryReturnError($sql);
        if ( $q->success ) {
            return $q->fetchAll();
        } else {
            return false;
        }
    }

}