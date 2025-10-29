<?php
namespace Lx\Database\DBALTypes;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ParameterType;

class TinyIntegerType extends Type {

  const TINYINT = "tinyint";

  public function getName() {
    return self::TINYINT;
  }

  public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string {
    return self::TINYINT;
    //return $platform->getIntegerTypeDeclarationSQL($fieldDeclaration);
  }

  public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed {
    return (null === $value) ? null : intval($value);
  }

  public function getBindingType() : ParameterType {
    return ParameterType::INTEGER;
    //return 'tinyint';
  }
}
