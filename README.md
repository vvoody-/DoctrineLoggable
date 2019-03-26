# DoctrineLoggable

## Installation

1. Install via composer:

    ```bash
    composer require adt/doctrine-loggable
    ```
    
2. Register this extension in your config.neon:

    ```neon
    extensions:
        - Adt\DoctrineLoggable\DI\LoggableExtension
    ```
        
3. Do database migrations

4. Add annotation to entities you wish to log

```php
<?php

use Doctrine\ORM\Mapping as ORM;
use Adt\DoctrineLoggable\Annotations as ADA;
	
/**
 * @ORM\Entity
 * @ADA\LoggableEntity
 */
class User
{

	/**
	 * @ORM\Column(type="string", nullable=true)
	 * @ADA\LoggableProperty(label="entity.user.firstname")
	 */
	protected $firstname;

	/**
	 * @ORM\ManyToMany(targetEntity="Role", inversedBy="users")
	 * @ADA\LoggableProperty(logEntity=false, label="entity.user.roles")
	 */
	protected $roles;
	
}

/**
 * @ORM\Entity
 * @ADA\LoggableIdentification(fields={"name"})
 */
class Role
{

	/**
	 * @ORM\Column(type="string")
	 */
	protected $name;

	/**
	 * @ORM\ManyToMany(targetEntity="User", mappedBy="roles")
	 */
	protected $users;

}
```
