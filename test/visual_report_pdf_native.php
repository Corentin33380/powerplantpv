<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
// Renders the visual maintenance PDF with the real TCPDF of a Dolibarr core, without database.
// The frozen dataset mimics a preventive maintenance with four inverters and 73 strings.
if (PHP_SAPI !== 'cli') { exit(1); }
$core = $argv[1] ?? '';
$module = $argv[2] ?? dirname(__DIR__);
$outputDir = $argv[3] ?? '';
if (!is_file($core.'/core/lib/functions.lib.php') || basename($module) !== 'powerplantpv') {
	fwrite(STDERR, "Usage: php test/visual_report_pdf_native.php CORE_HTDOCS MODULE_DIR_NAMED_POWERPLANTPV [OUTPUT_DIR]\n"); exit(1);
}
$dataRoot = sys_get_temp_dir().'/ppv-visual-'.bin2hex(random_bytes(6));
mkdir($dataRoot.'/mycompany/logos', 0777, true);
define('DOL_DOCUMENT_ROOT', $core);
define('DOL_DATA_ROOT', $dataRoot);
define('DOL_URL_ROOT', '');
define('DOL_MAIN_URL_ROOT', 'https://example.test');
define('MAIN_DB_PREFIX', 'visual_test_');
define('TCPDF_PATH', DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/');
define('TCPDI_PATH', DOL_DOCUMENT_ROOT.'/includes/tcpdi/');
if (is_file(DOL_DOCUMENT_ROOT.'/version.inc.php')) {
	require_once DOL_DOCUMENT_ROOT.'/version.inc.php';
} else {
	$versionSource = file_get_contents(DOL_DOCUMENT_ROOT.'/filefunc.inc.php');
	if (!is_string($versionSource) || !preg_match('/define\([\'"]DOL_VERSION[\'"],\s*[\'"]([^\'"]+)/', $versionSource, $versionMatch)) {
		throw new RuntimeException('Cannot identify the Dolibarr core version.');
	}
	define('DOL_VERSION', $versionMatch[1]);
}
require_once $core.'/core/lib/functions.lib.php';
require_once $core.'/core/class/translate.class.php';
$conf = (object) array(
	'entity' => 1,
	'global' => (object) array('MAIN_DISABLE_PDF_COMPRESSION' => '1', 'MAIN_LANG_DEFAULT' => 'fr_FR'),
	'file' => (object) array('character_set_client' => 'UTF-8', 'dol_document_root' => array('main' => $core, 'test' => dirname($module)), 'dol_url_root' => array('main' => '', 'test' => '/custom')),
	'mycompany' => (object) array('dir_output' => $dataRoot.'/mycompany', 'multidir_output' => array(1 => $dataRoot.'/mycompany')),
	'ficheinter' => (object) array('dir_output' => $dataRoot.'/ficheinter', 'multidir_output' => array(1 => $dataRoot.'/ficheinter')),
	'tzuserinputkey' => 'tzserver', 'format_date_short' => '%d/%m/%Y', 'format_date_text_short' => '%d %b %Y',
);
$langs = new Translate('', $conf);
$langs->setDefaultLang('fr_FR');
$langs->loadLangs(array('main', 'companies', 'interventions', 'products', 'dict', 'powerplantpv@powerplantpv'));
$user = (object) array('id' => 1, 'admin' => 0);
$mysoc = (object) array('name' => 'Installateur test', 'logo' => '', 'logo_small' => '');
require_once $core.'/core/lib/pdf.lib.php';
require_once $module.'/core/modules/fichinter/doc/pdf_powerplantpvreport.modules.php';

$checks = 0;
function visualPdfCheck($condition, $message)
{
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($message); }
}

