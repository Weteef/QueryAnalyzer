<?php

namespace QueryAnalyzer\Db\Adapter\Profiler;

use Zend\Db\Adapter\Profiler\Profiler;
use Zend\Db\Adapter\StatementContainerInterface;

class QueryAnalyzerProfiler extends Profiler{

    protected $routingTrace = null;

    protected $applicationTrace = array();

    protected $fullBacktrace = array();

    /**
     * @param string|StatementContainerInterface $target
     * @throws \Zend\Db\Adapter\Exception\InvalidArgumentException
     * @return this
     */
    public function profilerStart($target){
        $this->buildTraces(debug_backtrace());

        parent::profilerStart($target);

        $this->profiles[$this->currentIndex]['applicationTrace'] = $this->applicationTrace;
        $this->profiles[$this->currentIndex]['fullBacktrace'] = $this->fullBacktrace;

        return $this;
    }

    /**
     * @return this
     */
    public function profilerFinish(){
        $this->resetTraces();
        parent::profilerFinish();

        return $this;
    }

    public function getRoutingTrace(){
        return $this->routingTrace;
    }

    public function setRoutingTrace($trace){
      $this->routingTrace = $trace;

      return $this;
    }

    private function buildTraces($backtrace){
        foreach($backtrace as $caller){
            if($this->hasClass($caller)){
                $string = $caller['class'].$caller['type'].$caller['function'];

                if($this->hasLineNumber($caller)){
                  $string .='[Line: '.$caller['line'].']';
                }

                if($this->noFrameworkClass($caller)){
                    $this->applicationTrace[] = $string;
                }

                $this->fullBacktrace[] = $string;
            }
        }
    }

    private function noFrameworkClass($caller){
      return (strpos($caller['class'], "Zend") === false && strpos($caller['class'], "QueryAnalyzerProfiler") === false);
    }

    private function hasClass($caller){
      return isset($caller['class']);
    }

    private function hasLineNumber($caller){
      return isset($caller['line']);
    }

    private function resetTraces(){
        $this->applicationTrace = array();
        $this->fullBacktrace = array();
    }
}
