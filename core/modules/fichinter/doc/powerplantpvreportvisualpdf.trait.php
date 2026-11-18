<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/** Visual, data-driven maintenance layout. TCPDF remains responsible for text and pagination. */
trait PowerPlantPVReportVisualPdf
{
	/** @var string */
	protected $visualReportTitle = '';
	/** @var array<int,bool> */
	protected $visualHeaders = array();
	/** @var array<string,int> Inverter numbers used when product data is hidden */
	protected $visualInverterNumbers = array();

	/** @param TCPDF|TCPDI $pdf @return void */
	protected function visualNewPage(&$pdf)
	{
		if ($pdf->GetY() <= 34) { return; }
		$this->completePageFooter($pdf, 1);
		$pdf->AddPage();
		$this->reserveFooterSpace($pdf);
	}

	/** @param TCPDF|TCPDI $pdf @param CommonObject $object @param array<string,mixed> $dataset @param Translate $outputlangs @return void */
	protected function renderReport(&$pdf, $object, $dataset, $outputlangs)
	{
		if (empty($dataset['report_found'])) { $this->renderEmptyBlock($pdf, $outputlangs->transnoentities('PowerPlantPVReportNoSnapshot')); return; }
		$plants = array_values($dataset['powerplants']);
		foreach ($plants as $i => $plant) {
			if ($i > 0) { $this->visualNewPage($pdf); }
			$this->visualCover($pdf, $plant, $dataset, $outputlangs);
		}
		$general = array_filter($dataset['general_sections'], function ($row) {
			return !empty($row['section']->visible_pdf) && in_array((string) $row['section']->section_code, array('GENERAL_INFORMATION', 'GENERAL_OBSERVATIONS'), true);
		});
		if ($general) {
			$this->visualNewPage($pdf);
			foreach ($general as $row) {
				foreach ($this->filterVisibleFields($row['fields']) as $field) {
					if (PowerPlantPVReportPdfDataset::isFileField($field)) { $this->renderFieldFiles($pdf, $field, $dataset, $outputlangs); continue; }
					if (PowerPlantPVReportPdfDataset::isSignatureField($field)) { $this->renderSignatureField($pdf, $field, $dataset, $outputlangs); continue; }
					$value = PowerPlantPVReportPdfDataset::formatFieldValue($field, $outputlangs);
					if (trim(dol_string_nohtmltag($value)) === '') { continue; }
					$this->visualNoteWithConclusion($pdf, PowerPlantPVReportPdfDataset::localizedProperty($field, 'field_label', $outputlangs), $value, $outputlangs);
				}
			}
		}
		$controls = array(); $inverters = array(); $roof = array(); $production = array(); $dc = array(); $other = array();
		$all = $dataset['general_sections'];
		foreach ($dataset['sections_by_powerplant'] as $rows) { $all = array_merge($all, $rows); }
		foreach ($all as $row) {
			if (empty($row['section']->visible_pdf)) { continue; }
			$code = (string) $row['section']->section_code;
			if ($code === 'ELECTRICAL_BOX') { $controls[] = $row; }
			elseif ($code === 'INVERTER') { $inverters[] = $row; }
			elseif ($code === 'ROOF') { $roof[] = $row; }
			elseif ($code === 'PRODUCTION_READING') { $production[] = $row; }
			elseif ($code === 'DC_ELECTRICAL_MEASURE') { $dc[] = $row; }
			elseif (!in_array($code, array('GENERAL_INFORMATION', 'GENERAL_OBSERVATIONS', 'CUSTOMER_SIGNATURE'), true) && !$this->isPowerplantTechnicalSummarySectionRow($row)) { $other[] = $row; }
		}
		if ($controls || $inverters || $other) {
			$this->visualNewPage($pdf);
			foreach ($controls as $row) {
				$this->renderSectionTitle($pdf, PowerPlantPVReportPdfDataset::localizedProperty($row['section'], 'section_label', $outputlangs), 2);
				$this->visualPanels($pdf, array($this->visualSectionPanel($row, $outputlangs)), false);
				$this->visualSectionExtras($pdf, $row, $dataset, $outputlangs);
			}
			if ($inverters) {
				$this->renderSectionTitle($pdf, $outputlangs->transnoentities('PowerPlantPDFInverters'), 2);
				$panels = array();
				foreach ($inverters as $row) { $panels[] = $this->visualSectionPanel($row, $outputlangs); }
				$this->visualPanels($pdf, $panels, true);
				foreach ($inverters as $row) { $this->visualSectionExtras($pdf, $row, $dataset, $outputlangs); }
			}
			foreach ($other as $row) {
				$this->renderSectionTitle($pdf, PowerPlantPVReportPdfDataset::localizedProperty($row['section'], 'section_label', $outputlangs), 2);
				$this->visualPanels($pdf, array($this->visualSectionPanel($row, $outputlangs)), false);
				$this->visualSectionExtras($pdf, $row, $dataset, $outputlangs);
			}
		}
		if ($roof || $production) {
			$this->visualNewPage($pdf);
			foreach ($roof as $row) {
				$this->renderSectionTitle($pdf, PowerPlantPVReportPdfDataset::localizedProperty($row['section'], 'section_label', $outputlangs), 2);
				$this->visualPanels($pdf, array($this->visualSectionPanel($row, $outputlangs)), false);
				$this->visualSectionExtras($pdf, $row, $dataset, $outputlangs);
			}
			if ($production) { $this->renderSectionTitle($pdf, $outputlangs->transnoentities('PowerPlantPVVisualProduction'), 2); }
			foreach ($production as $row) { $this->visualReadings($pdf, $row, $dataset, $outputlangs); }
		}
		if ($dc) {
			$this->visualNewPage($pdf);
			$this->renderSectionTitle($pdf, $outputlangs->transnoentities('PowerPlantPVVisualDcMeasures'), 2);
			foreach ($dc as $row) {
				foreach (PowerPlantPVReportPresentation::groupMeasures(PowerPlantPVReportPresentation::visibleMeasures($row['dc_measures'], false)) as $group) { $this->visualDcChart($pdf, $group, $outputlangs); }
				$this->visualSectionExtras($pdf, $row, $dataset, $outputlangs);
			}
			foreach ($dc as $row) {
				foreach (PowerPlantPVReportPresentation::groupMeasures(PowerPlantPVReportPresentation::visibleMeasures($row['dc_measures'], false)) as $group) {
					$this->visualNewPage($pdf);
					$this->renderSectionTitle($pdf, $outputlangs->transnoentities('PowerPlantPVVisualDcAnnex'), 2);
					$this->visualDcAnnex($pdf, $group, $outputlangs);
				}
			}
		}
		// Optional client-facing layout: the equipment appendix stays available in the plant record.
		foreach ($this->visualOption('HIDE_EQUIPMENT') ? array() : $plants as $plant) {
			$this->visualNewPage($pdf);
			$this->renderSectionTitle($pdf, $outputlangs->transnoentities('PowerPlantPVVisualMaterialAnnex'), 2);
			$this->renderSectionTitle($pdf, $this->powerplantLabel($plant), 3);
			$snapshot = $this->resolvePowerplantTechnicalSummarySnapshot($plant, $outputlangs);
			foreach (($snapshot['sections'] ?? array()) as $section) {
				if (in_array((string) ($section['code'] ?? ''), array('site', 'connection', 'operation', 'material', 'composition', 'contacts'), true)) { $this->renderPowerplantTechnicalSummarySection($pdf, $section, $outputlangs); }
			}
			$url = dol_buildpath('/powerplantpv/powerplant_composition.php', 2).'?id='.(int) $plant->fk_powerplant;
			$this->writeHtml($pdf, '<p style="color:#64748b;">'.dol_escape_htmltag($outputlangs->transnoentities('PowerPlantPVVisualSerialAnnex')).' <a href="'.dol_escape_htmltag($url).'">'.dol_escape_htmltag($outputlangs->transnoentities('PowerPlantPVConsultPowerplant')).'</a></p>');
		}
		$this->visualSignatures($pdf, $object, $dataset, $outputlangs);
	}

