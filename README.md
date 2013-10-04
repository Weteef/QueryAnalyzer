QueryAnalyzer
=============

##Setup
- Attach Profiler to your DB-Adapter.
* for example the QueryAnalyerProfiler().
    $serviceManager
      ->get('Zend\Db\Adapter\Adapter')
      ->setProfiler(new QueryAnalyzerProfiler());


