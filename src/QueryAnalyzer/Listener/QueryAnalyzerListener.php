<?php
namespace QueryAnalyzer\Listener;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\ViewModel;

class QueryAnalyzerListener implements ListenerAggregateInterface
{
    protected $queryAnalyzerConfig;

    protected $profilers = array();

    protected $loggers = array();

    protected $routingTrace;
    /**
     * Attach one or more listeners
     *
     * Implementors may add an optional $priority argument; the EventManager
     * implementation will pass this to the aggregate.
     *
     * @param EventManagerInterface $events
     *
     * @return void
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_FINISH,
            array($this, 'queryAnalyzer'),
            500
        );

        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            array($this, 'setRoutingBacktrace'),
            0
        );
    }

    /**
     * Detach all previously attached listeners
     *
     * @param EventManagerInterface $events
     *
     * @return void
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    public function setRoutingBacktrace($e)
    {
        $application = $e->getApplication();
        $serviceManager = $application->getServiceManager();

        $routeMatch = $application->getMvcEvent()->getRouteMatch();
        $controllerKey = $routeMatch->getParam('controller', 'index');
        $controllerClass = $serviceManager->get('config')['controllers']['invokables'][$controllerKey];

        $this->routingTrace = $routeMatch->getMatchedRouteName().' - '.$controllerClass.'->'.$routeMatch->getParam('action', 'index').'Action()';
    }

    /**
     * @param MvcEvent $e
     */
    public function queryAnalyzer(MvcEvent $e)
    {
        $application    = $e->getApplication();
        $serviceManager = $application->getServiceManager();
        $config         = $serviceManager->get('config');

        $this->setConfig($config['queryanalyzer']);

        if($this->queryAnalyzerConfig['log'] == false && $this->queryAnalyzerConfig['displayQueryAnalyzer'] == false)
            return;

        foreach($this->queryAnalyzerConfig['dbadapter'] as $dbadapter){
            if($serviceManager->has($dbadapter) == false)
                continue;

            $profiler = $serviceManager->get($dbadapter)->getProfiler();

            if(isset($profiler) && $profiler instanceof \QueryAnalyzer\Db\Adapter\Profiler\QueryAnalyzerProfiler)
                $this->addProfiler($dbadapter, $profiler);
        }

        if($this->hasProfilers() == false)
            return;

        if($this->queryAnalyzerConfig['log']){
            foreach($this->queryAnalyzerConfig['loggers'] as $logger){
                if($serviceManager->has($logger))
                    $this->addLogger($serviceManager->get($logger));
            }

            $this->logQueries();
        }

        if($this->queryAnalyzerConfig['displayQueryAnalyzer']){
            $request        = $application->getRequest();
            $response       = $application->getResponse();
            $viewRenderer   = $serviceManager->get('ViewRenderer');

            $this->injectViewModel($viewRenderer, $request, $response);
        }
    }

    protected function logQueries()
    {
        foreach($this->loggers as $logger){
            $logger->info('Route: ' . $this->routingTrace);
            $logger->info('Queries: ' . $this->getTotalQueryCount() . ' Total Execution time: ' . $this->getTotalQueryExecutionTime() . 'ms');

            foreach($this->profilers as $profiler){
                foreach($profiler->getProfiles($this->queryAnalyzerConfig['orderByExecutionTime']) as $i => $data){
                    $logger->info($i + 1 . ' Execution time: '. round($data['elapse'] * 1000, 3) . 'ms');
                    $logger->info($data['sql']);

                    if(isset($data['parameters']) && count($data['parameters']->getNamedArray()) > 0){
                        foreach($data['parameters']->getNamedArray() as $key => $value){
                            $logger->info($key . ' => ' . $value);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $viewrenderer
     * @param $request
     * @param $response
     */
    protected function injectViewModel($viewrenderer, $request, $response)
    {
        if ($request->isXmlHttpRequest()) {
            return;
        }

        $queryAnalyzer = new ViewModel();
        $queryAnalyzer->setVariables(array(
            'profilers'                 => $this->profilers,
            'routingTrace'              => $this->routingTrace,
            'totalExecutionTime'        => $this->getTotalQueryExecutionTime(),
            'totalQueryCount'           => $this->getTotalQueryCount(),
            'style'                     => $this->queryAnalyzerConfig['appearance'],
            'orderBy'                   => $this->queryAnalyzerConfig['orderByExecutionTime'],
        ));
        $queryAnalyzer->setTemplate('QueryAnalyzer');

        $queryAnalyzerHtml = $viewrenderer->render($queryAnalyzer);
        $document = $response->getBody();

        if(strlen($this->queryAnalyzerConfig['appearance']['prependTo']) > 0){
            $pos = strpos($document, $this->queryAnalyzerConfig['appearance']['prependTo']);

            if($pos !== false){
                $document = substr_replace($document, $queryAnalyzerHtml, $pos, 0);
            }
        }else{
            $document .= $queryAnalyzerHtml;
        }

        $response->setContent($document);
    }

    /**
     * @param $logger
     */
    public function addLogger($logger){
        $this->loggers[] = $logger;
    }

    /**
     * @return array
     */
    public function getLoggers(){
        return $this->loggers;
    }

    /**
     * @return bool
     */
    public function hasLoggers(){
        return (count($this->loggers) > 0) ? true : false;
    }

    /**
     * @param $name
     * @param $profiler
     */
    public function addProfiler($name, $profiler){
        $this->profilers[$name] = $profiler;
    }

    /**
     * @return array
     */
    public function getProfilers(){
        return $this->profilers;
    }

    /**
     * @return bool
     */
    public function hasProfilers(){
        return (count($this->profilers) > 0) ? true : false;
    }

    /**
     * @param $config
     */
    public function setConfig($config){
        $this->queryAnalyzerConfig = $config;
    }

    /**
     * @return array
     */
    public function getConfig(){
        return $this->queryAnalyzerConfig;
    }

    /**
     * @return int
     */
    public function getTotalQueryExecutionTime(){
        $executionTime = 0;
        foreach($this->profilers as $profiler){
            $executionTime += $profiler->getTotalExecutionTime();
        }

        return $executionTime;
    }

    /**
     * @return int
     */
    public function getTotalQueryCount(){
        $queries = 0;
        foreach($this->profilers as $profiler){
            $queries += count($profiler->getProfiles());
        }

        return $queries;
    }
}