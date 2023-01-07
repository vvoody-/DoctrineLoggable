<?php

declare (strict_types=1);
namespace RectorPrefix202211;

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\AnnotationToAttributeRector;
use Rector\Php80\ValueObject\AnnotationToAttribute;
return static function (RectorConfig $rectorConfig) : void {
	$rectorConfig->ruleWithConfiguration(
		AnnotationToAttributeRector::class,
		[
			new AnnotationToAttribute('Adt\\DoctrineLoggable\\Annotations\\LoggableIdentification'),
			new AnnotationToAttribute('Adt\\DoctrineLoggable\\Annotations\\LoggableProperty'),
			new AnnotationToAttribute('Adt\\DoctrineLoggable\\Annotations\\LoggableEntity'),
		]
	);
};
