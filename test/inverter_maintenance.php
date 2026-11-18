<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
// Isolated behavioral tests. Database/ERP collaborators are simulated; files are real temporary files.
if (PHP_SAPI !== 'cli') { exit(1); }
require_once dirname(__DIR__).'/class/powerplantpvreportpresentation.class.php';
require_once dirname(__DIR__).'/class/powerplantpvequipmentidentity.class.php';
require_once dirname(__DIR__).'/class/powerplantpvreportserialannex.class.php';

$checks = 0;
function checkMaintenance($condition, $message)
{
	$GLOBALS['checks']++;
	if (!$condition) { throw new RuntimeException($message); }
}

$components = array();
for ($i = 0; $i < 1000; $i++) {
	$components[] = array('id' => $i + 1, 'fk_powerplant' => 2, 'fk_product' => 10, 'qty' => 1, 'status' => 'Active', 'pmax' => 450, 'serial_number' => 'PV'.$i);
}
$components[999]['status'] = 'Inactive';
$consolidated = PowerPlantPVReportPresentation::consolidate($components);
checkMaintenance(count($consolidated) === 1, '1000 panels produce one material line');
checkMaintenance($consolidated[0]['qty'] === 1000.0 && $consolidated[0]['pmax'] === 450, 'Quantity is summed and unit power unchanged');
checkMaintenance($consolidated[0]['multiple_statuses'] && $consolidated[0]['serial_number'] === '', 'Mixed statuses and no serial list');
$components[] = array('id' => 2000, 'fk_powerplant' => 3, 'fk_product' => 10, 'qty' => 1, 'status' => 'Active');
checkMaintenance(count(PowerPlantPVReportPresentation::consolidate($components)) === 2, 'Different owner plants never collapse');

