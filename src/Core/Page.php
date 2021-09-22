<?php
/**
 * @file      Page.php
 * @brief     Core\Page class
 * @author    Tomáš Vojík <vojik@wboy.cz>
 * @date      2021-09-22
 * @version   1.0
 * @since     1.0
 *
 * @defgroup  Pages Pages
 * @brief     All page classes
 */

namespace App\Core;


/**
 * @class   Page
 * @brief   Abstract Page class that specifies all basic functionality for other Pages
 *
 * @package Core
 * @ingroup Pages
 *
 * @author  Tomáš Vojík <vojik@wboy.cz>
 * @version 1.0
 * @since   1.0
 */
abstract class Page
{

	public array $middleware = [];
	/**
	 * @var string $title Page name
	 */
	protected string $title;
	/**
	 * @var string $description Page description
	 */
	protected string $description;
	/**
	 * @var array $params Parameters added to latte template
	 */
	protected array   $params = [];
	protected Request $request;

	/**
	 * Initialization function
	 *
	 * @param Request $request
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public function init(Request $request) : void {
		$this->request = $request;
		$this->params['page'] = $this;
		$this->params['request'] = $request;
		$this->params['errors'] = [];
	}

	/**
	 * Getter method for page title
	 *
	 * @return string
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public function getTitle() : string {
		return Constants::SITE_NAME.(!empty($this->title) ? ' - '.$this->title : '');
	}

	/**
	 * Getter method for page description
	 *
	 * @return string
	 *
	 * @version 1.0
	 * @since   1.0
	 */
	public function getDescription() : string {
		return $this->description;
	}
}
