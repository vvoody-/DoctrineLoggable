<?php

namespace Adt\DoctrineLoggable\Listener;

use Adt\DoctrineLoggable\Service\ChangeSetFactory;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManager;
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

		// musi se vytvorit struktura asociaci,
		// protoze pokud ma loggableEntity OneToOne nebo OneToMany vazby s nastavenym loggableProperty,
		// ve kterych dojde ke zmene, tak v getScheduledEntity metodach bude jen tato kolekce,
		// ale nebude tu materska entita, ktera ma nastaveno loggableEntity, a tudiz nedojde k zalogovani
		$structure = $this->changeSetFactory->getLoggableEntityAssociationStructure();
		
		$uow = $eventArgs->getEntityManager()->getUnitOfWork();
		foreach (['getScheduledEntityInsertions', 'getScheduledEntityUpdates', 'getScheduledEntityDeletions'] as $method) {
			foreach (call_user_func([$uow, $method]) as $entity) {
				$entityClass = ClassUtils::getClass($entity);
				// jedna se o entitu s anotaci loggableEntity
				if ($this->changeSetFactory->isEntityLogged($entityClass)) {
					$this->changeSetFactory->processLoggedEntity($entity);
				}
				// jedna se o upravenou asociaci, jejiz primarni entita ma anotaci loggableEntity
				elseif (isset($structure[$entityClass])) {
					if ($loggableEntity = $this->changeSetFactory->getLoggableEntityFromAssosicationStructure($entity)) {
						$this->changeSetFactory->processLoggedEntity($loggableEntity);
					}
				}
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
