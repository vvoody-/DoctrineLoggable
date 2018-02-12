<?php

namespace Adt\DoctrineLoggable\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;

/**
 * @Annotation
 * @Target("CLASS")
 */
class LoggableIdentification
{

	/**
	 * @Required
	 * @var array
	 */
	public $fields;

}
