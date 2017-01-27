<?php
/**
 * Download and save suppliers feeds backend script
 *
 * @author Petr Král
 * @version $Id: run.php 13452 2012-03-30 07:47:13Z pkral1 $
 */

ini_set('memory_limit', '1G');

define('BACKEND_DEBUG', 1);
define('ORACLE_TRUE', 't');
define('ORACLE_FALSE', 'f');
define('SQL_MAX_RUNNING_TIME', 2);
define('RUNNING_START', 1);
define('RUNNING_STOP', 2);
define('BACKUP_TIMEOUT_DAYS', 14);
define('SF_STATUS_DOWNLOAD_ERROR', 'ERR');
define('SF_STATUS_DOWNLOAD_OK', 'OK');
define('RUNNING_PROGRESS', 'GO');

// define default value for sap connection
$sap = null;

// load common libs
require_once dirname(__FILE__) . '/../lib/common-cli.inc.php';

// load SAP functions
require_once dirname(__FILE__) . '/../lib/common_sap.inc.php';

// create database instance
$db = Core::get('DB');

// create output log instance;
$output_log = new Output_Log('SUPPLIERS FEEDS');

// add posible args
$output_log->add_possible_argument('f', 'Force progress only', 'force', '-f | --force');

// process arguments
$args = $output_log->process_arguments(array(
	'f'
), array(
	'force'
));

// print outputlog start
$output_log->print_start();

$only_force = false;

// check is force mode
if (isset($args['f']) || isset($args['force'])) {
	$only_force = true;
}

// set program name
$program = ($only_force === true) ? 'supplier_feed_forced' : 'supplier_feed';

// create jobstat instance
$jobstat = new JobStat($db, $program);

// start job
$jobstat->job_start();

// set default value of complete processed records
$processed_records = 0;

