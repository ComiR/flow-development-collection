<?php
declare(ENCODING = 'utf-8');
namespace F3\FLOW3\MVC\Web\Routing;

/*                                                                        *
 * This script belongs to the FLOW3 framework.                            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License as published by the *
 * Free Software Foundation, either version 3 of the License, or (at your *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * @package FLOW3
 * @subpackage MVC
 * @version $Id$
 */

/**
 * Implementation of a standard route
 *
 * @package FLOW3
 * @subpackage MVC
 * @version $Id$
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @scope prototype
 */
class Route {

	const ROUTEPART_TYPE_STATIC = 'static';
	const ROUTEPART_TYPE_DYNAMIC = 'dynamic';
	const PATTERN_EXTRACTROUTEPARTS = '/(?P<optionalStart>\(?)(?P<dynamic>{?)(?P<content>@?[^}{\(\)]+)}?(?P<optionalEnd>\)?)/';

	/**
	 * Route name
	 *
	 * @var string
	 */
	protected $name = NULL;

	/**
	 * Default values
	 *
	 * @var array
	 */
	protected $defaults = array();

	/**
	 * URI Pattern of this route
	 * @var string
	 */
	protected $uriPattern = NULL;

	/**
	 * @var string
	 */
	protected $controllerObjectNamePattern = NULL;

	/**
	 * @var string
	 */
	protected $viewObjectNamePattern = NULL;

	/**
	 * Contains the routing results (indexed by "package", "controller" and "action") after a successful call of matches()
	 *
	 * @var array
	 */
	protected $matchResults = array();

	/**
	 * Contains the matching uri (excluding protocol and host) after a successful call of resolves()
	 *
	 * @var string
	 */
	protected $matchingURI;

	/**
	 * Contains associative array of custom Route Part handler classnames (key: Route Part name, value: Route Part handler classname)
	 *
	 * @var array
	 */
	protected $routePartHandlers = array();

	/**
	 * Indicates whether this route is parsed.
	 * For better performance, routes are only parsed if needed.
	 *
	 * @var boolean
	 */
	protected $isParsed = FALSE;

	/**
	 * Container for Route Parts.
	 *
	 * @var array
	 */
	protected $routeParts;

	/**
	 * @var \F3\FLOW3\Object\FactoryInterface
	 */
	protected $objectFactory;

	/**
	 * @var \F3\FLOW3\Object\ManagerInterface
	 */
	protected $objectManager;