	/**
	 * Read one visual PDF option of the current entity.
	 * @param string $name Suffix of POWERPLANTPV_REPORT_PDF_*
	 * @return bool True when enabled
	 */
	protected function visualOption($name)
	{
		return getDolGlobalInt('POWERPLANTPV_REPORT_PDF_'.$name) > 0;
	}

	/**
	 * Identity of an inverter for titles; without product data when the equipment is hidden.
	 * @param object $row Frozen equipment or DC measure
	 * @param bool $serial Include the serial number when the equipment is shown
	 * @return string Plain text
	 */
	protected function visualIdentity($row, $serial = false)
	{
		if (!$this->visualOption('HIDE_EQUIPMENT')) { return PowerPlantPVReportPresentation::identity($row, $serial); }
		$marker = trim((string) ($row->equipment_marker ?? $row->inverter_marker ?? ''));
		$name = trim((string) ($row->equipment_identification ?? $row->inverter_identification ?? ''));
		$parts = array_filter(array($marker, $name), static function ($part) { return $part !== ''; });
		if ($parts) { return implode(' | ', $parts); }
		// Legacy rows without marker: stable number in order of appearance, never the product label.
		$key = (int) ($row->fk_powerplant_line ?? $row->fk_inverter ?? 0);
		$key = $key > 0 ? 'line:'.$key : 'object:'.spl_object_id($row);
		if (!isset($this->visualInverterNumbers[$key])) { $this->visualInverterNumbers[$key] = count($this->visualInverterNumbers) + 1; }
		return $this->currentOutputLangs->transnoentities('PowerPlantPVVisualInverterNumber', $this->visualInverterNumbers[$key]);
	}

	/**
	 * Tell whether a yes/no answer was explicitly answered "No".
	 * @param object $field Frozen report field
	 * @return bool True for an explicit negative answer
	 */
	protected function visualIsNegativeAnswer($field)
	{
		return in_array((string) $field->field_type, array('yesno', 'boolean', 'checkbox'), true) && (string) $field->value_text !== '' && (int) $field->value_text === 0;
	}

	/** @param TCPDF|TCPDI $pdf @param PowerPlantPVReportPowerPlant $plant @param array<string,mixed> $dataset @param Translate $langs @return void */
	protected function visualCover(&$pdf, $plant, $dataset, $langs)
	{
		$rows = array_filter($dataset['sections_by_powerplant'][(int) $plant->id] ?? array(), function ($row) { return !empty($row['section']->visible_pdf); });
		$stats = PowerPlantPVReportPresentation::indicators($rows);
		$valid = 0; $applicable = 0; $dcValid = 0; $index = null; $inverterProduction = null; $vigilance = array();
		foreach ($rows as $row) {
			$prefix = !empty($row['equipment']) ? $this->visualIdentity($row['equipment'], false).' : ' : '';
			foreach ($this->filterVisibleFields($row['fields']) as $field) {
				if ((string) $field->field_type === 'conformity_so_valid_obs') {
					if ((string) $field->value_text !== 'not_applicable') { $applicable++; }
					if ((string) $field->value_text === 'valid') { $valid++; }
					if ((string) $field->value_text === 'observation') { $vigilance[] = $prefix.PowerPlantPVReportPdfDataset::localizedProperty($field, 'field_label', $langs); }
				} elseif ((string) ($row['section']->section_code ?? '') !== 'DC_ELECTRICAL_MEASURE' && in_array((string) $field->field_type, array('yesno', 'boolean', 'checkbox'), true) && (string) $field->value_text !== '') {
					// An explicit "No" is shown in orange in its panel, so it is also listed in the cover summary.
					$applicable++;
					if ($this->visualIsNegativeAnswer($field)) {
						$vigilance[] = $prefix.PowerPlantPVReportPdfDataset::localizedProperty($field, 'field_label', $langs).' : '.$langs->transnoentities('No');
					} else {
						$valid++;
					}
				}
				if ((string) $field->field_code === 'PRODUCTION_INDEX' && $field->value_number !== null && $field->value_number !== '') { $index = $field->value_number; }
				if ((string) $field->field_code === 'INVERTER_PRODUCTION' && $field->value_number !== null && $field->value_number !== '' && is_numeric($field->value_number)) { $inverterProduction = (float) $inverterProduction + (float) $field->value_number; }
			}
			foreach (($row['dc_measures'] ?? array()) as $measure) { if ((int) $measure->is_connected === 1 && (int) $measure->polarity_checked === 1 && (string) $measure->insulation_status === 'valid') { $dcValid++; } }
		}
		$snapshot = $this->resolvePowerplantTechnicalSummarySnapshot($plant, $langs); $facts = array();
		foreach (($snapshot['sections'] ?? array()) as $section) { foreach (($section['rows'] ?? array()) as $fact) { if (isset($fact['label_key'], $fact['value'])) { $facts[(string) $fact['label_key']] = (string) $fact['value']; } } }
		$missing = $langs->transnoentities('PowerPlantPVDataNotFilled');
		// Without a production meter index, the inverter readings of the plant are summed.
		$productionCard = $index !== null ? array(price($index, 0, $langs, 1, -1, 0), $langs->transnoentities('PowerPlantPVVisualProductionIndex'))
			: ($inverterProduction !== null ? array(price($inverterProduction, 0, $langs, 1, -1, 0), $langs->transnoentities('PowerPlantPVVisualInverterProduction')) : array($missing, $langs->transnoentities('PowerPlantPVVisualProductionIndex')));
		$cards = array(array($facts['PowerPlantPDFInstalledPower'] ?? $missing, $langs->transnoentities('PowerPlantInstalledPower')), array($valid.' / '.$applicable, $langs->transnoentities('PowerPlantPVVisualChecksConform')), array($dcValid.' / '.$stats['connected'], $langs->transnoentities('PowerPlantPVVisualStringsConform')), $productionCard);
		$this->visualKpis($pdf, $cards, 4, 18);
		$summary = $langs->transnoentities('PowerPlantPVVisualAnswerSummary', $stats['completed'], $stats['checks']);
		$heading = $vigilance ? $langs->transnoentities('PowerPlantPVVisualVigilance', count($vigilance)) : $langs->transnoentities('PowerPlantPVVisualAllConform');
		if ($vigilance) { $summary .= "\n".implode("\n", $vigilance); }
		$this->visualBanner($pdf, $heading, $summary, (bool) $vigilance);
		$this->visualIllustration($pdf, $plant, $langs);
		$this->renderSectionTitle($pdf, $langs->transnoentities('PowerPlantPVVisualClientPlant'), 2);
		$client = $dataset['thirdparty'];
		$left = array(array('label'=>$langs->transnoentities('Customer'), 'value'=>is_object($client) ? (string) $client->name : $missing), array('label'=>$langs->transnoentities('Address'), 'value'=>is_object($client) ? dol_format_address($client, 1, "\n", $langs) : $missing));
		$project = $dataset['project'] ?? null; if (is_object($project)) { $left[] = array('label'=>$langs->transnoentities('Project'), 'value'=>trim((string) $project->ref.' - '.(string) $project->title)); }
		$contract = $this->joinContractRefs($dataset['contracts']); if ($contract !== '') { $left[] = array('label'=>$langs->transnoentities('Contract'), 'value'=>$contract); }
		$left[] = array('label'=>$langs->transnoentities('Intervention'), 'value'=>PowerPlantPVReportPdfDataset::localizedProperty($dataset['report'], 'intervention_nature_label', $langs));
		$right = array();
		foreach (array('PowerPlantPDFReference', 'PowerPlantPDFAddress', 'PowerPlantPDFStatusPowerPlant', 'PowerPlantPDFCommissioningDate', 'PowerPlantPDFInstalledPower') as $key) { if (!empty($facts[$key])) { $right[] = array('label'=>$langs->transnoentities($key), 'value'=>$facts[$key]); } }
		$this->visualPanels($pdf, array(array('title'=>$langs->transnoentities('Customer'), 'rows'=>$left, 'info'=>true), array('title'=>(string) $plant->powerplant_label, 'rows'=>$right, 'info'=>true)), true);
		$connection = array();
		$connectionKeys = array('PowerPlantPDFPrmPdl', 'PowerPlantPDFConnectionType', 'PowerPlantPDFConnectionContractPower');
		if (!$this->visualOption('SHORT_CONNECTION')) { $connectionKeys = array_merge($connectionKeys, array('PowerPlantPDFEnedisCommissioningDate', 'PowerPlantPDFConnectionRequestNumber', 'PowerPlantPDFT0ObtentionDate')); }
		foreach ($connectionKeys as $key) { if (!empty($facts[$key])) { $connection[] = array('label'=>$langs->transnoentities($key),'value'=>$facts[$key]); } }
		if ($connection) { $this->visualPanels($pdf, array(array('title'=>$langs->transnoentities('PowerPlantPDFSectionConnection'), 'rows'=>$connection,'info'=>true)), false); }
		$this->renderSourceServices($pdf, $this->filterSourceServicesByPowerplant($dataset['source_services'], (int) $plant->id), $langs);
	}

