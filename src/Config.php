<?php

	namespace DBHelix;

	class Config {
		private array $Settings = [];

		public function __construct(array $Settings) {
			$this->Settings = $Settings;
		}

		public function get($Key) {
			return $this->Settings[$Key] ?? null;
		}

		public static function fromEnv($Env, $EnvFile = 'TESTS.env') {
			$EnvFile = parse_ini_file($EnvFile);
			$EnvPrefix = strtoupper($Env) . '_';

			return new self([
				'driver' => $EnvFile['DB_DRIVER'],
				'host' => $EnvFile[$EnvPrefix . 'DB_HOST'],
				'dbname' => $EnvFile[$EnvPrefix . 'DB_NAME'],
				'username' => $EnvFile[$EnvPrefix . 'DB_USERNAME'],
				'password' => $EnvFile[$EnvPrefix . 'DB_PASSWORD'],
//				'email' => [
//					'smtp_host' => $EnvFile['SMTP_HOST'],
//					'smtp_port' => $EnvFile['SMTP_PORT'],
//					'username' => $EnvFile['SMTP_USERNAME'],
//					'password' => $EnvFile['SMTP_PASSWORD'],
//				],
			]);
		}
	}