	/**
	 * Constructor
	 *
	 * @param \F3\FLOW3\Object\FactoryInterface $objectFactory
	 * @param \F3\FLOW3\Object\ManagerInterface $objectManager
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function __construct(\F3\FLOW3\Object\FactoryInterface $objectFactory, \F3\FLOW3\Object\ManagerInterface $objectManager) {
		$this->objectFactory = $objectFactory;
		$this->objectManager = $objectManager;
	}

	/**
	 * Sets Route name.
	 *
	 * @param string $name The Route name
	 * @return void
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * Returns the name of this Route.
	 *
	 * @return string Route name.
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Sets default values for this Route.
	 * This array is merged with the actual matchResults when match() is called.
	 *
	 * @param array $defaults
	 * @return void
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function setDefaults(array $defaults) {
		$this->defaults = $defaults;
	}

	/**
	 * Sets the URI pattern this route should match with
	 *
	 * @param string $uriPattern
	 * @return void
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function setUriPattern($uriPattern) {
		if (!is_string($uriPattern)) throw new \InvalidArgumentException('URI Pattern must be of type string, ' . gettype($uriPattern) . ' given.', 1223499724);
		$this->uriPattern = $uriPattern;
		$this->isParsed = FALSE;
	}

	/**
	 * Set a custom controller object name pattern which will be
	 * passed to the web request.
	 *
	 * @param string $pattern A pattern which may contain placeholders
	 * @return void
	 * @see \F3\FLOW3\MVC\Web\Request
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function setControllerObjectNamePattern($pattern) {
		$this->controllerObjectNamePattern = $pattern;
	}

	/**
	 * Returns the custom controller object name pattern.
	 *
	 * @return string The pattern or NULL if none was defined
	 * @see \F3\FLOW3\MVC\Web\Request
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getControllerObjectNamePattern() {
		return $this->controllerObjectNamePattern;
	}

	/**
	 * Sets a custom view object name pattern which will be
	 * passed to the web request.
	 *
	 * @param string $pattern A pattern which may contain placeholders
	 * @return void
	 * @see \F3\FLOW3\MVC\Web\Request
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function setViewObjectNamePattern($pattern) {
		$this->viewObjectNamePattern = $pattern;
	}

	/**
	 * Returns the custom view object name pattern.
	 *
	 * @return string The pattern or NULL if none was defined
	 * @see \F3\FLOW3\MVC\Web\Request
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getViewObjectNamePattern() {
		return $this->viewObjectNamePattern;
	}

	/**
	 * By default all Dynamic Route Parts are resolved by \F3\FLOW3\MVC\Web\Routing\DynamicRoutePart.
	 * But you can specify different classes to handle particular Route Parts.
	 * Note: Route Part handlers must implement \F3\FLOW3\MVC\Web\Routing\DynamicRoutePartInterface.
	 *
	 * Usage: setRoutePartHandlers(array('@controller' => 'F3\Package\Subpackage\MyRoutePartHandler'));
	 *
	 * @param array $routePartHandlers Route Part handler classnames
	 * @return void
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function setRoutePartHandlers(array $routePartHandlers) {
		$this->routePartHandlers = $routePartHandlers;
	}

	/**
	 * Returns an array with the Route match results.
	 *
	 * @return array An array of Route Parts and their values for further handling by the Router
	 * @see \F3\FLOW3\MVC\Web\Routing\Router
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function getMatchResults() {
		return $this->matchResults;
	}

	/**
	 * Returns the uri which corresponds to this Route.
	 *
	 * @return string A string containing the corresponding uri (excluding protocol and host)
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function getMatchingURI() {
		return $this->matchingURI;
	}

	/**
	 * Checks whether $requestPath corresponds to this Route.
	 * If all Route Parts match successfully TRUE is returned and $this->matchResults contains
	 * an array combining Route default values and calculated matchResults from the individual Route Parts.
	 *
	 * @param string $requestPath the request path without protocol, host and query string
	 * @return boolean TRUE if this Route corresponds to the given $requestPath, otherwise FALSE
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function matches($requestPath) {
		$this->matchResults = NULL;
		if ($requestPath === NULL) {
			return FALSE;
		}
		if ($this->uriPattern === NULL) {
			return FALSE;
		}
		if (!$this->isParsed) {
			$this->parse();
		}
		$matchResults = array();

		$requestPath = trim($requestPath, '/');
		$skipOptionalParts = FALSE;
		$optionalPartCount = 0;
		foreach ($this->routeParts as $routePart) {
			if ($routePart->isOptional()) {
				$optionalPartCount++;
				if ($skipOptionalParts) {
					if ($routePart->getDefaultValue() === NULL) {
						return FALSE;
					}
					continue;
				}
			} else {
				$optionalPartCount = 0;
				$skipOptionalParts = FALSE;
			}
			if (!$routePart->match($requestPath)) {
				if ($routePart->isOptional() && $optionalPartCount == 1) {
					if ($routePart->getDefaultValue() === NULL) {
						return FALSE;
					}
					$skipOptionalParts = TRUE;
				} else {
					return FALSE;
				}
			}
			if ($routePart->getValue() !== NULL) {
				$matchResults[$routePart->getName()] = $routePart->getValue();
			}
		}
		if (strlen($requestPath) > 0) {
			return FALSE;
		}
		$this->matchResults = \F3\FLOW3\Utility\Arrays::arrayMergeRecursiveOverrule($this->defaults, $matchResults);
		return TRUE;
	}

	/**
	 * Checks whether $routeValues can be resolved to a corresponding uri.
	 * If all Route Parts can resolve one or more of the $routeValues, TRUE is returned and $this->matchingURI contains
	 * the generated uri (excluding protocol and host).
	 *
	 * @param array $routeValues An array containing key/value pairs to be resolved to uri segments
	 * @return boolean TRUE if this Route corresponds to the given $routeValues, otherwise FALSE
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function resolves(array $routeValues) {
		$this->matchingURI = NULL;
		if ($this->uriPattern === NULL) {
			return FALSE;
		}
		if (!$this->isParsed) {
			$this->parse();
		}

		$matchingURI = '';
		$requireOptionalRouteParts = FALSE;
		$matchingOptionalUriPortion = '';
		$matchingUriPart = '';
		foreach ($this->routeParts as $routePart) {
			if (!$routePart->resolve($routeValues)) {
				if (!$routePart->hasDefaultValue()) {
					return FALSE;
				}
			}
			if (!$routePart->isOptional()) {
				$matchingURI.= $routePart->hasValue() ? $routePart->getValue() : $routePart->getDefaultValue();
				$requireOptionalRouteParts = FALSE;
				continue;
			}
			if ($routePart->hasValue() && $routePart->getValue() !== $routePart->getDefaultValue()) {
				$matchingOptionalUriPortion.= $routePart->getValue();
				$requireOptionalRouteParts = TRUE;
			} else {
				$matchingOptionalUriPortion.= $routePart->getDefaultValue();
			}
			if ($requireOptionalRouteParts) {
				$matchingURI.= $matchingOptionalUriPortion;
				$matchingOptionalUriPortion = '';
			}
		}

		foreach ($this->defaults as $key => $defaultValue) {
			if (isset($routeValues[$key])) {
				if ($routeValues[$key] != $defaultValue) {
					return FALSE;
				}
				unset($routeValues[$key]);
			}
		}

		if (count($routeValues) > 0) {
			return FALSE;
		}

		$this->matchingURI = $matchingURI;
		$this->matchingURI = \F3\PHP6\Functions::strtolower($matchingURI);
		return TRUE;
	}

	/**
	 * Iterates through all segments in $this->uriPattern and creates appropriate Route Part instances.
	 *
	 * @return void
	 * @author Bastian Waidelich <bastian@typo3.org>
	 */
	public function parse() {
		if ($this->isParsed) {
			return;
		}
		$this->routeParts = array();
		$currentRoutePartIsOptional = FALSE;
		if (substr($this->uriPattern, -1) === '/') {
			throw new \F3\FLOW3\MVC\Exception\InvalidUriPattern('the URI pattern "' . $this->uriPattern . '" ends with a slash, which is not allowed. You can put the trailing slash in brackets to make it optional.', 1234782997);
		}
		if (isset($this->uriPattern{0}) && $this->uriPattern{0} === '/') {
			throw new \F3\FLOW3\MVC\Exception\InvalidUriPattern('the URI pattern "' . $this->uriPattern . '" starts with a slash, which is not allowed.', 1234782983);
		}

		$matches = array();
		preg_match_all(self::PATTERN_EXTRACTROUTEPARTS, $this->uriPattern, $matches, PREG_SET_ORDER);

		$lastRoutePart = NULL;
		foreach ($matches as $matchIndex => $match) {
			$routePartType = empty($match['dynamic']) ? self::ROUTEPART_TYPE_STATIC : self::ROUTEPART_TYPE_DYNAMIC;
			$routePartName = $match['content'];
			if (!empty($match['optionalStart'])) {
				if ($lastRoutePart !== NULL && $lastRoutePart->isOptional()) {
					throw new \F3\FLOW3\MVC\Exception\InvalidUriPattern('the URI pattern "' . $this->uriPattern . '" contains succesive optional Route sections, which is not allowed.', 1234562050);
				}
				$currentRoutePartIsOptional = TRUE;
			}
			$routePart = NULL;
			switch ($routePartType) {
				case self::ROUTEPART_TYPE_DYNAMIC:
					if ($lastRoutePart instanceof \F3\FLOW3\MVC\Web\Routing\DynamicRoutePartInterface) {
						throw new \F3\FLOW3\MVC\Exception\InvalidUriPattern('the URI pattern "' . $this->uriPattern . '" contains succesive Dynamic Route Parts, which is not allowed.', 1218446975);
					}
					if (isset($this->routePartHandlers[$routePartName])) {
						$routePart = $this->objectManager->getObject($this->routePartHandlers[$routePartName]);
						if (!$routePart instanceof \F3\FLOW3\MVC\Web\Routing\DynamicRoutePartInterface) {
							throw new \F3\FLOW3\MVC\Exception\InvalidRoutePartHandler('routePart handlers must implement "\F3\FLOW3\MVC\Web\Routing\DynamicRoutePartInterface"', 1218480972);
						}
					} else {
						$routePart = $this->objectFactory->create('F3\FLOW3\MVC\Web\Routing\DynamicRoutePart');
					}
					if (isset($this->defaults[$routePartName])) {
						$routePart->setDefaultValue($this->defaults[$routePartName]);
					}
					break;
				case self::ROUTEPART_TYPE_STATIC:
					$routePart = $this->objectFactory->create('F3\FLOW3\MVC\Web\Routing\StaticRoutePart');
					if ($lastRoutePart !== NULL && $lastRoutePart instanceof \F3\FLOW3\MVC\Web\Routing\DynamicRoutePartInterface) {
						$lastRoutePart->setSplitString($routePartName);
					}
			}
			$routePart->setName($routePartName);
			$routePart->setOptional($currentRoutePartIsOptional);

			$this->routeParts[] = $routePart;
			if (!empty($match['optionalEnd'])) {
				if (!$currentRoutePartIsOptional) {
					throw new \F3\FLOW3\MVC\Exception\InvalidUriPattern('the URI pattern "' . $this->uriPattern . '" contains an unopened optional section.', 1234564495);
				}
				$currentRoutePartIsOptional = FALSE;
			}
			$lastRoutePart = $routePart;
		}
		if ($currentRoutePartIsOptional) {
			throw new \F3\FLOW3\MVC\Exception\InvalidUriPattern('the URI pattern "' . $this->uriPattern . '" contains an unterminated optional section.', 1234563922);
		}
		$this->isParsed = TRUE;
	}
}

?>
