<?php

namespace Adt\DoctrineLoggable\Service;

use Adt\DoctrineLoggable\ChangeSet AS CS;
use Adt\DoctrineLoggable\Annotations AS DLA;
use Adt\DoctrineLoggable\Entity\LogEntry;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\EventManager;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;

class ChangeSetFactory
{

	/** @var EntityManager */
	protected $em;

	/** @var UnitOfWork */
	protected $uow;

	/** @var Reader */
	private $reader;

	/** @var string */
	protected $logEntryClass = LogEntry::class;

	/** @var [] */
	protected $loggableEntityClasses = [];

	/** @var [] */
	protected $loggableEntityProperties = [];

	/**
	 * List of all log entries
	 *
	 * @var LogEntry[]
	 */
	protected $logEntries = [];

	/** @var LogEntry */
	protected $currentLogEntry;

	/** @var [] */
	protected $scheduledEntities = [];

	/** @var CS\ChangeSet[] */
	protected $computedEntityChangeSets = [];

	/** @var CS\Id[] */
	protected $identifications = [];

	/** @var UserIdProvider */
	private $userIdProvider;

	/** @var boolean */
	protected $afterShutdown = FALSE;

	public function __construct(Reader $reader, UserIdProvider $userIdProvider)
	{
		$this->reader = $reader;
		$this->userIdProvider = $userIdProvider;
	}

	public function isEntityLogged($entityClass)
	{
		if (!array_key_exists($entityClass, $this->loggableEntityClasses)) {
			$reflection = new \ReflectionClass($entityClass);
			$an = $this->reader->getClassAnnotation($reflection, DLA\LoggableEntity::class);
			$this->loggableEntityClasses[$entityClass] = (bool) $an;
		}
		return $this->loggableEntityClasses[$entityClass];
	}

	public function processLoggedEntity($entity)
	{
		$logEntry = $this->getLogEntry($entity);
		$changeSet = $this->getChangeSet($entity);

		if (!$changeSet->isChanged()) {
			return;
		}
		$this->logEntries[spl_object_hash($entity)] = $logEntry;
		$logEntry->setChangeset($changeSet);
	}

	public function updateIdentification($entity)
	{
		$oid = spl_object_hash($entity);
		if (array_key_exists($oid, $this->identifications)) {

			$metadata = $this->em->getClassMetadata(ClassUtils::getClass($entity));
			$id = $metadata->getIdentifierValues($entity);

			/** @var CS\Id $identification */
			$identification = $this->identifications[$oid];
			$identification->setId(implode('-', $id));
		}
	}

	/**
	 * @param $entity
	 * @return CS\ChangeSet
	 */
	protected function getChangeSet($entity = NULL)
	{
		if ($entity === NULL) {
			return NULL;
		}

		$class = ClassUtils::getClass($entity);
		$metadata = $this->em->getClassMetadata($class);

		$sploh = spl_object_hash($entity);
		if (isset($this->computedEntityChangeSets[$sploh])) {
			$changeSet = $this->computedEntityChangeSets[$sploh];
		} else {
			$changeSet = new CS\ChangeSet();
			$changeSet->setIdentification($this->createIdentification($entity));
			$this->computedEntityChangeSets[$sploh] = $changeSet;

			$insertions = $this->uow->getScheduledEntityInsertions();
			if (isset($insertions[$sploh])) {
				$changeSet->setAction(CS\ChangeSet::ACTION_CREATE);
			} else {
				$deletions = $this->uow->getScheduledEntityDeletions();
				if (isset($deletions[$sploh])) {
					$changeSet->setAction(CS\ChangeSet::ACTION_DELETE);
				}
			}
		}

		$uowEntiyChangeSet = $this->uow->getEntityChangeSet($entity);

		foreach ($this->getLoggedProperties(get_class($entity)) as $property) {

			// property is scalar
			$columnAnnotation = $this->reader->getPropertyAnnotation($property, Column::class);
			if ($columnAnnotation) {
				if (isset($uowEntiyChangeSet[$property->getName()])) {
					$type = Type::getType($metadata->getTypeOfField($property->getName()));
					$propertyChangeSet = $uowEntiyChangeSet[$property->getName()];
					$nodeScalar = new CS\Scalar(
						$property->name,
						$type->convertToDatabaseValue($propertyChangeSet[0], $this->em->getConnection()->getDatabasePlatform()),
						$type->convertToDatabaseValue($propertyChangeSet[1], $this->em->getConnection()->getDatabasePlatform())
					);
					$changeSet->addPropertyChange($nodeScalar);
				}
				continue;
			}


			// property is toOne association
			/** @var ManyToOne $manyToOneAnnotation */
			$manyToOneAnnotation = $this->reader->getPropertyAnnotation($property, ManyToOne::class);
			/** @var OneToOne $oneToOneAnnotation */
			$oneToOneAnnotation = $this->reader->getPropertyAnnotation($property, OneToOne::class);
			if ($manyToOneAnnotation || $oneToOneAnnotation) {

				$nodeAssociation = $this->getAssociationChangeSet($entity, $property);

				$changeSet->addPropertyChange($nodeAssociation);
				continue;
			}


			// property is toMany collection
			/** @var ManyToOne $manyToOneAnnotation */
			$manyToManyAnnotation = $this->reader->getPropertyAnnotation($property, ManyToMany::class);
			/** @var OneToOne $oneToOneAnnotation */
			$oneToManyAnnotation = $this->reader->getPropertyAnnotation($property, OneToMany::class);
			if ($manyToManyAnnotation || $oneToManyAnnotation) {

				$nodeCollection = $this->getCollectionChangeSet($entity, $property);

				$changeSet->addPropertyChange($nodeCollection);
			}

		}
		return $changeSet;
	}