	/** @param TCPDF|TCPDI $pdf @param array<int,array{0:string,1:string}> $cards @param int $columns @param float $height @return void */
	protected function visualKpis(&$pdf, $cards, $columns = 3, $height = 20)
	{
		$width = ($this->page_largeur - $this->marge_gauche - $this->marge_droite - ($columns - 1) * 3) / $columns;
		foreach (array_chunk($cards, $columns) as $line) {
			$this->ensureSpace($pdf, $height + 3); $y = $pdf->GetY();
			foreach ($line as $i => $card) {
				$x = $this->marge_gauche + $i * ($width + 3); $pdf->SetFillColor(232, 243, 255); $pdf->SetDrawColor(232, 243, 255); $pdf->RoundedRect($x, $y, $width, $height, 2, '1111', 'DF');
				$pdf->SetTextColor(24, 47, 79); $pdf->SetFont('', 'B', dol_strlen($card[0]) > 20 ? 10 : 14); $pdf->SetXY($x + 3, $y + 2); $pdf->MultiCell($width - 6, 6, $this->currentOutputLangs->convToOutputCharset($card[0]), 0, 'L');
				$pdf->SetFont('', '', 7); $pdf->SetTextColor(100, 116, 139); $pdf->SetXY($x + 3, $y + $height - ($height > 28 ? 15 : ($height > 20 ? 12 : 7))); $pdf->MultiCell($width - 6, 3, $this->currentOutputLangs->convToOutputCharset($card[1]), 0, 'L');
			}
			$pdf->SetY($y + $height + 3);
		}
		$pdf->SetFont('', '', $this->defaultFontSize); $pdf->SetTextColor(24, 47, 79);
	}

	/** @param TCPDF|TCPDI $pdf @param string $heading @param string $text @param bool $warning @return void */
	protected function visualBanner(&$pdf, $heading, $text, $warning)
	{
		$text = dol_trunc($text, 900); $pdf->SetFont('', '', 8); $width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$height = max(23, 12 + $pdf->getStringHeight($width - 22, $text)); $this->ensureSpace($pdf, $height + 3); $y = $pdf->GetY();
		$pdf->SetFillColor($warning ? 254 : 227, $warning ? 247 : 245, $warning ? 231 : 238); $pdf->SetDrawColor($warning ? 241 : 185, $warning ? 207 : 228, $warning ? 138 : 210); $pdf->RoundedRect($this->marge_gauche, $y, $width, $height, 2, '1111', 'DF');
		$pdf->SetFillColor($warning ? 218 : 18, $warning ? 148 : 128, $warning ? 0 : 92); $pdf->Circle($this->marge_gauche + 7, $y + 9, 3, 0, 360, 'F'); $pdf->SetTextColor(255, 255, 255); $pdf->SetFont('', 'B', 10); $pdf->SetXY($this->marge_gauche + 4, $y + 6); $pdf->Cell(6, 6, $warning ? '!' : 'i', 0, 0, 'C');
		$pdf->SetTextColor($warning ? 145 : 18, $warning ? 94 : 128, $warning ? 0 : 92); $pdf->SetFont('', 'B', 9); $pdf->SetXY($this->marge_gauche + 14, $y + 3); $pdf->MultiCell($width - 19, 4, $this->currentOutputLangs->convToOutputCharset($heading), 0, 'L');
		$pdf->SetTextColor(55, 65, 81); $pdf->SetFont('', '', 8); $pdf->SetXY($this->marge_gauche + 14, $y + 9); $pdf->MultiCell($width - 19, 4, $this->currentOutputLangs->convToOutputCharset($text), 0, 'L'); $pdf->SetY($y + $height + 3);
	}

