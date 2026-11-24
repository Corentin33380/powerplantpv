<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
// Renders the commissioning attestation PDF with the real TCPDF of a Dolibarr core, without database:
// once fully filled, once with missing data that must stay visible as "Non renseigné".
if (PHP_SAPI !== 'cli') { exit(1); }
$core = $argv[1] ?? '';
$module = $argv[2] ?? dirname(__DIR__);
$outputDir = $argv[3] ?? '';
if (!is_file($core.'/core/lib/functions.lib.php') || basename($module) !== 'powerplantpv') {
	fwrite(STDERR, "Usage: php test/attestation_mise_en_service_pdf_native.php CORE_HTDOCS MODULE_DIR_NAMED_POWERPLANTPV [OUTPUT_DIR]\n"); exit(1);
}
$dataRoot = sys_get_temp_dir().'/ppv-commissioning-'.bin2hex(random_bytes(6));
mkdir($dataRoot.'/mycompany/logos', 0777, true);
mkdir($dataRoot.'/powerplantpv/setup', 0777, true);
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
if (is_file($core.'/core/lib/html.lib.php')) { require_once $core.'/core/lib/html.lib.php'; }
require_once $core.'/core/class/translate.class.php';
$conf = (object) array(
	'entity' => 1,
	'global' => (object) array('MAIN_DISABLE_PDF_COMPRESSION' => '1', 'MAIN_LANG_DEFAULT' => 'fr_FR'),
	'file' => (object) array('character_set_client' => 'UTF-8', 'dol_document_root' => array('main' => $core, 'test' => dirname($module)), 'dol_url_root' => array('main' => '', 'test' => '/custom')),
	'mycompany' => (object) array('dir_output' => $dataRoot.'/mycompany', 'multidir_output' => array(1 => $dataRoot.'/mycompany')),
	'powerplantpv' => (object) array('dir_output' => $dataRoot.'/powerplantpv', 'multidir_output' => array(1 => $dataRoot.'/powerplantpv')),
	'modules_parts' => array(),
	'tzuserinputkey' => 'tzserver', 'format_date_short' => '%d/%m/%Y', 'format_date_text_short' => '%d %b %Y',
);
$langs = new Translate('', $conf);
$langs->setDefaultLang('fr_FR');
$langs->loadLangs(array('main', 'companies', 'powerplantpv@powerplantpv'));
$user = new class {
	public $id = 1;
	public $admin = 0;
	public function getFullName($langs) { return 'Technicien Test'; }
};
$mysoc = (object) array('name' => 'JPSUN', 'logo' => '', 'logo_small' => '');
// No database: the paper format falls back to A4, the fixture supplies every value.
$db = new class {
	public function escape($value) { return addslashes((string) $value); }
	public function sanitize($value) { return (string) $value; }
	public function prefix() { return MAIN_DB_PREFIX; }
	public function query($sql) { return false; }
	public function lasterror() { return ''; }
};
require_once $core.'/core/lib/pdf.lib.php';
require_once $module.'/core/modules/attestation/doc/pdf_attestation_mise_en_service.modules.php';

$checks = 0;
function commissioningPdfCheck($condition, $message)
{
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($message); }
}

/** Fixture model: frozen data instead of the database, test footer. */
class CommissioningPdfFixture extends pdf_attestation_mise_en_service
{
	public $fixture = array();
	public $images = array('', '');
	protected function collectCommissioningData($object, $outputlangs) { return $this->fixture; }
	protected function getSignatureImages($object) { return $this->images; }
	protected function updateLastMainDoc($object, $file) {}
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		$pdf->SetFont('', '', 7); $pdf->SetTextColor(0, 0, 0); $pdf->SetXY($this->marge_gauche, $this->page_hauteur - 14);
		$pdf->Cell(150, 4, 'JPSUN – Attestation de mise en service', 0, 0, 'L');
		$pdf->Cell(0, 4, 'Page '.$pdf->getPage(), 0, 0, 'R');
		return 10;
	}
}

function commissioningPdfText($file)
{
	$raw = file_get_contents($file);
	preg_match_all('/\[((?:\\\\.|[^\]\\\\])*)\]\s*TJ|\(((?:\\\\.|[^)\\\\])*)\)\s*Tj/s', $raw, $matches, PREG_SET_ORDER);
	$text = '';
	foreach ($matches as $match) {
		preg_match_all('/\(((?:\\\\.|[^)\\\\])*)\)/s', $match[1] !== '' ? $match[1] : '('.($match[2] ?? '').')', $pieces);
		$text .= stripcslashes(implode('', $pieces[1]));
	}
	return mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
}

$full = array(
	'writer_name' => 'Corentin Lasserre', 'writer_function' => 'Conducteur de travaux', 'company' => 'JPSUN', 'place' => 'Le Teich',
	'site_address' => 'Avenue du Président René Coty 33700 Mérignac France', 'project' => 'COSEC Gymnase Léo Lagrange', 'client' => 'Ville de Mérignac',
	'commissioning_date' => '20/11/2026', 'attestation_date' => '24/11/2026', 'installed_power' => '44,64 kWc',
	'modules_count' => '96', 'modules_models' => 'DUALSUN FLASH 465 Half-Cut Black', 'inverters_count' => '1', 'inverters_models' => 'HUAWEI SUN2000-40K-MC0',
	'connection_type' => 'Autoconsommation avec vente du surplus', 'consuel_number' => '2611-33-004521', 'consuel_date' => '12/11/2026',
	'pdl' => '25174892647301', 'racc' => 'RAC-DA22-112233',
);
$object = (object) array('ref' => 'ATT2611-0007', 'entity' => 1, 'fk_soc' => 0, 'date_attestation' => dol_mktime(12, 0, 0, 11, 24, 2026), 'date_signature' => null, 'signature_file' => '');
$outDir = $outputDir !== '' ? rtrim($outputDir, '/\\') : $dataRoot;
$conf->powerplantpv->multidir_output[1] = $outDir;

foreach (array('complete' => $full, 'incomplete' => array_merge($full, array('consuel_number' => '', 'consuel_date' => '', 'racc' => '', 'inverters_count' => '', 'writer_function' => ''))) as $case => $fixture) {
	$model = new CommissioningPdfFixture($db);
	$model->fixture = $fixture;
	$object->ref = 'ATT2611-0007-'.$case;
	commissioningPdfCheck($model->write_file($object, $langs) === 1, 'PDF written for '.$case);
	$file = $model->result['fullpath'];
	$text = commissioningPdfText($file);
	foreach (array('ATTESTATION DE MISE EN SERVICE', 'Corentin Lasserre', 'COSEC Gymnase Léo Lagrange', 'Contrôles préalables à la mise en service', 'Conformité et raccordement', '25174892647301', 'Fait à', 'Cachet de l\'entreprise') as $needle) {
		commissioningPdfCheck(strpos($text, $needle) !== false, $case.': PDF text contains '.$needle);
	}
	if ($case === 'complete') {
		foreach (array('2611-33-004521', 'HUAWEI SUN2000-40K-MC0', '44,64 kWc', 'RAC-DA22-112233') as $needle) {
			commissioningPdfCheck(strpos($text, $needle) !== false, 'complete: PDF text contains '.$needle);
		}
		commissioningPdfCheck(strpos($text, 'Non renseigné') === false, 'complete: nothing is reported missing');
	} else {
		commissioningPdfCheck(substr_count($text, 'Non renseigné') >= 5, 'incomplete: every missing value is shown');
	}
	echo 'Commissioning attestation PDF ('.$case.') -> '.$file."\n";
}
echo $checks." checks OK\n";
