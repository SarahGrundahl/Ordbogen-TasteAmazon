<?php
namespace includes\admin\internal\tasteamazon;

class Product extends TasteAmazon
{
  const TABLE = 'products';
  const COLUMN_PRIMARY_KEY = 'id';
  const COLUMN_TITLE = 'title';
  const COLUMN_TEXT = 'text';
  const COLUMN_DESCRIPTION = 'description';
  const COLUMN_IMAGE = 'image';
  // const VERBOSE_SQL = true;

  /**
  * Get product by product_id
  * @return int product_id
  * @throws Product[]
  */
  public static function getByProductId($productId)
  {
    $where = ''.self::COLUMN_PRIMARY_KEY.' = \''.$productId.'\'';
    return self::getAllByWhere($where);
  }

  /**
  * Map new product
  * @return string[] data
  * @throws Product[]
  */
  public function map(array $data)
  {
    $this->{self::COLUMN_TITLE} = $data['title'];
    $this->{self::COLUMN_TEXT} = $data['text'];
    $this->{self::COLUMN_DESCRIPTION} = $data['description'];
    $this->{self::COLUMN_IMAGE} = $data['image'];
  }
}
