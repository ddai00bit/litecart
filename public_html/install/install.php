<?php
  require('includes/header.inc.php');
  
  echo '<h1>Installer</h1>' . PHP_EOL;
  
  error_reporting(version_compare(PHP_VERSION, '5.4.0', '>=') ? E_ALL & ~E_STRICT : E_ALL);
  ini_set('ignore_repeated_errors', 'On');
  ini_set('log_errors', 'Off');
  ini_set('display_errors', 'On');
  ini_set('html_errors', 'On');

  if (empty($_POST['install'])) {
    header('Location: index.php');
    exit;
  }
  
// Function to copy recursive data
  function xcopy($source, $target) {
    if (is_dir($source)) {
      $source = rtrim($source, '/') . '/';
      $target = rtrim($target, '/') . '/';
      if (!file_exists($target)) mkdir($target);
      $dir = opendir($source);
      while(($file = readdir($dir)) !== false) {
        if ($file == '.' || $file == '..') continue;
        xcopy($source.$file, $target.$file);
      }
    } else if (!file_exists($target)) {
      copy($source, $target);
    }
  }
  
// Function to get object from a relative path to this script
  function get_absolute_path($path=null) {
    if (empty($path)) $path = dirname(__FILE__);
    $path = realpath($path);
    $path = str_replace('\\', '/', $path);
    $parts = array_filter(explode('/', $path), 'strlen');
    $absolutes = array();
    foreach ($parts as $part) {
      if ('.' == $part) continue;
      if ('..' == $part) {
        array_pop($absolutes);
      } else {
        $absolutes[] = $part;
      }
    }
    return ((strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') ? '' : '/') . implode('/', $absolutes);
  }
  
  ### Set Environment Variables ###################################
  
  $installation_path = get_absolute_path(dirname(__FILE__) .'/..') .'/';
  
  $_POST['admin_folder'] = str_replace('\\', '/', $_POST['admin_folder']);
  $_POST['admin_folder'] = rtrim($_POST['admin_folder'], '/') . '/';
  
  ### PHP > Check Version #############################

  echo '<p>Checking PHP version... ';
  
  if (version_compare(PHP_VERSION, '5.3', '<')) {
    die('<span class="error">[Error] PHP 5.3+ required - Detected '. PHP_VERSION .'</span></p>');
  } else if (version_compare(PHP_VERSION, '5.4', '<')) {
    echo PHP_VERSION .' <span class="ok">[OK]</span><br />'
       . '<span class="warning">PHP 5.4+ recommended</span></span></p>';
  } else {
    echo PHP_VERSION .' <span class="ok">[OK]</span></p>' . PHP_EOL;
  }
  
  ### Database > Connection ###################################

  echo '<p>Connecting to database... ';
  
  define('DB_SERVER', $_POST['db_server']);
  define('DB_USERNAME', $_POST['db_username']);
  define('DB_PASSWORD', $_POST['db_password']);
  define('DB_DATABASE', $_POST['db_database']);
  define('DB_TABLE_PREFIX', $_POST['db_table_prefix']);
  define('DB_DATABASE_CHARSET', 'utf8');
  define('DB_PERSISTENT_CONNECTIONS', 'false');
  
  require('database.class.php');
  $database = new database(null);
  
  echo '<span class="ok">[OK]</span></p>' . PHP_EOL;
  
  ### Database > Check Version #############################

  echo '<p>Checking MySQL version... ';
  
  $version_query = $database->query("SELECT VERSION();");
  $version = $database->fetch($version_query);

  
  if (version_compare($version['VERSION()'], '5.5', '<')) {
    die($version['VERSION()'] . ' <span class="error">[Error] MySQL 5.5+ required</span></p>');
  } else {
    echo $version['VERSION()'] . ' <span class="ok">[OK]</span></p>' . PHP_EOL;
  }
  
  ### Config > Write ###################################
  
  echo '<p>Writing config file... ';
  
  $config = file_get_contents('config');
  
  $map = array(
    '{ADMIN_FOLDER}' => rtrim($_POST['admin_folder'], '/'),
    '{DB_SERVER}' => $_POST['db_server'],
    '{DB_USERNAME}' => $_POST['db_username'],
    '{DB_PASSWORD}' => $_POST['db_password'],
    '{DB_DATABASE}' => $_POST['db_database'],
    '{DB_TABLE_PREFIX}' => $_POST['db_table_prefix'],
    '{DB_DATABASE_CHARSET}' => 'utf8',
    '{DB_PERSISTENT_CONNECTIONS}' => 'false',
    '{CLIENT_IP}' => $_POST['client_ip'],
    '{PASSWORD_SALT}' => substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", 10)), 0, 128),
  );
  
  foreach ($map as $search => $replace) {
    $config = str_replace($search, $replace, $config);
  }
  
  define('PASSWORD_SALT', $map['{PASSWORD_SALT}']); // we need it for later
  
  if (file_put_contents('../includes/config.inc.php', $config)) {
    echo '<span class="ok">[OK]</span></p>' . PHP_EOL;
  } else {
    die('<span class="error">[Done]</span></p>' . PHP_EOL);
  }
  
  ### Database > Cleaning ###################################
  
  echo '<p>Cleaning database... ';
  
  $sql = file_get_contents('clean.sql');
  $sql = str_replace('`lc_', '`'.DB_TABLE_PREFIX, $sql);
  
  $sql = explode('-- --------------------------------------------------------', $sql);
  
  foreach ($sql as $query) {
    $query = preg_replace('/--.*\s/', '', $query);
    $database->query($query);
  }
  
  echo '<span class="ok">[Done]</span></p>' . PHP_EOL;
  
  ### Database > Tables > Structure ###################################
  
  echo '<p>Writing database tables... ';
  
  $sql = file_get_contents('structure.sql');
  $sql = str_replace('`lc_', '`'.DB_TABLE_PREFIX, $sql);
  
  $sql = explode('-- --------------------------------------------------------', $sql);
  
  foreach ($sql as $query) {
    $query = preg_replace('/--.*\s/', '', $query);
    $database->query($query);
  }
  
  echo '<span class="ok">[Done]</span></p>' . PHP_EOL;
  
  ### Database > Tables > Data ###################################
  
  echo '<p>Writing database table data... ';
  
  $sql = file_get_contents('data.sql');
  $sql = str_replace('`lc_', '`'.DB_TABLE_PREFIX, $sql);
  
  $map = array(
    '{STORE_NAME}' => $_POST['store_name'],
    '{STORE_EMAIL}' => $_POST['store_email'],
    '{STORE_TIME_ZONE}' => $_POST['store_time_zone'],
    '{STORE_COUNTRY_CODE}' => $_POST['country_code'],
  );
  
  foreach ($map as $search => $replace) {
    $sql = str_replace($search, $replace, $sql);
  }
  
  $sql = explode('-- --------------------------------------------------------', $sql);
  
  foreach ($sql as $query) {
    $query = preg_replace('/--.*\s/', '', $query);
    $database->query($query);
  }
  
  echo '<span class="ok">[Done]</span></p>' . PHP_EOL;
  
  ### Database > Tables > Demo Data ###################################
  
  if (!empty($_POST['demo_data'])) {
    echo '<p>Writing demo data... ';
    
    $sql = file_get_contents('data/demo/data.sql');
    
    if (!empty($sql)) {
      $sql = str_replace('`lc_', '`'.DB_TABLE_PREFIX, $sql);
       
      $sql = explode('-- --------------------------------------------------------', $sql);
      
      foreach ($sql as $query) {
        $query = preg_replace('/--.*\s/', '', $query);
        $database->query($query);
      }
    }
    
    echo '<span class="ok">[Done]</span></p>' . PHP_EOL;
  }
  
  ### Files > Demo Data ###################################
  
  if (!empty($_POST['demo_data'])) {
    echo '<p>Copying demo files...';
    xcopy('data/demo/public_html/', $installation_path);
    echo ' <span class="ok">[Done]</span></p>' . PHP_EOL;
  }
  
  ### .htaccess mod rewrite ###################################
  
  echo '<p>Setting mod_rewrite base path...';
  
  $htaccess = file_get_contents('htaccess');
  
  $base_dir = str_replace(get_absolute_path($_SERVER['DOCUMENT_ROOT']), '', $installation_path);
  
  $htaccess = str_replace('{BASE_DIR}', $base_dir, $htaccess);
  
  file_put_contents('../.htaccess', $htaccess);
  
  echo ' <span class="ok">[Done]</span></p>' . PHP_EOL;
  
  ### Admin > Folder ###################################
  
  if (!empty($_POST['admin_folder']) && $_POST['admin_folder'] != 'admin/') {
    echo '<p>Renaming admin folder...';
    if (is_dir('../admin/')) {
      rename('../admin/', '../'.$_POST['admin_folder']);
      echo ' <span class="ok">[Done]</span></p>' . PHP_EOL;
    } else {
      echo ' <span class="error">[Skipped]</span></p>' . PHP_EOL;
    }
  }
  
  ### Admin > .htaccess Protection ###################################
  
  echo '<p>Securing admin folder...';
    
  $htaccess = '# Denied content' . PHP_EOL
            . '<FilesMatch "\.(htaccess|htpasswd|inc.php)$">' . PHP_EOL
            . '  Order Allow,Deny' . PHP_EOL
            . '  Deny from all' . PHP_EOL
            . '</FilesMatch>' . PHP_EOL
            . PHP_EOL
            . '# Basic authentication' . PHP_EOL
            . '<IfModule mod_auth_basic.c>' . PHP_EOL
            . '  AuthType Basic' . PHP_EOL
            . '  AuthName "Restricted Area"' . PHP_EOL
            . '  AuthUserFile ' . $installation_path . $_POST['admin_folder'] . '.htpasswd' . PHP_EOL
            . '  Require valid-user' . PHP_EOL
            . '</IfModule>' . PHP_EOL;
  
  if (is_dir('../'.$_POST['admin_folder'])) {
    file_put_contents('../'. $_POST['admin_folder'] .'.htaccess', $htaccess);
    echo ' <span class="ok">[Done]</span></p>' . PHP_EOL;
  } else {
    echo ' <span class="error">[Skipped]</span></p>' . PHP_EOL;
  }
  
  ### Admin > .htpasswd Users ###################################
  
  echo '<p>Granting admin access for user '. $_POST['username'] .'...';
  
  $htpasswd = $_POST['username'] .':{SHA}'. base64_encode(sha1($_POST['password'], true)) . PHP_EOL;
  file_put_contents('../'. $_POST['admin_folder'] . '.htpasswd', $htpasswd);
  
  echo ' <span class="ok">[Done]</span></p>' . PHP_EOL;
  
  ### Admin > Database > Users ###################################
  
  require('../includes/functions/func_password.inc.php');
  
  $database->query(
    "insert into ". str_replace('`lc_', '`'.DB_TABLE_PREFIX, '`lc_users`') ."
    (`id`, `status`, `username`, `password`, `date_updated`, `date_created`)
    values ('1', '1', '". $database->input($_POST['username']) ."', '". password_checksum('1', $_POST['password']) ."', '". date('Y-m-d H:i:s') ."', '". date('Y-m-d H:i:s') ."');"
  );
  
  ## Windows OS Adjustments ###################################
  
  if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
    echo '<p>Making adjustments for Windows platform...';
    $database->query(
      "update ". str_replace('`lc_', '`'.DB_TABLE_PREFIX, '`lc_languages`') ."
      set locale = 'english',
      charset = 'Windows-1252'
      where code = 'en'
      limit 1;"
    );
    echo ' <span class="ok">[Done]</span></p>' . PHP_EOL;
  } else if (strtoupper(substr(PHP_OS, 0, 6)) == 'DARWIN') {
    echo '<p>Making adjustments for Darwin (Mac) platform...';
    $database->query(
      "update ". str_replace('`lc_', '`'.DB_TABLE_PREFIX, '`lc_languages`') ."
      set locale = 'en_US.UTF-8',
      where code = 'en'
      limit 1;"
    );
    echo ' <span class="ok">[Done]</span></p>' . PHP_EOL;
  }
  
  ### Regional Data Patch ###################################
  
  if (!empty($_POST['country_code'])) {
    
    echo '<p>Patching installation with regional data...';
    
    $directories = glob('data/*'. $_POST['country_code'] .'*/');
    
    if (empty($directories)) {
      $directories = glob('data/*XX*/');
    }
    
    if (!empty($directories)) {
      foreach ($directories as $dir) {

        $dir = basename($dir);
        if ($dir == 'demo') continue;
        
        if (file_exists('data/'. $dir .'/data.sql')) {
          $sql = file_get_contents('data/'. $dir .'/data.sql');
          
          if (!empty($sql)) {
            $sql = str_replace('`lc_', '`'.DB_TABLE_PREFIX, $sql);
             
            $sql = explode('-- --------------------------------------------------------', $sql);
            
            foreach ($sql as $query) {
              $query = preg_replace('/--.*\s/', '', $query);
              $database->query($query);
            }
          }
        }
      }
      
      if (file_exists('data/'. $dir .'/public_html/')) {
        xcopy('data/'. $dir .'/public_html/', $installation_path);
      }
    }
    
    echo ' <span class="ok">[Done]</span></p>' . PHP_EOL;
  }
  
  ### ###################################
  
  echo PHP_EOL . '<h2>Complete</h2>' . PHP_EOL
     . '<p style="font-weight: bold;">Installation complete! Please delete the <strong>~/install/</strong> folder.</p>' . PHP_EOL
     . '<p style="font-weight: bold;">You may now log in to the <a href="../'. $_POST['admin_folder'] .'">administration area</a> and start configuring your store.</p>' . PHP_EOL;
  
  require('includes/footer.inc.php');
?>