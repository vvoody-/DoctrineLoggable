<?php

namespace Adt\DoctrineLoggable\Annotations;

use Attribute;
use Doctrine\ORM\Mapping\Annotation;

/**
 * @Annotation
 * @Target("CLASS")
 */
#[Attribute(Attribute::TARGET_CLASS)]
class LoggableEntity implements Annotation
{

}