	/**
	 * @param $entity
	 * @param \ReflectionProperty $property
	 * @return CS\ToMany
	 */
	protected function getCollectionChangeSet($entity, \ReflectionProperty $property)
	{
		$nodeCollection = new CS\ToMany($property->name);

		/** @var PersistentCollection $collection */
		$property->setAccessible(TRUE);
		$collection = $property->getValue($entity);

		if ($collection instanceof PersistentCollection) {
			$removed = $collection->getDeleteDiff();
			$added = $collection->getInsertDiff();
		} elseif ($collection instanceof Collection) {
			$removed = [];
			$added = $collection->toArray();
		} else {
			return $nodeCollection;
		}

		foreach ($removed as $relatedEntity) {
			$nodeCollection->addRemoved($this->createIdentification($relatedEntity));
		}

		foreach ($added as $relatedEntity) {
			$nodeCollection->addAdded($this->createIdentification($relatedEntity));
		}

		/** @var DLA\LoggableProperty $loggablePropertyAnnotation */
		$loggablePropertyAnnotation = $this->reader->getPropertyAnnotation($property, DLA\LoggableProperty::class);
		if ($loggablePropertyAnnotation->logEntity) {
			foreach ($collection as $relatedEntity) {
				$nodeCollection->addChangeSet($this->getChangeSet($relatedEntity));
			}
		}

		return $nodeCollection;
	}

	/**
	 * @param $entity
	 * @param \ReflectionProperty $property
	 * @return CS\ToOne
	 */
	protected function getAssociationChangeSet($entity, \ReflectionProperty $property)
	{
		/** @var DLA\LoggableProperty $loggedPropertyAnnotation */
		$loggedPropertyAnnotation = $this->reader->getPropertyAnnotation($property, DLA\LoggableProperty::class);
		/** @var ManyToOne $manyToOneAnnotation */
		$manyToOneAnnotation = $this->reader->getPropertyAnnotation($property, ManyToOne::class);
		/** @var OneToOne $oneToOneAnnotation */
		$oneToOneAnnotation = $this->reader->getPropertyAnnotation($property, OneToOne::class);

		$relatedEntity = $this->em->getClassMetadata(ClassUtils::getClass($entity))->getFieldValue($entity, $property->name);
		$newIdentification = $oldIdentification = $this->createIdentification($relatedEntity);

		// owning side (ManyToOne is always owning side, OneToOne only if inversedBy is set (or nothing set - unidirectional)
		if ($manyToOneAnnotation || ($oneToOneAnnotation && $oneToOneAnnotation->mappedBy === NULL)) {
			$uowEntityChangeSet = $this->uow->getEntityChangeSet($entity);
			if (isset($uowEntityChangeSet[$property->getName()])) {
				$propertyChangeSet = $uowEntityChangeSet[$property->getName()];
				$oldIdentification = $this->createIdentification($propertyChangeSet[0]);
			}

			// inversed side - its OneToOne with mappedBy annotation
			// TODO poradne otestovat, nebo este lepsi udelat testy
		} else {
			$ownerProperty = $oneToOneAnnotation->mappedBy;
			$ownerClass = $this->em->getClassMetadata(ClassUtils::getClass($entity))
				->getAssociationTargetClass($property->name);
			$identityMap = $this->uow->getIdentityMap();
			if (isset($identityMap[$ownerClass])) {
				foreach ($identityMap[$ownerClass] as $ownerEntity) {

					if (isset($this->scheduledEntities[spl_object_hash($ownerEntity)])) {

						if ($this->scheduledEntities[spl_object_hash($ownerEntity)] == CS\ChangeSet::ACTION_DELETE) {
							if ($entity === $ownerEntity->{$ownerProperty}) {
								$oldIdentification = $this->createIdentification($ownerEntity);
								break;
							}
						} else {
							$ownerEntityChangeSet = $this->uow->getEntityChangeSet($ownerEntity);
							if (isset($ownerEntityChangeSet[$ownerProperty])) {
								if ($ownerEntityChangeSet[$ownerProperty][0] == $entity) {
									$oldIdentification = $this->createIdentification($ownerEntity);
									break;
								}
							}
						}
					}
				}
			}
		}

		$toOne = new CS\ToOne($property->name, $oldIdentification, $newIdentification);

		if ($loggedPropertyAnnotation->logEntity) {
			$toOne->setChangeSet($this->getChangeSet($relatedEntity));
		}

		return $toOne;
	}

