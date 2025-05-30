<?php
namespace thybag\PseudoModel\Test;

use Mockery;
use Orchestra\Testbench\TestCase;
use thybag\PseudoModel\Test\Models\TestModel;
use thybag\PseudoModel\Exceptions\PersistException;

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

    public function testSaveSetsCreated()
    {
        // Save fails, ensure not created
        $mock = Mockery::mock(TestModel::class)->shouldAllowMockingProtectedMethods()->makePartial();
        $mock->fill(['name' => 'test']);

        $mock->shouldReceive('persist')->andReturn(false);
        $mock->save();
        $this->assertFalse($mock->doesModelExist());

        // Save succeeds, it is saved
        $mock = Mockery::mock(TestModel::class)->shouldAllowMockingProtectedMethods()->makePartial();
        $mock->fill(['name' => 'test']);

        $mock->shouldReceive('persist')->andReturn(true);
        
        $mock->save();
        $this->assertTrue($mock->doesModelExist());
    }

    public function testPersistCreate()
    {
        $mock = Mockery::mock(TestModel::class)->shouldAllowMockingProtectedMethods()->makePartial();
        $mock->fill(['name' => 'test']);

        $mock->shouldReceive('persist')->with('create', Mockery::any())->once()->andReturn(false);
        
        $mock->save();
    }

    public function testPersistCreateDoNothingIfNotDirty()
    {
        $mock = Mockery::mock(TestModel::class)->shouldAllowMockingProtectedMethods()->makePartial();
        $mock->shouldReceive('persist')->with('create', Mockery::any())->never()->andReturn(false);
        $mock->save();
    }

    public function testPersistUpdate()
    {
        $instance = (TestModel::make())->newInstance(['name' => 'exists'], true);

        $mock = Mockery::mock(TestModel::class)->shouldAllowMockingProtectedMethods()->makePartial();
        $mock->fill(['name' => 'test']);
        $mock->setAsExists();

        $mock->shouldReceive('persist')->with('update', Mockery::any())->once()->andReturn(false);
        
        $mock->save();
    }

    public function testPersistDeleteDoNothingIfNotCreated()
    {
        $mock = Mockery::mock(TestModel::class)->shouldAllowMockingProtectedMethods()->makePartial();
        $mock->fill(['name' => 'test']);

        $mock->shouldReceive('persist')->with('delete')->never()->andReturn(false);
        
        $mock->delete();
    }

    public function testPersistDelete()
    {
        $mock = Mockery::mock(TestModel::class)->shouldAllowMockingProtectedMethods()->makePartial();
        $mock->fill(['name' => 'test']);
        $mock->setAsExists();

        $mock->shouldReceive('persist')->with('delete')->once()->andReturn(false);
        
        $mock->delete();
    }

    public function testSavePersistOrFail()
    {
        $this->expectException(PersistException::class);

        $mock = Mockery::mock(TestModel::class)->shouldAllowMockingProtectedMethods()->makePartial();
        $mock->shouldReceive('persist')->with('create', Mockery::any())->once()->andReturn(false);
        $mock->fill(['name' => 'test']);


        $mock->saveOrFail();
    }

    public function testUpdatePersistOrFail()
    {
        $this->expectException(PersistException::class);

        $mock = Mockery::mock(TestModel::class)->shouldAllowMockingProtectedMethods()->makePartial();
        $mock->shouldReceive('persist')->with('update', Mockery::any())->once()->andReturn(false);
        $mock->setAsExists();
        $mock->updateOrFail(['name' => 'test']);
    }
}
