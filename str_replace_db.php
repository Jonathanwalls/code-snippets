<?php



/* WORDPRESS USERS */

if(file_exists('wp-config.php')){

	

	require_once('wp-config.php');

	

} else {

	define('DB_NAME', '');

	

	/** MySQL database username */

	define('DB_USER', '');

	

	/** MySQL database password */

	define('DB_PASSWORD', '');

	

	/** MySQL hostname */

	define('DB_HOST', 'localhost');

	

	define('DB_SCHEME', 'mysql'); // defaults to mysql, supports mysqli

}





/* DRUPAL USERS */



#$db_url = 'mysqli://username:pass@localhost/dbname';







/**

 * What do you want to search/replace?

 * 

 * You can use 2 strings or 2 arrays (of strings)

 */



$search_for = 'onpointadv.com/wordpress/';

$replace_with = 'onpointadv.com.s68457.gridserver.com/';

$undo = false; // true will reverse the 2 values above

$search_only = true; // this will provide a report of where a string was found



// if you only want to perform search/replace on specific tables, put them into this array.

$tables = array();







$reporting = 'basic'; // basic or extended













/*  no need to edit below this line */



class Str_replace_db {

	

	private $debug = false;

	private $init = false;

	

	private $errs = array();

	private $warns = array();

	private $msgs = array();

	private $changes = array();

	

	private $db_config;

	private $cache = array();

	

	private $search = '';

	private $replace = '';

	private $search_only = false;

	

	private $reporting = 'basic';

	

	private $start_time;

	private $queries_run = 0;

	private $items_changed = 0;

	private $items_checked = 0;

	

	function __construct($debug=false){

		

		global $reporting;

		global $search_only;

		

		$this->debug = $debug;

		

		$this->message('Initializing.');

		

		$this->_prevent_timeout();

		

		if($this->debug){

			$this->message('DEBUG MODE IS: <strong>ON</strong>.');

		}

		

		$this->_setup_search_replace();

		

		if($reporting){

			$this->reporting = $reporting;

		}

		

		$this->search_only = $search_only;

		

		if($this->search_only){

			$this->message('SEARCH MODE ONLY: <strong>TRUE</strong>.');

		}

		

		$this->start_time = $this->get_time(false);

		

		$this->init = true;

	}

	

	function get_time($string=true){

		if($string){

			$time = microtime();

			$time = ltrim(substr($time, 0, strpos($time, ' ')), '0');

			$time = date('Y-m-d H:i:s').$time;

			return $time;

		}

		return microtime(true);

	}

	

	function warning($warning){

		if($warning){

			$this->warns[$this->get_time()] = $warning;

		}

	}

	

	function error($error){

		if($error){

			$this->errs[$this->get_time()] = $error;

		}

	}

	

	function message($message){

		if($message){

			$this->msgs[$this->get_time()] = $message;

		}

	}

	

	function change($change){

		if($change){

			$this->changes[$this->get_time()] = htmlspecialchars($change);

		}

	}

	

	function _prevent_timeout(){

		$hrs = 1; // allow this script to run at 1 hour max

		set_time_limit(60*60*$hrs);

		

		$msg = 'Max Execution Time set to '.$hrs.' hour';

		if($hrs != 1){

			$msg .= 's';

		}

		$this->message($msg.'.');

	}

	

	

	function _setup_search_replace($search=NULL, $replace=NULL){

		global $search_for;

		global $replace_with;

		global $undo;

		

		if($this->search){

			return;

		}

		

		$search_for = $search ? $search : $search_for;

		$replace_with = $replace ? $replace : $replace_with;

		

		if($undo){

			$temp = $search_for;

			$search_for = $replace_with;

			$replace_with = $temp;

		}

		

		if($search_for != $this->search){

			$this->search = $search_for;

			if(is_array($this->search)){

				$search_for = implode(', ', $this->search);

			}

			$this->message('USING Search Term(s): <strong>'.$search_for.'</strong>.');

		}

		

		if($replace_with != $this->replace){

			$this->replace = $replace_with;

			

			if(is_array($this->replace)){

				$replace_with = implode(', ', $this->replace);

			}

			

			$this->message('USING Replace Term(s): <strong>'.$replace_with.'</strong>.');

		}

	}

	

	

	/**

	 * Str_replace_db::db_params()

	 * 

	 * @return

	 */

