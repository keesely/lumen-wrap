<?php
/**
 * 
 * @fileName TimestampType.php
 * @category PHP
 * @package void
 * @author Kee Guo <chinboy2012@gmail.com> 
 * @since 28/05/2018
 * @version TimestampType.php 2018.05.28
 * */
namespace Lx\Database\DBALTypes;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class TimestampType extends Type {

  const TIMESTAMP = 'timestamp';

  public function getName () {
    return self::TIMESTAMP;
  }

  public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string {
    return self::TIMESTAMP;
  }

  public function convertToPHPValue (mixed $value, AbstractPlatform $platform): mixed {
    if (null === $value) return null;
    if ($value instanceof \DataTime) return $value;
    $val = \DataTime::createFromFormat($platform->getDataTimeFormatString(), $value);
    if (!$val) $val = date_create($value);
    if (!$val) return null;
    return $val;
  }

}
