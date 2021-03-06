<?php

namespace Larke\Admin;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

use Larke\Admin\Model\AuthRule as AuthRuleModel;
use Larke\Admin\Model\Extension as ExtensionModel;
use Larke\Admin\Extension\Service as ExtensionService;

/*
 * 扩展
 *
 * @create 2020-10-30
 * @author deatil
 */
class Extension
{
    /**
     * @var array
     */
    public $extensions = [];

    /**
     * Extend a extension.
     *
     * @param string $name
     * @param string $class
     *
     * @return void
     */
    public function extend($name, $class)
    {
        $this->extensions[$name] = $class;
    }
    
    /**
     * Get a extension.
     *
     * @param string|array $name
     *
     * @return string|array
     */
    public function getExtend($names = null)
    {
        if (is_array($names)) {
            $extensions = [];
            foreach ($names as $name) {
                $extensions[$name] = $this->getExtend($name);
            }
            
            return $extensions;
        }
        
        if (isset($this->extensions[$names])) {
            $extension = $this->extensions[$names];
            return $extension;
        }
        
        return $this->extensions;
    }
    
    /**
     * Forget a extension or extensions.
     *
     * @param string|array $name
     *
     * @return string|array
     */
    public function forget($names)
    {
        if (is_array($names)) {
            $forgetExtensions = [];
            foreach ($names as $name) {
                $forgetExtensions[$name] = $this->forget($name);
            }
            
            return $forgetExtensions;
        }
        
        if (isset($this->extensions[$names])) {
            $extension = $this->extensions[$names];
            unset($this->extensions[$names]);
            return $extension;
        }
        
        return null;
    }
    
    /**
     * Set routes for this Route.
     *
     * @param $callback
     * @param $config
     * 
     * @return void
     */
    public function routes($callback, $config = [])
    {
        $attributes = array_merge(
            [
                'prefix' => config('larkeadmin.route.prefix'),
                'middleware' => config('larkeadmin.route.middleware'),
            ],
            $config
        );

        Route::group($attributes, $callback);
    }
    
    /**
     * Set namespaces.
     *
     * @param $prefix
     * @param $paths
     * 
     * @return void
     */
    public function namespaces($prefix, $paths = [])
    {
        app('larke.admin.loader')->setPsr4($prefix, $paths)->register();
    }
    
    /**
     * Register extensions'namespace.
     * 
     * @return void
     */
    public function registerExtensionNamespace()
    {
        $dir = $this->getExtensionDirectory();
        
        // 注入扩展命名空间
        app('larke.admin.loader')->set('', $dir)->register();
    }
    
    /**
     * Boot Extension.
     *
     * @return void
     */
    public function bootExtension()
    {
        if (! Schema::hasTable((new ExtensionModel)->getTable())) {
            return ;
        }
        
        $list = ExtensionModel::getExtensions();
        
        $services = collect($list)->map(function($data) {
            if ($data['status'] != 1) {
                return null;
            }

            if (empty($data['class_name'])) {
                return null;
            }
            
            $newClass = $this->getNewClass($data['class_name']);
            if (!$newClass) {
                return null;
            }
            
            return $newClass;
        })->filter(function($data) {
            return !empty($data);
        })->toArray();
        
        array_walk($services, function ($s) {
            $this->bootService($s);
        });
    }
    
    /**
     * Boot the given service.
     *
     * @return void
     */
    protected function bootService(ExtensionService $service)
    {
        $service->callBootingCallbacks();

        if (method_exists($service, 'boot')) {
            app()->call([$service, 'boot']);
        }

        $service->callBootedCallbacks();
    }
    
    /**
     * Load extensions.
     *
     * @return $this
     */
    public function loadExtension()
    {
        $dir = $this->getExtensionDirectory();
        
        $dirs = File::directories($dir);
        collect($dirs)->each(function($dir) {
            $bootstrap = $dir . DIRECTORY_SEPARATOR . 'bootstrap.php';
            if (File::exists($bootstrap)) {
                File::requireOnce($bootstrap);
            }
        });
        
        return $this;
    }
    
