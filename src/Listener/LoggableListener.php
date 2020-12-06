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

		foreach (['getScheduledEntityInsertions', 'getScheduledEntityDeletions', 'getScheduledEntityUpdates'] as $method) {
			foreach (call_user_func([$uow, $method]) as $entity) {
				$entityClass = ClassUtils::getClass($entity);
				if (!$this->changeSetFactory->isEntityLogged($entityClass)) {
					continue;
				}
				$this->changeSetFactory->processLoggedEntity($entity);
			}
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
