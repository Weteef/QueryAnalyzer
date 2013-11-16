<?php

namespace QueryAnalyzer\Db\Adapter\Profiler;

use Zend\Db\Adapter\Profiler\ProfilerInterface;
use Zend\Db\Adapter\StatementContainerInterface;

class QueryAnalyzerProfiler implements ProfilerInterface{
    protected $applicationTrace = array();

    protected $fullBacktrace = array();

    protected $totalExecutiontime = 0;

    /**
     * @var array
     */
    protected $profiles = array();

    /**
     * @var null
     */
    protected $currentIndex = 0;

    /**
     * @param string|StatementContainerInterface $target
     * @return $this
     * @throws \Zend\Db\Adapter\Exception\InvalidArgumentException
     */
    public function profilerStart($target){
        $this->buildTraces();

        $profileInformation = array(
            'sql' => '',
            'parameters' => null,
            'start' => microtime(true),
            'end' => null,
            'elapse' => null,
            'applicationTrace' => $this->applicationTrace,
            'fullBacktrace' => $this->fullBacktrace
        );
        if ($target instanceof StatementContainerInterface) {
            $profileInformation['sql'] = $target->getSql();
            $profileInformation['parameters'] = clone $target->getParameterContainer();
        } elseif (is_string($target)) {
            $profileInformation['sql'] = $target;
        } else {
            throw new \Zend\Db\Adapter\Exception\InvalidArgumentException(__FUNCTION__ . ' takes either a StatementContainer or a string');
        }

        $this->profiles[$this->currentIndex] = $profileInformation;

        return $this;
    }

    /**
     * @return $this
     * @throws \Zend\Db\Adapter\Exception\RuntimeException
     */
    public function profilerFinish(){
        if (!isset($this->profiles[$this->currentIndex])) {
            throw new \Zend\Db\Adapter\Exception\RuntimeException('A profile must be started before ' . __FUNCTION__ . ' can be called.');
        }
        $this->applicationTrace = array();
        $this->fullBacktrace = array();
        $current = &$this->profiles[$this->currentIndex];
        $current['end'] = microtime(true);
        $current['elapse'] = $current['end'] - $current['start'];
        $this->totalExecutiontime += round($current['elapse'] * 1000, 3);
        $this->currentIndex++;
        return $this;
    }

    /**
     * @return int
     */
    public function getTotalExecutionTime(){
        return $this->totalExecutiontime;
    }

    /**
     * @return $this
     */
    private function buildTraces(){
        $backtrace = debug_backtrace();

        foreach($backtrace as $caller){
            $traceEntry = array();
            if(isset($caller['class'])){
                $traceEntry['function'] = $caller['class'].$caller['type'].$caller['function'].'()';

                if(isset($caller['file'])){
                    $filename = substr (strrchr ($caller['file'], "\\"), 1);
                    $traceEntry['file'] = $filename;
                }else{
                    $traceEntry['file'] = "not traceable";
                }

                if(isset($caller['line'])){
                    $traceEntry['line'] = $caller['line'];
                }else{
                    $traceEntry['line'] = "not traceable";
                }

                if($this->isUserClass($caller)){
                    $this->applicationTrace[] = $traceEntry;
                    $traceEntry['applicationTrace'] = true;
                }else{
                    $traceEntry['applicationTrace'] = false;
                }

                $this->fullBacktrace[] = $traceEntry;
            }
        }

        return $this;
    }

    /**
     * @param array $caller
     * @return bool
     */
    private function isUserClass($caller){
      return (strpos($caller['class'], "Zend") === false && strpos($caller['class'], "QueryAnalyzerProfiler") === false);
    }

    /**
     * @param mixed $order
     * @return array
     */
    public function getProfiles($order = false)
    {
        if(is_string($order)){
            $order = strtolower($order);
            if(strcmp($order, 'asc')){
                return $this->sortProfilesByExecutionTimeASC($this->profiles);
            }elseif(strcmp($order, 'desc')){
                return $this->sortProfilesByExecutionTimeDESC($this->profiles);
            }
        }

        return $this->profiles;
    }

    /**
     * @param array $profiles
     * @return array
     */
    private function sortProfilesByExecutionTimeASC($profiles){
        usort($profiles, function($a, $b){
            if($a['elapse'] == $b['elapse'])
                return 0;

            return ($a['elapse'] < $b['elapse']) ? 1 : -1;
        });

        return $profiles;
    }

    /**
     * @param array $profiles
     * @return array
     */
    private function sortProfilesByExecutionTimeDESC($profiles){
        usort($profiles, function($a, $b){
            if($a['elapse'] == $b['elapse'])
                return 0;

            return ($a['elapse'] > $b['elapse']) ? 1 : -1;
        });

        return $profiles;
    }
}
