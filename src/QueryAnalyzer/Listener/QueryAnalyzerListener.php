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

    public function __construct($renderer, $profiler)
    {
        $this->renderer = $renderer;
        $this->profiler = $profiler;
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
            array($this, 'attachQueryAnalyzer'),
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

    public function attachQueryAnalyzer(MvcEvent $e)
    {
        $application = $e->getApplication();

        if($this->isHtmlInjectable($application)){
          $this->injectIntoHtml($this->setUpQueryAnlayzerModel(), $application->getResponse());
        }
    }

    public function setRoutingBacktraceOnRoute(MvcEvent $e){
        $application = $e->getApplication();
        $serviceManager = $application->getServiceManager();

        $routeMatch = $application->getMvcEvent()->getRouteMatch();
        $controllerKey = $routeMatch->getParam('controller', 'index');
        $controllerClass = $serviceManager->get('config')['controllers']['invokables'][$controllerKey];

        $this->profiler->setRoutingTrace($routeMatch->getMatchedRouteName().' - '.$controllerClass.'->'.$routeMatch->getParam('action', 'index').'Action()');
    }

    private function injectIntoHtml($queryAnalyzer, $response){
        $queryAnalyzerHtml = $this->renderer->render($queryAnalyzer);
        $injected    = preg_replace('/<\/body>/', $queryAnalyzerHtml. "</body>" , $response->getBody(), 1);
        $response->setContent($injected);
    }

    private function isHtmlInjectable($application){
        $request = $application->getRequest();
        return !$request->isXmlHttpRequest();
    }

    private function setUpQueryAnalyzerModel(){
      $queryAnalyzer = new ViewModel();
      $queryAnalyzer->setVariables(array(
          'queryData' => $this->profiler->getProfiles(),
          'routingTrace'  => $this->profiler->getRoutingTrace(),
          'totalExecutionTime' => $this->profiler->getTotalExecutionTime()
      ));
      $queryAnalyzer->setTemplate('QueryAnalyzer');
    }
}