try {
	$sql = "
		UPDATE " . DB_USER_MAINTENANCE . ".sf_source
		SET forcerun = '" . ORACLE_FALSE . "',
			running = '" . ORACLE_FALSE . "'
		WHERE running = '" . ORACLE_TRUE . "'
			AND (last_running < LOCALTIMESTAMP - INTERVAL '" . SQL_MAX_RUNNING_TIME . "' HOUR
			OR last_running IS NULL)
	";

	$db->Execute($sql);

	// do only force actions

	$sql = "
			SELECT src.source_id,
				src.supplier_id,
				src.purchase_organization,
				sup.name,
				src.url,
				active
			FROM " . DB_USER_MAINTENANCE . ".sf_supplier sup
			JOIN " . DB_USER_MAINTENANCE . ".sf_source src ON (src.supplier_id = sup.supplier_id)
			WHERE src.source_id in (3651) 
		";

//		182, 10403, 3511,
	//,3511,,311,182,10,2871,16,17,32,33,3311,2451,3671,3271,3592,3051,3811,
//		1231,1411,1451,1491,1492,1531,1593,10363

	// get rows
	$res = $db->Execute($sql);

	$sources = array();
	while (($row = $res->FetchRow()) !== false) {
		$sources[] = $row;
	}

	// set source list as running sources
	setSourcesRunning($db, $sources, RUNNING_START);

	// every source we need execute
	$output = array();
	$unknown_ids = array();
	$error_sources = array();

	foreach ($sources as $row) {
		try {
			// print event action executing
			$output_log->print_event('Executing supplier feed id #' . $row['source_id']);

			// create supplier instance
			$feed = new Supplier_Feed($db, $row['source_id'], $sap, $output_log);

			// load supplier feed
			$feed->load();

			// save supplier input
			$feed->save_input();

			// parse supplier feed
			$feed->parse();

			// create output data array
			$output[$row['supplier_id'] . '|' . $row['purchase_organization']][$row['source_id']] = $feed->get_data();

			// get and set unknown data
			$unknown_ids[$row['supplier_id'] . '|' . $row['purchase_organization']][$row['source_id']] = $feed->get_unknown_ids();

			// is only force progress we can not send data to SAP
			$supplier_without_call_sap = array();
			if ($only_force && $row['active'] == 'f') {
				$supplier_without_call_sap[$row['supplier_id']] = $row['supplier_id'];
			}

			// set download status as OK and write data to table
			setSourceStatusMessage($db, $row['source_id'], SF_STATUS_DOWNLOAD_OK);
		} catch (Exception $e) {
			// set status as ERR and write data to table
			setSourceStatusMessage($db, $row['source_id'], SF_STATUS_DOWNLOAD_ERROR);
			$error_sources[$row['purchase_organization']][$row['supplier_id']][$row['source_id']] = array(
				'url' => $row['url'],
				'supplier_name' => $row['name'],
				'msg' => $e->getMessage(),
				'time' => date('d.m.Y H:i:s')
			);
			$output_log->print_exception($e);
		}
	}

	if (!empty($error_sources)) {
		// try send errors messages
		$sended_emails = sendEmails($db, $error_sources);

		if ($sended_emails > 0) {
			$output_log->print_event('Sent ' . $sended_emails . ' errors messages');
		}
	}

	// group all sources from one supplier
	$columns = array(
		'supplier_id',
		'external_id',
		'variant_id',
		'ean',
		'available_from',
		'stock',
		'purchase_organization',
		'price',
		'currency',
		'valid_from',
		'valid_to'
	);
	$final = array();

	foreach ($output as $sup_id => $sup_sources) {
		foreach ($sup_sources as $src_id => $rows) {
			foreach ($rows as $article_id => $article_row) {
				foreach ($columns as $column_name) {
					if (isset($article_row[$column_name])) {
						$final[$sup_id][$article_id][$column_name] = $article_row[$column_name];
					} elseif (!isset($final[$sup_id][$article_id][$column_name])) {
						$final[$sup_id][$article_id][$column_name] = '';
					}
				}
			}
		}
	}
	unset($output);

	// print message backup data
	$output_log->print_event('Save backup data');

	// create dbfs instance
	$dbfs = new dbFS($db);

	// save dbfs current user
	$dbfs->set_current_user('backend');

	// create file name
	$file_name = 'spplrfd_bak_' . date('Y-m-d_H-i-s');

	// set folder name
	$folder_name = 'supplier_feeds_backup';

	// get folder id
	$folder_id = $dbfs->get_folder_id($folder_name, null);

	// folder does not exists
	if (!$folder_id) {
		$folder_id = $dbfs->mkdir($folder_name, null);
	}

	// set mime type
	$mime = 'application/octet-stream';

	// save file
	if ($dbfs->save_file($folder_id, $file_name, $mime, serialize($final))) {
		$output_log->print_event('Backup ' . $folder_id . '/' . $file_name . ' success');
	} else {
		$output_log->print_error('Backup ' . $folder_id . '/' . $file_name . ' failed!');
	}

	// unknown_ids
	$columns[] = 'status';
	$columns[] = 'crosscheck';
	$final_unknown_ids = array();
	foreach ($unknown_ids as $sup_id => $sup_sources) {
		foreach ($sup_sources as $src_id => $rows) {
			foreach ($rows as $article_id => $article_row) {
				foreach ($columns as $column_name) {
					if (isset($article_row[$column_name])) {
						$final_unknown_ids[$sup_id][$article_id][$column_name] = $article_row[$column_name];
					} elseif (!isset($final[$sup_id][$article_id][$column_name])) {
						$final_unknown_ids[$sup_id][$article_id][$column_name] = '';
					}
				}
			}
		}
	}
	unset($unknown_ids);

	// print message for try to send data to SAP
	$output_log->print_event('Send data to SAP');
	$processed_records = 0;

	// main progress foreach with SAP progresses
	foreach ($final as $sup_id => $articles) {
		list ($pure_sup_id, $pure_purch_org) = explode('|', $sup_id);

		// print message for prepare data for SAP
		$output_log->print_event('Prepare data for SAP: ' . $pure_sup_id);
		try {
			// is supplier without sap call?
			if (!in_array($pure_sup_id, $supplier_without_call_sap)) {
				// create sap call
				$sap_call = $sap->NewFunction('Z_CSSRC_GOVE_UPL');
				if ($sap_call === false) {
					// create error message SAP failed!
					$err_msg_SAP = 'could not initiate Z_CSSRC_GOVE_UPL for supplier "' . $pure_sup_id . '":' . $sap->GetStatus();

					// print SAP call error message
					$output_log->print_error($err_msg_SAP);
					throw new Exception($err_msg_SAP);
				}
				$sap_call->IV_NWAIT = 60;
			}

			// create sf_log and get log_id
			$log_id = 0;
			$sql = "
				INSERT INTO " . DB_USER_MAINTENANCE . ".sf_log (
					supplier_id,
					purchase_organization,
					time,
					status
				)
				VALUES
				(
					:supplier_id,
					:purchase_organization,
					LOCALTIMESTAMP,
				    :status
				)
				RETURNING log_id INTO :log_id
			";

			$binds = array(
				'supplier_id' => $pure_sup_id,
				'purchase_organization' => $pure_purch_org,
				'log_id' => $log_id,
				'status' => RUNNING_PROGRESS
			);

			$stmt = $db->Prepare($sql);
			$db->InParameter($stmt, $binds['supplier_id'], 'supplier_id');
			$db->InParameter($stmt, $binds['purchase_organization'], 'purchase_organization');
			$db->InParameter($stmt, $binds['status'], 'status');

			// get log id
			$db->OutParameter($stmt, $log_id, 'log_id');
			$db->Execute($stmt);

			foreach ($articles as $article_id => $fields) {
				$IT_IDATS = array(
					'LIFNR' => $fields['supplier_id'],
					'IDNLF' => mb_strtoupper($fields['external_id'], 'UTF-8'),
					'MATNR' => $fields['variant_id'],
					'EAN11' => $fields['ean'],
					'LIFAB' => $fields['available_from'],
					'MENGE_BI' => $fields['stock'],
					'EKORG' => $fields['purchase_organization'],
					'PRICE' => $fields['price'],
					'WAERS' => $fields['currency'],
					'DATAB' => $fields['valid_from'],
					'DATBI' => $fields['valid_to']
				);

				// save log
				$sql = '
					INSERT INTO ' . DB_USER_MAINTENANCE . '.sf_log_row
					(
						log_id,
						external_id,
						variant_id,
						ean,
						available_from,
						stock,
						price,
						valid_from,
						valid_till
					)
					VALUES
					(
						:log_id,
						:IDNLF,
						:MATNR,
						:EAN11,
						:LIFAB,
						:MENGE_BI,
						:PRICE,
						:DATAB,
						:DATBI
					)
				';

				$db->Execute($sql, array_merge($IT_IDATS, array(
					'log_id' => $log_id
				)));

				if (!in_array($pure_sup_id, $supplier_without_call_sap)) {
					// add to preparing progress for SAP
					$sap_call->IT_IDATS->Append($IT_IDATS);
				}
			}

			// unknown ids progress
			if (isset($final_unknown_ids[$sup_id])) {
				foreach ($final_unknown_ids[$sup_id] as $article_id => $fields) {
					$binds = array();
					$binds['log_id'] = $log_id;
					$binds['unknown_id'] = mb_strtoupper($article_id, 'UTF-8');
					$binds['status'] = null;
					if (!empty($fields['status'])) {
						$binds['status'] = $fields['status'];
					}
					$binds['external_id_supplier'] = null;
					if (!empty($fields['crosscheck']['external_id_supplier'])) {
						$binds['external_id_supplier'] = $fields['crosscheck']['external_id_supplier'];
					}
					$binds['external_id_mall'] = null;
					if (!empty($fields['crosscheck']['external_id_mall'])) {
						$binds['external_id_mall'] = $fields['crosscheck']['external_id_mall'];
					}
					$binds['ean_supplier'] = null;
					if (!empty($fields['crosscheck']['ean_supplier'])) {
						$binds['ean_supplier'] = $fields['crosscheck']['ean_supplier'];
					}
					$binds['ean_mall'] = null;
					if (!empty($fields['crosscheck']['ean_mall'])) {
						$binds['ean_mall'] = $fields['crosscheck']['ean_mall'];
					}
					$binds['variant_id'] = null;
					if (!empty($fields['crosscheck']['variant_id'])) {
						$binds['variant_id'] = $fields['crosscheck']['variant_id'];
					}

					$sql = '
						INSERT INTO ' . DB_USER_MAINTENANCE . '.sf_log_unknown (
							log_id,
							unknown_id,
							status,
							external_id_supplier,
							external_id_mall,
							ean_supplier,
							ean_mall,
							variant_id
						)
						VALUES (
							:log_id,
							:unknown_id,
							:status,
							:external_id_supplier,
							:external_id_mall,
							:ean_supplier,
							:ean_mall,
							:variant_id
						)
					';

					$db->Execute($sql, $binds);

					// post data to SAP 'stock 0 ks' in import data not found id, but in SAP yes
					if (isset($fields['stock']) && $fields['stock'] === 0) {
						$IT_IDATS = array(
							'LIFNR' => $fields['supplier_id'],
							'IDNLF' => isset($fields['external_id']) ? mb_strtoupper($fields['external_id'], 'UTF-8') : "",
							'MATNR' => isset($fields['variant_id']) ? $fields['variant_id'] : "",
							'EAN11' => isset($fields['ean']) ? $fields['ean'] : "",
							'LIFAB' => isset($fields['available_from']) ? $fields['available_from'] : "",
							'MENGE_BI' => $fields['stock'],
							'EKORG' => $fields['purchase_organization'],
							'PRICE' => isset($fields['price']) ? $fields['price'] : "",
							'WAERS' => $fields['currency'],
							'DATAB' => isset($fields['valid_from']) ? $fields['valid_from'] : "",
							'DATBI' => isset($fields['valid_to']) ? $fields['valid_to'] : ""
						);

						if (!in_array($pure_sup_id, $supplier_without_call_sap)) {
							// add to preparing progress for SAP
							$sap_call->IT_IDATS->Append($IT_IDATS);
						}
					}
				}
			}

			// supplires with call SAP
			if (!in_array($pure_sup_id, $supplier_without_call_sap)) {
				// do-while for "Nepodařilo se uzamknout výstupní databázovou tabulku YCSSRC_GOVE_IN pro zápis"
				do {
					// send supplier to SAP
					$locked = false;

					// print event message for sending data to SAP
					$output_log->print_event('Send to SAPu');

					// call SAP
					$sap_call->Call();

					// get call SAP status
					$status = $sap_call->GetStatus();

					$output_log->print_event('Get SAP call result');
					switch ($status) {
						// SAP call success
						case SAPRFC_OK:
							// check counts of successed and failed calls
							$error_cnt = $sap_call->EV_CNT_ERR;

							// set ok count
							$ok_cnt = $sap_call->EV_CNT;

							// get error messages
							$sap_err = $sap_call->ET_BAPIRET2;

							// reset SAP
							$sap_err->Reset();

							$errors = array();
							while ($sap_err->Next()) {
								if ($sap_err->row['ID'] == 'ZUPL_MSG' && $sap_err->row['NUMBER'] == 1) {
									$locked = true;
									$locked_msg = $sap_err->row['MESSAGE'];
								} else {
									$errors[] = $sap_err->row['MESSAGE'];
								}
							}

							// print error messages
							if ($locked === false) {
								if (empty($errors)) {
									// outputlogts('SAP bez chyb');
									$output_log->print_event('SAP OK');
									$final_status = 'OK';
								} else {
									$err_msg_sap = 'SAP input errors: ' . PHP_EOL . print_r($errors, true);
									$output_log->print_error($err_msg_sap);
									$final_status = 'ER';
								}
							} else {
								$err_msg_sap = 'SAP input errors: ' . PHP_EOL . $locked_msg;
								$output_log->print_error($err_msg_sap);
								$final_status = 'ER';
							}
							break;
						case SAPRFC_EXCEPTION:
							$err_msg_sap = 'SAP Exception: "' . $sap_call->GetException() . '"';
							$output_log->print_error($err_msg_sap);
							break;
						default:
							$err_msg_sap = 'SAP not OK: "' . $sap_call->getStatusTextLong() . '"';
							$output_log->print_error($err_msg_sap);
							break;
					}
					if ($locked === true) {
						// SAP table is lock now, try to call again for 30 sec
						$err_msg_sap = 'Send data to SAP for 30 sec after';
						$output_log->print_event($err_msg_sap);
						sleep(30);
					}
				} while ($locked === true);

				// close SAP handle
				$sap_call->Close();
				$info = substr(implode(PHP_EOL, $errors), 0, 4000);
			} else {
				$final_status = 'OK';
				$info = 'Its force calling for run not active supplier without SAP calling';
				// $info = 'Jedna se o vynucene spusteni neaktivniho dodavatele bez zaslani dat do SAPu';

				$msg_sap = 'Data to SAP not sent, its force running for not active supplier';
				// $msg_sap = 'nebyly zaslány data do SAPu jedná se o vynucené spuštění neaktivního dodavatele';

				$output_log->print_event($msg_sap);
			}

			// update log list
			$sql = '
				UPDATE ' . DB_USER_MAINTENANCE . '.sf_log
				SET status = :status,
					info = :info
				WHERE log_id = :log_id
			';

			$binds = array(
				'status' => $final_status,
				'info' => $info,
				'log_id' => $log_id
			);

			$db->Execute($sql, $binds);

			if ($final_status == 'OK') {
				$processed_records++;
			}
		} catch (Exception $e) {
			$output_log->print_exception($e);
			if (!in_array($pure_sup_id, $supplier_without_call_sap)) {
				// close sap handle
				$sap_call->Close();
			}
		}
	}

	// chyby ET_BAPIRET2
	/*
	 * ZATIM JEN na IET EV_CNT_ERR (int(4)) EV_CNT
	 * LIFNR LIFNR CHAR 10 0 Číslo účtu dodavatele IDNLF IDNLF CHAR 35 0 Číslo zboží u dodavatele MATNR MATNR CHAR 18 0 Číslo zboží EAN11 EAN11 CHAR 18 0 Evropské číslo artiklu (EAN) LIFAB LIFAB_BI CHAR 10 0 MožnoDodatOd (BTCI) MENGE_BI MENGE_BI CHAR 17 0 Množ.(pole batch-input) EKORG EKORG CHAR 4 0 Nákupní organizace PRICE EXVKW_BI CHAR 16 0 Externě zadaná prodej.hodnota ve firemní měně (pole BI) WAERS WAERS_BI CHAR 5 0 Klíč měny (BTCI) DATAB DATAB_BI CHAR 10 0 Podmínka platí od (BTCI) DATBI DATBI_BI CHAR 10 0 Podmínka platí do (BTCI)
	 */

	// for every complete source set running as STOP flag
	setSourcesRunning($db, $sources, RUNNING_STOP);

	$output_log->print_event('-- END --');
	$jobstat->job_finished('Complete: processed suppliers ' . $processed_records, $processed_records);
	$output_log->print_end();
} catch (Exception $e) {
	$output_log->print_exception($e);
	$jobstat->job_finished_error($e->getMessage());

	if (isset($sources)) {
		// for every complete source set running as STOP flag
		setSourcesRunning($db, $sources, RUNNING_STOP);
	}
}

