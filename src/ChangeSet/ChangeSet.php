<?php

namespace Adt\DoctrineLoggable\ChangeSet;

class ChangeSet
{

	const ACTION_CREATE = 'create';
	const ACTION_EDIT = 'edit';
	const ACTION_DELETE = 'delete';

	/** @var string */
	protected $a = self::ACTION_EDIT;

	/** @var Id */
	protected $i;

	/** @var PropertyChangeSet[] list of changed properties */
	protected $p = [];

	/**
	 * @param PropertyChangeSet $property
	 */
	public function addPropertyChange(PropertyChangeSet $property)
	{
		if ($property->isChanged() || $this->a === self::ACTION_CREATE) {
			if (isset($this->p[$property->getName()])) {
				$oldNodeProperty = $this->p[$property->getName()];
				$oldNodeProperty->merge($property);
			} else {
				$this->p[$property->getName()] = $property;
			}
		}
	}

	/**
	 * @return bool
	 */
	public function isChanged()
	{
		return count($this->p) > 0;
	}

	/**
	 * @return Id
	 */
	public function getIdentification()
	{
		return $this->i;
	}

	/**
	 * @param Id $identification
	 */
	public function setIdentification($identification)
	{
		$this->i = $identification;
	}

	/**
	 * @return string
	 */
	public function getAction()
	{
		return $this->a;
	}

	/**
	 * @param string $action
	 */
	public function setAction($action)
	{
		$this->a = $action;
	}

	function __wakeup()
	{
		foreach ($this->p as $name => $propertyChangeSet) {
			$propertyChangeSet->setName($name);
		}
	}

}
