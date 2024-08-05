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

}
