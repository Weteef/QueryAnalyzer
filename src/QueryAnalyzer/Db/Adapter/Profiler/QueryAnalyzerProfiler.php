<?php

namespace QueryAnalyzer\Db\Adapter\Profiler;

use Zend\Db\Adapter\Profiler\Profiler;
use Zend\Db\Adapter\StatementContainerInterface;

class QueryAnalyzerProfiler extends Profiler{

    protected $routingTrace = null;

    protected $applicationTrace = array();

    protected $fullBacktrace = array();

    protected $totalExecutiontime = 0;

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
     * @return Profiler
     */
    public function profilerFinish(){
        $this->resetTraces();

        if (!isset($this->profiles[$this->currentIndex])) {
            throw new Exception\RuntimeException('A profile must be started before ' . __FUNCTION__ . ' can be called.');
        }
        $this->calculateTotalExectutionTime();
        $this->currentIndex++;
        return $this;
    }


    public function getTotalExecutionTime(){
        return $this->totalExecutiontime;
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
                $trace = $caller['class'].$caller['type'].$caller['function'];

                if($this->hasLineNumber($caller)){
                    $trace .='[Line: '.$caller['line'].']';
                }

                if($this->noFrameworkClass($caller)){
                    $this->applicationTrace[] = $trace;
                }

                $this->fullBacktrace[] = $trace;
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

    private function calculateTotalExectutionTime(){
        $current = &$this->profiles[$this->currentIndex];
        $current['end'] = microtime(true);
        $current['elapse'] = $current['end'] - $current['start'];
        $this->totalExecutiontime += round($current['elapse'] * 1000, 3);
    }
}