/**
 * Get purchase organization emails list
 *
 * @return array
 */
function getPurchOrgEmailsList(ADODB_oci8dc $db)
{
	$sql = '
		SELECT purchase_organization,
			email
		FROM ' . DB_USER_MAINTENANCE . '.sf_email
	';

	$result = array();
	$res = $db->Execute($sql);

	while (($row = $res->FetchRow()) !== false) {
		$result[$row['purchase_organization']][] = $row['email'];
	}

	return $result;
}

/**
 * Send info emails with errors messages
 *
 * @param ADOConnection $_db
 * @param array $email_error_messages
 * @return int
 */
function sendEmails(ADODB_oci8dc $db, array $email_error_messages)
{
	$sended = 0;
	$emails = getPurchOrgEmailsList($db);

	foreach ($email_error_messages as $purchase_org => $suppliers) {
		if (isset($emails[$purchase_org]) && is_array($emails[$purchase_org])) {
			$mail = new PHPMailer();
			$mail->isSMTP();
			$mail->CharSet = 'UTF-8';
			$mail->SetFrom('no-reply@mall.cz');
			$mail->Subject = 'Download problem (' . date('d.m.Y H:i:s') . ') ';

			// create email body
			foreach ($suppliers as $supplier_id => $sources) {
				foreach ($sources as $source_id => $source) {
					$body .= $supplier_id . ' : ' . $source['supplier_name'] . "\n";
					$body .= 'Chyba : ' . $source['msg'] . "\n";
					$body .= 'Source URL : ' . $source['url'] . "\n";
					$body .= 'Webadmin URL :  http://webadmin.mall/reports-sales/sf-source/edit?id=' . $source_id . "\n\n";
				}
			}

			// set email body
			$mail->Body = $body;
			unset($body);

			// add email for sending
			foreach ($emails[$purchase_org] as $email) {
				// add email adress to PHPMailer
				$mail->AddAddress($email);
			}

			// try to send email message
			if ($mail->Send()) {
				$sended++;
			}
		}
	}
	return $sended;
}

