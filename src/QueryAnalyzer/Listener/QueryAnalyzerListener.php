<?php
namespace QueryAnalyzer\Listener;

use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\ViewModel;

class QueryAnalyzerListener implements ListenerAggregateInterface
{
    protected $renderer;

    protected $profiler;

    protected $queryAnalyzerConfig;

    protected $loggers = array();

    public function __construct($renderer, $profiler, $queryAnalyzerConfig = array())
    {
        $this->renderer = $renderer;
        $this->profiler = $profiler;
        $this->queryAnalyzerConfig = $queryAnalyzerConfig;
    }
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
            'finish',
            array($this, 'queryAnalyzer'),
            500
        );

        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, array($this, 'setRoutingBacktraceOnRoute'), 0);
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

    public function queryAnalyzer(MvcEvent $e)
    {
        $application = $e->getApplication();
        $request     = $application->getRequest();
        $response = $application->getResponse();

        if($this->queryAnalyzerConfig['log']){
            $this->logQueries();
        }

        if($this->queryAnalyzerConfig['displayQueryAnalyzer']){
            $this->injectViewModel($request, $response);
        }
    }

    protected function logQueries()
    {
        foreach($this->loggers as $logger){
            $logger->info('Route: ' . $this->profiler->getRoutingTrace());
            $logger->info('Queries: ' . count($this->profiler->getProfiles()) . ' Total Execution time: ' . $this->profiler->getTotalExecutionTime() . 'ms');

            foreach($this->profiler->getProfiles() as $i => $profile){
                $logger->info($i + 1 . ' Execution time: '. round($profile['elapse'] * 1000, 3) . 'ms');
                $logger->info($profile['sql']);

                if(isset($profile['parameters']) && count($profile['parameters']->getNamedArray()) > 0){
                    foreach($profile['parameters']->getNamedArray() as $key => $value){
                        $logger->info($key . ' => ' . $value);
                    }
                }
            }
        }
    }

    protected function injectViewModel($request, $response)
    {
        if ($request->isXmlHttpRequest()) {
            return;
        }

        $queryAnalyzer = new ViewModel();
        $queryAnalyzer->setVariables(array(
            'queryData'                 => $this->profiler->getProfiles(),
            'routingTrace'              => $this->profiler->getRoutingTrace(),
            'totalExecutionTime'        => $this->profiler->getTotalExecutionTime(),
            'style'                     => $this->queryAnalyzerConfig['appearance']
        ));
        $queryAnalyzer->setTemplate('QueryAnalyzer');

        $queryAnalyzerHtml = $this->renderer->render($queryAnalyzer);
        $injected    = preg_replace('/<\/body>/', $queryAnalyzerHtml. "</body>" , $response->getBody(), 1);
        $response->setContent($injected);
    }

    public function setRoutingBacktraceOnRoute(MvcEvent $e)
    {
        $application = $e->getApplication();
        $serviceManager = $application->getServiceManager();

        $routeMatch = $application->getMvcEvent()->getRouteMatch();
        $controllerKey = $routeMatch->getParam('controller', 'index');
        $controllerClass = $serviceManager->get('config')['controllers']['invokables'][$controllerKey];

        $this->profiler->setRoutingTrace($routeMatch->getMatchedRouteName().' - '.$controllerClass.'->'.$routeMatch->getParam('action', 'index').'Action()');
    }

    public function addLogger($logger)
    {
        $this->loggers[] = $logger;
    }
}