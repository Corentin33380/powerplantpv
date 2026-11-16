<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
// Native classes and formatting, no ERP bootstrap and no database connection.
if (PHP_SAPI !== 'cli') { exit(1); }
$core = $argv[1] ?? '';
$module = $argv[2] ?? dirname(__DIR__);
if (!is_file($core.'/core/lib/functions.lib.php') || basename($module) !== 'powerplantpv') {
	fwrite(STDERR, "Usage: php test/inverter_maintenance_native.php CORE_HTDOCS MODULE_DIR_NAMED_POWERPLANTPV\n"); exit(1);
}
define('DOL_DOCUMENT_ROOT', $core);
define('DOL_URL_ROOT', '');
define('DOL_MAIN_URL_ROOT', 'https://example.test');
define('MAIN_DB_PREFIX', 'native_test_');
if (is_file(DOL_DOCUMENT_ROOT.'/version.inc.php')) {
	require_once DOL_DOCUMENT_ROOT.'/version.inc.php';
} else {
	// Older core versions define their version in filefunc.inc.php; do not bootstrap an instance.
	$versionSource = file_get_contents(DOL_DOCUMENT_ROOT.'/filefunc.inc.php');
	if (!is_string($versionSource) || !preg_match('/define\([\'"]DOL_VERSION[\'"],\s*[\'"]([^\'"]+)/', $versionSource, $versionMatch)) {
		throw new RuntimeException('Cannot identify the Dolibarr core version.');
	}
	define('DOL_VERSION', $versionMatch[1]);
}

require_once $core.'/core/lib/functions.lib.php';
require_once $core.'/core/class/translate.class.php';
$conf = (object) array('entity' => 2, 'global' => (object) array(), 'file' => (object) array('dol_document_root' => array('main' => $core, 'test' => dirname($module)), 'dol_url_root' => array('main' => '', 'test' => '/custom')));
$langs = new Translate('', $conf);
$langs->defaultlang = 'fr_FR';
$langs->tab_translate = array('SeparatorDecimal' => ',', 'SeparatorThousand' => ' ', 'PowerPlantPVConsultPowerplant' => 'Consulter la centrale', 'PowerPlantPVMultipleStatuses' => 'États multiples');
require_once $module.'/class/powerplantpvpowerplantsummary.class.php';
$checks = 0;
function nativeMaintenanceCheck($condition, $message)
{
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($message); }
}
$summary = (new ReflectionClass('PowerPlantPVPowerPlantSummary'))->newInstanceWithoutConstructor();
$method = new ReflectionMethod($summary, 'buildMaterialRows');
$method->setAccessible(true);
$base = array('id' => 1, 'fk_product' => 10, 'category_code' => 'MODULE', 'product_ref' => 'PV-450', 'product_label' => 'Panneau <450>', 'qty' => 1, 'pmax' => 450, 'ac_nominal_power' => null, 'ac_max_power' => null, 'serial_number' => 'SECRET-SERIES', 'status' => 'Actif');
$rows = $method->invoke($summary, array($base, $base), array('MODULE'), $langs, 'module', 22);
nativeMaintenanceCheck(count($rows) === 1, 'Native summary consolidates product rows');
nativeMaintenanceCheck(price2num($rows[0]['qty']) == 2, 'Native locale quantity equals two');
nativeMaintenanceCheck($rows[0]['power'] === '450 W', 'Unit power remains 450 W');
nativeMaintenanceCheck($rows[0]['serial'] === 'Consulter la centrale' && $rows[0]['serial_powerplant_id'] === 22, 'Native summary carries a safe plant target, not serial data');
nativeMaintenanceCheck(strpos(json_encode($rows), 'SECRET-SERIES') === false, 'No serial leaked in display rows');
$other = $base; $other['status'] = 'Inactif';
$rows = $method->invoke($summary, array($base, $other), array('MODULE'), $langs, 'module', 22);
nativeMaintenanceCheck($rows[0]['status'] === 'États multiples', 'Mixed equipment statuses consolidated');
// Read the native descriptor migration on a simulated existing schema.
require_once $module.'/core/modules/modPowerPlantPV.class.php';
nativeMaintenanceCheck(method_exists('CommonObject', 'indexFile') && function_exists('getMultidirOutput'), 'Native document contracts exist on the tested core');
$descriptor = (new ReflectionClass('modPowerPlantPV'))->newInstanceWithoutConstructor();
nativeMaintenanceCheck(method_exists($descriptor, 'init'), 'Descriptor loads before activation without installed tables');