$measures = array();
foreach (array(10 => 'O10', 2 => 'O2', 1 => 'O1') as $id => $marker) {
	foreach (array(2, 1) as $mppt) {
		foreach (array(2, 1) as $input) {
			$measures[] = (object) array('fk_powerplant' => 2, 'fk_inverter' => $id, 'inverter_marker' => $marker, 'inverter_identification' => 'Identical product', 'inverter_ref' => 'SAME', 'inverter_label' => 'SAME', 'inverter_serial' => 'SN'.$id, 'mppt_number' => $mppt, 'pv_input_number' => $input, 'position' => 0, 'stable_key' => $id.':'.$mppt.':'.$input);
		}
	}
}
$previousDate = 1700000000;
$endDate = $previousDate + 300 * 86400;
$meterFields = array(
	(object) array('field_code' => 'PRODUCTION_INDEX', 'value_number' => 1900, 'unit' => 'kWh'),
	(object) array('field_code' => 'PRODUCTION_INDEX_N_MINUS_1', 'value_number' => 1000, 'unit' => 'kWh', 'value_date' => $previousDate),
	(object) array('field_code' => 'INJECTION_INDEX', 'value_number' => 1300, 'unit' => 'kWh'),
	(object) array('field_code' => 'INJECTION_INDEX_N_MINUS_1', 'value_number' => 800, 'unit' => 'kWh', 'value_date' => $previousDate),
);
checkMaintenance(PowerPlantPVReportPresentation::selfConsumptionBetweenReadings($meterFields, $endDate) === 400.0, 'Self-consumption is calculated over the actual period without annualization');
checkMaintenance(PowerPlantPVReportPresentation::calculateSelfConsumption(500, 1500, 100, 500) === array('energy' => 600.0, 'rate' => 60.0), 'Self-consumption rate uses the energy produced over the period');
checkMaintenance(PowerPlantPVReportPresentation::calculateSelfConsumption(500, 500, 100, 100) === array('energy' => 0.0, 'rate' => null), 'Zero production has no rate');
checkMaintenance(PowerPlantPVReportPresentation::calculateSelfConsumption(500, 600, 100, 201) === null, 'Injected energy cannot exceed produced energy');
$meterFields[2]->value_number = 1700;
checkMaintenance(PowerPlantPVReportPresentation::selfConsumptionBetweenReadings($meterFields, $endDate) === 0.0, 'A valid zero self-consumption is retained');
$meterFields[2]->value_number = 700;
checkMaintenance(PowerPlantPVReportPresentation::selfConsumptionBetweenReadings($meterFields, $endDate) === null, 'Counter reset is not interpreted as consumption');
$meterFields[2]->value_number = 1300;
$meterFields[3]->value_date = $previousDate - 86400;
checkMaintenance(PowerPlantPVReportPresentation::selfConsumptionBetweenReadings($meterFields, $endDate) === null, 'Different start dates cannot be compared');
$meterFields[3]->value_date = null;
checkMaintenance(PowerPlantPVReportPresentation::selfConsumptionBetweenReadings($meterFields, $endDate) === null, 'Historical readings with unknown dates are not extrapolated');
$groups = PowerPlantPVReportPresentation::groupMeasures($measures);
checkMaintenance(count($groups) === 3, 'Identical catalog products remain three physical inverter groups');
checkMaintenance(array_keys($groups) === array('2:line:1', '2:line:2', '2:line:10'), 'Natural marker order O1 O2 O10');
checkMaintenance(array_keys($groups['2:line:1']['mppts']) === array(1, 2), 'MPPTs numerically ordered');
checkMaintenance($groups['2:line:1']['mppts'][1][0]->pv_input_number === 1, 'Inputs numerically ordered');
$legacy = array();
foreach (array(array('10', 1, 1), array('2', 2, 1), array('2', 1, 2), array(' 2 ', 1, 1)) as $position => $values) {
	$legacy[] = (object) array('fk_powerplant' => 2, 'fk_inverter' => 0, 'inverter_label' => $values[0], 'mppt_number' => $values[1], 'pv_input_number' => $values[2], 'position' => $position, 'stable_key' => 'manual:'.$position);
}
$otherPlant = clone $legacy[1];
$otherPlant->fk_powerplant = 3;
$legacyGroups = PowerPlantPVReportPresentation::groupMeasures(array_merge($legacy, array($otherPlant)));
checkMaintenance(array_keys($legacyGroups) === array('2:manual:label:2', '2:manual:label:10', '3:manual:label:2'), 'Legacy inverter labels group within each plant in natural order');
checkMaintenance(array_keys($legacyGroups['2:manual:label:2']['mppts']) === array(1, 2), 'Legacy MPPTs are grouped and ordered numerically');
checkMaintenance(array_column($legacyGroups['2:manual:label:2']['mppts'][1], 'pv_input_number') === array(1, 2), 'Legacy inputs are ordered numerically within each MPPT');
$identity = $groups['2:line:1']['identity'];
checkMaintenance(PowerPlantPVReportPresentation::identity($identity, false) === 'O1 | Identical product', 'Mobile identity excludes serial');
checkMaintenance(PowerPlantPVReportPresentation::identity($identity) === 'O1 | Identical product | SN1', 'Desktop identity includes serial');
$identity->inverter_marker = '';
$identity->inverter_identification = '';
checkMaintenance(strpos(PowerPlantPVReportPresentation::identity($identity, false), '#1') !== false, 'Unnamed historic equipment has a distinct source fallback');
checkMaintenance(PowerPlantPVReportPresentation::csvText('000123') === "'000123", 'Serial leading zeroes protected as text');
checkMaintenance(PowerPlantPVReportPresentation::csvText("\t=HYPERLINK(\"bad\")")[0] === "'", 'Whitespace-prefixed formula neutralized');

