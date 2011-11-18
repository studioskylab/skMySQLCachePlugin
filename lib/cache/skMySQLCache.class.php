<?php

/**
 * Cache class that stores cached content in a MySQL database.
 *
 * @package    skMySQLCachePlugin
 * @author     Jaik Dean <jaik@studioskylab.com>
 */
class skMySQLCache extends sfCache
{

	/**
	 * Database connection instance
	 *
	 * @var PDO
	 **/
	protected $dbh = null;

	/**
	 * Prepared statements for each of the queries used
	 *
	 * @var array
	 **/
	protected $stmt = array();


	/**
	 * Initializes this sfCache instance.
	 *
	 * Available options:
	 *
	 * * database: Name of Doctrine connection from databases.yml
	 * * dsn: PDO-compatible DSN string
	 * * username: Username (for DSN string connection only)
	 * * password: Password (for DSN string connection only)
	 * * table: Name of cache table in the database. Defaults to "cache"
	 *
	 * * see sfCache for options available for all drivers
	 *
	 * @see sfCache
	 */
	public function initialize($options = array())
	{
		$options = array_merge(array(
			'table' => 'cache',
		), $options);

		parent::initialize($options);

		// ensure a database connection is specified
		if (!$this->getOption('database') && !$this->getOption('dsn')) {
			throw new sfInitializationException('You must pass a "database" or "dsn" option to initialize a skMySQLCache object.');
		}
	}


	/**
	 * @see sfCache
	 */
	public function getBackend()
	{
		return $this->getConnection();
	}


	/**
	 * @see sfCache
	 */
	public function get($key, $default = null)
	{
		// add the key prefix
		$key = $this->getOption('prefix') . $key;

		// prepare the statement
		if (!isset($this->stmt['get'])) {
			$this->stmt['get'] = $this->getConnection()->prepare('SELECT SQL_NO_CACHE `data` FROM `' . $this->getOption('table') . '` WHERE `key` = :key AND `timeout` > :timeout');
		}

		// execute the statement
		$this->stmt['get']->execute(array(
			':key'     => $key,
			':timeout' => time(),
		));

		$data = $this->stmt['get']->fetchColumn();
		$this->stmt['get']->closeCursor();

		return (false === $data ? $default : $data);
	}


	/**
	 * @see sfCache
	 */
	public function has($key)
	{
		// add the key prefix
		$key = $this->getOption('prefix') . $key;

		// prepare the statement
		if (!isset($this->stmt['has'])) {
			$this->stmt['has'] = $this->getConnection()->prepare('SELECT SQL_NO_CACHE COUNT(*) FROM `' . $this->getOption('table') . '` WHERE `key` = :key AND `timeout` > :timeout');
		}

		// execute the statement
		$this->stmt['has']->execute(array(
			':key'     => $key,
			':timeout' => time(),
		));

		$count = $this->stmt['has']->fetchColumn();
		$this->stmt['has']->closeCursor();

		return (boolean) $count;
	}


	/**
	 * @see sfCache
	 */
	public function set($key, $data, $lifetime = null)
	{
		// add the key prefix
		$key = $this->getOption('prefix') . $key;

		if ($this->getOption('automatic_cleaning_factor') > 0 && mt_rand(1, $this->getOption('automatic_cleaning_factor')) == 1) {
			$this->clean(sfCache::OLD);
		}

		// prepare the statement
		if (!isset($this->stmt['set'])) {
			$this->stmt['set'] = $this->getConnection()->prepare('REPLACE INTO `' . $this->getOption('table') . '` (`key`, `data`, `timeout`, `last_modified`) VALUES (:key, :data, :timeout, :last_modified)');
		}

		// execute the statement
		$now = time();
		$return = $this->stmt['set']->execute(array(
			':key'           => $key,
			':data'          => $data,
			':timeout'       => $now + $this->getLifetime($lifetime),
			':last_modified' => $now,
		));

		$this->stmt['set']->closeCursor();

		return $return;
	}


	/**
	 * @see sfCache
	 */
	public function remove($key)
	{
		// add the key prefix
		$key = $this->getOption('prefix') . $key;

		// prepare the statement
		if (!isset($this->stmt['remove'])) {
			$this->stmt['remove'] = $this->getConnection()->prepare('DELETE FROM `' . $this->getOption('table') . '` WHERE `key` = :key');
		}

		// execute the statement
		$return = $this->stmt['remove']->execute(array(':key' => $key));
		$this->stmt['remove']->closeCursor();

		return $return;
	}


