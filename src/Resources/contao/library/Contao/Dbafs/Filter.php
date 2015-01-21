<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2015 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\Dbafs;


/**
 * The class filters .svn folders, .DS_Store files and folders which are
 * excluded from being synchronized from a directory listing.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class Filter extends \RecursiveFilterIterator
{

	/**
	 * Exempted folders
	 * @var array
	 */
	protected $arrExempt = array();

	/**
	 * Ignored files
	 * @var array
	 */
	protected $arrIgnore = array('.DS_Store', '.svn');


	/**
	 * Exempt folders from the synchronisation (see #4522)
	 *
	 * @param \RecursiveIterator $iterator The iterator object
	 */
	public function __construct(\RecursiveIterator $iterator)
	{
		if ($GLOBALS['TL_CONFIG']['fileSyncExclude'] != '')
		{
			$this->arrExempt = array_map(function($e) {
				return $GLOBALS['TL_CONFIG']['uploadPath'] . '/' . $e;
			}, trimsplit(',', $GLOBALS['TL_CONFIG']['fileSyncExclude']));
		}

		parent::__construct($iterator);
	}


	/**
	 * Check whether the current element of the iterator is acceptable
	 *
	 * @return boolean True if the element is acceptable
	 */
	public function accept()
	{
		// The resource is to be ignored
		if (in_array($this->current()->getFilename(), $this->arrIgnore))
		{
			return false;
		}

		$strRelpath = str_replace(TL_ROOT . '/', '', $this->current()->getPathname());

		// The resource is an exempt folder
		if (in_array($strRelpath, $this->arrExempt))
		{
			return false;
		}

		// The resource is accepted
		return true;
	}
}
