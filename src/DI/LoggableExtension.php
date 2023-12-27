<?php

namespace Adt\DoctrineLoggable\DI;

use Adt\DoctrineLoggable\Entity\LogEntry;
use Adt\DoctrineLoggable\Listener\LoggableListener;
use Adt\DoctrineLoggable\Mapping\Driver\AttributeAnnotationReader;
use Adt\DoctrineLoggable\Service\ChangeSetFactory;
use Adt\DoctrineLoggable\Service\SessionUserIdProvider;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\EventManager;
use Kdyby\Doctrine\DI\IEntityProvider;
use Nette\Application\Application;
use Nette\DI\CompilerExtension;

class LoggableExtension extends CompilerExtension
{
	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('changeSetFactory'))
			->setFactory(ChangeSetFactory::class);

		$builder->addDefinition($this->prefix('listener'))
			->setFactory(LoggableListener::class);

		$builder->addDefinition($this->prefix('userIdProvider'))
			->setFactory(SessionUserIdProvider::class);
	}

	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();

		if ($builder->getByType(Reader::class)) {
			$attributeAnnotationReader = $builder->addDefinition($this->prefix('attributeAnnotationReader'))
				->setFactory(AttributeAnnotationReader::class, ['@' . Reader::class]);
		} else {
			$attributeAnnotationReader = $builder->addDefinition($this->prefix('attributeAnnotationReader'))
				->setFactory(AttributeAnnotationReader::class);
		}
		$attributeAnnotationReader->setAutowired(false);
		$builder->getDefinitionByType(ChangeSetFactory::class)
			->setArguments(['reader' => $attributeAnnotationReader]);

		$serviceName = $builder->getByType(EventManager::class);
		$builder->getDefinition($serviceName)
			->addSetup('addEventSubscriber', ['@' . $this->prefix('listener')]);

		$serviceName = $builder->getByType(Application::class);
		$builder->getDefinition($serviceName)
			->addSetup('$service->onShutdown[] = ?', [['@' . $this->prefix('changeSetFactory'), 'shutdownFlush']]);
	}
}
