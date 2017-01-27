<?php

/**
 * obecna trida pro stahovani dat od dodavatelu
 *
 * @author Pavel Berka <pavel.berka@mall.cz>
 */
class Supplier_Feed
{

	/**
	 * connect timeout
	 *
	 * @var integer
	 */
	const CONNECT_TIMEOUT = 300;

	/**
	 * pripojeni k databazi
	 *
	 * @var ADOConnection
	 */
	protected $db;

	/**
	 * pole s daty o feedu
	 *
	 * @var array
	 */
	protected $feed_attributes = array();

	/**
	 * indikator toho, jestli se maji ukladat ceny
	 *
	 * @var bool
	 */
	protected $save_price = false;

	/**
	 * indikator toho, jestli se maji ukladat skladove zasoby
	 *
	 * @var bool
	 */
	protected $save_stock = false;

	/**
	 * obsah zdrojoveho souboru
	 *
	 * @var string
	 */
	protected $input_file = '';

	/**
	 * pole s jednotlivymi radky pro csv v podobe pole
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * pole vsetkych znamych id ziskanych zo SAPu
	 *
	 * @var array
	 */
	protected $known_ids = array();

	/**
	 * pole nesparovanych id ( v SAPe sa id este nachadza, ale dodavatel ho uz v exportoch neuvadza )
	 *
	 * @var array
	 */
	protected $unknown_ids = array();

	/**
	 * pole id ziskanych zo SAPu ( pre krizovu kontrolu )
	 *
	 * @var array
	 */
	protected $crosscheck_ids = array();

	/**
	 * pole external_id ziskanych zo SAPu ( pre kontrolu duplicit v ramci krizovej kontroly )
	 *
	 * @var array
	 */
	protected $crosscheck_external_ids = array();

	/**
	 * pole external_id ktore su v SAPe duplicitne ( kontrola duplicit v ramci krizovej kontroly )
	 *
	 * @var array
	 */
	protected $crosscheck_repeated_external_ids = array();

	/**
	 * pole EANov ziskanych zo SAPu ( pre kontrolu duplicit v ramci krizovej kontroly )
	 *
	 * @var array
	 */
	protected $crosscheck_eans = array();

	/**
	 * pole EANov ktore su v SAPe duplicitne ( kontrola duplicit v ramci krizovej kontroly )
	 *
	 * @var array
	 */
	protected $crosscheck_repeated_eans = array();

	/**
	 * pole MANTNR a ich prislusne EANy ziskanych zo SAPu (jeden MANTNR moze obsahovat hlavny EAN + viacero dodatkovych EANov)
	 *
	 * @var array
	 */
	protected $matnr_eans = array();

	/**
	 * pole EANov a ich prislusne MANTNR ziskanych zo SAPu
	 *
	 * @var array
	 */
	protected $ean_matnr = array();

	/**
	 *
	 * @var SAPConnection
	 */
	protected $sap;

	/**
	 * @var Output_Log
	 */
	protected $logger;

	/**
	 * pole pre dodatocne prekonvertovanie string hodnot v $this->feed_attributes['stock'] pre xml exporty niektori dodavatelia v exportoch neuvadzaju numericke hodnoty hodnota 'default_in_stock' bude dodatocne nahradena obsahom premennej $this->feed_attributes['default_in_stock']
	 *
	 * @var array
	 */
	protected $xml_stock_convert = array(
		'CZA0' => array(
			'&gt;1000' => 1000,
			'&gt;15' => 15,
			'>15' => 16,
			'10+' => 11,
			'ano' => 'default_in_stock',
			'skladem' => 'default_in_stock',
			'true' => 'default_in_stock',
			'a' => 0,
			'b' => 3,
			'c' => 10
		),
		'HUA0' => array(
			'true' => 'default_in_stock',
			'yes' => 'default_in_stock',
		),
		'PLA0' => array(
			'true' => 'default_in_stock',
			'ponad 30' => 31,
			'dostępny' => 'default_in_stock',
			'jest' => 'default_in_stock'
		),
		'SKA0' => array(
			'&gt;1000' => 1000,
			'&gt;15' => 15,
			'10+' => 11,
			'ano' => 'default_in_stock',
			'skladem' => 'default_in_stock',
			'true' => 'default_in_stock'
		),
		'SIA0' => array(
			'>50' => 51,
			'1 - 2 dni' => 0,
			'1 dan' => 0,
			'11-25 kos' => 44,
			'1-5 dni' => 0,
			'2 -3 dni' => 0,
			'2 dneva' => 0,
			'2-3 dni' => 0,
			'25-50 kos' => 25,
			'3 - 4 dni' => 0,
			'3 - 4 tedne' => 0,
			'3 - 4 tedne od dneva naročila' => 0,
			'3 tedne' => 0,
			'3-50' => 3,
			'50-100 kos' => 50,
			'5-50' => 5,
			'7 - 14 dni' => 0,
			'ano' => 'default_in_stock',
			'artikel je na zalogi' => 'default_in_stock',
			'artikel ni na zalogi' => 0,
			'da' => 'default_in_stock',
			'da,' => 'default_in_stock',
			'do 2' => 2,
			'do 5' => 5,
			'dobava 1-2 dni' => 0,
			'dobava 2 - 3 tedne' => 0,
			'dobava 2 dni' => 0,
			'dobava 5 - 7 dni' => 0,
			'dobava po naročilu (6 - 8 tednov) ' => 0,
			'dobava po naročilu (6 - 8 tednov)' => 0,
			'dobavni rok: 1 dan' => 0,
			'dobavni rok: 3 dni' => 0,
			'je na zalogi' => 'default_in_stock',
			'kmalu' => 0,
			'manjša zaloga' => 'default_in_stock',
			'na voljo' => 'default_in_stock',
			'na zalogi' => 'default_in_stock',
			'na zalogi 4 kosi ali več' => 4,
			'na zalogi od 3.5.0213' => 0,
			'na zalogi trije kosi ali manj' => 3,
			'ne' => 0,
			'ni na zalogi' => 0,
			'ni več v prodaji' => 0,
			'po naročilu' => 0,
			'povpraševanje' => 0,
			'predvidoma 10-14 dni' => 0,
			'predvidoma 2 dni.' => 0,
			'predvidoma 3 dni' => 0,
			'predvidoma 7-10 dni' => 0,
			'preko 100 kos' => 101,
			'preveri' => 0,
			't: 01 | 530 08 00' => 0,
			'trenutno ni na zalogi' => 0,
			'v 1-2 dneh' => 0,
			'v prihodu' => 0,
			'v ukinjanju - zadnji kosi' => 0,
			'več' => 20,
			'več kot 4 tedne' => 0,
			'več kot 5' => 5,
			'yes' => 'default_in_stock',
			'zadnji kosi' => 0,
			'zaloga' => 'default_in_stock'
		)
	);

