<?php

namespace PromCMS\Core\Propel\Behaviors\PromModel;

use Propel\Generator\Behavior\I18n\I18nBehavior;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\Table;

class Behavior extends \Propel\Generator\Model\Behavior
{
  static string $PROM_ATTRIBUTE_NAMESPACE = 'prom';
  public function objectMethods()
  {
    $script = '';
    $script .= $this->addGetPromMetadata();
    $script .= $this->addIsSingleton();
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

  private function arrayToStringArray(array $input, $prefix = "[", $suffix = ']')
  {
    $output = $prefix;

    foreach ($input as $key => $value) {
      $valueAsString = $value;

      if (is_array($valueAsString)) {
        $valueAsString = $this->arrayToStringArray($value);
      } else {
        $valueAsString = json_encode($valueAsString);
      }

      $output .= "'$key' => $valueAsString,";
    }

    $output .= $suffix;

    return $output;
  }

  private function getPromMetadata(Column|Table $obj)
  {
    $attributes = $obj->getAttributes();
    $attributeNamespace = static::$PROM_ATTRIBUTE_NAMESPACE . '.';

    $attributeNames = array_filter($attributes, fn($key) => str_starts_with($key, $attributeNamespace), ARRAY_FILTER_USE_KEY);
    $result = [];

    foreach ($attributeNames as $attributeName => $attributeValue) {
      if ($attributeValue === 'false' || $attributeValue === 'true') {
        $attributeValue = $attributeValue === 'true';
      } else if (is_numeric($attributeValue)) {
        $attributeValue = intval($attributeValue);
      }

      $attributeName = str_replace('adminmetadata', 'adminMetadata', $attributeName);
      $attributeName = str_replace('ignoreseeding', 'ignoreSeeding', $attributeName);

      $this->assignArrayByPath($result, str_replace($attributeNamespace, '', $attributeName), $attributeValue);
    }

    return $result;
  }

  protected function addIsSingleton()
  {
    $table = $this->getTable();
    $tablePhpName = $table->getPhpName();

    return "
public static function isSingleton()
{
  return str_contains(($tablePhpName::TABLE_MAP)::TABLE_NAME, 'singleton_');
}";
  }

  protected function addGetPromMetadata()
  {
    $table = $this->getTable();
    $tablePhpName = $table->getPhpName();
    $columns = $table->getColumns();
    $localizedColumns = [];
    $columnsAsMetadata = [];

    /**
     * @var I18nBehavior $localizedBehaviorInfo
     */
    if ($localizedBehaviorInfo = $table->getBehavior('i18n')) {
      $localizedColumns = array_map(fn(Column $column) => $column->getName(), $localizedBehaviorInfo->getI18nColumns());
    }


    $hasSoftDelete = json_encode($table->hasBehavior('archivable'));
    $hasTimestamps = json_encode($table->hasBehavior('timestampable'));
    $hasOrdering = json_encode($table->hasBehavior('sortable'));
    $isDraftable = json_encode(false);

    $isSharable = $table->getAttribute('prom.sharable', 'false');
    $isOwnable = $table->getAttribute('prom.ownable', 'false');

    $tableMetadata = $this->getPromMetadata($table);
    $icon = $tableMetadata['adminMetadata']['icon'];

    foreach ($columns as $column) {
      $columnMetadata = $this->getPromMetadata($column);
      $columnName = $column->getName();

      $columnMetadata['required'] = $column->isNotNull();
      $columnMetadata['unique'] = $column->isUnique();
      $columnMetadata['translations'] = in_array($columnName, $localizedColumns);
      $columnMetadata['autoIncrement'] = $column->isAutoIncrement();

      $columnsAsMetadata[$columnName] = $columnMetadata;
    }

    return "
private static \$promCmsMetadata = [
  " . $this->arrayToStringArray($tableMetadata, "", "") . "
  /** @deprec */
  'icon' => '$icon',
  /** @deprec */
  'admin' => " . $this->arrayToStringArray($tableMetadata['adminMetadata']) . ", 
  'tableName' => ($tablePhpName::TABLE_MAP)::TABLE_NAME,
  'hasTimestamps' => $hasTimestamps,
  'hasSoftDelete' => $hasSoftDelete,
  'columns' => " . $this->arrayToStringArray($columnsAsMetadata) . ",
  'hasOrdering' => $hasOrdering,
  'isDraftable' => $isDraftable,
  'isSharable' => $isSharable,
  'ownable' => $isOwnable,
];

/**
 * Gets table, and it's columns, metadata
 *
 */
public static function getPromCmsMetadata()
{
  return static::\$promCmsMetadata;
}
";
  }
}