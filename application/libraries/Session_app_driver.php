<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Session_app_driver
 *
 * @author Solin SM
 */
class CI_Session_app_driver extends CI_Session_driver implements SessionHandlerInterface {

    protected $main_db;
    protected $_row_exists = FALSE;
    protected $_platform;
    protected $_mobile = FALSE;
    protected $_token;

    // ------------------------------------------------------------------------

    public function __construct(&$params) {
        $CI = & get_instance();
        
        isset($CI->db) OR $CI->load->database();
        $this->main_db = $CI->db;
             
        if ( isMobile() ){  
            if(!is_public_service()){
                /* 
                 * This will check the authonticate of user and 
                 * successfully : return session id, token .. etc
                 * failed : return Invalid Information. Try login again
                 */
                $result_auth = check_auth();                
                $this->_token = $result_auth['token'];
//                $session_id = $result_auth['session_id'];

//                $this->_session_id = $session_id;
                unset($this->_config['cookie_name']);  

                // this fixed another issue in code igniter by kill duplicate & null data ==> user=0
                $CI->db->where("USER_ID", 0);  
                $CI->db->delete($CI->config->item('sess_save_path')); 
            }
        }

        parent::__construct($params);

        /*
         *  define you dbdriver information (oci8 / mysql / postgre ... etc)
         *  here will checl for oracle
         */
        $db_driver = $this->main_db->dbdriver . (empty($this->main_db->subdriver) ? '' : '_' . $this->main_db->subdriver);

        if (strpos($db_driver, 'oci8') == True) {
            $this->_platform = 'oracle';
            
            // remove inactive sessions
            $this->gc($CI->config->config['sess_expiration']);
            $this->_config['match_ip'] = $CI->config->config['sess_match_ip'];
        }        
    }

    // ------------------------------------------------------------------------

    /**
     * Open
     *
     * Initializes the database connection
     *
     * @param	string	$save_path	Table name
     * @param	string	$name		Session cookie name, unused
     * @return	bool
     */
    public function open($save_path, $name) {
        // faild if there is no connection to db
        if (empty($this->main_db->conn_id) && !$this->main_db->db_connect()) {
            return $this->_fail();
        }

        return $this->_success;
    }

    // ------------------------------------------------------------------------

    /**
     * Read
     *
     * Reads session data and acquires a lock
     *
     * @param	string	$session_id	Session ID
     * @return	string	Serialized session data
     */
    public function read($session_id) {
        
        // update session to be same to that store in DB for the user
//        $session_id = ($this->_session_id == '') ? $session_id : $this->_session_id;
        
        // Prevent previous QB calls from messing with our queries
        $this->main_db->reset_query();

        if ( isMobile() ){ 
            if(!is_public_service()){
                // select data from DB
                if (!($result = $this->_db->get()) OR ( $result = $result->row()) === NULL) {
                    $this->_row_exists = FALSE;
                    return '';
                }

                $this->_row_exists = TRUE;
                return $result;
                
            }else{
                return '';
            }
        }
    }
    
    // ------------------------------------------------------------------------

    /**
     * Write
     *
     * Writes (create / update) session data
     *
     * @param	string	$session_id	Session ID
     * @param	string	$session_data	Serialized session data
     * @return	bool
     */
    public function write($session_id, $session_data) {
        
        // update session to be same to that store in DB for the user
//        $session_id = ($this->_session_id == '') ? $session_id : $this->_session_id;
        
        $token = isset($_SESSION['token']) ? $_SESSION['token'] : '';
      
        if ( isMobile() ){ 
            if( $_SERVER['REQUEST_URI'] == "/login" ){
                
                // insert query ,, insert user data log in with session id, token, LAST_ACTIVITY, used id ... etc
                $sql = 'INSERT INTO "' . $this->_config['save_path'] . '" (SESSION_ID,LAST_ACTIVITY,USER_ID,TOKEN)
                        VALUES (:session_id,:timestamp,:user_id,:token)';
                
                // if done insert return $this->_success
                // else return return $this->_fail()        
            }
            
            if(!is_public_service()){
                
                //update last_activity in DB
                $sql = 'UPDATE "' . $this->_config['save_path'] . '"
			SET LAST_ACTIVITY = :timestamp
                        WHERE TOKEN = :token and user_id = :user_id';
                // ...
                
                // if done insert return $this->_success
                // else return return $this->_fail()    
            }
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Close
     *
     * Releases locks
     *
     * @return	bool
     */
    public function close() {
        return $this->_success;
    }

    // ------------------------------------------------------------------------

    /**
     * Destroy
     *
     * Destroys the current session.
     *
     * @param	string	$session_id	Session SESSION_ID
     * @return	bool
     */
    public function destroy($session_id) {
        // update session to be same to that store in DB for the user
//        $session_id = ($this->_session_id == '') ? $session_id : $this->_session_id;
        
        // Prevent previous QB calls from messing with our queries
        $this->main_db->reset_query();

        // delete query 
        $this->main_db->where('SESSION__ID', $session_id);
        if (!$this->main_db->delete($this->_config['save_path'])) {
            return $this->_fail();
        }
        
        /*
         *  destroy coockie & 
         *  return success : if the session deleted successfully
         *  return fail : if the session not deleted or there is some thing wrong
         */
        if ($this->close() === $this->_success) {
            $this->_cookie_destroy();
            return $this->_success;
        }
        return $this->_fail();
    }

    // ------------------------------------------------------------------------

    /**
     * Garbage Collector
     *
     * Deletes expired sessions
     *
     * @param	int 	$maxlifetime	Maximum lifetime of sessions
     * @return	bool
     */
    public function gc($maxlifetime) {
        // Prevent previous QB calls from messing with our queries
        $this->main_db->reset_query();

        // delete session row if it is expired or last activity is less than the specified period 
        return ($this->main_db->delete($this->_config['save_path'], 'LAST_ACTIVITY < ' . (time() - $maxlifetime))) ? $this->_success : $this->_fail();
    }

    
}
