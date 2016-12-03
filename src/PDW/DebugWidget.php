<?php

namespace PDW;

use Phalcon\Di\FactoryDefault,
    Phalcon\DI\InjectionAwareInterface,
    Phalcon\DiInterface,
    Phalcon\Db\Profiler                 as Profiler,
    Phalcon\Mvc\View                    as View
;


/**
 * Class DebugWidget
 * @package PDW
 */
class DebugWidget implements InjectionAwareInterface
{

    /**
     * @var DiInterface
     */
    protected $_di;

    /**
     * @var float
     */
    private $startTime;

    /**
     * @var float
     */
    private $endTime;

    /**
     * @var int
     */
    private $queryCount = 0;

    /**
     * @var Profiler
     */
    protected $_profiler;

    /**
     * @var array
     */
    protected $_viewsRendered = [];

    /**
     * @var array
     */
    protected $_serviceNames  = [];


    /**
     * DebugWidget constructor.
     * @param $di
     * @param array $serviceNames
     */
    public function __construct(
        $di,
        $serviceNames =
            [
                'db'       => ['dbRead', 'dbWrite'],
                'dispatch' => ['dispatcher'],
                'view'     => ['view']
            ]
    ) {
        $this->_di       = $di;
        $this->startTime = microtime(true);
        $this->_profiler = new Profiler();
        $eventsManager   = $di->get('eventsManager');

        foreach ($di->getServices() as $service) {
            $name = $service->getName();
            foreach ($serviceNames as $eventName => $services) {
                if (in_array($name, $services)) {
                    $service->setShared(true);
                    $di->get($name)->setEventsManager($eventsManager);
                                        break;
                }
            }
        }

        foreach (array_keys($serviceNames) as $eventName) {
            $eventsManager->attach($eventName, $this);
        }

        $this->_serviceNames = $serviceNames;
    }

    /**
     * @param DiInterface $di
     */
    public function setDI(DiInterface $di)
    {
        $this->_di = $di;
    }

    /**
     * @return DiInterface
     */
    public function getDI()
    {
        return $this->_di;
    }

    /**
     * @param $event
     * @return mixed
     */
    public function getServices($event)
    {
        return $this->_serviceNames[$event];
    }

    /**
     * @param $event
     * @param $connection
     */
    public function beforeQuery($event, $connection)
    {
        $this->_profiler->startProfile(
            $connection->getRealSQLStatement(),
            $connection->getSQLVariables(),
            $connection->getSQLBindTypes()
        );
    }

    /**
     * @param \Phalcon\Events\Event $event
     * @param \Phalcon\Db\Adapter\Pdo\Mysql $connection
     */
    public function afterQuery( $event, $connection)
    {
        $this->_profiler->stopProfile();
        $this->queryCount++;
    }

    /**
     * Gets/Saves information about views and stores truncated viewParams.
     *
     * @param \Phalcon\Events\Event $event
     * @param \Phalcon\Mvc\View $view
     * @param unknown $file
     */
    public function beforeRenderView($event, $view, $file)
    {
        $params = [];
        $toView = $view->getParamsToView();
        $toView = !$toView ? [] : $toView;

        foreach ($toView as $k=>$v) {
            if (is_object($v)) {
                $params[$k] = get_class($v);
            } elseif(is_array($v)) {
                $array =[];
                foreach ($v as $key=>$value) {
                    if (is_object($value)) {
                        $array[$key] = get_class($value);
                    } elseif (is_array($value)) {
                        $array[$key] = 'Array[...]';
                    } else {
                        $array[$key] = $value;
                    }
                }
                $params[$k] = $array;
            } else {
                $params[$k] = (string)$v;
            }
        }

        $this->_viewsRendered[] = [
            'path'       => $view->getActiveRenderPath(),
            'params'     => $params,
            'controller' => $view->getControllerName(),
            'action'     => $view->getActionName(),
        ];
    }

    /**
     * @param $event
     * @param $view
     * @param $viewFile
     */
    public function afterRender($event, \Phalcon\Mvc\View $view, $viewFile)
    {
        $this->endTime  = microtime(true);
        $content        = $view->getContent();
        $styles         = $this->getInsertStyles();
        $styles        .= '</head>';
        $content        = str_replace("</head>", $styles, $content);
        $rendered       = $this->renderToolbar() . $this->getInsertScripts();
        $rendered      .= '</body>';
        $content        = str_replace('</body>', $rendered, $content);

        $view->setContent($content);
    }

    /**
     * Returns scripts to be inserted before <head>
     * Since setBaseUri may or may not end in a /, double slashes are removed.
     *
     * @return string
     */
    public function getInsertStyles()
    {
        $style = "";
        $css   = [
            '//cdnjs.cloudflare.com/ajax/libs/prism/1.5.0/themes/prism.min.css'
        ];

        foreach ($css as $src) {
            $style .= '<link rel="stylesheet" type="text/css" href="' . $src . '" />' . PHP_EOL;
        }

        return $style;
    }

    /**
     * Returns scripts to be inserted before <head>
     * Since setBaseUri may or may not end in a /, double slashes are removed.
     *
     * @return string
     */
    public function getInsertScripts()
    {
        $scripts = '';
        $js      = [
            '//cdnjs.cloudflare.com/ajax/libs/prism/1.5.0/prism.min.js'
        ];

        foreach ($js as $src) {
            $scripts .= '<script style="text/javascript" src="' . $src . '"></script>' . PHP_EOL;
        }

        return $scripts;
    }

    /**
     * @return string
     */
    public function renderToolbar()
    {
        $localDi = new FactoryDefault();
        $view    = new View();

        $view->setDI($localDi);

        $viewDir = dirname(__FILE__) .'/views/';
        $view->setViewsDir($viewDir);
        $view->registerEngines([
            '.php' => 'Phalcon\Mvc\View\Engine\Php' // set PHP file ext for views
        ]);

        // set vars
        $view->debugWidget = $this;

        $content = $view->getRender('toolbar', 'index');
        return $content;
    }

    /**
     * @param $rawSize
     * @return string
     */
    public function getHumanReadableSize($rawSize)
    {
        $hSize  = $rawSize;
        $metric = 'b';

        switch (true) {
            case ($hSize> 1024):
                $hSize = round($hSize / 1024, 2);
                $metric = 'kB';
                break;

            case ($hSize > 1024):
                $hSize = round($hSize / 1024, 2);
                $metric = 'mB';
                break;

        }

        return $hSize . ' ' . $metric;
    }

    /**
     * @return float|mixed
     */
    public function getStartTime()
    {
        return $this->startTime;
    }

    /**
     * @return float
     */
    public function getEndTime()
    {
        return $this->endTime;
    }

    /**
     * @return array
     */
    public function getRenderedViews()
    {
        return $this->_viewsRendered;
    }

    /**
     * @return int
     */
    public function getQueryCount()
    {
        return $this->queryCount;
    }

    /**
     * @return Profiler
     */
    public function getProfiler()
    {
        return $this->_profiler;
    }

}
