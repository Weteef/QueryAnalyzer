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
        'profiles' => new Profiles($this->profiler)
      ));
      $queryAnalyzer->setTemplate('QueryAnalyzer');
      return $queryAnalyzer;
    }

}

class Profiles{
  private $profiler;

  public function __construct($profiler){
    $this->profiler = $profiler;
    $this->profiles = $this->decorateProfiles();
  }

  public function getTotalExecutionTime(){
    return $this->profiler->getTotalExecutionTime();
  }

  public function getRoutingTrace(){
    return $this->profiler->getRoutingTrace();
  }

  public function getProfiles(){
    return $this->profiles;
  }

  public function getQueryCount(){
    return count($this->profiles);
  }

  private function decorateProfiles(){
    return array_map(function($profile){
      return new ProfileData($profile);
    }, $this->profiler->getProfiles());
  }
}

class ProfileData{
  private $applicationTrace = array();
  private $fullBacktrace = array();
  private $sql;
  private $parameters;
  private $start;
  private $end;
  private $elapse;

  public function __construct($data = array()){
    $this->applicationTrace = $data['applicationTrace'];
    $this->fullBacktrace = $data['fullBacktrace'];
    $this->sql = $data['sql'];
    $this->parameters = $data['parameters'];
    $this->start = $data['start'];
    $this->end = $data['end'];
    $this->elapse = $data['elapse'];
  }

  public function getExecutionTime(){
    return round($this->elapse * 1000, 3);
  }

  public function getSql(){
   return trim($this->sql);
  }

  public function hasParameters(){
   return (isset($this->parameters) && count($this->getParameters()) > 0);
  }

  public function getParameters(){
   return $this->parameters->getNamedArray();
  }
}
