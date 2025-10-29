<?php

namespace Lx\Database\DBALTypes;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class EnumType extends Type {

  const ENUM = "enum";

  public function getName() {
    return self::ENUM;
  }

  public function getSQLDeclaration(array $column, AbstractPlatform $platform): string {
    //$length = $column['length'] ?? [];
    return $platform->getClobTypeDeclarationSQL($column);
    //return sprintf("enum('%s')", implode("','", $length));
  }
  
  public function convertToPHPValue (mixed $value, AbstractPlatform $platform): mixed {
    return (null === $value) ? null : (string) $value;
  }

  public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed {
    return $value;
  }

  public function getBindingType (): ParameterType {
    return ParameterType::STRING;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresSQLCommentHint(AbstractPlatform $platform)
  {
    return true;
  }
}