	/** @param TCPDF|TCPDI $pdf @param PowerPlantPVReportPowerPlant $snapshot @param Translate $langs @return void */
	protected function visualIllustration(&$pdf, $snapshot, $langs)
	{
		global $user;
		if (!$user->hasRight('powerplantpv', 'powerplant', 'read')) { return; }
		$plant = new PowerPlant($this->db);
		if ($plant->fetch((int) $snapshot->fk_powerplant) <= 0 || (int) $plant->fk_report_illustration <= 0) { return; }
		if (!restrictedArea($user, 'powerplantpv', $plant, $plant->table_element.'&'.$plant->element, $plant->element, 'fk_soc', 'rowid', (int) ((int) $plant->status === PowerPlant::STATUS_DRAFT), 1)) { return; }
		$source = new PowerPlantPVReportIllustration($this->db); $files = $source->files($plant);
		if (!is_array($files) || !isset($files[(int) $plant->fk_report_illustration])) { $this->renderEmptyBlock($pdf, $langs->transnoentities('PowerPlantPVReportIllustrationInvalid')); return; }
		$path = $files[(int) $plant->fk_report_illustration]['path']; $size = @getimagesize($path);
		if (!$size) { return; }
		$width = $this->page_largeur - $this->marge_gauche - $this->marge_droite; $height = min(67, $width * $size[1] / $size[0]); $drawWidth = $height * $size[0] / $size[1];
		$this->ensureSpace($pdf, $height + 8); $y = $pdf->GetY(); $pdf->Image($path, $this->marge_gauche + ($width - $drawWidth) / 2, $y, $drawWidth, $height);
		$pdf->SetFont('', '', 7); $pdf->SetTextColor(100,116,139); $pdf->SetXY($this->marge_gauche, $y + $height + 1); $pdf->MultiCell($width, 3, $langs->convToOutputCharset($this->powerplantLabel($snapshot)), 0, 'C'); $pdf->SetY($y + $height + 7);
	}

	/** @param array<string,mixed> $row @param Translate $langs @return array<string,mixed> */
	protected function visualSectionPanel($row, $langs)
	{
		$equipment = $row['equipment'] ?? null; $title = is_object($equipment) ? $this->visualIdentity($equipment, false) : PowerPlantPVReportPdfDataset::localizedProperty($row['section'], 'section_label', $langs);
		$rows = array(); $power = '';
		foreach ($this->filterVisibleFields($row['fields']) as $field) {
			if (PowerPlantPVReportPdfDataset::isFileField($field) || PowerPlantPVReportPdfDataset::isSignatureField($field) || in_array((string) $field->field_type, array('textarea','html'), true)) { continue; }
			$value = PowerPlantPVReportPdfDataset::formatFieldValue($field, $langs);
			if ((string) $field->field_code === 'INVERTER_START_POWER') { $power = $value; continue; }
			$code = (string) $field->value_text;
			$status = (string) $field->field_type === 'conformity_so_valid_obs' ? ($code === 'valid' ? 'good' : ($code === 'observation' ? 'warning' : 'neutral')) : (in_array((string) $field->field_type, array('yesno','boolean','checkbox'), true) ? ($code === '1' ? 'good' : 'warning') : 'text');
			$rows[] = array('label'=>PowerPlantPVReportPdfDataset::localizedProperty($field,'field_label',$langs), 'value'=>$value !== '' ? $value : $langs->transnoentities('PowerPlantPVDataNotFilled'), 'status'=>$status);
		}
		$metadata = is_object($equipment) && !$this->visualOption('HIDE_EQUIPMENT') ? trim($this->formatEquipmentProductRef($equipment).' | '.(string) $equipment->serial_number) : '';
		return array('title'=>$title, 'rows'=>$rows, 'metadata'=>$metadata, 'power'=>$power);
	}

	/** @param TCPDF|TCPDI $pdf @param array<int,array<string,mixed>> $panels @param bool $paired @return void */
	protected function visualPanels(&$pdf, $panels, $paired)
	{
		$full = $this->page_largeur - $this->marge_gauche - $this->marge_droite; $columns = $paired ? 2 : 1; $width = ($full - ($columns - 1) * 4) / $columns;
		$parts = array();
		foreach ($panels as $panel) {
			$part = $panel; $part['rows'] = array(); $height = 18;
			foreach ($panel['rows'] as $row) {
				$pdf->SetFont('', '', 8); $rowHeight = max(5, $pdf->getStringHeight(($width - 8) * (!empty($panel['info']) ? 0.38 : 0.66), $row['label']) + 1, $pdf->getStringHeight(($width - 8) * (!empty($panel['info']) ? 0.62 : 0.34), $row['value']) + 1);
				if ($height + $rowHeight > 180 && $part['rows']) { $parts[] = $part; $part['rows'] = array(); $height = 18; }
				$row['height'] = $rowHeight; $part['rows'][] = $row; $height += $rowHeight;
			}
			if ($part['rows'] || $part['title'] !== '') { $parts[] = $part; }
		}
		foreach (array_chunk($parts, $columns) as $pair) {
			$heights = array();
			foreach ($pair as $panel) { $pdf->SetFont('', 'B', 9); $head = max(6, $pdf->getStringHeight($width - 8, $panel['title'])); $pdf->SetFont('', '', 6.8); $metadataHeight = !empty($panel['metadata']) ? $pdf->getStringHeight($width - 8, $panel['metadata']) + 3 : 0; $heights[] = $head + 10 + $metadataHeight + (!empty($panel['power']) ? 5 : 0) + array_sum(array_column($panel['rows'],'height')); }
			$height = max($heights); $this->ensureSpace($pdf, $height + 4); $y = $pdf->GetY();
			foreach ($pair as $i => $panel) {
				$x = $this->marge_gauche + $i * ($width + 4); $pdf->SetDrawColor(218,228,240); $pdf->SetFillColor(255,255,255); $pdf->RoundedRect($x,$y,$width,$height,2,'1111','DF');
				$pdf->SetTextColor(24,47,79); $pdf->SetFont('', 'B', 9); $pdf->SetXY($x+4,$y+3); $pdf->MultiCell($width-8,4,$this->currentOutputLangs->convToOutputCharset($panel['title']),0,'L'); $cy=max($y+10,$pdf->GetY()+1);
				if (!empty($panel['power'])) { $pdf->SetFont('', 'B', 8); $pdf->SetXY($x+4,$cy); $pdf->Cell($width-8,4,$this->currentOutputLangs->convToOutputCharset($this->currentOutputLangs->transnoentities('PowerPlantPVVisualStartPower').' '.$panel['power']),0,0,'L'); $cy+=5; }
				if (!empty($panel['metadata'])) { $pdf->SetTextColor(100,116,139); $pdf->SetFont('', '', 6.8); $pdf->SetXY($x+4,$cy); $pdf->MultiCell($width-8,3,$this->currentOutputLangs->convToOutputCharset($panel['metadata']),0,'L'); $cy=$pdf->GetY()+2; }
				foreach ($panel['rows'] as $j => $row) {
					$labelWidth=($width-8)*(!empty($panel['info'])?0.38:0.66); $valueWidth=$width-8-$labelWidth;
					$pdf->SetFont('', '', 8); $pdf->SetTextColor(!empty($panel['info'])?100:45,!empty($panel['info'])?116:55,!empty($panel['info'])?139:72); $pdf->SetXY($x+4,$cy); $pdf->MultiCell($labelWidth,$row['height'],$this->currentOutputLangs->convToOutputCharset($row['label']),0,'L');
					$vx=$x+4+$labelWidth; $value=$this->currentOutputLangs->convToOutputCharset($row['value']);
					if (!empty($panel['info']) || ($row['status'] ?? '') === 'text') { $pdf->SetTextColor(25,39,60); $pdf->SetXY($vx,$cy); $pdf->MultiCell($valueWidth,$row['height'],$value,0,'L'); }
					else {
						$status=$row['status'] ?? 'neutral'; $bg=$status==='good'?array(224,245,237):($status==='warning'?array(255,244,218):array(239,242,246)); $fg=$status==='good'?array(0,137,99):($status==='warning'?array(166,103,0):array(100,116,139));
						$pdf->SetFont('', '', 7.5); $bw=min($valueWidth,max(18,$pdf->GetStringWidth($value)+8)); $bh=max(4,$pdf->getStringHeight($bw-2,$value)); $pdf->SetFillColor($bg[0],$bg[1],$bg[2]); $pdf->RoundedRect($vx+$valueWidth-$bw,$cy,$bw,$bh+0.6,1.5,'1111','F'); $pdf->SetTextColor($fg[0],$fg[1],$fg[2]); $pdf->SetXY($vx+$valueWidth-$bw+1.5,$cy); $pdf->MultiCell($bw-2,$bh,$value,0,'C');
					}
					$cy+=$row['height'];
					if ($j<count($panel['rows'])-1) { $pdf->SetDrawColor(231,236,244); $pdf->Line($x+4,$cy-0.3,$x+$width-4,$cy-0.3); }
				}
			}
			$pdf->SetY($y+$height+4);
		}
		$pdf->SetFont('', '', $this->defaultFontSize); $pdf->SetTextColor(24,47,79);
	}

