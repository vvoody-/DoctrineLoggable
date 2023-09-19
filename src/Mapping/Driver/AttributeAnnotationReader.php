<?php

namespace Adt\DoctrineLoggable\Mapping\Driver;

use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\Mapping\Annotation;
use ReflectionClass;
use ReflectionMethod;

/**
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 *
 * @internal
 */
final class AttributeAnnotationReader implements Reader
{
	/**
	 * @var ?Reader
	 */
	private $annotationReader;

	/**
	 * @var AttributeReader
	 */
	private $attributeReader;

	public function __construct(?Reader $annotationReader = null)
	{
		if (PHP_VERSION_ID >= 80000) {
			$this->attributeReader = new AttributeReader();
		}
	$this->annotationReader = $annotationReader;
	}

	/**
	 * @return Annotation[]
	 */
	public function getClassAnnotations(ReflectionClass $class): array
	{
		$annotations = $this->attributeReader ? $this->attributeReader->getClassAnnotations($class) : [];;

		if ([] !== $annotations) {
			return $annotations;
		}

		if (!$this->annotationReader) {
			return [];
		}

		return $this->annotationReader->getClassAnnotations($class);
	}

	/**
	 * @param class-string<T> $annotationName the name of the annotation
	 *
	 * @return T|null the Annotation or NULL, if the requested annotation does not exist
	 *
	 * @template T
	 */
	public function getClassAnnotation(ReflectionClass $class, $annotationName)
	{
		$annotation = $this->attributeReader ? $this->attributeReader->getClassAnnotation($class, $annotationName) : null;

		if (null !== $annotation) {
			return $annotation;
		}

		if (!$this->annotationReader) {
			return null;
		}

		return $this->annotationReader->getClassAnnotation($class, $annotationName);
	}

	/**
	 * @return Annotation[]
	 */
	public function getPropertyAnnotations(\ReflectionProperty $property): array
	{
		$propertyAnnotations = $this->attributeReader ? $this->attributeReader->getPropertyAnnotations($property) : [];

		if ([] !== $propertyAnnotations) {
			return $propertyAnnotations;
		}

		if (!$this->annotationReader) {
			return [];
		}

		return $this->annotationReader->getPropertyAnnotations($property);
	}

	/**
	 * @param class-string<T> $annotationName the name of the annotation
	 *
	 * @return T|null the Annotation or NULL, if the requested annotation does not exist
	 *
	 * @template T
	 */
	public function getPropertyAnnotation(\ReflectionProperty $property, $annotationName)
	{
		$annotation = $this->attributeReader ? $this->attributeReader->getPropertyAnnotation($property, $annotationName) : null;

		if (null !== $annotation) {
			return $annotation;
		}

		if (!$this->annotationReader) {
			return null;
		}

		return $this->annotationReader->getPropertyAnnotation($property, $annotationName);
	}

	public function getMethodAnnotations(ReflectionMethod $method): array
	{
		throw new \BadMethodCallException('Not implemented');
	}

	/**
	 * @return mixed
	 */
	public function getMethodAnnotation(ReflectionMethod $method, $annotationName)
	{
		throw new \BadMethodCallException('Not implemented');
	}
}
