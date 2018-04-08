<?php
/**
 * @copyright Copyright (c)
 *
 * @author
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\user_typo3\Settings;

use OCP\IL10N;
use OCP\Settings\IIconSection;
use OCP\IURLGenerator;

class Section implements IIconSection {
	/** @var IL10N */
	private $l;

	/**
	 * @param IL10N $l
	 */
	public function __construct(IURLGenerator $url,IL10N $l) {
		$this->l = $l;
		$this->url = $url;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getID() {
		return 'usertypo3';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return $this->l->t('User TYPO3');
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPriority() {
		return 74;
	}
	/**
	 * {@inheritdoc}
	 */
	public function getIcon() {
		return $this->url->imagePath('user_typo3', 'app-dark.svg');
	}
}
