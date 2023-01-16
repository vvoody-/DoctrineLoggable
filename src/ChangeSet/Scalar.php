<?php

namespace Adt\DoctrineLoggable\ChangeSet;

use Adt\DoctrineLoggable\UnexpectedValueException;

class Scalar extends PropertyChangeSet
{

	/** @var string|int|float|bool|NULL old value */
	protected $o;

	/** @var string|int|float|bool|NULL new value */
	protected $n;

	/**
	 * true - property is marked as changed regardles of old and new values
	 *
	 * @var bool
	 */
	protected $f = false;

	public function __construct($name, $old, $new, $forceChanged = false)
	{
		parent::__construct($name);
		$this->o = $old;
		$this->n = $new;
		$this->f = $forceChanged;
	}


	public function isValueLogged(): bool
	{
		return !$this->f;
	}

	/**
	 * @return bool
	 */
	public function isChanged()
	{
		return $this->f || $this->o != $this->n;
	}

	/**
	 * @return bool|float|int|NULL|string
	 */
	public function getNew()
	{
		return $this->n;
	}

	/**
	 * @return bool|float|int|NULL|string
	 */
	public function getOld()
	{
		return $this->o;
	}

	/**
	 * @param Scalar $scalar
	 */
	public function merge(PropertyChangeSet $scalar)
	{
		if (!$scalar instanceof Scalar) {
			$class = get_class($scalar);
		    throw new UnexpectedValueException("You can not merge scalar change set with object of type '{$class}'");
		}
		$this->n = $scalar->getNew();
	}

	public function getType()
	{
		return self::TYPE_SCALAR;
	}

	function __sleep()
	{
		return ['o', 'n', 'f'];
	}
}
