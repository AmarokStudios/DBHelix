<?php

	namespace DBHelix\Utils;
		class Functions {
			public static function GET_CLIENT_IP() {
				return $_SERVER['HTTP_X_FORWARDED_FOR']
					?? $_SERVER['REMOTE_ADDR']
					?? $_SERVER['HTTP_CLIENT_IP']
					?? 'UNKNOWN';
			}

			public static function GET_USER_AGENT() {
				return $_SERVER['HTTP_USER_AGENT']
					?? 'UNKNOWN';
			}
		}