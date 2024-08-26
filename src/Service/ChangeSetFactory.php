<?php

namespace Adt\DoctrineLoggable\Service;

use Adt\DoctrineLoggable\ChangeSet AS CS;
use Adt\DoctrineLoggable\Annotations AS DLA;
use Adt\DoctrineLoggable\ChangeSet\ChangeSet;
use Adt\DoctrineLoggable\Entity\LogEntry;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\EventManager;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use Exception;
use Nette\Utils\Json;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

class ChangeSetFactory
{
	protected EntityManager $em;

	protected UnitOfWork $uow;

	private Reader $reader;

	protected string $logEntryClass = LogEntry::class;

	protected array $loggableEntityClasses = [];

	protected array $loggableEntityProperties = [];

	/**
	 * List of all log entries
	 *
	 * @var LogEntry[]
	 */
	protected array $logEntries = [];

	protected array $scheduledEntities = [];

	protected array $computedEntityChangeSets = [];

	protected array $identifications = [];

	/** @var UserIdProvider */
	private UserIdProvider $userIdProvider;

	protected $associationStructure = [];

	public function __construct(Reader $reader, UserIdProvider $userIdProvider)
	{
		$this->reader = $reader;
		$this->userIdProvider = $userIdProvider;
	}

	// vytvori napriklad nasledujici strukturu
	//$structure = [
	//	'Entity\UserAgreement' => [
	//		[
	//			'user'
	//		]
	//	],
	//	'Entity\File' => [
	//		[
	//			'userAgreementDocument',
	//			'user'
	//		],
	//		[
	//			'branchPhoto'
	//		]
	//	]
	//];
	// jedna se o cestu od entity, na ktere se udala zmena, k materske entite, u ktere je anotace loggableEntity
	/**
	 * @throws ReflectionException
	 */
	public function getLoggableEntityAssociationStructure($className = null, $path = [])
	{
		if ($this->associationStructure) {
			return $this->associationStructure;
		}

		$structure = [];
		$metadataFactory = $this->em->getMetadataFactory();
		$classes = $className ? [$metadataFactory->getMetadataFor($className)] : $metadataFactory->getAllMetadata();
		foreach ($classes as $classMetadata) {
			// pokud nejsme zanoreni, tak nas zajimaji jen entity s anotaci LoggableEntity
			if (!$className && !$this->isEntityLogged($classMetadata->getName())) {
				continue;
			}

			foreach ($this->getLoggedProperties($classMetadata->getName()) as $property) {
				// zajimaji nas jen asociace, nikoliv pole
				if (!$classMetadata->hasAssociation($property->getName())) {
					continue;
				}

				$associationMapping = $classMetadata->getAssociationMapping($property->getName());
				$associationPropertyName = '';
				if ($associationMapping['type'] === ClassMetadataInfo::ONE_TO_ONE) {
					// OneToOne works if entity is recreated on edit (for example files)
					continue;
					// TODO support of non recreating entities (for example address)
					//$associationPropertyName = 'inversedBy';
				}
				elseif ($associationMapping['type'] === ClassMetadataInfo::ONE_TO_MANY){
					$associationPropertyName = 'mappedBy';
				}
				if ($associationPropertyName) {
					if (empty($associationMapping[$associationPropertyName])) {
						throw new Exception('The "' . $associationPropertyName . '" annotation property is missing for loggable property "' . $classMetadata->getName() . '::$' . $property->getName() . '"');
					}

					$structure[$associationMapping['targetEntity']][] = array_merge([$associationMapping[$associationPropertyName]], $path);
				}
			}
		}

		if ($className) {
			return $structure;
		}

		return $this->associationStructure = $structure;
	}

	public function getLoggableEntityFromAssociationStructure($associationEntity)
	{
		$associationEntityClassName = ClassUtils::getClass($associationEntity);
		foreach($this->associationStructure[$associationEntityClassName] as $propertyStructure) {
			foreach ($propertyStructure as $propertyName) {
				$property = new ReflectionProperty($associationEntityClassName, $propertyName);
				$property->setAccessible(true);
				$value = $property->getValue($associationEntity);

				// pokud neni nastavena hodnota, vime ze jsme ve spatne ceste
				if (!$value) {
					continue 2;
				}

				// vylezli jsme o uroven vys, je potreba nastavit aktualni tridu, ve ktere se nachazime
				$associationEntityClassName = get_class($value);
				$associationEntity = $value;
			}

			if ($value) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * @throws ReflectionException
	 */
	public function isEntityLogged($entityClass)
	{
		if (!array_key_exists($entityClass, $this->loggableEntityClasses)) {
			$reflection = new ReflectionClass($entityClass);
			$an = $this->reader->getClassAnnotation($reflection, DLA\LoggableEntity::class);
			$this->loggableEntityClasses[$entityClass] = (bool) $an;
		}
		return $this->loggableEntityClasses[$entityClass];
	}

	public function processLoggedEntity($entity, $relatedEntity = null): void
	{
		$changeSet = $this->getChangeSet($entity, $relatedEntity);
		if (!$changeSet->isChanged()) {
			return;
		}
		$logEntry = $this->getLogEntry($entity);
		$logEntry->setChangeset($changeSet);
		$this->em->getUnitOfWork()->computeChangeSet($this->em->getClassMetadata(get_class($logEntry)), $logEntry);
	}

	public function updateIdentification($entity): void
	{
		$oid = spl_object_hash($entity);
		if (array_key_exists($oid, $this->identifications)) {

			$metadata = $this->em->getClassMetadata(ClassUtils::getClass($entity));
			$id = $metadata->getIdentifierValues($entity);

			$identification = $this->identifications[$oid];
			$identification->setId(implode('-', $id));
		}
	}

	/**
	 * @param object|null $entity
	 * @param object|null $relatedEntity
	 * @return ChangeSet
	 * @throws ReflectionException
	 */
	protected function getChangeSet(?object $entity =  null, ?object $relatedEntity = null)
	{
		if ($entity === null) {
			return null;
		}

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
					$propertyChangeSet = $uowEntiyChangeSet[$property->getName()];

					$nodeScalar = new CS\Scalar($property->name, $propertyChangeSet[0], $propertyChangeSet[1]);
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

				$nodeCollection = $this->getCollectionChangeSet($entity, $property, $relatedEntity);

				$changeSet->addPropertyChange($nodeCollection);
			}

		}
		return $changeSet;
	}