	/** @param TCPDF|TCPDI $pdf @param string $title @param string $text @param string $emptyText Grey text shown when the note is empty, nothing if '' @return void */
	protected function visualNote(&$pdf, $title, $text, $emptyText = '')
	{
		$text=trim(dol_string_nohtmltag($text,0)); $muted=false;
		if ($text==='') { if ($emptyText==='') { return; } $text=$emptyText; $muted=true; }
		$this->renderSectionTitle($pdf,$title,$muted?3:2); $width=$this->page_largeur-$this->marge_gauche-$this->marge_droite; $pdf->SetFont('', '', 8.5);
		if ($muted) { $this->ensureSpace($pdf,14); $y=$pdf->GetY(); $pdf->SetFillColor(250,251,253); $pdf->SetDrawColor(218,228,240); $pdf->RoundedRect($this->marge_gauche,$y,$width,10,2,'1111','DF'); $pdf->SetTextColor(100,116,139); $pdf->SetXY($this->marge_gauche+4,$y+3); $pdf->Cell($width-8,4,$this->currentOutputLangs->convToOutputCharset($text),0,0,'L'); $pdf->SetY($y+14); $pdf->SetTextColor(24,47,79); return; }
		$chunks=array(); $chunk=''; foreach (preg_split('/(?<=\s)/u',$text) ?: array() as $word) { $next=$chunk===''?$word:$chunk.$word; if ($chunk!=='' && $pdf->getStringHeight($width-8,$next)>150) { $chunks[]=$chunk; $chunk=$word; } else { $chunk=$next; } } if ($chunk!=='') { $chunks[]=$chunk; }
		foreach ($chunks as $part) { $height=max(14,$pdf->getStringHeight($width-8,$part)+8); $this->ensureSpace($pdf,$height+4); $y=$pdf->GetY(); $pdf->SetDrawColor(218,228,240); $pdf->RoundedRect($this->marge_gauche,$y,$width,$height,2,'1111','D'); $pdf->SetTextColor(40,50,68); $pdf->SetXY($this->marge_gauche+4,$y+4); $pdf->MultiCell($width-8,4,$this->currentOutputLangs->convToOutputCharset($part),0,'L'); $pdf->SetY($y+$height+5); }
	}

	/**
	 * Files, signatures and observation notes of one section. An empty observation stays visible as "No observation".
	 * @param TCPDF|TCPDI $pdf @param array<string,mixed> $row @param array<string,mixed> $dataset @param Translate $langs
	 * @param bool $includeText Also render short text fields (reading observations are not shown in a panel)
	 * @return void
	 */
	protected function visualSectionExtras(&$pdf, $row, $dataset, $langs, $includeText = false)
	{
		$types = $includeText ? array('textarea', 'html', 'text', 'varchar') : array('textarea', 'html');
		foreach ($this->filterVisibleFields($row['fields']) as $field) {
			if (PowerPlantPVReportPdfDataset::isSignatureField($field)) { $this->renderSignatureField($pdf,$field,$dataset,$langs); }
			elseif (PowerPlantPVReportPdfDataset::isFileField($field)) { $this->renderFieldFiles($pdf,$field,$dataset,$langs); }
			elseif (in_array((string)$field->field_type,$types,true)) { $this->visualNote($pdf,PowerPlantPVReportPdfDataset::localizedProperty($field,'field_label',$langs),PowerPlantPVReportPdfDataset::formatFieldValue($field,$langs),$langs->transnoentities('PowerPlantPVVisualNoObservation')); }
		}
	}

	/**
	 * General note whose final "Conclusion:" paragraph is highlighted in a green box.
	 * @param TCPDF|TCPDI $pdf @param string $title @param string $text @param Translate $langs
	 * @return void
	 */
	protected function visualNoteWithConclusion(&$pdf, $title, $text, $langs)
	{
		$plain = trim(dol_string_nohtmltag($text, 0));
		$keyword = preg_quote($langs->transnoentities('PowerPlantPVVisualConclusion'), '/');
		if (!preg_match('/(?:^|\n)\s*'.$keyword.'\s*:\s*(.+)$/isu', $plain, $match, PREG_OFFSET_CAPTURE)) { $this->visualNote($pdf, $title, $text); return; }
		$body = trim(substr($plain, 0, (int) $match[0][1]));
		$conclusion = trim(preg_replace('/\s+/u', ' ', $match[1][0]));
		if ($body !== '') { $this->visualNote($pdf, $title, $body); } else { $this->renderSectionTitle($pdf, $title, 2); }
		$width = $this->page_largeur - $this->marge_gauche - $this->marge_droite; $pdf->SetFont('', '', 8.5);
		$height = max(16, $pdf->getStringHeight($width - 22, $conclusion) + 11); $this->ensureSpace($pdf, $height + 4); $y = $pdf->GetY();
		$pdf->SetFillColor(227, 245, 238); $pdf->SetDrawColor(185, 228, 210); $pdf->RoundedRect($this->marge_gauche, $y, $width, $height, 2, '1111', 'DF');
		$pdf->SetFillColor(18, 128, 92); $pdf->Circle($this->marge_gauche + 7, $y + $height / 2, 3, 0, 360, 'F');
		$pdf->SetTextColor(18, 128, 92); $pdf->SetFont('', 'B', 9); $pdf->SetXY($this->marge_gauche + 14, $y + 3); $pdf->Cell($width - 19, 4, $langs->convToOutputCharset($langs->transnoentities('PowerPlantPVVisualConclusion')), 0, 0, 'L');
		$pdf->SetTextColor(40, 50, 68); $pdf->SetFont('', '', 8.5); $pdf->SetXY($this->marge_gauche + 14, $y + 8); $pdf->MultiCell($width - 19, 4, $langs->convToOutputCharset($conclusion), 0, 'L');
		$pdf->SetY($y + $height + 5); $pdf->SetFont('', '', $this->defaultFontSize); $pdf->SetTextColor(24, 47, 79);
	}

