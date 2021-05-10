<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


/* CONTENTS:
 * ===================================================
 *  - [constants]
 *  - // DBConnect class
 *  - CSRF class
 *  - [functions]
 *    - sanitize()
 */

// configs & constants
$config = parse_ini_file('../ut_lib/config.ini', true);
//
define('DIRECTOR_NAME',      $config['director']['name']);
define('DIRECTOR_NUMBER',    $config['director']['number']);
define('ACTIVE_MIN',         $config['parameters']['active_min']);
define('QUALIFIED_MIN',      $config['parameters']['qualified_min']);
define('SEMINAR_YEAR_START', $config['parameters']['seminar_year_start']);
define('DIQ_START',          $config['parameters']['diq_start']);
//
define('WEBMASTER_EMAIL', $config['misc']['webmaster_email']);
define('MEMBERS_TABLE',   'ut_'. DIRECTOR_NUMBER .'_members');
define('ORDERS_TABLE',    'ut_'. DIRECTOR_NUMBER .'_orders');
//
define('DB_USERNAME', $config['db']['username']);
define('DB_PASSWORD', $config['db']['password']);
define('DB_HOST',     $config['db']['host']);
define('DB_DATABASE', $config['db']['database']);


// DBConnect
// database connection class
// class DBConnect {
//     var $error;
//     var $db_conn;
	
    
// 	   function DBConnect() {}
	
    
// 	   public function connect() {
//         // the @ suppresses immediate error output
// 		   $this->db_conn = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
//         if (!$this->db_conn) {
//             $this->error = 'ERROR: Couldn\'t connect to MySQL: <strong>'. mysqli_connect_error() .'</strong> Please notify <a href="mailto:'. WEBMASTER_EMAIL .'">'. WEBMASTER_EMAIL .'</a> of this error.';
//         }
        
//         return $this->db_conn || false;
// 	   }
    
    
//     public function query($query) {
//         var $result = mysqli_query($this->db_conn, $query);
        
//         if (!$result) {
//             $this->error = 'ERROR: MySQL query error: <strong>'. mysqli_error($this->db_conn) .'</strong> Please notify <a href="mailto:'. WEBMASTER_EMAIL .'">'. WEBMASTER_EMAIL .'</a> of this error.';
//         }
        
//         return $result;
//     }
    
    
//     public function select_query($query) {
//         var $result = mysql_query($this->db_conn, $query);
//         var $rows = NULL;
        
//         if (!$result) {
//             $this->error = 'ERROR: MySQL SELECT query error: <strong>'. mysqli_error($this->db_conn) .'</strong> Please notify <a href="mailto:'. WEBMASTER_EMAIL .'">'. WEBMASTER_EMAIL .'</a> of this error.';
//         }
//         else {
//             $rows = array();
//             // keep looping as long as records are returned
//             while (var $row = mysqli_fetch_array($result, MYSQL_BOTH)) {
//                 $rows[] = $row;
//             }
//         }
        
//         return $rows;
//     }
// }


// CSRF
// cross-site request forgery protection class
/*   
 *   get_token_id()
 *       - generates or returns a previously generated token ID string
 *   
 *   get_token()
 *       - generates or returns a previously generated token value string
 *   
 *   verify('get'||'post')
 *       - verifies whether or not a valid token is in form data 
 */
class CSRF {
    
    function CSRF() {}
    
    
    public function get_token_id() {
        if (!isset($_SESSION['token_id'])) {
            $token_id = $this->random(10);
            $_SESSION['token_id'] = $token_id;
        }
        
        return $_SESSION['token_id'];
    }
    
    
    public function get_token() {
        if (!isset($_SESSION['token_value'])) {
            $token = hash('sha256', $this->random(500));
            $_SESSION['token_value'] = $token;
        }
        
        return $_SESSION['token_value']; 
    }
    
    
    // $method: STRING ('get' || 'post')
    public function verify($method) {
        if ($method == 'post' || $method == 'get') {
            $form = 'post' == $method ? $_POST : $_GET;
            $token_id = $this->get_token_id();
            if (isset($form[$token_id]) && ($form[$token_id] == $this->get_token())) {
                return true;
            }
        }
        
        return false;
    }
    
    
    // $len: INTEGER
    private function random($len) {
        $return = '';
        for ($i=0; $i<$len; ++$i) {
            if ($i % 2 == 0) mt_srand(time() % 2147 * 1000000 + (double) microtime() * 1000000);
            $rand = 48 + mt_rand() % 64;
            
            if ($rand > 57)   $rand += 7;
            if ($rand > 90)   $rand += 6;
            if ($rand == 123) $rand = 52;
            if ($rand == 124) $rand = 53;
            
            $return .= chr($rand);
        }
        
        return $return;
    }
}


// This is a helper function and not meant to be called by itself
function cleanInput($input) {
    $search = array(
        '@<script[^>]*?>.*?</script>@si',   // Strip out javascript
        '@<[\/\!]*?[^<>]*?>@si',            // Strip out HTML tags
        '@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly
        '@<![\s\S]*?--[ \t\n\r]*>@'         // Strip multi-line comments
    );
    
    $output = preg_replace($search, '', $input);
    
    return $output;
}


// This cleans the input and escapes correctly
// $input: STR || ARRAY
function sanitize($input, $db_conn, $skip_db) {
    if (!$db_conn && !$skip_db) {
        $db = new DBConnect();
        $db_conn = $db->connect();
    }
    
    if (is_array($input)) {
        foreach ($input as $var=>$val) {
            $output[$var] = sanitize($val, $db_conn, $skip_db);
        }
    }
    else {
        if (get_magic_quotes_gpc()) {
            $input = stripslashes($input);
        }
        $input  = cleanInput($input);
        if (!$skip_db) {
            $output = mysql_real_escape_string($input, $db_conn);
        }
        else $output = $input;
    }
    
    return $output;
}

?>
