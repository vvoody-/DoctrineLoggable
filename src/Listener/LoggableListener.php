<?php

namespace Adt\DoctrineLoggable\Listener;

use Adt\DoctrineLoggable\Service\ChangeSetFactory;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;

class LoggableListener implements EventSubscriber
{

	/** @var ChangeSetFactory */
	private $changeSetFactory;

	public function __construct(ChangeSetFactory $changeSetFactory)
	{
		$this->changeSetFactory = $changeSetFactory;
	}

	function getSubscribedEvents()
	{
		return [
			'onFlush',
			'postPersist',
		];
	}

	public function onFlush(OnFlushEventArgs $eventArgs)
	{
		if ($this->changeSetFactory->isAfterShutdown()) {
			return;
		}
		$this->changeSetFactory->setEntityManager($eventArgs->getEntityManager());
		$uow = $eventArgs->getEntityManager()->getUnitOfWork();

//		foreach ($this->uow->getScheduledEntityUpdates() as $entity) {
//			$this->scheduledEntities[spl_object_hash($entity)] = ChangeSet::ACTION_EDIT;
//		}
//		foreach ($this->uow->getScheduledEntityInsertions() as $entity) {
//			$this->scheduledEntities[spl_object_hash($entity)] = ChangeSet::ACTION_CREATE;
//		}
//		foreach ($this->uow->getScheduledEntityDeletions() as $entity) {
//			$this->scheduledEntities[spl_object_hash($entity)] = ChangeSet::ACTION_DELETE;
//		}


		foreach ($uow->getIdentityMap() as $entityClass => $entityList) {
			if (!$this->changeSetFactory->isEntityLogged($entityClass)) {
				continue;
			}
			foreach ($entityList as $entity) {
				$this->changeSetFactory->processLoggedEntity($entity);
			}
		}

		foreach ($uow->getScheduledEntityInsertions() as $entity) {
			$entityClass = ClassUtils::getClass($entity);
			if (!$this->changeSetFactory->isEntityLogged($entityClass)) {
				continue;
			}
			$this->changeSetFactory->processLoggedEntity($entity);
		}
	}

	public function postPersist(LifecycleEventArgs $args)
	{
		if ($this->changeSetFactory->isAfterShutdown()) {
			return;
		}
		$object = $args->getObject();
		$this->changeSetFactory->updateIdentification($object);
	}

}