	/**
	 * pole pre dodatocne prekonvertovanie string hodnot v $this->feed_attributes['stock'] pre csv exporty niektori dodavatelia v exportoch neuvadzaju numericke hodnoty hodnota 'default_in_stock' bude dodatocne nahradena obsahom premennej $this->feed_attributes['default_in_stock']
	 *
	 * @var array
	 */
	protected $csv_stock_convert = array(
		'CZA0' => array(
			'do 100 ks' => 11,
			'nad 100 ks' => 101
		),
		'HUA0' => array(),
		'PLA0' => array(),
		'SKA0' => array(
			'do 100 ks' => 11,
			'nad 100 ks' => 101
		),
		'SIA0' => array(
			'zaloga' => 'default_in_stock',
			'na zalogi' => 'default_in_stock',
			'da' => 'default_in_stock'
		)
	);

	/**
	 *
	 * @var string
	 */
	const STATUS_CROSSCHECK_FAILED = 'CF';

	/**
	 *
	 * @var string
	 */
	const STATUS_REPEATED_EXTERNAL_ID = 'RE';

	/**
	 *
	 * @var string
	 */
	const STATUS_REPEATED_EAN = 'RA';

	/**
	 *
	 * @var string
	 */
	const STATUS_INVALID_EAN = 'IE';

	/**
	 *
	 * @var string
	 */
	const IDENTIFY_BY_EAN = 'ean';

	/**
	 *
	 * @var string
	 */
	const IDENTIFY_BY_EXTERNAL_ID = 'external_id';

	/**
	 *
	 * @var string
	 */
	const IDENTIFY_BY_VARIANT_ID = 'variant_id';

	/**
	 * konstruktor nastavi feed_attributes a dalsi promenne
	 *
	 * @param ADOConnection $db
	 * @param integer $source_id
	 * @param SAPConnection $sap
	 * @param Output_Log $logger
	 */
	public function __construct($db, $source_id, $sap, $logger)
	{
		$this->db = $db;
		$this->sap = $sap;
		$this->logger = $logger;

		// nacist komplet udaje o zdroji
		$sql = "SELECT sup.*,
				src.source_id,
				src.protocol,
				src.url,
				src.file_format,
				src.username,
				src.password,
				src.save_price,
				src.save_stock,
				src.auth_method,
				src.currency,
				src.purchase_organization,
				src.xml_element,
				src.variant_id,
				src.external_id,
				src.ean,
				src.stock,
				src.price,
				src.price_2,
				src.price_3,
				src.price_4,
				src.loader,
				src.parser
			FROM " . DB_USER_MAINTENANCE . ".sf_supplier sup
				JOIN " . DB_USER_MAINTENANCE . ".sf_source src ON (src.supplier_id = sup.supplier_id)
			WHERE src.source_id = :source_id";
		$binds = array(
			'source_id' => $source_id
		);
		$this->feed_attributes = $this->db->GetRow($sql, $binds);

		// nastavit prmenne
		if ($this->feed_attributes['save_price'] == 't') {
			$this->save_price = true;
		}

		if ($this->feed_attributes['save_stock'] == 't') {
			$this->save_stock = true;
		}

		$this->get_known_ids();
	}

	/**
	 * upravi cislo na podobu vhodnou pro DB
	 *
	 * @param mixed $value
	 * @return float
	 */
	public function normalize_number($value)
	{
		// nahradit pripadnou desetinnou carku desetinnou teckou
		$ret = strtr($value, ',', '.');

		// vratit zaokrouhlenou hodnotu na tri desetinna mista
		return round(floatval($ret), 3);
	}

	/**
	 * ulozi zdrojovy soubor do dbFS
	 */
	public function save_input()
	{
		// nastartovat oracle DB a dbFS
		$oracle = Database_Factory::get_database(Database_Factory::TYPE_BACKEND, Database_Factory::USER_BACKEND);
		$dbfs = new dbFS($oracle);
		$dbfs->set_current_user('backend');

		// nazvy adresaru a souboru
		$folder_name = strtoupper(substr($this->feed_attributes['purchase_organization'], 0, 2) . $this->feed_attributes['name']);
		$file_name = date('Y-m-d_Hi');

		if ($this->feed_attributes['save_price'] == 't') {
			$file_name .= '_p';
		}

		if ($this->feed_attributes['save_stock'] == 't') {
			$file_name .= '_s';
		}
		$common_folder_name = 'supplier_feeds';

		// spolecny adresar
		$common_folder_id = $dbfs->get_folder_id($common_folder_name, null);
		if (!$common_folder_id) {
			$common_folder_id = $dbfs->mkdir($common_folder_name, null);
		}

		// adresar konkretniho feedu
		$folder_id = $dbfs->get_folder_id($folder_name, $common_folder_id);
		if (!$folder_id) {
			$folder_id = $dbfs->mkdir($folder_name, $common_folder_id);
		}

		// mime je urceno typem souboru v parametrech feedu
		switch ($this->feed_attributes['file_format']) {
			case 'xml':
				$mime = 'text/xml';
				break;

			case 'csv':
				$mime = 'text/csv';
				break;

			default:
				$mime = 'application/octet-stream';
				break;
		}

		// ulozit soubor
		$dbfs->save_file($folder_id, $file_name, $mime, $this->input_file);
	}

