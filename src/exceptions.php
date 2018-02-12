<?php

namespace Adt\DoctrineLoggable;

interface Exception
{

}

class UnexpectedValueException extends \UnexpectedValueException implements Exception
{

}