	function db_params(){

		global $db_url;

		

		if($this->db_config){

			return $this->db_config;

		}

		

		$args = func_get_args();

		

		if(count($args) > 0){

			if(is_array($args[0])){

				$db_params = $args[0];

			} else {

				$this->error('ERROR: Database parameters must be passed as associative array.');

			}

			

			if(!isset($db_params['scheme']) || empty($db_params['scheme'])){

				$db_params['scheme'] = 'mysql';

			}

			$db_params['using'] = 'custom';

			

		} elseif(isset($db_url) && !empty($db_url)){

		

			$db_params = parse_url($db_url);

			

			$db_params['database'] = trim($db_params['path'],'/');

			unset($db_params['path']);

			

			foreach($db_params as $k => $v){

				$db_params[$k] = urldecode($v);

			}

			if(!isset($db_params['pass'])){

				$db_params['pass'] = '';

			}

			if (isset($db_params['port'])) {

				$db_params['host'] = $url['host'] .':'. $url['port'];

			}

			

			$db_params['using'] = 'drupal';

			

		} elseif( defined('DB_NAME') && defined('DB_USER') && defined('DB_PASSWORD') && defined('DB_HOST') ){

			

			$db_params['scheme'] = !defined('DB_SCHEME') ? 'mysql' : DB_SCHEME;

			$db_params['database'] = DB_NAME;

			$db_params['host'] = DB_HOST;

			$db_params['user'] = DB_USER;

			$db_params['pass'] = DB_PASSWORD;

			

			$db_params['using'] = 'wordpress';

			

		} else {

			//TODO display a form where credentials can be entered.

			$this->error('Could not find database connection.');

			

		}

		

		$this->db_config = $db_params;

		

		return $this->db_config;

	}

	

	function db_connection(){

		$conn = false;

		

		$config = $this->db_params();

		

		if($config['scheme'] == 'mysql'){

			

			$conn = @mysql_connect($config['host'], $config['user'], $config['pass']);

			if(!$conn){

				$this->error('MySQL Connection Error: ' . mysql_error());

			} else {

				$sel = mysql_select_db($config['database']);

				if(!$sel){

					$this->error('MySQL Database Error: ' . mysql_error());

					$conn = false;

				}

			}

			

		} elseif($config['scheme'] == 'mysqli'){

			

			$conn = @mysqli_connect($config['host'], $config['user'], $config['pass'], $config['database']);

			if(!$conn){

				$this->error('MySQLi Connection Error: ' . mysqli_error($conn));

			}

			

		} else {

			$this->error('Unsupported database scheme');

		}

		

		return $conn;

	}

	

	function db_query($sql){

		

		$db = $this->db_connection();

		$config = $this->db_params();

		

		$obj = new stdClass();

		

		$obj->sql = trim($sql);

		$affected = false;

		

		if(substr($obj->sql, 0, 6) == 'INSERT' ||

			substr($obj->sql, 0, 6) == 'UPDATE' ||

			substr($obj->sql, 0, 7) == 'REPLACE' ||

			substr($obj->sql, 0, 6) == 'DELETE'){

				

			$affected = true;

		}

		

		if(!$db){

			$this->error('Unable to connect to database');

		} else {

				

			if($config['scheme'] == 'mysql'){

				

				$obj->result = @mysql_query($obj->sql, $db);

				$this->queries_run++;

				

				$affected_rows = 'mysql_affected_rows';

				$num_rows = 'mysql_num_rows';

				

			} elseif($config['scheme'] == 'mysqli'){

				

				$obj->result = @mysqli_query($db, $obj->sql);

				$this->queries_run++;

				

				$affected_rows = 'mysqli_affected_rows';

				$num_rows = 'mysqli_num_rows';

			}

			

			if($obj->result){

				if($affected){

					$obj->rows = $affected_rows();

				} else {

					$obj->rows = $num_rows($obj->result);

				}

			} else {

				if($config['scheme']){

					$this->error('MySQL Query Error: '.mysql_error());

				} elseif($config['scheme'] == 'mysqli'){

					$this->error('MySQLi Query Error: '.mysqli_error($db));

				}

			}

		}

		

		return $obj;

	}

	

	function db_escape($str){

		$config = $this->db_params();

		if($config['scheme'] == 'mysql'){

			return mysql_real_escape_string($str);

		} elseif($config['scheme'] == 'mysqli'){

			return mysqli_real_escape_string($this->db_connection(), $str);

		}

	}

	

	

