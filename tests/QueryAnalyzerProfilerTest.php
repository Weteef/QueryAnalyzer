<?php

require_once('src/QueryAnalyzer/Db/Adapter/Profiler/QueryAnalyzerProfiler.php');

use QueryAnalyzer\Db\Adapter\Profiler\QueryAnalyzerProfiler;

class QueryAnalyzerProfilerTest extends PHPUnit_Framework_TestCase{
  private $analyzer;

  protected function setUp(){
    $this->analyzer = new QueryAnalyzerProfiler();
  }

  public function testProfilerStartReturnsSelf(){
   $this->assertEquals($this->analyzer->profilerStart(""), $this->analyzer);
  }

  public function testSetRoutingTraceReturnsSelf(){
   $this->assertEquals($this->analyzer->setRoutingTrace(""), $this->analyzer);
  }

  public function testProfilerFinishReturnsSelf(){
   $this->analyzer->profilerStart("");
   $this->assertEquals($this->analyzer->profilerFinish(), $this->analyzer);
  }
}
