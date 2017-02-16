<?php
/**
 * ownCloud
 *
 * @author Robin Appelman
 * @author Bernhard Posselt
 * @copyright 2012 Robin Appelman <icewind@owncloud.com>
 * @copyright 2012 Bernhard Posselt <dev@bernhard-posselt.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Group;

use OC_Group;
use OC_User;
use Test\Traits\UserTrait;

/**
 * Class LegacyGroupTest
 *
 * @group DB
 *
 * @package Test\Group
 */
class LegacyGroupTest extends \Test\TestCase {
	use UserTrait;

	protected function setUp() {
		parent::setUp();
		OC_Group::clearBackends();
		OC_User::clearBackends();
	}

	public function testSingleBackend() {
		OC_Group::useBackend(new \Test\Util\Group\Dummy());

		$group1 = $this->getUniqueID();
		$group2 = $this->getUniqueID();
		OC_Group::createGroup($group1);
		OC_Group::createGroup($group2);

		$user1 = $this->getUniqueID();
		$user2 = $this->getUniqueID();
		$this->createUser($user1);
		$this->createUser($user2);

		$this->assertFalse(OC_Group::inGroup($user1, $group1), 'Asserting that user1 is not in group1');
		$this->assertFalse(OC_Group::inGroup($user2, $group1), 'Asserting that user2 is not in group1');
		$this->assertFalse(OC_Group::inGroup($user1, $group2), 'Asserting that user1 is not in group2');
		$this->assertFalse(OC_Group::inGroup($user2, $group2), 'Asserting that user2 is not in group2');

		$this->assertTrue(OC_Group::addToGroup($user1, $group1));

		$this->assertTrue(OC_Group::inGroup($user1, $group1), 'Asserting that user1 is in group1');
		$this->assertFalse(OC_Group::inGroup($user2, $group1), 'Asserting that user2 is not in group1');
		$this->assertFalse(OC_Group::inGroup($user1, $group2), 'Asserting that user1 is not in group2');
		$this->assertFalse(OC_Group::inGroup($user2, $group2), 'Asserting that user2 is not in group2');

		$this->assertTrue(OC_Group::addToGroup($user1, $group1));

		$this->assertEquals([$user1], OC_Group::usersInGroup($group1));
		$this->assertEquals([], OC_Group::usersInGroup($group2));

		$this->assertEquals([$group1], OC_Group::getUserGroups($user1));
		$this->assertEquals([], OC_Group::getUserGroups($user2));

		OC_Group::deleteGroup($group1);
		$this->assertEquals([], OC_Group::getUserGroups($user1));
		$this->assertEquals([], OC_Group::usersInGroup($group1));
		$this->assertFalse(OC_Group::inGroup($user1, $group1));
	}


	public function testNoEmptyGIDs() {
		OC_Group::useBackend(new \Test\Util\Group\Dummy());
		$emptyGroup = null;

		$this->assertFalse(OC_Group::createGroup($emptyGroup));
	}


	public function testNoGroupsTwice() {
		OC_Group::useBackend(new \Test\Util\Group\Dummy());
		$group = $this->getUniqueID();
		OC_Group::createGroup($group);

		$groupCopy = $group;

		OC_Group::createGroup($groupCopy);
		$this->assertEquals([$group], OC_Group::getGroups());
	}


	public function testDontDeleteAdminGroup() {
		OC_Group::useBackend(new \Test\Util\Group\Dummy());
		$adminGroup = 'admin';
		OC_Group::createGroup($adminGroup);

		$this->assertFalse(OC_Group::deleteGroup($adminGroup));
		$this->assertEquals([$adminGroup], OC_Group::getGroups());
	}


	public function testDontAddUserToNonexistentGroup() {
		OC_Group::useBackend(new \Test\Util\Group\Dummy());
		$groupNonExistent = 'notExistent';
		$user = $this->getUniqueID();

		$this->assertEquals(false, OC_Group::addToGroup($user, $groupNonExistent));
		$this->assertEquals([], OC_Group::getGroups());
	}

	public function testUsersInGroup() {
		OC_Group::useBackend(new \Test\Util\Group\Dummy());

		$group1 = $this->getUniqueID();
		$group2 = $this->getUniqueID();
		$group3 = $this->getUniqueID();
		$user1 = $this->getUniqueID();
		$user2 = $this->getUniqueID();
		$user3 = $this->getUniqueID();
		OC_Group::createGroup($group1);
		OC_Group::createGroup($group2);
		OC_Group::createGroup($group3);

		$this->createUser($user1);
		$this->createUser($user2);
		$this->createUser($user3);

		OC_Group::addToGroup($user1, $group1);
		OC_Group::addToGroup($user2, $group1);
		OC_Group::addToGroup($user3, $group1);
		OC_Group::addToGroup($user3, $group2);

		$this->assertEquals([$user1, $user2, $user3],
			OC_Group::usersInGroups([$group1, $group2, $group3]));

		// FIXME: needs more parameter variation
	}
}