class MaintenanceIdentityTestDb
{
	public $rows = array();
	public $updates = array();
	public $fail = false;
	public function prefix() { return 'long_test_prefix_'; }
	public function escape($value) { return str_replace("'", "''", $value); }
	public function lasterror() { return 'Expected database failure'; }
	public function query($sql)
	{
		if ($this->fail) { return false; }
		if (strpos($sql, 'FOR UPDATE') !== false) { return (object) array('rows' => array((object) array('rowid' => 2))); }
		if (strpos($sql, 'SELECT') === 0) { return (object) array('rows' => $this->rows); }
		$this->updates[] = $sql;
		return true;
	}
	public function fetch_object($result) { return array_shift($result->rows); }
}
$langs = new class {
	public function loadLangs($catalogues) {}
	public function transnoentities($key) { return $key === 'PowerPlantPVInverter' ? 'Onduleur' : $key; }
};
$db = new MaintenanceIdentityTestDb();
$db->rows = array(
	(object) array('rowid' => 1, 'qty' => 1, 'inverter_id' => 20, 'equipment_marker' => 'O1', 'equipment_identification' => 'North'),
	(object) array('rowid' => 2, 'qty' => 1, 'inverter_id' => 20, 'equipment_marker' => null, 'equipment_identification' => null),
	(object) array('rowid' => 3, 'qty' => 1, 'inverter_id' => 20, 'equipment_marker' => 'O3', 'equipment_identification' => 'South'),
);
$service = new PowerPlantPVEquipmentIdentity($db);
checkMaintenance($service->save(2, 2, 2, 'O2', 'Onduleur 2', true) === 1, 'Explicit identity creation succeeds');
checkMaintenance(strpos(end($db->updates), "equipment_marker = 'O2'") !== false, 'Requested marker stored, existing identities preserved');
$revision = PowerPlantPVEquipmentIdentity::revision($db->rows[1]);
$db->updates = array();
checkMaintenance($service->save(2, 2, 2, ' o1 ', 'Duplicate', false, $revision) < 0 && !$db->updates, 'Case-insensitive trimmed duplicate refused before mutation');
checkMaintenance($service->save(2, 2, 2, 'O2', 'Changed', false, 'obsolete') < 0 && !$db->updates, 'Stale identity edit refused');
$db->rows[1]->qty = 2;
checkMaintenance($service->save(2, 2, 2, 'O2', 'Grouped', false, $revision) < 0 && !$db->updates, 'Grouped quantity cannot acquire an individual identity');
$db->rows[1]->qty = 1;
$db->fail = true;
checkMaintenance($service->save(2, 2, 2, 'O2', 'Changed', false, $revision) < 0, 'Database failure is not success');

$db->fail = false; $db->rows[1]->category_code = 'ONDACC'; $db->updates = array();
checkMaintenance($service->save(2, 2, 2, '', '', true) === 1 && !$db->updates, 'An inverter accessory does not acquire a physical inverter marker');


require_once dirname(__DIR__).'/class/powerplantpvstringallocation.class.php';
if (!function_exists('getEntity')) { function getEntity($kind) { return '2'; } }
class MaintenanceAllocationDb
{
 public $queries = array(); public $fail = false;
 public function prefix() { return 'test_'; }
 public function sanitize($s) { return $s; }
 public function query($sql) {
  $this->queries[] = $sql;
  if ($this->fail) { return false; }
  return (object) array('rows' => strpos($sql, 'SELECT c.fk_product') === 0
   ? array((object) array('fk_product'=>10, 'qty'=>12, 'ref'=>'PV450', 'label'=>'Panel', 'pmax'=>450))
   : array((object) array('fk_inverter'=>31, 'fk_product'=>10, 'module_count'=>3), (object) array('fk_inverter'=>32, 'fk_product'=>10, 'module_count'=>4)));
 }
 public function fetch_object($r) { return array_shift($r->rows); }
 public function lasterror() { return 'Allocation read failed'; }
}
$allocationDb = new MaintenanceAllocationDb();
$allocation = new PowerPlantPVStringAllocation($allocationDb, 21, 2);
checkMaintenance($allocation->modules()[10]['power'] === 450.0, 'Module power comes from detailed product characteristics');
$counts = $allocation->connected(31);
checkMaintenance($counts['inverter'] === 3 && 12 - $counts['total'] === 5, 'Inverter count and remaining plant modules include all other inverters');
checkMaintenance($allocation->validate(10, 5, 1, 31, 1, 1), 'Available five modules may be connected');
checkMaintenance(!$allocation->validate(10, 6, 1, 31, 1, 1), 'Over-allocation refused');
checkMaintenance(!$allocation->validate(99, 1, 1, 31, 1, 1), 'Product outside composition refused');
checkMaintenance($allocation->validate(10, 0, 1, 31, 1, 1), 'Explicit zero module count is valid');
checkMaintenance(strpos(implode('\n', $allocationDb->queries), 'AND NOT (fk_inverter = 31 AND mppt_number = 1 AND pv_input_number = 1)') !== false, 'Current string excluded when editing its allocation');
$allocationDb->fail = true;
checkMaintenance(!$allocation->validate(10, 1, 1, 31, 1, 1), 'Database failure refuses allocation');