require_once $module.'/class/powerplantpvreportbuilder.class.php';
$builder = (new ReflectionClass('PowerPlantPVReportBuilder'))->newInstanceWithoutConstructor();
$guess = new ReflectionMethod($builder, 'guessEquipmentType'); $guess->setAccessible(true);
nativeMaintenanceCheck($guess->invoke($builder, (object) array('category_code'=>'ONDACC','inverter_id'=>20,'product_ref'=>'INV-CABLE','product_label'=>'Accessoire onduleur')) === 'OTHER', 'Native accessory category is not an installed inverter');
nativeMaintenanceCheck($guess->invoke($builder, (object) array('category_code'=>'MODULE','product_ref'=>'PV','product_label'=>'Panel')) === 'PANEL', 'Native PV category identifies panel serial policy');
$measure = (new ReflectionClass('PowerPlantPVReportDcMeasure'))->newInstanceWithoutConstructor();
$measure->stable_key = 'plant:line:1:dc:inverter:1:mppt:1:pvinput:1';
$measure->inverter_label = 'Original'; $measure->mppt_number = 1; $measure->pv_input_number = 1; $measure->is_connected = 1;
$assign = new ReflectionMethod($builder, 'assignSubmittedValueToDcMeasure'); $assign->setAccessible(true);
$assign->invoke($builder, $measure, array('inverter_label' => 'Falsified', 'mppt_number' => '99', 'pv_input_number' => '99', 'is_connected' => '0', 'open_circuit_voltage' => '0', 'polarity_checked' => '0'));
nativeMaintenanceCheck($measure->inverter_label === 'Original' && $measure->mppt_number === 1 && $measure->pv_input_number === 1 && $measure->is_connected === 1, 'Posted hidden controls cannot alter generated equipment topology');
nativeMaintenanceCheck($measure->open_circuit_voltage === 0.0 && $measure->polarity_checked === 0, 'Explicit measurement zero remains valid');
$measure->stable_key = 'section:dc:manual:1';
$assign->invoke($builder, $measure, array('inverter_label' => 'Manual', 'mppt_number' => '2', 'pv_input_number' => '3'));
nativeMaintenanceCheck($measure->inverter_label === 'Manual' && $measure->mppt_number === 2 && $measure->pv_input_number === 3, 'Manual topology remains editable');

class NativeMaintenanceSchemaDb
{
 public $columns = array(); public $index = false; public $ddl = array();
 public function prefix() { return 'long_test_prefix_'; }
 public function sanitize($value) { return $value; }
 public function escape($value) { return $value; }
 public function idate($value) { return '2000-01-01 00:00:00'; }
 public function query($sql) {
  if (strpos($sql, 'CREATE TABLE') === 0) { return true; }
  if (preg_match("/SHOW COLUMNS FROM ([a-z_]+) LIKE '((?:equipment|inverter)_(?:marker|identification)|fk_product)'/", $sql, $m)) {
   return (object) array('count' => isset($this->columns[$m[1].'.'.$m[2]]) ? 1 : 0);
  }
  if (strpos($sql, 'SHOW INDEX') === 0 && strpos($sql, 'uk_ppv_comp_marker') !== false) { return (object) array('count' => $this->index ? 1 : 0); }
  if (strpos($sql, 'ALTER TABLE') === 0) { $this->index = true; $this->ddl[] = $sql; return true; }
  if (strpos($sql, 'SHOW ') === 0) { return (object) array('count' => 1); }
  throw new RuntimeException('Unexpected migration mutation: '.$sql);
 }
 public function num_rows($result) { return $result->count; }
 public function free($result) {}
 public function DDLAddField($table, $field, $definition) { $this->columns[$table.'.'.$field] = true; $this->ddl[] = $table.'.'.$field; return 1; }
 public function lasterror() { return 'Simulated schema error'; }
}
$schema = new NativeMaintenanceSchemaDb();
$dbProperty = new ReflectionProperty($descriptor, 'db'); $dbProperty->setAccessible(true); $dbProperty->setValue($descriptor, $schema);
$migration = new ReflectionMethod($descriptor, 'ensureGeneratedReportSchema'); $migration->setAccessible(true);
nativeMaintenanceCheck($migration->invoke($descriptor) === 1 && count($schema->columns) === 7 && $schema->index, 'Existing schema receives six nullable identity columns and the module product relationship and a unique marker index');
$count = count($schema->ddl);
nativeMaintenanceCheck($migration->invoke($descriptor) === 1 && count($schema->ddl) === $count, 'Repeated activation does not recreate columns or indexes');

