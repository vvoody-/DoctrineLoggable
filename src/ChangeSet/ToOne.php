<?php

namespace Adt\DoctrineLoggable\ChangeSet;

class ToOne extends PropertyChangeSet
{

	/** @var Id|NULL old identification */
	protected $o;

	/** @var Id|NULL new identification */
	protected $n;

	/** @var ChangeSet|NULL change set of new entity or NULL if not logged or not changed */
	protected $ch;

	/**
	 * @param string $name
	 * @param Id|NULL $oldIdentification
	 * @param Id|NULL $newIdentification
	 */
	public function __construct($name, Id $oldIdentification = NULL, Id $newIdentification = NULL)
	{
		parent::__construct($name);
		$this->o = $oldIdentification;
		$this->n = $newIdentification;
	}

	/**
	 * @return bool
	 */
	public function isChanged()
	{
		return $this->o !== $this->n || $this->ch;
	}

	/**
	 * @param ChangeSet|NULL $entity
	 */
	public function setChangeSet(ChangeSet $entity = NULL)
	{
		if ($entity !== NULL && $entity->isChanged()) {
			$this->ch = $entity;
		} else {
			$this->ch = NULL;
		}
	}

	/**
	 * @param ToOne $toOne
	 */
	public function merge(PropertyChangeSet $toOne)
	{
		$this->n = $toOne->getNew();
		$this->ch = $toOne->getChangeSet();
	}

	/**
	 * @return Id|NULL
	 */
	public function getNew()
	{
		return $this->n;
	}

	/**
	 * @return ChangeSet|NULL
	 */
	public function getChangeSet()
	{
		return $this->ch;
	}

	public function getType()
	{
		return self::TYPE_TO_ONE;
	}

	function __sleep()
	{
		return ['o', 'n', 'ch'];
	}

	/**
	 * @return Id|NULL
	 */
	public function getOld()
	{
		return $this->o;
	}
}