	/**
	 * @param $entity
	 * @param ReflectionProperty $property
	 * @return CS\ToMany
	 */
	public function getCollectionChangeSet($entity, ReflectionProperty $property, $relatedEntity = null): CS\ToMany
	{
		$nodeCollection = new CS\ToMany($property->name);

		if ($entity instanceof \Doctrine\ORM\Proxy\Proxy) {
			if (!$entity->__isInitialized()) {
				$entity->__load();
			}
		}

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

		if ($relatedEntity) {
			foreach ($collection as $_relatedEntity) {
				if ($relatedEntity === $_relatedEntity) {
					$nodeCollection->addChangeSet($this->getChangeSet($relatedEntity));
					break;
				}
			}
		}

		return $nodeCollection;
	}

	/**
	 * @param $entity
	 * @param ReflectionProperty $property
	 * @return CS\ToOne
	 * @throws ReflectionException
	 */
	protected function getAssociationChangeSet($entity, ReflectionProperty $property)
	{
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

		return new CS\ToOne($property->name, $oldIdentification, $newIdentification);
	}

	/**
	 * @param object|NULL $entity
	 * @return CS\Id|NULL
	 * @throws ReflectionException
	 */
	public function createIdentification(?object $entity = NULL): ?CS\Id
	{
		if ($entity === NULL) {
			return NULL;
		}
		$entityHash = spl_object_hash($entity);
		if (!isset($this->identifications[$entityHash])) {
			$class = ClassUtils::getClass($entity);
			$metadata = $this->em->getClassMetadata($class);
			/** @var DLA\LoggableIdentification $identificationAnnotation */
			$identificationAnnotation = $this->reader->getClassAnnotation(new ReflectionClass($class), DLA\LoggableIdentification::class);
			$identificationData = [];
			if ($identificationAnnotation) {
				foreach ($identificationAnnotation->fields as $fieldName) {
					$fieldNameParts = explode('.', $fieldName);
					$values = [$entity];
					$newValues = [];
					foreach ($fieldNameParts as $fieldNamePart) {
						foreach ($values as $value) {
							if (is_object($value) && ($value instanceof \Doctrine\ORM\Proxy\Proxy)) {
								if (!$value->__isInitialized()) {
									$value->__load();
								}
							}

							$getter = 'get' . ucfirst($fieldNamePart);
							if (method_exists($value, $getter)) {
								$fieldValue = $value->$getter();
							} else {
								$fieldValue = $this->em->getClassMetadata(ClassUtils::getClass($value))
									->getFieldValue($value, $fieldNamePart);
							}
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
	 * @return ReflectionProperty[]
	 */
	protected function getLoggedProperties($entityClassName)
	{
		if (!isset($this->loggableEntityProperties[$entityClassName])) {
			$reflection = new ReflectionClass($entityClassName);
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

	public function getLogEntry($entity)
	{
		$soh = spl_object_hash($entity);
		if (isset($this->logEntries[$soh])) {
			return $this->logEntries[$soh];
		}

		$metadata = $this->em->getClassMetadata(get_class($entity));

		$logEntryClass = $this->getLogEntryClass();

		/** @var LogEntry $logEntry */
		$logEntry = new $logEntryClass;
		if ($this->userIdProvider) {
			$logEntry->setUserId($this->userIdProvider->getId());
		}
		$logEntry->setLoggedNow();
		$logEntry->setObjectClass($metadata->name);
		$logEntry->setObjectId(implode('-', $metadata->getIdentifierValues($entity)));
		$logEntry->setAction(CS\ChangeSet::ACTION_EDIT);

		$this->logEntries[spl_object_hash($entity)] = $logEntry;
		$this->em->persist($logEntry);

		return $logEntry;
	}

	/**
	 * @return string
	 */
	public function getLogEntryClass()
	{
		return $this->logEntryClass;
	}

	public function setLogEntryClass(string $logEntryClass): void
	{
		$this->logEntryClass = $logEntryClass;
	}

	/**
	 * @param $em
	 * @return $this
	 */
	public function setEntityManager($em)
	{
		$this->em = $em;
		$this->uow = $this->em->getUnitOfWork();
		return $this;
	}
}
