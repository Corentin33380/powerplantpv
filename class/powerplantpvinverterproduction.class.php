<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Extend draft report trees without moving historic plant readings to an inverter. */
class PowerPlantPVInverterProduction
{
	/**
	 * @param array<string,mixed> $tree Frozen report tree
	 * @return array<string,mixed> Tree with independent inverter reading sections
	 */
	public static function expand($tree)
	{
		if (empty($tree['report']) || (string) $tree['report']->status !== 'draft') { return $tree; }
		$existing = array();
		foreach ($tree['sections'] as $row) { $existing[(string) $row['section']->occurrence_key] = true; }
		$sections = array();
		foreach ($tree['sections'] as $row) {
			$section = $row['section'];
			if ((string) $section->section_code !== 'PRODUCTION_READING' || (int) $section->fk_report_equipment > 0) { $sections[] = $row; continue; }
			$templates = array();
			foreach ($row['fields'] as $field) {
				if (in_array((string) $field->field_code, array('INVERTER_PRODUCTION', 'INVERTER_PRODUCTION_N_MINUS_1'), true)) { $templates[] = $field; }
			}
			if (!$templates) { $sections[] = $row; continue; }
			$inverters = array_filter($tree['equipment'], static function ($equipment) use ($section) {
				return (string) $equipment->equipment_type === 'INVERTER' && (int) $equipment->fk_report_powerplant === (int) $section->fk_report_powerplant;
			});
			if (!$inverters) { $sections[] = $row; continue; }
			foreach ($row['fields'] as $index => $field) {
				if (!in_array((string) $field->field_code, array('INVERTER_PRODUCTION', 'INVERTER_PRODUCTION_N_MINUS_1'), true)) { continue; }
				$legacy = clone $field;
				$legacy->readonly = 1; $legacy->is_required = 0;
				$legacy->visible_form = $legacy->visible_pdf = ($field->value_number !== null && (string) $field->value_number !== '' ? 1 : 0);
				$legacy->field_label = preg_replace('/ \(ancien relevé global\)$/u', '', (string) $field->field_label).' (ancien relevé global)';
				$legacy->field_label_en = preg_replace('/ \(legacy plant reading\)$/', '', (string) $field->field_label_en).' (legacy plant reading)';
				$row['fields'][$index] = $legacy;
			}
			$sections[] = $row;
			foreach ($inverters as $equipment) {
				$sourceId = (int) ($equipment->fk_powerplant_line ?? 0);
				if ($sourceId <= 0) { $sourceId = (int) $equipment->fk_source_equipment; }
				$key = (string) $section->occurrence_key.':production:inverter:'.$sourceId;
				if ($sourceId <= 0 || isset($existing[$key])) { continue; }
				$newSection = clone $section;
				$newSection->id = $newSection->rowid = 0;
				$newSection->occurrence_key = $key;
				$newSection->fk_report_equipment = (int) $equipment->id;
				$newSection->scope_type = 'inverter';
				$newSection->equipment_type = 'INVERTER';
				$newSection->repeat_mode = 'once_per_equipment';
				$fields = array();
				foreach ($templates as $template) {
					$field = clone $template;
					$field->id = $field->rowid = $field->fk_report_section = 0;
					$field->fk_report_equipment = (int) $equipment->id;
					$field->stable_key = $key.':field:'.(string) $field->field_code;
					$field->scope_type = 'inverter';
					$field->visible_form = $field->visible_pdf = 1;
					$field->field_label = preg_replace('/ \(ancien relevé global\)$/u', '', (string) $field->field_label);
					$field->field_label_en = preg_replace('/ \(legacy plant reading\)$/', '', (string) $field->field_label_en);
					$field->value_number = $field->value_text = $field->value_date = null;
					$field->readonly = substr((string) $field->field_code, -10) === '_N_MINUS_1' ? 1 : 0;
					$fields[] = $field;
				}
				$sections[] = array('section' => $newSection, 'fields' => $fields, 'dc_measures' => array(), 'powerplant' => $row['powerplant'], 'equipment' => $equipment);
				$existing[$key] = true;
			}
		}
		$tree['sections'] = $sections;
		return $tree;
	}
}
