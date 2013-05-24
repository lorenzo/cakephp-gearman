<?php

App::uses('GearmanQueue', 'Gearman.Client');

/**
 * Tests GearmanQueue class
 *
 **/
class GearmanQueueTest extends CakeTestCase {

/**
 * Sets up a mocked logger stream
 *
 * @return void
 **/
	public function setUp() {
		parent::setUp();

		$class = $this->getMockClass('BaseLog');
		CakeLog::config('queuetest', array(
			'engine' => $class,
			'types' => array('error'),
			'scopes' => ['gearman']
		));
		$this->logger = CakeLog::stream('queuetest');
		Configure::write('Gearman', array());
	}

/**
 * Restores everything back to normal
 *
 * @return void
 **/
	public function tearDown() {
		parent::tearDown();

		GearmanQueue::client(false);
		CakeLog::enable('stderr');
		CakeLog::drop('queuetest');
		Configure::write('Gearman', array());
		unset($this->logger);
	}

/**
 * Tests that correct server lists string is passed to the client
 *
 * @return void
 **/
	public function testSetCorrectServersList() {
		$client = $this->getMock('GearmanClient', array('addServers'));
		$client->expects($this->once())->method('addServers')->with('127.0.0.1');
		GearmanQueue::client($client);
		$this->assertSame($client, GearmanQueue::client());

		$client = $this->getMock('GearmanClient', array('addServers'));
		$client->expects($this->once())->method('addServers')->with('foo,bar');
		Configure::write('Gearman.servers', array('foo', 'bar'));
		GearmanQueue::client($client);
		$this->assertSame($client, GearmanQueue::client());
	}

/**
 * Tests that it is possible to execute background jobs in normal priority
 *
 * @return void
 **/
	public function testExecuteNoPriority() {
		CakeLog::disable('stderr');
		Configure::write('Gearman.prefix', 'test');

		$client = $this->getMock('GearmanClient', array('doBackground'));
		$client = GearmanQueue::client($client);

		$client->expects($this->any())
			->method('returnCode')
			->will($this->returnValue(GEARMAN_SUCCESS));

		$client->expects($this->at(0))
			->method('doBackground')
			->with('test_foo', json_encode('data'))
			->will($this->returnValue(GEARMAN_SUCCESS));

		$data = array('bar' => 'baz');
		$client->expects($this->at(1))
			->method('doBackground')
			->with('test_bar', json_encode($data))
			->will($this->returnValue(GEARMAN_SUCCESS));

		GearmanQueue::execute('foo', 'data');
		GearmanQueue::execute('bar', $data);
	}

/**
 * Tests that calling execute with an incorrect priority raises an exception
 *
 * @expectedException InvalidArgumentException
 * @expectedExceptionMessage foo is not a valid priority, only accepting low and high
 * @return void
 **/
	public function testExecuteWrongPriority() {
		 GearmanQueue::execute('bar', 'baz', 'foo');
	}

/**
 * Tests that if server returns an status other than success it will return false
 *
 * @return void
 **/
	public function testExecuteBadReturn() {
		CakeLog::disable('stderr');

		$client = $this->getMock('GearmanClient', array('doBackground', 'returnCode'));
		GearmanQueue::client($client);

		$client->expects($this->any())
			->method('returnCode')
			->will($this->returnValue(-1));

		$client->expects($this->at(0))
			->method('doBackground')
			->with('foo', json_encode('data'))
			->will($this->returnValue(-1));

		$this->logger->expects($this->at(1))
			->method('write')
			->with('debug', 'Creating background job: foo ("data")');

		$this->logger->expects($this->at(2))
			->method('write')
			->with('debug', 'Could not create background job for task foo and data "data". Got -1');

		$this->assertFalse(GearmanQueue::execute('foo', 'data'));
	}

/**
 * Tests you can set priorities on the jobs to be run
 *
 * @return void
 **/
	public function testExecutePriorities() {
		CakeLog::disable('stderr');
		Configure::write('Gearman.prefix', 'test');

		$client = $this->getMock('GearmanClient', array('doLowBackground', 'doHighBackground'));
		 GearmanQueue::client($client);

		$client->expects($this->any())
			->method('returnCode')
			->will($this->returnValue(GEARMAN_SUCCESS));

		$client->expects($this->at(0))
			->method('doLowBackground')
			->with('test_foo', json_encode('data'))
			->will($this->returnValue(GEARMAN_SUCCESS));

		$client->expects($this->at(1))
			->method('doHighBackground')
			->with('test_foo', json_encode('data'))
			->will($this->returnValue(GEARMAN_SUCCESS));

		 GearmanQueue::execute('foo', 'data', 'low');
		 GearmanQueue::execute('foo', 'data', 'high');
	}
}