	function get_tables(){

		

		global $tables;

		

		$config = $this->db_params();

		

		if(isset($this->cache['db'][$config['database']]['db_tables'])){

			return $this->cache['db'][$config['database']]['db_tables'];

		}

		

		if(count($tables) <= 0){

			

			$tables = array();

			

			$sql = "SHOW TABLES;";

			$res = $this->db_query($sql);

			

			if($config['scheme'] == 'mysql'){

				while($row = mysql_fetch_object($res->result)){

					$tables[] = $row->{'Tables_in_'.$config['database']};

				}

			} elseif($config['scheme'] == 'mysqli'){

				while($row = mysqli_fetch_object($res->result)){

					$tables[] = $row->{'Tables_in_'.$config['database']};

				}

			}

			

		}

		

		$this->cache['db'][$config['database']]['db_tables'] = $tables;

		

		return $tables;

	}

	

	function get_columns($table){

		

		$config = $this->db_params();

		

		if(isset($this->cache['db'][$config['database']][$table]['cols'])){

			return $this->cache['db'][$config['database']][$table]['cols'];

		}

		

		$cols = array();

		

		$sql = 'SHOW FULL FIELDS FROM `'.$table.'`;';

		$res = $this->db_query($sql);

		

		if($config['scheme'] == 'mysql'){

			while($row = mysql_fetch_object($res->result)){

				$cols[] = $row;

			}

		} elseif($config['scheme'] == 'mysqli'){

			while($row = mysqli_fetch_object($res->result)){

				$cols[] = $row;

			}

		}

		

		$this->cache['db'][$config['database']][$table]['cols'] = $cols;

		

		return $cols;

	}

	

	function get_index($var=NULL){

		if(is_string($var)){

			$cols = $this->get_columns($var);

		} elseif(is_array($var)){

			$cols = $var;

		} else {

			$this->error('Invalid argument for get_index() method.');

			return false;

		}

		

		$index = false;

		

		foreach($cols as $col){

			if(isset($col->Key) && $col->Key == 'PRI'){

				$index = $col;

				break;

			}

		}

		

		return $index;

	}

	

	function get_data($table){

		if(!$table){

			$this->error('Cannot get data without table name.');

			return false;

		}

		

		$config = $this->db_params();

		

		$sql = 'SELECT * FROM `'.$table.'`;';

		$res = $this->db_query($sql);

		

		return $res;

	}

	

	function search_replace($search='', $replace=''){

		$config = $this->db_params();

		

		if(!$this->init){

			$this->__construct();

		}

		

		$this->_setup_search_replace($search, $replace);

		

		if(!$this->search){

			$this->error('Search value is blank. Nothing to search for.');

			$this->finish();

			return;

		}

		

		$params = $this->db_params();

		$tables = $this->get_tables();

		$num_tables = count($tables);

		

		$this->message('<strong>'.$num_tables.'</strong> Tables Found in <em>'.$params['database'].'</em>');



		foreach($tables as $table){

			$data = $this->get_data($table);

			if($data->rows <= 0){

				$this->message('SKIPPED table <em>'.$table.'</em>: No Data.');

				continue;

			}

			

			$columns = $this->get_columns($table);

			$index = $this->get_index($columns);

			

			if(!$index){

				$this->warning('WARNING: Table <em>'.$table.'</em> has No Identifiable Key Column.');

				continue;

			}

			

			$this->message('ANALYZING table <strong>'.$table.'</strong>: '.$data->rows.' total rows.');

			

			$table_changes = 0;

			

			$loop = 'mysql_fetch_assoc';

			

			if($config['scheme'] == 'mysql'){

				$loop = 'mysql_fetch_assoc';

			} elseif($config['scheme'] == 'mysqli'){

				$loop = 'mysqli_fetch_assoc';

			}

			

			while ($row = $loop($data->result)) {

				

				$set = array();

				$where = array();

				

				if($index){

					$where[$index->Field] = $row[$index->Field];

				}

				

				$row_change = false;

				

				foreach ($columns as $column) {

					$col_change = false;

					

					$this->items_checked++;

					

					$orig_value = $row[$column->Field];

					$new_value = $orig_value;

					

					if(strpos($orig_value, $this->search) !== false){

						

						$unserialized = unserialize($orig_value); // unserialise - if false returned we don't try to process it as serialised

						

						if ($unserialized === false) {

							

							if (is_string($orig_value)){

								$new_value = str_replace($this->search, $this->replace, $orig_value);

							}

						

						} else {

							$this->recursive_array_replace($this->search, $this->replace, $unserialized);

							

							$new_value = serialize($unserialized);

						}

					}

					

					if ($orig_value != $new_value) {   // If they're not the same, we need to add them to the update string

						$this->items_changed++;

						$table_changes++;

						

						$col_change = true;

						$row_change = true;

					}

						

					if($col_change){

						$set[$column->Field] = $new_value;

					}

					if(!$index){

						$where[$column->Field] = $orig_value;

					}

				}

					

				if ($row_change) {

					

					if(count($set) > 0 && count($where) > 0){

						$updateSQL = 'UPDATE `'.$table.'`';

						$setSQL = '';

						$whereSQL = '';

						

						foreach($set as $k => $item){

							$val = $this->db_escape($item);

							if($setSQL){

								$setSQL .= ', ';

							}

							$setSQL .= "`$k` = '$val'";

						}

						

						foreach($where as $k => $item){

							$val = $this->db_escape($item);

							if($whereSQL){

								$whereSQL .= ', ';

							}

							$whereSQL .= "`$k` = '$val'";

						}

						

						if($this->search_only){

							$this->change('INSTANCE FOUND in Table `'.$table.'` WHERE '.$whereSQL);

							continue;

						} else {

							

							$updateSQL .= ' SET '.$setSQL . ' WHERE '. $whereSQL.';';

							$this->change($updateSQL);

						}

					

						if(!$this->debug){

							####

							#

							# The following line performs the actual database update.

							# This is the only than that changes data in the database.

							# It is recommend you run first run in debug mode to test.

							#

							####

							

							$this->db_query($updateSQL);

						}

					}

					

				}

			

			} // end rows while

			

			$this->message($table_changes.' total updates for <strong>'.$table.'</strong>');

		}

		

		$this->finish();

	}

	

