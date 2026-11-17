<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Resolve selected native ECM illustrations inside the owning plant directory. */
class PowerPlantPVReportIllustration
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';
	/** @param DoliDB $db Database handler */
	public function __construct($db) { $this->db = $db; }

	/**
	 * Read eligible native attachments after the caller checked plant access.
	 * @param PowerPlant $plant Loaded owner
	 * @return array<int,array{label:string,path:string}>|int Files or negative on error
	 */
	public function files($plant)
	{
		global $conf;
		$entity = (int) $plant->entity;
		if ((int) $plant->id <= 0 || $entity <= 0 || !isset($conf->powerplantpv->multidir_output[$entity]) || trim((string) $conf->powerplantpv->multidir_output[$entity]) === '') {
			$this->error = 'PowerPlantPVDocumentDirectoryUnavailable'; return -1;
		}
		$root = getMultidirOutput($plant, 'powerplantpv', 0);
		if (!is_string($root) || $root === '' || strpos($root, 'error-') === 0) { $this->error = 'PowerPlantPVDocumentDirectoryUnavailable'; return -1; }
		$ownerDirectory = $root.'/powerplant/'.dol_sanitizeFileName((string) $plant->ref);
		if (is_link($ownerDirectory)) { $this->error = 'PowerPlantPVReportImageMissing'; return -1; }
		$directory = realpath($ownerDirectory);
		if ($directory === false) { return array(); }
		// ECM stores paths relative to DOL_DATA_ROOT, including owner entity directories.
		$dataRoot = realpath(DOL_DATA_ROOT);
		if ($dataRoot === false || strpos(str_replace('\\', '/', $directory).'/', str_replace('\\', '/', $dataRoot).'/') !== 0) { $this->error = 'PowerPlantPVReportImageMissing'; return -1; }
		$relative = substr(str_replace('\\', '/', $directory), strlen(str_replace('\\', '/', $dataRoot)) + 1);
		$logicalRelative = ltrim(str_replace('\\', '/', substr($ownerDirectory, strlen(DOL_DATA_ROOT))), '/');
		$sql = "SELECT rowid, filename, filepath FROM ".MAIN_DB_PREFIX."ecm_files WHERE entity = ".$entity;
		$sql .= " AND (filepath = '".$this->db->escape($relative)."' OR filepath = '".$this->db->escape($logicalRelative)."'";
		$sql .= " OR (src_object_type IN ('".$this->db->escape((string) $plant->element)."', '".$this->db->escape((string) $plant->table_element)."') AND src_object_id = ".((int) $plant->id).")) ORDER BY filename ASC";
		$result = $this->db->query($sql);
		if (!$result) { $this->error = $this->db->lasterror(); return -1; }
		$files = array();
		while (is_object($row = $this->db->fetch_object($result))) {
			// A source-object relation alone never authorizes a path outside the owner directory.
			if (realpath(DOL_DATA_ROOT.'/'.(string) $row->filepath) !== $directory) { continue; }
			$path = self::imagePath($directory, (string) $row->filename);
			if ($path !== '') { $files[(int) $row->rowid] = array('label' => (string) $row->filename, 'path' => $path); }
		}
		$this->db->free($result);
		return $files;
	}

	/**
	 * Reject traversal, out-of-directory symlinks and files without a supported actual image type.
	 * @param string $directory Verified plant directory
	 * @param string $filename ECM filename
	 * @return string Validated absolute image path or empty
	 */
	public static function imagePath($directory, $filename)
	{
		if ($filename === '' || basename($filename) !== $filename || strpos($filename, '\\') !== false) { return ''; }
		$path = realpath($directory.'/'.$filename);
		if ($path === false || !is_readable($path) || strpos(str_replace('\\', '/', $path), rtrim(str_replace('\\', '/', $directory), '/').'/') !== 0) { return ''; }
		$size = @getimagesize($path);
		return is_array($size) && in_array((int) $size[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG), true) ? $path : '';
	}
}
