<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvreportdcmeasure.class.php
 * \ingroup		powerplantpv
 * \brief		Generated report DC measure snapshot object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportgeneratedbase.class.php');

/**
 * Generated report DC measure snapshot object.
 */
class PowerPlantPVReportDcMeasure extends PowerPlantPVReportGeneratedBase
{
	public $element = 'powerplantpv_report_dc_measure';
	public $table_element = 'powerplantpv_report_dc_measure';
	public $picto = 'fa-bolt';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'fk_report' => array('type' => 'integer:PowerPlantPVReport:powerplantpv/class/powerplantpvreport.class.php', 'label' => 'PowerPlantPVReport', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1),
		'fk_report_section' => array('type' => 'integer:PowerPlantPVReportSection:powerplantpv/class/powerplantpvreportsection.class.php', 'label' => 'PowerPlantPVReportSection', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1),
		'fk_report_powerplant' => array('type' => 'integer:PowerPlantPVReportPowerPlant:powerplantpv/class/powerplantpvreportpowerplant.class.php', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'fk_report_equipment' => array('type' => 'integer:PowerPlantPVReportEquipment:powerplantpv/class/powerplantpvreportequipment.class.php', 'label' => 'PowerPlantPVEquipment', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'fk_powerplant' => array('type' => 'integer', 'label' => 'PowerPlant', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'fk_inverter' => array('type' => 'integer', 'label' => 'PowerPlantPVInverter', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'inverter_ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1),
		'inverter_label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => 1),
		'inverter_serial' => array('type' => 'varchar(128)', 'label' => 'SerialNumber', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => 1),
		'mppt_number' => array('type' => 'integer', 'label' => 'PowerPlantPVMPPT', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 1),
		'pv_input_number' => array('type' => 'integer', 'label' => 'PowerPlantPVPVInput', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 1),
		'string_ref' => array('type' => 'varchar(128)', 'label' => 'PowerPlantPVStringRef', 'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 1),
		'is_connected' => array('type' => 'smallint', 'label' => 'PowerPlantPVPVInputConnected', 'enabled' => 1, 'position' => 130, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'open_circuit_voltage' => array('type' => 'double(24,8)', 'label' => 'PowerPlantPVOpenCircuitVoltage', 'enabled' => 1, 'position' => 140, 'notnull' => 0, 'visible' => 1),
		'polarity_checked' => array('type' => 'smallint', 'label' => 'PowerPlantPVPolarityChecked', 'enabled' => 1, 'position' => 150, 'notnull' => 1, 'visible' => 1, 'default' => -1),
		'insulation_status' => array('type' => 'varchar(32)', 'label' => 'PowerPlantPVInsulationStatus', 'enabled' => 1, 'position' => 160, 'notnull' => 0, 'visible' => 1),
		'insulation_positive_to_ground' => array('type' => 'double(24,8)', 'label' => 'PowerPlantPVInsulationPositiveToGround', 'enabled' => 1, 'position' => 170, 'notnull' => 0, 'visible' => 1),
		'insulation_negative_to_ground' => array('type' => 'double(24,8)', 'label' => 'PowerPlantPVInsulationNegativeToGround', 'enabled' => 1, 'position' => 180, 'notnull' => 0, 'visible' => 1),
		'positive_to_ground_voltage' => array('type' => 'double(24,8)', 'label' => 'PowerPlantPVPositiveToGroundVoltage', 'enabled' => 1, 'position' => 181, 'notnull' => 0, 'visible' => 1),
		'negative_to_ground_voltage' => array('type' => 'double(24,8)', 'label' => 'PowerPlantPVNegativeToGroundVoltage', 'enabled' => 1, 'position' => 182, 'notnull' => 0, 'visible' => 1),
		'observation' => array('type' => 'text', 'label' => 'Observation', 'enabled' => 1, 'position' => 190, 'notnull' => 0, 'visible' => 1),
		'stable_key' => array('type' => 'varchar(255)', 'label' => 'Code', 'enabled' => 1, 'position' => 200, 'notnull' => 1, 'visible' => 1),
		'inverter_marker' => array('type' => 'varchar(32)', 'label' => 'PowerPlantPVEquipmentMarker', 'enabled' => 1, 'position' => 91, 'notnull' => 0, 'visible' => 1),
		'inverter_identification' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVEquipmentIdentification', 'enabled' => 1, 'position' => 92, 'notnull' => 0, 'visible' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 210, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => 0, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $fk_report;
	public $fk_report_section;
	public $fk_report_powerplant;
	public $fk_report_equipment;
	public $fk_powerplant;
	public $fk_inverter;
	public $inverter_ref;
	public $inverter_label;
	public $inverter_serial;
	public $mppt_number;
	public $pv_input_number;
	public $string_ref;
	public $is_connected;
	public $open_circuit_voltage;
	public $polarity_checked;
	public $insulation_status;
	public $insulation_positive_to_ground;
	public $insulation_negative_to_ground;
	public $positive_to_ground_voltage;
	public $negative_to_ground_voltage;
	public $observation;
	public $stable_key;
	public $inverter_marker;
	public $inverter_identification;
	public $position;

	/**
	 * Find finalized reports with recorded DC measures for the selected plant.
	 *
	 * @param int $powerplantId Plant id
	 * @param list<int> $interventionIds Interventions already accessible to the user
	 * @return array<int,true>|int Intervention IDs or -1 on database failure
	 */
	public function fetchHistoryInterventions(int $powerplantId, array $interventionIds)
	{
		if ($powerplantId <= 0 || !$interventionIds) {
			return array();
		}
		$prefix = $this->db->prefix();
		$sql = "SELECT DISTINCT r.fk_fichinter";
		$sql .= " FROM ".$prefix."powerplantpv_report as r";
		$sql .= " INNER JOIN ".$prefix."powerplantpv_report_powerplant as p ON p.fk_report = r.rowid AND p.entity = r.entity";
		$sql .= " INNER JOIN ".$prefix.$this->table_element." as m ON m.fk_report = r.rowid AND m.entity = r.entity";
		$sql .= " WHERE r.status = '".PowerPlantPVReport::STATUS_SAVED."'";
		$sql .= " AND r.entity IN (".$this->db->sanitize(getEntity('fichinter')).")";
		$sql .= " AND r.fk_fichinter IN (".$this->db->sanitize(implode(',', $interventionIds)).")";
		$sql .= " AND p.fk_powerplant = ".$powerplantId;
		$sql .= " AND (m.fk_powerplant = p.fk_powerplant OR (COALESCE(m.fk_powerplant, 0) = 0";
		$sql .= " AND (m.fk_report_powerplant = p.rowid OR (COALESCE(m.fk_report_powerplant, 0) = 0";
		$sql .= " AND NOT EXISTS (SELECT other.rowid FROM ".$prefix."powerplantpv_report_powerplant as other";
		$sql .= " WHERE other.entity = p.entity AND other.fk_report = p.fk_report AND other.rowid <> p.rowid)))))";
		$sql .= " AND (m.open_circuit_voltage IS NOT NULL OR m.polarity_checked >= 0";
		$sql .= " OR m.insulation_status IN ('valid', 'observation', 'not_applicable')";
		$sql .= " OR m.insulation_positive_to_ground IS NOT NULL OR m.insulation_negative_to_ground IS NOT NULL";
		$sql .= " OR m.positive_to_ground_voltage IS NOT NULL OR m.negative_to_ground_voltage IS NOT NULL";
		$sql .= " OR (m.observation IS NOT NULL AND m.observation <> ''))";
		$result = $this->db->query($sql);
		if (!$result) {
			$this->setError($this->db->lasterror());
			return -1;
		}
		$history = array();
		while (is_object($row = $this->db->fetch_object($result))) {
			$history[(int) $row->fk_fichinter] = true;
		}
		$this->db->free($result);
		return $history;
	}
	/** Whether this connected input has an older value that can fill an empty voltage. */
	public function hasLegacyPoleVoltages(): bool
	{
		return (int) $this->is_connected === 1 && (
			($this->positive_to_ground_voltage === null && is_numeric($this->insulation_positive_to_ground))
			|| ($this->negative_to_ground_voltage === null && is_numeric($this->insulation_negative_to_ground))
		);
	}

	/** Reclassify numbers explicitly confirmed to have been measured in volts. */
	public function adoptLegacyPoleVoltages(): void
	{
		if (!$this->hasLegacyPoleVoltages()) {
			return;
		}
		if ($this->positive_to_ground_voltage === null && is_numeric($this->insulation_positive_to_ground)) {
			$this->positive_to_ground_voltage = $this->insulation_positive_to_ground;
			$this->insulation_positive_to_ground = null;
		}
		if ($this->negative_to_ground_voltage === null && is_numeric($this->insulation_negative_to_ground)) {
			$this->negative_to_ground_voltage = $this->insulation_negative_to_ground;
			$this->insulation_negative_to_ground = null;
		}
	}

	/**
	 * Fetch one measure by report and stable key.
	 *
	 * @param	int		$reportId	Report id
	 * @param	string	$stableKey	Stable key
	 * @return	int					>0 if OK, 0 if not found, <0 on error
	 */
	public function fetchByStableKey($reportId, $stableKey)
	{
		$sql = "SELECT ".$this->getSelectFieldList();
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.fk_report = ".((int) $reportId);
		$sql .= " AND t.stable_key = '".$this->db->escape((string) $stableKey)."'";

		$rows = $this->fetchRowsFromSql($sql);
		if (!is_array($rows)) {
			return -1;
		}
		if (empty($rows[0])) {
			return 0;
		}
		foreach (array_keys($this->fields) as $field) {
			$this->{$field} = $rows[0]->{$field};
		}
		$this->id = (int) $this->rowid;

		return 1;
	}
}