    /**
     * Get extensions directory.
     *
     * @param string
     *
     * @return string
     */
    public function getExtensionDirectory($path = '')
    {
        $extensionDirectory =  config('larkeadmin.extension.directory');
        return $extensionDirectory.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
    
    /**
     * Get new class.
     *
     * @param string
     */
    public function getNewClass($className = null)
    {
        if (!class_exists($className)) {
            return false;
        }
        
        $newClass = app($className);
        if (!($newClass instanceof ExtensionService)) {
            return false;
        }
        
        return $newClass;
    }
    
    /**
     * Get new class.
     *
     * @param $className string
     * @param $method string
     * @param $param array
     *
     * @return mixed
     */
    public function getNewClassMethod($className = null, $method = null, $param = [])
    {
        if (empty($className) || empty($method)) {
            return false;
        }
        
        $newClass = $this->getNewClass($className);
        if (!$newClass) {
            return false;
        }
        
        if (!method_exists($newClass, $method)) {
            return false;
        }
        
        $res = call_user_func_array([$newClass, $method], $param);
        return $res;
    }
    
    /**
     * Get extension new class.
     *
     * @param string
     *
     * @return mixed|object
     */
    public function getExtensionNewClass($name = null)
    {
        if (empty($name)) {
            return false;
        }
        
        $className = Arr::get($this->extensions, $name);
        
        return $this->getNewClass($className);
    }
    
    /**
     * Get extension info.
     *
     * @param $name string
     *
     * @return array
     */
    public function getExtension($name = null)
    {
        $newClass = $this->getExtensionNewClass($name);
        if ($newClass === false) {
            return [];
        }
        
        if (!isset($newClass->info)) {
            return [];
        }
        
        $info = $newClass->info;
        
        return [
            'name' => Arr::get($info, 'name'),
            'title' => Arr::get($info, 'title'),
            'introduce' => Arr::get($info, 'introduce'),
            'author' => Arr::get($info, 'author'), 
            'authorsite' => Arr::get($info, 'authorsite'),
            'authoremail' => Arr::get($info, 'authoremail'),
            'version' => Arr::get($info, 'version'),
            'adaptation' => Arr::get($info, 'adaptation'),
            'require_extension' => Arr::get($info, 'require_extension', []),
            'config' => Arr::get($info, 'config', []),
            'class_name' => Arr::get($this->extensions, $name),
        ];
    }
    
    /**
     * Get extension config.
     *
     * @param $name string
     *
     * @return array
     */
    public function getExtensionConfig($name = null)
    {
        $info = $this->getExtension($name);
        if (empty($info)) {
            return [];
        }
        
        if (empty($info['config'])) {
            return [];
        }
        
        return $info['config'];
    }
    
    /**
     * Get extensions.
     *
     * @return array
     */
    public function getExtensions()
    {
        $extensions = $this->extensions;
        
        $thiz = $this;
        
        $list = collect($extensions)->map(function($className, $name) use($thiz) {
            $info = $thiz->getExtension($name);
            if (!empty($info)) {
                return $info;
            }
        })->filter(function($data) {
            return !empty($data);
        })->toArray();
        
        return $list;
    }
    
    /**
     * validateInfo.
     *
     * @param array
     *
     * @return boolen
     */
    public function validateInfo(array $info)
    {
        $mustInfo = [
            'name',
            'title',
            'introduce',
            'author',
            'version',
            'adaptation',
        ];
        if (empty($info)) {
            return false;
        }
        
        return !collect($mustInfo)
            ->contains(function ($key) use ($info) {
                return (!isset($info[$key]) || empty($info[$key]));
            });
    }
    
    /**
     * Create rule.
     *
     * @return $data array
     * @return $parentId int
     * @return $children array
     *
     * @return array
     */
    public function createRule(
        $data = [], 
        $parentId = 0, 
        array $children = []
    ) {
        if (empty($data)) {
            return false;
        }
        
        $lastOrder = AuthRuleModel::max('listorder');
        
        $rule = AuthRuleModel::create([
            'parentid' => $parentId,
            'listorder' => $lastOrder + 1,
            'title' => Arr::get($data, 'title'),
            'url' => Arr::get($data, 'url'),
            'method' => Arr::get($data, 'method'),
            'slug' => Arr::get($data, 'slug'),
            'description' => Arr::get($data, 'description'),
        ]);
        if (!empty($children)) {
            foreach ($children as $child) {
                $subChildren = Arr::get($child, 'children', []);
                $this->createRule($child, $rule->id, $subChildren);
            }
        }

        return $rule;
    }
}
