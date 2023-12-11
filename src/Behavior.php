<?php

namespace PromCMS\Core\Propel\Behaviors\PromModel;

use Propel\Generator\Model\Column;
use Propel\Generator\Model\Table;

class Behavior extends \Propel\Generator\Model\Behavior
{
  static string $PROM_ATTRIBUTE_NAMESPACE = 'prom';
  static string $PROM_ADMIN_METADATA_ATTRIBUTE_NAMESPACE = "prom.adminmetadata";

  public function objectMethods()
  {
    $script = $this->addGetPromMetadata();
    return $script;
  }

  private function assignArrayByPath(&$arr, $path, $value, $separator = '.')
  {
    $keys = explode($separator, $path);

    foreach ($keys as $key) {
      $arr = &$arr[$key];
    }

    $arr = $value;
  }

  private function booleanToString(bool $value)
  {
    return $value ? "true" : "false";
  }

  private function getAdminMetadata(Column|Table $obj)
  {
    $attributes = $obj->getAttributes();
    $attributeNamespace = static::$PROM_ADMIN_METADATA_ATTRIBUTE_NAMESPACE . '.';
    $attributeNames = array_filter($attributes, fn($value) => str_starts_with($value, $attributeNamespace));
    $result = [];

    foreach ($attributeNames as $attributeName) {
      $value = $obj->getAttribute($attributeName);

      if ($value === 'false' || $value === 'true') {
        $value = $value === 'true';
      } else if (is_numeric($value)) {
        $value = intval($value);
      }

      $this->assignArrayByPath($result, str_replace($attributeNamespace, '', $attributeName), $value);
    }

    return $result;
  }

  protected function addGetPromMetadata()
  {
    $table = $this->getTable();
    $tablePhpName = $table->getPhpName();

    $hasSoftDelete = $this->booleanToString($table->hasBehavior('archivable'));
    $hasTimestamps = $this->booleanToString($table->hasBehavior('timestampable'));
    $hasOrdering = $this->booleanToString($table->hasBehavior('sortable'));
    $isDraftable = $this->booleanToString(false);

    $ignoreSeeding = $table->getAttribute('prom.ignoreseeding', 'false');
    $isSharable = $table->getAttribute('prom.sharable', 'false');
    $isOwnable = $table->getAttribute('prom.ownable', 'false');

    // $tableMetadata = $this->getAdminMetadata($table);

    return "
/**
 * Gets table, and it's columns, metadata
 *
 */
public static function getPromCMSMetadata()
{
  return [
    'icon' => '', // This will be added later
    'ignoreSeeding' => $ignoreSeeding,
    'admin' => [], // This will be added later
    'tableName' => ($tablePhpName::TABLE_MAP)::TABLE_NAME,
    'hasTimestamps' => $hasTimestamps,
    'hasSoftDelete' => $hasSoftDelete,
    'columns' => [], // This will be added later
    'hasOrdering' => $hasOrdering,
    'isDraftable' => $isDraftable,
    'isSharable' => $isSharable,
    'ownable' => $isOwnable,
  ];
}
";
  }
}