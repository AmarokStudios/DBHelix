<?php

	namespace DBHelix\Interfaces;

	interface DatabaseInterface {

		public function enableLogging(): void;

		public function disableLogging(): void;

		public function query($SQL, $Params = []);

		public function select($Table, $Columns = '*', $Where = '', $Params = [], $Cache = false, $CacheDuration = 60);

		public function insert($Table, $Data);

		public function batchInsert($Table, $DataArray): void;

		public function batchUpdate($Table, $DataArray, $WhereColumn);

		public function update($Table, $Data, $Where, $WhereParams = []);

		public function renameColumn($Table, $OldColumnName, $NewColumnName, $ColumnType);

		public function dropColumn($Table, $ColumnName);

		public function delete($Table, $Where, $Params = []);

		public function countRows($Table, $Where = '', $Params = []);

		public function getTableSchema($Table);

		public function createTable($Table, $Columns);

		public function dropTable($Table);

		public function listTables();

		public function renameTable($oldName, $newName);

		public function truncateTable($tableName);

		public function tableExists($Table);

		public function escapeIdentifier($Identifier);

		public function beginTransaction();

		public function commit();

		public function rollBack();

		public function savepoint($SavepointName);

		public function rollbackToSavepoint($SavepointName);

		public function getConnection();

		public function exportToCSV($Table, $FilePath);

		public function exportToJSON($Table, $FilePath);

		public function importFromCSV($Table, $FilePath);

		public function callStoredProcedure($ProcedureName, $Params = []);

		public function buildSelectQuery($Table, $Columns = '*', $Conditions = [], $OrderBy = '', $Limit = '');

		public function applyMigrations($MigrationsDirectory);

		public function backupDatabase($backupFilePath);

		public function backupSelectedTables($backupFilePath, $tablesToBackup);

		public function restoreFromFile($backupFilePath);
	}
