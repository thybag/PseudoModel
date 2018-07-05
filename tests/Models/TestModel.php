<?php
namespace thybag\PseudoModel\Test\Models;

use thybag\PseudoModel\PseudoModel;

class TestModel extends PseudoModel {

	protected $fillable = [
		'name',
		'type',
		'amount'
	];

	public function doesModelExist()
	{
		return $this->exists;
	}
}