	/**
	 * @see sfCache
	 */
	public function removePattern($pattern)
	{
		// add the pattern prefix
		$pattern = $this->getOption('prefix') . $pattern;

		// prepare the statement
		if (!isset($this->stmt['removePattern'])) {
			/* Here we use a LIKE comparison to achieve a rough match. MySQL
			   will then only have to evaluate the regex against these rough
			   matches, rather than every record in the table. This can save
			   significant query execution time */
			$this->stmt['removePattern'] = $this->getConnection()->prepare('DELETE FROM `' . $this->getOption('table') . '` WHERE `key` LIKE :like AND `key` REGEXP :regexp');
		}

		// execute the statement
		$return = $this->stmt['removePattern']->execute(array(
			':like'   => $this->patternToLike($pattern),
			':regexp' => $this->patternToRegexp($pattern),
		));
		$this->stmt['removePattern']->closeCursor();

		return $return;
	}


	/**
	 * @see sfCache
	 */
	public function clean($mode = sfCache::ALL)
	{
		// prepare the statement
		if (sfCache::ALL == $mode) {
			if (!isset($this->stmt['cleanall'])) {
				$this->stmt['cleanall'] = $this->getConnection()->prepare('DELETE FROM `' . $this->getOption('table') . '`');
			}

			$stmt   = $this->stmt['cleanall'];
			$params = array();
		} else {
			if (!isset($this->stmt['clean'])) {
				$this->stmt['clean'] = $this->getConnection()->prepare('DELETE FROM `' . $this->getOption('table') . '` WHERE `timeout` < :timeout');
			}

			$stmt   = $this->stmt['clean'];
			$params = array(':timeout' => time());
		}

		// execute the statement
		$return = $stmt->execute($params);
		$stmt->closeCursor();

		return $return;
	}


	/**
	 * @see sfCache
	 */
	public function getTimeout($key)
	{
		// add the key prefix
		$key = $this->getOption('prefix') . $key;

		// prepare the statement
		if (!isset($this->stmt['getTimeout'])) {
			$this->stmt['getTimeout'] = $this->getConnection()->prepare('SELECT SQL_NO_CACHE `timeout` FROM `' . $this->getOption('table') . '` WHERE `key` = :key AND `timeout` > :timeout');
		}

		// execute the statement
		$this->stmt['getTimeout']->execute(array(
			':key'     => $key,
			':timeout' => time(),
		));

		$timeout = $this->stmt['getTimeout']->fetchColumn();
		$this->stmt['getTimeout']->closeCursor();

		return (false === $timeout ? 0 : $timeout);
	}


	/**
	 * @see sfCache
	 */
	public function getLastModified($key)
	{
		// add the key prefix
		$key = $this->getOption('prefix') . $key;

		// prepare the statement
		if (!isset($this->stmt['getLastModified'])) {
			$this->stmt['getLastModified'] = $this->getConnection()->prepare('SELECT SQL_NO_CACHE `last_modified` FROM `' . $this->getOption('table') . '` WHERE `key` = :key AND `timeout` > :timeout');
		}

		// execute the statement
		$this->stmt['getLastModified']->execute(array(
			':key'     => $key,
			':timeout' => time(),
		));

		$modified = $this->stmt['getLastModified']->fetchColumn();
		$this->stmt['getLastModified']->closeCursor();

		return (null === $modified ? 0 : $modified);
	}


	/**
	 * @see sfCache
	 */
	public function getMany($keys)
	{
		// add the key prefix
		foreach ($keys as $i => $key) {
			$keys[$i] = $this->getOption('prefix') . $key;
		}

		// prepare the statement
		$stmt     = $this->getConnection()->prepare('SELECT SQL_NO_CACHE `key`, `data` FROM `' . $this->getOption('table') . '` WHERE `key` IN (?' . str_repeat(',?', count($keys) - 1) . ') AND `timeout` > ?');
		$params   = $keys;
		$params[] = time();

		// execute the statement
		$stmt->execute($params);

		// recurse through the results and format for return
		$data = array();
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$data[$row['key']] = $row['data'];
		}

		return $data;
	}


	/**
	 * Get the database connection
	 *
	 * @return PDO
	 * @todo Add error checking
	 */
	protected function getConnection()
	{
		// establish the connection
		if ($this->dbh === null) {
			// use the named Doctrine database connection if specified
			if ($this->getOption('database')) {
				$this->dbh = Doctrine_Manager::getInstance()->getConnection($this->getOption('database'))->getDbh();
			} else {
				$this->dbh = new PDO($this->getOption('dsn'), $this->getOption('username'), $this->getOption('password'));
			}
		}

		return $this->dbh;
	}


	/**
	 * @see sfCache
	 */
	protected function patternToRegexp($pattern)
	{
		$regexp = str_replace(
			array('\\*\\*', '\\*'),
			array('.+?',    '[^' . preg_quote(sfCache::SEPARATOR) . ']+'),
			preg_quote($pattern)
		);

		return '^' . $regexp . '$';
	}


	/**
	 * Convert the given cache key pattern to a MySQL basic string comparison
	 * pattern.
	 *
	 * This will NOT be specific-enough to accurately match patterns, it will
	 * find some false-positives.
	 *
	 * @param string $pattern
	 * @return string
	 */
	protected function patternToLike($pattern)
	{
		return str_replace(array('**', '*'), '%', $pattern);
	}

}