// Exercise the actual PDF HTML renderers with a capture object, not a rendered PDF.
if (is_file(DOL_DOCUMENT_ROOT.'/core/lib/html.lib.php')) { require_once DOL_DOCUMENT_ROOT.'/core/lib/html.lib.php'; }
require_once $module.'/core/modules/fichinter/doc/pdf_powerplantpvreport.modules.php';
$pdfModel = (new ReflectionClass('pdf_powerplantpvreport'))->newInstanceWithoutConstructor();
$pdfModel->page_hauteur = 297;
$languageProperty = new ReflectionProperty($pdfModel, 'currentOutputLangs'); $languageProperty->setAccessible(true); $languageProperty->setValue($pdfModel, $langs);
$capture = new class {
 public $html = '';
 public function GetY() { return 20; }
 public function writeHTMLCell($w, $h, $x, $y, $html, ...$rest) { $this->html .= $html; }
 public function Ln($height) {}
};
$_SERVER['HTTP_HOST'] = 'example.test'; $_SERVER['SERVER_PORT'] = 443; $_SERVER['HTTPS'] = 'on';
$render = new ReflectionMethod($pdfModel, 'renderPowerplantSummaryTable'); $render->setAccessible(true);
$render->invokeArgs($pdfModel, array(&$capture, array(array('key'=>'serial','label_key'=>'SerialNumber')), $rows, $langs));
nativeMaintenanceCheck(strpos($capture->html, 'href=') !== false && strpos($capture->html, 'id=22') !== false && strpos($capture->html, 'SECRET-SERIES') === false, 'PDF summary emits the plant link instead of serials');
$capture->html = '';
$render = new ReflectionMethod($pdfModel, 'renderEquipmentSummary'); $render->setAccessible(true);
$panel = (object) array('equipment_type'=>'PANEL','equipment_brand'=>'Brand','equipment_model'=>'450 W','product_label'=>'Panel','product_ref'=>'PV-450','equipment_ref'=>'PV-450','serial_number'=>'SECRET-PANEL-SERIAL','equipment_position'=>'');
$render->invokeArgs($pdfModel, array(&$capture, $panel, $langs));
nativeMaintenanceCheck(strpos($capture->html, 'SECRET-PANEL-SERIAL') === false, 'Individual panel control summary does not leak a panel serial');
require_once DOL_DOCUMENT_ROOT.'/ecm/class/ecmfiles.class.php';
$db = $schema;
$filterError = '';
$filterSql = forgeSQLFromUniversalSearchCriteria('(t.entity:=:2) AND (t.src_object_id:=:99)', $filterError);
nativeMaintenanceCheck($filterError === '' && strpos($filterSql, 't.entity') !== false && method_exists('EcmFiles', 'fetchAll'), 'Owner-specific ECM lookup uses a native v20+ USF contract');

require_once $module.'/class/powerplantpvequipmentnumbering.class.php';
class NativeEquipmentCounterDb
{
 public $last = null; public $nb = 0; public $queries = array();
 public function prefix() { return MAIN_DB_PREFIX; }
 public function escape($value) { return addslashes($value); }
 public function sanitize($value) { return $value; }
 public function query($sql) { $this->queries[] = $sql; return (object) array('nb' => $this->nb, 'val' => $this->last); }
 public function fetch_object($row) { return $row; }
 public function lasterror() { return 'Database failure'; }
}
$counterDb = new NativeEquipmentCounterDb();
$inverterModel = new PowerPlantPVEquipmentNumbering($counterDb, 'inverter');
$stringModel = new PowerPlantPVEquipmentNumbering($counterDb, 'string');
$scope = (object) array('id' => 21, 'entity' => 2, 'fk_inverter' => 31);
nativeMaintenanceCheck($inverterModel->getNextValue($scope, 'OND{00}') === 'OND01', 'Native two-digit inverter counter starts at one');
nativeMaintenanceCheck(!isset($conf->global->MAIN_COUNTER_WITH_LESS_3_DIGITS), 'Short-counter allowance does not leak to other modules');
nativeMaintenanceCheck($stringModel->getNextValue($scope, 'PV{00}') === 'PV00', 'First chain starts at zero');
$counterDb->last = '0'; $counterDb->nb = 1;
nativeMaintenanceCheck($stringModel->getNextValue($scope, 'PV{00}') === 'PV01', 'PV00 advances to PV01');
$counterDb->last = '9';
nativeMaintenanceCheck($stringModel->getNextValue($scope, 'TOIT-{000}') === 'TOIT-010', 'Free prefix and native padding retained');
$queries = implode('\n', $counterDb->queries);
nativeMaintenanceCheck(strpos($queries, 'fk_powerplant = 21 AND entity = 2 AND fk_inverter = 31') !== false, 'Chain counter constrained by plant, owner and installed inverter');
$counterDb->last = null; $counterDb->nb = 0; $scope->fk_inverter = 32;
nativeMaintenanceCheck($stringModel->getNextValue($scope, 'PV{00}') === 'PV00', 'Another inverter starts its own zero counter');
nativeMaintenanceCheck($stringModel->getNextValue($scope, 'PV00') === '' && $stringModel->error === 'ErrorBadMask', 'Invalid mask rejected before allocation');
$counterDb->last = '99'; $counterDb->nb = 1;
nativeMaintenanceCheck($inverterModel->getNextValue($scope, 'OND{00}') === '', 'Exhausted native counter fails instead of truncating or duplicating');
print 'OK: '.$checks.' native checks, PHP '.PHP_VERSION.', Dolibarr '.DOL_VERSION." (no live database).\n";
