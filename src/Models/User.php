<?php

	namespace DBHelix\Models;

	class User {
		private int $ID;
		private $Organization; // Organization_ID - TODO: Need to create org model
		private string $Username;
		private string $Email;
		private string $Password;
		private string $PasswordHash;
		private bool $IsAdmin;
		private bool $IsActive;
		private string $CreatedAt;
		private string $CreatedBy;
		private string $ModifiedAt;
		private string $ModifiedBy;

		public function getID() {
			return (empty($this->ID)) ? null : $this->ID;
		}

		public function setID(int $ID) {
			$this->ID = $ID;
		}
		public function getOrganization() {
			return $this->Organization;
		}
		public function setOrganization($Organization) {
			$this->Organization = $Organization;
		}

		public function getUsername() {
			return $this->Username;
		}

		public function setUsername(string $Username) {
			$this->Username = $Username;
		}

		public function getEmail() {
			return $this->Email;
		}

		public function setPassword($Password) {
			$this->Password = $Password;
		}

		public function getPassword() {
			return $this->Password;
		}

		public function setPasswordHash($PasswordHash) {
			$this->PasswordHash = $PasswordHash;
		}

		public function getPasswordHash() {
			return $this->PasswordHash;
		}

		public function setEmail(string $Email) {
			$this->Email = $Email;
		}

		public function getIsAdmin() {
			return $this->IsAdmin;
		}

		public function setIsAdmin(bool $IsAdmin) {
			$this->IsAdmin = $IsAdmin;
		}

		public function getIsActive() {
			return $this->IsActive;
		}

		public function setIsActive(bool $IsActive) {
			$this->IsActive = $IsActive;
		}

		public function setCreatedAt(string $CreatedAt) {
			$this->CreatedAt = $CreatedAt;
		}

		public function getCreatedAt() {
			return $this->CreatedAt;
		}

		public function setCreatedBy(string $CreatedBy) {
			$this->CreatedBy = $CreatedBy;
		}

		public function getCreatedBy() {
			return $this->CreatedBy;
		}

		public function setModifiedAt(string $ModifiedAt) {
			$this->ModifiedAt = $ModifiedAt;
		}

		public function getModifiedAt() {
			return $this->ModifiedAt;
		}

		public function setModifiedBy(string $ModifiedBy) {
			$this->ModifiedBy = $ModifiedBy;
		}

		public function getModifiedBy() {
			return $this->ModifiedBy;
		}
	}