// Real filesystem tests for ownership and idempotence of the frozen annex.
$temporary = sys_get_temp_dir().'/ppv-maintenance-'.bin2hex(random_bytes(8));
mkdir($temporary.'/entity2', 0777, true);
define('DOL_DATA_ROOT', $temporary);
define('DOL_DOCUMENT_ROOT', $temporary);
mkdir($temporary.'/ecm/class', 0777, true);
file_put_contents($temporary.'/ecm/class/ecmfiles.class.php', "<?php\n");
function getMultidirOutput($object, $module, $forobject) { return $GLOBALS['conf']->{$module}->multidir_output[$object->entity].($forobject ? '/'.$object->ref : ''); }
function dol_mkdir($dir) { return is_dir($dir) || mkdir($dir, 0777, true) ? 1 : -1; }
function dol_sanitizeFileName($name) { return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name); }
function dol_delete_file($path) { return unlink($path); }
#[AllowDynamicProperties]
class EcmFiles
{
	public static $owners = array();
	public static $records = array();
	public $lines = array();
	public function __construct($db) {}
	public function fetchAll($sort, $field, $limit, $offset, $filter) {
		if (strpos($filter, '(t.entity:=:2)') === false) { throw new RuntimeException('Owner entity missing in ECM lookup'); }
		$this->lines = self::$records; return count($this->lines);
	}
	public function create($user) { self::$owners[] = $this->entity; self::$records[] = (object) array('filepath'=>$this->filepath,'filename'=>$this->filename,'src_object_type'=>$this->src_object_type); return 1; }
}
$conf = (object) array('entity' => 3, 'ficheinter' => (object) array('multidir_output' => array(2 => $temporary.'/entity2')));
$user = new stdClass();
$intervention = (object) array('id' => 99, 'entity' => 2, 'ref' => 'FI-99', 'table_element' => 'fichinter');
$plant = (object) array('powerplant_ref' => 'PLANT-2', 'technical_snapshot' => json_encode(array('serial_inventory' => array(array('product_ref' => 'PV', 'product_label' => 'Panel', 'serial' => '000123')))));
$tree = array('powerplants' => array($plant));
$annex = new PowerPlantPVReportSerialAnnex();
try {
	checkMaintenance($annex->generate($intervention, $tree) === 1, 'Frozen annex generated in owner entity');
	$path = $temporary.'/entity2/FI-99/'.$annex->filename;
	$contents = file_get_contents($path);
	checkMaintenance(strpos($contents, "'000123") !== false, 'Historical serial exported as protected text');
	checkMaintenance(EcmFiles::$owners === array(2), 'ECM index uses owner, not consultation entity');
	checkMaintenance($annex->generate($intervention, $tree) === 1 && file_get_contents($path) === $contents && EcmFiles::$owners === array(2), 'Repeated generation does not overwrite content');
	$plant->technical_snapshot = '{}';
	checkMaintenance($annex->generate($intervention, $tree) < 0, 'Old snapshot cannot borrow current serials');
	unset($conf->ficheinter->multidir_output[2]);
	checkMaintenance($annex->generate($intervention, $tree) < 0, 'Missing owner directory refused without fallback');
} finally {
	foreach (glob($temporary.'/entity2/FI-99/*') ?: array() as $file) { unlink($file); }
	if (is_dir($temporary.'/entity2/FI-99')) { rmdir($temporary.'/entity2/FI-99'); }
	rmdir($temporary.'/entity2'); unlink($temporary.'/ecm/class/ecmfiles.class.php');
	rmdir($temporary.'/ecm/class'); rmdir($temporary.'/ecm'); rmdir($temporary);
}
$emptyManual = (object) array('stable_key' => 'section:dc:manual:1', 'is_connected' => 1);
$unusedInput = (object) array('stable_key' => 'installed:1', 'is_connected' => 0);
$usedInput = (object) array('stable_key' => 'installed:2', 'is_connected' => 1);
checkMaintenance(count(PowerPlantPVReportPresentation::visibleMeasures(array($emptyManual, $unusedInput, $usedInput), true)) === 1, 'Screen option hides unused inputs and the empty manual placeholder');
checkMaintenance(count(PowerPlantPVReportPresentation::visibleMeasures(array($emptyManual, $unusedInput, $usedInput), false)) === 2, 'PDF retains unused installed inputs independently of the screen option');
$emptyManual->open_circuit_voltage = 0;
checkMaintenance(count(PowerPlantPVReportPresentation::visibleMeasures(array($emptyManual), false)) === 1, 'Legacy manual measurement zero is retained');
// Execute the real page validators without bootstrapping a web session.
$reportPage = file_get_contents(dirname(__DIR__).'/maintenance_intervention_report.php');
foreach (array('powerplantpvReportFindMissingRequiredFields', 'powerplantpvReportRequiredFieldIsEmpty', 'powerplantpvReportIsHtmlTextEmpty', 'powerplantpvReportLocalizedObjectLabel', 'powerplantpvReportFieldIsPreviousReading', 'powerplantpvReportIsNumericFieldType') as $functionName) {
	if (!preg_match('/^function '.preg_quote($functionName, '/').'\([^\n]*\)\R\{.*?^\}/ms', $reportPage, $match)) { throw new RuntimeException('Missing report validator '.$functionName); }
	eval($match[0]);
}
class PowerPlantPVReportField
{
	public static $rows = array();
	public function __construct($db) {}
	public function fetchAllByReport($id, $sort = '', $order = '') { return self::$rows; }
}
class PowerPlantPVReportEquipment
{
	public function __construct($db) {}
	public function fetchAllByReport($id, $sort = '', $order = '') { return array((object) array('equipment_type' => 'INVERTER', 'equipment_marker' => '', 'equipment_identification' => '', 'qty' => 2, 'fk_powerplant_line' => 9232)); }
}
$langs = new class {
	public $defaultlang = 'fr_FR';
	public function trans($key) { return $key; }
};
$requiredField = (object) array('is_required' => 1, 'readonly' => 0, 'visible_form' => 1, 'field_type' => 'number', 'field_code' => 'TEST', 'field_label' => 'Mesure requise', 'value_number' => 0);
PowerPlantPVReportField::$rows = array($requiredField);
checkMaintenance(powerplantpvReportFindMissingRequiredFields(1) === array(), 'Completed report with zero measurement and legacy unnamed inverters may finalize');
$requiredField->value_number = null;
checkMaintenance(powerplantpvReportFindMissingRequiredFields(1) === array('Mesure requise'), 'Missing required measurement still blocks finalization');
$requiredField->field_type = 'yesno'; $requiredField->value_text = '0';
checkMaintenance(powerplantpvReportFindMissingRequiredFields(1) === array(), 'Explicit No remains a completed required answer');
$requiredField->field_code = 'BOX_JOINTS_HINGES_CLEANING';
$requiredField->value_text = 'not_applicable';
checkMaintenance(powerplantpvReportFindMissingRequiredFields(1) === array(), 'Cabinet cleaning can be marked not applicable');
$requiredField->field_code = 'BOX_SECTION_SWITCH';
checkMaintenance(powerplantpvReportFindMissingRequiredFields(1) === array('Mesure requise'), 'Other yes/no checks still require an explicit yes or no');