/**
 * Set source status from status type variable and update data to table
 *
 * @param ADODB_oci8dc $db
 * @param int $source_id
 * @param string $status_type
 * @return boolean
 */
function setSourceStatusMessage(ADODB_oci8dc $db, $source_id, $status_type)
{
	$binds = array(
		'status_type' => $status_type,
		'source_id' => $source_id
	);

	// create sql query
	$sql = '
		UPDATE ' . DB_USER_MAINTENANCE . '.sf_source
		SET status_download  = :status_type
		WHERE source_id = :source_id
	';

	$db->Execute($sql, $binds);
}

/**
 * For simple sources setting runing status START/STOP
 *
 * @param ADOConnection $_db
 * @param array $sources
 * @param int $status
 */
function setSourcesRunning(ADODB_oci8dc $db, array $sources, $status)
{
	$source_ids = array();
	foreach ($sources as $row) {
		$source_ids[] = $row['source_id'];
	}

	if (count($sources) > 0) {
		$binds = array();
		$sources_ids_in = bind_variables_in($binds, 'source_id', $source_ids);
		switch ($status) {
			case RUNNING_START:
				$sql = "
					UPDATE " . DB_USER_MAINTENANCE . ".sf_source
					SET running = '" . ORACLE_TRUE . "',
						last_running = LOCALTIMESTAMP
					WHERE source_id IN (" . $sources_ids_in . ")
				";

				$db->Execute($sql, $binds);
				break;
			case RUNNING_STOP:
				$sql = "
					UPDATE " . DB_USER_MAINTENANCE . ".sf_source
					SET running = '" . ORACLE_FALSE . "',
						forcerun = '" . ORACLE_FALSE . "'
					WHERE source_id IN (" . $sources_ids_in . ")
				";

				$db->Execute($sql, $binds);
				break;
		}
	}
}
