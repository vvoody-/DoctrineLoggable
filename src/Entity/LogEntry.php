<?php

namespace Adt\DoctrineLoggable\Entity;

use Adt\DoctrineLoggable\ChangeSet\ChangeSet;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(
 * 	indexes={@ORM\Index(name="log_entity_lookup_idx", columns={"object_id", "object_class"})}
 * )
 */
class LogEntry
{
	
	/**
	 * @ORM\Id
	 * @ORM\Column(type="integer")
	 * @ORM\GeneratedValue
	 * @var integer
	 */
	protected $id;

	const ACTION_CREATE = 'create';
	const ACTION_UPDATE = 'update';
	const ACTION_REMOVE = 'remove';

	/**
	 * @var string $action
	 *
	 * @ORM\Column(type="string", length=8)
	 */
	protected $action;

	/**
	 * @var string $loggedAt
	 *
	 * @ORM\Column(type="datetime")
	 */
	protected $loggedAt;

	/**
	 * @var string $objectId
	 *
	 * @ORM\Column(type="integer", nullable=true)
	 */
	protected $objectId;

	/**
	 * @var string $objectClass
	 *
	 * @ORM\Column(type="string")
	 */
	protected $objectClass;

	/**
	 * @ORM\Column(type="object")
	 */
	protected $changeSet;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
	protected $userId;

	public function setLoggedNow()
	{
		$this->loggedAt = new \DateTime();
	}

	/**
	 * @return int
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getAction()
	{
		return $this->action;
	}

	/**
	 * @param string $action
	 */
	public function setAction($action)
	{
		$this->action = $action;
	}

	/**
	 * @return string
	 */
	public function getLoggedAt()
	{
		return $this->loggedAt;
	}

	/**
	 * @return string
	 */
	public function getObjectId()
	{
		return $this->objectId;
	}

	/**
	 * @param string $objectId
	 */
	public function setObjectId($objectId)
	{
		$this->objectId = $objectId;
	}

	/**
	 * @return string
	 */
	public function getObjectClass()
	{
		return $this->objectClass;
	}

	/**
	 * @param string $objectClass
	 */
	public function setObjectClass($objectClass)
	{
		$this->objectClass = $objectClass;
	}

	/**
	 * @return ChangeSet
	 */
	public function getChangeSet()
	{
		return $this->changeSet;
	}

	/**
	 * @param ChangeSet $changeSet
	 */
	public function setChangeSet(ChangeSet $changeSet)
	{
		$this->changeSet = $changeSet;
	}

	/**
	 * @return mixed
	 */
	public function getUserId()
	{
		return $this->userId;
	}

	/**
	 * @param mixed $userId
	 */
	public function setUserId($userId)
	{
		$this->userId = $userId;
	}

}
