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

	/**
	 * Set to false if you dont want to log changed values. Usefull for big text/blob fields.
	 * Value logged into changeset will be either null or true.
	 *
	 * @var bool
	 */
	public $logValue = TRUE;

	/** @var bool */
	public $logFile = FALSE;

	/** @var string */
	public $label = NULL;

}
