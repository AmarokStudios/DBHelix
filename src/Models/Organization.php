<?php

	namespace DBHelix\Models;

	class Organization {
		private int $ID;
		private string $Name;
		private string $SubscriptionLevel;
		private int $IntegrationExecutionTokens;
		private string $CreatedAt;
		private string $CreatedBy;
		private string $ModifiedAt;
		private string $ModifiedBy;

		public function setID(int $ID) {
			$this->ID = $ID;
		}

		public function getID(): int {
			return $this->ID;
		}

		public function setName(string $Name) {
			$this->Name = $Name;
		}

		public function getName(): string {
			return $this->Name;
		}

		public function setSubscriptionLevel(string $SubscriptionLevel) {
			$this->SubscriptionLevel = $SubscriptionLevel;
		}

		public function getSubscriptionLevel(): string {
			return $this->SubscriptionLevel;
		}

		public function setIntegrationExecutionTokens(int $IntegrationExecutionTokens) {
			$this->IntegrationExecutionTokens = $IntegrationExecutionTokens;
		}

		public function getIntegrationExecutionTokens(): int {
			return $this->IntegrationExecutionTokens;
		}

		public function setCreatedAt(string $CreatedAt) {
			$this->CreatedAt = $CreatedAt;
		}

		public function getCreatedAt(): string {
			return $this->CreatedAt;
		}
	}