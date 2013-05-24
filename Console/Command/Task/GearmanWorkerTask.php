<?php

App::uses('AppShell', 'Console/Command');
App::uses('CakeEvent', 'Event');
App::uses('CakeEventManager', 'Event');

/**
 * Utility functions to setup a worker server for Gearman
 *
 */
class GearmanWorkerTask extends AppShell {

/**
 * Internal reference to the GearmanWorker
 *
 * @var GearmanWorker
 */
	protected $_worker;

/**
 * Internal reference to the CakeEventManager
 *
 * @var CakeEventManager
 */
	protected $_eventManager;

/**
 * List of worker functions that will be internally sub-dispatched
 *
 * @var array
 */
	protected $_callbacks;

/**
 * A wrapper for the normal GearmanWorker::work() method, with some additional settings
 *
 * @param string $name the name of the task this worker implements
 * @return void
 */
	public function work($name = 'gearman') {
		$this->log(sprintf("Starting %s worker", $name), 'info', 'gearman');
		$worker = $this->getWorker();
		$worker->addOptions(GEARMAN_WORKER_NON_BLOCKING);
		$this->_setupEvents();

		while (true) {
			if (!$this->_triggerEvent('Gearman.beforeWork')) {
				break;
			}

			$worker->work();

			if (!$this->_triggerEvent('Gearman.afterWork')) {
				break;
			}

			// If we processed a job, don't bother to wait
			if ($worker->returnCode() == GEARMAN_SUCCESS) {
				continue;
    		}
			$this->_worker->wait();
		}
	}

/**
 * Get the GearmanWorker object
 *
 * @return GearmanWorker
 */
	public function getWorker() {
		if (empty($this->_worker)) {
			$this->_worker = new GearmanWorker;
			$servers = Configure::read('Gearman.servers') ?: array('127.0.0.1:4730');
			$this->_worker->addServers(implode(',', (array)$servers));
		}
		return $this->_worker;
	}

/**
 * Change the worker object, assumes it has been configured in advance
 *
 * @param GearmanWorker $worker
 * @return void
 */
	public function setWorker(GearmanWorker $worker) {
		$this->_worker = $worker;
	}

/**
 * Registers an object method to be called as a worker function for a specific task name
 *
 * @param string $name the name of the task to susbscribe for
 * @param object|callable $object the object that contains the worker method
 * @param string $method the name of the method that will be called with the job
 * @return void
 */
	public function addFunction($name, $object, $method = null) {
		$prefix = Configure::read('Gearman.prefix');
		if ($prefix) {
			$name = $prefix . '_' . $name;
		}

		if ($method) {
			$this->_callbacks[$name] = [$object, $method];
		} else {
			$this->_callbacks[$name] = $object;
		}

		$this->getWorker()->addFunction($name, array($this, '_work'));
	}

/**
 * Get the Event Manager
 *
 * If none exist it creates a new instance
 *
 * @return CakeEventManager
 */
	public function getEventManager() {
		if ($this->_eventManager === null) {
			$this->_eventManager = new CakeEventManager;
		}
		return $this->_eventManager;
	}

/**
 * Trigger a Gearman event
 *
 * @param string $name The event name
 * @param mixed $data The event data
 * @return boolean If the event was stopped or not
 */
	protected function _triggerEvent($name, $data = null) {
		$event = new CakeEvent($name, $this, $data);
		$this->getEventManager()->dispatch($event);
		return !$event->isStopped();
	}

/**
 * Setup some internal event listeners
 *
 * @return void
 */
	protected function _setupEvents() {
		$this->_checkForNoActiveConnectionsEvent();
	}

/**
 * Check if the worker have no active connections to a Gearman server
 *
 * @return boolean
 */
	protected function _checkForNoActiveConnectionsEvent() {
		$this->getEventManager()->attach(function($event) {
			if ($event->subject->getWorker()->returnCode() == GEARMAN_NO_ACTIVE_FDS) {
				$event->subject->log('Gearman server not found, reconnecting.', 'warning', 'gearman');
				sleep(1);
			}
		}, 'Gearman.afterWait');
	}

/**
 * The function that is used for all jobs, it will sub-dispatch to the real function
 * Useful for registering closures
 *
 * @return void
 */
	public function _work(GearmanJob $job) {
		$data = json_decode($job->workload(), true);
		call_user_func($this->_callbacks[$job->functionName()], $data, $job);
	}

}