	// Credits:  moz667 at gmail dot com for his recursive_array_replace posted at

	//           uk.php.net which saved me a little time - a perfect sample for me

	//           and seems to work in all cases.

	

	function recursive_array_replace($find, $replace, &$data){

		

		if (is_array($data)) {

			foreach ($data as $key => $value) {

				if (is_array($value)) {

					$this->recursive_array_replace($find, $replace, $data[$key]);

				} else {

					if (is_string($value)){

						$data[$key] = str_replace($find, $replace, $value);

					}

				}

			}

		} else {

			if (is_string($data)){

				$data = str_replace($find, $replace, $data);

			}

		}

		

		return $data;

	}

	

	function finish(){

		$this->message('<strong>Finished.</strong>');

		$end_time = $this->get_time(false);

		$diff = $end_time - $this->start_time;

		$this->message('Execution Time: <strong>'.$diff.'ms</strong>');

		$this->message('Queries Executed: <strong>'.number_format($this->queries_run).'</strong>');

		$this->message('Items Checked: <strong>'.number_format($this->items_checked).'</strong>');

		$this->message('Items Changed: <strong>'.number_format($this->items_changed).'</strong>');

	}

	

	function get_report(){

		$messages = $this->errs + $this->msgs + $this->changes + $this->warns;

		

		ksort($messages);

		

		if(count($messages) > 0){

			foreach($messages as $k => $msg){

				$class = 'general';

				

				if(isset($this->msgs[$k])){

					$class = 'message';

				} elseif(isset($this->errs[$k])){

					$class = 'error';

				} elseif(isset($this->changes[$k])){

					$class = 'change';

				} elseif(isset($this->warns[$k])){

					$class = 'warning';

				}

				

				echo '<div class="'.$class.'"><span class="time">['.$k.']</span> '.$msg.'</div>';

			}

		}

	}

}





?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">



<head>

	<meta http-equiv="content-type" content="text/html; charset=iso-8859-1" />

	<meta name="author" content="Brian DiChiara" />



	<title>Str_replace_db</title>

	<style type="text/css">

		.error { color:#C00; }

		.change { color:#2861B7; }

		.warning { color:#EDBE02; }

	</style>

</head>



<body>



<?php



$str_replace_db = new Str_replace_db();

// configure $str_replace_db->params() here if necessary.

$str_replace_db->search_replace(); // use search/replace arguments here if you'd like



$str_replace_db->get_report();

?>



</body>

</html>