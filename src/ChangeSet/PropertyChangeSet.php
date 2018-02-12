<?php

namespace Adt\DoctrineLoggable\ChangeSet;

abstract class PropertyChangeSet
{

	const TYPE_SCALAR = 'scalar';
	const TYPE_TO_ONE = 'toOne';
	const TYPE_TO_MANNY = 'toManny';

	/** @var string */
	protected $name;

	/**
	 * @param string $name
	 */
	public function __construct($name)
	{
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName($name)
	{
		$this->name = $name;
	}

	/**
	 * @return bool
	 */
	abstract public function isChanged();

	/**
	 * @return string
	 */
	abstract public function getType();

	/**
	 * @return array
	 */
	abstract public function __sleep();

	abstract public function merge(PropertyChangeSet $property);

}