/** Fixture model: frozen snapshot instead of the database, simple footer instead of the company record. */
class VisualPdfFixture extends pdf_powerplantpvreport
{
	public $snapshot = array();
	protected function resolvePowerplantTechnicalSummarySnapshot($powerplant, $outputlangs) { return $this->snapshot; }
	protected function visualIllustration(&$pdf, $snapshot, $langs) {}
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		$pdf->SetFont('', '', 7); $pdf->SetXY($this->marge_gauche, $this->page_hauteur - 14);
		$pdf->Cell(150, 4, 'Pied de page test', 0, 0, 'L');
		$pdf->Cell(0, 4, 'Page '.$pdf->getPage(), 0, 0, 'R');
		return 10;
	}
	protected function computeFooterHeight() { return 28; }
	/** A4 portrait without the database lookup of pdf_getFormat(). */
	public static function create($issuer)
	{
		$model = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
		$model->page_largeur = 210; $model->page_hauteur = 297; $model->format = array(210, 297);
		$model->marge_gauche = 10; $model->marge_droite = 10; $model->marge_haute = 10; $model->marge_basse = 10; $model->emetteur = $issuer;
		return $model;
	}
	/** Same sequence as write_file(), with an injected dataset. */
	public function build($object, $dataset, $file)
	{
		$langs = $GLOBALS['langs'];
		$pdf = pdf_getInstance($this->format);
		$this->defaultFontSize = max(8, pdf_getPDFFontSize($langs) - 1);
		$pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->SetAutoPageBreak(true, 0);
		$pdf->SetFont(pdf_getPDFFont($langs), '', $this->defaultFontSize);
		$pdf->SetMargins($this->marge_gauche, 34, $this->marge_droite);
		$pdf->SetCompression(false);
		$this->heightforfooter = $this->computeFooterHeight(); $this->currentObject = $object; $this->currentOutputLangs = $langs;
		$this->completedPageFooters = array(); $this->visualHeaders = array(); $this->visualInverterNumbers = array();
		$this->visualReportTitle = 'Rapport de maintenance préventive';
		$pdf->Open(); $pdf->AddPage(); $this->reserveFooterSpace($pdf);
		if (!isset($this->visualHeaders[(int) $pdf->getPage()])) { $this->renderHeader($pdf, $object, $dataset, $langs); }
		$this->renderReport($pdf, $object, $dataset, $langs);
		$this->renderFooters($pdf, $object, $langs);
		$pages = $pdf->getNumPages();
		$pdf->Close(); $pdf->Output($file, 'F');
		return $pages;
	}
}

function visualField($code, $label, $type, $value, $unit = '')
{
	$field = new stdClass();
	$field->field_code = $code; $field->field_label = $label; $field->field_type = $type; $field->unit = $unit; $field->visible_pdf = 1;
	$field->value_text = is_numeric($value) && in_array($type, array('double'), true) ? null : (string) $value;
	$field->value_number = $type === 'double' ? $value : null;
	$field->files = array();
	return $field;
}
function visualSection($code, $label, $fields, $equipment = null, $measures = array())
{
	return array('section' => (object) array('section_code' => $code, 'section_label' => $label, 'visible_pdf' => 1, 'fk_report_powerplant' => 7), 'fields' => $fields, 'equipment' => $equipment, 'dc_measures' => $measures);
}

