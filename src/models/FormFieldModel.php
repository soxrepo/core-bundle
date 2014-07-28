<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2014 Leo Feyer
 *
 * @package Core
 * @link    https://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

namespace Contao;

use Contao\Model\Collection;


/**
 * Reads and writes form fields
 *
 * @package   Models
 * @author    Leo Feyer <https://github.com/leofeyer>
 * @copyright Leo Feyer 2005-2014
 */
class FormFieldModel extends Model
{

	/**
	 * Table name
	 * @var string
	 */
	protected static $strTable = 'tl_form_field';


	/**
	 * Find published form fields by their parent ID
	 *
	 * @param int   $intPid     The form ID
	 * @param array $arrOptions An optional options array
	 *
	 * @return Collection|null A collection of models or null if there are no form fields
	 */
	public static function findPublishedByPid($intPid, array $arrOptions=[])
	{
		$t = static::$strTable;
		$arrColumns = ["$t.pid=?"];

		if (!BE_USER_LOGGED_IN)
		{
			$arrColumns[] = "$t.invisible=''";
		}

		if (!isset($arrOptions['order']))
		{
			$arrOptions['order'] = "$t.sorting";
		}

		return static::findBy($arrColumns, $intPid, $arrOptions);
	}
}
