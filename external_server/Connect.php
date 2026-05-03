<?php

namespace external_server;

// Provide a simple PDO wrapper that can be instantiated as \external_server\Connect

// config file location
define('CONFIG_PATH', __DIR__ . '/../config/config.ini');


// If a Connect class already exists (maybe provided by another autoloader), don't redeclare.
if (!class_exists(__NAMESPACE__ . '\\Connect', false)) {
	class Connect extends \PDO {
		public function __construct() {
			// Use a local config path. If a global CONFIG_PATH constant was defined
			// by a bootstrap, use it; otherwise fall back to the project's config.ini.
			$configPath = defined('CONFIG_PATH') ? CONFIG_PATH : __DIR__ . '/../config/config.ini';
			$config = @parse_ini_file($configPath) ?: [];
			$host = $config['DB_HOST'] ?? 'localhost';
			$dbname = $config['DB_NAME'] ?? 'universal_store';
			$user = $config['DB_USER'] ?? 'root';
			$pass = $config['DB_PASS'] ?? '';

			$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
			parent::__construct($dsn, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => false]);
		}
	}
}

?>
