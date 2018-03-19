<?php

namespace Adt\DoctrineLoggable\Annotations;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
class LoggableProperty
{
	
	/** @var bool */
	public $logEntity = TRUE;

	/** @var bool */
	public $logFile = FALSE;

	/** @var string */
	public $label = NULL;

}
