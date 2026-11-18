<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
require_once __DIR__.'/powerplantpvreportpresentation.class.php';

/** Generate a separate native intervention attachment from frozen PV module serials. */
class PowerPlantPVReportSerialAnnex
{
	/** @var string */
	public $error = '';
	/** @var string Generated basename */
	public $filename = '';

	/**
	 * The entry point checks functional permissions, owner access, CSRF and locking.
	 * @param object $intervention Loaded native intervention
	 * @param array<string,mixed> $tree Loaded frozen report tree
	 * @return int 1 on success, -1 on failure
	 */
	public function generate($intervention, $tree)
	{
		global $conf, $db, $user, $langs;
		$langs->loadLangs(array('main', 'products', 'powerplantpv@powerplantpv'));
		$entity = (int) $intervention->entity;
		$base = $conf->ficheinter->multidir_output[$entity] ?? null;
		if (!is_string($base) || trim($base) === '' || !is_dir($base)) {
			$this->error = 'PowerPlantPVDocumentDirectoryUnavailable';
			return -1;
		}
		$dir = getMultidirOutput($intervention, 'ficheinter', 1);
		if (!is_string($dir) || $dir === '' || strpos($dir, 'error-') === 0 || is_link($dir)) {
			$this->error = 'PowerPlantPVDocumentDirectoryUnavailable';
			return -1;
		}
		$rows = array();
		foreach ($tree['powerplants'] ?? array() as $plant) {
			$snapshot = json_decode((string) $plant->technical_snapshot, true);
			// Older snapshots cannot be completed using today's inventory.
			if (!is_array($snapshot) || !array_key_exists('serial_inventory', $snapshot)) {
				$this->error = 'PowerPlantPVSerialAnnexUnavailable';
				return -1;
			}
			foreach ($snapshot['serial_inventory'] as $row) {
				$rows[] = array((string) $intervention->ref, (string) $plant->powerplant_ref, (string) $row['product_ref'], (string) $row['product_label'], (string) $row['serial']);
			}
		}
		if (!$rows) {
			$this->error = 'PowerPlantPVSerialAnnexUnavailable';
			return -1;
		}
		if (!realpath(DOL_DATA_ROOT) || strpos(str_replace('\\', '/', realpath($base)).'/', rtrim(str_replace('\\', '/', realpath(DOL_DATA_ROOT)), '/').'/') !== 0) {
			$this->error = 'PowerPlantPVDocumentDirectoryUnavailable'; return -1;
		}
		if (dol_mkdir($dir) < 0 || !realpath($dir) || strpos(str_replace('\\', '/', realpath($dir)).'/', rtrim(str_replace('\\', '/', realpath($base)), '/').'/') !== 0) {
			$this->error = 'PowerPlantPVDocumentDirectoryUnavailable';
			return -1;
		}
		$stream = fopen('php://temp', 'w+b');
		if (!$stream) { $this->error = 'ErrorFileNotFound'; return -1; }
		fwrite($stream, "\xEF\xBB\xBF");
		$header = array('Ref', 'PowerPlant', 'Product', 'Label', 'PowerPlantSerialNumber');
		fputcsv($stream, array_map(static function ($key) use ($langs) { return $langs->transnoentities($key); }, $header), ';', '"', '');
		foreach ($rows as $row) {
			if (fputcsv($stream, array_map(array('PowerPlantPVReportPresentation', 'csvText'), $row), ';', '"', '') === false) {
				fclose($stream); $this->error = 'ErrorFileNotFound'; return -1;
			}
		}
		rewind($stream);
		$content = stream_get_contents($stream);
		fclose($stream);
		if (!is_string($content)) { $this->error = 'ErrorFileNotFound'; return -1; }
		$this->filename = dol_sanitizeFileName($intervention->ref).'-annexe-series-modules-'.substr(hash('sha256', $content), 0, 12).'.csv';
		$path = $dir.'/'.$this->filename;
		if (is_link($path)) { $this->error = 'ErrorFileNotFound'; return -1; }
		// Repeated generation of the same snapshot is idempotent; never overwrite a document.
		if (!is_file($path)) {
			$file = fopen($path, 'xb');
			if (!$file) { $this->error = 'ErrorFileNotFound'; return -1; }
			$written = fwrite($file, $content);
			$closed = fclose($file);
			if ($written !== strlen($content) || !$closed) {
				dol_delete_file($path);
				$this->error = 'ErrorFileNotFound'; return -1;
			}
		} elseif (hash_file('sha256', $path) !== hash('sha256', $content)) {
			$this->error = 'ErrorFileAlreadyExists'; return -1;
		}
		// Native ECM index with the real owner, even when viewed from another entity.
		require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
		$relative = ltrim(str_replace('\\', '/', substr(realpath($dir), strlen(realpath(DOL_DATA_ROOT)))), '/');
		$ecm = new EcmFiles($db);
		// fetchAll() accepts native USF on the complete v20+ baseline. Numeric owner/source
		// filters avoid relying on the later entity argument of fetch(relativepath).
		$filter = '(t.entity:=:'.$entity.') AND (t.src_object_id:=:'.(int) $intervention->id.')';
		$found = $ecm->fetchAll('ASC', 't.rowid', 0, 0, $filter);
		if ($found < 0) { $this->error = $ecm->error; return -1; }
		foreach ($ecm->lines as $line) {
			if ((string) $line->filepath === $relative && (string) $line->filename === $this->filename && (string) $line->src_object_type === (string) $intervention->table_element) { return 1; }
		}
		$ecm->entity = $entity;
		$ecm->filepath = $relative;
		$ecm->filename = $this->filename;
		$ecm->label = md5_file($path);
		$ecm->gen_or_uploaded = 'generated';
		$ecm->src_object_type = $intervention->table_element;
		$ecm->src_object_id = (int) $intervention->id;
		if ($ecm->create($user) < 0) { $this->error = $ecm->error; return -1; }
		return 1;
	}
}
