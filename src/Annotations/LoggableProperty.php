<?php

namespace Adt\DoctrineLoggable\Annotations;

use Attribute;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class LoggableProperty
{
	
	/** @var bool */
	public $logEntity = TRUE;

	/** @var bool */
	public $logFile = FALSE;

	/** @var string */
	public $label = NULL;

}
