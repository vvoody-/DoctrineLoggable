<?php

namespace Adt\DoctrineLoggable\Service;

use Nette\DI\Container;
use Nette\Security\User;

class SessionUserIdProvider implements UserIdProvider
{

	/** @var Container */
	private $container;

	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	public function getId()
	{
		/** @var User $user */
		$user = $this->container->getByType(User::class);
		return $user->isLoggedIn() ? $user->getId() : NULL;
	}

}
