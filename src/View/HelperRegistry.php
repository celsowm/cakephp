<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         2.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\View;

use Cake\Core\App;
use Cake\Core\ObjectRegistry;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\View\Exception\MissingHelperException;

/**
 * HelperRegistry is used as a registry for loaded helpers and handles loading
 * and constructing helper class objects.
 *
 * @extends \Cake\Core\ObjectRegistry<\Cake\View\Helper>
 * @implements \Cake\Event\EventDispatcherInterface<\Cake\View\View>
 */
class HelperRegistry extends ObjectRegistry implements EventDispatcherInterface
{
    /**
     * @use \Cake\Event\EventDispatcherTrait<\Cake\View\View>
     */
    use EventDispatcherTrait;

    /**
     * View object to use when making helpers.
     *
     * @var \Cake\View\View
     */
    protected View $_View;

    /**
     * Constructor
     *
     * @param \Cake\View\View $view View object.
     */
    public function __construct(View $view)
    {
        $this->_View = $view;
        $this->setEventManager($view->getEventManager());
    }

    /**
     * Tries to lazy load a helper based on its name, if it cannot be found
     * in the application folder, then it tries looking under the current plugin
     * if any
     *
     * @param string $helper The helper name to be loaded
     * @return bool whether the helper could be loaded or not
     * @throws \Cake\View\Exception\MissingHelperException When a helper could not be found.
     *    App helpers are searched, and then plugin helpers.
     */
    public function __isset(string $helper): bool
    {
        if (isset($this->_loaded[$helper])) {
            return true;
        }

        try {
            $this->load($helper);
        } catch (MissingHelperException $exception) {
            $plugin = $this->_View->getPlugin();
            if ($plugin) {
                $this->load($helper, ['className' => $plugin . '.' . $helper]);

                return true;
            }
        }

        if (!empty($exception)) {
            throw $exception;
        }

        return true;
    }

    /**
     * Provide public read access to the loaded objects
     *
     * @param string $name Name of property to read
     * @return \Cake\View\Helper|null
     */
    public function __get(string $name): ?Helper
    {
        // This calls __isset() and loading the named helper if it isn't already loaded.
        /** @psalm-suppress NoValue */
        if (isset($this->{$name})) {
            return $this->_loaded[$name];
        }

        return null;
    }

    /**
     * Resolve a helper classname.
     *
     * Part of the template method for Cake\Core\ObjectRegistry::load()
     *
     * @param string $class Partial classname to resolve.
     * @return class-string<\Cake\View\Helper>|null Either the correct class name or null.
     */
    protected function _resolveClassName(string $class): ?string
    {
        /** @var class-string<\Cake\View\Helper>|null */
        return App::className($class, 'View/Helper', 'Helper');
    }

    /**
     * Throws an exception when a helper is missing.
     *
     * Part of the template method for Cake\Core\ObjectRegistry::load()
     * and Cake\Core\ObjectRegistry::unload()
     *
     * @param string $class The classname that is missing.
     * @param string|null $plugin The plugin the helper is missing in.
     * @return void
     * @throws \Cake\View\Exception\MissingHelperException
     */
    protected function _throwMissingClassError(string $class, ?string $plugin): void
    {
        throw new MissingHelperException([
            'class' => $class . 'Helper',
            'plugin' => $plugin,
        ]);
    }

    /**
     * Create the helper instance.
     *
     * Part of the template method for Cake\Core\ObjectRegistry::load()
     * Enabled helpers will be registered with the event manager.
     *
     * @param \Cake\View\Helper|class-string<\Cake\View\Helper> $class The class to create.
     * @param string $alias The alias of the loaded helper.
     * @param array<string, mixed> $config An array of settings to use for the helper.
     * @return \Cake\View\Helper The constructed helper class.
     */
    protected function _create(object|string $class, string $alias, array $config): Helper
    {
        if (is_object($class)) {
            return $class;
        }

        $instance = new $class($this->_View, $config);

        if ($config['enabled'] ?? true) {
            $this->getEventManager()->on($instance);
        }

        return $instance;
    }
}
