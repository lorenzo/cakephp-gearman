<?php
App::uses('CakeLog', 'Log');
App::uses('Hash', 'Utility');
App::uses('Configure', 'Core');

/**
 * Manages the connection to one or multiple Gearman queueing servers and exposes
 * methods to execute jobs in the background.
 *
 **/
class GearmanQueue {

/**
 * Holds and instance for a server client
 *
 * @var GearmanClient
 **/
	protected static $_client = null;

/**
 * Returns the client instance to the background jobs server
 * If first param is an instance of GearmanClient it will configure the queue to use it
 * IF first param is false it will unset configured client to defaults
 *
 * @param GearmanClient|boolean $client null to return current instance, GearmanClient instance to configure it
 * to passed value, false to reset to defaults
 * @return GearmanClient
 **/
	public static function client($client = null) {
		if ($client instanceof GearmanClient) {
			static::$_client = $client;
			static::_setServers();
		}

		if ($client === false) {
			return static::$_client = null;
		}

		if (empty(static::$_client)) {
			static::$_client = new GearmanClient();
			static::_setServers();
		}

		return static::$_client;
	}

/**
 * Configures internal client reference to use the list of specified servers.
 * This list is specified using the configure class.
 *
 * @return void
 **/
	protected static function _setServers() {
		$servers = Configure::read('Gearman.servers') ?: array('127.0.0.1:4730');
		static::$_client->addServers(implode(',', $servers));
	}

/**
 * Starts a new background task by passing some data to it with a priority
 *
 * @param string $taskName name of the task to be executed
 * @param mixed $data info to be passed to background task
 * @param sting $priority null for normal or either "low" or "high"
 * @return boolean success
 **/
	public static function execute($taskName, $data = null, $priority = null) {
		if (!empty($priority)) {
			$priority = strtolower($priority);
			if (!in_array($priority, array('low', 'high'))) {
				throw new InvalidArgumentException(sprintf('%s is not a valid priority, only accepting low and high', $priority));
			}
		}

		$prefix = Configure::read('Gearman.prefix');
		if ($prefix) {
			$taskName = $prefix . '_' . $taskName;
		}

		$data = json_encode($data);
		CakeLog::debug(sprintf('Creating background job: %s', $taskName), array('gearman'));

		if ($priority == 'low') {
			$job =  static::client()->doLowBackground($taskName,  $data);
		}

		if ($priority == 'high') {
			$job =  static::client()->doHighBackground($taskName,  $data);
		}

		if (empty($priority)) {
			$job =  static::client()->doBackground($taskName,  $data);
		}
		
		if (static::client()->returnCode() !== GEARMAN_SUCCESS) {
			CakeLog::error(
				sprintf('Could not create background job for task %s and data %s. Got %s (%s)',
					$taskName,
					$data,
					$job,
					static::client()->error()
				),
				array('gearman')
			);
			return false;
		}

		return true;
	}

}