	/**
	 * @param object|NULL $entity
	 * @return CS\Id|NULL
	 */
	protected function createIdentification($entity = NULL)
	{
		if ($entity === NULL) {
			return NULL;
		}
		$entityHash = spl_object_hash($entity);
		if (!isset($this->identifications[$entityHash])) {

			$class = ClassUtils::getClass($entity);
			$metadata = $this->em->getClassMetadata($class);
			/** @var DLA\LoggableIdentification $identificationAnnotation */
			$identificationAnnotation = $this->reader->getClassAnnotation(new \ReflectionClass($class), DLA\LoggableIdentification::class);
			$identificationData = [];
			if ($identificationAnnotation) {
				foreach ($identificationAnnotation->fields as $fieldName) {
					$fieldNameParts = explode('.', $fieldName);
					$values = [$entity];
					$newValues = [];
					foreach ($fieldNameParts as $fieldNamePart) {
						foreach ($values as $value) {
							if (is_object($value) && $value instanceof \Doctrine\Persistence\Proxy) {
								if (!$value->__isInitialized()) {
									$value->__load();
								}
							}
							$fieldValue = $this->em->getClassMetadata(ClassUtils::getClass($value))
								->getFieldValue($value, $fieldNamePart);
							if (is_array($fieldValue) || $fieldValue instanceof \Traversable) {
								foreach ($fieldValue as $item) {
									$newValues[] = $this->convertIdentificationValue($item);
								}
							} else {
								$newValues[] = $this->convertIdentificationValue($fieldValue);
							}
						}
						$values = $newValues;
						$newValues = [];
					}
					$identificationData[$fieldName] = implode(', ', $values);
				}
			}
			$id = $metadata->getIdentifierValues($entity);
			$identification = new CS\Id(implode('-', $id), $class, $identificationData);

			$this->identifications[$entityHash] = $identification;
		}
		return $this->identifications[$entityHash];
	}

	protected function convertIdentificationValue($value)
	{
		if ($value instanceof \DateTime) {
			if ($value->format('H:i:s') == '00:00:00') {
				$value = $value->format('j.n.Y');
			} else {
				$value = $value->format('j.n.Y H:i');
			}
		}
		return $value;
	}

	/**
	 * @param $entityClassName
	 * @return \ReflectionProperty[]
	 */
	protected function getLoggedProperties($entityClassName)
	{
		if (!isset($this->loggableEntityProperties[$entityClassName])) {
			$reflection = new \ReflectionClass($entityClassName);
			$list = [];
			foreach ($reflection->getProperties() as $property) {
				$an = $this->reader->getPropertyAnnotation($property, DLA\LoggableProperty::class);
				if ($an !== NULL) {
					$list[] = $property;
				}
			}
			$this->loggableEntityProperties[$entityClassName] = $list;
		}
		return $this->loggableEntityProperties[$entityClassName];
	}

	public function isPropertyLogged($entityClassName, $propertyName)
	{
		$properties = $this->getLoggedProperties($entityClassName);
		return isset($properties[$propertyName]);
	}

	public function getPropertyAnnotation($entityClassName, $propertyName)
	{
		$properties = $this->getLoggedProperties($entityClassName);
		return isset($properties[$propertyName]) ? $properties[$propertyName] : NULL;
	}

	public function shutdownFlush()
	{
		$this->afterShutdown = TRUE;
		if (!isset($this->em) || count($this->logEntries) === 0) {
			return;
		}
		foreach ($this->logEntries as $logEntry) {
			$logEntry->setObjectId($logEntry->getChangeSet()->getIdentification()->getId());
			$logEntry->setAction($logEntry->getChangeSet()->getAction());
			$this->em->persist($logEntry);
			$this->em->flush($logEntry);
		}
	}

	protected function getLogEntry($entity)
	{
		$soh = spl_object_hash($entity);
		if (isset($this->logEntries[$soh])) {
			return $this->logEntries[$soh];
		}
		$metadata = $this->em->getClassMetadata(get_class($entity));
		$entityClassName = $metadata->name;

		$logEntryClass = $this->getLogEntryClass();
		/** @var LogEntry $logEntry */
		$logEntry = new $logEntryClass;
		if ($this->userIdProvider) {
			$logEntry->setUserId($this->userIdProvider->getId());
		}
		$logEntry->setLoggedNow();
		$logEntry->setObjectClass($entityClassName);

		$pkField = $metadata->getSingleIdentifierFieldName();

		$pk = $metadata->getIdentifierValues($entity);
		$logEntry->setObjectId(implode('-', $pk));

		return $logEntry;
	}

	/**
	 * @return string
	 */
	public function getLogEntryClass()
	{
		return $this->logEntryClass;
	}

	/**
	 * @param string $logEntryClass
	 */
	public function setLogEntryClass($logEntryClass)
	{
		$this->logEntryClass = $logEntryClass;
	}

	/**
	 * @param EntityManager $em
	 */
	public function setEntityManager($em)
	{
		$this->em = $em;
		$this->uow = $this->em->getUnitOfWork();
	}

	/**
	 * @return bool
	 */
	public function isAfterShutdown()
	{
		return $this->afterShutdown;
	}

}
