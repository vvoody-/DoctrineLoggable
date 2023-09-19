<?php

namespace Adt\DoctrineLoggable\Annotations;

use Attribute;
use Doctrine\ORM\Mapping\Annotation;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class LoggableProperty implements Annotation
{
	/** @var bool */
	public $logEntity = TRUE;

	/** @var bool */
	public $logFile = FALSE;

	/** @var string */
	public $label = NULL;

	/**
	 * @param bool $logEntity
	 * @param bool $logFile
	 * @param string|null $label
	 */
	public function __construct($logEntity = true, $logFile = false, string $label = null)
	{
		$this->logEntity = $logEntity;
		$this->logFile = $logFile;
		$this->label = $label;
	}
}