$plant = (object) array('id' => 7, 'fk_powerplant' => 18, 'powerplant_ref' => 'PP2611-0018', 'powerplant_label' => 'Ecopark 5 Revente Total');
$sections = array();
$sections[] = visualSection('ELECTRICAL_BOX', 'Vérifications coffrets', array(
	visualField('BOX_VISUAL_ENVELOPE', 'Contrôle visuel enveloppe : casse, étanchéité, corrosion', 'conformity_so_valid_obs', 'valid'),
	visualField('BOX_EARTHING_SIGNAGE', 'Contrôle présence mise à la terre et signalétique réglementaire', 'conformity_so_valid_obs', 'observation'),
	visualField('BOX_SECTION_SWITCH', 'Manipulation de l’interrupteur sectionneur', 'boolean', '0'),
	visualField('BOX_EMERGENCY_STOP_CHECK', 'Vérification fonctionnement arrêt d’urgence', 'conformity_so_valid_obs', 'valid'),
	visualField('BOX_OBSERVATION', 'Observations coffrets', 'textarea', ''),
));
$measures = array();
$voltages = array(array(525, 832, 835, 525, 524, 834, 838, 528), array(525, 830, 842, 522, 525, 843, 525, 632), array(520, 831, 620, 535, 520, 534, 703), array(520, 694, 530, 520, 690, 702, 533));
foreach (array(1, 2, 3, 4) as $number) {
	$equipment = (object) array('fk_powerplant_line' => 100 + $number, 'equipment_marker' => '', 'equipment_identification' => '', 'equipment_ref' => 'HUA_SUN2000-100KTL-M1', 'equipment_label' => 'Onduleur HUAWEI SUN2000-100KTL-M1', 'product_ref' => 'HUA_SUN2000-100KTL-M1', 'serial_number' => '6T22B90367'.$number.'0');
	$sections[] = visualSection('INVERTER', 'Onduleur', array(
		visualField('INVERTER_DC_SWITCH', 'Manipulation interrupteur DC', 'boolean', '1'),
		visualField('INVERTER_USB_MPPT_CAPS', 'Présence de bouchons USB + MPPT', 'boolean', $number >= 3 ? '0' : '1'),
		visualField('INVERTER_START_OPERATION', 'Contrôle de fonctionnement de l’onduleur au départ', 'conformity_so_valid_obs', 'valid'),
		visualField('INVERTER_START_POWER', 'Puissance au départ P =', 'double', 11000 + 1000 * $number, 'W'),
	), $equipment);
	foreach ($voltages[$number - 1] as $i => $volt) {
		$measure = new stdClass();
		$measure->fk_powerplant = 7; $measure->fk_inverter = 100 + $number; $measure->stable_key = 'plant:7:line:'.(100 + $number).':dc:'.$i;
		$measure->inverter_marker = ''; $measure->inverter_identification = ''; $measure->inverter_ref = 'HUA_SUN2000-100KTL-M1'; $measure->inverter_label = 'Onduleur HUAWEI'; $measure->inverter_serial = '6T22B90367'.$number.'0';
		$measure->mppt_number = intdiv($i, 2) + 1; $measure->pv_input_number = $i + 1; $measure->position = $i; $measure->string_ref = 'PV'.($i + 1); $measure->is_connected = 1;
		$measure->open_circuit_voltage = $volt; $measure->polarity_checked = 1; $measure->insulation_status = 'valid';
		$measure->insulation_positive_to_ground = 0; $measure->insulation_negative_to_ground = 0; $measure->observation = '';
		$measures[] = $measure;
	}
}
$sections[] = visualSection('ROOF', 'Opérations en toiture', array(
	visualField('ROOF_PANEL_VISUAL_CHECK', 'Contrôle des panneaux photovoltaïques', 'conformity_so_valid_obs', 'valid'),
	visualField('ROOF_CABLE_TRAY_CHECK', 'Vérification de l’état des chemins de câble et présence du capot', 'conformity_so_valid_obs', 'observation'),
	visualField('ROOF_EARTHING_CHECK', 'Contrôle des mises à la terre', 'conformity_so_valid_obs', 'not_applicable'),
));
$sections[] = visualSection('PRODUCTION_READING', 'Relevés', array(
	visualField('INVERTER_PRODUCTION_N_MINUS_1', 'Relevé production onduleur N-1', 'double', 527670, 'kWh'),
	visualField('INVERTER_PRODUCTION', 'Relevé production onduleur N', 'double', 561970, 'kWh'),
	visualField('INJECTION_INDEX_N_MINUS_1', 'Index injection N-1', 'double', 1097814, 'kWh'),
	visualField('INJECTION_INDEX', 'Index injection N', 'double', 1623230, 'kWh'),
	visualField('CONSUMPTION_INDEX', 'Index consommation N', 'double', null, 'kWh'),
	visualField('PRODUCTION_READING_OBSERVATION', 'Observation', 'text', 'Relevés effectués sur site.'),
));
$sections[] = visualSection('DC_ELECTRICAL_MEASURE', 'Mesures électriques DC', array(), null, $measures);
$general = array(
	array('section' => (object) array('section_code' => 'GENERAL_INFORMATION', 'section_label' => 'Informations générales', 'visible_pdf' => 1, 'fk_report_powerplant' => 0), 'fields' => array(visualField('GENERAL_INTERVENTION_CONTEXT', 'Contexte de l’intervention', 'textarea', 'Maintenance préventive annuelle de la centrale.')), 'equipment' => null, 'dc_measures' => array()),
	array('section' => (object) array('section_code' => 'GENERAL_OBSERVATIONS', 'section_label' => 'Observations générales', 'visible_pdf' => 1, 'fk_report_powerplant' => 0), 'fields' => array(visualField('GENERAL_OBSERVATIONS_TEXT', 'Observations générales', 'textarea', "Les contrôles sont conformes.\nConclusion : Aucun défaut majeur ; la fixation du capot reste à reprendre.")), 'equipment' => null, 'dc_measures' => array()),
);
$dataset = array(
	'report_found' => true,
	'report' => (object) array('report_template_label' => 'Rapport de maintenance préventive', 'intervention_nature_label' => 'Maintenance préventive'),
	'thirdparty' => (object) array('name' => 'SCI TEST', 'address' => '1 rue du Test', 'zip' => '33000', 'town' => 'Bordeaux', 'country_code' => 'FR'),
	'project' => null, 'contracts' => array(array('ref' => 'CT2611-0007')),
	'internal_contacts' => array(array('name' => 'Technicien Test', 'function' => '')),
	'source_services' => array(), 'powerplants' => array($plant),
	'general_sections' => $general, 'sections_by_powerplant' => array(7 => $sections),
);
$snapshot = array('sections' => array(
	array('code' => 'site', 'rows' => array(array('label_key' => 'PowerPlantPDFReference', 'value' => 'PP2611-0018'), array('label_key' => 'PowerPlantPDFInstalledPower', 'value' => '249,89 kWc'))),
	array('code' => 'connection', 'rows' => array(array('label_key' => 'PowerPlantPDFPrmPdl', 'value' => '50088794738676'), array('label_key' => 'PowerPlantPDFConnectionType', 'value' => 'Valorisation totale'), array('label_key' => 'PowerPlantPDFConnectionContractPower', 'value' => '440,00 kVA'), array('label_key' => 'PowerPlantPDFConnectionRequestNumber', 'value' => 'SDO-RP-2022-003925'))),
));
$object = (object) array('ref' => 'FI2611-0123', 'entity' => 1, 'datei' => dol_mktime(12, 0, 0, 11, 17, 2026));

