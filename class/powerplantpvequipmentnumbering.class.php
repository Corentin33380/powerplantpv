<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
require_once DOL_DOCUMENT_ROOT.'/core/class/commonnumrefgenerator.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

/** Native counter scoped to installed equipment, never to the product catalogue. */
class PowerPlantPVEquipmentNumbering extends CommonNumRefGenerator
{
	/** @var string */
	public $name = 'advanced';
	/** @var string */
	public $error = '';
	/** @var DoliDB */
	protected $db;
	/** @var string */
	private $kind;

	/** @param DoliDB $db Database @param string $kind inverter or string */
	public function __construct($db, $kind)
	{
		$this->db = $db;
		$this->kind = $kind;
	}

	/** @return string Configured entity-specific mask */
	public function getMask()
	{
		return getDolGlobalString('POWERPLANTPV_'.strtoupper($this->kind).'_MASK', $this->kind === 'inverter' ? 'OND{00}' : 'PV{00}');
	}

	/** @param Translate $langs Language @return string */
	public function info($langs) { return $langs->trans('PowerPlantPVEquipmentMaskHelp'); }

	/** @param object $object Scope @return bool */
	public function canBeActivated($object) { return $this->getNextValue($object) !== ''; }

	/** @return string Example for an empty scope; does not consume a number */
	public function getExample()
	{
		global $conf;
		return $this->getNextValue((object) array('id' => 0, 'entity' => (int) $conf->entity, 'fk_inverter' => 0));
	}

	/**
	 * The caller holds the parent plant lock until its insert commits.
	 * @param object $object Scope with id (plant), entity and fk_inverter (strings)
	 * @param string|null $mask Mask to validate without changing settings
	 * @return string Next reference, empty on error (see error)
	 */
	public function getNextValue($object, $mask = null)
	{
		global $conf;
		$this->error = '';
		$mask = $mask === null ? $this->getMask() : trim($mask);
		// Free literal prefix/suffix and exactly one native numeric counter.
		if (!in_array($this->kind, array('inverter', 'string'), true) || !preg_match('/^[^{}]*\{0+\}[^{}]*$/u', $mask) || mb_strlen($mask, 'UTF-8') > 32) {
			$this->error = 'ErrorBadMask';
			return '';
		}
		$table = $this->kind === 'inverter' ? 'powerplantpv_powerplantcomp' : 'powerplantpv_equipment_string';
		$field = $this->kind === 'inverter' ? 'equipment_marker' : 'string_ref';
		$where = ' AND fk_powerplant = '.(int) $object->id.' AND entity = '.(int) $object->entity;
		if ($this->kind === 'string') { $where .= ' AND fk_inverter = '.(int) $object->fk_inverter; }
		$rows = $this->db->query('SELECT COUNT(*) as nb FROM '.$this->db->prefix().$table.' WHERE '.$field." IS NOT NULL AND ".$field." <> ''".$where);
		$row = $rows ? $this->db->fetch_object($rows) : null;
		if (!$row) { $this->error = $this->db->lasterror(); return ''; }
		// The native offset -1 yields zero for the first chain only. Subsequent calls
		// use the unmodified mask, so PV00 advances to PV01 instead of repeating zero.
		if ($this->kind === 'string' && (int) $row->nb === 0) { $mask = preg_replace('/\{(0+)\}/', '{$1+-1}', $mask); }
		// Core v20-v25 gates short equipment counters behind this global setting.
		// Enable it only for this call and restore its exact prior state; no setting
		// for invoices or other modules is persisted or changed by this model.
		$hadShort = isset($conf->global->MAIN_COUNTER_WITH_LESS_3_DIGITS);
		$short = $hadShort ? $conf->global->MAIN_COUNTER_WITH_LESS_3_DIGITS : null;
		$conf->global->MAIN_COUNTER_WITH_LESS_3_DIGITS = '1';
		try {
			$value = get_next_value($this->db, $mask, $table, $field, $where, '', '', 'next', false);
		} finally {
			if ($hadShort) { $conf->global->MAIN_COUNTER_WITH_LESS_3_DIGITS = $short; }
			else { unset($conf->global->MAIN_COUNTER_WITH_LESS_3_DIGITS); }
		}
		if ($value === '' || strpos($value, 'Error') === 0 || strpos($value, '{') !== false || mb_strlen($value, 'UTF-8') > 32) {
			$this->error = strpos($value, 'Error') === 0 ? $value : 'ErrorBadMask';
			return '';
		}
		return $value;
	}
}
