<?php
/* Copyright (C) 2026		Pierre Ardoin				<developpeur@lesmetiersdubatiment.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file		class/powerplantpvinterventionnature.class.php
 * \ingroup		powerplantpv
 * \brief		Intervention nature configuration object.
 */

dol_include_once('/powerplantpv/class/powerplantpvreportconfigbase.class.php');

/**
 * Intervention nature configuration object.
 */
class PowerPlantPVInterventionNature extends PowerPlantPVReportConfigBase
{
	public $element = 'powerplantpv_intervention_nature';
	public $table_element = 'c_powerplantpv_intervention_nature';

	/**
	 * @var array<string,array<string,mixed>> Fields
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => -2),
		'code' => array('type' => 'varchar(64)', 'label' => 'Code', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'searchall' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'searchall' => 1),
		'label_en' => array('type' => 'varchar(255)', 'label' => 'PowerPlantPVEnglishLabel', 'enabled' => 1, 'position' => 21, 'notnull' => 0, 'visible' => 1),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'description_en' => array('type' => 'text', 'label' => 'PowerPlantPVEnglishDescription', 'enabled' => 1, 'position' => 31, 'notnull' => 0, 'visible' => 1),
		'fk_report_template' => array('type' => 'integer:PowerPlantPVReportTemplate:powerplantpv/class/powerplantpvreporttemplate.class.php', 'label' => 'PowerPlantPVReportTemplate', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'model_pdf' => array('type' => 'varchar(255)', 'label' => 'Model', 'enabled' => 1, 'position' => 42, 'notnull' => 0, 'visible' => 1),
		'report_template_code' => array('type' => 'varchar(64)', 'label' => 'PowerPlantPVReportTemplateCode', 'enabled' => 1, 'position' => 41, 'notnull' => 0, 'visible' => -2),
		'is_maintenance' => array('type' => 'smallint', 'label' => 'PowerPlantPVIsMaintenance', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'is_preventive' => array('type' => 'smallint', 'label' => 'PowerPlantPVIsPreventive', 'enabled' => 1, 'position' => 60, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'requires_report' => array('type' => 'smallint', 'label' => 'PowerPlantPVRequiresReport', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'requires_signature' => array('type' => 'smallint', 'label' => 'PowerPlantPVRequiresSignature', 'enabled' => 1, 'position' => 80, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'active' => array('type' => 'smallint', 'label' => 'Status', 'enabled' => 1, 'position' => 90, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 100, 'notnull' => 1, 'visible' => 1, 'default' => 0),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => 0, 'visible' => -2),
	);

	public $code;
	public $label;
	public $label_en;
	public $description;
	public $description_en;
	public $fk_report_template;
	public $report_template_code;
	public $model_pdf;
	public $is_maintenance;
	public $is_preventive;
	public $requires_report;
	public $requires_signature;
	public $active;
	public $position;

	/**
	 * Write a new dictionary row to the entity chosen by the native dictionary settings.
	 * @param User $user Author
	 * @param int<0,1> $notrigger Skip object triggers
	 * @return int New row id or negative on error
	 */
	public function create(User $user, $notrigger = 0)
	{
		$this->entity = (int) getEntity($this->table_element, 0);
		return parent::create($user, $notrigger);
	}

	/**
	 * Load an intervention nature visible through the native dictionary sharing settings.
	 * @param int $id Dictionary row ID
	 * @param string $ref Reference
	 * @return int Native fetch result
	 */
	public function fetch($id, $ref = '')
	{
		if ((int) $id <= 0) {
			return 0;
		}
		return $this->fetchCommon($id, $ref, ' AND t.entity IN ('.$this->db->sanitize(getEntity($this->table_element)).')', 1);
	}

