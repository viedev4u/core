<?php
/**
 * Copyright (c) 2015 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Traits;

use OC\User\Account;
use OC\User\AccountMapper;
use OC\User\User;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;

class MemoryAccountMapper extends AccountMapper {

	private static $accounts = [];
	private static $counter = 1000;

	public function insert(Entity $entity) {
		$entity->setId(self::$counter++);
		self::$accounts[$entity->getId()] = $entity;

		return $entity;
	}

	public function update(Entity $entity) {
		self::$accounts[$entity->getId()] = $entity;

		return $entity;
	}

	public function delete(Entity $entity) {
		unset(self::$accounts[$entity->getId()]);
	}

	public function getByEmail($email) {
		$match = array_filter(self::$accounts, function (Account $a) use ($email) {
			return $a->getEmail() === $email;
		});

		return $match;
	}

	public function getByUid($uid) {
		$match = array_filter(self::$accounts, function (Account $a) use ($uid) {
			return strtolower($a->getUserId()) === strtolower($uid);
		});
		if (empty($match)) {
			throw new DoesNotExistException('');
		}

		return array_values($match)[0];
	}

	public function getUserCount($hasLoggedIn) {
		return count(self::$accounts);
	}

	public function search($fieldName, $pattern, $limit, $offset) {
		$match = array_filter(self::$accounts, function (Account $a) use ($pattern) {
			return stripos($a->getUserId(), $pattern);
		});

		return $match;
	}
}

/**
 * Allow creating users in a temporary backend
 */
trait UserTrait {

	/** @var User[] */
	private $users = [];

	protected $useRealUserManager = false;

	protected function createUser($name, $password = null) {
		if (is_null($password)) {
			$password = $name;
		}
		$userManager = \OC::$server->getUserManager();
		if ($userManager->userExists($name)) {
			$userManager->get($name)->delete();
		}
		$user = $userManager->createUser($name, $password);
		$this->users[] = $user;
		return $user;
	}

	protected function setUpUserTrait() {
		if ($this->useRealUserManager) {
			return;
		}
		$config = \OC::$server->getConfig();
		$db = \OC::$server->getDatabaseConnection();
		$accountMapper = new MemoryAccountMapper($db);
		$userManager = new \OC\User\Manager($config, $accountMapper);

		$this->overwriteService('UserManager', $userManager);
	}

	protected function tearDownUserTrait() {
		foreach($this->users as $user) {
			$user->delete();
		}
		$this->restoreService('UserManager');
	}
}
