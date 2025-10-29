<?php
namespace Lx\Database\DBALTypes;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class UUIDType extends Type {

  public function getName() {
    return 'char(36)';
  }

  public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string {
    return 'char(36)';
  }

  public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed {
    return (null === $value) ? null : intval($value);
  }

  public function getBindingType(): ParameterType {
    return ParameterType::STRING;
    //return 'char(36)';
  }
}
