<?php

	namespace DBHelix\Repositories;
	require dirname(__FILE__, 3) . '/vendor/autoload.php';

	use DBHelix\Config;
	use DBHelix\Exceptions\DatabaseException;
	use DBHelix\Interfaces\DatabaseInterface;
	use DBHelix\Utils\Logger;
	use PDO;
	use PDOException;

	class DatabaseRepository implements DatabaseInterface {
		private PDO $PDO;
		private Logger $Logger;
		private Config $Config;
		private array $Cache = [];
		private array $StmtCache = [];
		private array $CacheExpiry = [];
		private int $TransactionCounter = 0;
		private bool $LoggingEnabled = false;

		// See: https://dev.mysql.com/doc/connector-j/en/connector-j-reference-error-sqlstates.html
		private array $RetryCodes = [
			 '1043'		// Bad handshake
		];

		public function __construct(Config $Config, Logger $Logger) {
			$this->Config = $Config;
			$this->Logger = $Logger;
			$this->connect();
		}

		private function connect(): void {
			try {
				$DSN = "{$this->Config->get('driver')}:host={$this->Config->get('host')};dbname={$this->Config->get('dbname')};charset=utf8mb4";
				$this->PDO = new PDO($DSN, $this->Config->get('username'), $this->Config->get('password'), [
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
					PDO::ATTR_EMULATE_PREPARES => false,
				]);
			} catch (PDOException $E) {
				$this->handleError($E);
			}
		}

		public function enableLogging(): void {
			$this->LoggingEnabled = true;
		}

		public function disableLogging(): void {
			$this->LoggingEnabled = false;
		}

		private function handleError($Exception) {
			$ErrorMessage = "Error: " . $Exception->getMessage();
			if ($this->LoggingEnabled) $this->Logger->log($ErrorMessage);
			throw new DatabaseException($ErrorMessage);
		}

		private function retryQuery($SQL, $Params, $Retries = 3) {
			$Attempt = 0;
			while ($Attempt < $Retries) {
				try {
					$Stmt = $this->prepareStmt($SQL);
					$Stmt->execute($Params);
					return $Stmt;
				} catch (PDOException $E) {
					$Attempt++;
					if ($Attempt >= $Retries) {
						$this->handleError($E);
					}
				}
			}
		}

		public function query($SQL, $Params = []) {
			$StartTime = microtime(true);
			try {
				$Stmt = $this->prepareStmt($SQL);
				$Stmt->execute($Params);
				$ExecutionTime = round((microtime(true) - $StartTime) * 1000, 2);
				if ($this->LoggingEnabled) $this->Logger->logQuery($SQL, $Params, $ExecutionTime);
				return $Stmt;
			} catch (DatabaseException $E) {
				If (in_array($E->getCode(), $this->RetryCodes)) {
					if ($this->LoggingEnabled) $this->Logger->logError("Query failed. Retrying...");
					return $this->retryQuery($SQL, $Params);
				} else {
					if ($this->LoggingEnabled) $this->Logger->logError("Query failed. Error: (" . $E->getCode() . ")" . $E->getMessage());
					return $E->getMessage();
				}
			}
		}

		private function prepareStmt($SQL) {
			if (isset($this->StmtCache[$SQL])) {
				return $this->StmtCache[$SQL];
			}
			$Stmt = $this->PDO->prepare($SQL);
			$this->StmtCache[$SQL] = $Stmt;
			return $Stmt;
		}

		public function select($Table, $Columns = '*', $Where = '', $Params = [], $Cache = false, $CacheDuration = 60) {
			$SQL = "SELECT {$Columns} FROM {$Table}";
			if ($Where) {
				$SQL .= " WHERE {$Where}";
			}
			$CacheKey = $SQL . json_encode($Params);
			if ($Cache && isset($this->Cache[$CacheKey])) {
				if (time() - $this->CacheExpiry[$CacheKey] < $CacheDuration) {
					return $this->Cache[$CacheKey];
				} else {
					unset($this->Cache[$CacheKey]);
					unset($this->CacheExpiry[$CacheKey]);
				}
			}
			$Result = $this->query($SQL, $Params)->fetchAll();
			if ($Cache) {
				$this->Cache[$CacheKey] = $Result;
				$this->CacheExpiry[$CacheKey] = time();
			}
			return $Result;
		}

		public function insert($Table, $Data) {
			$Columns = implode(', ', array_keys($Data));
			$Placeholders = ':' . implode(', :', array_keys($Data));
			$SQL = "INSERT INTO {$Table} ({$Columns}) VALUES ({$Placeholders})";
			$this->query($SQL, $Data);
			return $this->PDO->lastInsertId();
		}

		public function batchInsert($Table, $DataArray): void {
			$Columns = implode(', ', array_keys($DataArray[0]));
			$Placeholders = ':' . implode(', :', array_keys($DataArray[0]));
			$SQL = "INSERT INTO {$Table} ({$Columns}) VALUES ({$Placeholders})";

			$this->beginTransaction();
			try {
				$Stmt = $this->prepareStmt($SQL);
				foreach ($DataArray as $Data) {
					$Stmt->execute($Data);
					if ($this->LoggingEnabled) $this->Logger->logQuery($SQL, $Data);
				}
				$this->commit();
			} catch (PDOException $E) {
				$this->rollBack();
				$this->handleError($E);
			}
		}
		public function batchUpdate($Table, $DataArray, $WhereColumn) {
			$Columns = array_keys($DataArray[0]);
			$Set = implode(', ', array_map(function ($Column) use ($WhereColumn) {
				if ($Column !== $WhereColumn) {
					return "{$Column} = :{$Column}";
				}
			}, $Columns));
			$Set = implode(', ', array_filter(explode(', ', $Set))); // Filter out null values
			$SQL = "UPDATE {$Table} SET {$Set} WHERE {$WhereColumn} = :{$WhereColumn}";
			$this->beginTransaction();
			try {
				$Stmt = $this->prepareStmt($SQL);
				foreach ($DataArray as $Data) {
					$Stmt->execute($Data);
					if ($this->LoggingEnabled) $this->Logger->logQuery($SQL, $Data);
				}
				$this->commit();
			} catch (PDOException $E) {
				$this->rollBack();
				$this->handleError($E);
			}
		}

		public function update($Table, $Data, $Where, $WhereParams = []) {
			$Set = implode(', ', array_map(function ($Column) {
				return "{$Column} = :{$Column}";
			}, array_keys($Data)));
			$SQL = "UPDATE {$Table} SET {$Set} WHERE {$Where}";
			$Stmt = $this->PDO->prepare($SQL);

			// Merge data and where parameters to ensure all placeholders are covered
			$Params = array_merge($Data, $WhereParams);
			$Stmt->execute($Params);
		}

		public function renameColumn($Table, $OldColumnName, $NewColumnName, $ColumnType) {
			$SQL = "ALTER TABLE {$Table} CHANGE {$OldColumnName} {$NewColumnName} {$ColumnType}";
			$this->query($SQL);
		}

		public function dropColumn($Table, $ColumnName) {
			$SQL = "ALTER TABLE {$Table} DROP COLUMN {$ColumnName}";
			$this->query($SQL);
		}

		public function delete($Table, $Where, $Params = []) {
			$SQL = "DELETE FROM {$Table} WHERE {$Where}";
			$this->query($SQL, $Params);
		}

		public function countRows($Table, $Where = '', $Params = []) {
			$SQL = "SELECT COUNT(*) as count FROM {$Table}";
			if ($Where) {
				$SQL .= " WHERE {$Where}";
			}
			$Result = $this->query($SQL, $Params)->fetch();
			return $Result['count'];
		}

		public function getTableSchema($Table) {
			$SQL = "DESCRIBE {$Table}";
			return $this->query($SQL)->fetchAll();
		}

		public function createTable($Table, $Columns) {
			$ColumnDefs = [];
			foreach ($Columns as $Column => $Def) {
				$ColumnDefs[] = "{$Column} {$Def}";
			}
			$ColumnDefs = implode(', ', $ColumnDefs);
			$SQL = "CREATE TABLE IF NOT EXISTS {$Table} ({$ColumnDefs})";
			$this->query($SQL);
		}

		public function dropTable($Table) {
			$SQL = "DROP TABLE IF EXISTS {$Table};";
			$this->query($SQL);
		}

		public function listTables() {
			$SQL = "SHOW TABLES";
			return $this->query($SQL)->fetchAll();
		}

		public function renameTable($oldName, $newName) {
			$SQL = "RENAME TABLE {$oldName} TO {$newName}";
			return $this->query($SQL)->fetchAll();
		}

		public function truncateTable($tableName) {
			$SQL = "TRUNCATE TABLE {$tableName}";
			return $this->query($SQL)->fetchAll();
		}

		public function tableExists($Table) {
			try {
				$Result = $this->query("SELECT 1 FROM {$Table} LIMIT 1");
			} catch (PDOException $E) {
				return false;
			}
			return $Result !== false;
		}

		public function escapeIdentifier($Identifier) {
			return "`" . str_replace("`", "``", $Identifier) . "`";
		}

		public function beginTransaction() {
			if (!$this->TransactionCounter++) {
				$this->PDO->beginTransaction();
			}
		}

		public function commit() {
			if (!--$this->TransactionCounter) {
				$this->PDO->commit();
			}
		}

		public function rollBack() {
			if ($this->TransactionCounter >= 0) {
				$this->TransactionCounter = 0;
				$this->PDO->rollBack();
			}
		}

		public function savepoint($SavepointName) {
			$this->query("SAVEPOINT {$SavepointName}");
		}

		public function rollbackToSavepoint($SavepointName) {
			$this->query("ROLLBACK TO SAVEPOINT {$SavepointName}");
		}

		public function getConnection() {
			return $this->PDO;
		}

		public function exportToCSV($Table, $FilePath) {
			$Data = $this->select($Table);
			$File = fopen($FilePath, 'w');
			if (!empty($Data)) {
				fputcsv($File, array_keys($Data[0]));
				foreach ($Data as $Row) {
					fputcsv($File, $Row);
				}
			}
			fclose($File);
		}

		public function exportToJSON($Table, $FilePath) {
			$Data = $this->select($Table);
			file_put_contents($FilePath, json_encode($Data, JSON_PRETTY_PRINT));
		}

		public function importFromCSV($Table, $FilePath) {
			$File = fopen($FilePath, 'r');
			$Header = fgetcsv($File);
			$Data = [];
			while ($Row = fgetcsv($File)) {
				$Row = array_map(function($item) {
					return ($item != 0 && (empty(trim($item))) ? NULL : $item);
				}, $Row);
				$Data[] = array_combine($Header, $Row);
			}
			fclose($File);
			foreach ($Data as $Row) {
				$this->insert($Table, $Row);
			}
		}

		public function callStoredProcedure($ProcedureName, $Params = []) {
			$Placeholders = implode(', ', array_map(function ($Key) {
				return ":{$Key}";
			}, array_keys($Params)));
			$SQL = "CALL {$ProcedureName}({$Placeholders})";
			return $this->query($SQL, $Params)->fetchAll();
		}

		public function buildSelectQuery($Table, $Columns = '*', $Conditions = [], $OrderBy = '', $Limit = '', $Offset = '') {
			$SQL = "SELECT {$Columns} FROM {$Table}";
			if (!empty($Conditions)) {
				$SQL .= " WHERE " . implode(' AND ', array_map(function($Key) { return "{$Key} = :{$Key}"; }, array_keys($Conditions)));
			}
			if ($OrderBy) {
				$SQL .= " ORDER BY {$OrderBy}";
			}
			if ($Limit) {
				$SQL .= " LIMIT {$Limit}";
			}
			if ($Offset) {
				$SQL .= " OFFSET {$Offset}";
			}
			return $SQL;
		}

		// Migration System
		public function applyMigrations($MigrationsDirectory) {
			$AppliedMigrations = $this->getAppliedMigrations();

			$NewMigrations = [];
			foreach (scandir($MigrationsDirectory) as $File) {
				if (pathinfo($File, PATHINFO_EXTENSION) === 'php' && !in_array($File, $AppliedMigrations)) {
					$NewMigrations[] = $File;
				}
			}

			usort($NewMigrations, function($a, $b) {
				return strnatcmp($a, $b);
			});

			foreach ($NewMigrations as $Migration) {
				require_once $MigrationsDirectory . '/' . $Migration;
				$ClassName = pathinfo($Migration, PATHINFO_FILENAME);
				$MigrationInstance = new $ClassName();
				$MigrationInstance->up();
				$this->logMigration($Migration);
			}
		}

		private function getAppliedMigrations() {
			$this->query("CREATE TABLE IF NOT EXISTS migrations (id INT AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
			$Migrations = $this->query("SELECT migration FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
			return $Migrations;
		}

		private function logMigration($Migration) {
			$this->insert('migrations', ['migration' => $Migration]);
		}

		public function backupDatabase($backupFilePath) {
			$sql = "SHOW TABLES";
			$tables = $this->PDO->query($sql)->fetchAll(PDO::FETCH_COLUMN);
			$backupData = "";

			// Disable foreign key checks
			//$backupData .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

			foreach ($tables as $table) {
				$createTableSql = $this->PDO->query("SHOW CREATE TABLE {$table}")->fetch(PDO::FETCH_ASSOC);
				$backupData .= "DROP TABLE IF EXISTS {$table};\n";
				$backupData .= $createTableSql['Create Table'] . ";\n\n";

				$rows = $this->PDO->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
				foreach ($rows as $row) {
					$values = array_map(function($value) {
						return is_null($value) ? "NULL" : $this->PDO->quote($value);
					}, array_values($row));
					$columns = implode(", ", array_keys($row));
					$backupData .= "INSERT IGNORE INTO {$table} ({$columns}) VALUES (" . implode(", ", $values) . ");\n";
				}
				$backupData .= "\n\n";
			}

			// Re-enable foreign key checks
			//$backupData .= "SET FOREIGN_KEY_CHECKS = 1;\n";

			file_put_contents($backupFilePath, $backupData);
		}

		public function backupSelectedTables($backupFilePath, $tablesToBackup) {
			$backupData = "";
			foreach ($tablesToBackup as $table) {
				$createTableSql = $this->PDO->query("SHOW CREATE TABLE {$table}")->fetch(PDO::FETCH_ASSOC);
				$backupData .= "DROP TABLE IF EXISTS {$table};\n";
				$backupData .= $createTableSql['Create Table'] . ";\n\n";

				$rows = $this->PDO->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
				foreach ($rows as $row) {
					$values = array_map(function($value) {
						return is_null($value) ? "NULL" : $this->PDO->quote($value);
					}, array_values($row));
					$columns = implode(", ", array_keys($row));
					$backupData .= "INSERT IGNORE INTO {$table} ({$columns}) VALUES (" . implode(", ", $values) . ");\n";
				}
				$backupData .= "\n\n";
			}
			file_put_contents($backupFilePath, $backupData);
		}


		public function restoreFromFile($backupFilePath) {
			$sqls = file_get_contents($backupFilePath);
			$sqlArray = array_filter(array_map('trim', explode(";\n", $sqls))); // Split statements and remove empty ones

			try {
				// Disable foreign key checks before starting the transaction
				$this->PDO->exec("SET FOREIGN_KEY_CHECKS = 0;");

				// Start the main transaction
				$this->PDO->beginTransaction();

				foreach ($sqlArray as $index => $sql) {
					if (stripos($sql, 'SET FOREIGN_KEY_CHECKS') === false && !empty($sql)) {
						try {

							// Create a savepoint
							$savepointName = "sp" . $index;
							$this->savepoint($savepointName);

							// Execute the SQL statement
							$this->PDO->exec($sql);
						} catch (PDOException $e) {
							// Rollback to the last savepoint on failure
							$this->rollbackToSavepoint($savepointName);
							throw $e;
						}
					}
				}

				// Commit the whole transaction if all statements succeed
				if ($this->PDO->inTransaction()) {
					$this->commit();
				}


				// Enable foreign key checks after the transaction
				$this->PDO->exec("SET FOREIGN_KEY_CHECKS = 1;");
			} catch (PDOException $e) {
				// Rollback the entire transaction in case of any failure
				if ($this->PDO->inTransaction()) {
					$this->rollBack();
				}

				// Ensure foreign key checks are re-enabled if an error occurs
				$this->PDO->exec("SET FOREIGN_KEY_CHECKS = 1;");

				// Handle error
				echo $e->getMessage();
				$this->handleError($e);
			}
		}

	}