require_once dirname(__DIR__).'/class/powerplantpvinverterproduction.class.php';
require_once dirname(__DIR__).'/class/powerplantpvreportillustration.class.php';
$globalSection = (object) array('id' => 10, 'rowid' => 10, 'section_code' => 'PRODUCTION_READING', 'fk_report_equipment' => 0, 'fk_report_powerplant' => 6, 'occurrence_key' => 'section:PRODUCTION_READING:powerplant:6');
$production = (object) array('id' => 20, 'field_code' => 'INVERTER_PRODUCTION', 'value_number' => 123, 'value_text' => null, 'value_date' => null, 'field_label' => 'Production N', 'field_label_en' => 'Production N', 'is_required' => 1, 'visible_form' => 1, 'visible_pdf' => 1, 'readonly' => 0);
$previous = clone $production; $previous->field_code = 'INVERTER_PRODUCTION_N_MINUS_1';
$meter = clone $production; $meter->field_code = 'PRODUCTION_INDEX';
$tree = array('report' => (object) array('status' => 'draft'), 'sections' => array(array('section' => $globalSection, 'fields' => array($production, $previous, $meter), 'powerplant' => (object) array('id' => 6))), 'equipment' => array());
checkMaintenance(count(PowerPlantPVInverterProduction::expand($tree)['sections']) === 1, 'Plant without installed inverter retains its global readings');
foreach (array(114, 115, 116) as $i => $source) { $tree['equipment'][] = (object) array('id' => $i + 1, 'equipment_type' => 'INVERTER', 'fk_report_powerplant' => 6, 'fk_powerplant_line' => $source, 'fk_source_equipment' => 0); }
$expanded = PowerPlantPVInverterProduction::expand($tree);
checkMaintenance(count($expanded['sections']) === 4, 'Three installed inverters receive three distinct production sections');
checkMaintenance(count($expanded['sections'][0]['fields']) === 3 && $expanded['sections'][0]['fields'][2] === $meter, 'Plant production meter stays common and unchanged');
checkMaintenance($expanded['sections'][0]['fields'][0]->readonly === 1 && $expanded['sections'][0]['fields'][0]->value_number === 123, 'Legacy global value retained read-only without arbitrary attribution');
checkMaintenance($expanded['sections'][1]['fields'][0]->value_number === null && $production->value_number === 123, 'New inverter values start empty and source is not mutated');
checkMaintenance($expanded['sections'][1]['fields'][0]->stable_key !== $expanded['sections'][2]['fields'][0]->stable_key, 'Identical inverter models have distinct stable reading keys');
checkMaintenance($expanded['sections'][1]['fields'][1]->readonly === 1, 'Per-inverter previous reading is read-only');
checkMaintenance($expanded['sections'][1]['fields'][2]->field_code === 'INVERTER_INJECTION'
	&& $expanded['sections'][1]['fields'][2]->value_number === null
	&& $expanded['sections'][1]['fields'][2]->is_required === 0, 'Inverter injection is an optional independent reading');
