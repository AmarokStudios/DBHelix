<?php

	namespace DBHelix;

	use Exception;

	class Container {
		private array $bindings = [];

		public function set($name, $resolver): void {
			$this->bindings[$name] = $resolver;
		}

		public function get($name) {
			if (isset($this->bindings[$name])) {
				return call_user_func($this->bindings[$name]);
			}
			throw new Exception("Service not found: " . $name);
		}
	}