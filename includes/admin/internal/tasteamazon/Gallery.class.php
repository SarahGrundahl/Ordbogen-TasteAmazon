<?php
namespace includes\admin\internal\tasteamazon;

class Gallery extends TasteAmazon
{
  const TABLE = 'gallery';
  const COLUMN_PRIMARY_KEY = 'id';
  const COLUMN_IMAGE = 'image';
  const COLUMN_TITLE = 'title';
  // const VERBOSE_SQL = true;

  /**
  * Get gallery picture by id
  * @return int galleryId
  * @throws Gallery[]
  */
  public static function getByGalleryId($galleryId)
  {
    $where = ''.self::COLUMN_PRIMARY_KEY.' = \''.$galleryId.'\'';
    return self::getAllByWhere($where);
  }

  /**
  * Map new gallery picture
  * @return string[] data
  */
  public function map(array $data)
  {
    $this->{self::COLUMN_IMAGE} = $data['image'];
    $this->{self::COLUMN_TITLE} = $data['title'];
  }
}