	/** @param TCPDF|TCPDI $pdf @param array<string,mixed> $row @param array<string,mixed> $dataset @param Translate $langs @return void */
	protected function visualReadings(&$pdf, $row, $dataset, $langs)
	{
		$fields=PowerPlantPVReportPdfDataset::fieldsByCode($this->filterVisibleFields($row['fields'])); $cards=array();
		$title=!empty($row['equipment'])?$this->visualIdentity($row['equipment'],true):$langs->transnoentities('PowerPlantPVVisualCommonMeters'); $this->renderSectionTitle($pdf,$title,3);
		foreach ($fields as $code=>$field) {
			if (substr($code,-10)==='_N_MINUS_1' || !PowerPlantPVReportPdfDataset::isNumericFieldType($field->field_type)) { continue; }
			$value=$field->value_number; $label=PowerPlantPVReportPdfDataset::localizedProperty($field,'field_label',$langs); $previous=$fields[$code.'_N_MINUS_1'] ?? null;
			$hasValue=$value!==null && $value!=='' && is_numeric($value); $hasPrevious=is_object($previous) && $previous->value_number!==null && $previous->value_number!=='' && is_numeric($previous->value_number);
			if (!$hasValue && !$hasPrevious) { continue; } // A reading never taken would only add an empty card.
			$history=$hasPrevious ? 'N-1 : '.price($previous->value_number).' '.(string)$field->unit : $langs->transnoentities('PowerPlantPVReportNoPreviousReading');
			if ($hasValue && $hasPrevious) { $delta=(float)$value-(float)$previous->value_number; $history.="\n".$langs->transnoentities('PowerPlantPVVisualDelta').' : '.($delta>=0?'+':'-').price(abs($delta)).' '.(string)$field->unit; }
			$cards[]=array($hasValue?price($value).' '.(string)$field->unit:$langs->transnoentities('PowerPlantPVDataNotFilled'), $label."\n".$history);
		}
		if ($cards) { $this->visualKpis($pdf,$cards,3,31); }
		$this->visualSectionExtras($pdf,$row,$dataset,$langs,true);
	}

	/** @param TCPDF|TCPDI $pdf @param array<string,mixed> $group @param Translate $langs @return void */
	protected function visualDcChart(&$pdf, $group, $langs)
	{
		$rows=array(); foreach ($group['mppts'] as $inputs) { foreach ($inputs as $row) { if ((int)$row->is_connected===1) { $rows[]=$row; } } }
		foreach (array_chunk($rows,24) as $chunkIndex=>$chunk) {
			$volts=array(); $insulation=array(); $valid=0;
			foreach ($chunk as $row) { if ($row->open_circuit_voltage!==null && $row->open_circuit_voltage!=='' && is_numeric($row->open_circuit_voltage)) { $volts[]=(float)$row->open_circuit_voltage; } foreach (array('insulation_positive_to_ground','insulation_negative_to_ground') as $key) { if ($row->{$key}!==null && $row->{$key}!=='' && is_numeric($row->{$key})) { $insulation[]=(float)$row->{$key}; } } if ((string)$row->insulation_status==='valid' && (int)$row->polarity_checked===1) { $valid++; } }
			// All insulation readings at 0 means no insulation fault: flat curves would only add noise.
			$zeroIso=$insulation && max($insulation)<=0; $incomplete=count($volts)<count($chunk) || count($insulation)<2*count($chunk);
			$allIsoValid=true; foreach ($chunk as $row) { if ((string)$row->insulation_status!=='valid') { $allIsoValid=false; } }
			$numeric=(bool)($volts || $insulation); $height=$numeric?($incomplete?105:101):49; $this->ensureSpace($pdf,$height+5); $y=$pdf->GetY(); $x=$this->marge_gauche; $w=$this->page_largeur-$x-$this->marge_droite;
			$pdf->SetDrawColor(218,228,240); $pdf->RoundedRect($x,$y,$w,$height,2,'1111','D'); $pdf->SetFont('', 'B',9); $pdf->SetTextColor(24,47,79); $pdf->SetXY($x+4,$y+3); $pdf->MultiCell($w-48,4,$langs->convToOutputCharset($this->visualIdentity($group['identity'],false).' - '.count($chunk).' '.$langs->transnoentities('PowerPlantPVVisualStrings')),0,'L');
			$pdf->SetFont('', '',7.5); $pdf->SetXY($x+$w-40,$y+3); $pdf->Cell(36,4,$valid.' / '.count($chunk).' '.$langs->convToOutputCharset($langs->transnoentities('PowerPlantPVVisualConform')),0,0,'R');
			$missing=$langs->transnoentities('PowerPlantPVDataNotFilled'); $stats=array(array($volts?price(min($volts),0,$langs,1,-1,0).' - '.price(max($volts),0,$langs,1,-1,0).' V':$missing,$langs->transnoentities('PowerPlantPVOpenCircuitVoltage')),array($insulation?price(min($insulation),0,$langs,1,-1,1).' MOhm':$missing,$langs->transnoentities('PowerPlantPVVisualInsulationMin')),array($insulation?price(max($insulation),0,$langs,1,-1,1).' MOhm':$missing,$langs->transnoentities('PowerPlantPVVisualInsulationMax')));
			if ($zeroIso) { $stats[1]=array($langs->transnoentities('PowerPlantPVVisualNoInsulationFault'),$langs->transnoentities('PowerPlantPVVisualInsulationFault')); $stats[2]=array($allIsoValid?$langs->transnoentities('PowerPlantPVVisualConformSingular'):$langs->transnoentities('PowerPlantPVVisualToCheck'),$langs->transnoentities('PowerPlantPVInsulationStatus')); }
			$tile=($w-12)/3; foreach($stats as $i=>$stat){$tx=$x+4+$i*($tile+2);$pdf->SetFillColor(245,247,250);$pdf->RoundedRect($tx,$y+11,$tile-2,14,1,'1111','F');$pdf->SetFont('', '',7);$pdf->SetTextColor(100,116,139);$pdf->SetXY($tx+2,$y+12);$pdf->Cell($tile-6,4,$langs->convToOutputCharset($stat[1]));$pdf->SetFont('', 'B',9);$pdf->SetTextColor(24,47,79);$pdf->SetXY($tx+2,$y+17);$pdf->Cell($tile-6,5,$langs->convToOutputCharset($stat[0]));}
			if(!$numeric){$pdf->SetXY($x+4,$y+31);$pdf->SetFont('', '',8);$pdf->MultiCell($w-8,4,$langs->convToOutputCharset($langs->transnoentities('PowerPlantPVReportChartMissing')),0,'L');$pdf->SetY($y+$height+5);continue;}
			$gx=$x+12;$gy=$y+32;$gw=$w-25;$gh=48;$vmin=$volts?max(0,floor((min($volts)-10)/10)*10):0;$vmax=$volts?max($vmin+10,ceil((max($volts)+10)/10)*10):1;$imax=$insulation?max(1,ceil(max($insulation)/5)*5):1;
			$pdf->SetFont('', '',6.5);foreach(array(0,0.5,1) as $fraction){$py=$gy+$gh-$fraction*$gh;$pdf->SetDrawColor(233,238,245);$pdf->Line($gx,$py,$gx+$gw,$py);$pdf->SetTextColor(100,116,139);$pdf->SetXY($x+1,$py-2);$pdf->Cell(10,4,price($vmin+($vmax-$vmin)*$fraction,0,$langs,1,-1,0),0,0,'R');if(!$zeroIso){$pdf->SetTextColor(218,128,22);$pdf->SetXY($gx+$gw+1,$py-2);$pdf->Cell(11,4,price($imax*$fraction,0,$langs,1,-1,0));}}
			$step=$gw/count($chunk);foreach($chunk as $i=>$row){if($row->open_circuit_voltage===null || $row->open_circuit_voltage==='' || !is_numeric($row->open_circuit_voltage)){continue;}$bar=max(0.2,min($gh,((float)$row->open_circuit_voltage-$vmin)/($vmax-$vmin)*$gh));$pdf->SetFillColor(184,217,244);$pdf->Rect($gx+$i*$step+$step*0.14,$gy+$gh-$bar,$step*0.72,$bar,'F');if(count($chunk)<=12){$pdf->SetFont('', '',6);$pdf->SetTextColor(24,47,79);$pdf->SetXY($gx+$i*$step,$gy+$gh-$bar-3.5);$pdf->Cell($step,3,price((float)$row->open_circuit_voltage,0,$langs,1,-1,0),0,0,'C');}}
			foreach($zeroIso?array():array('insulation_positive_to_ground','insulation_negative_to_ground') as $series=>$key){$previous=null;$color=$series===0?array(242,146,34):array(31,52,85);$pdf->SetDrawColor($color[0],$color[1],$color[2]);$pdf->SetLineStyle(array('width'=>0.3,'dash'=>$series===0?0:'1,1'));foreach($chunk as $i=>$row){$value=$row->{$key};if($value===null || $value==='' || !is_numeric($value)){$previous=null;continue;}$px=$gx+($i+0.5)*$step;$py=$gy+$gh-(float)$value/$imax*$gh;if(is_array($previous)){$pdf->Line($previous[0],$previous[1],$px,$py);}$pdf->SetFillColor($color[0],$color[1],$color[2]);$pdf->Circle($px,$py,0.65,0,360,'F');$previous=array($px,$py);}}
			$pdf->SetLineStyle(array('width'=>0.2,'dash'=>0));$pdf->SetFont('', '',6);$pdf->SetTextColor(100,116,139);foreach($chunk as $i=>$row){$label=trim((string)$row->string_ref);if(dol_strlen($label)>7 || $label===''){$label=(string)($chunkIndex*24+$i+1);}$pdf->SetXY($gx+$i*$step,$gy+$gh+1);$pdf->Cell($step,3,$langs->convToOutputCharset($label),0,0,'C');$pdf->SetXY($gx+$i*$step,$gy+$gh+4);$pdf->Cell($step,3,'M'.$row->mppt_number,0,0,'C');}
			$pdf->SetXY($x+4,$y+91);$pdf->SetFont('', '',7);$pdf->SetTextColor(88,122,157);$pdf->Cell($w-8,4,$langs->convToOutputCharset($langs->transnoentities($zeroIso?'PowerPlantPVVisualChartLegendVoltage':'PowerPlantPVVisualChartLegend')),0,0,'C');if($incomplete){$pdf->SetXY($x+4,$y+97);$pdf->SetFont('', '',6.5);$pdf->SetTextColor(100,116,139);$pdf->Cell($w-8,3,$langs->convToOutputCharset($langs->transnoentities('PowerPlantPVReportChartMissing')),0,0,'C');}$pdf->SetY($y+$height+5);
		}
	}

