<?php
/**
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2017, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\Connector\Sabre;

use OCP\Comments\ICommentsManager;
use OCP\IUserSession;
use Sabre\DAV\PropFind;
use Sabre\DAV\ServerPlugin;
use OCP\Files\Folder;

class CommentPropertiesPlugin extends ServerPlugin {

	const PROPERTY_NAME_HREF   = '{http://owncloud.org/ns}comments-href';
	const PROPERTY_NAME_COUNT  = '{http://owncloud.org/ns}comments-count';
	const PROPERTY_NAME_UNREAD = '{http://owncloud.org/ns}comments-unread';

	/** @var  \Sabre\DAV\Server */
	protected $server;

	/** @var ICommentsManager */
	private $commentsManager;

	/** @var IUserSession */
	private $userSession;

	/**
	 * @var \OCP\Files\Folder
	 */
	private $userFolder;

	/**
	 * @var \OCP\IUser|null Current user
	 */
	private $user = null;

	/**
	 * @var int[]
	 */
	private $numberOfCommentsForNodes;

	public function __construct(ICommentsManager $commentsManager, IUserSession $userSession, Folder $userFolder) {
		$this->commentsManager = $commentsManager;
		$this->userSession = $userSession;
		$this->userFolder = $userFolder;
	}

	/**
	 * This initializes the plugin.
	 *
	 * This function is called by Sabre\DAV\Server, after
	 * addPlugin is called.
	 *
	 * This method should set up the required event subscriptions.
	 *
	 * @param \Sabre\DAV\Server $server
	 * @return void
	 */
	function initialize(\Sabre\DAV\Server $server) {
		$this->server = $server;
		$this->server->on('propFind', [$this, 'handleGetProperties']);
	}

	/**
	 * Adds tags and favorites properties to the response,
	 * if requested.
	 *
	 * @param PropFind $propFind
	 * @param \Sabre\DAV\INode $node
	 * @return void
	 */
	public function handleGetProperties(
		PropFind $propFind,
		\Sabre\DAV\INode $node
	) {
		if (!($node instanceof File) && !($node instanceof Directory)) {
			return;
		}

		// Get user session
		$this->user = $this->userSession->getUser();

		// Prefetch required data if we know that it is parent node
		if ($node instanceof \OCA\DAV\Connector\Sabre\Directory
			&& $propFind->getDepth() !== 0
			&& !is_null($propFind->getStatus(self::PROPERTY_NAME_UNREAD))) {

			$folderNode = $this->userFolder->get($node->getPath());

			// Get ID of parent folder
			$folderNodeID = intval($folderNode->getId());
			$nodeIdsArray = [$folderNodeID];
			$this->numberOfCommentsForNodes[$folderNodeID] = 0;

			// Get IDs for all children of the parent folder
			$children = $folderNode->getDirectoryListing();
			foreach ($children as $childNode) {
				if (!($childNode instanceof \OCP\Files\File) &&
					!($childNode instanceof \OCP\Files\Folder)) {
					return;
				}
				// Put node ID into an array
				$nodeId = intval($childNode->getId());
				array_push($nodeIdsArray, $nodeId);
				$this->numberOfCommentsForNodes[$nodeId] = 0;
			}

			if(!is_null($this->user)){
				// Fetch all unread comments with their nodeIDs
				$numberOfCommentsForNodes = $this->commentsManager->getNumberOfUnreadCommentsForNodes(
					'files',
					$nodeIdsArray,
					$this->user);

				// Map them to cached hash table
				foreach($numberOfCommentsForNodes as $nodeID => $numberOfCommentsForNode) {
					$this->numberOfCommentsForNodes[$nodeID] = intval($numberOfCommentsForNode);
				}
			}

		}

		$propFind->handle(self::PROPERTY_NAME_COUNT, function() use ($node) {
			return $this->commentsManager->getNumberOfCommentsForObject('files', strval($node->getId()));
		});

		$propFind->handle(self::PROPERTY_NAME_HREF, function() use ($node) {
			return $this->getCommentsLink($node);
		});

		$propFind->handle(self::PROPERTY_NAME_UNREAD, function() use ($node) {
			return $this->getUnreadCount($node);
		});
	}

	/**
	 * returns a reference to the comments node
	 *
	 * @param Node $node
	 * @return mixed|string
	 */
	public function getCommentsLink(Node $node) {
		$href =  $this->server->getBaseUri();
		$entryPoint = strpos($href, '/remote.php/');
		if($entryPoint === false) {
			// in case we end up somewhere else, unexpectedly.
			return null;
		}
		$commentsPart = 'dav/comments/files/' . rawurldecode($node->getId());
		$href = substr_replace($href, $commentsPart, $entryPoint + strlen('/remote.php/'));
		return $href;
	}

	/**
	 * returns the number of unread comments for the currently logged in user
	 * on the given file or directory node
	 *
	 * @param Node $node
	 * @return Int|null
	 */
	public function getUnreadCount(Node $node) {
		$numberOfCommentsForNode = 0;

		// Check if it is cached
		if (isset($this->numberOfCommentsForNodes[$node->getId()])) {
			$numberOfCommentsForNode = $this->numberOfCommentsForNodes[$node->getId()];
		} else if(!is_null($this->user)) {
			// Fetch all unread comments for this specific NodeID
			$numberOfCommentsForNodes = $this->commentsManager->getNumberOfUnreadCommentsForNodes(
				'files',
				[$node->getId()],
				$this->user);

			if (isset($numberOfCommentsForNodes[$node->getId()])) {
				$numberOfCommentsForNode = $numberOfCommentsForNodes[$node->getId()];
			}
		}
		return $numberOfCommentsForNode;
	}
}
