<?php

namespace Adt\DoctrineLoggable\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;
use Attribute;
use Doctrine\ORM\Mapping\Annotation;

/**
 * @Annotation
 * @Target("CLASS")
 */
#[Attribute(Attribute::TARGET_CLASS)]
class LoggableIdentification implements Annotation
{
	/**
	 * @Required
	 * @var array
	 */
	public $fields;

	public function __construct(array $fields)
	{
		$this->fields = $fields;
	}
}
