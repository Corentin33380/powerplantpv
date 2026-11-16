<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Shared presentation of installed equipment and frozen report data. */
class PowerPlantPVReportPresentation
{
	/**
	 * Consolidate raw composition rows before locale-dependent formatting.
	 * @param array<int,array<string,mixed>> $components Raw rows from one plant
	 * @return array<int,array<string,mixed>> Consolidated rows
	 */
	public static function consolidate($components)
	{
		$groups = array();
		foreach ($components as $row) {
			$key = (int) ($row['fk_powerplant'] ?? 0).':'.(int) ($row['fk_product'] ?? 0);
			if ((int) ($row['fk_product'] ?? 0) <= 0) {
				$key .= ':line:'.(int) ($row['id'] ?? count($groups));
			}
			if (!isset($groups[$key])) {
				$groups[$key] = $row;
				$groups[$key]['qty'] = 0;
				$groups[$key]['serial_number'] = '';
				$groups[$key]['multiple_statuses'] = false;
			}
			$groups[$key]['qty'] += (float) ($row['qty'] ?? 0);
			if ((string) ($groups[$key]['status'] ?? '') !== (string) ($row['status'] ?? '')) {
				$groups[$key]['multiple_statuses'] = true;
			}
		}
		return array_values($groups);
	}

	/**
	 * Format an identity from frozen equipment or DC measure properties.
	 * @param object $row Equipment or measure
	 * @param bool $serial Include the serial number
	 * @param string $emptyLabel Localized fallback for an unattached manual line
	 * @return string Plain text, to escape at the destination
	 */
	public static function identity($row, $serial = true, $emptyLabel = '')
	{
		$marker = trim((string) ($row->equipment_marker ?? $row->inverter_marker ?? ''));
		$name = trim((string) ($row->equipment_identification ?? $row->inverter_identification ?? ''));
		if ($marker === '' && $name === '') {
			$name = trim((string) ($row->equipment_ref ?? $row->inverter_ref ?? '').' '.(string) ($row->equipment_label ?? $row->inverter_label ?? ''));
			// The source line distinguishes identical legacy equipment without inventing a physical marker.
			$id = (int) ($row->fk_powerplant_line ?? $row->fk_inverter ?? 0);
			if ($id > 0) {
				$name .= ' #'.$id;
			}
		}
		$parts = array_filter(array($marker, $name), static function ($part) { return $part !== ''; });
		$number = trim((string) ($row->serial_number ?? $row->inverter_serial ?? ''));
		if ($serial && $number !== '') {
			$parts[] = $number;
		}
		return $parts ? implode(' | ', $parts) : $emptyLabel;
	}

	/**
	 * Group by stable installed identity, never by a human-readable label.
	 * @param array<int,object> $measures Frozen DC measures
	 * @return array<string,array{identity:object,mppts:array<string,array<int,object>>}>
	 */
	public static function groupMeasures($measures)
	{
		usort($measures, static function ($a, $b) {
			$plant = (int) $a->fk_powerplant <=> (int) $b->fk_powerplant;
			$marker = strnatcasecmp((string) ($a->inverter_marker ?? ''), (string) ($b->inverter_marker ?? ''));
			return $plant ?: $marker ?: ((int) $a->fk_inverter <=> (int) $b->fk_inverter)
				?: ((int) $a->mppt_number <=> (int) $b->mppt_number)
				?: ((int) $a->pv_input_number <=> (int) $b->pv_input_number)
				?: ((int) $a->position <=> (int) $b->position);
		});
		$groups = array();
		foreach ($measures as $row) {
			$source = (int) $row->fk_inverter;
			$key = (int) $row->fk_powerplant.':'.($source > 0 ? 'line:'.$source : 'manual:'.(string) $row->stable_key);
			$mppt = $row->mppt_number === null ? '' : (string) $row->mppt_number;
			if (!isset($groups[$key])) {
				$groups[$key] = array('identity' => $row, 'mppts' => array());
			}
			$groups[$key]['mppts'][$mppt][] = $row;
		}
		return $groups;
	}

	/**
	 * Prevent spreadsheet formula interpretation without losing serial leading zeroes.
	 * @param string $value Original cell
	 * @return string CSV text cell
	 */
	public static function csvText($value)
	{
		return preg_match('/^[\s\x00-\x20]*[=+@-]/u', $value) || preg_match('/^\d/', $value) ? "'".$value : $value;
	}
}
