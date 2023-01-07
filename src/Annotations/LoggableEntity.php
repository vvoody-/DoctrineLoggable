<?php

namespace Adt\DoctrineLoggable\Annotations;

use Attribute;

/**
 * @Annotation
 * @Target("CLASS")
 */
#[Attribute(Attribute::TARGET_CLASS)]
class LoggableEntity
{

}
