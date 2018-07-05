<?php
namespace thybag\PseudoModel\Test;

use Orchestra\Testbench\TestCase;
use thybag\PseudoModel\Test\Models\TestModel;

class PseudoModelTest extends TestCase
{
	public function testMake()
	{
		$test = TestModel::make(['name' => 'Test', 'type' => 'testing']);

		$this->assertEquals('Test', $test->name);
		$this->assertEquals('testing', $test->type);
		$this->assertEquals('testing', $test->toArray()['type']);

		$this->assertFalse($test->doesModelExist());
		$this->assertTrue($test->isDirty());
	}

	public function testCreate()
	{
		$test = TestModel::create(['name' => 'Test', 'type' => 'testing']);
		
		$this->assertEquals('Test', $test->name);
		$this->assertEquals('testing', $test->type);
		$this->assertEquals('testing', $test->toArray()['type']);

		$this->assertTrue($test->doesModelExist());
		$this->assertFalse($test->isDirty());
	}

	public function testNew()
	{
		$test = new TestModel(['name' => 'Test', 'type' => 'testing']);

		$this->assertEquals('Test', $test->name);
		$this->assertEquals('testing', $test->type);
		$this->assertEquals('testing', $test->toArray()['type']);

		$this->assertFalse($test->doesModelExist());
		$this->assertTrue($test->isDirty());
	}

	public function testMakeOnlyFillableFilled()
	{
		$test = TestModel::make(['name' => 'Test', 'type' => 'testing', 'house' => 'wat']);
		
		$this->assertEquals('Test', $test->name);
		$this->assertEquals('testing', $test->type);

		$this->assertNull($test->house);
	}
}


