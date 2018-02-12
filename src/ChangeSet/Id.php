<?php

namespace Adt\DoctrineLoggable\ChangeSet;

class Id
{

	/** @var int PK value */
	protected $id;

	/** @var string class of entity */
	protected $c;

	/** @var [] identification data, for example name, surname and email of user in User entity */
	protected $d;

	/**
	 * @param $id
	 * @param $class
	 * @param $identificationData
	 */
	public function __construct($id, $class, $identificationData)
	{
		$this->id = $id;
		$this->c = $class;
		$this->d = $identificationData;
	}

	/**
	 * @return string
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @param string $id
	 */
	public function setId($id)
	{
		$this->id = $id;
	}

	public function getClass()
	{
		return $this->c;
	}

	public function getIdentification()
	{
		return $this->d;
	}

}