	/** @param TCPDF|TCPDI $pdf @param array<string,mixed> $group @param Translate $langs @return void */
	protected function visualDcAnnex(&$pdf, $group, $langs)
	{
		$this->renderSectionTitle($pdf,$this->visualIdentity($group['identity'],true),3);
		$html='<table width="100%" cellpadding="3" cellspacing="0" style="font-size:8pt;"><thead><tr style="color:#64748b;">';
		$columns=array(array('PowerPlantPVMPPT',7),array('PowerPlantPVPVInput',7),array('PowerPlantPVStringRef',13),array('PowerPlantPVOpenCircuitVoltage',11),array('PowerPlantPVPolarityChecked',12),array('PowerPlantPVInsulationStatus',18),array('PowerPlantPVInsulationPositiveToGround',16),array('PowerPlantPVInsulationNegativeToGround',16));
		foreach($columns as $column){$html.='<td width="'.$column[1].'%">'.dol_escape_htmltag($langs->transnoentities($column[0]).(in_array($column[0], array('PowerPlantPVInsulationPositiveToGround', 'PowerPlantPVInsulationNegativeToGround'), true) ? ' (MOhm)' : ($column[0] === 'PowerPlantPVOpenCircuitVoltage' ? ' (V)' : ''))).'</td>';}$html.='</tr></thead><tbody>';$n=0;$notes=array();
		foreach($group['mppts'] as $inputs){foreach($inputs as $row){$html.='<tr nobr="true" style="background-color:'.($n++%2?'#f6f8fc':'#ffffff').';"><td>'.dol_escape_htmltag((string)$row->mppt_number).'</td><td>'.dol_escape_htmltag((string)$row->pv_input_number).'</td><td>'.dol_escape_htmltag((string)$row->string_ref).'</td>';if(!(int)$row->is_connected){$html.='<td colspan="5" style="color:#64748b;">'.dol_escape_htmltag($langs->transnoentities('PowerPlantPVNotUsedByPowerPlant')).'</td></tr>';continue;}
			$html.='<td>'.dol_escape_htmltag($row->open_circuit_voltage===null || $row->open_circuit_voltage===''?'—':price($row->open_circuit_voltage)).'</td>';
			foreach(array('polarity_checked','insulation_status') as $key){$good=$key==='polarity_checked'?(string)$row->{$key}==='1':(string)$row->{$key}==='valid';$value=$key==='polarity_checked'?$this->formatDcMeasureBoolean($row,$row->{$key},$langs):$this->formatDcMeasureInsulationStatus($row,$langs);$html.='<td style="color:'.($good?'#008963':'#64748b').';">'.dol_escape_htmltag($value).'</td>';}
			foreach(array('insulation_positive_to_ground','insulation_negative_to_ground') as $key){$html.='<td>'.dol_escape_htmltag($row->{$key}===null || $row->{$key}===''?'—':price($row->{$key})).'</td>';}$html.='</tr>';if(trim((string)$row->observation)!==''){$notes[]=$row->string_ref.' : '.$row->observation;}
		}}
		$this->writeHtml($pdf,$html.'</tbody></table>');$pdf->Ln(3);$this->visualNote($pdf,$langs->transnoentities('Observation'),implode("\n",$notes));
	}