	/**
	 * nacte zdrojovy soubor pres http
	 *
	 * @param string force_url URL souboru; pokud neni (null), bere se z parametru zdroje
	 */
	protected function load_source_from_http($force_url = null)
	{
		if ($force_url === null) {
			$full_url = $this->feed_attributes['protocol'] . '://' . $this->feed_attributes['url'];
		} else {
			$full_url = $force_url;
		}
		try {
			switch (strtolower($this->feed_attributes['auth_method'])) {
				case 'querystring':
				case '':
					// nahradit placeholdery username a password
					$translation = array(
						'%USERNAME%' => $this->feed_attributes['username'],
						'%PASSWORD%' => $this->feed_attributes['password']
					);
					$full_url = strtr($full_url, $translation);
					// pristupy jsou v querystringu, staci nacist soubor
					$this->input_file = file_get_contents($full_url);
					break;

				case 'basic':
				case 'digest':
				case 'ntlm':
					// vytvorit resource
					$handle = curl_init();

					// nastavit URL
					curl_setopt($handle, CURLOPT_URL, $full_url);

					// vysledek chceme jako string
					curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

					// nastavit typ autentikace
					switch (strtolower($this->feed_attributes['auth_method'])) {
						case 'basic':
							$curlauth = CURLAUTH_BASIC;
							break;
						case 'digest':
							$curlauth = CURLAUTH_DIGEST;
							break;
						case 'ntlm':
							$curlauth = CURLAUTH_NTLM;
							break;
					}
					curl_setopt($handle, CURLOPT_HTTPAUTH, $curlauth);

					// nastavit pristupove udaje
					curl_setopt($handle, CURLOPT_USERPWD, $this->feed_attributes['username'] . ':' . $this->feed_attributes['password']);
					// connect timeout
					curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);

					// nacist do promenne
					$this->input_file = curl_exec($handle);

					// zavrit resource
					curl_close($handle);
					break;

				default:
					throw new Exception(__FUNCTION__ . ' - neznamy typ prihlaseni');
					break;
			}
		} catch (Exception $e) {
			// zalogovat, ze se nepodarilo stahnout soubor
			echo $e->getMessage();
		}
	}

	protected function load_source_from_ftp()
	{
		// docasny lokalni soubor
		$local_file = tempnam('/tmp', 'splrfds_ftp_');

		// rozsekat URL podle lomitek
		$path = explode('/', $this->feed_attributes['url']);
		// server je prvni cast
		$server = array_shift($path);
		// soubor je posledni
		$remote_file = array_pop($path);
		// pripojit se
		$connection = ftp_connect($server);
		if (!$connection) {
			throw new Exception('nepodarilo se pripojit na FTP');
		}
		// prihlasit se
		$login_result = ftp_login($connection, $this->feed_attributes['username'], $this->feed_attributes['password']);
		if (!$login_result) {
			ftp_close($connection);
			throw new Exception('nepodarilo se prihlasit na FTP');
		}

		// prejit do podadresare
		$path = implode('/', $path);
		if ($path != '') {
			$chdir_result = @ftp_chdir($connection, $path);
			if (!$chdir_result) {
				$chdir_result = @ftp_chdir($connection, "/" . $path);
				if (!$chdir_result) {
					ftp_close($connection);
					throw new Exception('nepodarilo se prejit do ciloveho adresare na FTP');
				}
			}
		}

		// stahnout soubor
		$get_result = ftp_get($connection, $local_file, $remote_file, FTP_BINARY);
		if (!$get_result) {
			ftp_close($connection);
			throw new Exception('nepodarilo se stahnout soubor z FTP');
		}

		// odpojit se
		ftp_close($connection);

		// nacist docasny soubor do promenne
		$this->input_file = file_get_contents($local_file);

		// smazat docasny soubor
		// unlink($local_file);
	}

	/**
	 * nacte zdrojovy soubor pres sftp
	 *
	 * @throws Exception
	 */
	protected function load_source_from_sftp()
	{
		// rozsekat URL podle lomitek
		$path = explode('/', $this->feed_attributes['url']);
		// server je prvni cast
		$server = array_shift($path);
		// cesta a soubor
		$remote_file_with_path = implode('/', $path);

		// pripojit se
		$connection = ssh2_connect($server, 22);
		if (!$connection) {
			throw new Exception('nepodarilo se pripojit na SFTP');
		}

		// prihlasit se
		if (!ssh2_auth_password($connection, $this->feed_attributes['username'], $this->feed_attributes['password'])) {
			throw new Exception('nepodarilo se prihlasit na FTP');
		}

		// inicializace SFTP subsystemu
		$sftp = ssh2_sftp($connection);
		if (!$sftp) {
			throw new Exception('nepodarilo se inicializovat SFTP subsystem');
		}

		// nacist soubor do promenne
		$content = file_get_contents('ssh2.sftp://' . $sftp . '/' . $remote_file_with_path);
		if (!$content) {
			throw new Exception('nepodarilo se stahnout soubor z SFTP');
		}

		$this->input_file = $content;
	}

	/**
	 * ulozi radek s daty
	 *
	 * @param array $data
	 */
	protected function add_row($data)
	{
		try {
			if (empty($data) || !is_array($data)) {
				// parametr neni pole nebo je prazdny
				throw new Exception('vstup neni pole nebo je to prazdne pole');
			}

			// pole, do ktereho se budou vkladat data pro CSV a log
			$row = array(
				'supplier_id' => $this->feed_attributes['supplier_id'],
				'purchase_organization' => $this->feed_attributes['purchase_organization'],
				'currency' => $this->feed_attributes['currency']
			);

			if (empty($data[$this->feed_attributes['identify_by']])) {
				// radek bez klicoveho identifikatoru
				throw new Exception('chybi klicovy identifikator ' . $this->feed_attributes['identify_by'] . ': ' . print_r($data, true));
			} else {
				if ($this->feed_attributes['identify_by'] == self::IDENTIFY_BY_EAN && !$this->is_valid_ean13($data[self::IDENTIFY_BY_EAN])) {
					throw new Exception('spatne formovany EAN: ' . $data[self::IDENTIFY_BY_EAN]);
				}

				$id = mb_strtolower(trim($data[$this->feed_attributes['identify_by']]), 'UTF-8');
				$identify_by_array = array(
					self::IDENTIFY_BY_EAN,
					self::IDENTIFY_BY_EXTERNAL_ID
				);
				if (in_array($this->feed_attributes['identify_by'], $identify_by_array)) {
					// $this->known_ids obsahuje EANy bez pociatocnych 0
					if ($this->feed_attributes['identify_by'] == self::IDENTIFY_BY_EAN) {
						$id = ltrim($id, '0');
					}
					if (!in_array($id, $this->known_ids)) {
						// nezname external_id/ean
						return;
					}

					// krizova kontrola s kontrolou duplicity external_id a ean
					if ($this->feed_attributes['crosscheck'] == 't') {
						$data_external_id = mb_strtolower(trim($data[self::IDENTIFY_BY_EXTERNAL_ID]), 'UTF-8');
						$data_ean = ltrim(mb_strtolower(trim($data[self::IDENTIFY_BY_EAN]), 'UTF-8'), '0');

						if ($this->feed_attributes['identify_by'] == self::IDENTIFY_BY_EAN) {
							$crosscheck_value = $data_external_id;
						} else {
							$crosscheck_value = $data_ean;
						}

						// dodatocna validacia eanu v pripade krizovej kontroly ak identify_by = 'external_id'
						if ($this->feed_attributes['identify_by'] == self::IDENTIFY_BY_EXTERNAL_ID) {
							if (!$this->is_valid_ean13($data_ean)) {
								// nevalidny ean
								$this->unknown_ids[$id]['status'] = self::STATUS_INVALID_EAN;
								$this->unknown_ids[$id]['crosscheck']['ean_supplier'] = $data_ean;
								$this->unknown_ids[$id]['crosscheck']['external_id_supplier'] = $data_external_id;
								if ($this->feed_attributes['identify_by'] == self::IDENTIFY_BY_EAN) {
									$this->unknown_ids[$id]['crosscheck']['variant_id'] = $this->ean_matnr[$id];
								} else {
									$this->unknown_ids[$id]['crosscheck']['variant_id'] = $this->external_id_matnr[$id];
								}
								return;
							}
						}

						// kontrola duplicity external_id
						if (in_array($data_external_id, $this->crosscheck_repeated_external_ids)) {
							// najdene duplicitne external_id
							$this->unknown_ids[$id]['status'] = self::STATUS_REPEATED_EXTERNAL_ID;
							$this->unknown_ids[$id]['crosscheck']['external_id_mall'] = $data_external_id;
							if ($this->feed_attributes['identify_by'] == self::IDENTIFY_BY_EAN) {
								$this->unknown_ids[$id]['crosscheck']['variant_id'] = $this->ean_matnr[$id];
							} else {
								$this->unknown_ids[$id]['crosscheck']['variant_id'] = $this->external_id_matnr[$id];
							}
							return;
						}

						// kontrola duplicity ean
						if (in_array($data_ean, $this->crosscheck_repeated_eans)) {
							// najdeny duplicitny ean
							$this->unknown_ids[$id]['status'] = self::STATUS_REPEATED_EAN;
							$this->unknown_ids[$id]['crosscheck']['ean_mall'] = $data_ean;
							if ($this->feed_attributes['identify_by'] == self::IDENTIFY_BY_EAN) {
								$this->unknown_ids[$id]['crosscheck']['variant_id'] = $this->ean_matnr[$id];
							} else {
								$this->unknown_ids[$id]['crosscheck']['variant_id'] = $this->external_id_matnr[$id];
							}
							return;
						}

						// krizova kontrola
						if (!in_array($crosscheck_value, $this->crosscheck_ids[$id])) {
							// krizova kontrola neuspesna
							$this->unknown_ids[$id]['status'] = self::STATUS_CROSSCHECK_FAILED;
							if ($this->feed_attributes['identify_by'] == self::IDENTIFY_BY_EAN) {
								$this->unknown_ids[$id]['crosscheck']['external_id_supplier'] = $crosscheck_value;
								$this->unknown_ids[$id]['crosscheck']['external_id_mall'] = implode(',', $this->crosscheck_ids[$id]);
								$this->unknown_ids[$id]['crosscheck']['variant_id'] = $this->ean_matnr[$id];
							} else {
								$this->unknown_ids[$id]['crosscheck']['ean_supplier'] = $crosscheck_value;
								$this->unknown_ids[$id]['crosscheck']['ean_mall'] = implode(',', $this->crosscheck_ids[$id]);
								$this->unknown_ids[$id]['crosscheck']['variant_id'] = $this->external_id_matnr[$id];
							}
							return;
						}
					}
				}

				$row[$this->feed_attributes['identify_by']] = $id;

				if ($this->feed_attributes['identify_by'] == self::IDENTIFY_BY_EAN) {
					// potrebne je unsetnut hlavny EAN ale aj vsetky prislusne dodatkove EANy
					foreach ($this->matnr_eans[$this->ean_matnr[$id]] as $ean) {
						unset($this->unknown_ids[$ean]);
					}
				} else {
					unset($this->unknown_ids[$id]);
				}
			}

			if ($this->save_price) {
				// ma se ulozit cena
				if (isset($data['price'])) {
					$row['price'] = floatval($data['price']);
				} else {
					// cena neni dodana
					throw new Exception('ve vstupnim poli chybi cena');
				}
			}

			if ($this->save_stock) {
				// ma se ulozit sklad
				if (isset($data['stock'])) {
					$row['stock'] = floatval($data['stock']);
				} else {
					// sklad neni dodan, vezme se default
					$row['stock'] = $this->feed_attributes['default_in_stock'];
				}
			}

			if (isset($data['valid_from'])) {
				$row['valid_from'] = $data['valid_from'];
			}

			if (isset($data['valid_till'])) {
				$row['valid_till'] = $data['valid_till'];
			}

			if (isset($data['available_till'])) {
				$row['available_till'] = $data['available_till'];
			}

			// zaznamenat radku do globalniho pole
			$this->data[$row[$this->feed_attributes['identify_by']]] = $row;
		} catch (Exception $e) {
			// zalogovat, ze vznikla chyba na radce
			echo $e->getMessage() . PHP_EOL;
		}
	}

	/**
	 * zjisti, jestli zadany parametr je platny EAN-13
	 *
	 * @param mixed $test
	 * @return bool
	 */
	protected function is_valid_ean13($test)
	{
		try {
			$test = trim((string) $test);

			// dodavatelia niekedy zasielaju EAN bez pociatocnych 0, pre validaciu potrebujeme 13 znakov
			$test = str_pad($test, 13, '0', STR_PAD_LEFT);

			if (strlen($test) != 13) {
				throw new Exception('bad length: ' . strlen($test), 1);
			}

			$even = false;
			$sum = 0;
			$all_zeroes = true;
			for ($i = 12; $i >= 0; $i--) {
				if ($all_zeroes && ($test[$i] != 0)) {
					$all_zeroes = false;
				}
				if ($even === true) {
					// sude se nasobi tremi
					$sum += $test[$i] * 3;
					$even = false;
				} else {
					// liche se berou tak jak jsou
					$sum += $test[$i];
					$even = true;
				}
			}

			if (($sum % 10) != 0) {
				// soucet neni delitelny beze zbytku desiti
				throw new Exception('bad modulo: ' . ($sum % 10), 2);
			}

			if ($all_zeroes) {
				// vsechno jsou nuly, neberu jako EAN
				throw new Exception('all zeroes', 3);
			}

			// vse OK
			$return = true;
		} catch (Exception $e) {
			// nekde byla chyba, neni to EAN
			$return = false;
		}

		return $return;
	}

	/**
	 * nastavi datum posledniho spusteni v DB
	 */
	protected function update_last_success()
	{
		$sql = "UPDATE " . DB_USER_MAINTENANCE . ".sf_source
			SET last_success = LOCALTIMESTAMP
			WHERE source_id = :source_id";
		$binds = array(
			'source_id' => $this->feed_attributes['source_id']
		);
		$this->db->Execute($sql, $binds);
	}

	/**
	 * vrati zpracovana data
	 *
	 * @return array
	 */
	public function get_data()
	{
		return $this->data;
	}

	/**
	 * vrati nesparovane id
	 *
	 * @return array
	 */
	public function get_unknown_ids()
	{
		$allowed_identification = array(
			self::IDENTIFY_BY_EAN,
			self::IDENTIFY_BY_EXTERNAL_ID
		);
		if (in_array($this->feed_attributes['identify_by'], $allowed_identification) && count($this->data) > 0 && $this->save_stock) {
			foreach ($this->unknown_ids as $id => &$data) {
				$data['supplier_id'] = $this->feed_attributes['supplier_id'];
				$data['purchase_organization'] = $this->feed_attributes['purchase_organization'];
				$data['currency'] = $this->feed_attributes['currency'];
				if ($this->feed_attributes['identify_by'] == self::IDENTIFY_BY_EAN) {
					$data[self::IDENTIFY_BY_EAN] = $id;
				} elseif ($this->feed_attributes['identify_by'] == self::IDENTIFY_BY_EXTERNAL_ID) {
					$data[self::IDENTIFY_BY_EXTERNAL_ID] = $id;
				}
				$data['stock'] = 0;
			}
		}

		return $this->unknown_ids;
	}

	/**
	 * CSV line parser
	 *
	 * @param string $input_text
	 * @param string $delimiter
	 * @param string $text_qualifier
	 * @return mixed
	 */
	public function csv_parse_line($input_text, $delimiter = ';', $text_qualifier = '"')
	{
		$text = trim($input_text);
		if (is_string($delimiter) && is_string($text_qualifier)) {
			$re_d = '\x' . dechex(ord($delimiter));
			$re_tq = '\x' . dechex(ord($text_qualifier));
			$fields = array();
			$field_num = 0;
			while (strlen($text) > 0) {
				$matches = array();
				if ($text{0} == $text_qualifier) {
					preg_match('/^' . $re_tq . '((?:[^' . $re_tq . ']|(?<=\x5c)' . $re_tq . ')*)' . $re_tq . $re_d . '?(.*)$/', $text, $matches);

					$value = str_replace('\\' . $text_qualifier, $text_qualifier, $matches[1]);
					$text = trim($matches[2]);

					$fields[$field_num++] = $value;
				} else {
					preg_match('/^([^' . $re_d . ']*)' . $re_d . '?(.*)$/', $text, $matches);

					$value = $matches[1];
					$text = trim($matches[2]);

					$fields[$field_num++] = $value;
				}
			}
			return $fields;
		} else {
			return false;
		}
	}

	/**
	 * ziska ze SAPu znama IDcka produktu aktualniho dodavatele
	 */
	protected function get_known_ids()
	{
		// testy na konfiguraci
		if (!$this->sap) {
			throw new Exception('Chyba inicializace SAP connection v common_sap.inc.php');
		}

		$sap_call = $this->sap->NewFunction('Z_WEB_EINE');
		if ($sap_call == false) {
			$this->sap->PrintStatus();
			exit();
		}

		$sap_call->IV_LIFNR = str_pad($this->feed_attributes['supplier_id'], 10, '0', STR_PAD_LEFT);
		$sap_call->IV_EKORG = $this->feed_attributes['purchase_organization'];
		switch ($this->feed_attributes['identify_by']) {
			case self::IDENTIFY_BY_EXTERNAL_ID:
				$sap_call->IV_IDENTBY = 'IDNLF';
				$col = 'IDNLF';
				break;
			case self::IDENTIFY_BY_EAN:
				$sap_call->IV_IDENTBY = 'EAN';
				$col = 'EAN11';
				break;
			case self::IDENTIFY_BY_VARIANT_ID:
				// v pripade identify_by='variant_id', neriesime $this->known_ids a $this->unknown_ids
				$this->unknown_ids = array();
				return null;
				break;
			default:
				throw new Exception('Neznamy parametr identify_by=' . $this->feed_attributes['identify_by'] . ' (musi byt external_id, variant_id nebo ean)');
		}

		$sap_call->Call();
		// $sap_call->Debug(); // tohle vypisuje detaily o volani
		if ($sap_call->GetStatus() != SAPRFC_OK) {
			throw new Exception("Chyba pri volani SAPu");
		}

		$identify_by_array = array(
			self::IDENTIFY_BY_EAN,
			self::IDENTIFY_BY_EXTERNAL_ID
		);

		$matnr_external_ids = array();
		$sap_call->ET_V_EINE->Reset();
		while ($sap_call->ET_V_EINE->Next()) {
			$id = mb_strtolower(trim($sap_call->ET_V_EINE->row[$col]), 'UTF-8');
			$this->known_ids[$id] = $id;
			$this->unknown_ids[$id] = array();
			if ($this->feed_attributes['identify_by'] == self::IDENTIFY_BY_EAN) {
				$this->matnr_eans[$sap_call->ET_V_EINE->row['MATNR']][] = $id;
				$this->ean_matnr[$id] = $sap_call->ET_V_EINE->row['MATNR'];
			} else {
				$this->external_id_matnr[$id] = $sap_call->ET_V_EINE->row['MATNR'];
			}

			if ($this->feed_attributes['crosscheck'] == 't' && in_array($this->feed_attributes['identify_by'], $identify_by_array)) {
				$ean = mb_strtolower(trim($sap_call->ET_V_EINE->row['EAN11']), 'UTF-8');
				$external_id = mb_strtolower(trim($sap_call->ET_V_EINE->row['IDNLF']), 'UTF-8');
				$matnr_external_ids[$sap_call->ET_V_EINE->row['MATNR']] = $external_id;

				if ($this->feed_attributes['identify_by'] == self::IDENTIFY_BY_EAN) {
					$this->crosscheck_ids[$id][] = $external_id;
				} else {
					$this->crosscheck_ids[$id][] = $ean;
				}

				if (!empty($external_id) && in_array($external_id, $this->crosscheck_external_ids)) {
					$this->crosscheck_repeated_external_ids[] = $external_id;
				}
				$this->crosscheck_external_ids[] = $external_id;

				if (!empty($ean) && in_array($ean, $this->crosscheck_eans)) {
					$this->crosscheck_repeated_eans[] = $ean;
				}
				$this->crosscheck_eans[] = $ean;
			}
		}

		// dodatkove EANy
		$sap_call->E_EAN->Reset();
		while ($sap_call->E_EAN->Next()) {
			$ean = mb_strtolower(trim($sap_call->E_EAN->row['EAN11']), 'UTF-8');

			if ($this->feed_attributes['identify_by'] == self::IDENTIFY_BY_EAN) {
				$this->known_ids[$ean] = $ean;
				$this->unknown_ids[$ean] = array();
				$this->matnr_eans[$sap_call->E_EAN->row['MATNR']][] = $ean;
				$this->ean_matnr[$ean] = $sap_call->E_EAN->row['MATNR'];
			}

			if ($this->feed_attributes['crosscheck'] == 't' && in_array($this->feed_attributes['identify_by'], $identify_by_array)) {
				$external_id = $matnr_external_ids[$sap_call->E_EAN->row['MATNR']];

				if (!empty($external_id)) {
					if ($this->feed_attributes['identify_by'] == self::IDENTIFY_BY_EAN && $this->feed_attributes['crosscheck'] == 't') {
						$this->crosscheck_ids[$ean][] = $external_id;
					} else {
						$this->crosscheck_ids[$external_id][] = $ean;
					}
				}

				if (!empty($ean) && in_array($ean, $this->crosscheck_eans)) {
					$this->crosscheck_repeated_eans[] = $ean;
				}
				$this->crosscheck_eans[] = $ean;
			}
		}
	}

	/**
	 * metoda urcena na stahovanie suboru ak existuje specificky loader pouzije sa ten a ak nie tak sa pouzije vseobecna funkcia na stahovanie
	 *
	 * @throws Exception
	 */
	public function load()
	{
		if ($this->feed_attributes['loader'] === null) {
			// neni specifickej loader, jde jen o stazeni souboru
			switch ($this->feed_attributes['protocol']) {
				case 'http':
				case 'https':
					$this->load_source_from_http();
					break;
				case 'ftp':
					$this->load_source_from_ftp();
					break;
				case 'sftp':
					$this->load_source_from_sftp();
					break;
				default:
					throw new Exception('v nastaveni je zadany neznamy protokol');
					break;
			}
		} else {
			// v konfiguraci je specifickej loader, pouzije se ten
			$class_name = 'SF_' . $this->feed_attributes['loader'];
			if (class_exists($class_name) && method_exists($class_name, 'load')) {
				$class = new $class_name();
				$this->input_file = $class->load($this->db, $this->feed_attributes);
			} else {
				throw new Exception('loader neexistuje nebo nema verejnou metodu load');
			}
		}
	}

	/**
	 * metoda urcena na parsovanie suboru ak existuje specificky parser pouzije sa ten a ak nie tak sa pouzije vseobecny parser xml alebo csv
	 *
	 * @throws Exception
	 */
	public function parse()
	{
		if ($this->feed_attributes['parser'] === null) {
			// neni specifickej parser, zpracovat dle typu souboru
			switch ($this->feed_attributes['file_format']) {
				case 'xml':
					$this->parse_xml();
					break;
				case 'csv':
					$this->parse_csv();
					break;
				default:
					throw new Exception('v nastaveni je zadany neznamy typ souboru');
					break;
			}
		} else {
			// v konfiguraci je specifickej parser, pouzije se ten
			$class_name = 'SF_' . $this->feed_attributes['parser'];
			if (class_exists($class_name) && method_exists($class_name, 'parse')) {
				$class = new $class_name();
				$parsed_rows = $class->parse($this->feed_attributes, $this->input_file, $this, $this->db);
				foreach ($parsed_rows as $row_data) {
					$this->add_row($row_data);
				}
			} else {
				throw new Exception('parser neexistuje nebo nema verejnou metodu parse');
			}
		}
	}

	/**
	 * vseobecny xml parser
	 *
	 * @throws Exception
	 */
	protected function parse_xml()
	{
		if (empty($this->input_file)) {
			// soubor nenacten
			throw new Exception('Empty input file for source #' . $this->feed_attributes['source_id']);
		}
		if (!$xml = @simplexml_load_string($this->input_file, 'SimpleXMLElement', LIBXML_PARSEHUGE)) {
			// wrong XML format
			throw new Exception('Error parsing XML document');
		}

		if ($this->feed_attributes['xml_element'] === null) {
			throw new Exception('atribut xml_element nemoze byt prazdny');
		}

		$xml_element = $this->feed_attributes['xml_element'];
		switch ($this->feed_attributes['identify_by']) {
			case self::IDENTIFY_BY_EAN:
				if ($this->feed_attributes[self::IDENTIFY_BY_EAN] === null) {
					throw new Exception('atribut ean nemoze byt prazdny');
				}
				$ean = $this->feed_attributes[self::IDENTIFY_BY_EAN];
				if ($this->feed_attributes['crosscheck'] == 't') {
					if ($this->feed_attributes[self::IDENTIFY_BY_EXTERNAL_ID] === null) {
						throw new Exception('atribut external_id nemoze byt prazdny pri aktivovanej krizovej kontrole');
					}
					$external_id = $this->feed_attributes[self::IDENTIFY_BY_EXTERNAL_ID];
				}
				break;
			case self::IDENTIFY_BY_EXTERNAL_ID:
				if ($this->feed_attributes[self::IDENTIFY_BY_EXTERNAL_ID] === null) {
					throw new Exception('atribut external_id nemoze byt prazdny');
				}
				$external_id = $this->feed_attributes[self::IDENTIFY_BY_EXTERNAL_ID];
				if ($this->feed_attributes['crosscheck'] == 't') {
					if ($this->feed_attributes[self::IDENTIFY_BY_EAN] === null) {
						throw new Exception('atribut ean nemoze byt prazdny pri aktivovanej krizovej kontrole');
					}
					$ean = $this->feed_attributes[self::IDENTIFY_BY_EAN];
				}
				break;
			case self::IDENTIFY_BY_VARIANT_ID:
				if ($this->feed_attributes[self::IDENTIFY_BY_VARIANT_ID] === null) {
					throw new Exception('atribut variant_id nemoze byt prazdny');
				}
				$variant_id = $this->feed_attributes[self::IDENTIFY_BY_VARIANT_ID];
				break;
			default:
				throw new Exception('v nastaveni je zadany neznamy identifikacie podla (mozne je pouzit len "ean", "external_id", "variant_id")');
				break;
		}

		if ($this->feed_attributes['save_stock'] == 't') {
			if ($this->feed_attributes['stock'] === null) {
				throw new Exception('atribut stock nemoze byt prazdny');
			}
			$stock = $this->feed_attributes['stock'];
		}

		if ($this->feed_attributes['save_price'] == 't') {
			if ($this->feed_attributes['price'] === null) {
				throw new Exception('atribut price nemoze byt prazdny');
			}
		}
		foreach ($this->extractXmlValue($xml, $xml_element) as $item) {
			$data = array();
			switch ($this->feed_attributes['identify_by']) {
				case self::IDENTIFY_BY_EAN:
					$data[self::IDENTIFY_BY_EAN] = $this->extractXmlValueAsString($item, $ean);
					if ($this->feed_attributes['crosscheck'] == 't') {
						$external_id_value = $this->extractXmlValueAsString($item, $external_id);
						$external_id_value = preg_replace('~\s+~', ' ', $external_id_value);
						$data[self::IDENTIFY_BY_EXTERNAL_ID] = $external_id_value;
					}
					break;
				case self::IDENTIFY_BY_EXTERNAL_ID:
					$external_id_value = $this->extractXmlValueAsString($item, $external_id);
					$external_id_value = preg_replace('~\s+~', ' ', $external_id_value);
					$data[self::IDENTIFY_BY_EXTERNAL_ID] = $external_id_value;
					if ($this->feed_attributes['crosscheck'] == 't') {
						$data[self::IDENTIFY_BY_EAN] = $this->extractXmlValueAsString($item, $ean);
					}
					break;
				case self::IDENTIFY_BY_VARIANT_ID:
					$data[self::IDENTIFY_BY_VARIANT_ID] = $this->extractXmlValueAsString($item, $variant_id);
					break;
			}

			if ($this->feed_attributes['save_stock'] == 't') {
				$stock_value = $this->extractXmlValueAsString($item, $stock, false);
				$stock_value = mb_strtolower($stock_value, 'UTF-8');

				if (isset($this->xml_stock_convert[$this->feed_attributes['purchase_organization']][$stock_value])) {
					$data['stock'] = $this->xml_stock_convert[$this->feed_attributes['purchase_organization']][$stock_value];
					if ($data['stock'] === 'default_in_stock') {
						$data['stock'] = $this->feed_attributes['default_in_stock'];
					}
				} else {
					$data['stock'] = $this->normalize_number(trim((string) $stock_value, '<> '));
				}

				if ($data['stock'] < 0) {
					$data['stock'] = 0;
				}
			}

			if ($this->feed_attributes['save_price'] == 't') {
				$price = $this->normalize_number($this->extractXmlValueAsString($item, $this->feed_attributes['price'], false));
				if ($this->feed_attributes['price_2'] !== null) {
					$price += $this->normalize_number($this->extractXmlValueAsString($item, $this->feed_attributes['price_2'], false));
				}
				if ($this->feed_attributes['price_3'] !== null) {
					$price += $this->normalize_number($this->extractXmlValueAsString($item, $this->feed_attributes['price_3'], false));
				}
				if ($this->feed_attributes['price_4'] !== null) {
					$price += $this->normalize_number($this->extractXmlValueAsString($item, $this->feed_attributes['price_4'], false));
				}
				$data['price'] = $price;
			}

			$this->add_row($data);
		}
	}

	/**
	 * vseobecny csv parser
	 *
	 * @throws Exception
	 */
	protected function parse_csv()
	{
		if (empty($this->input_file)) {
			throw new Exception('Empty input file for source #' . $this->feed_attributes['source_id']);
		}

		switch ($this->feed_attributes['identify_by']) {
			case self::IDENTIFY_BY_EAN:
				if ($this->feed_attributes[self::IDENTIFY_BY_EAN] === null) {
					throw new Exception('atribut ean nemoze byt prazdny');
				}
				if ($this->feed_attributes['crosscheck'] == 't' && $this->feed_attributes[self::IDENTIFY_BY_EXTERNAL_ID] === null) {
					throw new Exception('atribut external_id nemoze byt prazdny pri aktivovanej krizovej kontrole');
				}
				break;
			case self::IDENTIFY_BY_EXTERNAL_ID:
				if ($this->feed_attributes[self::IDENTIFY_BY_EXTERNAL_ID] === null) {
					throw new Exception('atribut external_id nemoze byt prazdny');
				}
				if ($this->feed_attributes['crosscheck'] == 't' && $this->feed_attributes[self::IDENTIFY_BY_EAN] === null) {
					throw new Exception('atribut ean nemoze byt prazdny pri aktivovanej krizovej kontrole');
				}
				break;
			case self::IDENTIFY_BY_VARIANT_ID:
				if ($this->feed_attributes[self::IDENTIFY_BY_VARIANT_ID] === null) {
					throw new Exception('atribut variant_id nemoze byt prazdny');
				}
				break;
			default:
				throw new Exception('v nastaveni je zadany neznamy identifikacie podla (mozne je pouzit len "ean", "external_id", "variant_id")');
				break;
		}

		if ($this->feed_attributes['save_price'] == 't' && $this->feed_attributes['price'] === null) {
			throw new Exception('atribut price nemoze byt prazdny');
		}

		if ($this->feed_attributes['save_stock'] == 't' && $this->feed_attributes['stock'] === null) {
			throw new Exception('atribut stock nemoze byt prazdny');
		}

		$lines = explode("\n", $this->input_file);
		foreach ($lines as $line) {
			if (!empty($line)) {
				$fields = $this->csv_parse_line($line, ";", '"');
				$data = array();
				switch ($this->feed_attributes['identify_by']) {
					case self::IDENTIFY_BY_EAN:
						$data[self::IDENTIFY_BY_EAN] = trim((string) $fields[$this->feed_attributes[self::IDENTIFY_BY_EAN] - 1]);
						if ($this->feed_attributes['crosscheck'] == 't') {
							$data[self::IDENTIFY_BY_EXTERNAL_ID] = trim((string) $fields[$this->feed_attributes[self::IDENTIFY_BY_EXTERNAL_ID] - 1]);
						}
						break;
					case self::IDENTIFY_BY_EXTERNAL_ID:
						$data[self::IDENTIFY_BY_EXTERNAL_ID] = trim((string) $fields[$this->feed_attributes[self::IDENTIFY_BY_EXTERNAL_ID] - 1]);
						if ($this->feed_attributes['crosscheck'] == 't') {
							$data[self::IDENTIFY_BY_EAN] = trim((string) $fields[$this->feed_attributes[self::IDENTIFY_BY_EAN] - 1]);
							;
						}
						break;
					case self::IDENTIFY_BY_VARIANT_ID:
						$data[self::IDENTIFY_BY_VARIANT_ID] = trim((string) $fields[$this->feed_attributes[self::IDENTIFY_BY_VARIANT_ID] - 1]);
						break;
				}

				if ($this->feed_attributes['save_price'] == 't') {
					$price = $this->normalize_number((string) $fields[$this->feed_attributes['price'] - 1]);
					if ($this->feed_attributes['price_2'] !== null) {
						$price += $this->normalize_number((string) $fields[$this->feed_attributes['price_2'] - 1]);
					}
					if ($this->feed_attributes['price_3'] !== null) {
						$price += $this->normalize_number((string) $fields[$this->feed_attributes['price_3'] - 1]);
					}
					if ($this->feed_attributes['price_4'] !== null) {
						$price += $this->normalize_number((string) $fields[$this->feed_attributes['price_4'] - 1]);
					}
					$data['price'] = $price;
				}

				if ($this->feed_attributes['save_stock'] == 't') {
					$stock_value = (string) $fields[$this->feed_attributes['stock'] - 1];
					$stock_value = mb_strtolower($stock_value, 'UTF-8');

					if (isset($this->csv_stock_convert[$this->feed_attributes['purchase_organization']][$stock_value])) {
						$data['stock'] = $this->csv_stock_convert[$this->feed_attributes['purchase_organization']][$stock_value];
						if ($data['stock'] === 'default_in_stock') {
							$data['stock'] = $this->feed_attributes['default_in_stock'];
						}
					} else {
						$data['stock'] = $this->normalize_number(trim($stock_value));
					}

					if ($data['stock'] < 0) {
						$data['stock'] = 0;
					}
				}

				$this->add_row($data);
			}
		}
	}

	/**
	 *
	 * @param SimpleXMLElement $element
	 * @param string $path
	 * @param bool $trim = true
	 * @return string
	 */
	protected function extractXmlValueAsString(SimpleXMLElement $element, $path, $trim = true)
	{
		$element = $this->extractXmlValue($element, $path);
		$string = (string) $element;

		if ($trim) {
			$string = trim($string);
		}

		return $string;
	}

	/**
	 *
	 * @param SimpleXMLElement $element
	 * @param string $path
	 * @return SimpleXMLElement @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	protected function extractXmlValue(SimpleXMLElement $element, $path)
	{
		$expression = sprintf('return $element%s;', $path);

		return eval($expression);
	}
}
