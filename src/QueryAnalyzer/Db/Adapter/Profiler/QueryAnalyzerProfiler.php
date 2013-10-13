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
        $current = &$this->profiles[$this->currentIndex];
        $current['end'] = microtime(true);
        $current['elapse'] = $current['end'] - $current['start'];
        $this->totalExecutiontime += round($current['elapse'] * 1000, 3);
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
            $traceEntry = array();
            if($this->hasClass($caller)){
                $traceEntry['function'] = $caller['class'].$caller['type'].$caller['function'].'()';

                if($this->hasFileEntry($caller)){
                    $filename = substr (strrchr ($caller['file'], "\\"), 1);
                    $traceEntry['file'] = $filename;
                }else{
                    $traceEntry['file'] = "not traceable";
                }

                if($this->hasLineNumber($caller)){
                    $traceEntry['line'] = $caller['line'];
                }else{
                    $traceEntry['line'] = "not traceable";
                }

                if($this->noFrameworkClass($caller)){
                    $this->applicationTrace[] = $traceEntry;
                }

                $this->fullBacktrace[] = $traceEntry;
            }
        }
    }

    private function hasFileEntry($caller){
        return isset($caller['file']);
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