checkMaintenance($expanded['sections'][1]['fields'][3]->readonly === 1
	&& $expanded['sections'][1]['fields'][2]->stable_key !== $expanded['sections'][2]['fields'][2]->stable_key, 'Injection history is read-only and scoped to each inverter');
$reexpanded = PowerPlantPVInverterProduction::expand($expanded);
checkMaintenance(count($reexpanded['sections']) === 4 && count($reexpanded['sections'][1]['fields']) === 4, 'Repeated draft loading never duplicates inverter sections or fields');
$tree['report']->status = 'saved';
checkMaintenance(count(PowerPlantPVInverterProduction::expand($tree)['sections']) === 1, 'Finalized historic reports are not expanded or rewritten');
$stats = PowerPlantPVReportPresentation::indicators(array(array('fields' => array((object) array('visible_pdf' => 1, 'field_type' => 'conformity_so_valid_obs', 'value_text' => 'observation'), (object) array('visible_pdf' => 1, 'field_type' => 'conformity_so_valid_obs', 'value_text' => ''), (object) array('visible_pdf' => 1, 'field_type' => 'yesno', 'value_text' => '0')), 'dc_measures' => array($unusedInput, $usedInput))));
checkMaintenance($stats === array('checks' => 2, 'completed' => 1, 'observations' => 1, 'connected' => 1, 'inputs' => 2), 'PDF indicators distinguish unanswered checks, explicit observations, No answers and unused inputs');
$testDirectory = sys_get_temp_dir().'/ppv-image-'.bin2hex(random_bytes(6)); mkdir($testDirectory);
file_put_contents($testDirectory.'/photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
file_put_contents($testDirectory.'/false.png', '<?php echo 1;');
checkMaintenance(PowerPlantPVReportIllustration::imagePath($testDirectory, 'photo.png') !== '', 'Actual attached PNG is accepted');
checkMaintenance(PowerPlantPVReportIllustration::imagePath($testDirectory, '../photo.png') === '', 'Parent traversal cannot select another plant image');
checkMaintenance(PowerPlantPVReportIllustration::imagePath($testDirectory, 'false.png') === '', 'Filename extension cannot disguise non-image content');
unlink($testDirectory.'/photo.png'); unlink($testDirectory.'/false.png'); rmdir($testDirectory);

// ECM may use the table name as source type and a logical path through a symlinked data root.
$ownerRoot = DOL_DATA_ROOT.'/module'; $ownerDir = $ownerRoot.'/powerplant/OWNER';
mkdir($ownerDir, 0777, true);
file_put_contents($ownerDir.'/photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
$conf->powerplantpv = (object) array('multidir_output' => array(2 => $ownerRoot));
if (!defined('MAIN_DB_PREFIX')) { define('MAIN_DB_PREFIX', 'fixture_'); }
$imageDb = new class {
 public $rows; public $sql;
 public function escape($value) { return str_replace("'", "''", $value); }
 public function query($sql) { $this->sql = $sql; return (object) array(); }
 public function fetch_object($result) { return array_shift($this->rows); }
 public function free($result) {}
};
$imageDb->rows = array((object) array('rowid' => 1, 'filename' => 'photo.png', 'filepath' => 'module/powerplant/OWNER'), (object) array('rowid' => 2, 'filename' => 'photo.png', 'filepath' => 'module/powerplant/OTHER'));
$plantImage = (object) array('id' => 6, 'ref' => 'OWNER', 'entity' => 2, 'element' => 'powerplant', 'table_element' => 'powerplantpv_powerplant');
$imageService = new PowerPlantPVReportIllustration($imageDb);
$eligible = $imageService->files($plantImage);
checkMaintenance(is_array($eligible) && array_keys($eligible) === array(1), 'ECM source association never admits an image outside the owning plant directory');
checkMaintenance(strpos($imageDb->sql, "'powerplantpv_powerplant'") !== false && strpos($imageDb->sql, 'entity = 2') !== false, 'Image lookup supports the native table source type and exact owner entity');
unlink($ownerDir.'/photo.png'); rmdir($ownerDir); rmdir(dirname($ownerDir)); rmdir($ownerRoot); rmdir(DOL_DATA_ROOT);
print 'OK: '.$checks." behavioral checks (ERP/database collaborators simulated).\n";