$runs = array('default' => array(), 'client' => array('HIDE_EQUIPMENT', 'SHORT_CONNECTION', 'TECHNICAL_SERVICE_SIGNATURE'));
$contents = array();
foreach ($runs as $name => $options) {
	foreach (array('HIDE_EQUIPMENT', 'SHORT_CONNECTION', 'TECHNICAL_SERVICE_SIGNATURE') as $option) { unset($conf->global->{'POWERPLANTPV_REPORT_PDF_'.$option}); }
	foreach ($options as $option) { $conf->global->{'POWERPLANTPV_REPORT_PDF_'.$option} = '1'; }
	$model = VisualPdfFixture::create($mysoc);
	$model->snapshot = $snapshot;
	$file = ($outputDir !== '' ? rtrim($outputDir, '/\\') : $dataRoot).'/visual-'.$name.'.pdf';
	$pages = $model->build($object, $dataset, $file);
	visualPdfCheck(is_file($file) && $pages >= 4, 'The '.$name.' PDF is written with several pages');
	// Uncompressed core-font text: decode literal strings of the content streams.
	$raw = file_get_contents($file);
	preg_match_all('/\[((?:\\\\.|[^\]\\\\])*)\]\s*TJ|\(((?:\\\\.|[^)\\\\])*)\)\s*Tj/s', $raw, $matches, PREG_SET_ORDER);
	$text = '';
	foreach ($matches as $match) {
		preg_match_all('/\(((?:\\\\.|[^)\\\\])*)\)/s', $match[1] !== '' ? $match[1] : '('.($match[2] ?? '').')', $pieces);
		$text .= stripcslashes(implode('', $pieces[1]))."\n";
	}
	$contents[$name] = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
}
$default = $contents['default']; $client = $contents['client'];
visualPdfCheck(strpos($default, '6T22B9036710') !== false && strpos($default, 'SDO-RP-2022-003925') !== false, 'Default layout keeps inverter serials and the full connection block');
visualPdfCheck(strpos($client, '6T22B9036710') === false && strpos($client, 'SUN2000') === false && strpos($client, 'HUAWEI') === false, 'Client layout hides inverter product references and serial numbers');
visualPdfCheck(strpos($client, 'Onduleur 1') !== false && strpos($client, 'Onduleur 4') !== false, 'Inverters without marker are numbered in the client layout');
visualPdfCheck(strpos($client, 'SDO-RP-2022-003925') === false && strpos($client, '50088794738676') !== false, 'Short connection keeps the PRM and drops the request number');
visualPdfCheck(strpos($client, $langs->transnoentities('PowerPlantPVVisualMaterialAnnex')) === false && strpos($default, $langs->transnoentities('PowerPlantPVVisualMaterialAnnex')) !== false, 'Equipment appendix only in the default layout');
visualPdfCheck(strpos($default, $langs->transnoentities('PowerPlantPVVisualVigilance', 5)) !== false, 'Cover lists observations and explicit No answers: 2 observations, switch, two missing caps');
visualPdfCheck(strpos($default, $langs->transnoentities('PowerPlantPVVisualDelta').' : +525 416') !== false || strpos($default, '+525') !== false, 'Readings show the change since N-1');
visualPdfCheck(strpos($default, 'Index consommation N') === false, 'A reading never taken does not produce an empty card');
visualPdfCheck(strpos($default, 'Relevés effectués sur site.') !== false, 'Short reading observation is printed');
visualPdfCheck(strpos($default, $langs->transnoentities('PowerPlantPVVisualNoObservation')) !== false, 'Empty section observation remains visible');
visualPdfCheck(strpos($default, $langs->transnoentities('PowerPlantPVVisualConclusion')) !== false && strpos($default, 'Conclusion : Aucun') === false, 'Conclusion paragraph is moved to its highlighted box');
visualPdfCheck(strpos($default, $langs->transnoentities('PowerPlantPVVisualInsulationFault')) !== false && strpos($default, $langs->transnoentities('PowerPlantPVReportChartMissing')) === false, 'Zero insulation is reported as no fault, without the missing-data note');
// Card titles may wrap: compare the flattened text.
$flat = static function ($text) { return preg_replace('/\s+/u', ' ', $text); };
$serviceTitle = $langs->transnoentities('PowerPlantPVVisualTechnicalServiceSignature');
visualPdfCheck(strpos($flat($client), $serviceTitle) !== false && strpos($flat($default), $serviceTitle) === false, 'Technical department signature box is optional');
visualPdfCheck(strpos($default, 'Technicien Test') !== false && strpos($default, $langs->transnoentities('PowerPlantPVVisualCustomerSignature')) !== false, 'Signature cards show the technician and the customer');
if ($outputDir === '') { foreach (glob($dataRoot.'/*.pdf') ?: array() as $pdfFile) { unlink($pdfFile); } }
print 'OK: '.$checks.' visual PDF checks, PHP '.PHP_VERSION.', Dolibarr '.DOL_VERSION.' (real TCPDF, no database).'."\n";