	/**
	 * Final signature cards: technician, optional technical department, customer.
	 * Images come from the report signature fields; empty cards stay ready for a handwritten signature.
	 * @param TCPDF|TCPDI $pdf @param CommonObject $object @param array<string,mixed> $dataset @param Translate $langs
	 * @return void
	 */
	protected function visualSignatures(&$pdf, $object, $dataset, $langs)
	{
		$technicianField = null; $customerField = null;
		$all = $dataset['general_sections'] ?? array();
		foreach (($dataset['sections_by_powerplant'] ?? array()) as $rows) { $all = array_merge($all, $rows); }
		foreach ($all as $row) {
			$isCustomer = is_object($row['section'] ?? null) && $this->isCustomerSignatureSectionRow($row);
			foreach ($this->filterVisibleFields($row['fields'] ?? array()) as $field) {
				if (!PowerPlantPVReportPdfDataset::isSignatureField($field)) { continue; }
				$code = (string) $field->field_code;
				if ($isCustomer && $customerField === null) { $customerField = $field; }
				elseif (!$isCustomer && $technicianField === null && (strpos($code, 'TECHNICIAN_') === 0 || strpos($code, 'TECH_') === 0)) { $technicianField = $field; }
			}
		}
		$names = array();
		foreach (($dataset['internal_contacts'] ?? array()) as $contact) { if (is_array($contact) && !empty($contact['name'])) { $names[] = (string) $contact['name']; } }
		$date = $this->getInterventionDate($object); $date = !empty($date) ? dol_print_date($date, 'day', 'tzuser', $langs) : '';
		$technicianImage = $this->visualSignatureImage($technicianField, $dataset);
		$customerImage = $this->visualSignatureImage($customerField, $dataset);
		$customerName = is_object($customerField) ? trim(dol_string_nohtmltag(PowerPlantPVReportPdfDataset::formatFieldValue($customerField, $langs))) : '';
		$cards = array(array($langs->transnoentities('PowerPlantPVReportTechnicianSignature'), implode(', ', array_unique($names)), $names || $technicianImage !== '' ? $date : '', $technicianImage));
		if ($this->visualOption('TECHNICAL_SERVICE_SIGNATURE')) { $cards[] = array($langs->transnoentities('PowerPlantPVVisualTechnicalServiceSignature'), '', '', ''); }
		$cards[] = array($langs->transnoentities('PowerPlantPVVisualCustomerSignature'), $customerName, $customerImage !== '' ? $date : '', $customerImage);

		// The block sits at the bottom of the last page and the customer card covers the right half:
		// Dolibarr native online signature stamps the image at x = 53 % of the width and y = height - 60 mm.
		$height = 58; $y = $this->getContentBottomY() - $height - 2;
		if ($pdf->GetY() + 10 > $y) { $this->visualNewPage($pdf); $y = $this->getContentBottomY() - $height - 2; }
		$pdf->SetY($y - 9);
		$this->renderSectionTitle($pdf, $langs->transnoentities('PowerPlantPVReportSignatures'), 2);
		$customerX = round($this->page_largeur * 0.53) - 6; $leftCount = count($cards) - 1; $leftWidth = ($customerX - 4 - $this->marge_gauche - ($leftCount - 1) * 4) / $leftCount;
		foreach ($cards as $i => $card) {
			$isLast = $i === count($cards) - 1;
			$x = $isLast ? $customerX : $this->marge_gauche + $i * ($leftWidth + 4);
			$width = $isLast ? $this->page_largeur - $this->marge_droite - $customerX : $leftWidth;
			$pdf->SetDrawColor(218, 228, 240); $pdf->SetFillColor(255, 255, 255); $pdf->RoundedRect($x, $y, $width, $height, 2, '1111', 'DF');
			$pdf->SetTextColor(24, 47, 79); $pdf->SetFont('', 'B', 9); $pdf->SetXY($x + 4, $y + 3); $pdf->MultiCell($width - 8, 4, $langs->convToOutputCharset($card[0]), 0, 'L');
			foreach (array(array('Name', $card[1], 12), array('Date', $card[2], 17)) as $line) {
				$pdf->SetFont('', '', 8); $pdf->SetTextColor(100, 116, 139); $pdf->SetXY($x + 4, $y + $line[2]); $pdf->Cell(12, 4, $langs->convToOutputCharset($langs->transnoentities($line[0])), 0, 0, 'L');
				if ($line[1] !== '') { $pdf->SetTextColor(25, 39, 60); $pdf->SetFont('', 'B', 8); $pdf->SetXY($x + 16, $y + $line[2]); $pdf->Cell($width - 20, 4, $langs->convToOutputCharset(dol_trunc($line[1], 40)), 0, 0, 'L'); }
				else { $pdf->SetDrawColor(184, 192, 204); $pdf->SetLineStyle(array('width' => 0.2, 'dash' => '0.6,0.8')); $pdf->Line($x + 16, $y + $line[2] + 3.5, $x + $width - 4, $y + $line[2] + 3.5); $pdf->SetLineStyle(array('width' => 0.2, 'dash' => 0)); }
			}
			$bx = $x + 4; $by = $y + 24; $bw = $width - 8; $bh = $height - 28;
			$pdf->SetDrawColor(207, 213, 223); $pdf->SetLineStyle(array('width' => 0.2, 'dash' => '1,1')); $pdf->RoundedRect($bx, $by, $bw, $bh, 1.5, '1111', 'D'); $pdf->SetLineStyle(array('width' => 0.2, 'dash' => 0));
			$size = $card[3] !== '' ? @getimagesize($card[3]) : false;
			if (!empty($size[0]) && !empty($size[1])) { $ratio = min(($bw - 6) / $size[0], ($bh - 4) / $size[1]); $pdf->Image($card[3], $bx + ($bw - $size[0] * $ratio) / 2, $by + ($bh - $size[1] * $ratio) / 2, $size[0] * $ratio, $size[1] * $ratio); }
		}
		$pdf->SetY($y + $height + 4); $pdf->SetFont('', '', $this->defaultFontSize); $pdf->SetTextColor(24, 47, 79);
	}

	/**
	 * First readable image of a signature field.
	 * @param object|null $field Frozen signature field
	 * @param array<string,mixed> $dataset Dataset
	 * @return string Absolute path or ''
	 */
	protected function visualSignatureImage($field, $dataset)
	{
		if (!is_object($field) || empty($field->files) || !is_array($field->files)) { return ''; }
		foreach ($field->files as $file) {
			if (!is_object($file)) { continue; }
			$path = PowerPlantPVReportPdfDataset::resolveReportFilePath($dataset['report'], $file);
			if ($path !== '' && PowerPlantPVReportPdfDataset::isImageFile($file, $path)) { return $path; }
		}
		return '';
	}
}