	/**
	 * Validate object before persistence.
	 *
	 * @return	int		1 if OK, <0 if KO
	 */
	protected function validateObject()
	{
		if ($this->validateCodeField('code') < 0) {
			return -1;
		}
		if ($this->validateRequiredString('label', 'PowerPlantPVInterventionNatureLabelRequired') < 0) {
			return -1;
		}
		if ((string) $this->model_pdf !== '') {
			$models = self::getPdfModels($this->db);
			if (!isset($models[$this->model_pdf])) {
				$this->error = 'PowerPlantPVInvalidInterventionPdfModel';
				return -1;
			}
		}
		$this->is_maintenance = (int) $this->is_maintenance;
		$this->is_preventive = (int) $this->is_preventive;
		$this->requires_report = (int) $this->requires_report;
		$this->requires_signature = (int) $this->requires_signature;
		$this->active = (int) $this->active;
		$this->position = (int) $this->position;

		return 1;
	}

	/**
	 * Return active native intervention PDF models compatible with a nature.
	 * @param DoliDB $db Database
	 * @return array<string,string> Model IDs and labels
	 */
	public static function getPdfModels($db): array
	{
		require_once DOL_DOCUMENT_ROOT.'/core/modules/fichinter/modules_fichinter.php';
		$models = ModelePDFFicheinter::liste_modeles($db);
		if (!is_array($models)) {
			return array();
		}
		foreach (array_keys($models) as $model) {
			if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', (string) $model) || strpos((string) $model, 'doc_generic_') === 0) {
				unset($models[$model]);
			}
		}
		return $models;
	}

	/**
	 * Resolve the native PDF model for an intervention already authorized by the caller.
	 * @return ?string Model name, empty when unmapped, null on read failure
	 */
	public function configuredPdfModel(Fichinter $intervention): ?string
	{
		if ((int) $intervention->id <= 0) {
			return null;
		}
		$sql = "SELECT n.model_pdf FROM ".$this->db->prefix()."fichinter AS fi";
		$sql .= " LEFT JOIN ".$this->db->prefix()."fichinter_extrafields AS ef ON ef.fk_object = fi.rowid";
		$sql .= " LEFT JOIN ".$this->db->prefix().$this->table_element." AS n";
		$sql .= " ON n.rowid = ef.powerplantpv_intervention_nature AND n.entity IN (".$this->db->sanitize(getEntity($this->table_element)).")";
		// A shared intervention may only read a dictionary row of its own entity
		// while it is being accessed from another entity.
		$sql .= " AND (n.entity = fi.entity OR fi.entity IN (".$this->db->sanitize(getEntity('fichinter', 0))."))";
		$sql .= " WHERE fi.rowid = ".((int) $intervention->id);
		// The v20 create trigger can receive an object without its persisted entity.
		$sql .= (int) $intervention->entity > 0
			? " AND fi.entity = ".((int) $intervention->entity)
			: " AND fi.entity IN (".$this->db->sanitize(getEntity('fichinter', 0)).")";
		$result = $this->db->query($sql);
		if (!$result) {
			return null;
		}
		$row = $this->db->fetch_object($result);
		$this->db->free($result);

		return is_object($row) ? (string) $row->model_pdf : null;
	}

	/**
	 * Apply the nature's native PDF model to the intervention.
	 * @return int 1 when configured, 0 when unmapped, -1 on failure
	 */
	public function applyPdfModel(Fichinter $intervention, User $user, string $model): int
	{
		if ($model === '') {
			return 0;
		}
		if ((string) $intervention->model_pdf !== $model && $intervention->setDocModel($user, $model) <= 0) {
			return -1;
		}

		return 1;
	}

	/**
	 * Build additional SQL filters for fetchAll().
	 *
	 * @param	array<string,mixed>	$filters	Filters
	 * @return	string							SQL fragment
	 */
	protected function buildFetchAllWhere($filters)
	{
		$sql = '';
		if (!empty($filters['search'])) {
			$search = $this->db->escape((string) $filters['search']);
			$sql .= " AND (t.code LIKE '%".$search."%' OR t.label LIKE '%".$search."%')";
		}
		if (isset($filters['requires_report']) && $filters['requires_report'] !== '') {
			$sql .= " AND t.requires_report = ".((int) $filters['requires_report']);
		}

		return $sql;
	}
}
